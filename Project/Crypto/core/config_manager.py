"""
Configuration Manager Module

Handles encrypted configuration storage, environment management,
and secure credential handling for the trading system.
"""

from typing import Dict, Any, Optional, Union, Callable, List, Set
from pathlib import Path
import json
import os
import threading
import asyncio
import hashlib
from datetime import datetime
from dataclasses import dataclass, asdict
from enum import Enum
import weakref

from .security_module import SecurityModule
from .logger import SystemLogger


class Environment(Enum):
    """Environment types"""
    DEVELOPMENT = "development"
    TESTING = "testing"
    PRODUCTION = "production"


@dataclass
class ExchangeConfig:
    """Exchange configuration"""
    name: str
    api_key: str
    api_secret: str
    testnet: bool = True
    rate_limit: int = 1200  # requests per minute
    timeout: float = 30.0
    retry_attempts: int = 3


@dataclass
class TradingConfig:
    """Trading configuration"""
    enabled_pairs: list
    default_leverage: float = 1.0
    max_positions: int = 10
    position_size_pct: float = 0.02  # 2% of capital per position
    stop_loss_pct: float = 0.03  # 3% stop loss
    take_profit_pct: float = 0.06  # 6% take profit
    max_daily_trades: int = 50
    trading_hours_start: str = "00:00"
    trading_hours_end: str = "23:59"
    allowed_weekdays: list = [0, 1, 2, 3, 4, 5, 6]  # 0=Monday, 6=Sunday


@dataclass
class RiskConfig:
    """Risk management configuration"""
    max_capital_risk_pct: float = 0.02  # 2% of capital at risk per trade
    max_portfolio_risk_pct: float = 0.20  # 20% max portfolio exposure
    max_drawdown_pct: float = 0.15  # 15% max drawdown before emergency stop
    max_daily_loss_pct: float = 0.05  # 5% max daily loss
    correlation_limit: float = 0.7  # Max correlation between positions
    leverage_limit: float = 10.0
    emergency_stop_enabled: bool = True


@dataclass
class SecurityConfig:
    """Security configuration"""
    jwt_secret_key: str
    jwt_expiration_hours: int = 24
    encryption_enabled: bool = True
    api_rate_limiting: bool = True
    allowed_ips: list = []
    session_timeout_minutes: int = 60
    require_2fa: bool = False


@dataclass
class LoggingConfig:
    """Logging configuration"""
    level: str = "INFO"
    file_path: str = "logs/trading.log"
    max_file_size_mb: int = 100
    backup_count: int = 5
    format: str = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
    console_output: bool = True
    file_output: bool = True


@dataclass
class DatabaseConfig:
    """Database configuration"""
    enabled: bool = True
    type: str = "sqlite"  # sqlite, postgresql, mysql
    host: str = "localhost"
    port: int = 5432
    database: str = "trading_db"
    username: str = ""
    password: str = ""
    connection_pool_size: int = 10


@dataclass
class NotificationConfig:
    """Notification configuration"""
    email_enabled: bool = False
    email_smtp_server: str = ""
    email_port: int = 587
    email_username: str = ""
    email_password: str = ""
    email_recipients: list = []
    
    telegram_enabled: bool = False
    telegram_bot_token: str = ""
    telegram_chat_ids: list = []
    
    discord_enabled: bool = False
    discord_webhook_url: str = ""
    
    notification_levels: list = ["ERROR", "CRITICAL"]


@dataclass
class WebConfig:
    """Web interface configuration"""
    enabled: bool = True
    host: str = "0.0.0.0"
    port: int = 8080
    debug: bool = False
    secret_key: str = ""
    cors_enabled: bool = True
    rate_limiting: bool = True
    auth_required: bool = True


@dataclass
class SystemConfig:
    """Complete system configuration"""
    environment: Environment
    exchanges: Dict[str, ExchangeConfig]
    trading: TradingConfig
    risk: RiskConfig
    security: SecurityConfig
    logging: LoggingConfig
    database: DatabaseConfig
    notifications: NotificationConfig
    web: WebConfig
    
    created_at: datetime
    updated_at: datetime
    version: str = "1.0.0"
    config_hash: Optional[str] = None  # For synchronization detection


