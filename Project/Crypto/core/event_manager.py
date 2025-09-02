"""
Event Manager Module

Unified event system for cross-version broadcasting supporting both PyQt5 signals
and WebSocket events for seamless communication between GUI and web interfaces.
"""

from typing import Dict, Any, List, Callable, Optional, Set, Union, Type
from enum import Enum
from dataclasses import dataclass, field
from datetime import datetime
import threading
import asyncio
import json
import weakref
from abc import ABC, abstractmethod
import uuid

from .logger import SystemLogger


class EventPriority(Enum):
    """Event priority levels"""
    LOW = 1
    NORMAL = 2
    HIGH = 3
    CRITICAL = 4


class EventType(Enum):
    """System event types"""
    # System events
    SYSTEM_START = "system.start"
    SYSTEM_STOP = "system.stop"
    SYSTEM_PAUSE = "system.pause"
    SYSTEM_RESUME = "system.resume"
    SYSTEM_ERROR = "system.error"
    
    # Trading events
    TRADE_SIGNAL = "trade.signal"
    POSITION_OPEN = "position.open"
    POSITION_CLOSE = "position.close"
    POSITION_UPDATE = "position.update"
    ORDER_CREATED = "order.created"
    ORDER_FILLED = "order.filled"
    ORDER_CANCELLED = "order.cancelled"
    
    # Market data events
    PRICE_UPDATE = "market.price_update"
    ORDERBOOK_UPDATE = "market.orderbook_update"
    TICKER_UPDATE = "market.ticker_update"
    KLINE_UPDATE = "market.kline_update"
    
    # Configuration events
    CONFIG_CHANGED = "config.changed"
    SETTINGS_UPDATE = "settings.update"
    
    # UI events
    UI_UPDATE = "ui.update"
    STATUS_UPDATE = "status.update"
    NOTIFICATION = "notification"
    
    # Connection events
    CONNECTION_ESTABLISHED = "connection.established"
    CONNECTION_LOST = "connection.lost"
    
    # Risk management events
    RISK_LIMIT_EXCEEDED = "risk.limit_exceeded"
    EMERGENCY_STOP = "risk.emergency_stop"
    
    # Performance events
    PERFORMANCE_UPDATE = "performance.update"
    METRICS_UPDATE = "metrics.update"


@dataclass
class Event:
    """Event data structure"""
    id: str = field(default_factory=lambda: str(uuid.uuid4())[:8])
    type: EventType = EventType.UI_UPDATE
    data: Dict[str, Any] = field(default_factory=dict)
    priority: EventPriority = EventPriority.NORMAL
    source: str = "unknown"
    target: Optional[str] = None  # Specific target, None for broadcast
    timestamp: datetime = field(default_factory=datetime.now)
    processed: bool = False
    
    def to_dict(self) -> Dict[str, Any]:
        """Convert event to dictionary for serialization"""
        return {
            'id': self.id,
            'type': self.type.value,
            'data': self.data,
            'priority': self.priority.value,
            'source': self.source,
            'target': self.target,
            'timestamp': self.timestamp.isoformat(),
            'processed': self.processed
        }
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Event':
        """Create event from dictionary"""
        return cls(
            id=data.get('id', str(uuid.uuid4())[:8]),
            type=EventType(data['type']),
            data=data.get('data', {}),
            priority=EventPriority(data.get('priority', EventPriority.NORMAL.value)),
            source=data.get('source', 'unknown'),
            target=data.get('target'),
            timestamp=datetime.fromisoformat(data['timestamp']),
            processed=data.get('processed', False)
        )


class EventHandler(ABC):
    """Abstract base class for event handlers"""
    
    @abstractmethod
    async def handle_event(self, event: Event) -> bool:
        """Handle an event. Return True if handled successfully."""
        pass
    
    @abstractmethod
    def get_supported_events(self) -> List[EventType]:
        """Return list of supported event types."""
        pass


