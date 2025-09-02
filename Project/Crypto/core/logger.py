"""
Logger Module

Comprehensive logging system with file rotation, structured logging,
performance monitoring, and security event tracking.
"""

import logging
import logging.handlers
import json
import sys
import os
from datetime import datetime, timezone
from typing import Dict, Any, Optional, Union
from pathlib import Path
from dataclasses import dataclass, asdict
from enum import Enum
import traceback
import threading
import time


class LogLevel(Enum):
    """Log levels"""
    CRITICAL = "CRITICAL"
    ERROR = "ERROR"
    WARNING = "WARNING"
    INFO = "INFO"
    DEBUG = "DEBUG"


class LogCategory(Enum):
    """Log categories for better organization"""
    SYSTEM = "system"
    TRADING = "trading"
    SECURITY = "security"
    API = "api"
    PERFORMANCE = "performance"
    USER = "user"
    DATA = "data"


@dataclass
class LogEntry:
    """Structured log entry"""
    timestamp: str
    level: str
    category: str
    message: str
    module: str
    function: str
    line_number: int
    thread_id: int
    process_id: int
    extra_data: Dict[str, Any] = None
    exception: Optional[str] = None
    stack_trace: Optional[str] = None


@dataclass
class PerformanceMetric:
    """Performance monitoring metric"""
    name: str
    value: float
    unit: str
    timestamp: datetime
    tags: Dict[str, str] = None


