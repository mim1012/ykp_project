"""
Price Channel Calculator

Donchian Channel 기반 Price Channel 계산 및 돌파 감지 시스템.
API 문서의 명세를 정확히 따라 구현된 고성능 채널 분석 모듈.
"""

from typing import Dict, List, Optional, Tuple
from dataclasses import dataclass
from datetime import datetime
import pandas as pd
import numpy as np

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(__file__))))

from core.logger import SystemLogger

logger = SystemLogger()


@dataclass
class PriceChannelConfig:
    """Price Channel 설정"""
    period: int = 20  # 채널 계산 기간 (기본 20일)
    breakout_threshold: float = 0.001  # 돌파 임계값 (0.1%)
    confirmation_candles: int = 2  # 돌파 확인 캔들 수
    min_channel_width: float = 0.005  # 최소 채널 폭 (0.5%)
    volume_confirmation: bool = True  # 거래량 확인 사용 여부
    atr_filter: bool = True  # ATR 필터 사용 여부
    atr_multiplier: float = 1.5  # ATR 배수


class PriceChannelCalculator:
    """Price Channel 계산기 (Donchian Channel 기반)"""
    
    def __init__(self, config: PriceChannelConfig):
        self.config = config
        self.calculation_cache = {}
        self.last_update_time = {}
        
    def calculate_channel(self, price_data: pd.DataFrame) -> Dict:
        """
        Price Channel 계산 (API 문서 명세 기반)
        
        Args:
            price_data: OHLCV 데이터프레임
            
        Returns:
            채널 정보 딕셔너리
        """
        if len(price_data) < self.config.period:
            raise ValueError(f"데이터 길이가 부족합니다. 최소 {self.config.period}개 필요")
        
        try:
            # Donchian Channel 계산 (롤링 최고가/최저가)
            price_data = price_data.copy()
            
            # 상단선: 설정 기간 동안의 최고가
            price_data['upper_channel'] = price_data['high'].rolling(
                window=self.config.period
            ).max()
            
            # 하단선: 설정 기간 동안의 최저가  
            price_data['lower_channel'] = price_data['low'].rolling(
                window=self.config.period
            ).min()
            
            # 중간선: 상단선과 하단선의 평균
            price_data['middle_channel'] = (
                price_data['upper_channel'] + price_data['lower_channel']
            ) / 2
            
            # 채널 폭 계산 (중간선 대비 퍼센트)
            price_data['channel_width'] = (
                price_data['upper_channel'] - price_data['lower_channel']
            ) / price_data['middle_channel']
            
            # ATR 계산 (선택적)
            if self.config.atr_filter:
                price_data['atr'] = self._calculate_atr(price_data, period=14)
            
            # 최신 채널 정보 추출
            latest = price_data.iloc[-1]
            
            channel_info = {
                'upper_channel': latest['upper_channel'],
                'lower_channel': latest['lower_channel'], 
                'middle_channel': latest['middle_channel'],
                'channel_width': latest['channel_width'],
                'current_price': latest['close'],
                'timestamp': latest.name if hasattr(latest, 'name') else datetime.now(),
                'period': self.config.period
            }
            
            # ATR 정보 추가 (사용 시)
            if self.config.atr_filter:
                channel_info['atr'] = latest['atr']
                channel_info['atr_width'] = latest['atr'] / latest['close']
            
            # 채널 품질 검증
            channel_info['is_valid'] = self._validate_channel_quality(channel_info)
            
            return channel_info
            
        except Exception as e:
            logger.error(f"Price Channel 계산 오류: {e}")
            raise
    
    def _calculate_atr(self, data: pd.DataFrame, period: int = 14) -> pd.Series:
        """Average True Range 계산"""
        high = data['high']
        low = data['low']  
        close_prev = data['close'].shift(1)
        
        # True Range 계산
        tr1 = high - low
        tr2 = abs(high - close_prev)
        tr3 = abs(low - close_prev)
        
        true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = true_range.rolling(window=period).mean()
        
        return atr
    
    def _validate_channel_quality(self, channel_info: Dict) -> bool:
        """채널 품질 검증"""
        # 최소 채널 폭 확인
        if channel_info['channel_width'] < self.config.min_channel_width:
            return False
        
        # 가격이 채널 내부에 있는지 확인
        current_price = channel_info['current_price']
        if not (channel_info['lower_channel'] <= current_price <= channel_info['upper_channel']):
            # 이미 채널을 벗어난 상태면 유효하지 않음
            return False
        
        return True


