"""
Dual Version Bridge Utilities

Interface bridge between EXE (desktop GUI) and web dashboard versions,
providing setting format conversion, state synchronization, and 
cross-version communication protocols.
"""

from typing import Dict, Any, Optional, List, Union, Callable, Type
from enum import Enum
from dataclasses import dataclass, asdict
from datetime import datetime
import json
import threading
import asyncio
import base64
from pathlib import Path
import hashlib

from core.logger import SystemLogger
from core.config_manager import SystemConfig, Environment, ConfigChangeEvent
from core.system_manager import SystemManager, SystemState, ComponentState
from core.event_manager import EventManager, Event, EventType


class InterfaceType(Enum):
    """Interface types for version identification"""
    GUI = "gui"
    WEB = "web"
    CLI = "cli"
    SERVICE = "service"


@dataclass
class VersionInfo:
    """Version information for compatibility checking"""
    interface_type: InterfaceType
    version: str
    protocol_version: str = "1.0"
    capabilities: List[str] = None
    last_seen: datetime = None
    
    def __post_init__(self):
        if self.capabilities is None:
            self.capabilities = []
        if self.last_seen is None:
            self.last_seen = datetime.now()


@dataclass
class SyncState:
    """Synchronization state between versions"""
    gui_connected: bool = False
    web_connected: bool = False
    last_sync: Optional[datetime] = None
    sync_errors: int = 0
    pending_updates: List[str] = None
    
    def __post_init__(self):
        if self.pending_updates is None:
            self.pending_updates = []


