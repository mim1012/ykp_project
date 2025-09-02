"""
Desktop Widgets Package

Custom PyQt5 widgets for the crypto trading system.
"""

from .trading_panel import TradingPanel
from .positions_widget import PositionsWidget
from .orderbook_widget import OrderBookWidget
from .chart_widget import ChartWidget
from .log_widget import LogWidget
from .performance_widget import PerformanceWidget
from .risk_widget import RiskWidget
from .config_widget import ConfigWidget

__all__ = [
    'TradingPanel',
    'PositionsWidget',
    'OrderBookWidget', 
    'ChartWidget',
    'LogWidget',
    'PerformanceWidget',
    'RiskWidget',
    'ConfigWidget'
]