class SystemLogger:
    """
    Advanced logging system for the crypto trading platform.
    
    Features:
    - Structured JSON logging
    - File rotation and compression
    - Multiple log levels and categories
    - Performance monitoring
    - Security event tracking
    - Error aggregation
    - Remote logging capability
    """
    
    def __init__(self, 
                 name: str = "CryptoTrading",
                 log_dir: str = "logs",
                 log_level: str = "INFO",
                 max_file_size: int = 100 * 1024 * 1024,  # 100MB
                 backup_count: int = 10,
                 console_output: bool = True,
                 json_format: bool = True):
        """Initialize the logging system."""
        
        self.name = name
        self.log_dir = Path(log_dir)
        self.log_dir.mkdir(parents=True, exist_ok=True)
        
        # Create main logger
        self.logger = logging.getLogger(name)
        self.logger.setLevel(getattr(logging, log_level.upper()))
        
        # Clear existing handlers
        self.logger.handlers.clear()
        
        # Configuration
        self.json_format = json_format
        self.console_output = console_output
        
        # Performance metrics storage
        self.performance_metrics: list[PerformanceMetric] = []
        self.error_counts: Dict[str, int] = {}
        
        # Setup handlers
        self._setup_file_handlers(max_file_size, backup_count)
        if console_output:
            self._setup_console_handler()
            
        # Log startup
        self.info("Logger initialized", category=LogCategory.SYSTEM)
        
    def _setup_file_handlers(self, max_file_size: int, backup_count: int) -> None:
        """Setup rotating file handlers for different log levels."""
        
        # Main log file (all levels)
        main_handler = logging.handlers.RotatingFileHandler(
            filename=self.log_dir / "trading.log",
            maxBytes=max_file_size,
            backupCount=backup_count,
            encoding='utf-8'
        )
        main_handler.setLevel(logging.DEBUG)
        main_handler.setFormatter(self._get_formatter())
        self.logger.addHandler(main_handler)
        
        # Error log file (errors and critical only)
        error_handler = logging.handlers.RotatingFileHandler(
            filename=self.log_dir / "errors.log",
            maxBytes=max_file_size // 2,
            backupCount=backup_count,
            encoding='utf-8'
        )
        error_handler.setLevel(logging.ERROR)
        error_handler.setFormatter(self._get_formatter())
        self.logger.addHandler(error_handler)
        
        # Security log file
        security_handler = logging.handlers.RotatingFileHandler(
            filename=self.log_dir / "security.log",
            maxBytes=max_file_size // 4,
            backupCount=backup_count * 2,  # Keep more security logs
            encoding='utf-8'
        )
        security_handler.setLevel(logging.INFO)
        security_handler.addFilter(self._security_filter)
        security_handler.setFormatter(self._get_formatter())
        self.logger.addHandler(security_handler)
        
        # Trading log file
        trading_handler = logging.handlers.RotatingFileHandler(
            filename=self.log_dir / "trading.log",
            maxBytes=max_file_size,
            backupCount=backup_count,
            encoding='utf-8'
        )
        trading_handler.setLevel(logging.INFO)
        trading_handler.addFilter(self._trading_filter)
        trading_handler.setFormatter(self._get_formatter())
        self.logger.addHandler(trading_handler)
        
        # Performance log file
        performance_handler = logging.handlers.RotatingFileHandler(
            filename=self.log_dir / "performance.log",
            maxBytes=max_file_size // 4,
            backupCount=backup_count,
            encoding='utf-8'
        )
        performance_handler.setLevel(logging.INFO)
        performance_handler.addFilter(self._performance_filter)
        performance_handler.setFormatter(self._get_formatter())
        self.logger.addHandler(performance_handler)
        
    def _setup_console_handler(self) -> None:
        """Setup console handler for real-time monitoring."""
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.INFO)
        console_handler.setFormatter(self._get_console_formatter())
        self.logger.addHandler(console_handler)
        
    def _get_formatter(self) -> logging.Formatter:
        """Get formatter based on configuration."""
        if self.json_format:
            return JsonFormatter()
        else:
            return logging.Formatter(
                fmt='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
                datefmt='%Y-%m-%d %H:%M:%S'
            )
            
    def _get_console_formatter(self) -> logging.Formatter:
        """Get console-specific formatter."""
        return logging.Formatter(
            fmt='%(asctime)s - %(levelname)-8s - %(message)s',
            datefmt='%H:%M:%S'
        )
        
    def _security_filter(self, record: logging.LogRecord) -> bool:
        """Filter for security-related logs."""
        return hasattr(record, 'category') and record.category == LogCategory.SECURITY.value
        
    def _trading_filter(self, record: logging.LogRecord) -> bool:
        """Filter for trading-related logs."""
        return hasattr(record, 'category') and record.category == LogCategory.TRADING.value
        
    def _performance_filter(self, record: logging.LogRecord) -> bool:
        """Filter for performance-related logs."""
        return hasattr(record, 'category') and record.category == LogCategory.PERFORMANCE.value
        
    def _create_log_record(self, 
                          level: str,
                          message: str,
                          category: LogCategory = LogCategory.SYSTEM,
                          extra_data: Dict[str, Any] = None,
                          exc_info: bool = False) -> Dict[str, Any]:
        """Create structured log record."""
        
        # Get caller information
        frame = sys._getframe(3)  # Go up the call stack
        
        record_data = {
            'timestamp': datetime.now(timezone.utc).isoformat(),
            'level': level,
            'category': category.value,
            'message': message,
            'module': frame.f_globals.get('__name__', 'unknown'),
            'function': frame.f_code.co_name,
            'line_number': frame.f_lineno,
            'thread_id': threading.current_thread().ident,
            'process_id': os.getpid()
        }
        
        if extra_data:
            record_data['extra_data'] = extra_data
            
        if exc_info:
            exc_type, exc_value, exc_traceback = sys.exc_info()
            if exc_value:
                record_data['exception'] = str(exc_value)
                record_data['stack_trace'] = ''.join(
                    traceback.format_exception(exc_type, exc_value, exc_traceback)
                )
                
        return record_data
        
    # Main logging methods
    
    def debug(self, 
              message: str, 
              category: LogCategory = LogCategory.SYSTEM,
              extra_data: Dict[str, Any] = None) -> None:
        """Log debug message."""
        record = self._create_log_record("DEBUG", message, category, extra_data)
        self.logger.debug(message, extra={
            'category': category.value,
            'extra_data': extra_data,
            'structured_data': record
        })
        
    def info(self, 
             message: str, 
             category: LogCategory = LogCategory.SYSTEM,
             extra_data: Dict[str, Any] = None) -> None:
        """Log info message."""
        record = self._create_log_record("INFO", message, category, extra_data)
        self.logger.info(message, extra={
            'category': category.value,
            'extra_data': extra_data,
            'structured_data': record
        })
        
    def warning(self, 
                message: str, 
                category: LogCategory = LogCategory.SYSTEM,
                extra_data: Dict[str, Any] = None) -> None:
        """Log warning message."""
        record = self._create_log_record("WARNING", message, category, extra_data)
        self.logger.warning(message, extra={
            'category': category.value,
            'extra_data': extra_data,
            'structured_data': record
        })
        
    def error(self, 
              message: str, 
              category: LogCategory = LogCategory.SYSTEM,
              extra_data: Dict[str, Any] = None,
              exc_info: bool = False) -> None:
        """Log error message."""
        record = self._create_log_record("ERROR", message, category, extra_data, exc_info)
        self.logger.error(message, extra={
            'category': category.value,
            'extra_data': extra_data,
            'structured_data': record
        }, exc_info=exc_info)
        
        # Track error count
        error_key = f"{category.value}:{message[:50]}"
        self.error_counts[error_key] = self.error_counts.get(error_key, 0) + 1
        
    def critical(self, 
                 message: str, 
                 category: LogCategory = LogCategory.SYSTEM,
                 extra_data: Dict[str, Any] = None,
                 exc_info: bool = False) -> None:
        """Log critical message."""
        record = self._create_log_record("CRITICAL", message, category, extra_data, exc_info)
        self.logger.critical(message, extra={
            'category': category.value,
            'extra_data': extra_data,
            'structured_data': record
        }, exc_info=exc_info)
        
    # Specialized logging methods
    
    def log_trade(self, 
                  symbol: str, 
                  side: str, 
                  size: float, 
                  price: float, 
                  order_id: str = "",
                  exchange: str = "",
                  extra_data: Dict[str, Any] = None) -> None:
        """Log trading activity."""
        trade_data = {
            'symbol': symbol,
            'side': side,
            'size': size,
            'price': price,
            'order_id': order_id,
            'exchange': exchange,
            'value': size * price
        }
        if extra_data:
            trade_data.update(extra_data)
            
        self.info(
            f"Trade executed: {side.upper()} {size} {symbol} @ {price}",
            category=LogCategory.TRADING,
            extra_data=trade_data
        )
        
    def log_security_event(self, 
                          event_type: str, 
                          severity: str,
                          details: Dict[str, Any],
                          user_id: str = "",
                          ip_address: str = "") -> None:
        """Log security event."""
        security_data = {
            'event_type': event_type,
            'severity': severity,
            'user_id': user_id,
            'ip_address': ip_address,
            'details': details
        }
        
        message = f"Security event: {event_type} (Severity: {severity})"
        
        if severity.upper() == "CRITICAL":
            self.critical(message, LogCategory.SECURITY, security_data)
        elif severity.upper() == "HIGH":
            self.error(message, LogCategory.SECURITY, security_data)
        else:
            self.warning(message, LogCategory.SECURITY, security_data)
            
    def log_api_request(self, 
                       method: str, 
                       endpoint: str, 
                       status_code: int,
                       response_time: float,
                       user_id: str = "",
                       ip_address: str = "") -> None:
        """Log API request."""
        api_data = {
            'method': method,
            'endpoint': endpoint,
            'status_code': status_code,
            'response_time_ms': response_time * 1000,
            'user_id': user_id,
            'ip_address': ip_address
        }
        
        level = "ERROR" if status_code >= 400 else "INFO"
        message = f"API {method} {endpoint} - {status_code} ({response_time:.2f}ms)"
        
        if level == "ERROR":
            self.error(message, LogCategory.API, api_data)
        else:
            self.info(message, LogCategory.API, api_data)
            
    def log_performance_metric(self, 
                              name: str, 
                              value: float, 
                              unit: str = "",
                              tags: Dict[str, str] = None) -> None:
        """Log performance metric."""
        metric = PerformanceMetric(
            name=name,
            value=value,
            unit=unit,
            timestamp=datetime.now(timezone.utc),
            tags=tags or {}
        )
        
        self.performance_metrics.append(metric)
        
        # Keep only recent metrics (last 1000)
        if len(self.performance_metrics) > 1000:
            self.performance_metrics = self.performance_metrics[-1000:]
            
        self.info(
            f"Performance metric: {name} = {value} {unit}",
            category=LogCategory.PERFORMANCE,
            extra_data=asdict(metric)
        )
        
    # Context managers for automatic logging
    
    def log_execution_time(self, operation_name: str):
        """Context manager to log execution time."""
        return ExecutionTimeLogger(self, operation_name)
        
    def log_exception_context(self, operation_name: str):
        """Context manager to log exceptions."""
        return ExceptionLogger(self, operation_name)
        
    # Utility methods
    
    def get_error_summary(self, hours: int = 24) -> Dict[str, int]:
        """Get error summary for the last N hours."""
        return dict(self.error_counts)
        
    def get_recent_performance_metrics(self, minutes: int = 60) -> list[PerformanceMetric]:
        """Get recent performance metrics."""
        cutoff_time = datetime.now(timezone.utc) - timedelta(minutes=minutes)
        return [m for m in self.performance_metrics if m.timestamp > cutoff_time]
        
    def clear_metrics(self) -> None:
        """Clear stored metrics and error counts."""
        self.performance_metrics.clear()
        self.error_counts.clear()
        self.info("Metrics cleared", category=LogCategory.SYSTEM)
        
    def set_log_level(self, level: str) -> None:
        """Change log level dynamically."""
        self.logger.setLevel(getattr(logging, level.upper()))
        self.info(f"Log level changed to {level.upper()}", category=LogCategory.SYSTEM)
        
    def flush_logs(self) -> None:
        """Force flush all log handlers."""
        for handler in self.logger.handlers:
            if hasattr(handler, 'flush'):
                handler.flush()


