"""
Test script for the 4 exit conditions system according to PRD specifications.

This script demonstrates the configuration and testing of:
1. PCS 청산 (1단~12단, 1STEP/2STEP)
2. PC 트레일링 청산 (PCT 손실중 청산)  
3. 호가 청산 (틱 기반)
4. PC 본절 청산 (2단계 시스템)
"""

import asyncio
import logging
from datetime import datetime, timedelta
from typing import Dict, Any
import sys
import os

# Add project root to path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from core.trading_engine import (
    TradingEngine, Position, ExitConditionType,
    PCSLiquidationCondition, PCTrailingCondition, 
    TickBasedExitCondition, PCBreakevenCondition
)
from core.logger import SystemLogger
from core.data_processor import TickerData, TechnicalIndicators

# Set up logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = SystemLogger("ExitConditionsTest")


class MockDataProcessor:
    """Mock data processor for testing"""
    
    def __init__(self):
        self.current_price = 50000.0
        self.price_history = []
        
    async def get_latest_data(self) -> Dict[str, Any]:
        """Return mock market data"""
        ticker = TickerData(
            symbol="BTCUSDT",
            price=self.current_price,
            bid=self.current_price - 0.5,
            ask=self.current_price + 0.5,
            high_24h=self.current_price + 1000,
            low_24h=self.current_price - 1000,
            volume_24h=1000000,
            timestamp=datetime.now()
        )
        
        return {
            'tickers': {'BTCUSDT': ticker},
            'orderbooks': {},
            'indicators': {
                'BTCUSDT': {
                    '1m': TechnicalIndicators(
                        sma_20=self.current_price - 100,
                        sma_50=self.current_price - 200,
                        sma_200=self.current_price - 500,
                        rsi=50.0,
                        macd=0.0,
                        bollinger_upper=self.current_price + 100,
                        bollinger_lower=self.current_price - 100,
                        timestamp=datetime.now()
                    )
                }
            }
        }
    
    def simulate_price_movement(self, new_price: float):
        """Simulate price movement for testing"""
        self.price_history.append(self.current_price)
        self.current_price = new_price
        if len(self.price_history) > 100:
            self.price_history = self.price_history[-100:]


async def test_pcs_liquidation():
    """Test PCS 청산 (1단~12단, 1STEP/2STEP) conditions"""
    print("\n" + "="*60)
    print("Testing PCS 청산 (1단~12단, 1STEP/2STEP)")
    print("="*60)
    
    # Create PCS liquidation condition
    pcs_condition = PCSLiquidationCondition(logger)
    
    # Configure for 1STEP liquidation
    pcs_condition.config.update({
        'enabled_levels': [1, 2, 3],
        'liquidation_type': '1STEP',
        'levels': {
            1: {'take_profit': 2.0, 'stop_loss': -1.0},
            2: {'take_profit': 4.0, 'stop_loss': -2.0},
            3: {'take_profit': 6.0, 'stop_loss': -3.0},
        }
    })
    
    # Create test position
    position = Position(
        symbol="BTCUSDT",
        side="long",
        size=1.0,
        entry_price=50000.0,
        current_price=50000.0,
        unrealized_pnl=0.0,
        timestamp=datetime.now(),
        exchange="binance"
    )
    
    mock_data = MockDataProcessor()
    
    print(f"Initial position: {position.symbol} {position.side} at {position.entry_price}")
    
    # Test take profit Level 1 (+2%)
    print(f"\n--- Testing Take Profit Level 1 (+2.0%) ---")
    mock_data.simulate_price_movement(51000.0)  # +2% profit
    position.current_price = 51000.0
    
    market_data = await mock_data.get_latest_data()
    exit_result = await pcs_condition.check_exit_condition(position, market_data)
    
    if exit_result:
        print(f"✅ PCS Level 1 Take Profit triggered!")
        print(f"   Exit Type: {exit_result['exit_type']}")
        print(f"   Size: {exit_result['size']}")
        print(f"   Reason: {exit_result['reason']}")
        print(f"   Metadata: {exit_result['metadata']}")
    else:
        print(f"❌ PCS Level 1 not triggered (expected +2.0%)")
    
    # Reset position state for next test
    position.exit_conditions_state = {}
    
    # Test stop loss Level 1 (-1%)
    print(f"\n--- Testing Stop Loss Level 1 (-1.0%) ---")
    mock_data.simulate_price_movement(49500.0)  # -1% loss
    position.current_price = 49500.0
    
    market_data = await mock_data.get_latest_data()
    exit_result = await pcs_condition.check_exit_condition(position, market_data)
    
    if exit_result:
        print(f"✅ PCS Level 1 Stop Loss triggered!")
        print(f"   Exit Type: {exit_result['exit_type']}")
        print(f"   Size: {exit_result['size']}")
        print(f"   Reason: {exit_result['reason']}")
    else:
        print(f"❌ PCS Level 1 not triggered (expected -1.0%)")
    
    # Test 2STEP liquidation
    print(f"\n--- Testing 2STEP Liquidation ---")
    pcs_condition.config['liquidation_type'] = '2STEP'
    position.exit_conditions_state = {}
    
    mock_data.simulate_price_movement(52000.0)  # +4% profit for Level 2
    position.current_price = 52000.0
    
    market_data = await mock_data.get_latest_data()
    exit_result = await pcs_condition.check_exit_condition(position, market_data)
    
    if exit_result:
        print(f"✅ PCS Level 2 First Step (50%) triggered!")
        print(f"   Exit Type: {exit_result['exit_type']}")
        print(f"   Size: {exit_result['size']} (should be 50% of position)")
        print(f"   Partial: {exit_result['partial']}")
        
        # Simulate second step
        exit_result2 = await pcs_condition.check_exit_condition(position, market_data)
        if exit_result2:
            print(f"✅ PCS Level 2 Second Step (remaining 50%) triggered!")
            print(f"   Exit Type: {exit_result2['exit_type']}")
            print(f"   Full Exit: {exit_result2['full_exit']}")
    else:
        print(f"❌ PCS Level 2 not triggered (expected +4.0%)")