@dataclass
class ConfigChangeEvent:
    """Configuration change event"""
    change_id: str
    timestamp: datetime
    section: str
    key: str
    old_value: Any
    new_value: Any
    source: str = "unknown"


class ConfigManager:
    """
    Enhanced configuration manager with encrypted storage, environment support,
    and cross-version synchronization capabilities.
    
    Features:
    - Encrypted configuration storage using Fernet
    - Environment-specific configurations
    - Configuration validation
    - Hot-reload capability
    - Backup and restore functionality
    - Cross-version synchronization
    - Change notification system
    - Version compatibility checking
    """
    
    def __init__(self, 
                 security_module: SecurityModule, 
                 logger: SystemLogger,
                 config_dir: str = "config"):
        """Initialize enhanced configuration manager."""
        self.security = security_module
        self.logger = logger
        self.config_dir = Path(config_dir)
        self.config_dir.mkdir(parents=True, exist_ok=True)
        
        self.current_config: Optional[SystemConfig] = None
        self.environment = Environment.DEVELOPMENT
        self.config_cache: Dict[Environment, SystemConfig] = {}
        
        # Synchronization features
        self.change_callbacks: List[Callable[[ConfigChangeEvent], None]] = []
        self.sync_callbacks: List[Callable[[SystemConfig], None]] = []
        self.config_lock = threading.RLock()
        self.sync_enabled = True
        self.sync_interval = 5.0  # seconds
        self.sync_task: Optional[asyncio.Task] = None
        
        # Change tracking
        self.change_history: List[ConfigChangeEvent] = []
        self.max_history_size = 100
        self.last_sync_hash: Optional[str] = None
        
        # Version compatibility
        self.min_compatible_version = "1.0.0"
        self.max_compatible_version = "2.0.0"
        
        # Cross-version sync clients
        self.sync_clients: Set[str] = set()
        self.pending_changes: Dict[str, ConfigChangeEvent] = {}
        
        # Configuration file paths
        self.config_files = {
            Environment.DEVELOPMENT: self.config_dir / "development.enc",
            Environment.TESTING: self.config_dir / "testing.enc", 
            Environment.PRODUCTION: self.config_dir / "production.enc"
        }
        
        # Sync state file
        self.sync_state_file = self.config_dir / "sync_state.json"
        
        # Template configurations
        self._create_default_templates()
        
        # Load sync state
        self._load_sync_state()
        
    def _create_default_templates(self) -> None:
        """Create default configuration templates if they don't exist."""
        template_dir = self.config_dir / "templates"
        template_dir.mkdir(exist_ok=True)
        
        # Development template
        dev_config = self._get_default_config(Environment.DEVELOPMENT)
        self._save_template(dev_config, template_dir / "development.json")
        
        # Testing template
        test_config = self._get_default_config(Environment.TESTING)
        self._save_template(test_config, template_dir / "testing.json")
        
        # Production template
        prod_config = self._get_default_config(Environment.PRODUCTION)
        self._save_template(prod_config, template_dir / "production.json")
        
    def _get_default_config(self, env: Environment) -> SystemConfig:
        """Get default configuration for environment."""
        is_production = env == Environment.PRODUCTION
        
        # Default exchange configurations
        exchanges = {
            "binance": ExchangeConfig(
                name="binance",
                api_key="YOUR_BINANCE_API_KEY",
                api_secret="YOUR_BINANCE_API_SECRET",
                testnet=not is_production
            ),
            "bybit": ExchangeConfig(
                name="bybit", 
                api_key="YOUR_BYBIT_API_KEY",
                api_secret="YOUR_BYBIT_API_SECRET",
                testnet=not is_production
            )
        }
        
        # Default trading pairs
        enabled_pairs = [
            "BTCUSDT", "ETHUSDT", "ADAUSDT", "DOTUSDT", "LINKUSDT",
            "BNBUSDT", "LTCUSDT", "XRPUSDT", "EOSUSDT", "ETCUSDT"
        ]
        
        return SystemConfig(
            environment=env,
            exchanges=exchanges,
            trading=TradingConfig(
                enabled_pairs=enabled_pairs,
                max_positions=5 if is_production else 10,
                position_size_pct=0.01 if is_production else 0.02
            ),
            risk=RiskConfig(
                max_capital_risk_pct=0.01 if is_production else 0.02,
                max_portfolio_risk_pct=0.15 if is_production else 0.20,
                emergency_stop_enabled=True
            ),
            security=SecurityConfig(
                jwt_secret_key=self.security.generate_random_key().decode(),
                encryption_enabled=True,
                api_rate_limiting=True,
                require_2fa=is_production
            ),
            logging=LoggingConfig(
                level="WARNING" if is_production else "INFO",
                console_output=not is_production
            ),
            database=DatabaseConfig(
                enabled=True,
                type="postgresql" if is_production else "sqlite",
                database=f"trading_{env.value}"
            ),
            notifications=NotificationConfig(
                email_enabled=is_production,
                telegram_enabled=is_production
            ),
            web=WebConfig(
                enabled=True,
                debug=not is_production,
                auth_required=True,
                secret_key=self.security.generate_random_key().decode()
            ),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )
        
    def _save_template(self, config: SystemConfig, file_path: Path) -> None:
        """Save configuration template as JSON."""
        try:
            if not file_path.exists():
                with open(file_path, 'w', encoding='utf-8') as f:
                    json.dump(asdict(config), f, indent=2, default=str)
                self.logger.info(f"Created configuration template: {file_path}")
        except Exception as e:
            self.logger.error(f"Failed to save template {file_path}: {e}")
            
    def load_config(self, environment: Environment = Environment.DEVELOPMENT) -> SystemConfig:
        """Load configuration for specified environment."""
        self.environment = environment
        
        # Check cache first
        if environment in self.config_cache:
            self.current_config = self.config_cache[environment]
            return self.current_config
            
        config_file = self.config_files[environment]
        
        try:
            if config_file.exists():
                # Load encrypted configuration
                with open(config_file, 'rb') as f:
                    encrypted_data = f.read()
                    
                decrypted_data = self.security.decrypt_data(encrypted_data)
                config_dict = json.loads(decrypted_data.decode())
                
                # Convert dict back to SystemConfig
                config = self._dict_to_config(config_dict)
                config.updated_at = datetime.now()
                
            else:
                # Create default configuration
                self.logger.warning(f"Configuration file not found: {config_file}")
                config = self._get_default_config(environment)
                self.save_config(config, environment)
                
            self.config_cache[environment] = config
            self.current_config = config
            
            self.logger.info(f"Loaded configuration for environment: {environment.value}")
            return config
            
        except Exception as e:
            self.logger.error(f"Failed to load configuration: {e}")
            # Return default configuration as fallback
            return self._get_default_config(environment)
            
    def save_config(self, config: SystemConfig, environment: Environment) -> bool:
        """Save configuration with encryption."""
        try:
            config.updated_at = datetime.now()
            config_dict = asdict(config)
            
            # Convert to JSON and encrypt
            json_data = json.dumps(config_dict, default=str, indent=2)
            encrypted_data = self.security.encrypt_data(json_data.encode())
            
            # Save encrypted configuration
            config_file = self.config_files[environment]
            with open(config_file, 'wb') as f:
                f.write(encrypted_data)
                
            # Update cache
            self.config_cache[environment] = config
            
            self.logger.info(f"Saved configuration for environment: {environment.value}")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to save configuration: {e}")
            return False
            
    def update_config(self, updates: Dict[str, Any], environment: Optional[Environment] = None) -> bool:
        """Update specific configuration values."""
        if environment is None:
            environment = self.environment
            
        config = self.load_config(environment)
        
        try:
            # Apply updates to configuration
            for key, value in updates.items():
                if hasattr(config, key):
                    if isinstance(getattr(config, key), dict):
                        getattr(config, key).update(value)
                    else:
                        setattr(config, key, value)
                        
            return self.save_config(config, environment)
            
        except Exception as e:
            self.logger.error(f"Failed to update configuration: {e}")
            return False
            
    def get_exchange_config(self, exchange_name: str) -> Optional[ExchangeConfig]:
        """Get configuration for specific exchange."""
        if self.current_config and exchange_name in self.current_config.exchanges:
            return self.current_config.exchanges[exchange_name]
        return None
        
    def get_trading_config(self) -> Optional[TradingConfig]:
        """Get trading configuration."""
        return self.current_config.trading if self.current_config else None
        
    def get_risk_config(self) -> Optional[RiskConfig]:
        """Get risk management configuration."""
        return self.current_config.risk if self.current_config else None
        
    def get_security_config(self) -> Optional[SecurityConfig]:
        """Get security configuration."""
        return self.current_config.security if self.current_config else None
        
    def validate_config(self, config: SystemConfig) -> tuple[bool, list[str]]:
        """Validate configuration and return validation results."""
        errors = []
        
        # Validate exchanges
        if not config.exchanges:
            errors.append("At least one exchange must be configured")
            
        for name, exchange in config.exchanges.items():
            if not exchange.api_key or exchange.api_key == "YOUR_API_KEY":
                errors.append(f"Invalid API key for exchange: {name}")
            if not exchange.api_secret or exchange.api_secret == "YOUR_API_SECRET":
                errors.append(f"Invalid API secret for exchange: {name}")
                
        # Validate trading configuration
        if not config.trading.enabled_pairs:
            errors.append("No trading pairs configured")
            
        if config.trading.position_size_pct <= 0 or config.trading.position_size_pct > 1:
            errors.append("Position size percentage must be between 0 and 1")
            
        # Validate risk configuration
        if config.risk.max_drawdown_pct <= 0 or config.risk.max_drawdown_pct > 1:
            errors.append("Max drawdown percentage must be between 0 and 1")
            
        # Validate security configuration
        if not config.security.jwt_secret_key:
            errors.append("JWT secret key is required")
            
        return len(errors) == 0, errors
        
    def backup_config(self, environment: Environment, backup_dir: str = "backups") -> str:
        """Create backup of configuration."""
        backup_path = Path(backup_dir)
        backup_path.mkdir(parents=True, exist_ok=True)
        
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_file = backup_path / f"config_{environment.value}_{timestamp}.enc"
        
        try:
            source_file = self.config_files[environment]
            if source_file.exists():
                import shutil
                shutil.copy2(source_file, backup_file)
                self.logger.info(f"Configuration backed up to: {backup_file}")
                return str(backup_file)
        except Exception as e:
            self.logger.error(f"Failed to backup configuration: {e}")
            
        return ""
        
    def restore_config(self, backup_file: str, environment: Environment) -> bool:
        """Restore configuration from backup."""
        try:
            backup_path = Path(backup_file)
            if backup_path.exists():
                import shutil
                target_file = self.config_files[environment]
                shutil.copy2(backup_path, target_file)
                
                # Clear cache to force reload
                if environment in self.config_cache:
                    del self.config_cache[environment]
                    
                self.logger.info(f"Configuration restored from: {backup_file}")
                return True
        except Exception as e:
            self.logger.error(f"Failed to restore configuration: {e}")
            
        return False
        
    def _dict_to_config(self, config_dict: Dict[str, Any]) -> SystemConfig:
        """Convert dictionary to SystemConfig object."""
        # Convert string dates back to datetime objects
        if 'created_at' in config_dict:
            config_dict['created_at'] = datetime.fromisoformat(config_dict['created_at'])
        if 'updated_at' in config_dict:
            config_dict['updated_at'] = datetime.fromisoformat(config_dict['updated_at'])
            
        # Convert environment string to enum
        config_dict['environment'] = Environment(config_dict['environment'])
        
        # Convert nested dictionaries to dataclass objects
        if 'exchanges' in config_dict:
            exchanges = {}
            for name, exchange_data in config_dict['exchanges'].items():
                exchanges[name] = ExchangeConfig(**exchange_data)
            config_dict['exchanges'] = exchanges
            
        # Convert other nested objects
        if 'trading' in config_dict:
            config_dict['trading'] = TradingConfig(**config_dict['trading'])
            
        if 'risk' in config_dict:
            config_dict['risk'] = RiskConfig(**config_dict['risk'])
            
        if 'security' in config_dict:
            config_dict['security'] = SecurityConfig(**config_dict['security'])
            
        if 'logging' in config_dict:
            config_dict['logging'] = LoggingConfig(**config_dict['logging'])
            
        if 'database' in config_dict:
            config_dict['database'] = DatabaseConfig(**config_dict['database'])
            
        if 'notifications' in config_dict:
            config_dict['notifications'] = NotificationConfig(**config_dict['notifications'])
            
        if 'web' in config_dict:
            config_dict['web'] = WebConfig(**config_dict['web'])
            
        return SystemConfig(**config_dict)
        
    def get_current_config(self) -> Optional[SystemConfig]:
        """Get currently loaded configuration."""
        return self.current_config
        
    def reload_config(self) -> bool:
        """Reload configuration from file."""
        try:
            # Clear cache
            if self.environment in self.config_cache:
                del self.config_cache[self.environment]
                
            # Reload configuration
            self.load_config(self.environment)
            self.logger.info("Configuration reloaded successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to reload configuration: {e}")
            return False
            
    # Synchronization Methods
    
    def start_sync_monitoring(self) -> bool:
        """Start configuration synchronization monitoring."""
        try:
            if self.sync_task is not None:
                return True
                
            self.sync_enabled = True
            self.sync_task = asyncio.create_task(self._sync_monitoring_loop())
            self.logger.info("Configuration sync monitoring started")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to start sync monitoring: {e}")
            return False
            
    async def stop_sync_monitoring(self) -> bool:
        """Stop configuration synchronization monitoring."""
        try:
            self.sync_enabled = False
            
            if self.sync_task:
                self.sync_task.cancel()
                try:
                    await self.sync_task
                except asyncio.CancelledError:
                    pass
                self.sync_task = None
                
            self.logger.info("Configuration sync monitoring stopped")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to stop sync monitoring: {e}")
            return False
            
    def register_change_callback(self, callback: Callable[[ConfigChangeEvent], None]) -> None:
        """Register a callback for configuration changes."""
        self.change_callbacks.append(callback)
        self.logger.debug("Configuration change callback registered")
        
    def register_sync_callback(self, callback: Callable[[SystemConfig], None]) -> None:
        """Register a callback for configuration synchronization."""
        self.sync_callbacks.append(callback)
        self.logger.debug("Configuration sync callback registered")
        
    def unregister_change_callback(self, callback: Callable[[ConfigChangeEvent], None]) -> None:
        """Unregister a change callback."""
        try:
            self.change_callbacks.remove(callback)
        except ValueError:
            pass
            
    def unregister_sync_callback(self, callback: Callable[[SystemConfig], None]) -> None:
        """Unregister a sync callback."""
        try:
            self.sync_callbacks.remove(callback)
        except ValueError:
            pass
            
    def register_sync_client(self, client_id: str) -> None:
        """Register a client for cross-version synchronization."""
        with self.config_lock:
            self.sync_clients.add(client_id)
            self.logger.info(f"Sync client registered: {client_id}")
            
    def unregister_sync_client(self, client_id: str) -> None:
        """Unregister a sync client."""
        with self.config_lock:
            self.sync_clients.discard(client_id)
            self.logger.info(f"Sync client unregistered: {client_id}")
            
    def update_config_value(self, section: str, key: str, value: Any, 
                           source: str = "unknown", 
                           environment: Optional[Environment] = None) -> bool:
        """Update a specific configuration value with change tracking."""
        if environment is None:
            environment = self.environment
            
        try:
            with self.config_lock:
                config = self.load_config(environment)
                
                # Get old value
                old_value = None
                if hasattr(config, section):
                    section_obj = getattr(config, section)
                    if hasattr(section_obj, key):
                        old_value = getattr(section_obj, key)
                    else:
                        # Handle dict-like access for exchanges
                        if isinstance(section_obj, dict) and key in section_obj:
                            old_value = section_obj[key]
                            
                # Update value
                if hasattr(config, section):
                    section_obj = getattr(config, section)
                    if hasattr(section_obj, key):
                        setattr(section_obj, key, value)
                    elif isinstance(section_obj, dict):
                        section_obj[key] = value
                    else:
                        raise ValueError(f"Cannot update {section}.{key}")
                else:
                    raise ValueError(f"Section {section} not found")
                    
                # Update config hash and timestamp
                config.updated_at = datetime.now()
                config.config_hash = self._calculate_config_hash(config)
                
                # Save configuration
                success = self.save_config(config, environment)
                
                if success:
                    # Create change event
                    change_event = ConfigChangeEvent(
                        change_id=str(datetime.now().timestamp()),
                        timestamp=datetime.now(),
                        section=section,
                        key=key,
                        old_value=old_value,
                        new_value=value,
                        source=source
                    )
                    
                    # Add to history
                    self._add_change_to_history(change_event)
                    
                    # Notify callbacks
                    self._notify_change_callbacks(change_event)
                    
                    self.logger.info(f"Configuration updated: {section}.{key} = {value}")
                    return True
                    
                return False
                
        except Exception as e:
            self.logger.error(f"Failed to update config value {section}.{key}: {e}")
            return False
            
    def get_config_hash(self, environment: Optional[Environment] = None) -> Optional[str]:
        """Get the current configuration hash."""
        if environment is None:
            environment = self.environment
            
        config = self.load_config(environment)
        return self._calculate_config_hash(config)
        
    def check_version_compatibility(self, version: str) -> bool:
        """Check if a configuration version is compatible."""
        try:
            # Simple version comparison - in practice, use semantic versioning
            return (version >= self.min_compatible_version and 
                   version <= self.max_compatible_version)
        except Exception:
            return False
            
    def sync_from_external(self, external_config: SystemConfig, 
                          source: str = "external") -> bool:
        """Synchronize configuration from external source."""
        try:
            with self.config_lock:
                # Version compatibility check
                if not self.check_version_compatibility(external_config.version):
                    self.logger.warning(f"Incompatible config version: {external_config.version}")
                    return False
                    
                # Check if config has actually changed
                external_hash = self._calculate_config_hash(external_config)
                current_hash = self.get_config_hash(external_config.environment)
                
                if external_hash == current_hash:
                    return True  # No changes needed
                    
                # Apply external configuration
                self.save_config(external_config, external_config.environment)
                
                # Clear cache to force reload
                if external_config.environment in self.config_cache:
                    del self.config_cache[external_config.environment]
                    
                # Notify sync callbacks
                self._notify_sync_callbacks(external_config)
                
                self.logger.info(f"Configuration synchronized from {source}")
                return True
                
        except Exception as e:
            self.logger.error(f"Failed to sync from external source: {e}")
            return False
            
    def get_change_history(self, limit: Optional[int] = None) -> List[ConfigChangeEvent]:
        """Get configuration change history."""
        if limit is None:
            return list(self.change_history)
        return list(self.change_history[-limit:])
        
    def export_config_for_sync(self, environment: Optional[Environment] = None) -> Dict[str, Any]:
        """Export configuration for cross-version synchronization."""
        if environment is None:
            environment = self.environment
            
        try:
            config = self.load_config(environment)
            config_dict = asdict(config)
            
            # Add sync metadata
            config_dict['sync_metadata'] = {
                'exported_at': datetime.now().isoformat(),
                'config_hash': self._calculate_config_hash(config),
                'version': config.version
            }
            
            return config_dict
            
        except Exception as e:
            self.logger.error(f"Failed to export config for sync: {e}")
            return {}
            
    def import_config_from_sync(self, config_dict: Dict[str, Any], 
                               source: str = "sync") -> bool:
        """Import configuration from synchronization data."""
        try:
            # Remove sync metadata
            sync_metadata = config_dict.pop('sync_metadata', {})
            
            # Convert to SystemConfig
            config = self._dict_to_config(config_dict)
            
            # Apply synchronization
            return self.sync_from_external(config, source)
            
        except Exception as e:
            self.logger.error(f"Failed to import config from sync: {e}")
            return False
            
    # Private synchronization methods
    
    async def _sync_monitoring_loop(self) -> None:
        """Main synchronization monitoring loop."""
        while self.sync_enabled:
            try:
                # Check for configuration changes
                await self._check_config_changes()
                
                # Process pending changes
                await self._process_pending_changes()
                
                # Sleep until next check
                await asyncio.sleep(self.sync_interval)
                
            except Exception as e:
                self.logger.error(f"Sync monitoring error: {e}")
                await asyncio.sleep(self.sync_interval * 2)
                
    async def _check_config_changes(self) -> None:
        """Check for external configuration changes."""
        try:
            current_hash = self.get_config_hash()
            
            if self.last_sync_hash is None:
                self.last_sync_hash = current_hash
                return
                
            if current_hash != self.last_sync_hash:
                # Configuration has changed externally
                self.logger.info("External configuration change detected")
                
                # Reload configuration
                self.reload_config()
                
                # Update sync hash
                self.last_sync_hash = current_hash
                
                # Notify sync callbacks
                if self.current_config:
                    self._notify_sync_callbacks(self.current_config)
                    
        except Exception as e:
            self.logger.error(f"Failed to check config changes: {e}")
            
    async def _process_pending_changes(self) -> None:
        """Process pending configuration changes from sync clients."""
        try:
            with self.config_lock:
                if not self.pending_changes:
                    return
                    
                changes_to_process = list(self.pending_changes.values())
                self.pending_changes.clear()
                
            # Process changes
            for change in changes_to_process:
                try:
                    self.update_config_value(
                        change.section,
                        change.key,
                        change.new_value,
                        change.source
                    )
                except Exception as e:
                    self.logger.error(f"Failed to process pending change: {e}")
                    
        except Exception as e:
            self.logger.error(f"Failed to process pending changes: {e}")
            
    def _calculate_config_hash(self, config: SystemConfig) -> str:
        """Calculate hash of configuration for change detection."""
        try:
            # Convert to dict and remove volatile fields
            config_dict = asdict(config)
            config_dict.pop('updated_at', None)
            config_dict.pop('config_hash', None)
            
            # Calculate hash
            config_json = json.dumps(config_dict, sort_keys=True, default=str)
            return hashlib.sha256(config_json.encode()).hexdigest()[:16]
            
        except Exception as e:
            self.logger.error(f"Failed to calculate config hash: {e}")
            return str(datetime.now().timestamp())[:16]
            
    def _add_change_to_history(self, change: ConfigChangeEvent) -> None:
        """Add a change event to history."""
        self.change_history.append(change)
        
        # Trim history if too large
        if len(self.change_history) > self.max_history_size:
            self.change_history = self.change_history[-self.max_history_size:]
            
    def _notify_change_callbacks(self, change: ConfigChangeEvent) -> None:
        """Notify all change callbacks."""
        for callback in self.change_callbacks:
            try:
                callback(change)
            except Exception as e:
                self.logger.error(f"Change callback error: {e}")
                
    def _notify_sync_callbacks(self, config: SystemConfig) -> None:
        """Notify all sync callbacks."""
        for callback in self.sync_callbacks:
            try:
                callback(config)
            except Exception as e:
                self.logger.error(f"Sync callback error: {e}")
                
    def _load_sync_state(self) -> None:
        """Load synchronization state from file."""
        try:
            if self.sync_state_file.exists():
                with open(self.sync_state_file, 'r') as f:
                    sync_state = json.load(f)
                    
                self.last_sync_hash = sync_state.get('last_sync_hash')
                self.sync_clients = set(sync_state.get('sync_clients', []))
                
                self.logger.debug("Sync state loaded")
                
        except Exception as e:
            self.logger.error(f"Failed to load sync state: {e}")
            
    def _save_sync_state(self) -> None:
        """Save synchronization state to file."""
        try:
            sync_state = {
                'last_sync_hash': self.last_sync_hash,
                'sync_clients': list(self.sync_clients),
                'last_updated': datetime.now().isoformat()
            }
            
            with open(self.sync_state_file, 'w') as f:
                json.dump(sync_state, f, indent=2)
                
        except Exception as e:
            self.logger.error(f"Failed to save sync state: {e}")