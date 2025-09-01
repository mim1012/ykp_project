"""
Trend Reversal Detector

추세 반전 패턴 감지 시스템. PCS 3단계 청산에서 사용되는
고도화된 캔들 패턴 및 기술적 분석 기반 반전 신호 감지.
"""

from typing import Dict, List, Optional, Tuple
from enum import Enum
from dataclasses import dataclass
from datetime import datetime
import pandas as pd
import numpy as np

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(__file__))))

from core.logger import SystemLogger

logger = SystemLogger()


class ReversalPattern(Enum):
    """반전 패턴 유형"""
    CONSECUTIVE_REVERSAL = "연속_반전"  # 연속 음봉/양봉
    LONG_SHADOW = "긴_그림자"  # 긴 위꼬리/아래꼬리
    DOJI_REVERSAL = "도지_반전"  # 도지 캔들 출현
    VOLUME_REVERSAL = "볼륨_반전"  # 고볼륨 반전 캔들
    EXHAUSTION_GAP = "소진_갭"  # 소진성 갭 출현


@dataclass
class ReversalSignal:
    """반전 신호 정보"""
    pattern: ReversalPattern
    strength: float  # 반전 강도 (0.0 ~ 1.0)
    confidence: float  # 신뢰도 (0.0 ~ 1.0)
    timestamp: datetime
    candle_count: int  # 분석된 캔들 수
    volume_surge: bool  # 볼륨 급증 여부
    shadow_ratio: Optional[float] = None  # 그림자 비율
    body_ratio: Optional[float] = None  # 몸통 비율
    
    def to_dict(self) -> Dict:
        return {
            'pattern': self.pattern.value,
            'strength': self.strength,
            'confidence': self.confidence,
            'timestamp': self.timestamp.isoformat(),
            'candle_count': self.candle_count,
            'volume_surge': self.volume_surge,
            'shadow_ratio': self.shadow_ratio,
            'body_ratio': self.body_ratio
        }


