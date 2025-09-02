"""
Exit Strategies Module

Implements various exit strategies including PCS (Price Channel System) liquidation,
trailing stops, and advanced risk management techniques.
"""

from .pcs_exit_system import (
    PCSPosition,
    PCSStage,
    PCSConfig,
    PCSExitSystem,
    PCSExitExecutor,
    PCSPerformanceAnalyzer
)
from .price_channel_calculator import (
    PriceChannelConfig,
    PriceChannelCalculator,
    PriceChannelBreakoutDetector
)
from .trend_reversal_detector import (
    TrendReversalDetector,
    ReversalPattern,
    ReversalSignal
)

__all__ = [
    'PCSPosition',
    'PCSStage', 
    'PCSConfig',
    'PCSExitSystem',
    'PCSExitExecutor',
    'PCSPerformanceAnalyzer',
    'PriceChannelConfig',
    'PriceChannelCalculator',
    'PriceChannelBreakoutDetector',
    'TrendReversalDetector',
    'ReversalPattern',
    'ReversalSignal'
]