@dataclass
class BridgeMessage:
    """Message format for cross-version communication"""
    id: str
    source: InterfaceType
    target: Optional[InterfaceType]
    message_type: str
    payload: Dict[str, Any]
    timestamp: datetime
    priority: int = 1  # 1=low, 2=normal, 3=high, 4=critical
    
    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary for serialization"""
        return {
            'id': self.id,
            'source': self.source.value,
            'target': self.target.value if self.target else None,
            'message_type': self.message_type,
            'payload': self.payload,
            'timestamp': self.timestamp.isoformat(),
            'priority': self.priority
        }
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'BridgeMessage':
        """Create from dictionary"""
        return cls(
            id=data['id'],
            source=InterfaceType(data['source']),
            target=InterfaceType(data['target']) if data['target'] else None,
            message_type=data['message_type'],
            payload=data['payload'],
            timestamp=datetime.fromisoformat(data['timestamp']),
            priority=data.get('priority', 1)
        )


class SettingsConverter:
    """Utility class for converting settings between GUI and web formats"""
    
    @staticmethod
    def gui_to_web_format(gui_settings: Dict[str, Any]) -> Dict[str, Any]:
        """Convert GUI settings format to web format"""
        try:
            # GUI typically uses more structured format, web uses flattened
            web_settings = {}
            
            # Entry conditions conversion
            if 'entry_conditions' in gui_settings:
                entry = gui_settings['entry_conditions']
                web_settings['entry_conditions'] = {
                    'moving_average': {
                        'enabled': entry.get('ma_enabled', False),
                        'period': entry.get('ma_period', 20),
                        'type': entry.get('ma_type', 'SMA')
                    },
                    'price_channel': {
                        'enabled': entry.get('pc_enabled', False),
                        'period': entry.get('pc_period', 20),
                        'threshold': entry.get('pc_threshold', 0.1)
                    },
                    'order_flow': {
                        'enabled': entry.get('orderflow_enabled', False),
                        'threshold': entry.get('orderflow_threshold', 1000)
                    },
                    'tick_based': {
                        'enabled': entry.get('tick_enabled', False),
                        'min_ticks': entry.get('min_ticks', 3)
                    },
                    'candle_pattern': {
                        'enabled': entry.get('pattern_enabled', False),
                        'patterns': entry.get('patterns', ['hammer', 'doji'])
                    }
                }
            
            # Exit conditions conversion
            if 'exit_conditions' in gui_settings:
                exit_cond = gui_settings['exit_conditions']
                web_settings['exit_conditions'] = {
                    'pcs_exit': {
                        'enabled': exit_cond.get('pcs_enabled', True),
                        'levels': exit_cond.get('pcs_levels', [0.5, 1.0, 1.5])
                    },
                    'pc_trailing': {
                        'enabled': exit_cond.get('trailing_enabled', True),
                        'distance': exit_cond.get('trailing_distance', 0.2)
                    },
                    'order_exit': {
                        'enabled': exit_cond.get('order_exit_enabled', False),
                        'profit_target': exit_cond.get('profit_target', 2.0)
                    },
                    'pc_breakeven': {
                        'enabled': exit_cond.get('breakeven_enabled', True),
                        'threshold': exit_cond.get('breakeven_threshold', 0.5)
                    }
                }
            
            # System settings conversion
            if 'system_settings' in gui_settings:
                system = gui_settings['system_settings']
                web_settings['system_settings'] = {
                    'exchange': system.get('exchange', 'binance'),
                    'symbols': system.get('trading_pairs', ['BTCUSDT', 'ETHUSDT']),
                    'leverage': system.get('leverage', 10),
                    'position_size': system.get('position_size_pct', 0.1),
                    'max_positions': system.get('max_positions', 2),
                    'time_control': {
                        'enabled': system.get('time_control_enabled', True),
                        'start': system.get('trading_start', '09:00'),
                        'end': system.get('trading_end', '17:00')
                    },
                    'risk_management': {
                        'max_daily_loss': system.get('max_daily_loss', 5.0),
                        'max_drawdown': system.get('max_drawdown', 10.0),
                        'stop_loss': system.get('stop_loss', 2.0)
                    }
                }
            
            return web_settings
            
        except Exception as e:
            raise ValueError(f"Failed to convert GUI to web format: {e}")
    
    @staticmethod
    def web_to_gui_format(web_settings: Dict[str, Any]) -> Dict[str, Any]:
        """Convert web settings format to GUI format"""
        try:
            gui_settings = {}
            
            # Entry conditions conversion
            if 'entry_conditions' in web_settings:
                entry = web_settings['entry_conditions']
                gui_settings['entry_conditions'] = {
                    'ma_enabled': entry.get('moving_average', {}).get('enabled', False),
                    'ma_period': entry.get('moving_average', {}).get('period', 20),
                    'ma_type': entry.get('moving_average', {}).get('type', 'SMA'),
                    'pc_enabled': entry.get('price_channel', {}).get('enabled', False),
                    'pc_period': entry.get('price_channel', {}).get('period', 20),
                    'pc_threshold': entry.get('price_channel', {}).get('threshold', 0.1),
                    'orderflow_enabled': entry.get('order_flow', {}).get('enabled', False),
                    'orderflow_threshold': entry.get('order_flow', {}).get('threshold', 1000),
                    'tick_enabled': entry.get('tick_based', {}).get('enabled', False),
                    'min_ticks': entry.get('tick_based', {}).get('min_ticks', 3),
                    'pattern_enabled': entry.get('candle_pattern', {}).get('enabled', False),
                    'patterns': entry.get('candle_pattern', {}).get('patterns', ['hammer', 'doji'])
                }
            
            # Exit conditions conversion
            if 'exit_conditions' in web_settings:
                exit_cond = web_settings['exit_conditions']
                gui_settings['exit_conditions'] = {
                    'pcs_enabled': exit_cond.get('pcs_exit', {}).get('enabled', True),
                    'pcs_levels': exit_cond.get('pcs_exit', {}).get('levels', [0.5, 1.0, 1.5]),
                    'trailing_enabled': exit_cond.get('pc_trailing', {}).get('enabled', True),
                    'trailing_distance': exit_cond.get('pc_trailing', {}).get('distance', 0.2),
                    'order_exit_enabled': exit_cond.get('order_exit', {}).get('enabled', False),
                    'profit_target': exit_cond.get('order_exit', {}).get('profit_target', 2.0),
                    'breakeven_enabled': exit_cond.get('pc_breakeven', {}).get('enabled', True),
                    'breakeven_threshold': exit_cond.get('pc_breakeven', {}).get('threshold', 0.5)
                }
            
            # System settings conversion
            if 'system_settings' in web_settings:
                system = web_settings['system_settings']
                gui_settings['system_settings'] = {
                    'exchange': system.get('exchange', 'binance'),
                    'trading_pairs': system.get('symbols', ['BTCUSDT', 'ETHUSDT']),
                    'leverage': system.get('leverage', 10),
                    'position_size_pct': system.get('position_size', 0.1),
                    'max_positions': system.get('max_positions', 2),
                    'time_control_enabled': system.get('time_control', {}).get('enabled', True),
                    'trading_start': system.get('time_control', {}).get('start', '09:00'),
                    'trading_end': system.get('time_control', {}).get('end', '17:00'),
                    'max_daily_loss': system.get('risk_management', {}).get('max_daily_loss', 5.0),
                    'max_drawdown': system.get('risk_management', {}).get('max_drawdown', 10.0),
                    'stop_loss': system.get('risk_management', {}).get('stop_loss', 2.0)
                }
            
            return gui_settings
            
        except Exception as e:
            raise ValueError(f"Failed to convert web to GUI format: {e}")
    
    @staticmethod
    def validate_settings_compatibility(gui_settings: Dict[str, Any], 
                                      web_settings: Dict[str, Any]) -> List[str]:
        """Validate compatibility between GUI and web settings"""
        issues = []
        
        try:
            # Convert both to a common format for comparison
            gui_normalized = SettingsConverter.gui_to_web_format(gui_settings)
            
            # Check entry conditions compatibility
            if 'entry_conditions' in gui_normalized and 'entry_conditions' in web_settings:
                gui_entry = gui_normalized['entry_conditions']
                web_entry = web_settings['entry_conditions']
                
                for condition in ['moving_average', 'price_channel', 'order_flow']:
                    if (gui_entry.get(condition, {}).get('enabled', False) != 
                        web_entry.get(condition, {}).get('enabled', False)):
                        issues.append(f"Entry condition '{condition}' enabled state mismatch")
                        
            # Check system settings compatibility
            if 'system_settings' in gui_normalized and 'system_settings' in web_settings:
                gui_system = gui_normalized['system_settings']
                web_system = web_settings['system_settings']
                
                critical_fields = ['exchange', 'leverage', 'max_positions']
                for field in critical_fields:
                    if gui_system.get(field) != web_system.get(field):
                        issues.append(f"System setting '{field}' value mismatch")
                        
        except Exception as e:
            issues.append(f"Settings validation error: {e}")
            
        return issues


class StateSync:
    """Synchronization utilities for state management between versions"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.sync_lock = threading.RLock()
        self.version_states: Dict[InterfaceType, Dict[str, Any]] = {}
        self.sync_callbacks: List[Callable[[InterfaceType, Dict[str, Any]], None]] = []
        
    def register_version_state(self, interface_type: InterfaceType, 
                             state: Dict[str, Any]) -> None:
        """Register state for a specific version interface"""
        with self.sync_lock:
            self.version_states[interface_type] = state.copy()
            
        self.logger.debug(f"State registered for {interface_type.value}")
        
        # Notify callbacks
        self._notify_sync_callbacks(interface_type, state)
    
    def get_version_state(self, interface_type: InterfaceType) -> Dict[str, Any]:
        """Get state for a specific version interface"""
        with self.sync_lock:
            return self.version_states.get(interface_type, {}).copy()
    
    def sync_states(self, source: InterfaceType, 
                   target: InterfaceType) -> bool:
        """Synchronize state from source to target"""
        try:
            with self.sync_lock:
                if source not in self.version_states:
                    self.logger.warning(f"Source state not found: {source.value}")
                    return False
                    
                source_state = self.version_states[source].copy()
                
                # Apply state to target
                self.version_states[target] = source_state
                
            self.logger.info(f"State synced from {source.value} to {target.value}")
            
            # Notify callbacks
            self._notify_sync_callbacks(target, source_state)
            
            return True
            
        except Exception as e:
            self.logger.error(f"State sync error: {e}")
            return False
    
    def get_sync_differences(self, interface1: InterfaceType, 
                           interface2: InterfaceType) -> Dict[str, Any]:
        """Get differences between two interface states"""
        try:
            with self.sync_lock:
                state1 = self.version_states.get(interface1, {})
                state2 = self.version_states.get(interface2, {})
                
            return self._calculate_state_diff(state1, state2)
            
        except Exception as e:
            self.logger.error(f"Failed to calculate state differences: {e}")
            return {}
    
    def add_sync_callback(self, callback: Callable[[InterfaceType, Dict[str, Any]], None]) -> None:
        """Add a callback for state synchronization events"""
        self.sync_callbacks.append(callback)
    
    def remove_sync_callback(self, callback: Callable[[InterfaceType, Dict[str, Any]], None]) -> None:
        """Remove a sync callback"""
        try:
            self.sync_callbacks.remove(callback)
        except ValueError:
            pass
    
    def _notify_sync_callbacks(self, interface_type: InterfaceType, 
                             state: Dict[str, Any]) -> None:
        """Notify all sync callbacks"""
        for callback in self.sync_callbacks:
            try:
                callback(interface_type, state)
            except Exception as e:
                self.logger.error(f"Sync callback error: {e}")
    
    def _calculate_state_diff(self, state1: Dict[str, Any], 
                            state2: Dict[str, Any]) -> Dict[str, Any]:
        """Calculate differences between two states"""
        differences = {}
        
        # Find keys in state1 but not in state2
        for key in state1:
            if key not in state2:
                differences[f"+{key}"] = state1[key]
            elif state1[key] != state2[key]:
                differences[f"~{key}"] = {"old": state2[key], "new": state1[key]}
        
        # Find keys in state2 but not in state1
        for key in state2:
            if key not in state1:
                differences[f"-{key}"] = state2[key]
        
        return differences


