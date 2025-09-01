"""Unit tests for TradingEngine."""

import pytest
from unittest.mock import Mock, AsyncMock

from core.trading_engine import TradingEngine, TradingSignal, Position


@pytest.mark.asyncio
class TestTradingEngine:
    """Test cases for TradingEngine."""
    
    @pytest.fixture
    def trading_engine(self, mock_logger):
        """Create trading engine with mocked dependencies."""
        risk_manager = Mock()
        binance_connector = Mock()
        bybit_connector = Mock()
        data_processor = Mock()
        
        return TradingEngine(
            risk_manager=risk_manager,
            binance_connector=binance_connector,
            bybit_connector=bybit_connector,
            data_processor=data_processor,
            logger=mock_logger
        )
    
    async def test_initialization(self, trading_engine):
        """Test trading engine initialization."""
        assert trading_engine.active_positions == {}
        assert trading_engine.signal_history == []
        assert not trading_engine.is_running
        
    async def test_start_stop(self, trading_engine):
        """Test start and stop functionality."""
        # Mock the connectors
        trading_engine.binance.connect = AsyncMock()
        trading_engine.bybit.connect = AsyncMock()
        trading_engine.binance.disconnect = AsyncMock()
        trading_engine.bybit.disconnect = AsyncMock()
        
        # Test start
        await trading_engine.start()
        assert trading_engine.is_running
        
        # Test stop  
        await trading_engine.stop()
        assert not trading_engine.is_running