class TrendReversalDetector:
    """추세 반전 패턴 감지기"""
    
    def __init__(self):
        self.detection_history = []
        self.pattern_weights = {
            ReversalPattern.CONSECUTIVE_REVERSAL: 1.0,
            ReversalPattern.LONG_SHADOW: 0.8,
            ReversalPattern.DOJI_REVERSAL: 0.7,
            ReversalPattern.VOLUME_REVERSAL: 0.9,
            ReversalPattern.EXHAUSTION_GAP: 0.6
        }
    
    def detect_reversal_pattern(self, position, candles: pd.DataFrame) -> Dict:
        """
        추세 반전 패턴 감지 (PCS 3단 청산용)
        
        Args:
            position: PCSPosition 객체
            candles: 최근 캔들 데이터
            
        Returns:
            반전 감지 결과
        """
        if len(candles) < 2:
            return {'reversal_detected': False, 'reason': 'insufficient_data'}
        
        detected_patterns = []
        
        # 1. 연속 반전 캔들 감지
        consecutive_pattern = self._detect_consecutive_reversal(position, candles)
        if consecutive_pattern:
            detected_patterns.append(consecutive_pattern)
        
        # 2. 긴 그림자 패턴 감지
        shadow_pattern = self._detect_long_shadow_pattern(position, candles)
        if shadow_pattern:
            detected_patterns.append(shadow_pattern)
        
        # 3. 도지 반전 패턴 감지
        doji_pattern = self._detect_doji_reversal(position, candles)
        if doji_pattern:
            detected_patterns.append(doji_pattern)
        
        # 4. 볼륨 반전 패턴 감지
        volume_pattern = self._detect_volume_reversal(position, candles)
        if volume_pattern:
            detected_patterns.append(volume_pattern)
        
        # 패턴 종합 평가
        if detected_patterns:
            strongest_pattern = max(detected_patterns, key=lambda x: x.strength * x.confidence)
            
            return {
                'reversal_detected': True,
                'pattern': strongest_pattern.pattern.value,
                'strength': strongest_pattern.strength,
                'confidence': strongest_pattern.confidence,
                'all_patterns': [p.to_dict() for p in detected_patterns],
                'timestamp': datetime.now(),
                'candles_analyzed': len(candles)
            }
        
        return {'reversal_detected': False, 'reason': 'no_patterns_detected'}
    
    def _detect_consecutive_reversal(self, position, candles: pd.DataFrame) -> Optional[ReversalSignal]:
        """연속 반전 캔들 감지 (API 문서 명세)"""
        
        if position.direction == 'BUY':
            # 매수 포지션: 연속 음봉 감지
            consecutive_bearish = all(
                candle['close'] < candle['open']
                for _, candle in candles.iterrows()
            )
            
            if consecutive_bearish:
                # 반전 강도 계산 (음봉 크기 기준)
                bear_strength = np.mean([
                    (candle['open'] - candle['close']) / candle['open']
                    for _, candle in candles.iterrows()
                ])
                
                return ReversalSignal(
                    pattern=ReversalPattern.CONSECUTIVE_REVERSAL,
                    strength=min(bear_strength * 5, 1.0),  # 정규화
                    confidence=0.85,
                    timestamp=datetime.now(),
                    candle_count=len(candles),
                    volume_surge=self._check_volume_surge(candles)
                )
        
        else:
            # 매도 포지션: 연속 양봉 감지
            consecutive_bullish = all(
                candle['close'] > candle['open']
                for _, candle in candles.iterrows()
            )
            
            if consecutive_bullish:
                # 반전 강도 계산 (양봉 크기 기준)
                bull_strength = np.mean([
                    (candle['close'] - candle['open']) / candle['open']
                    for _, candle in candles.iterrows()
                ])
                
                return ReversalSignal(
                    pattern=ReversalPattern.CONSECUTIVE_REVERSAL,
                    strength=min(bull_strength * 5, 1.0),  # 정규화
                    confidence=0.85,
                    timestamp=datetime.now(),
                    candle_count=len(candles),
                    volume_surge=self._check_volume_surge(candles)
                )
        
        return None
    
    def _detect_long_shadow_pattern(self, position, candles: pd.DataFrame) -> Optional[ReversalSignal]:
        """긴 그림자 패턴 감지"""
        
        if len(candles) == 0:
            return None
        
        last_candle = candles.iloc[-1]
        
        # 캔들 몸통과 그림자 계산
        body_size = abs(last_candle['close'] - last_candle['open'])
        upper_shadow = last_candle['high'] - max(last_candle['open'], last_candle['close'])
        lower_shadow = min(last_candle['open'], last_candle['close']) - last_candle['low']
        
        # 몸통이 너무 작으면 제외
        if body_size < last_candle['close'] * 0.001:  # 0.1% 이하
            return None
        
        shadow_ratio = 0
        pattern_detected = False
        
        if position.direction == 'BUY':
            # 매수 포지션: 긴 위꼬리 감지 (상승 피로감)
            shadow_ratio = upper_shadow / body_size
            if shadow_ratio > 2.0:  # 위꼬리가 몸통의 2배 이상
                pattern_detected = True
        
        else:
            # 매도 포지션: 긴 아래꼬리 감지 (하락 피로감)
            shadow_ratio = lower_shadow / body_size
            if shadow_ratio > 2.0:  # 아래꼬리가 몸통의 2배 이상
                pattern_detected = True
        
        if pattern_detected:
            return ReversalSignal(
                pattern=ReversalPattern.LONG_SHADOW,
                strength=min(shadow_ratio / 4.0, 1.0),  # 정규화
                confidence=0.75,
                timestamp=datetime.now(),
                candle_count=1,
                volume_surge=self._check_volume_surge(candles),
                shadow_ratio=shadow_ratio,
                body_ratio=body_size / (last_candle['high'] - last_candle['low'])
            )
        
        return None
    
    def _detect_doji_reversal(self, position, candles: pd.DataFrame) -> Optional[ReversalSignal]:
        """도지 반전 패턴 감지"""
        
        if len(candles) == 0:
            return None
        
        last_candle = candles.iloc[-1]
        
        # 도지 판단 기준
        body_size = abs(last_candle['close'] - last_candle['open'])
        total_range = last_candle['high'] - last_candle['low']
        
        if total_range <= 0:
            return None
        
        body_ratio = body_size / total_range
        
        # 도지 조건: 몸통이 전체 범위의 10% 이하
        if body_ratio <= 0.1:
            # 도지의 위치에 따른 반전 강도 계산
            high_position = (last_candle['high'] - last_candle['close']) / total_range
            low_position = (last_candle['close'] - last_candle['low']) / total_range
            
            # 상단/하단 근처의 도지가 더 강한 반전 신호
            doji_strength = 0.5
            if position.direction == 'BUY' and high_position < 0.3:  # 상단 근처 도지
                doji_strength = 0.8
            elif position.direction == 'SELL' and low_position < 0.3:  # 하단 근처 도지
                doji_strength = 0.8
            
            return ReversalSignal(
                pattern=ReversalPattern.DOJI_REVERSAL,
                strength=doji_strength,
                confidence=0.65,
                timestamp=datetime.now(),
                candle_count=1,
                volume_surge=self._check_volume_surge(candles),
                body_ratio=body_ratio
            )
        
        return None
    
    def _detect_volume_reversal(self, position, candles: pd.DataFrame) -> Optional[ReversalSignal]:
        """볼륨 반전 패턴 감지"""
        
        if 'volume' not in candles.columns or len(candles) < 2:
            return None
        
        last_candle = candles.iloc[-1]
        recent_avg_volume = candles['volume'].mean()
        
        # 볼륨이 평균의 2배 이상이면서 반전 캔들
        if last_candle['volume'] > recent_avg_volume * 2.0:
            
            # 반전 여부 확인
            is_reversal_candle = False
            
            if position.direction == 'BUY':
                # 매수 포지션: 고볼륨 음봉
                is_reversal_candle = last_candle['close'] < last_candle['open']
            else:
                # 매도 포지션: 고볼륨 양봉  
                is_reversal_candle = last_candle['close'] > last_candle['open']
            
            if is_reversal_candle:
                volume_ratio = last_candle['volume'] / recent_avg_volume
                
                return ReversalSignal(
                    pattern=ReversalPattern.VOLUME_REVERSAL,
                    strength=min(volume_ratio / 5.0, 1.0),  # 정규화
                    confidence=0.80,
                    timestamp=datetime.now(),
                    candle_count=len(candles),
                    volume_surge=True
                )
        
        return None
    
    def _check_volume_surge(self, candles: pd.DataFrame) -> bool:
        """볼륨 급증 확인"""
        if 'volume' not in candles.columns or len(candles) < 2:
            return False
        
        recent_volume = candles['volume'].tail(2).mean()
        avg_volume = candles['volume'].mean()
        
        return recent_volume > avg_volume * 1.5  # 50% 이상 증가
    
    def get_reversal_statistics(self) -> Dict:
        """반전 감지 통계"""
        if not self.detection_history:
            return {'total_detections': 0}
        
        patterns = [d['pattern'] for d in self.detection_history]
        pattern_counts = {}
        
        for pattern in ReversalPattern:
            pattern_counts[pattern.value] = patterns.count(pattern.value)
        
        return {
            'total_detections': len(self.detection_history),
            'pattern_distribution': pattern_counts,
            'average_strength': np.mean([d['strength'] for d in self.detection_history]),
            'average_confidence': np.mean([d['confidence'] for d in self.detection_history]),
            'last_detection': self.detection_history[-1]['timestamp'].isoformat() if self.detection_history else None
        }


