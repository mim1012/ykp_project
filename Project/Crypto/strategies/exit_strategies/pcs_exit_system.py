"""
PCS (Price Channel System) 3단계 청산 시스템

PRD 및 API 문서 명세에 따른 정교한 3단계 청산 시스템 구현.
각 단계별로 다른 청산 조건과 비율을 적용하여 리스크를 점진적으로 관리.
"""

from typing import Dict, List, Optional, Tuple, Any
from enum import Enum
from dataclasses import dataclass, field
from datetime import datetime, timedelta
import asyncio
import logging
import pandas as pd
import numpy as np
from decimal import Decimal

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(__file__))))

from core.logger import SystemLogger
from core.constants import PCSConstants, PerformanceTargets
from core.exceptions import PCSError, InvalidPCSStageError, PCSExecutionError
from .price_channel_calculator import PriceChannelCalculator, PriceChannelConfig
from .trend_reversal_detector import TrendReversalDetector, ReversalPattern

logger = SystemLogger()


class PCSStage(Enum):
    """PCS 청산 단계"""
    STAGE_1 = "1단"
    STAGE_2 = "2단" 
    STAGE_3 = "3단"
    COMPLETED = "완료"


@dataclass
class PCSConfig:
    """PCS 청산 시스템 설정"""
    
    # 1단 청산 설정 (초기 수익 확보)
    stage1_profit_threshold: float = PCSConstants.STAGE1_PROFIT_THRESHOLD
    stage1_exit_ratio: float = PCSConstants.STAGE1_EXIT_RATIO
    
    # 2단 청산 설정 (채널 이탈 감지)
    stage2_channel_break_threshold: float = PCSConstants.STAGE2_CHANNEL_BREAK_THRESHOLD
    stage2_exit_ratio: float = PCSConstants.STAGE2_EXIT_RATIO
    
    # 3단 청산 설정 (추세 반전 감지)
    stage3_reversal_candles: int = PCSConstants.STAGE3_REVERSAL_CANDLES
    stage3_exit_ratio: float = PCSConstants.STAGE3_EXIT_RATIO
    
    # 공통 설정
    channel_period: int = 20  # Price Channel 계산 기간
    min_holding_time_minutes: int = 5  # 최소 보유 시간
    
    # 리스크 관리 설정
    breakeven_after_stage1: bool = True  # 1단 청산 후 무손실 설정
    trailing_stop_after_stage2: bool = True  # 2단 청산 후 트레일링 적용
    trailing_stop_percent: float = 0.02  # 2% 트레일링 스톱


@dataclass
class PCSPosition:
    """PCS 청산 대상 포지션"""
    
    symbol: str
    entry_price: float
    original_quantity: float
    current_quantity: float
    entry_time: datetime
    direction: str  # 'BUY' or 'SELL'
    
    # 단계별 실행 상태
    current_stage: PCSStage = PCSStage.STAGE_1
    stage1_executed: bool = False
    stage2_executed: bool = False
    stage3_executed: bool = False
    
    # 청산 기록
    stage1_liquidation_time: Optional[datetime] = None
    stage2_liquidation_time: Optional[datetime] = None
    stage3_liquidation_time: Optional[datetime] = None
    
    stage1_liquidation_price: Optional[float] = None
    stage2_liquidation_price: Optional[float] = None
    stage3_liquidation_price: Optional[float] = None
    
    stage1_pnl: Optional[float] = None
    stage2_pnl: Optional[float] = None
    stage3_pnl: Optional[float] = None
    
    # 리스크 관리
    current_stop_loss: Optional[float] = None
    trailing_stop_active: bool = False
    
    @property
    def remaining_ratio(self) -> float:
        """잔여 포지션 비율"""
        return self.current_quantity / self.original_quantity if self.original_quantity > 0 else 0
    
    @property
    def holding_time_minutes(self) -> float:
        """보유 시간 (분)"""
        return (datetime.now() - self.entry_time).total_seconds() / 60
    
    @property
    def total_liquidated_quantity(self) -> float:
        """총 청산된 수량"""
        return self.original_quantity - self.current_quantity
    
    @property
    def liquidation_progress(self) -> str:
        """청산 진행 상황"""
        if self.stage3_executed:
            return "완전청산"
        elif self.stage2_executed:
            return f"2단계청산({100-self.remaining_ratio*100:.1f}%)"
        elif self.stage1_executed:
            return f"1단계청산({100-self.remaining_ratio*100:.1f}%)"
        else:
            return "청산대기"
    
    def calculate_unrealized_pnl(self, current_price: float) -> float:
        """미실현 손익 계산"""
        if self.direction == 'BUY':
            return (current_price - self.entry_price) * self.current_quantity
        else:
            return (self.entry_price - current_price) * self.current_quantity
    
    def calculate_unrealized_pnl_percent(self, current_price: float) -> float:
        """미실현 손익률 계산"""
        if self.direction == 'BUY':
            return (current_price - self.entry_price) / self.entry_price
        else:
            return (self.entry_price - current_price) / self.entry_price
    
    def to_dict(self) -> Dict[str, Any]:
        """딕셔너리 변환 (API 응답용)"""
        return {
            'symbol': self.symbol,
            'entry_price': self.entry_price,
            'original_quantity': self.original_quantity,
            'current_quantity': self.current_quantity,
            'entry_time': self.entry_time.isoformat(),
            'direction': self.direction,
            'current_stage': self.current_stage.value,
            'liquidation_progress': self.liquidation_progress,
            'remaining_ratio': self.remaining_ratio,
            'holding_time_minutes': self.holding_time_minutes,
            'stage1_executed': self.stage1_executed,
            'stage2_executed': self.stage2_executed,
            'stage3_executed': self.stage3_executed,
            'current_stop_loss': self.current_stop_loss,
            'trailing_stop_active': self.trailing_stop_active
        }


