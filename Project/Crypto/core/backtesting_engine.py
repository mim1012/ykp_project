"""
Backtesting Engine

과거 데이터를 사용한 거래 전략 성과 검증 및 분석 시스템.
PRD 요구사항에 따른 신뢰할 수 있는 백테스팅 환경 제공.
"""

from typing import Dict, List, Optional, Tuple, Any
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from enum import Enum
import pandas as pd
import numpy as np
import asyncio
from concurrent.futures import ThreadPoolExecutor
import json

from .logger import SystemLogger
from .constants import RiskConstants, PerformanceTargets, ValidationConstants
from .exceptions import (
    BacktestError, InsufficientBacktestDataError, 
    InvalidSignalError, ParameterValidationError
)
from .trading_engine import TradingEngine
from .risk_manager import RiskManager

logger = SystemLogger.get_logger(__name__)


class BacktestMode(Enum):
    """백테스팅 모드"""
    STRATEGY_VALIDATION = "strategy_validation"  # 전략 검증
    PARAMETER_OPTIMIZATION = "parameter_optimization"  # 파라미터 최적화
    RISK_ANALYSIS = "risk_analysis"  # 리스크 분석
    PERFORMANCE_COMPARISON = "performance_comparison"  # 성과 비교


@dataclass
class BacktestConfig:
    """백테스팅 설정"""
    
    # === 기본 설정 ===
    start_date: str  # 'YYYY-MM-DD' 형식
    end_date: str
    initial_balance: float = 100000.0  # 초기 자본 (USDT)
    symbols: List[str] = field(default_factory=lambda: ['BTCUSDT', 'ETHUSDT'])
    timeframes: List[str] = field(default_factory=lambda: ['1h', '4h', '1d'])
    
    # === 거래 설정 ===
    commission_rate: float = 0.0004  # 0.04% 수수료
    slippage_rate: float = 0.001  # 0.1% 슬리피지
    leverage: int = 10  # 기본 레버리지
    
    # === 리스크 설정 ===
    max_drawdown_limit: float = 0.20  # 20% 최대 드로우다운
    daily_loss_limit: float = 0.05  # 5% 일일 손실 한도
    position_size_percent: float = 0.10  # 10% 포지션 크기
    
    # === 성능 설정 ===
    benchmark_symbol: str = 'BTCUSDT'  # 벤치마크 심볼
    report_frequency: str = 'daily'  # 리포트 주기
    
    def validate(self) -> bool:
        """설정 유효성 검증"""
        try:
            # 날짜 검증
            start_dt = datetime.strptime(self.start_date, '%Y-%m-%d')
            end_dt = datetime.strptime(self.end_date, '%Y-%m-%d')
            
            if start_dt >= end_dt:
                raise ValueError("시작일이 종료일보다 늦습니다")
            
            # 기간 검증 (최소 7일)
            if (end_dt - start_dt).days < 7:
                raise ValueError("백테스트 기간이 너무 짧습니다 (최소 7일)")
            
            # 파라미터 검증
            if self.initial_balance <= 0:
                raise ValueError("초기 자본이 0 이하입니다")
            
            if not (0 < self.commission_rate < 0.01):
                raise ValueError("수수료율이 범위를 벗어났습니다 (0~1%)")
                
            return True
            
        except Exception as e:
            logger.error(f"백테스트 설정 검증 실패: {e}")
            return False


@dataclass  
class BacktestPosition:
    """백테스트 포지션 정보"""
    
    symbol: str
    side: str  # 'BUY' or 'SELL'
    entry_price: float
    quantity: float
    entry_time: datetime
    exit_price: Optional[float] = None
    exit_time: Optional[datetime] = None
    exit_reason: Optional[str] = None
    
    commission_paid: float = 0.0
    slippage_cost: float = 0.0
    
    @property
    def is_open(self) -> bool:
        """포지션 열림 상태"""
        return self.exit_price is None
    
    @property
    def holding_time_hours(self) -> float:
        """보유 시간 (시간)"""
        if self.exit_time:
            return (self.exit_time - self.entry_time).total_seconds() / 3600
        return (datetime.now() - self.entry_time).total_seconds() / 3600
    
    @property
    def unrealized_pnl(self) -> float:
        """미실현 손익"""
        if not self.exit_price:
            return 0.0
        
        if self.side == 'BUY':
            return (self.exit_price - self.entry_price) * self.quantity
        else:
            return (self.entry_price - self.exit_price) * self.quantity
    
    @property
    def realized_pnl(self) -> float:
        """실현 손익 (수수료 및 슬리피지 제외)"""
        return self.unrealized_pnl - self.commission_paid - self.slippage_cost
    
    @property
    def return_percent(self) -> float:
        """수익률 (%)"""
        investment = self.entry_price * self.quantity
        if investment <= 0:
            return 0.0
        return (self.realized_pnl / investment) * 100