class DualVersionBridge:
    """
    Main bridge class for dual-version integration.
    
    Coordinates communication between GUI and web versions,
    handles setting synchronization, and manages cross-version state.
    """
    
    def __init__(self, logger: SystemLogger, 
                 system_manager: Optional[SystemManager] = None,
                 event_manager: Optional[EventManager] = None):
        """Initialize the dual version bridge."""
        self.logger = logger
        self.system_manager = system_manager
        self.event_manager = event_manager
        
        # Bridge state
        self.active_versions: Dict[InterfaceType, VersionInfo] = {}
        self.sync_state = SyncState()
        self.message_queue: List[BridgeMessage] = []
        self.queue_lock = threading.RLock()
        
        # Utilities
        self.settings_converter = SettingsConverter()
        self.state_sync = StateSync(logger)
        
        # Communication
        self.message_handlers: Dict[str, Callable] = {}
        self.bridge_callbacks: List[Callable] = []
        
        # Performance optimization
        self.cache_enabled = True
        self.settings_cache: Dict[str, Any] = {}
        self.cache_timeout = 30  # seconds
        self.cache_timestamps: Dict[str, datetime] = {}
        
        # Initialize message handlers
        self._setup_message_handlers()
        
        self.logger.info("Dual version bridge initialized")
    
    def register_version(self, interface_type: InterfaceType, 
                        version: str, capabilities: List[str] = None) -> bool:
        """Register a version interface"""
        try:
            version_info = VersionInfo(
                interface_type=interface_type,
                version=version,
                capabilities=capabilities or [],
                last_seen=datetime.now()
            )
            
            self.active_versions[interface_type] = version_info
            
            # Update sync state
            if interface_type == InterfaceType.GUI:
                self.sync_state.gui_connected = True
            elif interface_type == InterfaceType.WEB:
                self.sync_state.web_connected = True
                
            self.logger.info(f"Version registered: {interface_type.value} v{version}")
            
            # Emit event if event manager available
            if self.event_manager:
                self.event_manager.emit_event(
                    EventType.CONNECTION_ESTABLISHED,
                    {
                        'interface_type': interface_type.value,
                        'version': version,
                        'capabilities': capabilities or []
                    },
                    source="dual_version_bridge"
                )
            
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to register version {interface_type.value}: {e}")
            return False
    
    def unregister_version(self, interface_type: InterfaceType) -> bool:
        """Unregister a version interface"""
        try:
            if interface_type in self.active_versions:
                del self.active_versions[interface_type]
                
                # Update sync state
                if interface_type == InterfaceType.GUI:
                    self.sync_state.gui_connected = False
                elif interface_type == InterfaceType.WEB:
                    self.sync_state.web_connected = False
                    
                self.logger.info(f"Version unregistered: {interface_type.value}")
                
                # Emit event if event manager available
                if self.event_manager:
                    self.event_manager.emit_event(
                        EventType.CONNECTION_LOST,
                        {'interface_type': interface_type.value},
                        source="dual_version_bridge"
                    )
                
                return True
            
            return False
            
        except Exception as e:
            self.logger.error(f"Failed to unregister version {interface_type.value}: {e}")
            return False
    
    def sync_settings(self, source: InterfaceType, target: InterfaceType,
                     settings: Dict[str, Any]) -> bool:
        """Synchronize settings between versions"""
        try:
            # Convert settings format if needed
            if source == InterfaceType.GUI and target == InterfaceType.WEB:
                converted_settings = self.settings_converter.gui_to_web_format(settings)
            elif source == InterfaceType.WEB and target == InterfaceType.GUI:
                converted_settings = self.settings_converter.web_to_gui_format(settings)
            else:
                converted_settings = settings.copy()
            
            # Cache settings
            if self.cache_enabled:
                cache_key = f"{target.value}_settings"
                self.settings_cache[cache_key] = converted_settings
                self.cache_timestamps[cache_key] = datetime.now()
            
            # Send message to target
            message = BridgeMessage(
                id=f"sync_{datetime.now().timestamp()}",
                source=source,
                target=target,
                message_type="settings_sync",
                payload={
                    'settings': converted_settings,
                    'sync_timestamp': datetime.now().isoformat()
                },
                timestamp=datetime.now(),
                priority=3  # High priority
            )
            
            success = self._send_message(message)
            
            if success:
                self.sync_state.last_sync = datetime.now()
                self.logger.info(f"Settings synced from {source.value} to {target.value}")
            
            return success
            
        except Exception as e:
            self.logger.error(f"Settings sync failed: {e}")
            self.sync_state.sync_errors += 1
            return False
    
    def get_cached_settings(self, interface_type: InterfaceType) -> Optional[Dict[str, Any]]:
        """Get cached settings for an interface type"""
        if not self.cache_enabled:
            return None
            
        cache_key = f"{interface_type.value}_settings"
        
        if cache_key in self.settings_cache:
            # Check cache validity
            cache_time = self.cache_timestamps.get(cache_key)
            if cache_time and (datetime.now() - cache_time).seconds < self.cache_timeout:
                return self.settings_cache[cache_key].copy()
            else:
                # Remove expired cache
                del self.settings_cache[cache_key]
                if cache_key in self.cache_timestamps:
                    del self.cache_timestamps[cache_key]
        
        return None
    
    def broadcast_system_event(self, event_type: str, data: Dict[str, Any]) -> bool:
        """Broadcast system event to all connected versions"""
        try:
            message = BridgeMessage(
                id=f"broadcast_{datetime.now().timestamp()}",
                source=InterfaceType.SERVICE,
                target=None,  # Broadcast to all
                message_type="system_event",
                payload={
                    'event_type': event_type,
                    'data': data
                },
                timestamp=datetime.now(),
                priority=2
            )
            
            return self._send_message(message)
            
        except Exception as e:
            self.logger.error(f"Failed to broadcast system event: {e}")
            return False
    
    def get_bridge_status(self) -> Dict[str, Any]:
        """Get comprehensive bridge status"""
        return {
            'active_versions': {
                version_type.value: {
                    'version': info.version,
                    'capabilities': info.capabilities,
                    'last_seen': info.last_seen.isoformat()
                }
                for version_type, info in self.active_versions.items()
            },
            'sync_state': {
                'gui_connected': self.sync_state.gui_connected,
                'web_connected': self.sync_state.web_connected,
                'last_sync': self.sync_state.last_sync.isoformat() if self.sync_state.last_sync else None,
                'sync_errors': self.sync_state.sync_errors,
                'pending_updates': len(self.sync_state.pending_updates)
            },
            'message_queue_size': len(self.message_queue),
            'cache_size': len(self.settings_cache),
            'performance': {
                'cache_enabled': self.cache_enabled,
                'cache_timeout': self.cache_timeout
            }
        }
    
    def validate_cross_version_compatibility(self) -> List[str]:
        """Validate compatibility between active versions"""
        issues = []
        
        try:
            # Check if both GUI and web are active
            if InterfaceType.GUI in self.active_versions and InterfaceType.WEB in self.active_versions:
                gui_info = self.active_versions[InterfaceType.GUI]
                web_info = self.active_versions[InterfaceType.WEB]
                
                # Check version compatibility
                if gui_info.version != web_info.version:
                    issues.append(f"Version mismatch: GUI v{gui_info.version} vs Web v{web_info.version}")
                
                # Check capability compatibility
                gui_caps = set(gui_info.capabilities)
                web_caps = set(web_info.capabilities)
                
                missing_in_web = gui_caps - web_caps
                missing_in_gui = web_caps - gui_caps
                
                if missing_in_web:
                    issues.append(f"Web missing capabilities: {list(missing_in_web)}")
                if missing_in_gui:
                    issues.append(f"GUI missing capabilities: {list(missing_in_gui)}")
            
            # Check settings compatibility if both have cached settings
            gui_settings = self.get_cached_settings(InterfaceType.GUI)
            web_settings = self.get_cached_settings(InterfaceType.WEB)
            
            if gui_settings and web_settings:
                compatibility_issues = self.settings_converter.validate_settings_compatibility(
                    gui_settings, web_settings
                )
                issues.extend(compatibility_issues)
            
        except Exception as e:
            issues.append(f"Compatibility check error: {e}")
        
        return issues
    
    # Private methods
    
    def _setup_message_handlers(self) -> None:
        """Setup message handlers for different message types"""
        self.message_handlers = {
            'settings_sync': self._handle_settings_sync,
            'system_event': self._handle_system_event,
            'status_request': self._handle_status_request,
            'ping': self._handle_ping
        }
    
    def _send_message(self, message: BridgeMessage) -> bool:
        """Send a message through the bridge"""
        try:
            with self.queue_lock:
                self.message_queue.append(message)
            
            # Process message immediately for high priority
            if message.priority >= 3:
                return self._process_message(message)
            
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to send message: {e}")
            return False
    
    def _process_message(self, message: BridgeMessage) -> bool:
        """Process a bridge message"""
        try:
            handler = self.message_handlers.get(message.message_type)
            if handler:
                return handler(message)
            else:
                self.logger.warning(f"No handler for message type: {message.message_type}")
                return False
                
        except Exception as e:
            self.logger.error(f"Message processing error: {e}")
            return False
    
    def _handle_settings_sync(self, message: BridgeMessage) -> bool:
        """Handle settings synchronization message"""
        try:
            settings = message.payload.get('settings', {})
            
            # Apply settings based on target
            if message.target:
                self.state_sync.register_version_state(message.target, settings)
                
            return True
            
        except Exception as e:
            self.logger.error(f"Settings sync handling error: {e}")
            return False
    
    def _handle_system_event(self, message: BridgeMessage) -> bool:
        """Handle system event message"""
        try:
            event_type = message.payload.get('event_type')
            event_data = message.payload.get('data', {})
            
            # Emit through event manager if available
            if self.event_manager:
                self.event_manager.emit_event(
                    EventType(event_type) if event_type in EventType._value2member_map_ else EventType.UI_UPDATE,
                    event_data,
                    source="dual_version_bridge"
                )
            
            return True
            
        except Exception as e:
            self.logger.error(f"System event handling error: {e}")
            return False
    
    def _handle_status_request(self, message: BridgeMessage) -> bool:
        """Handle status request message"""
        try:
            status = self.get_bridge_status()
            
            # Send response (this would typically go through a communication channel)
            response = BridgeMessage(
                id=f"status_response_{datetime.now().timestamp()}",
                source=InterfaceType.SERVICE,
                target=message.source,
                message_type="status_response",
                payload={'status': status},
                timestamp=datetime.now()
            )
            
            return self._send_message(response)
            
        except Exception as e:
            self.logger.error(f"Status request handling error: {e}")
            return False
    
    def _handle_ping(self, message: BridgeMessage) -> bool:
        """Handle ping message"""
        try:
            # Update last seen for source version
            if message.source in self.active_versions:
                self.active_versions[message.source].last_seen = datetime.now()
            
            # Send pong response
            pong = BridgeMessage(
                id=f"pong_{datetime.now().timestamp()}",
                source=InterfaceType.SERVICE,
                target=message.source,
                message_type="pong",
                payload={'timestamp': datetime.now().isoformat()},
                timestamp=datetime.now()
            )
            
            return self._send_message(pong)
            
        except Exception as e:
            self.logger.error(f"Ping handling error: {e}")
            return False


# Global bridge instance
_bridge_instance: Optional[DualVersionBridge] = None
_bridge_lock = threading.Lock()


def get_dual_version_bridge() -> Optional[DualVersionBridge]:
    """Get the global dual version bridge instance."""
    return _bridge_instance


def initialize_dual_version_bridge(logger: SystemLogger,
                                 system_manager: Optional[SystemManager] = None,
                                 event_manager: Optional[EventManager] = None) -> DualVersionBridge:
    """Initialize the global dual version bridge instance."""
    global _bridge_instance
    
    with _bridge_lock:
        if _bridge_instance is None:
            _bridge_instance = DualVersionBridge(logger, system_manager, event_manager)
        return _bridge_instance


def shutdown_dual_version_bridge() -> None:
    """Shutdown the global dual version bridge instance."""
    global _bridge_instance
    
    with _bridge_lock:
        _bridge_instance = None