class PCSExitSystem:
    """PCS 청산 시스템 메인 클래스"""
    
    def __init__(self, config: PCSConfig, api_connector):
        self.config = config
        self.api_connector = api_connector
        self.positions: Dict[str, PCSPosition] = {}
        
        # 지원 모듈들
        self.price_channel_calculator = PriceChannelCalculator(
            PriceChannelConfig(period=config.channel_period)
        )
        self.trend_reversal_detector = TrendReversalDetector()
        self.exit_executor = PCSExitExecutor(api_connector, config)
        self.performance_analyzer = PCSPerformanceAnalyzer()
        
        logger.info(f"PCS 청산 시스템 초기화 완료: {config.channel_period}일 채널")
    
    def add_position(self, symbol: str, entry_price: float, quantity: float, direction: str) -> str:
        """새 포지션 추가"""
        position_id = f"{symbol}_{int(datetime.now().timestamp())}"
        
        position = PCSPosition(
            symbol=symbol,
            entry_price=entry_price,
            original_quantity=quantity,
            current_quantity=quantity,
            entry_time=datetime.now(),
            direction=direction
        )
        
        self.positions[position_id] = position
        
        logger.info(f"PCS 포지션 추가: {symbol} {direction} {quantity:.6f} @ {entry_price:.2f}")
        return position_id
    
    async def evaluate_all_positions(self, market_data: Dict[str, pd.DataFrame]) -> List[Dict]:
        """모든 포지션의 청산 조건 평가"""
        exit_signals = []
        
        for position_id, position in self.positions.items():
            if position.current_quantity <= 0:
                continue
                
            # 최소 보유 시간 확인
            if position.holding_time_minutes < self.config.min_holding_time_minutes:
                continue
            
            symbol_data = market_data.get(position.symbol)
            if symbol_data is None or len(symbol_data) < self.config.channel_period:
                continue
            
            # 현재 가격 및 수익률 계산
            current_price = symbol_data.iloc[-1]['close']
            profit_ratio = position.calculate_unrealized_pnl_percent(current_price)
            
            # 단계별 청산 조건 평가
            stage_signals = await self._evaluate_position_stages(
                position_id, position, symbol_data, current_price, profit_ratio
            )
            
            exit_signals.extend(stage_signals)
        
        return exit_signals
    
    async def _evaluate_position_stages(
        self, 
        position_id: str, 
        position: PCSPosition, 
        market_data: pd.DataFrame,
        current_price: float,
        profit_ratio: float
    ) -> List[Dict]:
        """포지션별 단계 청산 조건 평가"""
        
        signals = []
        
        # 1단 청산 조건 확인
        if not position.stage1_executed:
            stage1_signal = self._evaluate_stage1_exit(position, profit_ratio, current_price)
            if stage1_signal:
                stage1_signal['position_id'] = position_id
                signals.append(stage1_signal)
        
        # 2단 청산 조건 확인  
        elif position.stage1_executed and not position.stage2_executed:
            stage2_signal = await self._evaluate_stage2_exit(position, market_data, current_price)
            if stage2_signal:
                stage2_signal['position_id'] = position_id
                signals.append(stage2_signal)
        
        # 3단 청산 조건 확인
        elif position.stage2_executed and not position.stage3_executed:
            stage3_signal = self._evaluate_stage3_exit(position, market_data, current_price)
            if stage3_signal:
                stage3_signal['position_id'] = position_id
                signals.append(stage3_signal)
        
        return signals
    
    def _evaluate_stage1_exit(self, position: PCSPosition, profit_ratio: float, current_price: float) -> Optional[Dict]:
        """1단 청산 조건 평가 (2% 수익 달성)"""
        
        if profit_ratio >= self.config.stage1_profit_threshold:
            exit_quantity = position.original_quantity * self.config.stage1_exit_ratio
            
            return {
                'stage': PCSStage.STAGE_1,
                'symbol': position.symbol,
                'exit_quantity': exit_quantity,
                'exit_ratio': self.config.stage1_exit_ratio,
                'current_price': current_price,
                'profit_ratio': profit_ratio,
                'reason': f'{self.config.stage1_profit_threshold*100}% 수익 달성',
                'urgency': 'MEDIUM',
                'order_type': 'LIMIT'  # 1단은 지정가로 여유롭게
            }
        
        return None
    
    async def _evaluate_stage2_exit(
        self, 
        position: PCSPosition, 
        market_data: pd.DataFrame,
        current_price: float
    ) -> Optional[Dict]:
        """2단 청산 조건 평가 (Price Channel 이탈 감지)"""
        
        try:
            # Price Channel 계산
            channel_info = self.price_channel_calculator.calculate_channel(market_data)
            
            # 채널 이탈 확인
            channel_break = False
            break_reason = ""
            
            if position.direction == 'BUY':
                # 매수 포지션: 상단 채널 아래로 이탈
                upper_threshold = channel_info['upper_channel'] * (1 - self.config.stage2_channel_break_threshold)
                if current_price < upper_threshold:
                    channel_break = True
                    break_reason = f"상단 채널({channel_info['upper_channel']:.2f}) 아래로 이탈"
            else:
                # 매도 포지션: 하단 채널 위로 이탈
                lower_threshold = channel_info['lower_channel'] * (1 + self.config.stage2_channel_break_threshold)
                if current_price > lower_threshold:
                    channel_break = True
                    break_reason = f"하단 채널({channel_info['lower_channel']:.2f}) 위로 이탈"
            
            if channel_break:
                exit_quantity = position.current_quantity * self.config.stage2_exit_ratio
                
                return {
                    'stage': PCSStage.STAGE_2,
                    'symbol': position.symbol,
                    'exit_quantity': exit_quantity,
                    'exit_ratio': self.config.stage2_exit_ratio,
                    'current_price': current_price,
                    'reason': break_reason,
                    'channel_info': channel_info,
                    'urgency': 'HIGH',
                    'order_type': 'LIMIT'  # 2단도 지정가 우선
                }
        
        except Exception as e:
            logger.error(f"2단 청산 조건 평가 오류 ({position.symbol}): {e}")
        
        return None
    
    def _evaluate_stage3_exit(
        self, 
        position: PCSPosition, 
        market_data: pd.DataFrame,
        current_price: float
    ) -> Optional[Dict]:
        """3단 청산 조건 평가 (추세 반전 패턴 감지)"""
        
        if len(market_data) < self.config.stage3_reversal_candles:
            return None
        
        # 최근 캔들들 분석
        recent_candles = market_data.tail(self.config.stage3_reversal_candles)
        
        # 추세 반전 패턴 감지
        reversal_result = self.trend_reversal_detector.detect_reversal_pattern(
            position, recent_candles
        )
        
        if reversal_result['reversal_detected']:
            return {
                'stage': PCSStage.STAGE_3,
                'symbol': position.symbol,
                'exit_quantity': position.current_quantity,
                'exit_ratio': self.config.stage3_exit_ratio,
                'current_price': current_price,
                'reason': f'추세 반전 패턴 감지: {reversal_result["pattern"]}',
                'reversal_pattern': reversal_result,
                'urgency': 'CRITICAL',
                'order_type': 'MARKET'  # 3단은 즉시 시장가 청산
            }
        
        return None
    
    async def execute_pcs_exit(self, exit_signal: Dict) -> Dict[str, Any]:
        """PCS 청산 실행"""
        position_id = exit_signal['position_id']
        position = self.positions.get(position_id)
        
        if not position:
            return {'success': False, 'error': f'포지션 {position_id} 찾을 수 없음'}
        
        try:
            # 청산 주문 실행
            execution_result = await self.exit_executor.execute_exit_order(exit_signal, position)
            
            if execution_result['success']:
                # 포지션 상태 업데이트
                await self._update_position_after_exit(position, exit_signal, execution_result)
                
                # 청산 후 설정 적용
                if position.current_quantity > 0:
                    await self._apply_post_exit_settings(position, exit_signal)
                
                # 성과 기록
                self.performance_analyzer.record_exit_execution(exit_signal, execution_result)
                
                logger.info(f"PCS {exit_signal['stage'].value} 청산 완료: "
                          f"{position.symbol} {exit_signal['exit_quantity']:.6f} @ {execution_result['exit_price']:.2f}")
                
                return {
                    'success': True,
                    'position_id': position_id,
                    'stage': exit_signal['stage'],
                    'exit_quantity': exit_signal['exit_quantity'],
                    'exit_price': execution_result['exit_price'],
                    'remaining_quantity': position.current_quantity,
                    'execution_result': execution_result
                }
            else:
                logger.error(f"PCS 청산 실패: {execution_result.get('error', 'Unknown error')}")
                return {'success': False, 'error': execution_result.get('error')}
        
        except Exception as e:
            logger.error(f"PCS 청산 실행 중 예외 발생: {e}")
            return {'success': False, 'error': str(e)}
    
    async def _update_position_after_exit(
        self, 
        position: PCSPosition, 
        exit_signal: Dict, 
        execution_result: Dict
    ):
        """청산 후 포지션 상태 업데이트"""
        
        # 포지션 수량 업데이트
        position.current_quantity -= exit_signal['exit_quantity']
        
        # 단계별 실행 상태 및 기록 업데이트
        stage = exit_signal['stage']
        exit_price = execution_result['exit_price']
        
        if stage == PCSStage.STAGE_1:
            position.stage1_executed = True
            position.stage1_liquidation_time = datetime.now()
            position.stage1_liquidation_price = exit_price
            position.stage1_pnl = self._calculate_stage_pnl(position, exit_signal, exit_price)
            position.current_stage = PCSStage.STAGE_2
            
        elif stage == PCSStage.STAGE_2:
            position.stage2_executed = True
            position.stage2_liquidation_time = datetime.now()
            position.stage2_liquidation_price = exit_price
            position.stage2_pnl = self._calculate_stage_pnl(position, exit_signal, exit_price)
            position.current_stage = PCSStage.STAGE_3
            
        elif stage == PCSStage.STAGE_3:
            position.stage3_executed = True
            position.stage3_liquidation_time = datetime.now()
            position.stage3_liquidation_price = exit_price
            position.stage3_pnl = self._calculate_stage_pnl(position, exit_signal, exit_price)
            position.current_stage = PCSStage.COMPLETED
    
    def _calculate_stage_pnl(self, position: PCSPosition, exit_signal: Dict, exit_price: float) -> float:
        """단계별 손익 계산"""
        if position.direction == 'BUY':
            return (exit_price - position.entry_price) * exit_signal['exit_quantity']
        else:
            return (position.entry_price - exit_price) * exit_signal['exit_quantity']
    
    async def _apply_post_exit_settings(self, position: PCSPosition, exit_signal: Dict):
        """청산 후 설정 적용"""
        
        stage = exit_signal['stage']
        
        if stage == PCSStage.STAGE_1 and self.config.breakeven_after_stage1:
            # 1단 청산 후: 무손실 손절가 설정
            await self._set_breakeven_stop_loss(position)
            
        elif stage == PCSStage.STAGE_2 and self.config.trailing_stop_after_stage2:
            # 2단 청산 후: 트레일링 스톱 설정
            await self._set_trailing_stop(position)
    
    async def _set_breakeven_stop_loss(self, position: PCSPosition):
        """무손실 손절가 설정"""
        position.current_stop_loss = position.entry_price
        
        # API를 통한 손절 주문 설정
        await self.exit_executor.update_stop_loss_order(position, position.entry_price)
        
        logger.info(f"무손실 손절가 설정: {position.symbol} @ {position.entry_price:.2f}")
    
    async def _set_trailing_stop(self, position: PCSPosition):
        """트레일링 스톱 설정"""
        position.trailing_stop_active = True
        
        # 현재 가격 기준 트레일링 스톱 계산
        current_price = exit_signal.get('current_price', position.entry_price)
        trailing_distance = current_price * self.config.trailing_stop_percent
        
        if position.direction == 'BUY':
            trailing_stop_price = current_price - trailing_distance
        else:
            trailing_stop_price = current_price + trailing_distance
        
        position.current_stop_loss = trailing_stop_price
        
        logger.info(f"트레일링 스톱 설정: {position.symbol} {self.config.trailing_stop_percent*100:.1f}% @ {trailing_stop_price:.2f}")
    
    def get_position_summary(self) -> Dict[str, Any]:
        """포지션 요약 정보"""
        active_positions = [pos for pos in self.positions.values() if pos.current_quantity > 0]
        
        return {
            'total_positions': len(active_positions),
            'stage1_positions': len([p for p in active_positions if p.current_stage == PCSStage.STAGE_1]),
            'stage2_positions': len([p for p in active_positions if p.current_stage == PCSStage.STAGE_2]),
            'stage3_positions': len([p for p in active_positions if p.current_stage == PCSStage.STAGE_3]),
            'positions': [pos.to_dict() for pos in active_positions]
        }