class PriceChannelBreakoutDetector:
    """Price Channel 돌파 감지기"""
    
    def __init__(self, config: PriceChannelConfig):
        self.config = config
        self.calculator = PriceChannelCalculator(config)
        self.breakout_history = []
        
    def detect_breakout(self, price_data: pd.DataFrame) -> Dict:
        """
        Price Channel 돌파 감지 (API 문서 명세 기반)
        
        Returns:
            돌파 신호 정보 또는 None
        """
        try:
            # 채널 정보 계산
            channel_info = self.calculator.calculate_channel(price_data)
            
            # 채널 품질 확인
            if not channel_info['is_valid']:
                return {'signal': None, 'reason': 'invalid_channel'}
            
            current_price = channel_info['current_price']
            upper_channel = channel_info['upper_channel'] 
            lower_channel = channel_info['lower_channel']
            
            # 상단 돌파 확인 (매수 신호)
            upper_breakout_price = upper_channel * (1 + self.config.breakout_threshold)
            if current_price > upper_breakout_price:
                if self._confirm_breakout(price_data, 'upper'):
                    return self._create_breakout_signal('BUY', channel_info, price_data)
            
            # 하단 돌파 확인 (매도 신호)  
            lower_breakout_price = lower_channel * (1 - self.config.breakout_threshold)
            if current_price < lower_breakout_price:
                if self._confirm_breakout(price_data, 'lower'):
                    return self._create_breakout_signal('SELL', channel_info, price_data)
            
            return {'signal': None, 'reason': 'no_breakout'}
            
        except Exception as e:
            logger.error(f"돌파 감지 오류: {e}")
            return {'signal': None, 'reason': f'error: {e}'}
    
    def _confirm_breakout(self, price_data: pd.DataFrame, direction: str) -> bool:
        """돌파 확인 (연속 캔들 + 볼륨 검증)"""
        
        if len(price_data) < self.config.confirmation_candles:
            return False
        
        recent_candles = price_data.tail(self.config.confirmation_candles)
        
        # 연속 캔들 확인
        candle_confirmation = False
        if direction == 'upper':
            # 상단 돌파: 연속 상승 캔들 확인
            candle_confirmation = all(
                candle['close'] > candle['open'] 
                for _, candle in recent_candles.iterrows()
            )
        else:
            # 하단 돌파: 연속 하락 캔들 확인
            candle_confirmation = all(
                candle['close'] < candle['open']
                for _, candle in recent_candles.iterrows()
            )
        
        # 볼륨 확인 (선택적)
        volume_confirmation = True
        if self.config.volume_confirmation and 'volume' in recent_candles.columns:
            avg_volume = price_data['volume'].tail(20).mean()
            recent_avg_volume = recent_candles['volume'].mean()
            volume_confirmation = recent_avg_volume > avg_volume * 1.2  # 20% 증가
        
        return candle_confirmation and volume_confirmation
    
    def _create_breakout_signal(self, direction: str, channel_info: Dict, price_data: pd.DataFrame) -> Dict:
        """돌파 신호 생성"""
        
        # 신호 강도 계산
        confidence = self._calculate_breakout_confidence(channel_info, price_data, direction)
        
        signal = {
            'signal': 'BREAKOUT',
            'direction': direction,
            'entry_price': channel_info['current_price'],
            'upper_channel': channel_info['upper_channel'],
            'lower_channel': channel_info['lower_channel'],
            'middle_channel': channel_info['middle_channel'],
            'channel_width': channel_info['channel_width'],
            'confidence': confidence,
            'timestamp': datetime.now(),
            'period': channel_info['period']
        }
        
        # ATR 정보 추가 (사용 시)
        if 'atr' in channel_info:
            signal['atr'] = channel_info['atr']
            signal['atr_width'] = channel_info['atr_width']
        
        # 돌파 기록 저장
        self.breakout_history.append(signal)
        
        # 최대 100개 기록 유지
        if len(self.breakout_history) > 100:
            self.breakout_history.pop(0)
        
        logger.info(f"Price Channel 돌파 감지: {direction} @ {signal['entry_price']:.2f} "
                   f"(신뢰도: {confidence:.2f})")
        
        return signal
    
    def _calculate_breakout_confidence(self, channel_info: Dict, price_data: pd.DataFrame, direction: str) -> float:
        """돌파 신뢰도 계산"""
        
        confidence = 0.7  # 기본 신뢰도
        
        # 채널 폭이 클수록 신뢰도 증가
        width_bonus = min(channel_info['channel_width'] / 0.02, 0.2)  # 최대 0.2 보너스
        confidence += width_bonus
        
        # ATR 필터 적용 (사용 시)
        if 'atr' in channel_info and self.config.atr_filter:
            atr_width = channel_info['atr_width']
            breakout_strength = abs(channel_info['current_price'] - 
                                  (channel_info['upper_channel'] if direction == 'BUY' 
                                   else channel_info['lower_channel']))
            
            # 돌파 강도가 ATR 대비 클수록 신뢰도 증가
            if breakout_strength > channel_info['atr'] * self.config.atr_multiplier:
                confidence += 0.1
        
        # 최근 볼륨 증가 확인
        if 'volume' in price_data.columns:
            recent_volume = price_data['volume'].tail(self.config.confirmation_candles).mean()
            avg_volume = price_data['volume'].tail(20).mean()
            
            if recent_volume > avg_volume * 1.5:  # 50% 볼륨 증가
                confidence += 0.1
        
        return min(round(confidence, 2), 1.0)  # 최대 1.0으로 제한