@dataclass
class BacktestResults:
    """백테스팅 결과"""
    
    # === 기본 정보 ===
    start_date: str
    end_date: str
    duration_days: int
    symbols_tested: List[str]
    
    # === 거래 통계 ===
    total_trades: int = 0
    winning_trades: int = 0
    losing_trades: int = 0
    win_rate: float = 0.0
    
    # === 수익성 지표 ===
    total_return: float = 0.0  # 총 수익률 (%)
    annualized_return: float = 0.0  # 연환산 수익률
    max_drawdown: float = 0.0  # 최대 낙폭 (%)
    sharpe_ratio: float = 0.0  # 샤프 비율
    
    # === 리스크 지표 ===
    volatility: float = 0.0  # 변동성 (%)
    var_95: float = 0.0  # 95% VaR
    max_consecutive_losses: int = 0
    
    # === 거래 세부사항 ===
    average_trade_duration_hours: float = 0.0
    average_winning_trade: float = 0.0
    average_losing_trade: float = 0.0
    profit_factor: float = 0.0
    
    # === 포지션 분석 ===
    positions: List[BacktestPosition] = field(default_factory=list)
    daily_returns: List[float] = field(default_factory=list)
    portfolio_values: List[Tuple[datetime, float]] = field(default_factory=list)
    
    def to_dict(self) -> Dict[str, Any]:
        """딕셔너리 변환 (보고서 생성용)"""
        return {
            'summary': {
                'period': f"{self.start_date} ~ {self.end_date}",
                'duration_days': self.duration_days,
                'symbols_tested': self.symbols_tested,
                'total_trades': self.total_trades,
                'win_rate': f"{self.win_rate:.1f}%",
                'total_return': f"{self.total_return:.2f}%",
                'max_drawdown': f"{self.max_drawdown:.2f}%",
                'sharpe_ratio': f"{self.sharpe_ratio:.2f}"
            },
            'performance': {
                'annualized_return': f"{self.annualized_return:.2f}%",
                'volatility': f"{self.volatility:.2f}%",
                'profit_factor': f"{self.profit_factor:.2f}",
                'var_95': f"{self.var_95:.2f}%",
                'max_consecutive_losses': self.max_consecutive_losses
            },
            'trading_stats': {
                'winning_trades': self.winning_trades,
                'losing_trades': self.losing_trades,
                'avg_trade_duration': f"{self.average_trade_duration_hours:.1f}h",
                'avg_winning_trade': f"{self.average_winning_trade:.2f}%",
                'avg_losing_trade': f"{self.average_losing_trade:.2f}%"
            },
            'positions_count': len(self.positions),
            'portfolio_snapshots': len(self.portfolio_values)
        }


