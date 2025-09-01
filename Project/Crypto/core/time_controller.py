"""
Time Controller Module

Implements weekly and daily time control system for trading activities.
Manages trading schedules, market hours, and time-based restrictions.
"""

from typing import Dict, List, Optional, Tuple, Any
from datetime import datetime, time, timedelta, timezone
import pytz
from enum import Enum
import asyncio
from dataclasses import dataclass

from .logger import SystemLogger


class TimeZone(Enum):
    """Supported time zones"""
    UTC = "UTC"
    NEW_YORK = "America/New_York"
    LONDON = "Europe/London"
    TOKYO = "Asia/Tokyo"
    SYDNEY = "Australia/Sydney"
    HONG_KONG = "Asia/Hong_Kong"


class TradingSession(Enum):
    """Trading session types"""
    PRE_MARKET = "pre_market"
    REGULAR = "regular"
    AFTER_HOURS = "after_hours"
    CLOSED = "closed"


@dataclass
class TradingHours:
    """Trading hours configuration"""
    start_time: time
    end_time: time
    timezone: str
    days_of_week: List[int]  # 0=Monday, 6=Sunday
    session_type: TradingSession = TradingSession.REGULAR


@dataclass
class MarketSchedule:
    """Market schedule for specific exchange/market"""
    market_name: str
    timezone: str
    regular_hours: TradingHours
    pre_market_hours: Optional[TradingHours] = None
    after_hours: Optional[TradingHours] = None
    holidays: List[datetime] = None


@dataclass
class TimeRestriction:
    """Time-based trading restriction"""
    name: str
    start_datetime: datetime
    end_datetime: datetime
    restriction_type: str  # 'no_trading', 'reduced_size', 'manual_only'
    affected_pairs: List[str] = None
    reason: str = ""