class MultiTimeframePriceChannel:
    """다중 시간대 Price Channel 분석"""
    
    def __init__(self, timeframes: List[str]):
        self.timeframes = timeframes
        self.detectors = {}
        
        # 각 시간대별 감지기 초기화
        for tf in timeframes:
            config = self._get_timeframe_config(tf)
            self.detectors[tf] = PriceChannelBreakoutDetector(config)
    
    def _get_timeframe_config(self, timeframe: str) -> PriceChannelConfig:
        """시간대별 설정 반환 (API 문서 기반)"""
        
        configs = {
            '1m': PriceChannelConfig(
                period=20, 
                breakout_threshold=0.001,
                confirmation_candles=2,
                min_channel_width=0.003
            ),
            '5m': PriceChannelConfig(
                period=20,
                breakout_threshold=0.002, 
                confirmation_candles=2,
                min_channel_width=0.005
            ),
            '15m': PriceChannelConfig(
                period=14,
                breakout_threshold=0.003,
                confirmation_candles=1,
                min_channel_width=0.008
            ),
            '1h': PriceChannelConfig(
                period=10,
                breakout_threshold=0.005,
                confirmation_candles=1,
                min_channel_width=0.01
            ),
            '4h': PriceChannelConfig(
                period=7,
                breakout_threshold=0.008,
                confirmation_candles=1,
                min_channel_width=0.015
            ),
            '1d': PriceChannelConfig(
                period=5,
                breakout_threshold=0.01,
                confirmation_candles=1,
                min_channel_width=0.02
            )
        }
        
        return configs.get(timeframe, PriceChannelConfig())
    
    def analyze_all_timeframes(self, price_data_dict: Dict[str, pd.DataFrame]) -> Dict:
        """모든 시간대 분석 및 신호 종합"""
        
        results = {}
        signals = []
        
        for timeframe in self.timeframes:
            if timeframe in price_data_dict and len(price_data_dict[timeframe]) >= 20:
                detector = self.detectors[timeframe]
                result = detector.detect_breakout(price_data_dict[timeframe])
                results[timeframe] = result
                
                if result.get('signal') == 'BREAKOUT':
                    signals.append({**result, 'timeframe': timeframe})
        
        # 신호 종합 분석
        return self._synthesize_multi_timeframe_signals(results, signals)
    
    def _synthesize_multi_timeframe_signals(self, results: Dict, signals: List[Dict]) -> Dict:
        """다중 시간대 신호 종합"""
        
        if not signals:
            return {'signal': None, 'reason': 'no_signals_across_timeframes'}
        
        # 방향별 신호 분류
        buy_signals = [s for s in signals if s['direction'] == 'BUY']
        sell_signals = [s for s in signals if s['direction'] == 'SELL']
        
        # 강한 신호 판단 (2개 이상 시간대에서 동일 방향)
        if len(buy_signals) >= 2:
            avg_confidence = sum(s['confidence'] for s in buy_signals) / len(buy_signals)
            strongest_signal = max(buy_signals, key=lambda x: x['confidence'])
            
            return {
                'signal': 'STRONG_BREAKOUT',
                'direction': 'BUY',
                'confidence': avg_confidence,
                'supporting_timeframes': [s['timeframe'] for s in buy_signals],
                'signal_count': len(buy_signals),
                'strongest_timeframe': strongest_signal['timeframe'],
                'entry_price': strongest_signal['entry_price'],
                'channel_info': strongest_signal
            }
            
        elif len(sell_signals) >= 2:
            avg_confidence = sum(s['confidence'] for s in sell_signals) / len(sell_signals)
            strongest_signal = max(sell_signals, key=lambda x: x['confidence'])
            
            return {
                'signal': 'STRONG_BREAKOUT',
                'direction': 'SELL', 
                'confidence': avg_confidence,
                'supporting_timeframes': [s['timeframe'] for s in sell_signals],
                'signal_count': len(sell_signals),
                'strongest_timeframe': strongest_signal['timeframe'],
                'entry_price': strongest_signal['entry_price'],
                'channel_info': strongest_signal
            }
            
        elif len(signals) == 1:
            # 단일 시간대 신호
            single_signal = signals[0]
            return {
                'signal': 'SINGLE_BREAKOUT',
                'direction': single_signal['direction'],
                'confidence': single_signal['confidence'] * 0.8,  # 단일 신호는 신뢰도 감소
                'supporting_timeframes': [single_signal['timeframe']],
                'signal_count': 1,
                'entry_price': single_signal['entry_price'],
                'channel_info': single_signal
            }
        
        else:
            # 상충하는 신호들
            return {'signal': 'CONFLICTING', 'reason': 'conflicting_signals_across_timeframes'}


