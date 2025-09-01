"""
Method Helpers

긴 메서드들을 작은 함수로 분할하기 위한 헬퍼 함수들.
클린 코드 가이드라인에 따라 메서드 길이를 20줄 이하로 유지.
"""

from typing import Dict, List, Optional, Tuple, Any
from datetime import datetime
import pandas as pd
import numpy as np

from .constants import TradingConstants, ValidationConstants
from .exceptions import InvalidSignalError, InsufficientDataError, ParameterValidationError


def validate_market_data(market_data: Dict[str, Any], symbol: str) -> bool:
    """시장 데이터 유효성 검증"""
    
    if not market_data or 'tickers' not in market_data:
        raise InsufficientDataError('market_data', 1, 0)
    
    if symbol not in market_data['tickers']:
        raise InsufficientDataError(f'ticker_data_{symbol}', 1, 0)
    
    ticker = market_data['tickers'][symbol]
    if not ticker or 'price' not in ticker:
        raise InvalidMarketDataError(symbol, 'price')
    
    return True


def validate_price_range(price: float, symbol: str) -> bool:
    """가격 범위 검증"""
    
    if price <= ValidationConstants.MIN_PRICE:
        raise ParameterValidationError(
            'price', price, 'float', 
            f'>{ValidationConstants.MIN_PRICE}'
        )
    
    if price > ValidationConstants.MAX_PRICE:
        raise ParameterValidationError(
            'price', price, 'float',
            f'<{ValidationConstants.MAX_PRICE}'
        )
    
    return True


def validate_quantity_range(quantity: float, symbol: str) -> bool:
    """수량 범위 검증"""
    
    if quantity <= ValidationConstants.MIN_QUANTITY:
        raise ParameterValidationError(
            'quantity', quantity, 'float',
            f'>{ValidationConstants.MIN_QUANTITY}'
        )
    
    if quantity > ValidationConstants.MAX_QUANTITY:
        raise ParameterValidationError(
            'quantity', quantity, 'float',
            f'<{ValidationConstants.MAX_QUANTITY}'
        )
    
    return True


def calculate_signal_strength(
    base_strength: float, 
    volume_multiplier: float = 1.0,
    volatility_multiplier: float = 1.0
) -> float:
    """신호 강도 계산 (표준화된 공식)"""
    
    # 기본 강도에 각종 승수 적용
    adjusted_strength = base_strength * volume_multiplier * volatility_multiplier
    
    # 0.0 ~ 1.0 범위로 제한
    return max(0.0, min(1.0, adjusted_strength))


def is_candle_bullish(candle: Dict[str, float]) -> bool:
    """양봉 여부 확인"""
    return candle['close'] > candle['open']


def is_candle_bearish(candle: Dict[str, float]) -> bool:
    """음봉 여부 확인"""
    return candle['close'] < candle['open']


def calculate_candle_body_ratio(candle: Dict[str, float]) -> float:
    """캔들 몸통 비율 계산"""
    
    body_size = abs(candle['close'] - candle['open'])
    total_range = candle['high'] - candle['low']
    
    if total_range <= 0:
        return 0.0
    
    return body_size / total_range


def calculate_candle_shadow_ratios(candle: Dict[str, float]) -> Tuple[float, float]:
    """캔들 위/아래 그림자 비율 계산"""
    
    body_top = max(candle['open'], candle['close'])
    body_bottom = min(candle['open'], candle['close'])
    body_size = abs(candle['close'] - candle['open'])
    
    if body_size <= 0:
        return 0.0, 0.0
    
    upper_shadow = candle['high'] - body_top
    lower_shadow = body_bottom - candle['low']
    
    upper_ratio = upper_shadow / body_size
    lower_ratio = lower_shadow / body_size
    
    return upper_ratio, lower_ratio


def check_consecutive_candle_pattern(
    candles: pd.DataFrame, 
    pattern_type: str, 
    min_count: int = 2
) -> bool:
    """연속 캔들 패턴 확인"""
    
    if len(candles) < min_count:
        return False
    
    if pattern_type == 'bullish':
        return all(
            candle['close'] > candle['open']
            for _, candle in candles.tail(min_count).iterrows()
        )
    elif pattern_type == 'bearish':
        return all(
            candle['close'] < candle['open']
            for _, candle in candles.tail(min_count).iterrows()
        )
    
    return False


def calculate_volume_surge_ratio(
    recent_volume: float, 
    average_volume: float
) -> float:
    """볼륨 급증 비율 계산"""
    
    if average_volume <= 0:
        return 0.0
    
    return recent_volume / average_volume


def is_volume_surge_significant(
    recent_volume: float,
    average_volume: float,
    surge_threshold: float = 1.5
) -> bool:
    """의미있는 볼륨 급증 여부 확인"""
    
    surge_ratio = calculate_volume_surge_ratio(recent_volume, average_volume)
    return surge_ratio >= surge_threshold


def calculate_price_change_percent(old_price: float, new_price: float) -> float:
    """가격 변화율 계산"""
    
    if old_price <= 0:
        return 0.0
    
    return (new_price - old_price) / old_price