class PCSExitExecutor:
    """PCS 청산 주문 실행"""
    
    def __init__(self, api_connector, config: PCSConfig):
        self.api_connector = api_connector
        self.config = config
        self.execution_history = []
    
    async def execute_exit_order(self, exit_signal: Dict, position: PCSPosition) -> Dict[str, Any]:
        """청산 주문 실행"""
        
        try:
            # 주문 파라미터 계산
            order_params = self._calculate_exit_order_params(exit_signal, position)
            
            # 거래소 API를 통한 주문 실행
            if exit_signal['urgency'] == 'CRITICAL':
                # 긴급 상황: 즉시 시장가 청산
                order_result = await self.api_connector.place_market_order(
                    order_params['symbol'],
                    order_params['side'],
                    order_params['quantity']
                )
            else:
                # 일반 상황: 지정가 청산
                order_result = await self.api_connector.place_limit_order(
                    order_params['symbol'],
                    order_params['side'], 
                    order_params['quantity'],
                    order_params['price']
                )
            
            # 실행 기록 저장
            execution_record = {
                'timestamp': datetime.now(),
                'position_id': exit_signal.get('position_id'),
                'stage': exit_signal['stage'],
                'symbol': position.symbol,
                'exit_quantity': exit_signal['exit_quantity'],
                'exit_price': order_result.get('price', exit_signal['current_price']),
                'order_id': order_result.get('orderId'),
                'urgency': exit_signal['urgency'],
                'reason': exit_signal['reason']
            }
            
            self.execution_history.append(execution_record)
            
            return {
                'success': True,
                'exit_price': order_result.get('price', exit_signal['current_price']),
                'order_id': order_result.get('orderId'),
                'execution_record': execution_record
            }
        
        except Exception as e:
            logger.error(f"청산 주문 실행 실패: {e}")
            return {'success': False, 'error': str(e)}
    
    def _calculate_exit_order_params(self, exit_signal: Dict, position: PCSPosition) -> Dict[str, Any]:
        """청산 주문 파라미터 계산"""
        
        # 청산 방향 결정 (진입과 반대)
        exit_side = 'SELL' if position.direction == 'BUY' else 'BUY'
        
        # 기본 주문 파라미터
        order_params = {
            'symbol': position.symbol,
            'side': exit_side,
            'quantity': exit_signal['exit_quantity']
        }
        
        # 주문 타입별 가격 설정
        if exit_signal.get('order_type') == 'LIMIT':
            # 지정가: 현재가에서 유리한 방향으로 조정
            if exit_side == 'SELL':
                order_params['price'] = exit_signal['current_price'] * 0.999  # 0.1% 낮게
            else:
                order_params['price'] = exit_signal['current_price'] * 1.001  # 0.1% 높게
        
        return order_params
    
    async def update_stop_loss_order(self, position: PCSPosition, new_stop_price: float):
        """손절 주문 업데이트"""
        try:
            # 기존 손절 주문 취소 (구현 필요)
            # await self.api_connector.cancel_stop_loss_orders(position.symbol)
            
            # 새로운 손절 주문 생성
            stop_side = 'SELL' if position.direction == 'BUY' else 'BUY'
            
            stop_order_result = await self.api_connector.place_stop_loss_order(
                position.symbol,
                stop_side,
                position.current_quantity,
                new_stop_price
            )
            
            logger.info(f"손절 주문 업데이트: {position.symbol} @ {new_stop_price:.2f}")
            return stop_order_result
            
        except Exception as e:
            logger.error(f"손절 주문 업데이트 실패: {e}")
            raise