class PriceChannelEntryStrategy:
    """Price Channel 기반 진입 전략"""
    
    def __init__(self, config: PriceChannelConfig, risk_manager):
        self.config = config
        self.risk_manager = risk_manager
        
    def calculate_entry_parameters(self, breakout_signal: Dict, account_balance: float) -> Dict:
        """진입 파라미터 계산"""
        
        # 손절가 계산
        stop_loss_price = self._calculate_stop_loss_price(breakout_signal)
        
        # 포지션 크기 계산 (리스크 기반)
        position_size = self.risk_manager.calculate_position_size(
            balance=account_balance,
            entry_price=breakout_signal['entry_price'],
            stop_loss_price=stop_loss_price,
            signal_confidence=breakout_signal['confidence']
        )
        
        # 익절 목표 계산
        take_profit_price = self._calculate_take_profit_price(breakout_signal)
        
        return {
            'entry_order': {
                'symbol': breakout_signal.get('symbol', 'BTCUSDT'),
                'side': breakout_signal['direction'],
                'type': 'MARKET',  # 돌파 시 즉시 진입
                'quantity': position_size,
                'timestamp': datetime.now()
            },
            'stop_loss_order': {
                'symbol': breakout_signal.get('symbol', 'BTCUSDT'),
                'side': 'SELL' if breakout_signal['direction'] == 'BUY' else 'BUY',
                'type': 'STOP_LOSS_LIMIT',
                'quantity': position_size,
                'stop_price': stop_loss_price,
                'price': stop_loss_price * (0.995 if breakout_signal['direction'] == 'BUY' else 1.005)
            },
            'take_profit_order': {
                'symbol': breakout_signal.get('symbol', 'BTCUSDT'),
                'side': 'SELL' if breakout_signal['direction'] == 'BUY' else 'BUY',
                'type': 'LIMIT',
                'quantity': position_size,
                'price': take_profit_price
            },
            'risk_info': {
                'entry_price': breakout_signal['entry_price'],
                'stop_loss_price': stop_loss_price,
                'take_profit_price': take_profit_price,
                'position_size': position_size,
                'max_loss': abs(breakout_signal['entry_price'] - stop_loss_price) * position_size,
                'risk_reward_ratio': self._calculate_risk_reward_ratio(
                    breakout_signal['entry_price'], stop_loss_price, take_profit_price
                )
            }
        }
    
    def _calculate_stop_loss_price(self, signal: Dict) -> float:
        """손절가 계산"""
        if signal['direction'] == 'BUY':
            # 매수 시: 하단 채널 아래 0.5%
            return signal['lower_channel'] * 0.995
        else:
            # 매도 시: 상단 채널 위 0.5%
            return signal['upper_channel'] * 1.005
    
    def _calculate_take_profit_price(self, signal: Dict) -> float:
        """익절가 계산"""
        entry_price = signal['entry_price']
        stop_loss_price = self._calculate_stop_loss_price(signal)
        
        # 리스크 대비 2:1 수익률 목표
        risk_distance = abs(entry_price - stop_loss_price)
        
        if signal['direction'] == 'BUY':
            return entry_price + (risk_distance * 2)
        else:
            return entry_price - (risk_distance * 2)
    
    def _calculate_risk_reward_ratio(self, entry_price: float, stop_loss: float, take_profit: float) -> float:
        """위험 대비 보상 비율 계산"""
        risk = abs(entry_price - stop_loss)
        reward = abs(take_profit - entry_price)
        
        return reward / risk if risk > 0 else 0


# 모듈 익스포트
__all__ = [
    'PriceChannelConfig',
    'PriceChannelCalculator', 
    'PriceChannelBreakoutDetector',
    'MultiTimeframePriceChannel',
    'PriceChannelEntryStrategy'
]