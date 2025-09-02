"""
Risk Manager Module

Implements comprehensive 12-level risk management system as specified in PRD.
Handles position sizing, drawdown control, exposure limits, and risk monitoring.
"""

from typing import Dict, List, Optional, Tuple, Any, Union
from enum import Enum
from dataclasses import dataclass, field
from datetime import datetime, timedelta
import asyncio
import logging
import numpy as np
from collections import defaultdict
import json
import threading

from .logger import SystemLogger
from .constants import RiskConstants, PerformanceTargets
from .exceptions import (
    RiskLimitExceededError, ExcessiveDrawdownError, 
    ConsecutiveLossLimitError, InsufficientBalanceError
)


class RiskLevel(Enum):
    """Risk level classifications"""
    VERY_LOW = 1
    LOW = 2
    LOW_MEDIUM = 3
    MEDIUM_LOW = 4
    MEDIUM = 5
    MEDIUM_HIGH = 6
    HIGH_MEDIUM = 7
    HIGH = 8
    VERY_HIGH = 9
    EXTREME = 10
    CRITICAL = 11
    EMERGENCY = 12


@dataclass
class RiskMetrics:
    """Risk metrics data structure"""
    total_exposure: float
    max_drawdown: float
    current_drawdown: float
    var_95: float  # Value at Risk 95%
    sharpe_ratio: float
    win_rate: float
    avg_win: float
    avg_loss: float
    risk_score: int
    timestamp: datetime


class PositionStatus(Enum):
    """Position status types"""
    OPEN = "open"
    CLOSED = "closed"
    LIQUIDATED = "liquidated"
    STOPPED = "stopped"
    

class PositionSide(Enum):
    """Position side types"""
    LONG = "long"
    SHORT = "short"


@dataclass
class ProfitLossLevel:
    """Profit/Loss level configuration (12단계 익절/손절 시스템)"""
    level: int  # 1-6
    percentage: float  # +2%, +4%, +6%, +8%, +10%, +12% for profit; -1%, -2%, -3%, -4%, -5%, -6% for loss
    is_profit: bool  # True for profit, False for loss
    executed: bool = False
    execution_time: Optional[datetime] = None
    execution_price: Optional[float] = None
    partial_close_ratio: float = 0.2  # Close 20% of position at each level
    

@dataclass 
class Position:
    """
    Individual position with 12-level profit/loss management
    개별 포지션 12단계 익절/손절 관리 시스템
    """
    # Basic position information
    position_id: str
    symbol: str
    side: PositionSide
    size: float  # Position size in USDT
    entry_price: float
    current_price: float
    leverage: float = 1.0
    
    # Timestamps
    open_time: datetime = field(default_factory=datetime.now)
    close_time: Optional[datetime] = None
    
    # Position status
    status: PositionStatus = PositionStatus.OPEN
    remaining_size: float = field(init=False)  # Remaining position size after partial closes
    
    # 12-level profit/loss system
    profit_levels: List[ProfitLossLevel] = field(init=False)
    loss_levels: List[ProfitLossLevel] = field(init=False)
    
    # Risk metrics
    max_drawdown: float = 0.0
    max_profit: float = 0.0
    realized_pnl: float = 0.0
    unrealized_pnl: float = field(init=False)
    
    # Additional tracking
    fee_paid: float = 0.0
    metadata: Dict[str, Any] = field(default_factory=dict)
    
    def __post_init__(self):
        """Initialize profit/loss levels after object creation"""
        self.remaining_size = self.size
        self._setup_profit_loss_levels()
        
    def _setup_profit_loss_levels(self) -> None:
        """
        Setup 12-level profit/loss system
        익절 단계: +2.0%, +4.0%, +6.0%, +8.0%, +10.0%, +12.0%
        손절 단계: -1.0%, -2.0%, -3.0%, -4.0%, -5.0%, -6.0%
        """
        # Profit levels (익절)
        profit_percentages = RiskConstants.PROFIT_LEVELS
        self.profit_levels = [
            ProfitLossLevel(level=i+1, percentage=pct, is_profit=True)
            for i, pct in enumerate(profit_percentages)
        ]
        
        # Loss levels (손절)
        loss_percentages = RiskConstants.LOSS_LEVELS
        self.loss_levels = [
            ProfitLossLevel(level=i+1, percentage=-pct, is_profit=False)
            for i, pct in enumerate(loss_percentages)
        ]
    
    @property
    def unrealized_pnl(self) -> float:
        """Calculate unrealized PnL"""
        price_diff = self.current_price - self.entry_price
        if self.side == PositionSide.SHORT:
            price_diff = -price_diff
        return (price_diff / self.entry_price) * self.remaining_size
    
    @property
    def total_pnl(self) -> float:
        """Total PnL (realized + unrealized)"""
        return self.realized_pnl + self.unrealized_pnl
    
    @property
    def pnl_percentage(self) -> float:
        """PnL as percentage of original position size"""
        if self.size == 0:
            return 0.0
        return self.total_pnl / self.size
    
    @property
    def liquidation_price(self) -> float:
        """Calculate liquidation price for leveraged positions"""
        if self.leverage <= 1.0:
            return 0.0 if self.side == PositionSide.LONG else float('inf')
            
        # Simplified liquidation price calculation
        maintenance_margin_rate = RiskConstants.MAINTENANCE_MARGIN_RATE
        
        if self.side == PositionSide.LONG:
            return self.entry_price * (1 - (1/self.leverage) + maintenance_margin_rate)
        else:
            return self.entry_price * (1 + (1/self.leverage) - maintenance_margin_rate)
    
    @property
    def distance_to_liquidation(self) -> float:
        """Distance to liquidation as percentage"""
        if self.leverage <= 1.0:
            return 1.0  # No liquidation risk for spot positions
            
        liq_price = self.liquidation_price
        if liq_price == 0 or liq_price == float('inf'):
            return 1.0
            
        return abs(self.current_price - liq_price) / self.current_price
    
    def update_price(self, new_price: float) -> List[ProfitLossLevel]:
        """
        Update current price and check for profit/loss level triggers
        Returns list of triggered levels
        """
        self.current_price = new_price
        
        # Update max profit/drawdown tracking
        current_pnl_pct = self.pnl_percentage
        if current_pnl_pct > 0:
            self.max_profit = max(self.max_profit, current_pnl_pct)
        else:
            self.max_drawdown = max(self.max_drawdown, abs(current_pnl_pct))
        
        triggered_levels = []
        
        # Check profit levels
        for level in self.profit_levels:
            if not level.executed and current_pnl_pct >= level.percentage:
                triggered_levels.append(level)
                
        # Check loss levels  
        for level in self.loss_levels:
            if not level.executed and current_pnl_pct <= level.percentage:
                triggered_levels.append(level)
        
        return triggered_levels
    
    def execute_level(self, level: ProfitLossLevel, execution_price: float) -> float:
        """
        Execute profit/loss level (partial close)
        Returns the closed amount in USDT
        """
        if level.executed:
            return 0.0
            
        # Calculate amount to close
        close_amount = self.remaining_size * level.partial_close_ratio
        
        # Calculate PnL for this closure
        price_diff = execution_price - self.entry_price
        if self.side == PositionSide.SHORT:
            price_diff = -price_diff
            
        pnl = (price_diff / self.entry_price) * close_amount
        
        # Update position
        self.remaining_size -= close_amount
        self.realized_pnl += pnl
        
        # Mark level as executed
        level.executed = True
        level.execution_time = datetime.now()
        level.execution_price = execution_price
        
        # Close position completely if remaining size is too small
        if self.remaining_size < 10:  # Less than 10 USDT remaining
            self.close_position(execution_price)
            
        return close_amount
    
    def close_position(self, close_price: float) -> None:
        """Close position completely"""
        if self.status != PositionStatus.OPEN:
            return
            
        # Calculate final PnL
        price_diff = close_price - self.entry_price
        if self.side == PositionSide.SHORT:
            price_diff = -price_diff
            
        final_pnl = (price_diff / self.entry_price) * self.remaining_size
        self.realized_pnl += final_pnl
        
        # Update status
        self.remaining_size = 0.0
        self.status = PositionStatus.CLOSED
        self.close_time = datetime.now()
        self.current_price = close_price
    
    def get_risk_assessment(self) -> 'PositionRisk':
        """Get risk assessment for this position"""
        return PositionRisk(
            symbol=self.symbol,
            exposure_pct=self.remaining_size / 1000.0,  # Assuming 1000 USDT base
            leverage=self.leverage,
            liquidation_price=self.liquidation_price,
            distance_to_liquidation=self.distance_to_liquidation,
            volatility=0.02,  # TODO: Calculate actual volatility
            risk_level=self._calculate_position_risk_level()
        )
    
    def _calculate_position_risk_level(self) -> RiskLevel:
        """Calculate position-specific risk level"""
        # Base risk on distance to liquidation and position size
        distance = self.distance_to_liquidation
        size_ratio = self.remaining_size / self.size if self.size > 0 else 0
        
        if distance < 0.05:  # Less than 5% to liquidation
            return RiskLevel.EMERGENCY
        elif distance < 0.10:  # Less than 10% to liquidation
            return RiskLevel.CRITICAL
        elif size_ratio > 0.10:  # More than 10% of capital
            return RiskLevel.EXTREME
        elif size_ratio > 0.05:  # More than 5% of capital
            return RiskLevel.HIGH
        else:
            return RiskLevel.MEDIUM
    
    def to_dict(self) -> Dict[str, Any]:
        """Convert position to dictionary for serialization"""
        return {
            'position_id': self.position_id,
            'symbol': self.symbol,
            'side': self.side.value,
            'size': self.size,
            'remaining_size': self.remaining_size,
            'entry_price': self.entry_price,
            'current_price': self.current_price,
            'leverage': self.leverage,
            'status': self.status.value,
            'realized_pnl': self.realized_pnl,
            'unrealized_pnl': self.unrealized_pnl,
            'total_pnl': self.total_pnl,
            'pnl_percentage': self.pnl_percentage,
            'max_profit': self.max_profit,
            'max_drawdown': self.max_drawdown,
            'liquidation_price': self.liquidation_price,
            'distance_to_liquidation': self.distance_to_liquidation,
            'open_time': self.open_time.isoformat(),
            'close_time': self.close_time.isoformat() if self.close_time else None,
            'profit_levels': [
                {
                    'level': level.level,
                    'percentage': level.percentage,
                    'executed': level.executed,
                    'execution_time': level.execution_time.isoformat() if level.execution_time else None,
                    'execution_price': level.execution_price
                }
                for level in self.profit_levels
            ],
            'loss_levels': [
                {
                    'level': level.level,
                    'percentage': level.percentage,
                    'executed': level.executed,
                    'execution_time': level.execution_time.isoformat() if level.execution_time else None,
                    'execution_price': level.execution_price
                }
                for level in self.loss_levels
            ]
        }


