"""
Trading Engine Module

Implements the main trading engine with 5 entry conditions and 4 exit conditions
as specified in the PRD. Handles strategy execution, position management,
and signal generation.
"""

from typing import Dict, List, Optional, Tuple, Any, Union
from enum import Enum
from dataclasses import dataclass
from datetime import datetime, timedelta
import asyncio
import logging
from abc import ABC, abstractmethod
import numpy as np

from .risk_manager import RiskManager
from .api_connector import BinanceConnector, BybitConnector
from .data_processor import DataProcessor, TickerData, KlineData, TechnicalIndicators
from .logger import SystemLogger
from .constants import TradingConstants, PCSConstants, PerformanceTargets
from .exceptions import (
    TradingError, InvalidSignalError, OrderExecutionError,
    InsufficientDataError, RiskLimitExceededError
)

# Import the new PCS 3-stage liquidation system  
# Temporarily commented out to fix import issues
# from strategies.exit_strategies import (
#     PCSPosition, PCSExitExecutor, TrendReversalDetector, 
#     PerformanceAnalyzer, PCSLiquidationStage, ExitUrgency,
#     PriceChannelCalculator, ChannelBreakoutDetector
# )


class EntryConditionType(Enum):
    """Entry condition types as per PRD specifications"""
    MOVING_AVERAGE = "moving_average"
    PRICE_CHANNEL = "price_channel"
    ORDERBOOK_TICK = "orderbook_tick"
    TICK_PATTERN = "tick_pattern"
    CANDLE_STATE = "candle_state"


class ExitConditionType(Enum):
    """Exit condition types as per PRD specifications"""
    PCS_LIQUIDATION = "pcs_liquidation"        # PCS 청산 (1단~12단, 1STEP/2STEP)
    PC_TRAILING = "pc_trailing"                # PC 트레일링 청산 (PCT 손실중 청산)
    TICK_BASED = "tick_based"                  # 호가 청산 (틱 기반)
    PC_BREAKEVEN = "pc_breakeven"              # PC 본절 청산 (2단계 시스템)


class ExitCondition(Enum):
    """Legacy exit condition types"""
    TAKE_PROFIT = "take_profit"
    STOP_LOSS = "stop_loss"
    TIME_BASED = "time_based"
    SIGNAL_REVERSAL = "signal_reversal"


@dataclass
class TradingSignal:
    """Trading signal data structure"""
    symbol: str
    signal_type: str
    direction: str  # 'long' or 'short'
    strength: float  # 0.0 to 1.0
    entry_price: float
    timestamp: datetime
    metadata: Dict[str, Any]
    condition_type: EntryConditionType
    additional_entry_ratio: float = 0.0  # For tick-based additional entries