class BacktestingEngine:
    """
    백테스팅 엔진
    
    과거 시장 데이터를 사용하여 거래 전략의 성과를 시뮬레이션하고
    다양한 성과 지표를 계산하여 신뢰할 수 있는 전략 검증을 제공.
    """
    
    def __init__(self, config: BacktestConfig):
        self.config = config
        self.current_balance = config.initial_balance
        self.positions: List[BacktestPosition] = []
        self.closed_positions: List[BacktestPosition] = []
        
        # 성과 추적
        self.portfolio_values: List[Tuple[datetime, float]] = []
        self.daily_returns: List[float] = []
        
        # 시뮬레이션 상태
        self.current_time: Optional[datetime] = None
        self.simulation_running = False
        
        logger.info(f"백테스팅 엔진 초기화: {config.start_date} ~ {config.end_date}")
    
    async def run_backtest(self, strategy_config: Dict, market_data: Dict[str, pd.DataFrame]) -> BacktestResults:
        """
        백테스트 실행
        
        Args:
            strategy_config: 전략 설정
            market_data: {symbol: DataFrame} 형태의 시장 데이터
            
        Returns:
            BacktestResults: 백테스트 결과
        """
        try:
            logger.info("백테스트 실행 시작...")
            
            # 데이터 검증
            self._validate_market_data(market_data)
            
            # 시뮬레이션 초기화
            await self._initialize_simulation(strategy_config)
            
            # 시뮬레이션 실행
            await self._run_simulation(market_data)
            
            # 결과 계산 및 반환
            results = self._calculate_results()
            
            logger.info(f"백테스트 완료: {results.total_trades}건 거래, {results.total_return:.2f}% 수익")
            return results
            
        except Exception as e:
            logger.error(f"백테스트 실행 실패: {e}")
            raise BacktestError(f"백테스트 실행 중 오류: {e}")
    
    def _validate_market_data(self, market_data: Dict[str, pd.DataFrame]):
        """시장 데이터 검증"""
        
        for symbol in self.config.symbols:
            if symbol not in market_data:
                raise InsufficientBacktestDataError(symbol, 1, 0)
            
            data = market_data[symbol]
            
            # 데이터 기간 검증
            start_date = datetime.strptime(self.config.start_date, '%Y-%m-%d')
            end_date = datetime.strptime(self.config.end_date, '%Y-%m-%d')
            required_days = (end_date - start_date).days
            
            if len(data) < required_days:
                raise InsufficientBacktestDataError(symbol, required_days, len(data))
            
            # 필수 컬럼 확인
            required_columns = ['open', 'high', 'low', 'close', 'volume']
            missing_columns = [col for col in required_columns if col not in data.columns]
            
            if missing_columns:
                raise BacktestError(f"{symbol} 데이터에 필수 컬럼 누락: {missing_columns}")
    
    async def _initialize_simulation(self, strategy_config: Dict):
        """시뮬레이션 초기화"""
        
        # 거래 엔진 초기화 (백테스트 모드)
        self.trading_engine = TradingEngine(
            config={**strategy_config, 'mode': 'backtest'},
            api_connector=None,  # 백테스트에서는 실제 API 사용 안함
            risk_manager=RiskManager(
                limits=RiskConstants.__dict__,
                initial_balance=self.config.initial_balance
            )
        )
        
        # 초기 포트폴리오 값 기록
        start_time = datetime.strptime(self.config.start_date, '%Y-%m-%d')
        self.portfolio_values.append((start_time, self.current_balance))
        self.current_time = start_time
        
        logger.info(f"시뮬레이션 초기화 완료: 초기자본 ${self.config.initial_balance:,.2f}")
    
    async def _run_simulation(self, market_data: Dict[str, pd.DataFrame]):
        """시뮬레이션 메인 루프"""
        
        self.simulation_running = True
        processed_days = 0
        
        # 날짜별로 시뮬레이션 실행
        start_date = datetime.strptime(self.config.start_date, '%Y-%m-%d')
        end_date = datetime.strptime(self.config.end_date, '%Y-%m-%d')
        
        current_date = start_date
        while current_date <= end_date and self.simulation_running:
            
            # 해당 날짜의 시장 데이터 추출
            daily_data = self._extract_daily_data(market_data, current_date)
            
            if daily_data:
                # 거래 신호 생성
                signals = await self._generate_backtest_signals(daily_data)
                
                # 진입 신호 처리
                for signal in signals:
                    await self._process_entry_signal(signal, current_date)
                
                # 기존 포지션 관리
                await self._manage_existing_positions(daily_data, current_date)
                
                # 일일 성과 기록
                self._record_daily_performance(current_date)
            
            # 다음 날로 이동
            current_date += timedelta(days=1)
            processed_days += 1
            
            # 진행률 로그 (매 30일마다)
            if processed_days % 30 == 0:
                progress = (processed_days / (end_date - start_date).days) * 100
                logger.info(f"백테스트 진행률: {progress:.1f}% ({processed_days}/{(end_date - start_date).days}일)")
        
        # 남은 포지션 모두 청산
        await self._close_all_remaining_positions(end_date)
        
        logger.info(f"시뮬레이션 완료: {processed_days}일 처리")
    
    def _extract_daily_data(self, market_data: Dict[str, pd.DataFrame], date: datetime) -> Optional[Dict]:
        """특정 날짜의 시장 데이터 추출"""
        
        daily_data = {}
        date_str = date.strftime('%Y-%m-%d')
        
        for symbol, data in market_data.items():
            # 해당 날짜 데이터 찾기
            try:
                if hasattr(data.index, 'date'):
                    mask = data.index.date == date.date()
                else:
                    mask = data.index.str.contains(date_str)
                
                day_data = data[mask]
                
                if not day_data.empty:
                    daily_data[symbol] = day_data.iloc[-1].to_dict()  # 마지막 데이터 사용
                    
            except Exception as e:
                logger.debug(f"{symbol} {date_str} 데이터 추출 실패: {e}")
                continue
        
        return daily_data if daily_data else None
    
    async def _generate_backtest_signals(self, market_data: Dict) -> List[Dict]:
        """백테스트용 거래 신호 생성"""
        
        signals = []
        
        try:
            # 실제 거래 엔진의 신호 생성 로직 호출
            # (백테스트 모드에서는 API 호출 없이 시뮬레이션)
            for symbol, data in market_data.items():
                
                # 기본 신호 생성 (예시)
                signal = self._simulate_signal_generation(symbol, data)
                
                if signal and self._validate_backtest_signal(signal):
                    signals.append(signal)
        
        except Exception as e:
            logger.error(f"백테스트 신호 생성 오류: {e}")
        
        return signals
    
    def _simulate_signal_generation(self, symbol: str, data: Dict) -> Optional[Dict]:
        """신호 생성 시뮬레이션 (단순화된 로직)"""
        
        # 간단한 이동평균 기반 신호 생성 (예시)
        price = data.get('close', 0)
        
        if price > 0:
            # 임의의 신호 생성 (실제로는 전략 엔진 사용)
            if np.random.random() > 0.95:  # 5% 확률로 신호 생성
                direction = 'BUY' if np.random.random() > 0.5 else 'SELL'
                
                return {
                    'symbol': symbol,
                    'direction': direction,
                    'entry_price': price,
                    'confidence': np.random.uniform(0.6, 0.9),
                    'timestamp': self.current_time
                }
        
        return None
    
    def _validate_backtest_signal(self, signal: Dict) -> bool:
        """백테스트 신호 검증"""
        
        required_fields = ['symbol', 'direction', 'entry_price', 'confidence']
        
        for field in required_fields:
            if field not in signal:
                return False
        
        # 신뢰도 검증
        if signal['confidence'] < 0.6:  # 최소 60% 신뢰도
            return False
        
        return True
    
    async def _process_entry_signal(self, signal: Dict, current_time: datetime):
        """진입 신호 처리"""
        
        try:
            # 포지션 크기 계산
            position_value = self.current_balance * self.config.position_size_percent
            quantity = position_value / signal['entry_price']
            
            # 레버리지 적용
            effective_quantity = quantity * self.config.leverage
            
            # 잔고 확인
            required_margin = position_value
            if self.current_balance < required_margin:
                logger.debug(f"잔고 부족으로 진입 신호 무시: {signal['symbol']}")
                return
            
            # 수수료 및 슬리피지 계산
            commission = position_value * self.config.commission_rate
            slippage = position_value * self.config.slippage_rate
            
            # 포지션 생성
            position = BacktestPosition(
                symbol=signal['symbol'],
                side=signal['direction'],
                entry_price=signal['entry_price'],
                quantity=effective_quantity,
                entry_time=current_time,
                commission_paid=commission,
                slippage_cost=slippage
            )
            
            # 포지션 추가 및 잔고 업데이트
            self.positions.append(position)
            self.current_balance -= (required_margin + commission + slippage)
            
            logger.debug(f"백테스트 포지션 진입: {position.symbol} {position.side} @ {position.entry_price:.2f}")
        
        except Exception as e:
            logger.error(f"진입 신호 처리 오류: {e}")
    
    async def _manage_existing_positions(self, market_data: Dict, current_time: datetime):
        """기존 포지션 관리 (청산 조건 확인)"""
        
        positions_to_close = []
        
        for position in self.positions:
            if not position.is_open:
                continue
            
            symbol_data = market_data.get(position.symbol)
            if not symbol_data:
                continue
            
            current_price = symbol_data.get('close', 0)
            if current_price <= 0:
                continue
            
            # 청산 조건 확인
            should_exit, exit_reason = self._check_exit_conditions(position, current_price, current_time)
            
            if should_exit:
                # 포지션 청산
                await self._close_position(position, current_price, current_time, exit_reason)
                positions_to_close.append(position)
        
        # 청산된 포지션 이동
        for position in positions_to_close:
            self.positions.remove(position)
            self.closed_positions.append(position)
    
    def _check_exit_conditions(
        self, 
        position: BacktestPosition, 
        current_price: float, 
        current_time: datetime
    ) -> Tuple[bool, str]:
        """청산 조건 확인"""
        
        # 현재 손익 계산
        if position.side == 'BUY':
            pnl_percent = (current_price - position.entry_price) / position.entry_price
        else:
            pnl_percent = (position.entry_price - current_price) / position.entry_price
        
        # 익절 조건 (2% 수익)
        if pnl_percent >= 0.02:
            return True, "take_profit"
        
        # 손절 조건 (-1% 손실)
        if pnl_percent <= -0.01:
            return True, "stop_loss"
        
        # 시간 제한 (24시간)
        holding_hours = (current_time - position.entry_time).total_seconds() / 3600
        if holding_hours >= 24:
            return True, "time_limit"
        
        return False, ""
    
    async def _close_position(
        self, 
        position: BacktestPosition, 
        exit_price: float, 
        exit_time: datetime,
        exit_reason: str
    ):
        """포지션 청산"""
        
        # 청산 정보 업데이트
        position.exit_price = exit_price
        position.exit_time = exit_time
        position.exit_reason = exit_reason
        
        # 추가 수수료 계산
        exit_commission = (exit_price * position.quantity) * self.config.commission_rate
        position.commission_paid += exit_commission
        
        # 잔고 업데이트
        if position.side == 'BUY':
            proceeds = exit_price * position.quantity
        else:
            proceeds = position.entry_price * position.quantity + position.unrealized_pnl
        
        self.current_balance += proceeds - exit_commission
        
        logger.debug(f"백테스트 포지션 청산: {position.symbol} {position.return_percent:.2f}% ({exit_reason})")
    
    async def _close_all_remaining_positions(self, final_date: datetime):
        """남은 모든 포지션 청산"""
        
        for position in self.positions:
            if position.is_open:
                # 마지막 가격으로 청산
                await self._close_position(position, position.entry_price, final_date, "simulation_end")
        
        # 모든 포지션을 closed_positions로 이동
        self.closed_positions.extend(self.positions)
        self.positions.clear()
    
    def _record_daily_performance(self, date: datetime):
        """일일 성과 기록"""
        
        # 현재 포트폴리오 가치 계산
        portfolio_value = self.current_balance
        
        # 미실현 손익 포함
        for position in self.positions:
            if position.is_open:
                portfolio_value += position.unrealized_pnl
        
        # 기록 저장
        self.portfolio_values.append((date, portfolio_value))
        
        # 일일 수익률 계산
        if len(self.portfolio_values) > 1:
            prev_value = self.portfolio_values[-2][1]
            daily_return = (portfolio_value - prev_value) / prev_value
            self.daily_returns.append(daily_return)
    
    def _calculate_results(self) -> BacktestResults:
        """백테스트 결과 계산"""
        
        # 기간 계산
        start_date = datetime.strptime(self.config.start_date, '%Y-%m-%d')
        end_date = datetime.strptime(self.config.end_date, '%Y-%m-%d')
        duration_days = (end_date - start_date).days
        
        # 거래 통계
        total_trades = len(self.closed_positions)
        winning_trades = len([p for p in self.closed_positions if p.realized_pnl > 0])
        losing_trades = total_trades - winning_trades
        win_rate = (winning_trades / total_trades * 100) if total_trades > 0 else 0
        
        # 수익성 지표
        final_portfolio_value = self.portfolio_values[-1][1] if self.portfolio_values else self.config.initial_balance
        total_return = ((final_portfolio_value - self.config.initial_balance) / self.config.initial_balance) * 100
        annualized_return = total_return * (365 / duration_days) if duration_days > 0 else 0
        
        # 리스크 지표
        max_drawdown = self._calculate_max_drawdown()
        sharpe_ratio = self._calculate_sharpe_ratio()
        volatility = np.std(self.daily_returns) * np.sqrt(365) * 100 if self.daily_returns else 0
        var_95 = np.percentile(self.daily_returns, 5) * 100 if self.daily_returns else 0
        
        # 거래 분석
        winning_returns = [p.return_percent for p in self.closed_positions if p.realized_pnl > 0]
        losing_returns = [p.return_percent for p in self.closed_positions if p.realized_pnl < 0]
        
        avg_winning_trade = np.mean(winning_returns) if winning_returns else 0
        avg_losing_trade = np.mean(losing_returns) if losing_returns else 0
        
        # Profit Factor
        gross_profit = sum(p.realized_pnl for p in self.closed_positions if p.realized_pnl > 0)
        gross_loss = abs(sum(p.realized_pnl for p in self.closed_positions if p.realized_pnl < 0))
        profit_factor = gross_profit / gross_loss if gross_loss > 0 else float('inf')
        
        # 평균 거래 시간
        avg_duration = np.mean([p.holding_time_hours for p in self.closed_positions]) if self.closed_positions else 0
        
        # 연속 손실 계산
        max_consecutive_losses = self._calculate_max_consecutive_losses()
        
        return BacktestResults(
            start_date=self.config.start_date,
            end_date=self.config.end_date,
            duration_days=duration_days,
            symbols_tested=self.config.symbols,
            total_trades=total_trades,
            winning_trades=winning_trades,
            losing_trades=losing_trades,
            win_rate=win_rate,
            total_return=total_return,
            annualized_return=annualized_return,
            max_drawdown=max_drawdown,
            sharpe_ratio=sharpe_ratio,
            volatility=volatility,
            var_95=var_95,
            max_consecutive_losses=max_consecutive_losses,
            average_trade_duration_hours=avg_duration,
            average_winning_trade=avg_winning_trade,
            average_losing_trade=avg_losing_trade,
            profit_factor=profit_factor,
            positions=self.closed_positions,
            daily_returns=self.daily_returns,
            portfolio_values=self.portfolio_values
        )
    
    def _calculate_max_drawdown(self) -> float:
        """최대 드로우다운 계산"""
        
        if len(self.portfolio_values) < 2:
            return 0.0
        
        values = [v[1] for v in self.portfolio_values]
        peak = np.maximum.accumulate(values)
        drawdown = (values - peak) / peak
        
        return abs(np.min(drawdown)) * 100
    
    def _calculate_sharpe_ratio(self) -> float:
        """샤프 비율 계산"""
        
        if len(self.daily_returns) < 30:  # 최소 30일 데이터 필요
            return 0.0
        
        excess_returns = np.array(self.daily_returns)  # 무위험 수익률 0으로 가정
        
        if np.std(excess_returns) == 0:
            return 0.0
        
        sharpe = np.mean(excess_returns) / np.std(excess_returns) * np.sqrt(365)
        return sharpe
    
    def _calculate_max_consecutive_losses(self) -> int:
        """최대 연속 손실 계산"""
        
        if not self.closed_positions:
            return 0
        
        max_consecutive = 0
        current_consecutive = 0
        
        for position in self.closed_positions:
            if position.realized_pnl < 0:
                current_consecutive += 1
                max_consecutive = max(max_consecutive, current_consecutive)
            else:
                current_consecutive = 0
        
        return max_consecutive
    
    def generate_performance_report(self, results: BacktestResults) -> str:
        """성과 보고서 생성"""
        
        report = f"""
        
📊 백테스트 성과 보고서
{'='*50}

📋 기본 정보
- 테스트 기간: {results.start_date} ~ {results.end_date} ({results.duration_days}일)
- 테스트 심볼: {', '.join(results.symbols_tested)}
- 초기 자본: ${self.config.initial_balance:,.2f}

💰 수익성 지표
- 총 수익률: {results.total_return:.2f}%
- 연환산 수익률: {results.annualized_return:.2f}%
- 샤프 비율: {results.sharpe_ratio:.2f}
- Profit Factor: {results.profit_factor:.2f}

📈 거래 통계
- 총 거래 수: {results.total_trades}건
- 승률: {results.win_rate:.1f}% ({results.winning_trades}승 {results.losing_trades}패)
- 평균 승리 거래: {results.average_winning_trade:.2f}%
- 평균 손실 거래: {results.average_losing_trade:.2f}%
- 평균 보유 시간: {results.average_trade_duration_hours:.1f}시간

⚠️ 리스크 지표  
- 최대 드로우다운: {results.max_drawdown:.2f}%
- 변동성: {results.volatility:.2f}%
- 95% VaR: {results.var_95:.2f}%
- 최대 연속 손실: {results.max_consecutive_losses}회

🎯 성과 평가
"""
        
        # 성과 등급 판정
        if results.sharpe_ratio >= 2.0 and results.win_rate >= 60:
            report += "- 성과 등급: ⭐⭐⭐ 우수 (실거래 권장)\n"
        elif results.sharpe_ratio >= 1.0 and results.win_rate >= 50:
            report += "- 성과 등급: ⭐⭐ 양호 (소액 테스트 권장)\n"
        else:
            report += "- 성과 등급: ⭐ 개선 필요 (전략 수정 권장)\n"
        
        return report