@dataclass
class PositionRisk:
    """Individual position risk assessment"""
    symbol: str
    exposure_pct: float
    leverage: float
    liquidation_price: float
    distance_to_liquidation: float
    volatility: float
    risk_level: RiskLevel


class RiskManager:
    """
    Comprehensive 12-level risk management system with integrated portfolio management.
    
    Features:
    - 12-step profit/loss system: +2%,+4%,+6%,+8%,+10%,+12% (profit), -1%,-2%,-3%,-4%,-5%,-6% (loss)
    - Portfolio-based risk control with position correlation analysis
    - Real-time risk assessment and emergency liquidation conditions
    - Dynamic position sizing based on volatility and market conditions
    - Daily loss limit of -5.0% with "profit after loss resets system" logic
    
    Risk Levels:
    1-3: Conservative (Low risk, small positions)
    4-6: Moderate (Medium risk, standard positions)
    7-9: Aggressive (High risk, larger positions)
    10-12: Emergency (Critical risk, emergency protocols)
    """
    
    def __init__(self, 
                 logger: SystemLogger, 
                 initial_capital: float = 10000.0,
                 default_position_size: float = 1000.0,
                 max_positions: int = 3,
                 max_leverage_exposure: float = 10000.0,
                 backtesting_mode: bool = False):
        """Initialize comprehensive risk manager with portfolio integration."""
        self.logger = logger
        self.initial_capital = initial_capital
        self.current_capital = initial_capital
        self.max_capital = initial_capital
        self.backtesting_mode = backtesting_mode
        
        # Initialize integrated portfolio
        self.portfolio = Portfolio(
            logger=logger,
            initial_capital=initial_capital,
            default_position_size=default_position_size,
            max_positions=max_positions,
            max_leverage_exposure=max_leverage_exposure
        )
        
        # Risk configuration (enhanced for 12-level system)
        self.max_position_size_pct = 0.10  # 10% max per position (1000/10000)
        self.max_total_exposure_pct = 0.30  # 30% max total exposure (3 positions * 10%)
        self.max_drawdown_pct = 0.20  # 20% max drawdown (emergency stop)
        self.max_daily_loss_pct = 0.05  # 5% max daily loss
        
        # 12-level profit/loss configuration
        self.profit_levels = [0.02, 0.04, 0.06, 0.08, 0.10, 0.12]  # 2%, 4%, 6%, 8%, 10%, 12%
        self.loss_levels = [0.01, 0.02, 0.03, 0.04, 0.05, 0.06]     # 1%, 2%, 3%, 4%, 5%, 6%
        
        # Risk tracking (enhanced)
        self.risk_events: List[Dict[str, Any]] = []
        self.market_volatility_cache: Dict[str, float] = {}  # symbol -> volatility
        self.correlation_threshold = 0.7  # High correlation threshold
        
        # Real-time monitoring
        self.last_risk_check = datetime.now()
        self.risk_check_interval = 5  # seconds
        self.price_update_buffer: Dict[str, List[Tuple[datetime, float]]] = defaultdict(list)
        
        # Emergency controls (enhanced)
        self.emergency_stop = False
        self.risk_override = False
        self.emergency_liquidation_active = False
        self.last_emergency_check = datetime.now()
        
        # Advanced risk features
        self.dynamic_sizing_enabled = True
        self.market_regime = "normal"  # normal, volatile, trending, ranging
        self.regime_adjustment_factor = 1.0
        
        # Risk level configurations
        self._setup_risk_levels()
        
    def _setup_risk_levels(self) -> None:
        """Setup risk level configurations."""
        self.risk_levels = {
            RiskLevel.VERY_LOW: {
                'max_position_pct': 0.01,  # 1%
                'max_leverage': 2.0,
                'max_exposure_pct': 0.05,  # 5%
                'stop_loss_pct': 0.02,  # 2%
                'description': 'Very conservative trading'
            },
            RiskLevel.LOW: {
                'max_position_pct': 0.02,  # 2%
                'max_leverage': 3.0,
                'max_exposure_pct': 0.08,  # 8%
                'stop_loss_pct': 0.025,  # 2.5%
                'description': 'Conservative trading'
            },
            RiskLevel.LOW_MEDIUM: {
                'max_position_pct': 0.025,  # 2.5%
                'max_leverage': 4.0,
                'max_exposure_pct': 0.10,  # 10%
                'stop_loss_pct': 0.03,  # 3%
                'description': 'Low-medium risk'
            },
            RiskLevel.MEDIUM_LOW: {
                'max_position_pct': 0.03,  # 3%
                'max_leverage': 5.0,
                'max_exposure_pct': 0.12,  # 12%
                'stop_loss_pct': 0.035,  # 3.5%
                'description': 'Medium-low risk'
            },
            RiskLevel.MEDIUM: {
                'max_position_pct': 0.04,  # 4%
                'max_leverage': 6.0,
                'max_exposure_pct': 0.15,  # 15%
                'stop_loss_pct': 0.04,  # 4%
                'description': 'Medium risk'
            },
            RiskLevel.MEDIUM_HIGH: {
                'max_position_pct': 0.05,  # 5%
                'max_leverage': 7.0,
                'max_exposure_pct': 0.18,  # 18%
                'stop_loss_pct': 0.045,  # 4.5%
                'description': 'Medium-high risk'
            },
            RiskLevel.HIGH_MEDIUM: {
                'max_position_pct': 0.06,  # 6%
                'max_leverage': 8.0,
                'max_exposure_pct': 0.20,  # 20%
                'stop_loss_pct': 0.05,  # 5%
                'description': 'High-medium risk'
            },
            RiskLevel.HIGH: {
                'max_position_pct': 0.07,  # 7%
                'max_leverage': 10.0,
                'max_exposure_pct': 0.25,  # 25%
                'stop_loss_pct': 0.06,  # 6%
                'description': 'High risk'
            },
            RiskLevel.VERY_HIGH: {
                'max_position_pct': 0.08,  # 8%
                'max_leverage': 12.0,
                'max_exposure_pct': 0.30,  # 30%
                'stop_loss_pct': 0.08,  # 8%
                'description': 'Very high risk'
            },
            RiskLevel.EXTREME: {
                'max_position_pct': 0.10,  # 10%
                'max_leverage': 15.0,
                'max_exposure_pct': 0.40,  # 40%
                'stop_loss_pct': 0.10,  # 10%
                'description': 'Extreme risk - caution advised'
            },
            RiskLevel.CRITICAL: {
                'max_position_pct': 0.02,  # Reduced to 2%
                'max_leverage': 2.0,  # Reduced leverage
                'max_exposure_pct': 0.10,  # Reduced exposure
                'stop_loss_pct': 0.02,  # Tight stops
                'description': 'Critical risk - emergency protocols'
            },
            RiskLevel.EMERGENCY: {
                'max_position_pct': 0.01,  # Minimal positions
                'max_leverage': 1.0,  # No leverage
                'max_exposure_pct': 0.05,  # Minimal exposure
                'stop_loss_pct': 0.01,  # Very tight stops
                'description': 'Emergency - close all positions'
            }
        }
        
    def get_current_risk_level(self) -> RiskLevel:
        """Calculate current risk level based on comprehensive portfolio metrics."""
        current_drawdown = self.portfolio.drawdown_from_peak
        total_exposure = self.portfolio.total_exposure / self.initial_capital
        daily_loss = self.portfolio.get_daily_loss_percentage()
        
        # Additional factors for enhanced risk assessment
        leverage_exposure_ratio = self.portfolio.total_leverage_exposure / self.max_leverage_exposure if hasattr(self.portfolio, 'max_leverage_exposure') else 0
        correlation_risk = self._calculate_correlation_risk()
        consecutive_losses = self.portfolio.performance_stats.get('current_loss_streak', 0)
        
        # Determine risk level based on comprehensive factors
        risk_score = 1
        
        # Drawdown factor (enhanced for 12-level system)
        if current_drawdown > 0.20:  # 20%+ - Emergency liquidation
            risk_score = max(risk_score, 12)
        elif current_drawdown > 0.18:  # 18-20% - Critical
            risk_score = max(risk_score, 11)
        elif current_drawdown > 0.15:  # 15-18% - Extreme
            risk_score = max(risk_score, 10)
        elif current_drawdown > 0.12:  # 12-15% - Very High
            risk_score = max(risk_score, 9)
        elif current_drawdown > 0.10:  # 10-12% - High
            risk_score = max(risk_score, 8)
        elif current_drawdown > 0.08:  # 8-10% - High Medium
            risk_score = max(risk_score, 7)
        elif current_drawdown > 0.06:  # 6-8% - Medium High
            risk_score = max(risk_score, 6)
        elif current_drawdown > 0.04:  # 4-6% - Medium
            risk_score = max(risk_score, 5)
        elif current_drawdown > 0.02:  # 2-4% - Medium Low
            risk_score = max(risk_score, 4)
        elif current_drawdown > 0.01:  # 1-2% - Low Medium
            risk_score = max(risk_score, 3)
        elif current_drawdown > 0.005:  # 0.5-1% - Low
            risk_score = max(risk_score, 2)
        
        # Exposure factor (adjusted for portfolio limits)
        if total_exposure > 0.50:  # 50%+ - Critical
            risk_score = max(risk_score, 12)
        elif total_exposure > 0.40:  # 40-50% - Extreme
            risk_score = max(risk_score, 10)
        elif total_exposure > 0.30:  # 30-40% - Very High
            risk_score = max(risk_score, 9)
        elif total_exposure > 0.25:  # 25-30% - High
            risk_score = max(risk_score, 8)
        elif total_exposure > 0.20:  # 20-25% - High Medium
            risk_score = max(risk_score, 7)
        elif total_exposure > 0.15:  # 15-20% - Medium High
            risk_score = max(risk_score, 6)
        elif total_exposure > 0.10:  # 10-15% - Medium
            risk_score = max(risk_score, 5)
        
        # Daily loss factor (enhanced)
        if daily_loss > 0.08:  # 8%+ - Emergency
            risk_score = max(risk_score, 12)
        elif daily_loss > 0.06:  # 6-8% - Critical
            risk_score = max(risk_score, 11)
        elif daily_loss > 0.05:  # 5-6% - Extreme (daily limit)
            risk_score = max(risk_score, 10)
        elif daily_loss > 0.04:  # 4-5% - Very High
            risk_score = max(risk_score, 9)
        elif daily_loss > 0.03:  # 3-4% - High
            risk_score = max(risk_score, 8)
        elif daily_loss > 0.02:  # 2-3% - Medium High
            risk_score = max(risk_score, 6)
        
        # Leverage exposure factor
        if leverage_exposure_ratio > 0.90:  # 90%+ of max leverage
            risk_score = max(risk_score, 11)
        elif leverage_exposure_ratio > 0.75:  # 75-90%
            risk_score = max(risk_score, 9)
        elif leverage_exposure_ratio > 0.50:  # 50-75%
            risk_score = max(risk_score, 7)
        
        # Correlation risk factor
        if correlation_risk > 0.80:  # High correlation between positions
            risk_score = max(risk_score, 8)
        elif correlation_risk > 0.60:
            risk_score = max(risk_score, 6)
        
        # Consecutive losses factor
        if consecutive_losses >= 5:
            risk_score = max(risk_score, 10)
        elif consecutive_losses >= 3:
            risk_score = max(risk_score, 8)
        elif consecutive_losses >= 2:
            risk_score = max(risk_score, 6)
        
        return RiskLevel(risk_score)
        
    def _calculate_correlation_risk(self) -> float:
        """Calculate correlation risk between open positions"""
        correlations = self.portfolio.calculate_position_correlations()
        if not correlations:
            return 0.0
            
        # Return the maximum correlation as the risk indicator
        return max(correlations.values()) if correlations else 0.0
        
    async def can_open_position(self, signal: Any, symbol: str = None, size: float = None) -> Tuple[bool, str]:
        """Check if new position can be opened based on comprehensive risk analysis."""
        # Emergency stop check
        if self.emergency_stop:
            return False, "Emergency stop activated - no new positions allowed"
        
        # Portfolio-level checks
        symbol = symbol or getattr(signal, 'symbol', 'UNKNOWN')
        position_size = size or self.portfolio.default_position_size
        
        can_open, reason = self.portfolio.can_open_new_position(symbol, position_size)
        if not can_open:
            return False, reason
            
        # Risk level checks
        current_risk = self.get_current_risk_level()
        if current_risk in [RiskLevel.CRITICAL, RiskLevel.EMERGENCY]:
            return False, f"Risk level {current_risk.name} - no new positions allowed"
            
        # Market volatility check
        if self.dynamic_sizing_enabled:
            volatility = self._get_market_volatility(symbol)
            if volatility > 0.10 and current_risk.value >= 8:  # 10% volatility + high risk
                return False, "High market volatility combined with elevated risk level"
        
        # Correlation check for concentrated risk
        if len(self.portfolio.open_positions) > 0:
            max_correlation = self._calculate_symbol_correlation(symbol)
            if max_correlation > self.correlation_threshold and current_risk.value >= 6:
                return False, f"High correlation with existing positions: {max_correlation:.2f}"
        
        return True, "Position can be opened"
        
    async def calculate_position_size(self, signal: Any, symbol: str = None) -> float:
        """Calculate appropriate position size with dynamic sizing based on volatility and risk."""
        current_risk = self.get_current_risk_level()
        risk_config = self.risk_levels[current_risk]
        symbol = symbol or getattr(signal, 'symbol', 'UNKNOWN')
        
        # Base position size from portfolio configuration
        base_size = self.portfolio.default_position_size
        
        # Adjust for risk level
        risk_multiplier = min(1.0, risk_config['max_position_pct'] / self.max_position_size_pct)
        
        # Adjust for signal strength
        signal_strength = getattr(signal, 'strength', 0.5)
        signal_multiplier = 0.5 + (signal_strength * 0.5)  # Range: 0.5 - 1.0
        
        # Dynamic volatility adjustment
        volatility_multiplier = 1.0
        if self.dynamic_sizing_enabled:
            volatility = self._get_market_volatility(symbol)
            volatility_multiplier = max(0.3, 1.0 - (volatility * 2.0))  # Reduce size for high volatility
        
        # Market regime adjustment
        regime_multiplier = self.regime_adjustment_factor
        
        # Correlation penalty
        correlation_multiplier = 1.0
        if len(self.portfolio.open_positions) > 0:
            max_correlation = self._calculate_symbol_correlation(symbol)
            correlation_multiplier = max(0.5, 1.0 - (max_correlation * 0.3))
        
        # Calculate final position size
        position_size = (base_size * 
                        risk_multiplier * 
                        signal_multiplier * 
                        volatility_multiplier * 
                        regime_multiplier * 
                        correlation_multiplier)
        
        # Apply portfolio limits
        max_allowed = min(
            self.portfolio.current_capital * self.max_position_size_pct,
            self.portfolio.max_leverage_exposure - self.portfolio.total_leverage_exposure
        )
        position_size = min(position_size, max_allowed)
        
        # Ensure minimum viable position size
        position_size = max(position_size, 50.0)  # Minimum 50 USDT
        
        self.logger.info(
            f"Calculated position size for {symbol}: {position_size:.2f} USDT "
            f"(Risk: {current_risk.name}, Signal: {signal_strength:.2f}, "
            f"Vol: {volatility_multiplier:.2f}, Corr: {correlation_multiplier:.2f})"
        )
        
        return position_size
        
    def get_position_risk_assessment(self, position: Position) -> PositionRisk:
        """Assess comprehensive risk for individual position."""
        # Use position's built-in risk assessment as base
        position_risk = position.get_risk_assessment()
        
        # Enhance with additional risk factors
        current_volatility = self._get_market_volatility(position.symbol)
        market_correlation = self._calculate_symbol_correlation(position.symbol)
        
        # Adjust risk level based on market conditions
        enhanced_risk_level = position_risk.risk_level
        
        # Upgrade risk level for high volatility
        if current_volatility > 0.08:  # 8% daily volatility
            enhanced_risk_level = RiskLevel(min(12, enhanced_risk_level.value + 2))
        elif current_volatility > 0.05:  # 5% daily volatility
            enhanced_risk_level = RiskLevel(min(12, enhanced_risk_level.value + 1))
        
        # Upgrade risk level for high correlation
        if market_correlation > 0.80:
            enhanced_risk_level = RiskLevel(min(12, enhanced_risk_level.value + 2))
        elif market_correlation > 0.60:
            enhanced_risk_level = RiskLevel(min(12, enhanced_risk_level.value + 1))
        
        return PositionRisk(
            symbol=position.symbol,
            exposure_pct=position_risk.exposure_pct,
            leverage=position.leverage,
            liquidation_price=position.liquidation_price,
            distance_to_liquidation=position.distance_to_liquidation,
            volatility=current_volatility,
            risk_level=enhanced_risk_level
        )
        
    def get_portfolio_risk_metrics(self) -> RiskMetrics:
        """Calculate comprehensive portfolio risk metrics with real-time computation."""
        # Use portfolio metrics as base
        portfolio_summary = self.portfolio.get_portfolio_summary()
        
        # Calculate advanced risk metrics
        var_95 = self.calculate_var_95()
        sharpe_ratio = self.calculate_sharpe_ratio()
        win_rate, avg_win, avg_loss = self.calculate_win_loss_metrics()
        
        # Calculate maximum historical drawdown
        max_drawdown = self._calculate_max_historical_drawdown()
        
        risk_score = self.get_current_risk_level().value
        
        return RiskMetrics(
            total_exposure=portfolio_summary['total_exposure'] / self.initial_capital,
            max_drawdown=max_drawdown,
            current_drawdown=portfolio_summary['drawdown_from_peak'],
            var_95=var_95,
            sharpe_ratio=sharpe_ratio,
            win_rate=win_rate,
            avg_win=avg_win,
            avg_loss=avg_loss,
            risk_score=risk_score,
            timestamp=datetime.now()
        )
        
    # Enhanced Risk Calculation Methods
    
    def _get_market_volatility(self, symbol: str) -> float:
        """Get or estimate market volatility for symbol."""
        # Check cache first
        if symbol in self.market_volatility_cache:
            return self.market_volatility_cache[symbol]
        
        # Calculate from recent price data if available
        if symbol in self.price_update_buffer:
            prices = [price for _, price in self.price_update_buffer[symbol][-20:]]  # Last 20 updates
            if len(prices) >= 2:
                returns = [(prices[i] / prices[i-1] - 1) for i in range(1, len(prices))]
                volatility = np.std(returns) * np.sqrt(24)  # Daily volatility (assuming hourly updates)
                self.market_volatility_cache[symbol] = volatility
                return volatility
        
        # Default volatility estimates by asset type
        if 'BTC' in symbol.upper():
            return 0.04  # 4% daily volatility for Bitcoin
        elif 'ETH' in symbol.upper():
            return 0.05  # 5% daily volatility for Ethereum
        elif 'USDT' in symbol.upper() or 'USDC' in symbol.upper():
            return 0.001  # Very low volatility for stablecoins
        else:
            return 0.06  # 6% default for altcoins
    
    def _calculate_symbol_correlation(self, symbol: str) -> float:
        """Calculate maximum correlation with existing positions."""
        if not self.portfolio.open_positions:
            return 0.0
        
        max_correlation = 0.0
        for position in self.portfolio.open_positions:
            if position.symbol != symbol:
                # Simplified correlation calculation
                correlation = self._estimate_correlation(symbol, position.symbol)
                max_correlation = max(max_correlation, correlation)
        
        return max_correlation
    
    def _estimate_correlation(self, symbol1: str, symbol2: str) -> float:
        """Estimate correlation between two symbols."""
        # Simplified correlation based on asset types
        if symbol1 == symbol2:
            return 1.0
        
        # High correlation for same base asset
        base1 = symbol1.split('USDT')[0] if 'USDT' in symbol1 else symbol1.split('/')[0]
        base2 = symbol2.split('USDT')[0] if 'USDT' in symbol2 else symbol2.split('/')[0]
        
        if base1 == base2:
            return 0.95
        
        # Medium correlation for major cryptos
        majors = ['BTC', 'ETH', 'BNB', 'ADA', 'SOL']
        if base1 in majors and base2 in majors:
            return 0.7
        
        # Low correlation otherwise
        return 0.3
    
    def _calculate_max_historical_drawdown(self) -> float:
        """Calculate maximum historical drawdown from trade history."""
        if not self.portfolio.trade_history:
            return 0.0
        
        # Calculate running balance from trade history
        balance = self.initial_capital
        peak = balance
        max_dd = 0.0
        
        for trade in sorted(self.portfolio.trade_history, key=lambda x: x['timestamp']):
            balance += trade['pnl']
            peak = max(peak, balance)
            current_dd = (peak - balance) / peak if peak > 0 else 0.0
            max_dd = max(max_dd, current_dd)
        
        return max_dd
        
    def calculate_var_95(self) -> float:
        """Calculate Value at Risk at 95% confidence level using historical returns."""
        if len(self.portfolio.trade_history) < 10:
            return 0.0
        
        # Calculate daily returns from trade history
        daily_returns = []
        
        # Group trades by day and calculate daily PnL
        daily_pnl = {}
        for trade in self.portfolio.trade_history:
            trade_date = trade['timestamp'].strftime('%Y-%m-%d') if hasattr(trade['timestamp'], 'strftime') else str(trade['timestamp'])[:10]
            if trade_date not in daily_pnl:
                daily_pnl[trade_date] = 0.0
            daily_pnl[trade_date] += trade['pnl']
        
        # Convert to returns
        for pnl in daily_pnl.values():
            daily_return = pnl / self.initial_capital
            daily_returns.append(daily_return)
        
        if len(daily_returns) < 5:
            return 0.0
        
        # Calculate 95% VaR (5th percentile of returns)
        sorted_returns = sorted(daily_returns)
        var_index = max(0, int(len(sorted_returns) * 0.05) - 1)
        var_95 = abs(sorted_returns[var_index])
        
        return var_95
        
    def calculate_sharpe_ratio(self) -> float:
        """Calculate Sharpe ratio using portfolio returns."""
        if len(self.portfolio.trade_history) < 10:
            return 0.0
        
        # Calculate daily returns
        daily_pnl = {}
        for trade in self.portfolio.trade_history:
            trade_date = trade['timestamp'].strftime('%Y-%m-%d') if hasattr(trade['timestamp'], 'strftime') else str(trade['timestamp'])[:10]
            if trade_date not in daily_pnl:
                daily_pnl[trade_date] = 0.0
            daily_pnl[trade_date] += trade['pnl']
        
        daily_returns = [pnl / self.initial_capital for pnl in daily_pnl.values()]
        
        if len(daily_returns) < 5:
            return 0.0
        
        # Calculate Sharpe ratio
        mean_return = np.mean(daily_returns)
        std_return = np.std(daily_returns)
        
        if std_return == 0:
            return 0.0
        
        # Assuming risk-free rate of 0 for simplicity
        sharpe_ratio = mean_return / std_return * np.sqrt(252)  # Annualized
        
        return sharpe_ratio
        
    def calculate_win_loss_metrics(self) -> Tuple[float, float, float]:
        """Calculate win rate, average win, and average loss from trade history."""
        if not self.portfolio.trade_history:
            return 0.0, 0.0, 0.0
        
        wins = [trade['pnl'] for trade in self.portfolio.trade_history if trade['pnl'] > 0]
        losses = [trade['pnl'] for trade in self.portfolio.trade_history if trade['pnl'] < 0]
        
        total_trades = len(self.portfolio.trade_history)
        if total_trades == 0:
            return 0.0, 0.0, 0.0
        
        win_rate = len(wins) / total_trades
        avg_win = np.mean(wins) if wins else 0.0
        avg_loss = np.mean(losses) if losses else 0.0
        
        return win_rate, avg_win, avg_loss
    
    # Portfolio Management Methods (New)
    
    def open_position(self, symbol: str, side: str, entry_price: float, 
                     size: float = None, leverage: float = 1.0, 
                     signal_metadata: Dict[str, Any] = None) -> Optional[Position]:
        """
        Open new position with integrated risk management
        새로운 포지션 개설 및 통합 리스크 관리
        
        Args:
            symbol: Trading symbol
            side: 'long' or 'short'
            entry_price: Entry price
            size: Position size in USDT (optional, uses calculated size)
            leverage: Leverage multiplier
            signal_metadata: Additional signal information
            
        Returns:
            Position object if successful, None otherwise
        """
        try:
            # Convert side to enum
            position_side = PositionSide.LONG if side.lower() == 'long' else PositionSide.SHORT
            
            # Open position through portfolio
            position = self.portfolio.open_position(
                symbol=symbol,
                side=position_side,
                entry_price=entry_price,
                size=size,
                leverage=leverage,
                metadata=signal_metadata
            )
            
            if position:
                # Update capital tracking
                self.current_capital = self.portfolio.current_capital
                self.max_capital = max(self.max_capital, self.portfolio.portfolio_value)
                
                # Log risk assessment
                risk_assessment = self.get_position_risk_assessment(position)
                self.logger.info(
                    f"Position opened with risk level: {risk_assessment.risk_level.name} "
                    f"(Exposure: {risk_assessment.exposure_pct:.2%}, "
                    f"Volatility: {risk_assessment.volatility:.2%})"
                )
                
                # Check if emergency conditions are triggered
                self.check_emergency_conditions()
            
            return position
            
        except Exception as e:
            self.logger.error(f"Failed to open position for {symbol}: {str(e)}")
            return None
    
    def close_position(self, position_id: str, close_price: float, reason: str = "Manual close") -> bool:
        """Close position with profit/loss level execution tracking"""
        success = self.portfolio.close_position(position_id, close_price, reason)
        
        if success:
            # Update capital tracking
            self.current_capital = self.portfolio.current_capital
            self.max_capital = max(self.max_capital, self.portfolio.portfolio_value)
            
            # Check for daily reset logic (profit after loss)
            self.portfolio.reset_daily_tracking()
            
            # Check emergency conditions
            self.check_emergency_conditions()
        
        return success
    
    def update_market_prices(self, price_updates: Dict[str, float]) -> None:
        """
        Update market prices and execute triggered profit/loss levels
        시장 가격 업데이트 및 손익 레벨 실행
        
        Args:
            price_updates: Dict of symbol -> current_price
        """
        # Update price buffer for volatility calculation
        current_time = datetime.now()
        for symbol, price in price_updates.items():
            self.price_update_buffer[symbol].append((current_time, price))
            # Keep only last 100 price updates per symbol
            if len(self.price_update_buffer[symbol]) > 100:
                self.price_update_buffer[symbol] = self.price_update_buffer[symbol][-100:]
        
        # Update portfolio positions and get triggered levels
        triggered_positions = self.portfolio.update_position_prices(price_updates)
        
        # Execute triggered levels
        if triggered_positions:
            self.portfolio.execute_triggered_levels(triggered_positions)
            
            # Update capital tracking
            self.current_capital = self.portfolio.current_capital
            self.max_capital = max(self.max_capital, self.portfolio.portfolio_value)
        
        # Clear volatility cache periodically
        if len(self.price_update_buffer) > 0 and current_time.minute % 15 == 0:
            self.market_volatility_cache.clear()
        
        # Perform real-time risk assessment
        if (current_time - self.last_risk_check).total_seconds() >= self.risk_check_interval:
            self._perform_real_time_risk_assessment()
            self.last_risk_check = current_time
    
    def _perform_real_time_risk_assessment(self) -> None:
        """Perform comprehensive real-time risk assessment"""
        current_risk = self.get_current_risk_level()
        portfolio_summary = self.portfolio.get_portfolio_summary()
        
        # Log risk status
        self.logger.debug(
            f"Risk Assessment - Level: {current_risk.name} "
            f"({current_risk.value}/12), "
            f"Drawdown: {portfolio_summary['drawdown_from_peak']:.2%}, "
            f"Daily Loss: {portfolio_summary['daily_loss_percentage']:.2%}, "
            f"Positions: {portfolio_summary['open_positions_count']}/{self.portfolio.max_positions}"
        )
        
        # Check for emergency liquidation conditions
        if current_risk in [RiskLevel.CRITICAL, RiskLevel.EMERGENCY]:
            if not self.emergency_liquidation_active:
                self._initiate_emergency_procedures(current_risk)
        
        # Update market regime based on portfolio performance
        self._update_market_regime()
    
    def _initiate_emergency_procedures(self, risk_level: RiskLevel) -> None:
        """Initiate emergency risk procedures"""
        self.emergency_liquidation_active = True
        
        if risk_level == RiskLevel.EMERGENCY:
            # Emergency: Close all positions immediately
            self.logger.critical("EMERGENCY LIQUIDATION: Closing all positions immediately")
            
            for position in self.portfolio.open_positions.copy():
                self.close_position(
                    position.position_id, 
                    position.current_price, 
                    f"Emergency liquidation - Risk Level {risk_level.name}"
                )
            
            self.activate_emergency_stop("Emergency risk level reached - all positions closed")
            
        elif risk_level == RiskLevel.CRITICAL:
            # Critical: Reduce positions to minimum
            self.logger.warning("CRITICAL RISK: Reducing positions to minimum safe levels")
            
            # Close highest risk positions first
            positions_by_risk = sorted(
                self.portfolio.open_positions,
                key=lambda p: self.get_position_risk_assessment(p).risk_level.value,
                reverse=True
            )
            
            # Close positions until we're under critical threshold
            positions_to_close = len(positions_by_risk) - 1  # Keep only 1 position
            for position in positions_by_risk[:positions_to_close]:
                self.close_position(
                    position.position_id,
                    position.current_price,
                    f"Critical risk reduction - Risk Level {risk_level.name}"
                )
    
    def _update_market_regime(self) -> None:
        """Update market regime assessment based on recent performance"""
        if len(self.portfolio.trade_history) < 10:
            return
        
        # Analyze recent trades for regime detection
        recent_trades = self.portfolio.trade_history[-10:]
        recent_returns = [trade['pnl_percentage'] for trade in recent_trades]
        
        volatility = np.std(recent_returns) if recent_returns else 0.0
        avg_return = np.mean(recent_returns) if recent_returns else 0.0
        
        # Determine market regime
        if volatility > 0.05:  # High volatility
            self.market_regime = "volatile"
            self.regime_adjustment_factor = 0.7  # Reduce position sizes
        elif avg_return > 0.02:  # Strong positive trend
            self.market_regime = "trending"
            self.regime_adjustment_factor = 1.1  # Slightly increase position sizes
        elif abs(avg_return) < 0.005:  # Low volatility, sideways
            self.market_regime = "ranging"
            self.regime_adjustment_factor = 0.9  # Slightly reduce position sizes
        else:
            self.market_regime = "normal"
            self.regime_adjustment_factor = 1.0
        
        # Log regime changes
        if hasattr(self, '_last_regime') and self._last_regime != self.market_regime:
            self.logger.info(f"Market regime changed: {self._last_regime} → {self.market_regime}")
        self._last_regime = self.market_regime
    
    # Real-time Monitoring Methods
    
    def get_real_time_risk_summary(self) -> Dict[str, Any]:
        """Get comprehensive real-time risk summary"""
        current_risk = self.get_current_risk_level()
        portfolio_summary = self.portfolio.get_portfolio_summary()
        risk_metrics = self.get_portfolio_risk_metrics()
        
        # Calculate additional metrics
        open_positions = self.portfolio.open_positions
        position_risks = [self.get_position_risk_assessment(pos) for pos in open_positions]
        
        avg_position_risk = np.mean([pr.risk_level.value for pr in position_risks]) if position_risks else 0
        max_position_risk = max([pr.risk_level.value for pr in position_risks]) if position_risks else 0
        
        return {
            # Overall risk
            'overall_risk_level': current_risk.name,
            'overall_risk_score': current_risk.value,
            'risk_description': self.risk_levels[current_risk]['description'],
            
            # Portfolio metrics
            'portfolio_value': portfolio_summary['portfolio_value'],
            'total_pnl': portfolio_summary['total_pnl'],
            'total_pnl_percentage': portfolio_summary['total_pnl'] / self.initial_capital * 100,
            'unrealized_pnl': portfolio_summary['unrealized_pnl'],
            'realized_pnl': portfolio_summary['realized_pnl'],
            
            # Risk metrics
            'current_drawdown': portfolio_summary['drawdown_from_peak'] * 100,
            'max_drawdown': risk_metrics.max_drawdown * 100,
            'daily_pnl': portfolio_summary['daily_pnl'],
            'daily_loss_percentage': portfolio_summary['daily_loss_percentage'] * 100,
            
            # Position metrics
            'open_positions_count': len(open_positions),
            'max_positions': self.portfolio.max_positions,
            'total_exposure': portfolio_summary['total_exposure'],
            'leverage_exposure': portfolio_summary['leverage_exposure'],
            'avg_position_risk': avg_position_risk,
            'max_position_risk': max_position_risk,
            
            # Advanced metrics
            'sharpe_ratio': risk_metrics.sharpe_ratio,
            'var_95': risk_metrics.var_95 * 100,
            'win_rate': risk_metrics.win_rate * 100,
            'avg_win': risk_metrics.avg_win,
            'avg_loss': risk_metrics.avg_loss,
            
            # Market conditions
            'market_regime': self.market_regime,
            'regime_adjustment_factor': self.regime_adjustment_factor,
            
            # Emergency status
            'emergency_stop': self.emergency_stop,
            'emergency_liquidation_active': self.emergency_liquidation_active,
            
            # Position details
            'positions': [
                {
                    'id': pos.position_id,
                    'symbol': pos.symbol,
                    'side': pos.side.value,
                    'size': pos.remaining_size,
                    'entry_price': pos.entry_price,
                    'current_price': pos.current_price,
                    'pnl': pos.total_pnl,
                    'pnl_percentage': pos.pnl_percentage * 100,
                    'risk_level': self.get_position_risk_assessment(pos).risk_level.name,
                    'executed_profit_levels': sum(1 for level in pos.profit_levels if level.executed),
                    'executed_loss_levels': sum(1 for level in pos.loss_levels if level.executed)
                }
                for pos in open_positions
            ],
            
            'timestamp': datetime.now().isoformat()
        }
    
    def get_position_details(self, position_id: str) -> Optional[Dict[str, Any]]:
        """Get detailed information for specific position"""
        if position_id not in self.portfolio.positions:
            return None
        
        position = self.portfolio.positions[position_id]
        risk_assessment = self.get_position_risk_assessment(position)
        
        return {
            **position.to_dict(),
            'risk_assessment': {
                'risk_level': risk_assessment.risk_level.name,
                'risk_score': risk_assessment.risk_level.value,
                'exposure_percentage': risk_assessment.exposure_pct * 100,
                'volatility': risk_assessment.volatility * 100,
                'distance_to_liquidation': risk_assessment.distance_to_liquidation * 100,
                'liquidation_price': risk_assessment.liquidation_price
            }
        }
    
    # Emergency Controls (Enhanced)
    
    def activate_emergency_stop(self, reason: str) -> None:
        """Activate emergency stop."""
        self.emergency_stop = True
        self.risk_events.append({
            'type': 'emergency_stop',
            'reason': reason,
            'timestamp': datetime.now()
        })
        self.logger.critical(f"EMERGENCY STOP ACTIVATED: {reason}")
        
    def deactivate_emergency_stop(self) -> None:
        """Deactivate emergency stop (manual override)."""
        self.emergency_stop = False
        self.risk_events.append({
            'type': 'emergency_stop_deactivated',
            'timestamp': datetime.now()
        })
        self.logger.warning("Emergency stop deactivated")
        
    def check_emergency_conditions(self) -> None:
        """Check for emergency conditions and activate stop if necessary."""
        current_drawdown = self.get_current_drawdown()
        daily_loss = self.get_daily_loss()
        
        # Check drawdown emergency
        if current_drawdown >= 0.20:  # 20% drawdown
            self.activate_emergency_stop(f"Maximum drawdown exceeded: {current_drawdown:.2%}")
            return
            
        # Check daily loss emergency
        if daily_loss >= 0.10:  # 10% daily loss
            self.activate_emergency_stop(f"Maximum daily loss exceeded: {daily_loss:.2%}")
            return
            
    # Portfolio Updates
    
    def update_capital(self, new_capital: float) -> None:
        """Update current capital and track maximum."""
        self.current_capital = new_capital
        self.max_capital = max(self.max_capital, new_capital)
        
        # Update daily PnL
        today = datetime.now().strftime('%Y-%m-%d')
        if today not in self.daily_pnl:
            self.daily_pnl[today] = 0.0
            
        # Check emergency conditions after capital update
        self.check_emergency_conditions()
        
    def record_trade_pnl(self, pnl: float, symbol: str) -> None:
        """Record trade PnL for risk tracking."""
        today = datetime.now().strftime('%Y-%m-%d')
        if today not in self.daily_pnl:
            self.daily_pnl[today] = 0.0
            
        self.daily_pnl[today] += pnl
        self.update_capital(self.current_capital + pnl)
        
        # Log trade
        self.position_history.append({
            'symbol': symbol,
            'pnl': pnl,
            'timestamp': datetime.now(),
            'daily_total': self.daily_pnl[today]
        })
        
    # Public Interface
    
    def get_risk_status(self) -> Dict[str, Any]:
        """Get comprehensive risk status."""
        current_risk = self.get_current_risk_level()
        risk_config = self.risk_levels[current_risk]
        
        return {
            'risk_level': current_risk.name,
            'risk_score': current_risk.value,
            'description': risk_config['description'],
            'emergency_stop': self.emergency_stop,
            'current_drawdown': self.get_current_drawdown(),
            'daily_loss': self.get_daily_loss(),
            'total_exposure': self.get_total_exposure(),
            'max_position_size_pct': risk_config['max_position_pct'],
            'max_leverage': risk_config['max_leverage'],
            'current_capital': self.current_capital,
            'max_capital': self.max_capital
        }
        
    def get_risk_limits(self) -> Dict[str, float]:
        """Get current risk limits based on risk level."""
        current_risk = self.get_current_risk_level()
        return self.risk_levels[current_risk].copy()