class EntryCondition(ABC):
    """Base class for entry conditions"""
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        """Initialize entry condition.
        
        Args:
            logger: System logger instance
            enabled: Whether this condition is enabled
        """
        self.logger = logger
        self.enabled = enabled
        self.last_check_time = datetime.now()
        self.performance_stats = {
            'checks_count': 0,
            'signals_generated': 0,
            'avg_check_time_ms': 0.0
        }
    
    @abstractmethod
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check if entry condition is met.
        
        Args:
            symbol: Trading symbol
            market_data: Latest market data
            indicators: Technical indicators data
            
        Returns:
            TradingSignal if condition is met, None otherwise
        """
        pass
    
    def _performance_track_start(self) -> float:
        """Start performance tracking."""
        import time
        return time.perf_counter()
    
    def _performance_track_end(self, start_time: float) -> None:
        """End performance tracking."""
        import time
        elapsed = (time.perf_counter() - start_time) * 1000  # Convert to ms
        
        self.performance_stats['checks_count'] += 1
        self.performance_stats['avg_check_time_ms'] = (
            (self.performance_stats['avg_check_time_ms'] * (self.performance_stats['checks_count'] - 1) + elapsed)
            / self.performance_stats['checks_count']
        )
        
        if elapsed > 10:  # Log if check takes more than 10ms
            self.logger.warning(f"{self.__class__.__name__} check took {elapsed:.2f}ms (target: <10ms)")
    
    def get_performance_stats(self) -> Dict[str, Any]:
        """Get performance statistics."""
        return self.performance_stats.copy()


@dataclass
class Position:
    """Position data structure"""
    symbol: str
    side: str  # 'long' or 'short'
    size: float
    entry_price: float
    current_price: float
    unrealized_pnl: float
    timestamp: datetime
    exchange: str
    # Exit condition tracking
    exit_conditions_state: Dict[str, Any] = None
    partial_exit_history: List[Dict[str, Any]] = None
    
    def __post_init__(self):
        """Initialize exit condition state after object creation."""
        if self.exit_conditions_state is None:
            self.exit_conditions_state = {}
        if self.partial_exit_history is None:
            self.partial_exit_history = []


class TradingEngine:
    """
    Main trading engine class implementing strategy logic.
    
    Features:
    - 5 entry conditions for signal generation
    - 4 exit conditions for position management
    - Real-time market data processing
    - Risk-aware position sizing
    - Multi-exchange execution
    """
    
    def __init__(
        self,
        risk_manager: RiskManager,
        binance_connector: BinanceConnector,
        bybit_connector: BybitConnector,
        data_processor: DataProcessor,
        logger: SystemLogger
    ):
        """Initialize trading engine with required components."""
        self.risk_manager = risk_manager
        self.binance = binance_connector
        self.bybit = bybit_connector
        self.data_processor = data_processor
        self.logger = logger
        
        self.active_positions: Dict[str, Position] = {}
        self.signal_history: List[TradingSignal] = []
        self.is_running: bool = False
        self.strategy_config: Dict[str, Any] = {}
        
        # Initialize the 5 entry conditions as per PRD
        self.ma_condition = MovingAverageCondition(logger)
        self.price_channel_condition = PriceChannelCondition(logger)
        self.orderbook_tick_condition = OrderbookTickCondition(logger)
        self.tick_pattern_condition = TickPatternCondition(logger)
        self.candle_state_condition = CandleStateCondition(logger)
        
        # Entry conditions list for easy iteration
        self.entry_conditions = [
            self.ma_condition,
            self.price_channel_condition,
            self.orderbook_tick_condition,
            self.tick_pattern_condition,
            self.candle_state_condition
        ]
        
        # Initialize the 4 exit conditions as per PRD specifications
        self.pcs_liquidation = PCSLiquidationCondition(logger)
        self.pc_trailing = PCTrailingCondition(logger)  
        self.tick_based_exit = TickBasedExitCondition(logger)
        self.pc_breakeven = PCBreakevenCondition(logger)
        
        # Exit conditions list for easy iteration
        self.exit_conditions = [
            self.pcs_liquidation,
            self.pc_trailing,
            self.tick_based_exit,
            self.pc_breakeven
        ]
        
    async def start(self) -> None:
        """Start the trading engine."""
        self.logger.info("Starting trading engine...")
        self.is_running = True
        
        # Initialize connections
        await self.binance.connect()
        await self.bybit.connect()
        
        # Start main trading loop
        await self._main_loop()
        
    async def stop(self) -> None:
        """Stop the trading engine gracefully."""
        self.logger.info("Stopping trading engine...")
        self.is_running = False
        
        # Close all positions
        await self._close_all_positions()
        
        # Disconnect from exchanges
        await self.binance.disconnect()
        await self.bybit.disconnect()
        
    async def _main_loop(self) -> None:
        """Main trading loop."""
        while self.is_running:
            try:
                # Process market data
                market_data = await self.data_processor.get_latest_data()
                
                # Generate signals
                signals = await self._generate_signals(market_data)
                
                # Process entry conditions
                for signal in signals:
                    await self._process_entry_signal(signal)
                    
                # Process exit conditions
                await self._process_exit_conditions()
                
                # Update positions
                await self._update_positions()
                
                # Sleep before next iteration
                await asyncio.sleep(1)
                
            except Exception as e:
                self.logger.error(f"Error in main trading loop: {e}")
                await asyncio.sleep(5)
                
    # Entry Conditions Implementation (5 conditions as per PRD)
    
    async def _generate_signals(self, market_data: Dict[str, Any]) -> List[TradingSignal]:
        """Generate trading signals based on 5 entry conditions specified in PRD."""
        signals = []
        
        # Get symbols to analyze
        symbols = list(market_data.get('tickers', {}).keys())
        
        for symbol in symbols:
            if not market_data['tickers'][symbol]:
                continue
                
            # Get technical indicators for the symbol
            indicators = market_data.get('indicators', {}).get(symbol, {}).get('1m')
            
            # Entry Condition 1: Moving Average Condition (이동평균선 조건)
            ma_signal = await self.ma_condition.check_condition(symbol, market_data, indicators)
            if ma_signal:
                signals.append(ma_signal)
            
            # Entry Condition 2: Price Channel Condition
            pc_signal = await self.price_channel_condition.check_condition(symbol, market_data, indicators)
            if pc_signal:
                signals.append(pc_signal)
            
            # Entry Condition 3: Orderbook Tick Detection (호가 감지 진입)
            tick_signal = await self.orderbook_tick_condition.check_condition(symbol, market_data, indicators)
            if tick_signal:
                signals.append(tick_signal)
            
            # Entry Condition 4: Tick Pattern Additional Entry (틱 기반 추가 진입)
            pattern_signal = await self.tick_pattern_condition.check_condition(symbol, market_data, indicators)
            if pattern_signal:
                signals.append(pattern_signal)
            
            # Entry Condition 5: Candle State Condition (캔들 상태 조건)
            candle_signal = await self.candle_state_condition.check_condition(symbol, market_data, indicators)
            if candle_signal:
                signals.append(candle_signal)
        
        return signals
        
        
    # Exit Conditions Implementation (PRD 4가지 청산 조건)
    
    async def _process_exit_conditions(self) -> None:
        """Process exit conditions for all active positions according to PRD specifications."""
        for symbol, position in self.active_positions.copy().items():
            try:
                # Get current market data for exit condition evaluation
                market_data = await self.data_processor.get_latest_data()
                indicators = market_data.get('indicators', {}).get(symbol, {}).get('1m')
                
                # Exit Condition 1: PCS 청산 (1단~12단, 1STEP/2STEP)
                exit_result = await self.pcs_liquidation.check_exit_condition(position, market_data, indicators)
                if exit_result:
                    await self._execute_exit_order(position, exit_result)
                    if exit_result.get('full_exit', False):
                        continue
                    
                # Exit Condition 2: PC 트레일링 청산 (PCT 손실중 청산)
                exit_result = await self.pc_trailing.check_exit_condition(position, market_data, indicators)
                if exit_result:
                    await self._execute_exit_order(position, exit_result)
                    continue
                    
                # Exit Condition 3: 호가 청산 (틱 기반)
                exit_result = await self.tick_based_exit.check_exit_condition(position, market_data, indicators)
                if exit_result:
                    await self._execute_exit_order(position, exit_result)
                    continue
                    
                # Exit Condition 4: PC 본절 청산 (2단계 시스템)
                exit_result = await self.pc_breakeven.check_exit_condition(position, market_data, indicators)
                if exit_result:
                    await self._execute_exit_order(position, exit_result)
                    continue
                    
            except Exception as e:
                self.logger.error(f"Error processing exit conditions for {symbol}: {e}")
                
    async def _execute_exit_order(self, position: Position, exit_result: Dict[str, Any]) -> None:
        """Execute exit order based on exit condition result."""
        try:
            exit_type = exit_result.get('exit_type', 'unknown')
            exit_size = exit_result.get('size', position.size)
            exit_price = exit_result.get('price', position.current_price)
            is_partial = exit_result.get('partial', False)
            
            self.logger.info(f"Executing {exit_type} exit for {position.symbol}: "
                           f"Size: {exit_size}, Price: {exit_price}, Partial: {is_partial}")
            
            # Execute the exit order
            if position.exchange == 'binance':
                order = await self.binance.place_order(
                    symbol=position.symbol,
                    side='sell' if position.side == 'long' else 'buy',
                    size=exit_size,
                    order_type='market',
                    reduce_only=True
                )
            else:
                order = await self.bybit.place_order(
                    symbol=position.symbol,
                    side='sell' if position.side == 'long' else 'buy',
                    size=exit_size,
                    order_type='market',
                    reduce_only=True
                )
            
            # Update position or remove if fully closed
            if is_partial:
                # Update position size and record partial exit
                position.size -= exit_size
                position.partial_exit_history.append({
                    'exit_type': exit_type,
                    'size': exit_size,
                    'price': exit_price,
                    'timestamp': datetime.now(),
                    'remaining_size': position.size
                })
                self.logger.info(f"Partial exit completed: {position.symbol} - "
                               f"Closed: {exit_size}, Remaining: {position.size}")
            else:
                # Full exit - remove position
                position_key = f"{position.symbol}_{position.exchange}"
                if position_key in self.active_positions:
                    del self.active_positions[position_key]
                self.logger.info(f"Full exit completed: {position.symbol} - Type: {exit_type}")
                
        except Exception as e:
            self.logger.error(f"Error executing exit order: {e}")
            
    # Legacy exit condition methods (kept for backward compatibility)
    
    async def _check_take_profit(self, position: Position) -> bool:
        """Legacy take profit check."""
        return False
        
    async def _check_stop_loss(self, position: Position) -> bool:
        """Legacy stop loss check."""
        return False
        
    async def _check_time_based_exit(self, position: Position) -> bool:
        """Legacy time-based exit check."""
        return False
        
    async def _check_signal_reversal(self, position: Position) -> bool:
        """Legacy signal reversal check."""
        return False
        
    # Position Management
    
    async def _process_entry_signal(self, signal: TradingSignal) -> None:
        """Process entry signal and create position if conditions are met."""
        # Risk management check
        if not await self.risk_manager.can_open_position(signal):
            self.logger.warning(f"Risk manager rejected signal for {signal.symbol}")
            return
            
        # Calculate position size
        position_size = await self.risk_manager.calculate_position_size(signal)
        
        # Execute trade
        await self._execute_entry_order(signal, position_size)
        
    async def _execute_entry_order(self, signal: TradingSignal, size: float) -> None:
        """Execute entry order on appropriate exchange."""
        try:
            # Choose exchange based on signal metadata or default logic
            exchange = signal.metadata.get('exchange', 'binance')
            
            if exchange == 'binance':
                order = await self.binance.place_order(
                    symbol=signal.symbol,
                    side=signal.direction,
                    size=size,
                    order_type='market'
                )
            else:
                order = await self.bybit.place_order(
                    symbol=signal.symbol,
                    side=signal.direction,
                    size=size,
                    order_type='market'
                )
                
            # Create position record
            position = Position(
                symbol=signal.symbol,
                side=signal.direction,
                size=size,
                entry_price=signal.entry_price,
                current_price=signal.entry_price,
                unrealized_pnl=0.0,
                timestamp=datetime.now(),
                exchange=exchange
            )
            
            self.active_positions[f"{signal.symbol}_{exchange}"] = position
            self.logger.info(f"Opened position: {position}")
            
        except Exception as e:
            self.logger.error(f"Error executing entry order: {e}")
            
    async def _close_position(self, position: Position, reason: str) -> None:
        """Close position with specified reason."""
        try:
            if position.exchange == 'binance':
                order = await self.binance.place_order(
                    symbol=position.symbol,
                    side='sell' if position.side == 'long' else 'buy',
                    size=position.size,
                    order_type='market'
                )
            else:
                order = await self.bybit.place_order(
                    symbol=position.symbol,
                    side='sell' if position.side == 'long' else 'buy',
                    size=position.size,
                    order_type='market'
                )
                
            # Remove from active positions
            position_key = f"{position.symbol}_{position.exchange}"
            if position_key in self.active_positions:
                del self.active_positions[position_key]
                
            self.logger.info(f"Closed position: {position.symbol} - Reason: {reason}")
            
        except Exception as e:
            self.logger.error(f"Error closing position: {e}")
            
    async def _update_positions(self) -> None:
        """Update current prices and PnL for all active positions."""
        for position_key, position in self.active_positions.items():
            try:
                # Get current price
                current_price = await self._get_current_price(position.symbol, position.exchange)
                position.current_price = current_price
                
                # Calculate unrealized PnL
                if position.side == 'long':
                    position.unrealized_pnl = (current_price - position.entry_price) * position.size
                else:
                    position.unrealized_pnl = (position.entry_price - current_price) * position.size
                    
            except Exception as e:
                self.logger.error(f"Error updating position {position_key}: {e}")
                
    async def _get_current_price(self, symbol: str, exchange: str) -> float:
        """Get current price for symbol from specified exchange."""
        if exchange == 'binance':
            return await self.binance.get_current_price(symbol)
        else:
            return await self.bybit.get_current_price(symbol)
            
    async def _close_all_positions(self) -> None:
        """Close all active positions."""
        for position in list(self.active_positions.values()):
            await self._close_position(position, "shutdown")
            
    # Public interface methods
    
    def get_active_positions(self) -> Dict[str, Position]:
        """Get all active positions."""
        return self.active_positions.copy()
        
    def get_signal_history(self) -> List[TradingSignal]:
        """Get signal history."""
        return self.signal_history.copy()
        
    def get_engine_status(self) -> Dict[str, Any]:
        """Get engine status information."""
        return {
            'is_running': self.is_running,
            'active_positions_count': len(self.active_positions),
            'total_signals_generated': len(self.signal_history),
            'uptime': datetime.now(),
            'exchanges_connected': {
                'binance': self.binance.is_connected(),
                'bybit': self.bybit.is_connected()
            }
        }
        
    def get_entry_conditions_performance(self) -> Dict[str, Dict[str, Any]]:
        """Get performance statistics for all entry conditions."""
        return {
            'moving_average': self.ma_condition.get_performance_stats(),
            'price_channel': self.price_channel_condition.get_performance_stats(),
            'orderbook_tick': self.orderbook_tick_condition.get_performance_stats(),
            'tick_pattern': self.tick_pattern_condition.get_performance_stats(),
            'candle_state': self.candle_state_condition.get_performance_stats()
        }
        
    def configure_entry_condition(self, condition_type: EntryConditionType, config: Dict[str, Any]) -> bool:
        """Configure a specific entry condition."""
        try:
            if condition_type == EntryConditionType.MOVING_AVERAGE:
                self.ma_condition.config.update(config)
            elif condition_type == EntryConditionType.PRICE_CHANNEL:
                self.price_channel_condition.config.update(config)
            elif condition_type == EntryConditionType.ORDERBOOK_TICK:
                self.orderbook_tick_condition.config.update(config)
            elif condition_type == EntryConditionType.TICK_PATTERN:
                self.tick_pattern_condition.config.update(config)
            elif condition_type == EntryConditionType.CANDLE_STATE:
                self.candle_state_condition.config.update(config)
            else:
                return False
                
            self.logger.info(f"Updated configuration for {condition_type.value}: {config}")
            return True
            
        except Exception as e:
            self.logger.error(f"Error configuring {condition_type.value}: {e}")
            return False
            
    def enable_entry_condition(self, condition_type: EntryConditionType, enabled: bool = True) -> bool:
        """Enable or disable a specific entry condition."""
        try:
            if condition_type == EntryConditionType.MOVING_AVERAGE:
                self.ma_condition.enabled = enabled
            elif condition_type == EntryConditionType.PRICE_CHANNEL:
                self.price_channel_condition.enabled = enabled
            elif condition_type == EntryConditionType.ORDERBOOK_TICK:
                self.orderbook_tick_condition.enabled = enabled
            elif condition_type == EntryConditionType.TICK_PATTERN:
                self.tick_pattern_condition.enabled = enabled
            elif condition_type == EntryConditionType.CANDLE_STATE:
                self.candle_state_condition.enabled = enabled
            else:
                return False
                
            self.logger.info(f"{'Enabled' if enabled else 'Disabled'} {condition_type.value} condition")
            return True
            
        except Exception as e:
            self.logger.error(f"Error setting {condition_type.value} enabled state: {e}")
            return False
            
    # Exit Conditions Configuration Methods
    
    def configure_exit_condition(self, condition_type: ExitConditionType, config: Dict[str, Any]) -> bool:
        """Configure a specific exit condition."""
        try:
            if condition_type == ExitConditionType.PCS_LIQUIDATION:
                self.pcs_liquidation.config.update(config)
            elif condition_type == ExitConditionType.PC_TRAILING:
                self.pc_trailing.config.update(config)
            elif condition_type == ExitConditionType.TICK_BASED:
                self.tick_based_exit.config.update(config)
            elif condition_type == ExitConditionType.PC_BREAKEVEN:
                self.pc_breakeven.config.update(config)
            else:
                return False
                
            self.logger.info(f"Updated exit condition configuration for {condition_type.value}: {config}")
            return True
            
        except Exception as e:
            self.logger.error(f"Error configuring exit condition {condition_type.value}: {e}")
            return False
            
    def enable_exit_condition(self, condition_type: ExitConditionType, enabled: bool = True) -> bool:
        """Enable or disable a specific exit condition."""
        try:
            if condition_type == ExitConditionType.PCS_LIQUIDATION:
                self.pcs_liquidation.enabled = enabled
            elif condition_type == ExitConditionType.PC_TRAILING:
                self.pc_trailing.enabled = enabled
            elif condition_type == ExitConditionType.TICK_BASED:
                self.tick_based_exit.enabled = enabled
            elif condition_type == ExitConditionType.PC_BREAKEVEN:
                self.pc_breakeven.enabled = enabled
            else:
                return False
                
            self.logger.info(f"{'Enabled' if enabled else 'Disabled'} {condition_type.value} exit condition")
            return True
            
        except Exception as e:
            self.logger.error(f"Error setting {condition_type.value} exit condition enabled state: {e}")
            return False
            
    def get_exit_conditions_performance(self) -> Dict[str, Dict[str, Any]]:
        """Get performance statistics for all exit conditions."""
        performance_data = {
            'pcs_liquidation': self.pcs_liquidation.get_performance_stats(),
            'pc_trailing': self.pc_trailing.get_performance_stats(),
            'tick_based_exit': self.tick_based_exit.get_performance_stats(),
            'pc_breakeven': self.pc_breakeven.get_performance_stats()
        }
        
        # Add comprehensive PCS 3-stage system performance
        try:
            performance_data['pcs_3_stage_system'] = self.pcs_liquidation.get_pcs_performance_report()
        except Exception as e:
            self.logger.error(f"PCS 3단계 시스템 성능 조회 중 오류: {e}")
            performance_data['pcs_3_stage_system'] = {'error': str(e)}
        
        return performance_data
        
    def get_position_exit_states(self, symbol: str, exchange: str) -> Optional[Dict[str, Any]]:
        """Get exit condition states for a specific position."""
        position_key = f"{symbol}_{exchange}"
        position = self.active_positions.get(position_key)
        
        if not position:
            return None
            
        return {
            'symbol': symbol,
            'exchange': exchange,
            'side': position.side,
            'size': position.size,
            'entry_price': position.entry_price,
            'current_price': position.current_price,
            'unrealized_pnl': position.unrealized_pnl,
            'exit_conditions_state': position.exit_conditions_state,
            'partial_exit_history': position.partial_exit_history,
            'exit_conditions_performance': self.get_exit_conditions_performance()
        }
        
    def reset_position_exit_states(self, symbol: str, exchange: str, condition_type: Optional[ExitConditionType] = None) -> bool:
        """Reset exit condition states for a specific position."""
        try:
            position_key = f"{symbol}_{exchange}"
            position = self.active_positions.get(position_key)
            
            if not position:
                self.logger.warning(f"Position not found: {position_key}")
                return False
            
            if condition_type is None:
                # Reset all exit condition states
                position.exit_conditions_state = {}
                self.logger.info(f"Reset all exit condition states for {position_key}")
            else:
                # Reset specific exit condition state
                condition_keys = {
                    ExitConditionType.PCS_LIQUIDATION: [k for k in position.exit_conditions_state.keys() if k.startswith('pcs_level_')],
                    ExitConditionType.PC_TRAILING: ['pc_trailing_state'],
                    ExitConditionType.TICK_BASED: ['tick_based_state'],  # If any tick-based state tracking needed
                    ExitConditionType.PC_BREAKEVEN: ['pc_breakeven_state']
                }
                
                keys_to_reset = condition_keys.get(condition_type, [])
                for key in keys_to_reset:
                    if key in position.exit_conditions_state:
                        del position.exit_conditions_state[key]
                        
                self.logger.info(f"Reset {condition_type.value} exit condition states for {position_key}")
            
            return True
            
        except Exception as e:
            self.logger.error(f"Error resetting exit condition states for {symbol}_{exchange}: {e}")
            return False


# ============================================================================
# 5 Entry Conditions Implementation (as per PRD specifications)
# ============================================================================

class MovingAverageCondition(EntryCondition):
    """
    이동평균선 조건 (Moving Average Condition) - Entry Condition 1
    
    Features:
    - 8가지 선택: 시가 vs 이평선, 현재가 vs 이평선 비교 (4가지씩)
    - MA 기간 설정 가능 (20, 50, 200일)
    - 실시간 계산 with pandas rolling windows
    - Performance target: <10ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'enabled_comparisons': {
                # 시가 vs 이평선 비교 (4가지)
                'open_above_ma_long': True,   # 시가 > 이평선 → 매수 진입
                'open_below_ma_short': True,  # 시가 < 이평선 → 매도 진입
                'open_above_ma_short': False, # 시가 > 이평선 → 매도 진입
                'open_below_ma_long': False,  # 시가 < 이평선 → 매수 진입
                
                # 현재가 vs 이평선 비교 (4가지)
                'current_above_ma_long': True,   # 현재가 > 이평선 → 매수 진입
                'current_below_ma_short': True,  # 현재가 < 이평선 → 매도 진입
                'current_above_ma_short': False, # 현재가 > 이평선 → 매도 진입
                'current_below_ma_long': False,  # 현재가 < 이평선 → 매수 진입
            },
            'ma_periods': [20, 50, 200],
            'primary_period': 20,
            'secondary_period': 50,
            'long_period': 200,
            'min_strength_threshold': TradingConstants.MA_MIN_STRENGTH_THRESHOLD,
            'use_multiple_timeframes': True,
            'confirmation_required': True,     # Require confirmation from multiple MAs
        }
        # Cache for MA calculations
        self.ma_cache: Dict[str, Dict[int, float]] = {}
        self.price_history: Dict[str, deque] = {}
        
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check moving average conditions with 8 comparison types."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            ticker = market_data.get('tickers', {}).get(symbol)
            klines = market_data.get('klines', {}).get(symbol, {}).get('1m')
            
            if not ticker:
                return None
            
            current_price = ticker.price
            
            # Get open price from current candle or approximate from ticker
            open_price = klines.open_price if klines else ticker.ask
            
            # Calculate MAs with real-time data
            ma_values = await self._calculate_moving_averages(symbol, current_price, market_data)
            if not ma_values:
                return None
            
            # Get primary MA for comparison
            primary_ma = ma_values.get(self.config['primary_period'])
            secondary_ma = ma_values.get(self.config['secondary_period'])
            long_ma = ma_values.get(self.config['long_period'])
            
            if primary_ma is None:
                return None
            
            signal = None
            config = self.config['enabled_comparisons']
            
            # Calculate signal strength and check minimum threshold
            open_deviation = abs(open_price - primary_ma) / primary_ma
            current_deviation = abs(current_price - primary_ma) / primary_ma
            
            if max(open_deviation, current_deviation) < self.config['min_strength_threshold']:
                return None
            
            # Check 시가 vs 이평선 조건 (4가지)
            if config['open_above_ma_long'] and open_price > primary_ma:
                confirmation = self._check_ma_confirmation(open_price, ma_values, 'long')
                if confirmation or not self.config['confirmation_required']:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_OPEN_ABOVE_LONG',
                        direction='long',
                        strength=min(open_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={
                            'comparison_type': 'open_above_ma_long',
                            'primary_ma': primary_ma,
                            'secondary_ma': secondary_ma,
                            'long_ma': long_ma,
                            'open_price': open_price,
                            'current_price': current_price,
                            'open_deviation_pct': open_deviation * 100,
                            'confirmation': confirmation,
                            'ma_periods_used': list(ma_values.keys())
                        }
                    )
            
            elif config['open_below_ma_short'] and open_price < primary_ma:
                confirmation = self._check_ma_confirmation(open_price, ma_values, 'short')
                if confirmation or not self.config['confirmation_required']:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_OPEN_BELOW_SHORT',
                        direction='short',
                        strength=min(open_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={
                            'comparison_type': 'open_below_ma_short',
                            'primary_ma': primary_ma,
                            'secondary_ma': secondary_ma,
                            'long_ma': long_ma,
                            'open_price': open_price,
                            'current_price': current_price,
                            'open_deviation_pct': open_deviation * 100,
                            'confirmation': confirmation,
                            'ma_periods_used': list(ma_values.keys())
                        }
                    )
            
            elif config['open_above_ma_short'] and open_price > primary_ma:
                confirmation = self._check_ma_confirmation(open_price, ma_values, 'short')
                if confirmation or not self.config['confirmation_required']:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_OPEN_ABOVE_SHORT',
                        direction='short',
                        strength=min(open_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={'comparison_type': 'open_above_ma_short', 'primary_ma': primary_ma, 'open_price': open_price}
                    )
            
            elif config['open_below_ma_long'] and open_price < primary_ma:
                confirmation = self._check_ma_confirmation(open_price, ma_values, 'long')
                if confirmation or not self.config['confirmation_required']:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_OPEN_BELOW_LONG',
                        direction='long',
                        strength=min(open_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={'comparison_type': 'open_below_ma_long', 'primary_ma': primary_ma, 'open_price': open_price}
                    )
            
            # Check 현재가 vs 이평선 조건 (4가지) - only if no open price signal
            if not signal:
                if config['current_above_ma_long'] and current_price > primary_ma:
                    confirmation = self._check_ma_confirmation(current_price, ma_values, 'long')
                    if confirmation or not self.config['confirmation_required']:
                        signal = TradingSignal(
                            symbol=symbol,
                            signal_type='MA_CURRENT_ABOVE_LONG',
                            direction='long',
                            strength=min(current_deviation * 10, 1.0),
                            entry_price=current_price,
                            timestamp=datetime.now(),
                            condition_type=EntryConditionType.MOVING_AVERAGE,
                            metadata={
                                'comparison_type': 'current_above_ma_long',
                                'primary_ma': primary_ma,
                                'current_price': current_price,
                                'current_deviation_pct': current_deviation * 100,
                                'confirmation': confirmation
                            }
                        )
                
                elif config['current_below_ma_short'] and current_price < primary_ma:
                    confirmation = self._check_ma_confirmation(current_price, ma_values, 'short')
                    if confirmation or not self.config['confirmation_required']:
                        signal = TradingSignal(
                            symbol=symbol,
                            signal_type='MA_CURRENT_BELOW_SHORT',
                            direction='short',
                            strength=min(current_deviation * 10, 1.0),
                            entry_price=current_price,
                            timestamp=datetime.now(),
                            condition_type=EntryConditionType.MOVING_AVERAGE,
                            metadata={
                                'comparison_type': 'current_below_ma_short',
                                'primary_ma': primary_ma,
                                'current_price': current_price,
                                'current_deviation_pct': current_deviation * 100,
                                'confirmation': confirmation
                            }
                        )
                
                elif config['current_above_ma_short'] and current_price > primary_ma:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_CURRENT_ABOVE_SHORT',
                        direction='short',
                        strength=min(current_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={'comparison_type': 'current_above_ma_short', 'primary_ma': primary_ma}
                    )
                
                elif config['current_below_ma_long'] and current_price < primary_ma:
                    signal = TradingSignal(
                        symbol=symbol,
                        signal_type='MA_CURRENT_BELOW_LONG',
                        direction='long',
                        strength=min(current_deviation * 10, 1.0),
                        entry_price=current_price,
                        timestamp=datetime.now(),
                        condition_type=EntryConditionType.MOVING_AVERAGE,
                        metadata={'comparison_type': 'current_below_ma_long', 'primary_ma': primary_ma}
                    )
            
            if signal:
                self.performance_stats['signals_generated'] += 1
                self.logger.debug(f"MA condition signal generated for {symbol}: {signal.signal_type} (Strength: {signal.strength:.3f})")
            
            return signal
            
        except Exception as e:
            self.logger.error(f"Error in MovingAverageCondition for {symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    async def _calculate_moving_averages(self, symbol: str, current_price: float, 
                                       market_data: Dict[str, Any]) -> Dict[int, float]:
        """Calculate moving averages using real-time data with pandas rolling windows."""
        # Initialize price history for symbol if not exists
        if symbol not in self.price_history:
            self.price_history[symbol] = deque(maxlen=max(self.config['ma_periods']) + 10)
        
        # Add current price
        self.price_history[symbol].append(current_price)
        
        # Convert to list for pandas calculation
        prices = list(self.price_history[symbol])
        
        if len(prices) < min(self.config['ma_periods']):
            return {}
        
        ma_values = {}
        try:
            import pandas as pd
            price_series = pd.Series(prices)
            
            for period in self.config['ma_periods']:
                if len(prices) >= period:
                    ma_value = price_series.rolling(window=period).mean().iloc[-1]
                    ma_values[period] = ma_value
        
        except ImportError:
            # Fallback to simple calculation without pandas
            for period in self.config['ma_periods']:
                if len(prices) >= period:
                    ma_value = sum(prices[-period:]) / period
                    ma_values[period] = ma_value
        
        # Cache the results
        if symbol not in self.ma_cache:
            self.ma_cache[symbol] = {}
        self.ma_cache[symbol].update(ma_values)
        
        return ma_values
    
    def _check_ma_confirmation(self, price: float, ma_values: Dict[int, float], direction: str) -> bool:
        """Check if multiple MAs confirm the signal direction."""
        if not self.config['use_multiple_timeframes'] or len(ma_values) < 2:
            return True
        
        primary_ma = ma_values.get(self.config['primary_period'])
        secondary_ma = ma_values.get(self.config['secondary_period'])
        
        if primary_ma is None or secondary_ma is None:
            return True
        
        if direction == 'long':
            # For long: price should be above both MAs, and short MA should be above long MA
            return price > primary_ma and price > secondary_ma and primary_ma >= secondary_ma
        else:
            # For short: price should be below both MAs, and short MA should be below long MA
            return price < primary_ma and price < secondary_ma and primary_ma <= secondary_ma


