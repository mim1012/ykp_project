"""Unit tests for RiskManager."""

import pytest
from core.risk_manager import RiskManager, RiskLevel


class TestRiskManager:
    """Test cases for RiskManager."""
    
    def test_initialization(self, risk_manager):
        """Test risk manager initialization."""
        assert risk_manager.initial_capital == 10000.0
        assert risk_manager.current_capital == 10000.0
        assert not risk_manager.emergency_stop
        
    def test_risk_level_calculation(self, risk_manager):
        """Test risk level calculation."""
        # Test default level
        risk_level = risk_manager.get_current_risk_level()
        assert risk_level == RiskLevel.VERY_LOW
        
        # Test with high drawdown
        risk_manager.current_capital = 8000  # 20% drawdown
        risk_level = risk_manager.get_current_risk_level()
        assert risk_level == RiskLevel.EMERGENCY