class AdvancedReversalDetector(TrendReversalDetector):
    """고급 반전 패턴 감지기"""
    
    def __init__(self):
        super().__init__()
        self.fibonacci_levels = [0.236, 0.382, 0.618, 0.786]
        
    def detect_fibonacci_reversal(self, position, price_data: pd.DataFrame, lookback_days: int = 10) -> Optional[ReversalSignal]:
        """피보나치 되돌림 반전 감지"""
        
        if len(price_data) < lookback_days:
            return None
        
        recent_data = price_data.tail(lookback_days)
        
        # 최근 추세의 고점과 저점 찾기
        high_price = recent_data['high'].max()
        low_price = recent_data['low'].min() 
        current_price = recent_data.iloc[-1]['close']
        
        # 피보나치 되돌림 레벨 계산
        price_range = high_price - low_price
        fib_levels = {}
        
        if position.direction == 'BUY':
            # 매수 포지션: 고점에서 되돌림 확인
            for level in self.fibonacci_levels:
                fib_levels[level] = high_price - (price_range * level)
            
            # 현재가가 주요 피보나치 레벨 근처에서 지지받는지 확인
            for level, price in fib_levels.items():
                if abs(current_price - price) / current_price < 0.01:  # 1% 이내
                    return ReversalSignal(
                        pattern=ReversalPattern.EXHAUSTION_GAP,
                        strength=level,  # 피보나치 레벨이 강도
                        confidence=0.70,
                        timestamp=datetime.now(),
                        candle_count=lookback_days,
                        volume_surge=self._check_volume_surge(recent_data)
                    )
        
        else:
            # 매도 포지션: 저점에서 되돌림 확인
            for level in self.fibonacci_levels:
                fib_levels[level] = low_price + (price_range * level)
            
            # 현재가가 주요 피보나치 레벨에서 저항받는지 확인
            for level, price in fib_levels.items():
                if abs(current_price - price) / current_price < 0.01:  # 1% 이내
                    return ReversalSignal(
                        pattern=ReversalPattern.EXHAUSTION_GAP,
                        strength=level,  # 피보나치 레벨이 강도
                        confidence=0.70,
                        timestamp=datetime.now(),
                        candle_count=lookback_days,
                        volume_surge=self._check_volume_surge(recent_data)
                    )
        
        return None
    
    def detect_rsi_divergence(self, position, price_data: pd.DataFrame, rsi_period: int = 14) -> Optional[ReversalSignal]:
        """RSI 다이버전스 반전 감지"""
        
        if len(price_data) < rsi_period * 2:
            return None
        
        # RSI 계산
        rsi_values = self._calculate_rsi(price_data, rsi_period)
        
        # 최근 데이터
        recent_data = price_data.tail(10)
        recent_rsi = rsi_values.tail(10)
        
        if position.direction == 'BUY':
            # 매수 포지션: 베어리시 다이버전스 감지
            # 가격은 상승하지만 RSI는 하락
            price_trend = recent_data['close'].iloc[-1] > recent_data['close'].iloc[0]
            rsi_trend = recent_rsi.iloc[-1] < recent_rsi.iloc[0]
            
            if price_trend and rsi_trend and recent_rsi.iloc[-1] > 70:  # 과매수 구간
                divergence_strength = (recent_rsi.iloc[0] - recent_rsi.iloc[-1]) / recent_rsi.iloc[0]
                
                return ReversalSignal(
                    pattern=ReversalPattern.EXHAUSTION_GAP,
                    strength=min(divergence_strength * 2, 1.0),
                    confidence=0.75,
                    timestamp=datetime.now(), 
                    candle_count=10,
                    volume_surge=self._check_volume_surge(recent_data)
                )
        
        else:
            # 매도 포지션: 불리시 다이버전스 감지
            # 가격은 하락하지만 RSI는 상승
            price_trend = recent_data['close'].iloc[-1] < recent_data['close'].iloc[0]
            rsi_trend = recent_rsi.iloc[-1] > recent_rsi.iloc[0]
            
            if price_trend and rsi_trend and recent_rsi.iloc[-1] < 30:  # 과매도 구간
                divergence_strength = (recent_rsi.iloc[-1] - recent_rsi.iloc[0]) / (100 - recent_rsi.iloc[0])
                
                return ReversalSignal(
                    pattern=ReversalPattern.EXHAUSTION_GAP,
                    strength=min(divergence_strength * 2, 1.0),
                    confidence=0.75,
                    timestamp=datetime.now(),
                    candle_count=10,
                    volume_surge=self._check_volume_surge(recent_data)
                )
        
        return None
    
    def _calculate_rsi(self, price_data: pd.DataFrame, period: int = 14) -> pd.Series:
        """RSI (Relative Strength Index) 계산"""
        close_prices = price_data['close']
        
        # 가격 변화량 계산
        delta = close_prices.diff()
        
        # 상승분과 하락분 분리
        gains = delta.where(delta > 0, 0)
        losses = -delta.where(delta < 0, 0)
        
        # 평균 상승/하락 계산
        avg_gains = gains.rolling(window=period).mean()
        avg_losses = losses.rolling(window=period).mean()
        
        # RS와 RSI 계산
        rs = avg_gains / avg_losses
        rsi = 100 - (100 / (1 + rs))
        
        return rsi


# 모듈 익스포트
__all__ = [
    'ReversalPattern',
    'ReversalSignal', 
    'TrendReversalDetector',
    'AdvancedReversalDetector'
]