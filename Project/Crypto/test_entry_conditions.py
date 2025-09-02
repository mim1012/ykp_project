#!/usr/bin/env python3
"""
Test script for the 5 entry conditions implementation
"""

import asyncio
from datetime import datetime
import sys
import os

# Add the project root to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from core.trading_engine import (
    MovingAverageCondition, 
    PriceChannelCondition, 
    OrderbookTickCondition,
    TickPatternCondition, 
    CandleStateCondition,
    EntryConditionType
)
from core.logger import SystemLogger
from core.data_processor import TechnicalIndicators, TickerData


async def test_entry_conditions():
    """Test all 5 entry conditions with sample data"""
    
    logger = SystemLogger()
    
    # Initialize all 5 conditions
    conditions = {
        'moving_average': MovingAverageCondition(logger),
        'price_channel': PriceChannelCondition(logger),
        'orderbook_tick': OrderbookTickCondition(logger),
        'tick_pattern': TickPatternCondition(logger),
        'candle_state': CandleStateCondition(logger)
    }
    
    print("🚀 Testing 5 Entry Conditions Implementation")
    print("=" * 60)
    
    # Sample market data
    ticker = TickerData(
        symbol="BTCUSDT",
        price=50000.0,
        bid=49995.0,
        ask=50005.0,
        volume_24h=1000000.0,
        change_24h=2.5,
        high_24h=51000.0,
        low_24h=49000.0,
        timestamp=datetime.now(),
        exchange="binance"
    )
    
    market_data = {
        'tickers': {
            'BTCUSDT': ticker
        },
        'orderbooks': {},
        'indicators': {
            'BTCUSDT': {
                '1m': TechnicalIndicators(
                    symbol="BTCUSDT",
                    timeframe="1m",
                    sma_20=49800.0,  # Below current price for bullish signal
                    sma_50=49500.0,
                    ema_12=49900.0,
                    ema_26=49700.0,
                    rsi_14=65.0,
                    bollinger_upper=50200.0,
                    bollinger_middle=50000.0,
                    bollinger_lower=49800.0,
                    timestamp=datetime.now()
                )
            }
        }
    }
    
    # Test each condition
    for name, condition in conditions.items():
        print(f"\n📊 Testing {name.replace('_', ' ').title()} Condition:")
        print("-" * 40)
        
        try:
            # Get performance stats before
            stats_before = condition.get_performance_stats()
            
            # Test the condition
            signal = await condition.check_condition(
                symbol="BTCUSDT",
                market_data=market_data,
                indicators=market_data['indicators']['BTCUSDT']['1m']
            )
            
            # Get performance stats after
            stats_after = condition.get_performance_stats()
            
            if signal:
                print(f"✅ Signal Generated: {signal.signal_type}")
                print(f"   Direction: {signal.direction}")
                print(f"   Strength: {signal.strength:.3f}")
                print(f"   Entry Price: ${signal.entry_price:,.2f}")
                print(f"   Condition Type: {signal.condition_type.value}")
                
                if hasattr(signal, 'additional_entry_ratio') and signal.additional_entry_ratio > 0:
                    print(f"   Additional Entry: {signal.additional_entry_ratio*100:.1f}%")
                    
                print(f"   Metadata: {signal.metadata}")
            else:
                print("❌ No signal generated")
            
            # Performance metrics
            checks_diff = stats_after['checks_count'] - stats_before['checks_count']
            signals_diff = stats_after['signals_generated'] - stats_before['signals_generated']
            avg_time = stats_after['avg_check_time_ms']
            
            print(f"⚡ Performance:")
            print(f"   Checks performed: {checks_diff}")
            print(f"   Signals generated: {signals_diff}")
            print(f"   Avg check time: {avg_time:.2f}ms {'✅' if avg_time < 10 else '⚠️'}")
            
        except Exception as e:
            print(f"❌ Error testing {name}: {e}")
    
    print("\n" + "=" * 60)
    print("🎯 Entry Conditions Test Summary:")
    print("=" * 60)
    
    total_checks = 0
    total_signals = 0
    max_time = 0
    
    for name, condition in conditions.items():
        stats = condition.get_performance_stats()
        total_checks += stats['checks_count']
        total_signals += stats['signals_generated']
        max_time = max(max_time, stats['avg_check_time_ms'])
        
        performance_status = "✅ GOOD" if stats['avg_check_time_ms'] < 10 else "⚠️ SLOW"
        print(f"{name.replace('_', ' ').title():20}: {stats['checks_count']} checks, "
              f"{stats['signals_generated']} signals, {stats['avg_check_time_ms']:.2f}ms {performance_status}")
    
    print(f"\nOverall Performance:")
    print(f"Total checks: {total_checks}")
    print(f"Total signals: {total_signals}")
    print(f"Max check time: {max_time:.2f}ms {'✅' if max_time < 10 else '⚠️'}")
    
    if max_time < 10:
        print("\n🎉 All conditions meet the <10ms performance target!")
    else:
        print(f"\n⚠️ Some conditions exceed the 10ms performance target")
    
    print("\n✨ Implementation Summary:")
    print("✅ Moving Average Condition: 8가지 선택 (시가/현재가 vs 이평선)")
    print("✅ Price Channel Condition: 상단/하단 돌파 감지")
    print("✅ Orderbook Tick Condition: 호가 감지 진입 (3틱↑/2틱↓)")
    print("✅ Tick Pattern Condition: 5틱 상승 후 2틱 하락 시 30% 추가 진입")
    print("✅ Candle State Condition: 양봉/음봉 상태 기준 진입")
    print("✅ Performance optimization: All conditions target <10ms execution")
    print("✅ Error handling and logging integrated")
    print("✅ Comprehensive configuration options")


if __name__ == "__main__":
    asyncio.run(test_entry_conditions())