async def test_pc_trailing():
    """Test PC 트레일링 청산 (PCT 손실중 청산) conditions"""
    print("\n" + "="*60)
    print("Testing PC 트레일링 청산 (PCT 손실중 청산)")
    print("="*60)
    
    pc_trailing = PCTrailingCondition(logger)
    pc_trailing.config.update({
        'channel_period': 10,  # Smaller period for testing
        'only_when_losing': True,
        'min_trail_distance_percent': 0.5,  # Lower threshold for testing
    })
    
    # Create test position in loss
    position = Position(
        symbol="BTCUSDT",
        side="long",
        size=1.0,
        entry_price=50000.0,
        current_price=49000.0,  # Start in loss
        unrealized_pnl=-1000.0,
        timestamp=datetime.now(),
        exchange="binance"
    )
    
    mock_data = MockDataProcessor()
    
    print(f"Initial position: {position.symbol} {position.side} at {position.entry_price}")
    print(f"Current price: {position.current_price} (in loss for testing)")
    
    # Build price history to establish channel
    price_sequence = [49000, 49200, 49100, 49300, 49250, 49400, 49350, 49450, 49500, 49400]
    
    for i, price in enumerate(price_sequence):
        mock_data.simulate_price_movement(price)
        position.current_price = price
        market_data = await mock_data.get_latest_data()
        
        print(f"Step {i+1}: Price = {price}")
        
        exit_result = await pc_trailing.check_exit_condition(position, market_data)
        
        if exit_result:
            print(f"✅ PC Trailing exit triggered at price {price}!")
            print(f"   Exit Type: {exit_result['exit_type']}")
            print(f"   Reason: {exit_result['reason']}")
            print(f"   Trail Distance: {exit_result['metadata']['trail_distance_percent']:.2f}%")
            break
    else:
        print(f"❌ PC Trailing exit not triggered in test sequence")


async def test_tick_based_exit():
    """Test 호가 청산 (틱 기반) conditions"""
    print("\n" + "="*60)
    print("Testing 호가 청산 (틱 기반)")
    print("="*60)
    
    tick_based = TickBasedExitCondition(logger)
    tick_based.config.update({
        'long_exit_down_ticks': 3,  # Lower threshold for testing
        'short_exit_up_ticks': 3,
        'tick_size_threshold': 0.01,
    })
    
    # Test long position with consecutive down ticks
    position = Position(
        symbol="BTCUSDT",
        side="long", 
        size=1.0,
        entry_price=50000.0,
        current_price=50000.0,
        unrealized_pnl=0.0,
        timestamp=datetime.now(),
        exchange="binance"
    )
    
    mock_data = MockDataProcessor()
    
    print(f"Testing LONG position - consecutive down ticks:")
    
    # Simulate consecutive down ticks
    down_tick_sequence = [50000.0, 49995.0, 49990.0, 49985.0]  # 3 consecutive down ticks
    
    for i, price in enumerate(down_tick_sequence):
        mock_data.simulate_price_movement(price)
        position.current_price = price
        market_data = await mock_data.get_latest_data()
        
        print(f"Tick {i+1}: Price = {price}")
        
        exit_result = await tick_based.check_exit_condition(position, market_data)
        
        if exit_result:
            print(f"✅ Tick-based exit triggered after {i+1} ticks!")
            print(f"   Exit Type: {exit_result['exit_type']}")
            print(f"   Down Ticks Count: {exit_result['metadata']['down_ticks_count']}")
            print(f"   Exit Price Type: {exit_result['metadata']['exit_price_type']}")
            break
    else:
        print(f"❌ Tick-based exit not triggered")
    
    # Test short position with consecutive up ticks  
    print(f"\nTesting SHORT position - consecutive up ticks:")
    
    position.side = "short"
    position.current_price = 50000.0
    tick_based.tick_history = {}  # Reset tick history
    
    # Simulate consecutive up ticks
    up_tick_sequence = [50000.0, 50005.0, 50010.0, 50015.0]  # 3 consecutive up ticks
    
    for i, price in enumerate(up_tick_sequence):
        mock_data.simulate_price_movement(price)
        position.current_price = price
        market_data = await mock_data.get_latest_data()
        
        print(f"Tick {i+1}: Price = {price}")
        
        exit_result = await tick_based.check_exit_condition(position, market_data)
        
        if exit_result:
            print(f"✅ Tick-based exit triggered after {i+1} ticks!")
            print(f"   Exit Type: {exit_result['exit_type']}")
            print(f"   Up Ticks Count: {exit_result['metadata']['up_ticks_count']}")
            break
    else:
        print(f"❌ Tick-based exit not triggered")