def normalize_timeframe_data(
    data: pd.DataFrame, 
    required_columns: List[str] = None
) -> pd.DataFrame:
    """시간프레임 데이터 정규화"""
    
    required_columns = required_columns or ['open', 'high', 'low', 'close', 'volume']
    
    # 필수 컬럼 확인
    missing_columns = [col for col in required_columns if col not in data.columns]
    if missing_columns:
        raise InsufficientDataError(
            f'columns_{missing_columns}', 
            len(required_columns), 
            len(required_columns) - len(missing_columns)
        )
    
    # 데이터 타입 확인 및 변환
    numeric_columns = ['open', 'high', 'low', 'close', 'volume']
    for col in numeric_columns:
        if col in data.columns:
            data[col] = pd.to_numeric(data[col], errors='coerce')
    
    # NaN 값 확인
    if data[required_columns].isnull().any().any():
        raise InvalidMarketDataError('unknown', 'null_values')
    
    return data


def calculate_moving_average(
    prices: pd.Series, 
    period: int, 
    ma_type: str = 'simple'
) -> pd.Series:
    """이동평균 계산"""
    
    if len(prices) < period:
        raise InsufficientDataError('price_data', period, len(prices))
    
    if ma_type == 'simple':
        return prices.rolling(window=period).mean()
    elif ma_type == 'exponential':
        return prices.ewm(span=period).mean()
    else:
        raise ParameterValidationError(
            'ma_type', ma_type, 'str', 
            'simple, exponential'
        )


def calculate_atr(
    high: pd.Series, 
    low: pd.Series, 
    close: pd.Series, 
    period: int = 14
) -> pd.Series:
    """Average True Range 계산"""
    
    if len(high) < period or len(low) < period or len(close) < period:
        raise InsufficientDataError('ohlc_data', period, min(len(high), len(low), len(close)))
    
    # True Range 계산
    close_prev = close.shift(1)
    tr1 = high - low
    tr2 = abs(high - close_prev)
    tr3 = abs(low - close_prev)
    
    true_range = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
    atr = true_range.rolling(window=period).mean()
    
    return atr


def is_breakout_confirmed(
    current_price: float,
    breakout_level: float,
    direction: str,
    threshold_percent: float = None
) -> bool:
    """돌파 확인"""
    
    threshold_percent = threshold_percent or TradingConstants.CHANNEL_BREAKOUT_THRESHOLD
    
    if direction.upper() == 'BUY':
        breakout_price = breakout_level * (1 + threshold_percent)
        return current_price > breakout_price
    elif direction.upper() == 'SELL':
        breakout_price = breakout_level * (1 - threshold_percent)
        return current_price < breakout_price
    
    return False


def format_signal_for_logging(signal: Dict[str, Any]) -> str:
    """로깅용 신호 포맷팅"""
    
    direction = signal.get('direction', 'Unknown')
    symbol = signal.get('symbol', 'Unknown') 
    strength = signal.get('strength', 0.0)
    entry_price = signal.get('entry_price', 0.0)
    
    return f"{direction} {symbol} @ {entry_price:.6f} (강도: {strength:.2f})"


def create_performance_summary(
    execution_times: List[float],
    target_time_ms: float = None
) -> Dict[str, Any]:
    """성능 요약 생성"""
    
    target_time_ms = target_time_ms or PerformanceTargets.SIGNAL_GENERATION_TARGET_MS
    
    if not execution_times:
        return {'message': 'No performance data available'}
    
    avg_time = np.mean(execution_times)
    max_time = np.max(execution_times)
    min_time = np.min(execution_times)
    
    return {
        'average_time_ms': avg_time,
        'max_time_ms': max_time,
        'min_time_ms': min_time,
        'target_time_ms': target_time_ms,
        'target_achieved': avg_time <= target_time_ms,
        'total_samples': len(execution_times)
    }


def split_large_position(
    total_quantity: float,
    split_ratios: List[float]
) -> List[float]:
    """큰 포지션을 비율에 따라 분할"""
    
    if abs(sum(split_ratios) - 1.0) > 0.001:  # 허용 오차 0.1%
        raise ParameterValidationError(
            'split_ratios', split_ratios, 'List[float]',
            'sum must equal 1.0'
        )
    
    quantities = []
    remaining_quantity = total_quantity
    
    for i, ratio in enumerate(split_ratios):
        if i == len(split_ratios) - 1:
            # 마지막 분할은 남은 수량 전부
            quantities.append(remaining_quantity)
        else:
            quantity = total_quantity * ratio
            quantities.append(quantity)
            remaining_quantity -= quantity
    
    return quantities


# 모듈 익스포트
__all__ = [
    'validate_market_data',
    'validate_price_range', 
    'validate_quantity_range',
    'calculate_signal_strength',
    'is_candle_bullish',
    'is_candle_bearish',
    'calculate_candle_body_ratio',
    'calculate_candle_shadow_ratios',
    'check_consecutive_candle_pattern',
    'calculate_volume_surge_ratio',
    'is_volume_surge_significant',
    'calculate_price_change_percent',
    'normalize_timeframe_data',
    'calculate_moving_average',
    'calculate_atr',
    'is_breakout_confirmed',
    'format_signal_for_logging',
    'create_performance_summary',
    'split_large_position'
]