# === 백테스팅 유틸리티 함수들 ===
async def run_parameter_optimization(
    config: BacktestConfig,
    parameter_ranges: Dict[str, Tuple[float, float]],
    market_data: Dict[str, pd.DataFrame]
) -> Dict[str, Any]:
    """파라미터 최적화 실행"""
    
    best_params = {}
    best_sharpe = -float('inf')
    optimization_results = []
    
    # 그리드 서치 방식으로 최적화
    param_combinations = _generate_parameter_combinations(parameter_ranges)
    
    logger.info(f"파라미터 최적화 시작: {len(param_combinations)}개 조합 테스트")
    
    for i, params in enumerate(param_combinations):
        try:
            # 백테스트 실행
            engine = BacktestingEngine(config)
            results = await engine.run_backtest(params, market_data)
            
            # 결과 기록
            optimization_results.append({
                'parameters': params,
                'sharpe_ratio': results.sharpe_ratio,
                'total_return': results.total_return,
                'max_drawdown': results.max_drawdown,
                'win_rate': results.win_rate
            })
            
            # 최적 파라미터 업데이트
            if results.sharpe_ratio > best_sharpe:
                best_sharpe = results.sharpe_ratio
                best_params = params.copy()
            
            # 진행률 표시
            if (i + 1) % 10 == 0:
                progress = ((i + 1) / len(param_combinations)) * 100
                logger.info(f"최적화 진행률: {progress:.1f}% (현재 최고 Sharpe: {best_sharpe:.2f})")
        
        except Exception as e:
            logger.error(f"파라미터 최적화 오류 (조합 {i}): {e}")
            continue
    
    logger.info(f"파라미터 최적화 완료: 최적 Sharpe 비율 {best_sharpe:.2f}")
    
    return {
        'best_parameters': best_params,
        'best_sharpe_ratio': best_sharpe,
        'all_results': optimization_results,
        'total_combinations_tested': len(param_combinations)
    }


def _generate_parameter_combinations(parameter_ranges: Dict[str, Tuple[float, float]]) -> List[Dict]:
    """파라미터 조합 생성"""
    
    combinations = []
    
    # 간단한 그리드 서치 (각 파라미터별 3개 값)
    for param_name, (min_val, max_val) in parameter_ranges.items():
        if param_name not in combinations:
            combinations = [{}]
        
        new_combinations = []
        test_values = [min_val, (min_val + max_val) / 2, max_val]
        
        for combo in combinations:
            for value in test_values:
                new_combo = combo.copy()
                new_combo[param_name] = value
                new_combinations.append(new_combo)
        
        combinations = new_combinations
    
    return combinations[:27]  # 최대 27개 조합으로 제한


# 모듈 익스포트
__all__ = [
    'BacktestMode',
    'BacktestConfig',
    'BacktestPosition', 
    'BacktestResults',
    'BacktestingEngine',
    'run_parameter_optimization'
]