class Portfolio:
    """
    Portfolio management with comprehensive risk control
    포트폴리오 관리 및 종합 리스크 컨트롤
    
    Key Features:
    - Default position size: 1000 USDT
    - Maximum positions: 3
    - Maximum leverage exposure: 10,000 USDT (10x)
    - Daily loss limit: -5.0%
    - Correlation analysis between positions
    """
    
    def __init__(self, 
                 logger: SystemLogger,
                 initial_capital: float = 10000.0,
                 default_position_size: float = 1000.0,
                 max_positions: int = 3,
                 max_leverage_exposure: float = 10000.0):
        """
        Initialize portfolio
        
        Args:
            logger: System logger instance
            initial_capital: Initial capital in USDT
            default_position_size: Default position size in USDT
            max_positions: Maximum number of concurrent positions
            max_leverage_exposure: Maximum leverage exposure in USDT
        """
        self.logger = logger
        self.initial_capital = initial_capital
        self.current_capital = initial_capital
        self.default_position_size = default_position_size
        self.max_positions = max_positions
        self.max_leverage_exposure = max_leverage_exposure
        
        # Position management
        self.positions: Dict[str, Position] = {}  # position_id -> Position
        self.symbol_positions: Dict[str, List[str]] = defaultdict(list)  # symbol -> [position_ids]
        
        # Daily tracking
        self.daily_pnl: Dict[str, float] = {}  # date -> daily_pnl
        self.daily_trades: Dict[str, int] = defaultdict(int)  # date -> trade_count
        self.max_daily_loss_pct = 0.05  # 5% daily loss limit
        
        # Risk tracking
        self.max_portfolio_value = initial_capital
        self.trade_history: List[Dict[str, Any]] = []
        self.correlation_matrix: Dict[Tuple[str, str], float] = {}
        
        # Performance tracking
        self.performance_stats = {
            'total_trades': 0,
            'winning_trades': 0,
            'losing_trades': 0,
            'avg_win': 0.0,
            'avg_loss': 0.0,
            'max_consecutive_wins': 0,
            'max_consecutive_losses': 0,
            'current_win_streak': 0,
            'current_loss_streak': 0,
            'largest_win': 0.0,
            'largest_loss': 0.0
        }
        
        # Thread safety
        self._lock = threading.RLock()
    
    @property
    def open_positions(self) -> List[Position]:
        """Get all open positions"""
        return [pos for pos in self.positions.values() if pos.status == PositionStatus.OPEN]
    
    @property
    def total_exposure(self) -> float:
        """Calculate total portfolio exposure in USDT"""
        return sum(pos.remaining_size for pos in self.open_positions)
    
    @property
    def total_leverage_exposure(self) -> float:
        """Calculate total leverage exposure"""
        return sum(pos.remaining_size * pos.leverage for pos in self.open_positions)
    
    @property
    def portfolio_pnl(self) -> float:
        """Calculate total portfolio PnL"""
        return sum(pos.total_pnl for pos in self.positions.values())
    
    @property
    def unrealized_pnl(self) -> float:
        """Calculate total unrealized PnL"""
        return sum(pos.unrealized_pnl for pos in self.open_positions)
    
    @property
    def realized_pnl(self) -> float:
        """Calculate total realized PnL"""
        return sum(pos.realized_pnl for pos in self.positions.values())
    
    @property
    def portfolio_value(self) -> float:
        """Calculate current portfolio value"""
        return self.current_capital + self.unrealized_pnl
    
    @property
    def drawdown_from_peak(self) -> float:
        """Calculate current drawdown from peak portfolio value"""
        if self.max_portfolio_value == 0:
            return 0.0
        return (self.max_portfolio_value - self.portfolio_value) / self.max_portfolio_value
    
    def can_open_new_position(self, symbol: str, size: float = None) -> Tuple[bool, str]:
        """
        Check if new position can be opened
        
        Returns:
            (can_open: bool, reason: str)
        """
        with self._lock:
            # Check maximum positions limit
            if len(self.open_positions) >= self.max_positions:
                return False, f"Maximum positions limit reached: {self.max_positions}"
            
            # Check daily loss limit
            daily_loss_pct = self.get_daily_loss_percentage()
            if daily_loss_pct >= self.max_daily_loss_pct:
                return False, f"Daily loss limit exceeded: {daily_loss_pct:.2%}"
            
            # Check leverage exposure limit
            position_size = size or self.default_position_size
            if self.total_leverage_exposure + position_size > self.max_leverage_exposure:
                return False, f"Maximum leverage exposure limit exceeded: {self.max_leverage_exposure} USDT"
            
            # Check available capital
            if position_size > self.current_capital * 0.5:  # Don't use more than 50% of capital per position
                return False, f"Position size too large: {position_size} USDT (max 50% of capital)"
                
            return True, "Position can be opened"
    
    def open_position(self, 
                     symbol: str, 
                     side: PositionSide, 
                     entry_price: float,
                     size: float = None,
                     leverage: float = 1.0,
                     metadata: Dict[str, Any] = None) -> Optional[Position]:
        """
        Open new position
        
        Args:
            symbol: Trading symbol
            side: Position side (LONG/SHORT)
            entry_price: Entry price
            size: Position size in USDT (default: self.default_position_size)
            leverage: Leverage multiplier
            metadata: Additional position metadata
            
        Returns:
            Position object if successful, None otherwise
        """
        with self._lock:
            # Validate position opening
            can_open, reason = self.can_open_new_position(symbol, size)
            if not can_open:
                self.logger.warning(f"Cannot open position for {symbol}: {reason}")
                return None
            
            # Use default size if not specified
            position_size = size or self.default_position_size
            
            # Generate unique position ID
            position_id = f"{symbol}_{side.value}_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{len(self.positions)}"
            
            # Create position
            position = Position(
                position_id=position_id,
                symbol=symbol,
                side=side,
                size=position_size,
                entry_price=entry_price,
                current_price=entry_price,
                leverage=leverage,
                metadata=metadata or {}
            )
            
            # Add to portfolio
            self.positions[position_id] = position
            self.symbol_positions[symbol].append(position_id)
            
            # Log position opening
            self.logger.info(
                f"Opened {side.value} position for {symbol}: "
                f"Size={position_size:.2f} USDT, Entry={entry_price:.6f}, "
                f"Leverage={leverage}x, ID={position_id}"
            )
            
            return position
    
    def close_position(self, position_id: str, close_price: float, reason: str = "Manual close") -> bool:
        """
        Close position completely
        
        Args:
            position_id: Position ID to close
            close_price: Closing price
            reason: Reason for closing
            
        Returns:
            True if successful, False otherwise
        """
        with self._lock:
            if position_id not in self.positions:
                self.logger.warning(f"Position {position_id} not found")
                return False
            
            position = self.positions[position_id]
            
            if position.status != PositionStatus.OPEN:
                self.logger.warning(f"Position {position_id} is not open (status: {position.status.value})")
                return False
            
            # Close the position
            old_pnl = position.realized_pnl
            position.close_position(close_price)
            pnl_change = position.realized_pnl - old_pnl
            
            # Update portfolio capital
            self.current_capital += pnl_change
            self.max_portfolio_value = max(self.max_portfolio_value, self.portfolio_value)
            
            # Update daily PnL
            today = datetime.now().strftime('%Y-%m-%d')
            if today not in self.daily_pnl:
                self.daily_pnl[today] = 0.0
            self.daily_pnl[today] += pnl_change
            
            # Update performance statistics
            self._update_performance_stats(position)
            
            # Record trade history
            self.trade_history.append({
                'position_id': position_id,
                'symbol': position.symbol,
                'side': position.side.value,
                'entry_price': position.entry_price,
                'close_price': close_price,
                'size': position.size,
                'pnl': position.realized_pnl,
                'pnl_percentage': position.pnl_percentage,
                'duration': (position.close_time - position.open_time).total_seconds() / 60,  # minutes
                'reason': reason,
                'timestamp': position.close_time
            })
            
            # Remove from symbol tracking
            if position_id in self.symbol_positions[position.symbol]:
                self.symbol_positions[position.symbol].remove(position_id)
            
            self.logger.info(
                f"Closed position {position_id}: PnL={pnl_change:.2f} USDT "
                f"({position.pnl_percentage:.2%}), Reason: {reason}"
            )
            
            return True
    
    def update_position_prices(self, price_updates: Dict[str, float]) -> List[Tuple[Position, List[ProfitLossLevel]]]:
        """
        Update prices for all positions and check for triggered levels
        
        Args:
            price_updates: Dict of symbol -> current_price
            
        Returns:
            List of (Position, triggered_levels) tuples
        """
        triggered_positions = []
        
        with self._lock:
            for position in self.open_positions:
                if position.symbol in price_updates:
                    new_price = price_updates[position.symbol]
                    triggered_levels = position.update_price(new_price)
                    
                    if triggered_levels:
                        triggered_positions.append((position, triggered_levels))
                        
                        self.logger.debug(
                            f"Position {position.position_id} triggered {len(triggered_levels)} levels "
                            f"at price {new_price:.6f}"
                        )
            
            # Update max portfolio value
            self.max_portfolio_value = max(self.max_portfolio_value, self.portfolio_value)
        
        return triggered_positions
    
    def execute_triggered_levels(self, triggered_positions: List[Tuple[Position, List[ProfitLossLevel]]]) -> None:
        """
        Execute triggered profit/loss levels
        
        Args:
            triggered_positions: List of positions with triggered levels
        """
        with self._lock:
            for position, levels in triggered_positions:
                for level in levels:
                    closed_amount = position.execute_level(level, position.current_price)
                    
                    if closed_amount > 0:
                        level_type = "Profit" if level.is_profit else "Loss"
                        self.logger.info(
                            f"Executed {level_type} Level {level.level} for {position.symbol}: "
                            f"Closed {closed_amount:.2f} USDT at {level.percentage:.1%}"
                        )
                        
                        # Update daily PnL for partial closes
                        today = datetime.now().strftime('%Y-%m-%d')
                        if today not in self.daily_pnl:
                            self.daily_pnl[today] = 0.0
                        
                        price_diff = position.current_price - position.entry_price
                        if position.side == PositionSide.SHORT:
                            price_diff = -price_diff
                        partial_pnl = (price_diff / position.entry_price) * closed_amount
                        self.daily_pnl[today] += partial_pnl
                        self.current_capital += partial_pnl
    
    def get_daily_loss_percentage(self) -> float:
        """Get current daily loss percentage"""
        today = datetime.now().strftime('%Y-%m-%d')
        daily_pnl = self.daily_pnl.get(today, 0.0)
        
        if daily_pnl >= 0:
            return 0.0
        return abs(daily_pnl) / self.initial_capital
    
    def reset_daily_tracking(self) -> None:
        """
        Reset daily tracking (called after profit following losses)
        손실 후 이익 시 시스템 초기화
        """
        today = datetime.now().strftime('%Y-%m-%d')
        
        # Check if we made profit today after previous losses
        daily_pnl = self.daily_pnl.get(today, 0.0)
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        yesterday_pnl = self.daily_pnl.get(yesterday, 0.0)
        
        if daily_pnl > 0 and yesterday_pnl < 0:
            self.logger.info("Profit after loss detected - resetting daily risk tracking")
            # Reset consecutive loss tracking but keep actual PnL records
            self.performance_stats['current_loss_streak'] = 0
            self.performance_stats['current_win_streak'] += 1
    
    def _update_performance_stats(self, closed_position: Position) -> None:
        """Update performance statistics after position close"""
        pnl = closed_position.realized_pnl
        
        self.performance_stats['total_trades'] += 1
        
        if pnl > 0:
            self.performance_stats['winning_trades'] += 1
            self.performance_stats['current_win_streak'] += 1
            self.performance_stats['current_loss_streak'] = 0
            self.performance_stats['max_consecutive_wins'] = max(
                self.performance_stats['max_consecutive_wins'],
                self.performance_stats['current_win_streak']
            )
            self.performance_stats['largest_win'] = max(self.performance_stats['largest_win'], pnl)
            
            # Update average win
            total_wins = self.performance_stats['winning_trades']
            self.performance_stats['avg_win'] = (
                (self.performance_stats['avg_win'] * (total_wins - 1) + pnl) / total_wins
            )
        else:
            self.performance_stats['losing_trades'] += 1
            self.performance_stats['current_loss_streak'] += 1
            self.performance_stats['current_win_streak'] = 0
            self.performance_stats['max_consecutive_losses'] = max(
                self.performance_stats['max_consecutive_losses'],
                self.performance_stats['current_loss_streak']
            )
            self.performance_stats['largest_loss'] = min(self.performance_stats['largest_loss'], pnl)
            
            # Update average loss
            total_losses = self.performance_stats['losing_trades']
            if total_losses > 0:
                self.performance_stats['avg_loss'] = (
                    (self.performance_stats['avg_loss'] * (total_losses - 1) + pnl) / total_losses
                )
    
    def calculate_position_correlations(self) -> Dict[Tuple[str, str], float]:
        """
        Calculate correlations between open positions
        
        Returns:
            Dictionary of (symbol1, symbol2) -> correlation coefficient
        """
        open_symbols = list(set(pos.symbol for pos in self.open_positions))
        correlations = {}
        
        for i, symbol1 in enumerate(open_symbols):
            for j, symbol2 in enumerate(open_symbols[i+1:], i+1):
                # Simplified correlation calculation (would need actual price history)
                # For now, assign high correlation to similar assets
                if symbol1.startswith('BTC') and symbol2.startswith('BTC'):
                    correlation = 0.8
                elif symbol1.startswith('ETH') and symbol2.startswith('ETH'):
                    correlation = 0.7
                elif 'USDT' in symbol1 and 'USDT' in symbol2:
                    correlation = 0.3
                else:
                    correlation = 0.1
                    
                correlations[(symbol1, symbol2)] = correlation
        
        self.correlation_matrix = correlations
        return correlations
    
    def get_risk_concentration(self) -> Dict[str, float]:
        """
        Calculate risk concentration by asset/symbol
        
        Returns:
            Dictionary of symbol -> concentration percentage
        """
        if not self.open_positions:
            return {}
        
        symbol_exposure = defaultdict(float)
        for position in self.open_positions:
            symbol_exposure[position.symbol] += position.remaining_size
        
        total_exposure = sum(symbol_exposure.values())
        if total_exposure == 0:
            return {}
        
        return {symbol: exposure / total_exposure for symbol, exposure in symbol_exposure.items()}
    
    def get_portfolio_summary(self) -> Dict[str, Any]:
        """Get comprehensive portfolio summary"""
        open_pos = self.open_positions
        
        return {
            'portfolio_value': self.portfolio_value,
            'initial_capital': self.initial_capital,
            'current_capital': self.current_capital,
            'total_pnl': self.portfolio_pnl,
            'realized_pnl': self.realized_pnl,
            'unrealized_pnl': self.unrealized_pnl,
            'drawdown_from_peak': self.drawdown_from_peak,
            'max_portfolio_value': self.max_portfolio_value,
            'open_positions_count': len(open_pos),
            'total_positions_count': len(self.positions),
            'total_exposure': self.total_exposure,
            'leverage_exposure': self.total_leverage_exposure,
            'daily_pnl': self.daily_pnl.get(datetime.now().strftime('%Y-%m-%d'), 0.0),
            'daily_loss_percentage': self.get_daily_loss_percentage(),
            'performance_stats': self.performance_stats.copy(),
            'risk_concentration': self.get_risk_concentration(),
            'position_correlations': self.correlation_matrix.copy(),
            'open_positions': [pos.to_dict() for pos in open_pos],
            'timestamp': datetime.now().isoformat()
        }


# Export classes for external use
__all__ = [
    'RiskLevel',
    'RiskMetrics',
    'PositionRisk',
    'PositionStatus',
    'PositionSide',
    'ProfitLossLevel',
    'Position',
    'Portfolio',
    'RiskManager'
]