class PyQt5EventBridge:
    """Bridge for PyQt5 signal integration"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.signal_connections: Dict[EventType, List[Any]] = {}
        
    def connect_signal(self, event_type: EventType, signal: Any) -> None:
        """Connect a PyQt5 signal to an event type"""
        try:
            if event_type not in self.signal_connections:
                self.signal_connections[event_type] = []
            self.signal_connections[event_type].append(signal)
            self.logger.debug(f"Connected PyQt5 signal to {event_type.value}")
        except Exception as e:
            self.logger.error(f"Failed to connect PyQt5 signal: {e}")
    
    def emit_to_qt(self, event: Event) -> None:
        """Emit event to connected PyQt5 signals"""
        try:
            signals = self.signal_connections.get(event.type, [])
            for signal in signals:
                if hasattr(signal, 'emit'):
                    signal.emit(event.data)
        except Exception as e:
            self.logger.error(f"Failed to emit to PyQt5 signals: {e}")


class WebSocketEventBridge:
    """Bridge for WebSocket event broadcasting"""
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.websocket_clients: Set[Any] = set()
        self.namespace_handlers: Dict[str, Callable] = {}
        
    def add_websocket_client(self, client: Any) -> None:
        """Add a WebSocket client for event broadcasting"""
        self.websocket_clients.add(client)
        self.logger.debug(f"Added WebSocket client: {id(client)}")
        
    def remove_websocket_client(self, client: Any) -> None:
        """Remove a WebSocket client"""
        self.websocket_clients.discard(client)
        self.logger.debug(f"Removed WebSocket client: {id(client)}")
        
    def add_namespace_handler(self, namespace: str, handler: Callable) -> None:
        """Add a namespace-specific handler for SocketIO"""
        self.namespace_handlers[namespace] = handler
        
    async def emit_to_websockets(self, event: Event) -> None:
        """Emit event to WebSocket clients"""
        try:
            event_data = event.to_dict()
            
            # Emit to direct WebSocket clients
            if self.websocket_clients:
                for client in list(self.websocket_clients):
                    try:
                        if hasattr(client, 'send'):
                            await client.send(json.dumps(event_data))
                        elif hasattr(client, 'emit'):
                            client.emit(event.type.value, event_data)
                    except Exception as e:
                        self.logger.warning(f"Failed to send to WebSocket client: {e}")
                        self.websocket_clients.discard(client)
            
            # Emit to namespace handlers (e.g., Flask-SocketIO)
            for namespace, handler in self.namespace_handlers.items():
                try:
                    await handler(event.type.value, event_data, namespace)
                except Exception as e:
                    self.logger.error(f"Failed to emit to namespace {namespace}: {e}")
                    
        except Exception as e:
            self.logger.error(f"Failed to emit to WebSockets: {e}")


class EventManager:
    """
    Unified event manager supporting both PyQt5 signals and WebSocket events.
    
    Features:
    - Event queuing with priority handling
    - Cross-version event broadcasting
    - Handler registration and management
    - Event filtering and routing
    - Performance monitoring
    """
    
    def __init__(self, logger: SystemLogger):
        self.logger = logger
        self.instance_id = str(uuid.uuid4())[:8]
        
        # Event handling
        self.handlers: Dict[EventType, List[EventHandler]] = {}
        self.callback_handlers: Dict[EventType, List[Callable]] = {}
        self.event_queue: List[Event] = []
        self.queue_lock = threading.RLock()
        
        # Bridges
        self.qt_bridge = PyQt5EventBridge(logger)
        self.websocket_bridge = WebSocketEventBridge(logger)
        
        # Processing
        self.processing = False
        self.process_task: Optional[asyncio.Task] = None
        self.batch_size = 50
        self.max_queue_size = 1000
        
        # Statistics
        self.events_processed = 0
        self.events_failed = 0
        self.events_dropped = 0
        self.last_process_time = datetime.now()
        
        # Filters
        self.event_filters: List[Callable[[Event], bool]] = []
        self.blocked_sources: Set[str] = set()
        self.blocked_types: Set[EventType] = set()
        
        self.logger.info(f"EventManager initialized (ID: {self.instance_id})")
        
    async def start(self) -> bool:
        """Start the event manager."""
        try:
            if self.processing:
                return True
                
            self.processing = True
            self.process_task = asyncio.create_task(self._process_events())
            
            self.logger.info("EventManager started")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to start EventManager: {e}")
            return False
            
    async def stop(self) -> bool:
        """Stop the event manager."""
        try:
            self.processing = False
            
            if self.process_task:
                self.process_task.cancel()
                try:
                    await self.process_task
                except asyncio.CancelledError:
                    pass
                    
            # Process remaining events
            await self._process_remaining_events()
            
            self.logger.info("EventManager stopped")
            return True
            
        except Exception as e:
            self.logger.error(f"Failed to stop EventManager: {e}")
            return False
            
    def emit_event(self, event_type: EventType, data: Dict[str, Any],
                   priority: EventPriority = EventPriority.NORMAL,
                   source: str = "unknown", target: Optional[str] = None) -> str:
        """Emit an event."""
        event = Event(
            type=event_type,
            data=data,
            priority=priority,
            source=source,
            target=target
        )
        
        return self._queue_event(event)
        
    def emit_event_sync(self, event_type: EventType, data: Dict[str, Any],
                       priority: EventPriority = EventPriority.CRITICAL,
                       source: str = "unknown", target: Optional[str] = None) -> bool:
        """Emit and immediately process a high-priority event synchronously."""
        event = Event(
            type=event_type,
            data=data,
            priority=priority,
            source=source,
            target=target
        )
        
        # Process immediately for critical events
        if priority == EventPriority.CRITICAL:
            try:
                asyncio.create_task(self._process_single_event(event))
                return True
            except Exception as e:
                self.logger.error(f"Failed to process sync event: {e}")
                return False
        else:
            # Queue for normal processing
            self._queue_event(event)
            return True
            
    def register_handler(self, event_type: EventType, handler: EventHandler) -> None:
        """Register an event handler."""
        if event_type not in self.handlers:
            self.handlers[event_type] = []
        self.handlers[event_type].append(handler)
        self.logger.debug(f"Registered handler for {event_type.value}")
        
    def register_callback(self, event_type: EventType, callback: Callable[[Event], None]) -> None:
        """Register a callback function for an event type."""
        if event_type not in self.callback_handlers:
            self.callback_handlers[event_type] = []
        self.callback_handlers[event_type].append(callback)
        self.logger.debug(f"Registered callback for {event_type.value}")
        
    def unregister_handler(self, event_type: EventType, handler: EventHandler) -> None:
        """Unregister an event handler."""
        if event_type in self.handlers:
            try:
                self.handlers[event_type].remove(handler)
                if not self.handlers[event_type]:
                    del self.handlers[event_type]
            except ValueError:
                pass
                
    def unregister_callback(self, event_type: EventType, callback: Callable) -> None:
        """Unregister a callback function."""
        if event_type in self.callback_handlers:
            try:
                self.callback_handlers[event_type].remove(callback)
                if not self.callback_handlers[event_type]:
                    del self.callback_handlers[event_type]
            except ValueError:
                pass
                
    # PyQt5 Integration
    def connect_qt_signal(self, event_type: EventType, signal: Any) -> None:
        """Connect a PyQt5 signal to receive events."""
        self.qt_bridge.connect_signal(event_type, signal)
        
    # WebSocket Integration
    def add_websocket_client(self, client: Any) -> None:
        """Add a WebSocket client for event broadcasting."""
        self.websocket_bridge.add_websocket_client(client)
        
    def remove_websocket_client(self, client: Any) -> None:
        """Remove a WebSocket client."""
        self.websocket_bridge.remove_websocket_client(client)
        
    def add_socketio_handler(self, namespace: str, socketio_instance: Any) -> None:
        """Add Flask-SocketIO handler."""
        async def emit_handler(event_type: str, data: Dict[str, Any], ns: str):
            socketio_instance.emit(event_type, data, namespace=ns)
            
        self.websocket_bridge.add_namespace_handler(namespace, emit_handler)
        
    # Event Filtering
    def add_event_filter(self, filter_func: Callable[[Event], bool]) -> None:
        """Add an event filter function. Return True to allow, False to block."""
        self.event_filters.append(filter_func)
        
    def block_source(self, source: str) -> None:
        """Block events from a specific source."""
        self.blocked_sources.add(source)
        
    def unblock_source(self, source: str) -> None:
        """Unblock events from a source."""
        self.blocked_sources.discard(source)
        
    def block_event_type(self, event_type: EventType) -> None:
        """Block a specific event type."""
        self.blocked_types.add(event_type)
        
    def unblock_event_type(self, event_type: EventType) -> None:
        """Unblock an event type."""
        self.blocked_types.discard(event_type)
        
    # Statistics and Status
    def get_status(self) -> Dict[str, Any]:
        """Get event manager status and statistics."""
        with self.queue_lock:
            queue_size = len(self.event_queue)
            
        return {
            'instance_id': self.instance_id,
            'processing': self.processing,
            'queue_size': queue_size,
            'max_queue_size': self.max_queue_size,
            'events_processed': self.events_processed,
            'events_failed': self.events_failed,
            'events_dropped': self.events_dropped,
            'last_process_time': self.last_process_time.isoformat(),
            'handlers_count': sum(len(handlers) for handlers in self.handlers.values()),
            'callbacks_count': sum(len(callbacks) for callbacks in self.callback_handlers.values()),
            'websocket_clients': len(self.websocket_bridge.websocket_clients),
            'qt_connections': sum(len(signals) for signals in self.qt_bridge.signal_connections.values())
        }
        
    def _queue_event(self, event: Event) -> str:
        """Queue an event for processing."""
        try:
            # Apply filters
            if not self._should_process_event(event):
                self.events_dropped += 1
                return event.id
                
            with self.queue_lock:
                # Check queue size limit
                if len(self.event_queue) >= self.max_queue_size:
                    # Remove oldest low-priority event
                    for i, queued_event in enumerate(self.event_queue):
                        if queued_event.priority == EventPriority.LOW:
                            self.event_queue.pop(i)
                            self.events_dropped += 1
                            break
                    else:
                        # If no low-priority events, drop the new one
                        if event.priority == EventPriority.LOW:
                            self.events_dropped += 1
                            return event.id
                            
                # Insert event based on priority
                inserted = False
                for i, queued_event in enumerate(self.event_queue):
                    if event.priority.value > queued_event.priority.value:
                        self.event_queue.insert(i, event)
                        inserted = True
                        break
                        
                if not inserted:
                    self.event_queue.append(event)
                    
            return event.id
            
        except Exception as e:
            self.logger.error(f"Failed to queue event: {e}")
            return event.id
            
    def _should_process_event(self, event: Event) -> bool:
        """Check if an event should be processed."""
        # Check blocked sources
        if event.source in self.blocked_sources:
            return False
            
        # Check blocked types
        if event.type in self.blocked_types:
            return False
            
        # Apply custom filters
        for filter_func in self.event_filters:
            try:
                if not filter_func(event):
                    return False
            except Exception as e:
                self.logger.warning(f"Event filter error: {e}")
                
        return True
        
    async def _process_events(self) -> None:
        """Main event processing loop."""
        while self.processing:
            try:
                # Get batch of events
                events_to_process = []
                with self.queue_lock:
                    if self.event_queue:
                        batch_size = min(self.batch_size, len(self.event_queue))
                        events_to_process = self.event_queue[:batch_size]
                        self.event_queue = self.event_queue[batch_size:]
                        
                # Process events
                if events_to_process:
                    for event in events_to_process:
                        await self._process_single_event(event)
                        
                    self.last_process_time = datetime.now()
                else:
                    # No events, sleep briefly
                    await asyncio.sleep(0.1)
                    
            except Exception as e:
                self.logger.error(f"Event processing error: {e}")
                await asyncio.sleep(1)
                
    async def _process_single_event(self, event: Event) -> bool:
        """Process a single event."""
        try:
            success = True
            
            # Process with registered handlers
            handlers = self.handlers.get(event.type, [])
            for handler in handlers:
                try:
                    result = await handler.handle_event(event)
                    if not result:
                        success = False
                except Exception as e:
                    self.logger.error(f"Handler error for {event.type.value}: {e}")
                    success = False
                    
            # Process with callback handlers
            callbacks = self.callback_handlers.get(event.type, [])
            for callback in callbacks:
                try:
                    if asyncio.iscoroutinefunction(callback):
                        await callback(event)
                    else:
                        callback(event)
                except Exception as e:
                    self.logger.error(f"Callback error for {event.type.value}: {e}")
                    success = False
                    
            # Emit to PyQt5 signals
            self.qt_bridge.emit_to_qt(event)
            
            # Emit to WebSocket clients
            await self.websocket_bridge.emit_to_websockets(event)
            
            # Update statistics
            if success:
                self.events_processed += 1
            else:
                self.events_failed += 1
                
            event.processed = True
            return success
            
        except Exception as e:
            self.logger.error(f"Event processing error: {e}")
            self.events_failed += 1
            return False
            
    async def _process_remaining_events(self) -> None:
        """Process any remaining events during shutdown."""
        try:
            with self.queue_lock:
                remaining_events = list(self.event_queue)
                self.event_queue.clear()
                
            for event in remaining_events:
                await self._process_single_event(event)
                
        except Exception as e:
            self.logger.error(f"Failed to process remaining events: {e}")


# Global event manager instance
_event_manager_instance: Optional[EventManager] = None
_instance_lock = threading.Lock()


def get_event_manager() -> Optional[EventManager]:
    """Get the global event manager instance."""
    return _event_manager_instance


def initialize_event_manager(logger: SystemLogger) -> EventManager:
    """Initialize the global event manager instance."""
    global _event_manager_instance
    
    with _instance_lock:
        if _event_manager_instance is None:
            _event_manager_instance = EventManager(logger)
        return _event_manager_instance


def shutdown_event_manager() -> None:
    """Shutdown the global event manager instance."""
    global _event_manager_instance
    
    with _instance_lock:
        if _event_manager_instance is not None:
            asyncio.create_task(_event_manager_instance.stop())
            _event_manager_instance = None