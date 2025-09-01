"""Pytest configuration and fixtures."""

import pytest
import asyncio
from unittest.mock import Mock

from core.logger import SystemLogger
from core.security_module import SecurityModule
from core.config_manager import ConfigManager
from core.risk_manager import RiskManager


@pytest.fixture
def mock_logger():
    """Mock logger fixture."""
    return Mock(spec=SystemLogger)


@pytest.fixture  
def security_module(mock_logger):
    """Security module fixture."""
    return SecurityModule(mock_logger)


@pytest.fixture
def config_manager(security_module, mock_logger):
    """Configuration manager fixture."""
    return ConfigManager(security_module, mock_logger)


@pytest.fixture
def risk_manager(mock_logger):
    """Risk manager fixture."""
    return RiskManager(mock_logger)