class TimeController:
    """
    Comprehensive time control system for trading operations.
    
    Features:
    - Multiple timezone support
    - Market hours management
    - Trading session detection
    - Time-based restrictions
    - Schedule-based automation
    - Holiday calendar integration
    """
    
    def __init__(self, logger: SystemLogger, default_timezone: str = "UTC"):
        """Initialize time controller."""
        self.logger = logger
        self.default_timezone = default_timezone
        
        # Market schedules
        self.market_schedules: Dict[str, MarketSchedule] = {}
        
        # Time restrictions
        self.time_restrictions: List[TimeRestriction] = []
        
        # Global trading settings
        self.global_trading_enabled = True
        self.allowed_weekdays = [0, 1, 2, 3, 4, 5, 6]  # All days by default
        self.daily_trading_start = time(0, 0)  # 00:00
        self.daily_trading_end = time(23, 59)  # 23:59
        
        # Session tracking
        self.current_sessions: Dict[str, TradingSession] = {}
        
        # Setup default market schedules
        self._setup_default_schedules()
        
    def _setup_default_schedules(self) -> None:
        """Setup default market schedules for major markets."""
        
        # Crypto markets (24/7)
        crypto_schedule = MarketSchedule(
            market_name="crypto",
            timezone="UTC",
            regular_hours=TradingHours(
                start_time=time(0, 0),
                end_time=time(23, 59),
                timezone="UTC",
                days_of_week=[0, 1, 2, 3, 4, 5, 6],  # All days
                session_type=TradingSession.REGULAR
            )
        )
        self.market_schedules["crypto"] = crypto_schedule
        
        # US Stock Market
        us_schedule = MarketSchedule(
            market_name="us_stocks",
            timezone="America/New_York",
            regular_hours=TradingHours(
                start_time=time(9, 30),
                end_time=time(16, 0),
                timezone="America/New_York",
                days_of_week=[0, 1, 2, 3, 4],  # Monday to Friday
                session_type=TradingSession.REGULAR
            ),
            pre_market_hours=TradingHours(
                start_time=time(4, 0),
                end_time=time(9, 30),
                timezone="America/New_York",
                days_of_week=[0, 1, 2, 3, 4],
                session_type=TradingSession.PRE_MARKET
            ),
            after_hours=TradingHours(
                start_time=time(16, 0),
                end_time=time(20, 0),
                timezone="America/New_York",
                days_of_week=[0, 1, 2, 3, 4],
                session_type=TradingSession.AFTER_HOURS
            )
        )
        self.market_schedules["us_stocks"] = us_schedule
        
        # London Stock Exchange
        london_schedule = MarketSchedule(
            market_name="lse",
            timezone="Europe/London",
            regular_hours=TradingHours(
                start_time=time(8, 0),
                end_time=time(16, 30),
                timezone="Europe/London",
                days_of_week=[0, 1, 2, 3, 4],
                session_type=TradingSession.REGULAR
            )
        )
        self.market_schedules["lse"] = london_schedule
        
        # Tokyo Stock Exchange
        tokyo_schedule = MarketSchedule(
            market_name="tse",
            timezone="Asia/Tokyo",
            regular_hours=TradingHours(
                start_time=time(9, 0),
                end_time=time(15, 0),
                timezone="Asia/Tokyo",
                days_of_week=[0, 1, 2, 3, 4],
                session_type=TradingSession.REGULAR
            )
        )
        self.market_schedules["tse"] = tokyo_schedule
        
    def is_trading_time(self, market: str = "crypto", current_time: Optional[datetime] = None) -> bool:
        """Check if current time is within trading hours for specified market."""
        if current_time is None:
            current_time = datetime.now(timezone.utc)
            
        # Check global trading enabled
        if not self.global_trading_enabled:
            return False
            
        # Check if market exists
        if market not in self.market_schedules:
            self.logger.warning(f"Unknown market: {market}")
            return False
            
        schedule = self.market_schedules[market]
        
        # Convert current time to market timezone
        market_tz = pytz.timezone(schedule.timezone)
        market_time = current_time.astimezone(market_tz)
        
        # Check day of week
        if market_time.weekday() not in schedule.regular_hours.days_of_week:
            return False
            
        # Check time range
        current_time_only = market_time.time()
        
        # Handle overnight sessions (e.g., 22:00 to 06:00)
        if schedule.regular_hours.start_time > schedule.regular_hours.end_time:
            # Overnight session
            return (current_time_only >= schedule.regular_hours.start_time or 
                   current_time_only <= schedule.regular_hours.end_time)
        else:
            # Regular session
            return (schedule.regular_hours.start_time <= current_time_only <= 
                   schedule.regular_hours.end_time)
                   
    def get_current_session(self, market: str = "crypto", current_time: Optional[datetime] = None) -> TradingSession:
        """Get current trading session for specified market."""
        if current_time is None:
            current_time = datetime.now(timezone.utc)
            
        if market not in self.market_schedules:
            return TradingSession.CLOSED
            
        schedule = self.market_schedules[market]
        
        # Convert to market timezone
        market_tz = pytz.timezone(schedule.timezone)
        market_time = current_time.astimezone(market_tz)
        current_time_only = market_time.time()
        weekday = market_time.weekday()
        
        # Check regular hours
        if (weekday in schedule.regular_hours.days_of_week and
            self._is_time_in_range(current_time_only, 
                                 schedule.regular_hours.start_time,
                                 schedule.regular_hours.end_time)):
            return TradingSession.REGULAR
            
        # Check pre-market
        if (schedule.pre_market_hours and 
            weekday in schedule.pre_market_hours.days_of_week and
            self._is_time_in_range(current_time_only,
                                 schedule.pre_market_hours.start_time,
                                 schedule.pre_market_hours.end_time)):
            return TradingSession.PRE_MARKET
            
        # Check after hours
        if (schedule.after_hours and
            weekday in schedule.after_hours.days_of_week and
            self._is_time_in_range(current_time_only,
                                 schedule.after_hours.start_time,
                                 schedule.after_hours.end_time)):
            return TradingSession.AFTER_HOURS
            
        return TradingSession.CLOSED
        
    def _is_time_in_range(self, current_time: time, start_time: time, end_time: time) -> bool:
        """Check if current time is within range, handling overnight sessions."""
        if start_time > end_time:
            # Overnight session
            return current_time >= start_time or current_time <= end_time
        else:
            # Regular session
            return start_time <= current_time <= end_time
            
    def get_next_trading_session(self, market: str = "crypto") -> Tuple[datetime, TradingSession]:
        """Get next trading session start time for specified market."""
        current_time = datetime.now(timezone.utc)
        
        if market not in self.market_schedules:
            return current_time, TradingSession.CLOSED
            
        schedule = self.market_schedules[market]
        
        # Convert to market timezone
        market_tz = pytz.timezone(schedule.timezone)
        market_time = current_time.astimezone(market_tz)
        
        # Find next session (simplified logic)
        next_day = market_time + timedelta(days=1)
        next_session_start = market_tz.localize(
            datetime.combine(next_day.date(), schedule.regular_hours.start_time)
        )
        
        return next_session_start.astimezone(timezone.utc), TradingSession.REGULAR
        
    def add_time_restriction(self, restriction: TimeRestriction) -> None:
        """Add time-based trading restriction."""
        self.time_restrictions.append(restriction)
        self.logger.info(f"Added time restriction: {restriction.name}")
        
    def remove_time_restriction(self, restriction_name: str) -> bool:
        """Remove time restriction by name."""
        initial_count = len(self.time_restrictions)
        self.time_restrictions = [r for r in self.time_restrictions 
                                if r.name != restriction_name]
        
        removed = len(self.time_restrictions) < initial_count
        if removed:
            self.logger.info(f"Removed time restriction: {restriction_name}")
        return removed
        
    def check_time_restrictions(self, symbol: str = "", current_time: Optional[datetime] = None) -> Tuple[bool, str]:
        """Check if current time has any active restrictions."""
        if current_time is None:
            current_time = datetime.now(timezone.utc)
            
        for restriction in self.time_restrictions:
            # Check if restriction is active
            if restriction.start_datetime <= current_time <= restriction.end_datetime:
                # Check if restriction applies to symbol
                if restriction.affected_pairs is None or symbol in restriction.affected_pairs:
                    if restriction.restriction_type == 'no_trading':
                        return False, f"Trading restricted: {restriction.reason}"
                    elif restriction.restriction_type == 'reduced_size':
                        return True, f"Reduced position sizes: {restriction.reason}"
                    elif restriction.restriction_type == 'manual_only':
                        return True, f"Manual trading only: {restriction.reason}"
                        
        return True, "No restrictions"
        
    def set_daily_trading_hours(self, start_time: time, end_time: time) -> None:
        """Set global daily trading hours."""
        self.daily_trading_start = start_time
        self.daily_trading_end = end_time
        self.logger.info(f"Set daily trading hours: {start_time} - {end_time}")
        
    def set_allowed_weekdays(self, weekdays: List[int]) -> None:
        """Set allowed weekdays for trading (0=Monday, 6=Sunday)."""
        self.allowed_weekdays = weekdays
        days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        day_names = [days[d] for d in weekdays]
        self.logger.info(f"Set allowed trading days: {', '.join(day_names)}")
        
    def is_weekday_allowed(self, current_time: Optional[datetime] = None) -> bool:
        """Check if current weekday is allowed for trading."""
        if current_time is None:
            current_time = datetime.now(timezone.utc)
        return current_time.weekday() in self.allowed_weekdays
        
    def is_daily_time_allowed(self, current_time: Optional[datetime] = None) -> bool:
        """Check if current time is within daily trading hours."""
        if current_time is None:
            current_time = datetime.now(timezone.utc)
            
        current_time_only = current_time.time()
        return self._is_time_in_range(current_time_only, 
                                    self.daily_trading_start, 
                                    self.daily_trading_end)
                                    
    def get_time_until_next_session(self, market: str = "crypto") -> timedelta:
        """Get time until next trading session."""
        current_time = datetime.now(timezone.utc)
        next_session_time, _ = self.get_next_trading_session(market)
        return next_session_time - current_time
        
    def schedule_task(self, task_time: datetime, task_func: callable, *args, **kwargs) -> None:
        """Schedule a task to run at specific time."""
        async def scheduled_task():
            current_time = datetime.now(timezone.utc)
            if task_time > current_time:
                delay = (task_time - current_time).total_seconds()
                await asyncio.sleep(delay)
            
            try:
                if asyncio.iscoroutinefunction(task_func):
                    await task_func(*args, **kwargs)
                else:
                    task_func(*args, **kwargs)
                self.logger.info(f"Scheduled task executed at {datetime.now()}")
            except Exception as e:
                self.logger.error(f"Scheduled task failed: {e}")
                
        asyncio.create_task(scheduled_task())
        self.logger.info(f"Scheduled task for {task_time}")
        
    def create_emergency_restriction(self, duration_hours: int = 24, reason: str = "Emergency") -> None:
        """Create emergency trading restriction."""
        start_time = datetime.now(timezone.utc)
        end_time = start_time + timedelta(hours=duration_hours)
        
        restriction = TimeRestriction(
            name=f"emergency_{int(start_time.timestamp())}",
            start_datetime=start_time,
            end_datetime=end_time,
            restriction_type="no_trading",
            reason=reason
        )
        
        self.add_time_restriction(restriction)
        self.logger.critical(f"Emergency trading restriction activated: {reason}")
        
    def enable_global_trading(self) -> None:
        """Enable global trading."""
        self.global_trading_enabled = True
        self.logger.info("Global trading enabled")
        
    def disable_global_trading(self) -> None:
        """Disable global trading."""
        self.global_trading_enabled = False
        self.logger.warning("Global trading disabled")
        
    def add_market_schedule(self, schedule: MarketSchedule) -> None:
        """Add custom market schedule."""
        self.market_schedules[schedule.market_name] = schedule
        self.logger.info(f"Added market schedule for: {schedule.market_name}")
        
    def get_market_status(self, market: str = "crypto") -> Dict[str, Any]:
        """Get comprehensive market status."""
        current_time = datetime.now(timezone.utc)
        current_session = self.get_current_session(market, current_time)
        is_trading_time = self.is_trading_time(market, current_time)
        next_session_time, next_session_type = self.get_next_trading_session(market)
        time_until_next = self.get_time_until_next_session(market)
        
        restrictions_allowed, restriction_msg = self.check_time_restrictions()
        
        return {
            'market': market,
            'current_time_utc': current_time.isoformat(),
            'current_session': current_session.value,
            'is_trading_time': is_trading_time,
            'global_trading_enabled': self.global_trading_enabled,
            'weekday_allowed': self.is_weekday_allowed(current_time),
            'daily_time_allowed': self.is_daily_time_allowed(current_time),
            'restrictions_allowed': restrictions_allowed,
            'restriction_message': restriction_msg,
            'next_session_time': next_session_time.isoformat(),
            'next_session_type': next_session_type.value,
            'time_until_next_session_seconds': int(time_until_next.total_seconds()),
            'active_restrictions': len([r for r in self.time_restrictions 
                                      if r.start_datetime <= current_time <= r.end_datetime])
        }
        
    def get_trading_calendar(self, market: str = "crypto", days: int = 7) -> List[Dict[str, Any]]:
        """Get trading calendar for next N days."""
        calendar = []
        current_date = datetime.now(timezone.utc).date()
        
        for i in range(days):
            check_date = current_date + timedelta(days=i)
            check_datetime = datetime.combine(check_date, time(12, 0), timezone.utc)
            
            session = self.get_current_session(market, check_datetime)
            is_trading = self.is_trading_time(market, check_datetime)
            
            calendar.append({
                'date': check_date.isoformat(),
                'weekday': check_date.weekday(),
                'is_trading_day': is_trading,
                'session_type': session.value,
                'market': market
            })
            
        return calendar
        
    def cleanup_expired_restrictions(self) -> int:
        """Remove expired time restrictions."""
        current_time = datetime.now(timezone.utc)
        initial_count = len(self.time_restrictions)
        
        self.time_restrictions = [r for r in self.time_restrictions 
                                if r.end_datetime > current_time]
        
        removed_count = initial_count - len(self.time_restrictions)
        if removed_count > 0:
            self.logger.info(f"Cleaned up {removed_count} expired time restrictions")
            
        return removed_count