class PCSPerformanceAnalyzer:
    """PCS 성과 분석기"""
    
    def __init__(self):
        self.execution_records = []
        self.performance_data = []
    
    def record_exit_execution(self, exit_signal: Dict, execution_result: Dict):
        """청산 실행 기록"""
        record = {
            'timestamp': datetime.now(),
            'stage': exit_signal['stage'].value,
            'symbol': exit_signal['symbol'],
            'exit_quantity': exit_signal['exit_quantity'],
            'exit_price': execution_result['exit_price'],
            'reason': exit_signal['reason'],
            'urgency': exit_signal['urgency'],
            'success': execution_result['success']
        }
        
        self.execution_records.append(record)
    
    def analyze_pcs_performance(self) -> Dict[str, Any]:
        """PCS 시스템 성과 분석"""
        
        if not self.execution_records:
            return {'message': 'No execution data available'}
        
        # 단계별 성과 분석
        stage_performance = self._analyze_stage_performance()
        
        # 전체 성과 지표
        overall_performance = self._analyze_overall_performance()
        
        # 최적화 제안
        optimization_suggestions = self._generate_optimization_suggestions(stage_performance)
        
        return {
            'stage_performance': stage_performance,
            'overall_performance': overall_performance,
            'optimization_suggestions': optimization_suggestions,
            'total_executions': len(self.execution_records),
            'analysis_timestamp': datetime.now().isoformat()
        }
    
    def _analyze_stage_performance(self) -> Dict[str, Any]:
        """단계별 성과 분석"""
        stages = ['1단', '2단', '3단']
        stage_stats = {}
        
        for stage in stages:
            stage_records = [r for r in self.execution_records if r['stage'] == stage]
            
            if stage_records:
                success_rate = len([r for r in stage_records if r['success']]) / len(stage_records)
                avg_profit = np.mean([r.get('pnl', 0) for r in stage_records])
                
                stage_stats[stage] = {
                    'execution_count': len(stage_records),
                    'success_rate': success_rate,
                    'average_profit': avg_profit,
                    'last_execution': stage_records[-1]['timestamp'].isoformat()
                }
            else:
                stage_stats[stage] = {
                    'execution_count': 0,
                    'success_rate': 0,
                    'average_profit': 0,
                    'last_execution': None
                }
        
        return stage_stats
    
    def _analyze_overall_performance(self) -> Dict[str, Any]:
        """전체 성과 분석"""
        successful_executions = [r for r in self.execution_records if r['success']]
        
        return {
            'total_executions': len(self.execution_records),
            'success_rate': len(successful_executions) / len(self.execution_records),
            'stage1_ratio': len([r for r in successful_executions if r['stage'] == '1단']) / len(successful_executions),
            'stage2_ratio': len([r for r in successful_executions if r['stage'] == '2단']) / len(successful_executions),
            'stage3_ratio': len([r for r in successful_executions if r['stage'] == '3단']) / len(successful_executions),
            'average_execution_time': self._calculate_average_execution_time()
        }
    
    def _generate_optimization_suggestions(self, stage_performance: Dict) -> List[str]:
        """최적화 제안 생성"""
        suggestions = []
        
        # 1단 청산 분석
        stage1_success = stage_performance.get('1단', {}).get('success_rate', 0)
        if stage1_success < 0.8:
            suggestions.append("1단 청산 임계값 조정 검토 필요 (현재 2%)")
        
        # 2단 청산 분석
        stage2_count = stage_performance.get('2단', {}).get('execution_count', 0)
        stage1_count = stage_performance.get('1단', {}).get('execution_count', 0)
        
        if stage1_count > 0 and stage2_count < stage1_count * 0.3:
            suggestions.append("2단 청산 조건 완화 검토 필요 (채널 이탈 임계값 0.5%)")
        
        # 3단 청산 분석
        stage3_count = stage_performance.get('3단', {}).get('execution_count', 0)
        if stage3_count > stage1_count * 0.5:
            suggestions.append("추세 반전 감지 조건 강화 필요 (현재 2캔들)")
        
        return suggestions
    
    def _calculate_average_execution_time(self) -> float:
        """평균 실행 시간 계산"""
        # 실행 시간 데이터가 있다면 계산 (구현 시 추가)
        return 0.0


# 모듈 익스포트를 위한 __all__ 정의
__all__ = [
    'PCSStage',
    'PCSConfig', 
    'PCSPosition',
    'PCSExitSystem',
    'PCSExitExecutor',
    'PCSPerformanceAnalyzer'
]