class JsonFormatter(logging.Formatter):
    """JSON formatter for structured logging."""
    
    def format(self, record: logging.LogRecord) -> str:
        """Format log record as JSON."""
        # Get structured data if available
        if hasattr(record, 'structured_data'):
            log_data = record.structured_data
        else:
            # Fallback to basic structure
            log_data = {
                'timestamp': datetime.fromtimestamp(record.created, tz=timezone.utc).isoformat(),
                'level': record.levelname,
                'message': record.getMessage(),
                'module': record.module,
                'function': record.funcName,
                'line_number': record.lineno
            }
            
        return json.dumps(log_data, ensure_ascii=False)


class ExecutionTimeLogger:
    """Context manager for logging execution time."""
    
    def __init__(self, logger: SystemLogger, operation_name: str):
        self.logger = logger
        self.operation_name = operation_name
        self.start_time = None
        
    def __enter__(self):
        self.start_time = time.time()
        return self
        
    def __exit__(self, exc_type, exc_val, exc_tb):
        execution_time = time.time() - self.start_time
        self.logger.log_performance_metric(
            name=f"{self.operation_name}_execution_time",
            value=execution_time,
            unit="seconds"
        )


class ExceptionLogger:
    """Context manager for logging exceptions."""
    
    def __init__(self, logger: SystemLogger, operation_name: str):
        self.logger = logger
        self.operation_name = operation_name
        
    def __enter__(self):
        return self
        
    def __exit__(self, exc_type, exc_val, exc_tb):
        if exc_type is not None:
            self.logger.error(
                f"Exception in {self.operation_name}: {exc_val}",
                category=LogCategory.SYSTEM,
                extra_data={'operation': self.operation_name},
                exc_info=True
            )
            return False  # Don't suppress the exception