async def test_pc_breakeven():
    """Test PC 본절 청산 (2단계 시스템) conditions"""
    print("\n" + "="*60)
    print("Testing PC 본절 청산 (2단계 시스템)")
    print("="*60)
    
    pc_breakeven = PCBreakevenCondition(logger)
    pc_breakeven.config.update({
        'channel_period': 10,  # Smaller for testing
        'breakout_confirmation_percent': 0.5,  # Lower threshold
        'breakeven_tolerance_percent': 0.1,    # Tighter tolerance
    })
    
    position = Position(
        symbol="BTCUSDT",
        side="long",
        size=1.0,
        entry_price=50000.0,
        current_price=50000.0,
        unrealized_pnl=0.0,
        timestamp=datetime.now(),
        exchange="binance"
    )
    
    mock_data = MockDataProcessor()
    
    print(f"Initial position: {position.symbol} {position.side} at {position.entry_price}")
    
    # Stage 1: Build price channel and trigger breakout
    print(f"\n--- Stage 1: Building price channel and triggering breakout ---")
    
    # Build channel around 49500-50500
    channel_prices = [49500, 49600, 49700, 49800, 49900, 50000, 50100, 50200, 50300, 50400]
    
    for price in channel_prices:
        mock_data.simulate_price_movement(price)
        position.current_price = price
        market_data = await mock_data.get_latest_data()
        
        exit_result = await pc_breakeven.check_exit_condition(position, market_data)
        if exit_result:
            print(f"❌ Unexpected exit in stage 1 at price {price}")
    
    # Trigger upper breakout (above 50400 + 0.5% = 50625)
    breakout_price = 50630.0
    print(f"Triggering breakout at {breakout_price}")
    
    mock_data.simulate_price_movement(breakout_price)
    position.current_price = breakout_price
    market_data = await mock_data.get_latest_data()
    
    exit_result = await pc_breakeven.check_exit_condition(position, market_data)
    if exit_result:
        print(f"❌ Unexpected exit during breakout")
    else:
        print(f"✅ Stage 1 completed - breakout detected")
    
    # Stage 2: Return to entry price area
    print(f"\n--- Stage 2: Testing return to entry price (breakeven) ---")
    
    # Simulate return to entry price area (50000 ± 0.1% = 49950-50050)
    return_price = 50025.0  # Within tolerance
    print(f"Price returning to entry area: {return_price}")
    
    mock_data.simulate_price_movement(return_price)
    position.current_price = return_price
    market_data = await mock_data.get_latest_data()
    
    exit_result = await pc_breakeven.check_exit_condition(position, market_data)
    
    if exit_result:
        print(f"✅ PC Breakeven exit triggered!")
        print(f"   Exit Type: {exit_result['exit_type']}")
        print(f"   Entry Price: {exit_result['metadata']['entry_price']}")
        print(f"   Current Price: {exit_result['metadata']['current_price']}")
        print(f"   Breakout Price: {exit_result['metadata']['breakout_price']}")
        print(f"   Stage 1 Duration: {exit_result['metadata']['stage1_duration_minutes']:.1f} minutes")
    else:
        print(f"❌ PC Breakeven exit not triggered")


async def main():
    """Main test function"""
    print("Starting Exit Conditions Test Suite")
    print("Testing 4 exit conditions according to PRD specifications:")
    print("1. PCS 청산 (1단~12단, 1STEP/2STEP)")
    print("2. PC 트레일링 청산 (PCT 손실중 청산)")  
    print("3. 호가 청산 (틱 기반)")
    print("4. PC 본절 청산 (2단계 시스템)")
    
    try:
        await test_pcs_liquidation()
        await test_pc_trailing()
        await test_tick_based_exit()
        await test_pc_breakeven()
        
        print("\n" + "="*60)
        print("✅ All exit condition tests completed!")
        print("="*60)
        
    except Exception as e:
        print(f"\n❌ Error during testing: {e}")
        import traceback
        traceback.print_exc()


if __name__ == "__main__":
    asyncio.run(main())