class PriceChannelCondition(EntryCondition):
    """
    Price Channel 조건 (Donchian Channel) - Entry Condition 2
    
    Features:
    - Donchian Channel 구현 (API 문서 PriceChannelCalculator 방식)
    - 상단 돌파 → 매수, 하단 돌파 → 매도
    - 기간: 기본 20일, 설정 가능
    - 돌파 확인: 2캤들 연속 확인 + 최소 채널 폭 검증
    - Performance target: <10ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'channel_period': 20,
            'breakout_threshold': TradingConstants.CHANNEL_BREAKOUT_THRESHOLD,
            'min_channel_width_pct': 1.0,       # Minimum 1% channel width
            'consecutive_candles_required': 2,    # 2 consecutive candles for confirmation
            'enable_upper_breakout': True,
            'enable_lower_breakout': True,
            'volume_confirmation': False,         # Require volume spike for confirmation
            'volume_threshold': 1.5,             # 1.5x average volume
            'use_atr_filter': True,              # Use ATR for minimum channel width
            'atr_multiplier': 2.0,               # ATR multiplier for channel width
        }
        self.price_history: Dict[str, deque] = {}  # High/Low/Close history
        self.volume_history: Dict[str, deque] = {}
        self.breakout_history: Dict[str, List[Dict]] = {}  # Track breakout confirmations
        
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check Donchian Channel breakout conditions with advanced confirmation."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            ticker = market_data.get('tickers', {}).get(symbol)
            klines = market_data.get('klines', {}).get(symbol, {}).get('1m')
            
            if not ticker:
                return None
            
            current_price = ticker.price
            current_volume = ticker.volume_24h if hasattr(ticker, 'volume_24h') else 0
            
            # Get OHLC data for proper Donchian Channel calculation
            if klines:
                high_price = klines.high_price
                low_price = klines.low_price
                close_price = klines.close_price
            else:
                high_price = low_price = close_price = current_price
            
            # Initialize history for symbol
            if symbol not in self.price_history:
                self.price_history[symbol] = deque(maxlen=self.config['channel_period'] + 5)
            if symbol not in self.volume_history:
                self.volume_history[symbol] = deque(maxlen=self.config['channel_period'] + 5)
            if symbol not in self.breakout_history:
                self.breakout_history[symbol] = []
            
            # Add current OHLC data to history
            candle_data = {
                'high': high_price,
                'low': low_price,
                'close': close_price,
                'volume': current_volume,
                'timestamp': datetime.now()
            }
            
            self.price_history[symbol].append(candle_data)
            self.volume_history[symbol].append(current_volume)
            
            # Need minimum history to calculate Donchian Channel
            if len(self.price_history[symbol]) < self.config['channel_period']:
                return None
            
            # Calculate Donchian Channel (true High/Low based)
            channel_data = self._calculate_donchian_channel(symbol)
            if not channel_data:
                return None
            
            channel_high = channel_data['upper']
            channel_low = channel_data['lower'] 
            channel_middle = channel_data['middle']
            channel_width_pct = channel_data['width_pct']
            atr_value = channel_data.get('atr', 0)
            
            # Check minimum channel width
            if channel_width_pct < self.config['min_channel_width_pct']:
                return None
            
            # Check ATR-based filter if enabled
            if self.config['use_atr_filter'] and atr_value > 0:
                min_channel_width = atr_value * self.config['atr_multiplier']
                if (channel_high - channel_low) < min_channel_width:
                    return None
            
            signal = None
            
            # Check upper breakout (매수 진입)
            if (self.config['enable_upper_breakout'] and 
                self._check_upper_breakout(symbol, current_price, channel_high)):
                
                # Check consecutive candle confirmation
                if self._confirm_breakout(symbol, 'upper', channel_high):
                    
                    # Volume confirmation if required
                    volume_confirmed = True
                    if self.config['volume_confirmation']:
                        volume_confirmed = self._check_volume_confirmation(symbol)
                    
                    if volume_confirmed:
                        strength = self._calculate_breakout_strength(current_price, channel_high, channel_width_pct, 'upper')
                        
                        signal = TradingSignal(
                            symbol=symbol,
                            signal_type='DONCHIAN_UPPER_BREAKOUT',
                            direction='long',
                            strength=strength,
                            entry_price=current_price,
                            timestamp=datetime.now(),
                            condition_type=EntryConditionType.PRICE_CHANNEL,
                            metadata={
                                'channel_type': 'donchian',
                                'channel_period': self.config['channel_period'],
                                'channel_high': channel_high,
                                'channel_low': channel_low,
                                'channel_middle': channel_middle,
                                'channel_width_pct': channel_width_pct,
                                'breakout_type': 'upper',
                                'breakout_percentage': (current_price - channel_high) / channel_high * 100,
                                'atr_value': atr_value,
                                'consecutive_confirmations': self._count_consecutive_confirmations(symbol, 'upper'),
                                'volume_confirmed': volume_confirmed,
                                'entry_rationale': 'Donchian upper channel breakout with confirmation'
                            }
                        )
            
            # Check lower breakout (매도 진입)
            elif (self.config['enable_lower_breakout'] and 
                  self._check_lower_breakout(symbol, current_price, channel_low)):
                
                # Check consecutive candle confirmation
                if self._confirm_breakout(symbol, 'lower', channel_low):
                    
                    # Volume confirmation if required
                    volume_confirmed = True
                    if self.config['volume_confirmation']:
                        volume_confirmed = self._check_volume_confirmation(symbol)
                    
                    if volume_confirmed:
                        strength = self._calculate_breakout_strength(current_price, channel_low, channel_width_pct, 'lower')
                        
                        signal = TradingSignal(
                            symbol=symbol,
                            signal_type='DONCHIAN_LOWER_BREAKOUT',
                            direction='short',
                            strength=strength,
                            entry_price=current_price,
                            timestamp=datetime.now(),
                            condition_type=EntryConditionType.PRICE_CHANNEL,
                            metadata={
                                'channel_type': 'donchian',
                                'channel_period': self.config['channel_period'],
                                'channel_high': channel_high,
                                'channel_low': channel_low,
                                'channel_middle': channel_middle,
                                'channel_width_pct': channel_width_pct,
                                'breakout_type': 'lower',
                                'breakout_percentage': (channel_low - current_price) / channel_low * 100,
                                'atr_value': atr_value,
                                'consecutive_confirmations': self._count_consecutive_confirmations(symbol, 'lower'),
                                'volume_confirmed': volume_confirmed,
                                'entry_rationale': 'Donchian lower channel breakout with confirmation'
                            }
                        )
            
            if signal:
                self.performance_stats['signals_generated'] += 1
                self.logger.debug(f"Donchian Channel breakout signal for {symbol}: {signal.signal_type} "
                                f"(Strength: {signal.strength:.3f}, Width: {channel_width_pct:.2f}%)")
            
            return signal
            
        except Exception as e:
            self.logger.error(f"Error in PriceChannelCondition for {symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    def _calculate_donchian_channel(self, symbol: str) -> Optional[Dict[str, float]]:
        """Calculate Donchian Channel values."""
        try:
            price_data = list(self.price_history[symbol])
            
            if len(price_data) < self.config['channel_period']:
                return None
            
            # Get the last N periods for calculation (excluding current)
            period_data = price_data[-self.config['channel_period']-1:-1]  # Exclude current candle
            
            if not period_data:
                return None
            
            # Calculate channel boundaries
            highs = [candle['high'] for candle in period_data]
            lows = [candle['low'] for candle in period_data]
            closes = [candle['close'] for candle in period_data]
            
            channel_high = max(highs)
            channel_low = min(lows)
            channel_middle = (channel_high + channel_low) / 2
            channel_width_pct = ((channel_high - channel_low) / channel_middle) * 100
            
            # Calculate ATR for additional confirmation
            atr_value = 0
            if len(period_data) >= 14:  # Need at least 14 periods for ATR
                atr_value = self._calculate_atr(period_data[-14:])
            
            return {
                'upper': channel_high,
                'lower': channel_low,
                'middle': channel_middle,
                'width_pct': channel_width_pct,
                'atr': atr_value
            }
            
        except Exception as e:
            self.logger.error(f"Error calculating Donchian Channel for {symbol}: {e}")
            return None
    
    def _calculate_atr(self, ohlc_data: List[Dict]) -> float:
        """Calculate Average True Range."""
        if len(ohlc_data) < 2:
            return 0
        
        true_ranges = []
        for i in range(1, len(ohlc_data)):
            current = ohlc_data[i]
            previous = ohlc_data[i-1]
            
            tr1 = current['high'] - current['low']
            tr2 = abs(current['high'] - previous['close'])
            tr3 = abs(current['low'] - previous['close'])
            
            true_range = max(tr1, tr2, tr3)
            true_ranges.append(true_range)
        
        return sum(true_ranges) / len(true_ranges) if true_ranges else 0
    
    def _check_upper_breakout(self, symbol: str, current_price: float, channel_high: float) -> bool:
        """Check if current price breaks above upper channel."""
        breakout_level = channel_high * (1 + self.config['breakout_threshold'])
        return current_price > breakout_level
    
    def _check_lower_breakout(self, symbol: str, current_price: float, channel_low: float) -> bool:
        """Check if current price breaks below lower channel."""
        breakout_level = channel_low * (1 - self.config['breakout_threshold'])
        return current_price < breakout_level
    
    def _confirm_breakout(self, symbol: str, direction: str, channel_level: float) -> bool:
        """Confirm breakout with consecutive candles."""
        if self.config['consecutive_candles_required'] <= 1:
            return True
        
        price_data = list(self.price_history[symbol])
        if len(price_data) < self.config['consecutive_candles_required']:
            return False
        
        recent_candles = price_data[-self.config['consecutive_candles_required']:]
        
        if direction == 'upper':
            breakout_level = channel_level * (1 + self.config['breakout_threshold'])
            return all(candle['close'] > breakout_level for candle in recent_candles)
        else:  # lower
            breakout_level = channel_level * (1 - self.config['breakout_threshold'])
            return all(candle['close'] < breakout_level for candle in recent_candles)
    
    def _check_volume_confirmation(self, symbol: str) -> bool:
        """Check if volume confirms the breakout."""
        if len(self.volume_history[symbol]) < 10:
            return True  # Not enough data, assume confirmed
        
        recent_volumes = list(self.volume_history[symbol])
        current_volume = recent_volumes[-1]
        avg_volume = sum(recent_volumes[-10:-1]) / 9  # Average of last 9 volumes
        
        return current_volume >= (avg_volume * self.config['volume_threshold'])
    
    def _calculate_breakout_strength(self, current_price: float, channel_level: float, 
                                   channel_width_pct: float, direction: str) -> float:
        """Calculate signal strength based on breakout magnitude and channel characteristics."""
        if direction == 'upper':
            price_deviation = (current_price - channel_level) / channel_level
        else:
            price_deviation = (channel_level - current_price) / channel_level
        
        # Base strength from price deviation
        base_strength = min(price_deviation * 10, 0.8)
        
        # Adjust for channel width (wider channels = stronger signals)
        width_factor = min(channel_width_pct / 5.0, 0.2)  # Up to 0.2 bonus
        
        total_strength = min(base_strength + width_factor, 1.0)
        return max(total_strength, 0.1)  # Minimum strength of 0.1
    
    def _count_consecutive_confirmations(self, symbol: str, direction: str) -> int:
        """Count consecutive candles confirming the breakout."""
        price_data = list(self.price_history[symbol])
        if len(price_data) < 2:
            return 0
        
        # This is a simplified implementation
        # In practice, you would check actual consecutive confirmations
        return min(len(price_data), self.config['consecutive_candles_required'])


class OrderbookTickCondition(EntryCondition):
    """
    호가 감지 진입 (Advanced Orderbook Analysis) - Entry Condition 3
    
    Features:
    - API 문서 OrderbookAnalyzer 방식 적용
    - 상승 틱 (3틱 → 매수), 하락 틱 (2틱 → 매도)
    - WebSocket 실시간 오더북 분석
    - 물량 불균형 감지 (임계값 2.0)
    - 대량 주문 감지 (10,000 USDT)
    - 0호가 즉시 진입
    - Performance target: <10ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'up_ticks_threshold': 3,             # 3틱 상승 시 매수
            'down_ticks_threshold': 2,           # 2틱 하락 시 매도
            'enable_zero_spread_entry': True,    # 0호가 즉시 진입
            'tick_size_threshold': TradingConstants.TICK_SIZE_THRESHOLD,
            
            # Advanced orderbook analysis
            'volume_imbalance_threshold': 2.0,   # 물량 불균형 임계값
            'large_order_threshold': 10000,      # 대량 주문 감지 (USDT)
            'orderbook_depth_levels': 10,        # 분석할 호가 단수
            'tick_analysis_window': 10,          # 틱 분석 윈도우
            
            # WebSocket data validation
            'max_spread_pct': 1.0,              # 최대 스프레드 1%
            'min_liquidity_depth': 1000,        # 최소 유동성 (USDT)
            'orderbook_freshness_ms': 1000,     # 오더북 신선도 (1초)
            
            # Signal confirmation
            'require_volume_confirmation': True,
            'require_spread_confirmation': True,
            'require_depth_confirmation': True,
        }
        self.tick_history: Dict[str, deque] = {}
        self.orderbook_history: Dict[str, deque] = {}
        self.volume_imbalance_history: Dict[str, deque] = {}
        self.large_orders_detected: Dict[str, List[Dict]] = {}
        
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check advanced orderbook tick conditions with volume imbalance and large order detection."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            ticker = market_data.get('tickers', {}).get(symbol)
            orderbook = market_data.get('orderbooks', {}).get(symbol)
            trades = market_data.get('recent_trades', {}).get(symbol, [])
            
            if not ticker:
                return None
            
            current_price = ticker.price
            bid = ticker.bid
            ask = ticker.ask
            spread = ask - bid
            spread_pct = (spread / current_price) * 100 if current_price > 0 else 0
            
            # Initialize history structures
            self._initialize_history(symbol)
            
            # Validate orderbook data quality
            if not self._validate_orderbook_data(symbol, ticker, orderbook, spread_pct):
                return None
            
            # Record current tick with enhanced data
            tick_data = {
                'price': current_price,
                'bid': bid,
                'ask': ask,
                'spread': spread,
                'spread_pct': spread_pct,
                'timestamp': datetime.now(),
                'volume': getattr(ticker, 'volume_24h', 0),
                'orderbook_valid': orderbook is not None
            }
            
            self.tick_history[symbol].append(tick_data)
            
            # Store orderbook data for deeper analysis
            if orderbook:
                orderbook_data = {
                    'bids': orderbook.bids[:self.config['orderbook_depth_levels']],
                    'asks': orderbook.asks[:self.config['orderbook_depth_levels']],
                    'timestamp': datetime.now(),
                    'total_bid_volume': sum(level[1] for level in orderbook.bids[:self.config['orderbook_depth_levels']]),
                    'total_ask_volume': sum(level[1] for level in orderbook.asks[:self.config['orderbook_depth_levels']])
                }
                self.orderbook_history[symbol].append(orderbook_data)
            
            signal = None
            
            # Priority 1: Zero spread immediate entry (0호가 즉시 진입)
            if (self.config['enable_zero_spread_entry'] and 
                spread <= self.config['tick_size_threshold']):
                
                signal = self._create_zero_spread_signal(symbol, ticker, orderbook)
            
            # Priority 2: Large order detection (대량 주문 감지)
            elif orderbook and self._detect_large_orders(symbol, orderbook):
                signal = self._create_large_order_signal(symbol, ticker, orderbook)
            
            # Priority 3: Volume imbalance detection (물량 불균형 감지)
            elif orderbook and self._detect_volume_imbalance(symbol, orderbook):
                signal = self._create_volume_imbalance_signal(symbol, ticker, orderbook)
            
            # Priority 4: Traditional tick analysis
            elif len(self.tick_history[symbol]) >= self.config['tick_analysis_window']:
                signal = self._analyze_tick_patterns(symbol, ticker, orderbook)
            
            if signal:
                # Apply additional confirmations
                if self._validate_signal_confirmations(symbol, signal, orderbook):
                    self.performance_stats['signals_generated'] += 1
                    self.logger.debug(f"Advanced orderbook signal for {symbol}: {signal.signal_type} "
                                    f"(Strength: {signal.strength:.3f}, Spread: {spread_pct:.3f}%)")
                    return signal
                else:
                    self.logger.debug(f"Signal failed confirmation checks for {symbol}: {signal.signal_type}")
            
            return None
            
        except Exception as e:
            self.logger.error(f"Error in OrderbookTickCondition for {symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    def _initialize_history(self, symbol: str) -> None:
        """Initialize history structures for symbol."""
        if symbol not in self.tick_history:
            self.tick_history[symbol] = deque(maxlen=self.config['tick_analysis_window'])
        if symbol not in self.orderbook_history:
            self.orderbook_history[symbol] = deque(maxlen=20)
        if symbol not in self.volume_imbalance_history:
            self.volume_imbalance_history[symbol] = deque(maxlen=10)
        if symbol not in self.large_orders_detected:
            self.large_orders_detected[symbol] = []
    
    def _validate_orderbook_data(self, symbol: str, ticker, orderbook, spread_pct: float) -> bool:
        """Validate orderbook data quality and freshness."""
        # Check spread validity
        if spread_pct > self.config['max_spread_pct']:
            return False
        
        # Check minimum liquidity if orderbook exists
        if orderbook:
            total_liquidity = 0
            for level in orderbook.bids[:5]:
                total_liquidity += level[0] * level[1]  # price * volume
            for level in orderbook.asks[:5]:
                total_liquidity += level[0] * level[1]  # price * volume
            
            if total_liquidity < self.config['min_liquidity_depth']:
                return False
        
        return True
    
    def _create_zero_spread_signal(self, symbol: str, ticker, orderbook) -> TradingSignal:
        """Create zero spread signal."""
        spread = ticker.ask - ticker.bid
        
        # Determine direction based on recent momentum or default to long
        direction = 'long'  # Default
        if len(self.tick_history[symbol]) > 2:
            recent_ticks = list(self.tick_history[symbol])[-3:]
            price_changes = [recent_ticks[i]['price'] - recent_ticks[i-1]['price'] 
                           for i in range(1, len(recent_ticks))]
            avg_change = sum(price_changes) / len(price_changes)
            direction = 'long' if avg_change >= 0 else 'short'
        
        return TradingSignal(
            symbol=symbol,
            signal_type='ZERO_SPREAD_IMMEDIATE',
            direction=direction,
            strength=1.0,
            entry_price=ticker.price,
            timestamp=datetime.now(),
            condition_type=EntryConditionType.ORDERBOOK_TICK,
            metadata={
                'entry_type': 'zero_spread_immediate',
                'spread': spread,
                'bid': ticker.bid,
                'ask': ticker.ask,
                'liquidity_score': self._calculate_liquidity_score(orderbook) if orderbook else 0,
                'entry_rationale': 'Zero spread detected - immediate arbitrage opportunity'
            }
        )
    
    def _detect_large_orders(self, symbol: str, orderbook) -> bool:
        """Detect large orders in orderbook (대량 주문 감지)."""
        if not orderbook or not orderbook.bids or not orderbook.asks:
            return False
        
        # Check for large orders in top levels
        for level in orderbook.bids[:5]:
            order_value = level[0] * level[1]  # price * volume
            if order_value >= self.config['large_order_threshold']:
                return True
        
        for level in orderbook.asks[:5]:
            order_value = level[0] * level[1]  # price * volume
            if order_value >= self.config['large_order_threshold']:
                return True
        
        return False
    
    def _create_large_order_signal(self, symbol: str, ticker, orderbook) -> TradingSignal:
        """Create signal based on large order detection."""
        # Determine direction based on where large order was detected
        large_bid_value = 0
        large_ask_value = 0
        
        for level in orderbook.bids[:5]:
            order_value = level[0] * level[1]
            if order_value >= self.config['large_order_threshold']:
                large_bid_value = max(large_bid_value, order_value)
        
        for level in orderbook.asks[:5]:
            order_value = level[0] * level[1]
            if order_value >= self.config['large_order_threshold']:
                large_ask_value = max(large_ask_value, order_value)
        
        # Large bid suggests support (bullish), large ask suggests resistance (bearish)
        if large_bid_value > large_ask_value:
            direction = 'long'
            strength = min((large_bid_value / self.config['large_order_threshold']) * 0.1, 1.0)
            signal_type = 'LARGE_BID_DETECTED'
        else:
            direction = 'short'
            strength = min((large_ask_value / self.config['large_order_threshold']) * 0.1, 1.0)
            signal_type = 'LARGE_ASK_DETECTED'
        
        return TradingSignal(
            symbol=symbol,
            signal_type=signal_type,
            direction=direction,
            strength=strength,
            entry_price=ticker.ask if direction == 'long' else ticker.bid,
            timestamp=datetime.now(),
            condition_type=EntryConditionType.ORDERBOOK_TICK,
            metadata={
                'entry_type': 'large_order_detection',
                'large_bid_value': large_bid_value,
                'large_ask_value': large_ask_value,
                'threshold': self.config['large_order_threshold'],
                'entry_rationale': f'Large {"bid" if direction == "long" else "ask"} order detected indicating institutional interest'
            }
        )
    
    def _detect_volume_imbalance(self, symbol: str, orderbook) -> bool:
        """Detect volume imbalance in orderbook (물량 불균형 감지)."""
        if not orderbook or not orderbook.bids or not orderbook.asks:
            return False
        
        # Calculate volume imbalance ratio
        top_levels = 5
        bid_volume = sum(level[1] for level in orderbook.bids[:top_levels])
        ask_volume = sum(level[1] for level in orderbook.asks[:top_levels])
        
        if bid_volume == 0 or ask_volume == 0:
            return False
        
        imbalance_ratio = max(bid_volume / ask_volume, ask_volume / bid_volume)
        
        # Store imbalance for trend analysis
        imbalance_data = {
            'ratio': imbalance_ratio,
            'bid_volume': bid_volume,
            'ask_volume': ask_volume,
            'timestamp': datetime.now()
        }
        self.volume_imbalance_history[symbol].append(imbalance_data)
        
        return imbalance_ratio >= self.config['volume_imbalance_threshold']
    
    def _create_volume_imbalance_signal(self, symbol: str, ticker, orderbook) -> TradingSignal:
        """Create signal based on volume imbalance."""
        # Calculate current imbalance
        bid_volume = sum(level[1] for level in orderbook.bids[:5])
        ask_volume = sum(level[1] for level in orderbook.asks[:5])
        
        if bid_volume > ask_volume:
            direction = 'long'
            imbalance_ratio = bid_volume / ask_volume if ask_volume > 0 else float('inf')
            signal_type = 'VOLUME_IMBALANCE_BID'
        else:
            direction = 'short'
            imbalance_ratio = ask_volume / bid_volume if bid_volume > 0 else float('inf')
            signal_type = 'VOLUME_IMBALANCE_ASK'
        
        strength = min((imbalance_ratio / self.config['volume_imbalance_threshold']) * 0.3, 1.0)
        
        return TradingSignal(
            symbol=symbol,
            signal_type=signal_type,
            direction=direction,
            strength=strength,
            entry_price=ticker.ask if direction == 'long' else ticker.bid,
            timestamp=datetime.now(),
            condition_type=EntryConditionType.ORDERBOOK_TICK,
            metadata={
                'entry_type': 'volume_imbalance',
                'imbalance_ratio': imbalance_ratio,
                'bid_volume': bid_volume,
                'ask_volume': ask_volume,
                'threshold': self.config['volume_imbalance_threshold'],
                'entry_rationale': f'Volume imbalance detected - {"bid" if direction == "long" else "ask"} side dominance'
            }
        )
    
    def _analyze_tick_patterns(self, symbol: str, ticker, orderbook) -> Optional[TradingSignal]:
        """Analyze traditional tick patterns."""
        recent_ticks = list(self.tick_history[symbol])[-self.config['tick_analysis_window']:]
        
        if len(recent_ticks) < max(self.config['up_ticks_threshold'], self.config['down_ticks_threshold']):
            return None
        
        # Count consecutive up/down ticks
        up_ticks = 0
        down_ticks = 0
        max_consecutive_up = 0
        max_consecutive_down = 0
        
        for i in range(1, len(recent_ticks)):
            if recent_ticks[i]['price'] > recent_ticks[i-1]['price']:
                up_ticks += 1
                max_consecutive_up = max(max_consecutive_up, up_ticks)
                down_ticks = 0
            elif recent_ticks[i]['price'] < recent_ticks[i-1]['price']:
                down_ticks += 1
                max_consecutive_down = max(max_consecutive_down, down_ticks)
                up_ticks = 0
            else:
                up_ticks = down_ticks = 0
        
        # Check for up tick threshold (매수 진입)
        if max_consecutive_up >= self.config['up_ticks_threshold']:
            return TradingSignal(
                symbol=symbol,
                signal_type='CONSECUTIVE_UP_TICKS',
                direction='long',
                strength=min(max_consecutive_up / self.config['up_ticks_threshold'], 1.0),
                entry_price=ticker.ask,
                timestamp=datetime.now(),
                condition_type=EntryConditionType.ORDERBOOK_TICK,
                metadata={
                    'entry_type': 'consecutive_up_ticks',
                    'consecutive_up_ticks': max_consecutive_up,
                    'threshold': self.config['up_ticks_threshold'],
                    'recent_prices': [t['price'] for t in recent_ticks[-5:]]
                }
            )
        
        # Check for down tick threshold (매도 진입)
        elif max_consecutive_down >= self.config['down_ticks_threshold']:
            return TradingSignal(
                symbol=symbol,
                signal_type='CONSECUTIVE_DOWN_TICKS',
                direction='short',
                strength=min(max_consecutive_down / self.config['down_ticks_threshold'], 1.0),
                entry_price=ticker.bid,
                timestamp=datetime.now(),
                condition_type=EntryConditionType.ORDERBOOK_TICK,
                metadata={
                    'entry_type': 'consecutive_down_ticks',
                    'consecutive_down_ticks': max_consecutive_down,
                    'threshold': self.config['down_ticks_threshold'],
                    'recent_prices': [t['price'] for t in recent_ticks[-5:]]
                }
            )
        
        return None
    
    def _validate_signal_confirmations(self, symbol: str, signal: TradingSignal, orderbook) -> bool:
        """Validate signal with additional confirmations."""
        # Volume confirmation
        if self.config['require_volume_confirmation'] and orderbook:
            if not self._check_volume_support(orderbook, signal.direction):
                return False
        
        # Spread confirmation
        if self.config['require_spread_confirmation']:
            if not self._check_spread_support(symbol, signal):
                return False
        
        # Depth confirmation
        if self.config['require_depth_confirmation'] and orderbook:
            if not self._check_depth_support(orderbook, signal.direction):
                return False
        
        return True
    
    def _check_volume_support(self, orderbook, direction: str) -> bool:
        """Check if orderbook volume supports the signal direction."""
        bid_volume = sum(level[1] for level in orderbook.bids[:3])
        ask_volume = sum(level[1] for level in orderbook.asks[:3])
        
        if direction == 'long':
            return bid_volume >= ask_volume * 0.8  # At least 80% of ask volume
        else:
            return ask_volume >= bid_volume * 0.8  # At least 80% of bid volume
    
    def _check_spread_support(self, symbol: str, signal: TradingSignal) -> bool:
        """Check if spread supports the signal."""
        if len(self.tick_history[symbol]) < 3:
            return True
        
        recent_spreads = [tick['spread_pct'] for tick in list(self.tick_history[symbol])[-3:]]
        avg_spread = sum(recent_spreads) / len(recent_spreads)
        
        return avg_spread <= self.config['max_spread_pct']
    
    def _check_depth_support(self, orderbook, direction: str) -> bool:
        """Check if orderbook depth supports the signal direction."""
        # Calculate weighted average price for next 3 levels
        if direction == 'long':
            levels = orderbook.asks[:3]
        else:
            levels = orderbook.bids[:3]
        
        total_volume = sum(level[1] for level in levels)
        return total_volume >= 100  # Minimum depth requirement
    
    def _calculate_liquidity_score(self, orderbook) -> float:
        """Calculate a liquidity score for the orderbook."""
        if not orderbook:
            return 0
        
        bid_depth = sum(level[1] for level in orderbook.bids[:5])
        ask_depth = sum(level[1] for level in orderbook.asks[:5])
        
        total_depth = bid_depth + ask_depth
        balance_score = 1 - abs(bid_depth - ask_depth) / (bid_depth + ask_depth) if (bid_depth + ask_depth) > 0 else 0
        
        # Normalize liquidity score (0-1)
        depth_score = min(total_depth / 10000, 1.0)  # Normalize against 10k volume
        
        return (depth_score * 0.7) + (balance_score * 0.3)


class TickPatternCondition(EntryCondition):
    """
    틱 기반 추가 진입 (Advanced Pattern Recognition) - Entry Condition 4
    
    Features:
    - 패턴: 5틱 상승 후 2틱 하락 시 30% 추가 진입
    - 설정 가능한 틱 임계값
    - 시간 기반 패턴 유효성 (10초 윈도우)
    - 실시간 패턴 검증 및 전랥 실행
    - 다중 패턴 지원 (Reversal, Continuation, Breakout)
    - Performance target: <10ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            # Primary pattern (5up-2down)
            'pattern_up_ticks': 5,           # 상승 틱 수
            'pattern_down_ticks': 2,         # 하락 틱 수
            'additional_entry_ratio': 0.3,   # 30% 추가 진입
            'pattern_timeout_seconds': 10,   # 패턴 유효 시간 (10초 윈도우)
            
            # Advanced pattern recognition
            'enable_reversal_patterns': True,      # 반전 패턴
            'enable_continuation_patterns': True,   # 지속 패턴
            'enable_breakout_patterns': True,       # 돌파 패턴
            
            # Pattern validation
            'min_tick_size': TradingConstants.TICK_SIZE_THRESHOLD,
            'min_pattern_strength': 0.1,          # 최소 패턴 강도
            'require_volume_confirmation': False,  # 거래량 확인 필요
            'volume_spike_threshold': 1.5,         # 거래량 급증 임계값
            
            # Time-based validation
            'pattern_window_seconds': 10,         # 패턴 감지 윈도우
            'cooldown_seconds': 30,               # 패턴 간 쿨다운
        }
        self.tick_patterns: Dict[str, deque] = {}
        self.pattern_history: Dict[str, List[Dict]] = {}
        self.last_pattern_time: Dict[str, datetime] = {}
        
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check advanced tick pattern conditions for additional entries."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            ticker = market_data.get('tickers', {}).get(symbol)
            trades = market_data.get('recent_trades', {}).get(symbol, [])
            
            if not ticker:
                return None
            
            current_price = ticker.price
            current_time = datetime.now()
            current_volume = getattr(ticker, 'volume_24h', 0)
            
            # Initialize pattern tracking
            self._initialize_pattern_tracking(symbol)
            
            # Check cooldown period
            if not self._check_cooldown(symbol, current_time):
                return None
            
            # Add current tick with enhanced data
            tick_info = {
                'price': current_price,
                'timestamp': current_time,
                'volume': current_volume,
                'bid': ticker.bid,
                'ask': ticker.ask,
                'direction': None,  # Will be calculated
                'strength': 0,      # Tick strength
            }
            
            self.tick_patterns[symbol].append(tick_info)
            
            # Calculate tick directions and strengths
            self._calculate_tick_properties(symbol)
            
            signal = None
            
            # Priority 1: Check primary pattern (5up-2down for additional entry)
            if self.config['additional_entry_ratio'] > 0:
                signal = self._check_primary_pattern(symbol, ticker, current_time)
            
            # Priority 2: Check reversal patterns
            if not signal and self.config['enable_reversal_patterns']:
                signal = self._check_reversal_patterns(symbol, ticker, current_time)
            
            # Priority 3: Check continuation patterns
            if not signal and self.config['enable_continuation_patterns']:
                signal = self._check_continuation_patterns(symbol, ticker, current_time)
            
            # Priority 4: Check breakout patterns
            if not signal and self.config['enable_breakout_patterns']:
                signal = self._check_breakout_patterns(symbol, ticker, current_time)
            
            if signal:
                # Validate pattern with additional confirmations
                if self._validate_pattern_signal(symbol, signal, trades):
                    self._record_pattern_execution(symbol, signal, current_time)
                    self.performance_stats['signals_generated'] += 1
                    self.logger.debug(f"Advanced tick pattern signal for {symbol}: {signal.signal_type} "
                                    f"(Strength: {signal.strength:.3f}, Additional: {getattr(signal, 'additional_entry_ratio', 0)*100:.1f}%)")
                    return signal
                else:
                    self.logger.debug(f"Pattern signal validation failed for {symbol}: {signal.signal_type}")
            
            return None
            
        except Exception as e:
            self.logger.error(f"Error in TickPatternCondition for {symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    def _initialize_pattern_tracking(self, symbol: str) -> None:
        """Initialize pattern tracking structures."""
        if symbol not in self.tick_patterns:
            max_ticks = max(20, self.config['pattern_up_ticks'] + self.config['pattern_down_ticks'] + 10)
            self.tick_patterns[symbol] = deque(maxlen=max_ticks)
        
        if symbol not in self.pattern_history:
            self.pattern_history[symbol] = []
        
        if symbol not in self.last_pattern_time:
            self.last_pattern_time[symbol] = datetime.min
    
    def _check_cooldown(self, symbol: str, current_time: datetime) -> bool:
        """Check if enough time has passed since last pattern."""
        if symbol in self.last_pattern_time:
            time_diff = (current_time - self.last_pattern_time[symbol]).total_seconds()
            return time_diff >= self.config['cooldown_seconds']
        return True
    
    def _calculate_tick_properties(self, symbol: str) -> None:
        """Calculate direction and strength for each tick."""
        ticks = list(self.tick_patterns[symbol])
        
        for i in range(1, len(ticks)):
            price_diff = ticks[i]['price'] - ticks[i-1]['price']
            time_diff = (ticks[i]['timestamp'] - ticks[i-1]['timestamp']).total_seconds()
            
            if abs(price_diff) >= self.config['min_tick_size']:
                if price_diff > 0:
                    ticks[i]['direction'] = 'up'
                elif price_diff < 0:
                    ticks[i]['direction'] = 'down'
                else:
                    ticks[i]['direction'] = 'flat'
                
                # Calculate tick strength (price change / time)
                if time_diff > 0:
                    ticks[i]['strength'] = abs(price_diff) / time_diff
            else:
                ticks[i]['direction'] = 'flat'
                ticks[i]['strength'] = 0
    
    def _check_primary_pattern(self, symbol: str, ticker, current_time: datetime) -> Optional[TradingSignal]:
        """Check primary pattern: 5up-2down for additional entry."""
        ticks = list(self.tick_patterns[symbol])
        required_ticks = self.config['pattern_up_ticks'] + self.config['pattern_down_ticks']
        
        if len(ticks) < required_ticks:
            return None
        
        # Check if pattern occurred within time window
        pattern_window = timedelta(seconds=self.config['pattern_window_seconds'])
        recent_ticks = [tick for tick in ticks if (current_time - tick['timestamp']) <= pattern_window]
        
        if len(recent_ticks) < required_ticks:
            return None
        
        # Look for the specific pattern: N up ticks followed by M down ticks
        pattern_found, pattern_data = self._detect_up_down_pattern(
            recent_ticks, 
            self.config['pattern_up_ticks'], 
            self.config['pattern_down_ticks']
        )
        
        if pattern_found:
            # Calculate pattern strength
            up_strength = pattern_data['avg_up_strength']
            down_strength = pattern_data['avg_down_strength']
            pattern_strength = min((up_strength + down_strength) / 2, 1.0)
            
            if pattern_strength >= self.config['min_pattern_strength']:
                return TradingSignal(
                    symbol=symbol,
                    signal_type='TICK_PATTERN_5UP_2DOWN_ADDITIONAL',
                    direction='long',  # Additional long position after pullback
                    strength=pattern_strength,
                    entry_price=ticker.price,
                    timestamp=current_time,
                    condition_type=EntryConditionType.TICK_PATTERN,
                    additional_entry_ratio=self.config['additional_entry_ratio'],
                    metadata={
                        'pattern_type': 'primary_5up_2down',
                        'up_ticks_found': pattern_data['up_count'],
                        'down_ticks_found': pattern_data['down_count'],
                        'pattern_strength': pattern_strength,
                        'avg_up_strength': up_strength,
                        'avg_down_strength': down_strength,
                        'additional_entry_percentage': self.config['additional_entry_ratio'] * 100,
                        'pattern_duration_seconds': pattern_data['duration_seconds'],
                        'entry_rationale': '5-tick upward momentum followed by 2-tick pullback - additional entry opportunity'
                    }
                )
        
        return None
    
    def _check_reversal_patterns(self, symbol: str, ticker, current_time: datetime) -> Optional[TradingSignal]:
        """Check for reversal patterns."""
        ticks = list(self.tick_patterns[symbol])[-15:]  # Last 15 ticks
        
        if len(ticks) < 7:
            return None
        
        # Look for strong directional move followed by reversal
        # Pattern: 4+ consecutive ticks in one direction, then 3+ in opposite
        reversal_found, reversal_data = self._detect_reversal_pattern(ticks)
        
        if reversal_found:
            pattern_strength = min(reversal_data['strength'], 1.0)
            
            if pattern_strength >= self.config['min_pattern_strength']:
                return TradingSignal(
                    symbol=symbol,
                    signal_type=f'TICK_PATTERN_REVERSAL_{reversal_data["new_direction"].upper()}',
                    direction=reversal_data['new_direction'],
                    strength=pattern_strength,
                    entry_price=ticker.ask if reversal_data['new_direction'] == 'long' else ticker.bid,
                    timestamp=current_time,
                    condition_type=EntryConditionType.TICK_PATTERN,
                    metadata={
                        'pattern_type': 'reversal',
                        'previous_direction': reversal_data['previous_direction'],
                        'new_direction': reversal_data['new_direction'],
                        'reversal_strength': pattern_strength,
                        'consecutive_previous': reversal_data['consecutive_previous'],
                        'consecutive_new': reversal_data['consecutive_new'],
                        'entry_rationale': f'Strong {reversal_data["previous_direction"]} momentum reversal to {reversal_data["new_direction"]}'
                    }
                )
        
        return None
    
    def _check_continuation_patterns(self, symbol: str, ticker, current_time: datetime) -> Optional[TradingSignal]:
        """Check for continuation patterns."""
        ticks = list(self.tick_patterns[symbol])[-10:]
        
        if len(ticks) < 6:
            return None
        
        # Look for: trend -> brief pause -> continuation
        continuation_found, continuation_data = self._detect_continuation_pattern(ticks)
        
        if continuation_found:
            pattern_strength = min(continuation_data['strength'], 1.0)
            
            if pattern_strength >= self.config['min_pattern_strength']:
                return TradingSignal(
                    symbol=symbol,
                    signal_type=f'TICK_PATTERN_CONTINUATION_{continuation_data["direction"].upper()}',
                    direction=continuation_data['direction'],
                    strength=pattern_strength,
                    entry_price=ticker.ask if continuation_data['direction'] == 'long' else ticker.bid,
                    timestamp=current_time,
                    condition_type=EntryConditionType.TICK_PATTERN,
                    metadata={
                        'pattern_type': 'continuation',
                        'trend_direction': continuation_data['direction'],
                        'pause_duration': continuation_data['pause_duration'],
                        'continuation_strength': pattern_strength,
                        'entry_rationale': f'Trend continuation after brief pause - {continuation_data["direction"]} momentum resuming'
                    }
                )
        
        return None
    
    def _check_breakout_patterns(self, symbol: str, ticker, current_time: datetime) -> Optional[TradingSignal]:
        """Check for breakout patterns."""
        ticks = list(self.tick_patterns[symbol])[-12:]
        
        if len(ticks) < 8:
            return None
        
        # Look for: consolidation -> sudden strong move
        breakout_found, breakout_data = self._detect_breakout_pattern(ticks)
        
        if breakout_found:
            pattern_strength = min(breakout_data['strength'], 1.0)
            
            if pattern_strength >= self.config['min_pattern_strength']:
                return TradingSignal(
                    symbol=symbol,
                    signal_type=f'TICK_PATTERN_BREAKOUT_{breakout_data["direction"].upper()}',
                    direction=breakout_data['direction'],
                    strength=pattern_strength,
                    entry_price=ticker.ask if breakout_data['direction'] == 'long' else ticker.bid,
                    timestamp=current_time,
                    condition_type=EntryConditionType.TICK_PATTERN,
                    metadata={
                        'pattern_type': 'breakout',
                        'breakout_direction': breakout_data['direction'],
                        'consolidation_range': breakout_data['consolidation_range'],
                        'breakout_strength': pattern_strength,
                        'volatility_expansion': breakout_data['volatility_expansion'],
                        'entry_rationale': f'Price breakout from consolidation range - {breakout_data["direction"]} momentum'
                    }
                )
        
        return None
    
    def _detect_up_down_pattern(self, ticks: List[Dict], up_required: int, down_required: int) -> Tuple[bool, Dict]:
        """Detect specific up-then-down pattern."""
        if len(ticks) < up_required + down_required:
            return False, {}
        
        # Find sequences of consecutive up and down ticks
        up_sequences = []
        down_sequences = []
        current_sequence = []
        current_direction = None
        
        for i, tick in enumerate(ticks):
            if tick['direction'] == 'up':
                if current_direction != 'up':
                    if current_sequence and current_direction == 'down':
                        down_sequences.append(current_sequence.copy())
                    current_sequence = [i]
                    current_direction = 'up'
                else:
                    current_sequence.append(i)
            elif tick['direction'] == 'down':
                if current_direction != 'down':
                    if current_sequence and current_direction == 'up':
                        up_sequences.append(current_sequence.copy())
                    current_sequence = [i]
                    current_direction = 'down'
                else:
                    current_sequence.append(i)
            else:  # flat
                if current_sequence:
                    if current_direction == 'up':
                        up_sequences.append(current_sequence.copy())
                    elif current_direction == 'down':
                        down_sequences.append(current_sequence.copy())
                current_sequence = []
                current_direction = None
        
        # Add last sequence
        if current_sequence:
            if current_direction == 'up':
                up_sequences.append(current_sequence)
            elif current_direction == 'down':
                down_sequences.append(current_sequence)
        
        # Look for up sequence followed by down sequence
        for up_seq in up_sequences:
            if len(up_seq) >= up_required:
                for down_seq in down_sequences:
                    if len(down_seq) >= down_required and min(down_seq) > max(up_seq):
                        # Calculate pattern data
                        up_strengths = [ticks[i]['strength'] for i in up_seq]
                        down_strengths = [ticks[i]['strength'] for i in down_seq]
                        
                        start_time = ticks[up_seq[0]]['timestamp']
                        end_time = ticks[down_seq[-1]]['timestamp']
                        duration = (end_time - start_time).total_seconds()
                        
                        return True, {
                            'up_count': len(up_seq),
                            'down_count': len(down_seq),
                            'avg_up_strength': sum(up_strengths) / len(up_strengths) if up_strengths else 0,
                            'avg_down_strength': sum(down_strengths) / len(down_strengths) if down_strengths else 0,
                            'duration_seconds': duration
                        }
        
        return False, {}
    
    def _detect_reversal_pattern(self, ticks: List[Dict]) -> Tuple[bool, Dict]:
        """Detect reversal patterns."""
        # Find longest consecutive sequence in each direction
        max_up_consecutive = 0
        max_down_consecutive = 0
        current_up = 0
        current_down = 0
        
        for tick in ticks:
            if tick['direction'] == 'up':
                current_up += 1
                current_down = 0
                max_up_consecutive = max(max_up_consecutive, current_up)
            elif tick['direction'] == 'down':
                current_down += 1
                current_up = 0
                max_down_consecutive = max(max_down_consecutive, current_down)
            else:
                current_up = current_down = 0
        
        # Check for reversal: strong move in one direction followed by strong move in opposite
        if max_up_consecutive >= 4 and max_down_consecutive >= 3:
            # Determine which came last
            last_directions = [tick['direction'] for tick in ticks[-5:] if tick['direction'] != 'flat']
            if last_directions:
                if last_directions[-1] == 'down' and any(d == 'up' for d in last_directions[:-3]):
                    return True, {
                        'previous_direction': 'up',
                        'new_direction': 'short',
                        'consecutive_previous': max_up_consecutive,
                        'consecutive_new': max_down_consecutive,
                        'strength': min((max_up_consecutive + max_down_consecutive) / 10, 1.0)
                    }
                elif last_directions[-1] == 'up' and any(d == 'down' for d in last_directions[:-3]):
                    return True, {
                        'previous_direction': 'down',
                        'new_direction': 'long',
                        'consecutive_previous': max_down_consecutive,
                        'consecutive_new': max_up_consecutive,
                        'strength': min((max_up_consecutive + max_down_consecutive) / 10, 1.0)
                    }
        
        return False, {}
    
    def _detect_continuation_pattern(self, ticks: List[Dict]) -> Tuple[bool, Dict]:
        """Detect continuation patterns."""
        if len(ticks) < 6:
            return False, {}
        
        # Look for: initial trend -> pause (flat/small moves) -> continuation
        first_half = ticks[:len(ticks)//2]
        second_half = ticks[len(ticks)//2:]
        
        # Analyze first half for dominant direction
        up_count = sum(1 for tick in first_half if tick['direction'] == 'up')
        down_count = sum(1 for tick in first_half if tick['direction'] == 'down')
        
        if up_count < 2 and down_count < 2:
            return False, {}
        
        dominant_direction = 'up' if up_count > down_count else 'down'
        
        # Check if second half continues the trend after pause
        second_up = sum(1 for tick in second_half if tick['direction'] == 'up')
        second_down = sum(1 for tick in second_half if tick['direction'] == 'down')
        second_flat = sum(1 for tick in second_half if tick['direction'] == 'flat')
        
        # Pattern: dominant direction continues after brief pause
        if dominant_direction == 'up' and second_up >= 2 and second_up > second_down:
            return True, {
                'direction': 'long',
                'pause_duration': second_flat,
                'strength': min((up_count + second_up) / len(ticks), 1.0)
            }
        elif dominant_direction == 'down' and second_down >= 2 and second_down > second_up:
            return True, {
                'direction': 'short',
                'pause_duration': second_flat,
                'strength': min((down_count + second_down) / len(ticks), 1.0)
            }
        
        return False, {}
    
    def _detect_breakout_pattern(self, ticks: List[Dict]) -> Tuple[bool, Dict]:
        """Detect breakout patterns."""
        if len(ticks) < 8:
            return False, {}
        
        # Calculate price range for consolidation period
        prices = [tick['price'] for tick in ticks[:-3]]  # Exclude last 3 ticks
        consolidation_high = max(prices)
        consolidation_low = min(prices)
        consolidation_range = consolidation_high - consolidation_low
        
        # Check recent ticks for breakout
        recent_ticks = ticks[-3:]
        recent_prices = [tick['price'] for tick in recent_ticks]
        
        # Calculate volatility expansion
        consolidation_avg_strength = sum(tick['strength'] for tick in ticks[:-3]) / len(ticks[:-3])
        recent_avg_strength = sum(tick['strength'] for tick in recent_ticks) / len(recent_ticks)
        volatility_expansion = recent_avg_strength / consolidation_avg_strength if consolidation_avg_strength > 0 else 1
        
        # Check for breakout
        if max(recent_prices) > consolidation_high + (consolidation_range * 0.1):  # 10% breakout
            return True, {
                'direction': 'long',
                'consolidation_range': consolidation_range,
                'volatility_expansion': volatility_expansion,
                'strength': min(volatility_expansion / 2, 1.0)
            }
        elif min(recent_prices) < consolidation_low - (consolidation_range * 0.1):
            return True, {
                'direction': 'short',
                'consolidation_range': consolidation_range,
                'volatility_expansion': volatility_expansion,
                'strength': min(volatility_expansion / 2, 1.0)
            }
        
        return False, {}
    
    def _validate_pattern_signal(self, symbol: str, signal: TradingSignal, trades: List) -> bool:
        """Validate pattern signal with additional confirmations."""
        # Volume confirmation if required
        if self.config['require_volume_confirmation'] and trades:
            if not self._check_volume_spike(trades):
                return False
        
        # Pattern strength check
        if signal.strength < self.config['min_pattern_strength']:
            return False
        
        return True
    
    def _check_volume_spike(self, trades: List) -> bool:
        """Check if recent trades show volume spike."""
        if len(trades) < 10:
            return True  # Not enough data, assume ok
        
        recent_volume = sum(trade.get('volume', 0) for trade in trades[-3:])
        baseline_volume = sum(trade.get('volume', 0) for trade in trades[-10:-3]) / 7
        
        return recent_volume >= (baseline_volume * self.config['volume_spike_threshold'])
    
    def _record_pattern_execution(self, symbol: str, signal: TradingSignal, current_time: datetime) -> None:
        """Record pattern execution for cooldown tracking."""
        self.last_pattern_time[symbol] = current_time
        
        pattern_record = {
            'signal_type': signal.signal_type,
            'direction': signal.direction,
            'strength': signal.strength,
            'timestamp': current_time,
            'additional_ratio': getattr(signal, 'additional_entry_ratio', 0)
        }
        
        self.pattern_history[symbol].append(pattern_record)
        
        # Keep only recent history
        if len(self.pattern_history[symbol]) > 50:
            self.pattern_history[symbol] = self.pattern_history[symbol][-50:]


class CandleStateCondition(EntryCondition):
    """
    캔들 상태 조건 - Entry Condition 5
    
    Features:
    - 양봉 시 → 매수 진입
    - 음봉 시 → 매도 진입
    - 현재 봉 기준으로 실시간 판단
    - Performance target: <10ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'enable_bullish_entry': True,   # 양봉 시 매수
            'enable_bearish_entry': True,   # 음봉 시 매도
            'min_candle_body_ratio': 0.6,   # 최소 몸체 비율 (60%)
            'candle_confirmation_threshold': TradingConstants.CANDLE_CONFIRMATION_THRESHOLD,
        }
        self.candle_data: Dict[str, Dict[str, Any]] = {}
        
    async def check_condition(self, 
                            symbol: str, 
                            market_data: Dict[str, Any],
                            indicators: Optional[TechnicalIndicators] = None) -> Optional[TradingSignal]:
        """Check candle state conditions."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            ticker = market_data.get('tickers', {}).get(symbol)
            if not ticker:
                return None
            
            current_price = ticker.price
            high_24h = ticker.high_24h
            low_24h = ticker.low_24h
            
            # For real-time candle analysis, we simulate current candle state
            # In production, this would use actual OHLC data from klines
            
            # Initialize candle tracking if not exists
            if symbol not in self.candle_data:
                self.candle_data[symbol] = {
                    'open': current_price,
                    'high': current_price,
                    'low': current_price,
                    'close': current_price,
                    'start_time': datetime.now()
                }
                
            candle = self.candle_data[symbol]
            
            # Update current candle data
            candle['close'] = current_price
            candle['high'] = max(candle['high'], current_price)
            candle['low'] = min(candle['low'], current_price)
            
            # Calculate candle properties
            open_price = candle['open']
            close_price = candle['close']
            high_price = candle['high']
            low_price = candle['low']
            
            # Calculate body and shadow ratios
            total_range = high_price - low_price
            if total_range == 0:
                return None
                
            body_size = abs(close_price - open_price)
            body_ratio = body_size / total_range if total_range > 0 else 0
            
            # Check if candle change meets threshold
            price_change_ratio = abs(close_price - open_price) / open_price
            if price_change_ratio < self.config['candle_confirmation_threshold']:
                return None
            
            signal = None
            
            # Check for bullish candle (양봉) - close > open
            if (self.config['enable_bullish_entry'] and 
                close_price > open_price and 
                body_ratio >= self.config['min_candle_body_ratio']):
                
                strength = min(price_change_ratio * 10, 1.0)  # Scale to 0-1
                
                signal = TradingSignal(
                    symbol=symbol,
                    signal_type='BULLISH_CANDLE',
                    direction='long',
                    strength=strength,
                    entry_price=current_price,
                    timestamp=datetime.now(),
                    condition_type=EntryConditionType.CANDLE_STATE,
                    metadata={
                        'candle_type': 'bullish',
                        'open_price': open_price,
                        'close_price': close_price,
                        'high_price': high_price,
                        'low_price': low_price,
                        'body_ratio': body_ratio,
                        'price_change_percent': price_change_ratio * 100,
                        'candle_color': 'green'
                    }
                )
            
            # Check for bearish candle (음봉) - close < open
            elif (self.config['enable_bearish_entry'] and 
                  close_price < open_price and 
                  body_ratio >= self.config['min_candle_body_ratio']):
                
                strength = min(price_change_ratio * 10, 1.0)  # Scale to 0-1
                
                signal = TradingSignal(
                    symbol=symbol,
                    signal_type='BEARISH_CANDLE',
                    direction='short',
                    strength=strength,
                    entry_price=current_price,
                    timestamp=datetime.now(),
                    condition_type=EntryConditionType.CANDLE_STATE,
                    metadata={
                        'candle_type': 'bearish',
                        'open_price': open_price,
                        'close_price': close_price,
                        'high_price': high_price,
                        'low_price': low_price,
                        'body_ratio': body_ratio,
                        'price_change_percent': price_change_ratio * 100,
                        'candle_color': 'red'
                    }
                )
            
            # Reset candle data periodically (every minute for fresh analysis)
            candle_age = datetime.now() - candle['start_time']
            if candle_age.total_seconds() > 60:  # Reset every minute
                self.candle_data[symbol] = {
                    'open': current_price,
                    'high': current_price,
                    'low': current_price,
                    'close': current_price,
                    'start_time': datetime.now()
                }
            
            if signal:
                self.performance_stats['signals_generated'] += 1
                self.logger.debug(f"Candle state signal for {symbol}: {signal.signal_type}")
            
            return signal
            
        except Exception as e:
            self.logger.error(f"Error in CandleStateCondition for {symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)


# ============================================================================
# 4 Exit Conditions Implementation (PRD 청산 조건 명세서)
# ============================================================================

class BaseExitCondition(ABC):
    """Base class for exit conditions"""
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        """Initialize exit condition.
        
        Args:
            logger: System logger instance
            enabled: Whether this condition is enabled
        """
        self.logger = logger
        self.enabled = enabled
        self.last_check_time = datetime.now()
        self.performance_stats = {
            'checks_count': 0,
            'exits_triggered': 0,
            'avg_check_time_ms': 0.0
        }
    
    @abstractmethod
    async def check_exit_condition(self, 
                                 position: Position, 
                                 market_data: Dict[str, Any],
                                 indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """Check if exit condition is met.
        
        Args:
            position: Current position to check
            market_data: Latest market data
            indicators: Technical indicators data
            
        Returns:
            Dict containing exit order details if condition is met, None otherwise
            Format: {
                'exit_type': str,
                'size': float,
                'price': float,
                'partial': bool,
                'reason': str,
                'metadata': Dict[str, Any]
            }
        """
        pass
    
    def _performance_track_start(self) -> float:
        """Start performance tracking."""
        import time
        return time.perf_counter()
    
    def _performance_track_end(self, start_time: float) -> None:
        """End performance tracking."""
        import time
        elapsed = (time.perf_counter() - start_time) * 1000  # Convert to ms
        
        self.performance_stats['checks_count'] += 1
        self.performance_stats['avg_check_time_ms'] = (
            (self.performance_stats['avg_check_time_ms'] * (self.performance_stats['checks_count'] - 1) + elapsed)
            / self.performance_stats['checks_count']
        )
        
        if elapsed > 5:  # Log if check takes more than 5ms for exit conditions
            self.logger.warning(f"{self.__class__.__name__} check took {elapsed:.2f}ms (target: <5ms)")
    
    def get_performance_stats(self) -> Dict[str, Any]:
        """Get performance statistics."""
        return self.performance_stats.copy()


class PCSLiquidationCondition(BaseExitCondition):
    """
    PCS 3단계 청산 시스템 (Enhanced) - Exit Condition 1
    
    새로운 3단계 청산 시스템:
    - 1단 청산: 30% 부분 청산 (2% 수익 달성)
    - 2단 청산: 50% 추가 청산 (Price Channel 이탈 감지) 
    - 3단 청산: 100% 완전 청산 (추세 반전 패턴 감지)
    
    고급 기능:
    - 실시간 Price Channel 계산 (20일 기간)
    - 추세 반전 패턴 자동 감지
    - 트레일링 스톱 및 무손실 구간 설정
    - Performance target: <5ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        
        # PCS 3단계 시스템 초기화
        self.pcs_executor = PCSExitExecutor(logger)
        self.price_channel_calculator = PriceChannelCalculator(logger, period=20)
        self.channel_breakout_detector = ChannelBreakoutDetector(logger, threshold_percentage=0.5)
        self.performance_analyzer = PerformanceAnalyzer(logger)
        
        # 포지션별 PCS 상태 관리 
        self.pcs_positions: Dict[str, PCSPosition] = {}
        
        # 설정
        self.config = {
            'enable_3_stage_liquidation': True,  # 3단계 청산 시스템 사용
            'stage1_profit_threshold': 2.0,      # 1단 청산: 2% 수익
            'stage1_liquidation_ratio': 0.3,     # 30% 청산
            'stage2_channel_threshold': 0.5,     # 2단 청산: 0.5% 채널 이탈
            'stage2_liquidation_ratio': 0.5,     # 50% 청산 (잔여의)
            'trailing_stop_distance': 2.0,       # 2% 트레일링 스톱
            'channel_calculation_period': 20,    # 20일 Price Channel
            'min_reversal_candles': 2,          # 최소 반전 캔들 수
            'min_hold_time_minutes': 5,         # 최소 보유 시간 (분)
            'performance_target_ms': 5.0        # 성능 목표 (밀리초)
        }
        
    async def check_exit_condition(self, 
                                 position: Position, 
                                 market_data: Dict[str, Any],
                                 indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """PCS 3단계 청산 시스템 청산 조건 평가"""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            # 3단계 시스템 사용여부 확인
            if self.config.get('enable_3_stage_liquidation', True):
                return await self._check_3_stage_liquidation(position, market_data, indicators)
            else:
                # 레거시 12단계 시스템 (아래 별도 메소드로 구현)
                return await self._check_legacy_liquidation(position, market_data)
                
        except Exception as e:
            self.logger.error(f"PCS 청산 조건 평가 중 오류 ({position.symbol}): {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    async def _check_3_stage_liquidation(self, position: Position, 
                                       market_data: Dict[str, Any],
                                       indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """
        3단계 청산 시스템 평가
        
        1단 청산: 30% 부분 청산 (2% 수익)
        2단 청산: 50% 추가 청산 (Price Channel 이탈) 
        3단 청산: 100% 완전 청산 (추세 반전 패턴)
        """
        try:
            position_key = f"{position.symbol}_{position.side}_{id(position)}"
            
            # PCS 포지션 초기화 또는 검색
            if position_key not in self.pcs_positions:
                pcs_position = PCSPosition(
                    symbol=position.symbol,
                    side=position.side,
                    original_size=position.size,
                    entry_price=position.entry_price,
                    entry_time=position.timestamp
                )
                self.pcs_positions[position_key] = pcs_position
            else:
                pcs_position = self.pcs_positions[position_key]
                # 포지션 크기 업데이트 (부분 청산 반영)
                pcs_position.remaining_size = position.size
            
            # Price Channel 데이터 추가 (캔들 데이터 사용)
            klines = market_data.get('klines', [])
            if klines:
                for kline in klines[-self.config['channel_calculation_period']:]:
                    self.price_channel_calculator.add_kline_data(position.symbol, kline)
            
            # Price Channel 계산
            price_channel = self.price_channel_calculator.calculate_price_channel(position.symbol)
            
            # 기술적 지표에 Price Channel 정보 추가
            if indicators and price_channel:
                if not hasattr(indicators, 'price_channel'):
                    indicators.price_channel = {}
                indicators.price_channel['upper'] = price_channel.upper_line
                indicators.price_channel['lower'] = price_channel.lower_line
            
            # PCS 청산 조건 평가
            exit_info = await self.pcs_executor.evaluate_exit_conditions(
                pcs_position, market_data, indicators
            )
            
            if exit_info:
                # 청산 실행
                success = await self.pcs_executor.execute_liquidation(pcs_position, exit_info)
                
                if success:
                    self.performance_stats['exits_triggered'] += 1
                    
                    # 청산 완료 시 성능 분석에 추가
                    if pcs_position.current_stage == PCSLiquidationStage.COMPLETED:
                        self.performance_analyzer.add_completed_position(pcs_position)
                        del self.pcs_positions[position_key]
                    
                    # 기존 Position 객체와 호환되는 결과 반환
                    return self._convert_to_legacy_exit_result(exit_info, pcs_position)
                    
            return None
            
        except Exception as e:
            self.logger.error(f"3단계 청산 시스템 평가 중 오류: {e}")
            return None
    
    def _convert_to_legacy_exit_result(self, exit_info: Dict[str, Any], 
                                     pcs_position: PCSPosition) -> Dict[str, Any]:
        """PCS 청산 결과를 기존 trading_engine 호환 형식으로 변환"""
        stage = exit_info['stage']
        urgency = exit_info.get('urgency', ExitUrgency.MEDIUM)
        
        # 기존 시스템과 호환되도록 exit_type 설정
        if stage == PCSLiquidationStage.STAGE_1:
            exit_type = 'PCS_STAGE1_PROFIT_30PCT'
            partial = True
            full_exit = False
        elif stage == PCSLiquidationStage.STAGE_2: 
            exit_type = 'PCS_STAGE2_CHANNEL_50PCT'
            partial = True if pcs_position.remaining_size > exit_info['liquidation_amount'] else False
            full_exit = False
        else:  # STAGE_3
            exit_type = 'PCS_STAGE3_REVERSAL_100PCT' 
            partial = False
            full_exit = True
        
        return {
            'exit_type': exit_type,
            'size': exit_info['liquidation_amount'],
            'price': exit_info['liquidation_price'],
            'partial': partial,
            'full_exit': full_exit,
            'reason': exit_info['reason'],
            'urgency': urgency.value,
            'metadata': {
                'pcs_stage': stage.value,
                'liquidation_amount': exit_info['liquidation_amount'],
                'liquidation_price': exit_info['liquidation_price'], 
                'remaining_size': pcs_position.remaining_size - exit_info['liquidation_amount'],
                'total_pnl': pcs_position.total_pnl,
                'hold_time_minutes': pcs_position.hold_time.total_seconds() / 60,
                'post_actions': exit_info.get('post_actions', [])
            }
        }
    
    async def _check_legacy_liquidation(self, position: Position, market_data: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """
        기존 12단계 청산 시스템 (호환성을 위해 유지)
        
        12단계 익절/손절 설정으로 1STEP 또는 2STEP 청산
        """
        try:
            current_price = position.current_price
            entry_price = position.entry_price
            
            # PnL 비율 계산
            if position.side == 'long':
                pnl_percentage = ((current_price - entry_price) / entry_price) * 100
            else:
                pnl_percentage = ((entry_price - current_price) / entry_price) * 100
            
            # 레거시 12단계 설정
            legacy_levels = {
                1: {'take_profit': 2.0, 'stop_loss': -1.0},
                2: {'take_profit': 4.0, 'stop_loss': -2.0}, 
                3: {'take_profit': 6.0, 'stop_loss': -3.0},
                4: {'take_profit': 8.0, 'stop_loss': -4.0},
                5: {'take_profit': 10.0, 'stop_loss': -5.0},
                6: {'take_profit': 12.0, 'stop_loss': -6.0}
            }
            
            # 활성화된 레벨 확인
            for level in [1, 2, 3]:  # 기본 3단계만 사용
                if level not in legacy_levels:
                    continue
                    
                level_config = legacy_levels[level]
                take_profit = level_config['take_profit']
                stop_loss = level_config['stop_loss']
                
                # 레벨 상태 초기화
                level_key = f'pcs_legacy_level_{level}'
                if level_key not in position.exit_conditions_state:
                    position.exit_conditions_state[level_key] = {'triggered': False}
                
                level_state = position.exit_conditions_state[level_key]
                if level_state['triggered']:
                    continue
                
                # 조건 확인
                if pnl_percentage >= take_profit:
                    level_state['triggered'] = True
                    return {
                        'exit_type': f'PCS_LEGACY_PROFIT_L{level}',
                        'size': position.size,
                        'price': position.current_price,
                        'partial': False,
                        'full_exit': True,
                        'reason': f'Legacy_Take_Profit_Level_{level}',
                        'metadata': {
                            'legacy_level': level,
                            'trigger_pnl': take_profit,
                            'actual_pnl': pnl_percentage
                        }
                    }
                elif pnl_percentage <= stop_loss:
                    level_state['triggered'] = True
                    return {
                        'exit_type': f'PCS_LEGACY_LOSS_L{level}',
                        'size': position.size,
                        'price': position.current_price,
                        'partial': False,
                        'full_exit': True,
                        'reason': f'Legacy_Stop_Loss_Level_{level}',
                        'metadata': {
                            'legacy_level': level,
                            'trigger_pnl': stop_loss,
                            'actual_pnl': pnl_percentage
                        }
                    }
            
            return None
            
        except Exception as e:
            self.logger.error(f"레거시 청산 시스템 평가 중 오류: {e}")
            return None
    
    def get_pcs_performance_report(self) -> Dict[str, Any]:
        """
        PCS 3단계 청산 시스템 성능 보고서 반환
        """
        try:
            performance_report = self.performance_analyzer.generate_performance_report()
            executor_metrics = self.pcs_executor.performance_metrics
            channel_stats = {}
            
            # 심볼별 Price Channel 계산 성능
            for symbol in self.pcs_positions.keys():
                symbol_name = symbol.split('_')[0]
                channel_stats[symbol_name] = self.price_channel_calculator.get_performance_stats(symbol_name)
            
            return {
                'pcs_performance': performance_report,
                'executor_metrics': executor_metrics,
                'channel_calculation_stats': channel_stats,
                'active_positions': len(self.pcs_positions),
                'system_health': 'OK' if executor_metrics['performance_target_met'] else 'WARNING'
            }
            
        except Exception as e:
            self.logger.error(f"PCS 성능 보고서 생성 중 오류: {e}")
            return {'error': str(e)}
    
    def cleanup_completed_positions(self):
        """청산 완료된 포지션 정리"""
        completed_keys = []
        for key, pcs_pos in self.pcs_positions.items():
            if pcs_pos.current_stage == PCSLiquidationStage.COMPLETED:
                completed_keys.append(key)
        
        for key in completed_keys:
            del self.pcs_positions[key]
        
        if completed_keys:
            self.logger.info(f"청산 완료 포지션 {len(completed_keys)}개 정리 완료")


class PCTrailingCondition(BaseExitCondition):
    """
    PC 트레일링 청산 (PCT 손실중 청산) - Exit Condition 2
    
    Features:
    - 매수 시: 하단선 하락 → 청산
    - 매도 시: 상단선 상승 → 청산
    - 손실중 청산 옵션: "손실중에만 청산" 설정
    - Price Channel 기반 동적 트레일링
    - Performance target: <5ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'channel_period': 20,                    # Price Channel 기간
            'trailing_sensitivity': 0.5,             # 트레일링 민감도 (0.1 ~ 1.0)
            'only_when_losing': True,                # 손실중에만 청산
            'min_trail_distance_percent': 1.0,       # 최소 트레일링 거리 (%)
            'enable_long_trailing': True,            # 매수 포지션 트레일링
            'enable_short_trailing': True,           # 매도 포지션 트레일링
        }
        self.price_history: Dict[str, List[float]] = {}
        
    async def check_exit_condition(self, 
                                 position: Position, 
                                 market_data: Dict[str, Any],
                                 indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """Check PC trailing conditions."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            symbol = position.symbol
            current_price = position.current_price
            entry_price = position.entry_price
            side = position.side
            
            # Initialize Price Channel state if not exists
            pc_key = 'pc_trailing_state'
            if pc_key not in position.exit_conditions_state:
                position.exit_conditions_state[pc_key] = {
                    'highest_channel_high': current_price if side == 'short' else None,
                    'lowest_channel_low': current_price if side == 'long' else None,
                    'last_channel_update': datetime.now()
                }
            
            pc_state = position.exit_conditions_state[pc_key]
            
            # Calculate current PnL
            if side == 'long':
                current_pnl = (current_price - entry_price) / entry_price
            else:  # short
                current_pnl = (entry_price - current_price) / entry_price
            
            # Check "손실중에만 청산" condition
            if self.config['only_when_losing'] and current_pnl >= 0:
                return None
            
            # Get price history for channel calculation
            if symbol not in self.price_history:
                self.price_history[symbol] = []
            
            # Add current price to history
            self.price_history[symbol].append(current_price)
            if len(self.price_history[symbol]) > self.config['channel_period']:
                self.price_history[symbol] = self.price_history[symbol][-self.config['channel_period']:]
            
            # Need minimum history for channel calculation
            if len(self.price_history[symbol]) < self.config['channel_period']:
                return None
            
            # Calculate Price Channel
            price_list = self.price_history[symbol][:-1]  # Exclude current price
            channel_high = max(price_list)
            channel_low = min(price_list)
            
            exit_result = None
            
            if side == 'long' and self.config['enable_long_trailing']:
                # 매수 포지션: 하단선 하락 시 청산
                if pc_state['lowest_channel_low'] is None:
                    pc_state['lowest_channel_low'] = channel_low
                
                # Update trailing low
                if channel_low > pc_state['lowest_channel_low']:
                    pc_state['lowest_channel_low'] = channel_low
                    pc_state['last_channel_update'] = datetime.now()
                
                # Check for trailing exit (하단선이 하락했을 때)
                trail_distance = (pc_state['lowest_channel_low'] - channel_low) / pc_state['lowest_channel_low']
                min_distance = self.config['min_trail_distance_percent'] / 100
                
                if (channel_low < pc_state['lowest_channel_low'] and 
                    trail_distance >= min_distance):
                    
                    exit_result = {
                        'exit_type': 'PC_TRAILING_LONG_EXIT',
                        'size': position.size,
                        'price': current_price,
                        'partial': False,
                        'full_exit': True,
                        'reason': 'PC_Lower_Channel_Declined',
                        'metadata': {
                            'side': 'long',
                            'channel_period': self.config['channel_period'],
                            'previous_low': pc_state['lowest_channel_low'],
                            'current_low': channel_low,
                            'trail_distance_percent': trail_distance * 100,
                            'current_pnl_percent': current_pnl * 100,
                            'only_when_losing': self.config['only_when_losing']
                        }
                    }
            
            elif side == 'short' and self.config['enable_short_trailing']:
                # 매도 포지션: 상단선 상승 시 청산
                if pc_state['highest_channel_high'] is None:
                    pc_state['highest_channel_high'] = channel_high
                
                # Update trailing high
                if channel_high < pc_state['highest_channel_high']:
                    pc_state['highest_channel_high'] = channel_high
                    pc_state['last_channel_update'] = datetime.now()
                
                # Check for trailing exit (상단선이 상승했을 때)
                trail_distance = (channel_high - pc_state['highest_channel_high']) / pc_state['highest_channel_high']
                min_distance = self.config['min_trail_distance_percent'] / 100
                
                if (channel_high > pc_state['highest_channel_high'] and 
                    trail_distance >= min_distance):
                    
                    exit_result = {
                        'exit_type': 'PC_TRAILING_SHORT_EXIT',
                        'size': position.size,
                        'price': current_price,
                        'partial': False,
                        'full_exit': True,
                        'reason': 'PC_Upper_Channel_Increased',
                        'metadata': {
                            'side': 'short',
                            'channel_period': self.config['channel_period'],
                            'previous_high': pc_state['highest_channel_high'],
                            'current_high': channel_high,
                            'trail_distance_percent': trail_distance * 100,
                            'current_pnl_percent': current_pnl * 100,
                            'only_when_losing': self.config['only_when_losing']
                        }
                    }
            
            if exit_result:
                self.performance_stats['exits_triggered'] += 1
                self.logger.info(f"PC Trailing exit triggered for {position.symbol}: "
                               f"Side: {side}, Reason: {exit_result['reason']}")
            
            return exit_result
            
        except Exception as e:
            self.logger.error(f"Error in PCTrailingCondition for {position.symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)


class TickBasedExitCondition(BaseExitCondition):
    """
    호가 청산 (틱 기반) - Exit Condition 3
    
    Features:
    - 매수 포지션: 5틱 하락 시 청산
    - 매도 포지션: 5틱 상승 시 청산
    - 설정 가능한 틱 임계값
    - 즉시 시장가 청산
    - Performance target: <5ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'long_exit_down_ticks': 5,      # 매수 포지션: N틱 하락 시 청산
            'short_exit_up_ticks': 5,       # 매도 포지션: N틱 상승 시 청산
            'tick_size_threshold': 0.0001,  # 최소 틱 크기
            'consecutive_ticks_only': True,  # 연속 틱만 카운트
        }
        self.tick_history: Dict[str, List[Dict[str, Any]]] = {}
        
    async def check_exit_condition(self, 
                                 position: Position, 
                                 market_data: Dict[str, Any],
                                 indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """Check tick-based exit conditions."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            symbol = position.symbol
            current_price = position.current_price
            side = position.side
            
            ticker = market_data.get('tickers', {}).get(symbol)
            if not ticker:
                return None
            
            # Initialize tick history for symbol
            if symbol not in self.tick_history:
                self.tick_history[symbol] = []
            
            # Record current tick
            tick_data = {
                'price': current_price,
                'timestamp': datetime.now(),
                'bid': ticker.bid,
                'ask': ticker.ask
            }
            
            self.tick_history[symbol].append(tick_data)
            
            # Keep last 20 ticks for analysis
            if len(self.tick_history[symbol]) > 20:
                self.tick_history[symbol] = self.tick_history[symbol][-20:]
            
            # Need minimum ticks for analysis
            required_ticks = max(self.config['long_exit_down_ticks'], self.config['short_exit_up_ticks'])
            if len(self.tick_history[symbol]) < required_ticks + 1:
                return None
            
            # Analyze recent tick movements
            recent_ticks = self.tick_history[symbol][-required_ticks-1:]
            
            exit_result = None
            
            if side == 'long':
                # 매수 포지션: 연속 하락 틱 확인
                down_tick_count = self._count_consecutive_ticks(recent_ticks, 'down')
                
                if down_tick_count >= self.config['long_exit_down_ticks']:
                    exit_result = {
                        'exit_type': 'TICK_BASED_LONG_EXIT',
                        'size': position.size,
                        'price': ticker.bid,  # 즉시 시장가 청산 (bid price for long exit)
                        'partial': False,
                        'full_exit': True,
                        'reason': f'Consecutive_Down_Ticks_{down_tick_count}',
                        'metadata': {
                            'side': 'long',
                            'down_ticks_count': down_tick_count,
                            'threshold': self.config['long_exit_down_ticks'],
                            'exit_price_type': 'bid',
                            'recent_prices': [t['price'] for t in recent_ticks[-5:]]
                        }
                    }
            
            elif side == 'short':
                # 매도 포지션: 연속 상승 틱 확인
                up_tick_count = self._count_consecutive_ticks(recent_ticks, 'up')
                
                if up_tick_count >= self.config['short_exit_up_ticks']:
                    exit_result = {
                        'exit_type': 'TICK_BASED_SHORT_EXIT',
                        'size': position.size,
                        'price': ticker.ask,  # 즉시 시장가 청산 (ask price for short exit)
                        'partial': False,
                        'full_exit': True,
                        'reason': f'Consecutive_Up_Ticks_{up_tick_count}',
                        'metadata': {
                            'side': 'short',
                            'up_ticks_count': up_tick_count,
                            'threshold': self.config['short_exit_up_ticks'],
                            'exit_price_type': 'ask',
                            'recent_prices': [t['price'] for t in recent_ticks[-5:]]
                        }
                    }
            
            if exit_result:
                self.performance_stats['exits_triggered'] += 1
                self.logger.info(f"Tick-based exit triggered for {position.symbol}: "
                               f"Side: {side}, Reason: {exit_result['reason']}")
            
            return exit_result
            
        except Exception as e:
            self.logger.error(f"Error in TickBasedExitCondition for {position.symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)
    
    def _count_consecutive_ticks(self, ticks: List[Dict[str, Any]], direction: str) -> int:
        """Count consecutive ticks in specified direction."""
        if len(ticks) < 2:
            return 0
        
        consecutive_count = 0
        max_consecutive = 0
        
        for i in range(1, len(ticks)):
            current_price = ticks[i]['price']
            previous_price = ticks[i-1]['price']
            price_diff = abs(current_price - previous_price)
            
            # Skip if price difference is too small
            if price_diff < self.config['tick_size_threshold']:
                if not self.config['consecutive_ticks_only']:
                    continue
                else:
                    consecutive_count = 0
                    continue
            
            # Check direction
            if direction == 'up' and current_price > previous_price:
                consecutive_count += 1
            elif direction == 'down' and current_price < previous_price:
                consecutive_count += 1
            else:
                if self.config['consecutive_ticks_only']:
                    consecutive_count = 0
            
            max_consecutive = max(max_consecutive, consecutive_count)
        
        return max_consecutive


class PCBreakevenCondition(BaseExitCondition):
    """
    PC 본절 청산 (2단계 시스템) - Exit Condition 4
    
    Features:
    - 1단계: PC선 돌파 확인
    - 2단계: 진입가 복귀 시 청산
    - Break-even 로직 구현
    - Performance target: <5ms execution time
    """
    
    def __init__(self, logger: SystemLogger, enabled: bool = True):
        super().__init__(logger, enabled)
        self.config = {
            'channel_period': 20,                    # Price Channel 기간
            'breakout_confirmation_percent': 0.5,    # 돌파 확인 임계값 (%)
            'breakeven_tolerance_percent': 0.2,      # 본절 허용 오차 (%)
            'stage1_timeout_minutes': 30,            # 1단계 타임아웃 (분)
            'enable_long_breakeven': True,           # 매수 포지션 본절
            'enable_short_breakeven': True,          # 매도 포지션 본절
        }
        self.price_history: Dict[str, List[float]] = {}
        
    async def check_exit_condition(self, 
                                 position: Position, 
                                 market_data: Dict[str, Any],
                                 indicators: Optional[TechnicalIndicators] = None) -> Optional[Dict[str, Any]]:
        """Check PC breakeven conditions (2-stage system)."""
        if not self.enabled:
            return None
            
        start_time = self._performance_track_start()
        
        try:
            symbol = position.symbol
            current_price = position.current_price
            entry_price = position.entry_price
            side = position.side
            
            # Initialize breakeven state if not exists
            be_key = 'pc_breakeven_state'
            if be_key not in position.exit_conditions_state:
                position.exit_conditions_state[be_key] = {
                    'stage': 1,  # 1: PC 돌파 대기, 2: 진입가 복귀 대기
                    'breakout_confirmed': False,
                    'breakout_time': None,
                    'breakout_direction': None,  # 'up' or 'down'
                    'breakout_price': None
                }
            
            be_state = position.exit_conditions_state[be_key]
            
            # Check stage 1 timeout
            if (be_state['stage'] == 2 and be_state['breakout_time'] and
                datetime.now() - be_state['breakout_time'] > timedelta(minutes=self.config['stage1_timeout_minutes'])):
                # Reset to stage 1 after timeout
                be_state['stage'] = 1
                be_state['breakout_confirmed'] = False
                self.logger.debug(f"PC Breakeven stage 1 timeout for {symbol}, resetting to stage 1")
            
            # Get Price Channel data
            if symbol not in self.price_history:
                self.price_history[symbol] = []
            
            self.price_history[symbol].append(current_price)
            if len(self.price_history[symbol]) > self.config['channel_period']:
                self.price_history[symbol] = self.price_history[symbol][-self.config['channel_period']:]
            
            # Need minimum history
            if len(self.price_history[symbol]) < self.config['channel_period']:
                return None
            
            # Calculate Price Channel
            price_list = self.price_history[symbol][:-1]  # Exclude current price
            channel_high = max(price_list)
            channel_low = min(price_list)
            
            exit_result = None
            
            if be_state['stage'] == 1:
                # Stage 1: PC 돌파 확인
                breakout_threshold_up = channel_high * (1 + self.config['breakout_confirmation_percent'] / 100)
                breakout_threshold_down = channel_low * (1 - self.config['breakout_confirmation_percent'] / 100)
                
                if side == 'long' and self.config['enable_long_breakeven']:
                    # 매수 포지션: 상단선 돌파 확인
                    if current_price > breakout_threshold_up:
                        be_state['stage'] = 2
                        be_state['breakout_confirmed'] = True
                        be_state['breakout_time'] = datetime.now()
                        be_state['breakout_direction'] = 'up'
                        be_state['breakout_price'] = current_price
                        
                        self.logger.info(f"PC Breakeven Stage 1 completed for {symbol} (LONG): "
                                       f"Upper breakout at {current_price:.6f}")
                
                elif side == 'short' and self.config['enable_short_breakeven']:
                    # 매도 포지션: 하단선 돌파 확인
                    if current_price < breakout_threshold_down:
                        be_state['stage'] = 2
                        be_state['breakout_confirmed'] = True
                        be_state['breakout_time'] = datetime.now()
                        be_state['breakout_direction'] = 'down'
                        be_state['breakout_price'] = current_price
                        
                        self.logger.info(f"PC Breakeven Stage 1 completed for {symbol} (SHORT): "
                                       f"Lower breakout at {current_price:.6f}")
            
            elif be_state['stage'] == 2:
                # Stage 2: 진입가 복귀 확인 (본절 청산)
                tolerance = entry_price * (self.config['breakeven_tolerance_percent'] / 100)
                breakeven_upper = entry_price + tolerance
                breakeven_lower = entry_price - tolerance
                
                # 진입가 근처로 복귀했는지 확인
                if breakeven_lower <= current_price <= breakeven_upper:
                    exit_result = {
                        'exit_type': f'PC_BREAKEVEN_{side.upper()}_EXIT',
                        'size': position.size,
                        'price': current_price,
                        'partial': False,
                        'full_exit': True,
                        'reason': 'PC_Breakeven_Entry_Price_Return',
                        'metadata': {
                            'side': side,
                            'entry_price': entry_price,
                            'current_price': current_price,
                            'breakout_price': be_state['breakout_price'],
                            'breakout_direction': be_state['breakout_direction'],
                            'breakout_time': be_state['breakout_time'],
                            'stage1_duration_minutes': ((datetime.now() - be_state['breakout_time']).total_seconds() / 60) if be_state['breakout_time'] else 0,
                            'tolerance_percent': self.config['breakeven_tolerance_percent'],
                            'channel_high': channel_high,
                            'channel_low': channel_low
                        }
                    }
            
            if exit_result:
                self.performance_stats['exits_triggered'] += 1
                self.logger.info(f"PC Breakeven exit triggered for {position.symbol}: "
                               f"Side: {side}, Stage: {be_state['stage']}")
                
                # Reset state after exit
                be_state['stage'] = 1
                be_state['breakout_confirmed'] = False
            
            return exit_result
            
        except Exception as e:
            self.logger.error(f"Error in PCBreakevenCondition for {position.symbol}: {e}")
            return None
        finally:
            self._performance_track_end(start_time)