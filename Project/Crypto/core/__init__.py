"""
Core trading system modules.

This package contains all the business logic for the crypto trading system,
including trading engine, risk management, API connectors, and security modules.
"""

__version__ = "1.0.0"
__author__ = "Crypto Trading System Team"

from .trading_engine import TradingEngine
from .risk_manager import RiskManager
from .api_connector import BinanceConnector, BybitConnector
from .config_manager import ConfigManager
from .security_module import SecurityModule
from .time_controller import TimeController
from .data_processor import DataProcessor
from .logger import SystemLogger

__all__ = [
    'TradingEngine',
    'RiskManager', 
    'BinanceConnector',
    'BybitConnector',
    'ConfigManager',
    'SecurityModule',
    'TimeController',
    'DataProcessor',
    'SystemLogger'
]