"""
Desktop GUI Package

PyQt5-based desktop application for the crypto trading system.
Provides comprehensive trading interface, monitoring, and configuration.
"""

__version__ = "1.0.0"
__author__ = "Crypto Trading System Team"

from .main_window import MainWindow
from .widgets import *
from .dialogs import *
from .utils import *

__all__ = [
    'MainWindow'
]