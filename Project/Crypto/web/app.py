"""
Flask Web Dashboard for Crypto Trading System
Enhanced implementation with unified system integration, real-time synchronization,
and seamless cross-version compatibility.
"""

from flask import Flask, render_template, request, jsonify, redirect, url_for, session
from flask_socketio import SocketIO, emit, disconnect
from flask_jwt_extended import JWTManager, create_access_token, jwt_required, get_jwt_identity
from werkzeug.security import check_password_hash, generate_password_hash
from datetime import datetime, timedelta
import json
import os
import sqlite3
import redis
import threading
import time
import logging
from typing import Dict, Any, Optional
import secrets
import asyncio

# Import unified system components
from core.system_manager import SystemManager
from core.event_manager import EventManager, EventType
from core.config_manager import ConfigManager
from utils.dual_version_bridge import DualVersionBridge, InterfaceType

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


def create_app(components: Dict[str, Any]) -> Flask:
    """
    Create enhanced Flask application with unified system integration.
    
    Args:
        components: Dictionary containing unified system components
            - system_manager: SystemManager instance
            - event_manager: EventManager instance
            - config_manager: ConfigManager instance
            - dual_bridge: DualVersionBridge instance
            - trading_engine: TradingEngine instance
            - logger: SystemLogger instance
    
    Returns:
        Flask application instance with SocketIO
    """
    # Extract components
    system_manager = components['system_manager']
    event_manager = components['event_manager']
    config_manager = components['config_manager']
    dual_bridge = components['dual_bridge']
    trading_engine = components['trading_engine']
    sys_logger = components['logger']
    
    # Create Flask app
    app = Flask(__name__)
    
    # Get configuration from unified config manager
    config = config_manager.get_current_config()
    web_config = config.web if config else None
    security_config = config.security if config else None
    
    # Configure Flask app
    app.config['SECRET_KEY'] = security_config.jwt_secret_key if security_config else secrets.token_hex(32)
    app.config['JWT_SECRET_KEY'] = security_config.jwt_secret_key if security_config else secrets.token_hex(32)
    app.config['JWT_ACCESS_TOKEN_EXPIRES'] = timedelta(hours=security_config.jwt_expiration_hours if security_config else 24)
    
    # Initialize extensions
    socketio = SocketIO(app, cors_allowed_origins="*", async_mode='threading')
    jwt = JWTManager(app)
    
    # Store components in app context for access in routes
    app.system_manager = system_manager
    app.event_manager = event_manager
    app.config_manager = config_manager
    app.dual_bridge = dual_bridge
    app.trading_engine = trading_engine
    app.sys_logger = sys_logger
    app.socketio = socketio
    
    # Register event manager WebSocket client
    event_manager.add_websocket_client(socketio)
    
    # Setup enhanced routes and handlers
    setup_enhanced_routes(app)
    setup_enhanced_websocket_handlers(app, socketio)
    setup_dual_version_integration(app)
    
    sys_logger.info("Enhanced Flask application created with unified integration")
    
    return app


def setup_enhanced_routes(app: Flask):
    """Setup enhanced routes with unified system integration."""
    
    @app.route('/')
    def index():
        """Enhanced index route with dual-version status"""
        if 'user_id' in session:
            return redirect(url_for('dashboard'))
        return redirect(url_for('login'))
    
    @app.route('/login', methods=['GET', 'POST'])
    def login():
        """Enhanced login with cross-version synchronization"""
        if request.method == 'POST':
            data = request.get_json()
            username = data.get('username')
            password = data.get('password')
            remember_me = data.get('remember_me', False)
            
            user = db_manager.authenticate_user(username, password)
            
            if user:
                # Create JWT token
                expires_delta = timedelta(days=7) if remember_me else timedelta(hours=24)
                access_token = create_access_token(
                    identity=user['id'],
                    expires_delta=expires_delta
                )
                
                # Set session
                session['user_id'] = user['id']
                session['username'] = user['username']
                
                # Emit login event through unified system
                app.event_manager.emit_event(
                    EventType.UI_UPDATE,
                    {
                        'event': 'user_login',
                        'user_id': user['id'],
                        'username': user['username'],
                        'interface': 'web'
                    },
                    source="web_auth"
                )
                
                app.sys_logger.info(f"User {username} logged in via web interface")
                
                return jsonify({
                    'success': True,
                    'access_token': access_token,
                    'user': user,
                    'dual_version_status': app.dual_bridge.get_bridge_status()
                })
            else:
                return jsonify({
                    'success': False,
                    'message': '잘못된 사용자명 또는 비밀번호입니다.'
                }), 401
        
        return render_template('login.html')
    
    @app.route('/dashboard')
    def dashboard():
        """Enhanced dashboard with unified system status"""
        if 'user_id' not in session:
            return redirect(url_for('login'))
        
        # Get comprehensive system status
        system_status = app.system_manager.get_system_status()
        bridge_status = app.dual_bridge.get_bridge_status()
        
        return render_template('dashboard.html', 
                             username=session.get('username'),
                             system_status=system_status,
                             bridge_status=bridge_status)
    
    @app.route('/settings')
    def settings():
        """Enhanced settings with cross-version synchronization"""
        if 'user_id' not in session:
            return redirect(url_for('login'))
        
        # Get settings from unified config manager
        user_settings = db_manager.get_trading_settings(session['user_id'])
        
        # Get cached settings from dual bridge if available
        cached_gui_settings = app.dual_bridge.get_cached_settings(InterfaceType.GUI)
        
        # Merge settings if both exist
        if cached_gui_settings:
            # Convert and merge settings
            try:
                from utils.dual_version_bridge import SettingsConverter
                converter = SettingsConverter()
                gui_as_web = converter.gui_to_web_format(cached_gui_settings)
                
                # Show compatibility status
                compatibility_issues = converter.validate_settings_compatibility(
                    cached_gui_settings, user_settings
                )
                
                return render_template('settings.html', 
                                     username=session.get('username'),
                                     settings=user_settings,
                                     gui_settings=gui_as_web,
                                     compatibility_issues=compatibility_issues)
            except Exception as e:
                app.sys_logger.error(f"Settings merge error: {e}")
        
        return render_template('settings.html', 
                             username=session.get('username'),
                             settings=user_settings)
    
    # Enhanced API routes
    @app.route('/api/system/status')
    @jwt_required()
    def api_system_status():
        """Get comprehensive unified system status"""
        return jsonify({
            'system_status': app.system_manager.get_system_status(),
            'bridge_status': app.dual_bridge.get_bridge_status(),
            'event_manager_status': app.event_manager.get_status(),
            'config_sync_status': {
                'sync_enabled': app.config_manager.sync_enabled,
                'last_sync': app.config_manager.last_sync_hash,
                'sync_clients': len(app.config_manager.sync_clients)
            }
        })
    
    @app.route('/api/settings/sync', methods=['POST'])
    @jwt_required()
    def api_settings_sync():
        """Synchronize settings across versions"""
        try:
            data = request.get_json()
            settings = data.get('settings', {})
            target_interface = data.get('target', 'gui')  # Default sync to GUI
            
            user_id = get_jwt_identity()
            
            # Save settings locally
            success = db_manager.save_trading_settings(user_id, settings)
            
            if success and target_interface.lower() == 'gui':
                # Sync to GUI version if connected
                target_type = InterfaceType.GUI
                sync_success = app.dual_bridge.sync_settings(
                    InterfaceType.WEB, target_type, settings
                )
                
                return jsonify({
                    'success': success,
                    'sync_success': sync_success,
                    'message': '설정이 저장되고 GUI와 동기화되었습니다.' if sync_success 
                              else '설정이 저장되었지만 GUI 동기화에 실패했습니다.'
                })
            
            return jsonify({
                'success': success,
                'message': '설정이 저장되었습니다.' if success else '설정 저장에 실패했습니다.'
            })
            
        except Exception as e:
            app.sys_logger.error(f"Settings sync error: {e}")
            return jsonify({
                'success': False,
                'message': f'동기화 오류: {str(e)}'
            }), 500


def setup_enhanced_websocket_handlers(app: Flask, socketio: SocketIO):
    """Setup enhanced WebSocket handlers with unified event integration."""
    
    @socketio.on('connect')
    def on_connect():
        """Handle client connection with dual-version awareness"""
        app.sys_logger.info(f"Enhanced client connected: {request.sid}")
        
        # Register this client with event manager
        app.event_manager.add_websocket_client(request.sid)
        
        # Send comprehensive initial data
        emit('system_status_update', {
            'system_status': app.system_manager.get_system_status(),
            'bridge_status': app.dual_bridge.get_bridge_status(),
            'connected_versions': list(app.dual_bridge.active_versions.keys())
        })
        
        # Emit connection event through unified system
        app.event_manager.emit_event(
            EventType.CONNECTION_ESTABLISHED,
            {'client_id': request.sid, 'interface': 'web'},
            source="web_websocket"
        )
    
    @socketio.on('disconnect')
    def on_disconnect():
        """Handle client disconnection"""
        app.sys_logger.info(f"Enhanced client disconnected: {request.sid}")
        
        # Unregister client from event manager
        app.event_manager.remove_websocket_client(request.sid)
        
        # Emit disconnection event
        app.event_manager.emit_event(
            EventType.CONNECTION_LOST,
            {'client_id': request.sid, 'interface': 'web'},
            source="web_websocket"
        )
    
    @socketio.on('request_sync')
    def on_request_sync(data):
        """Handle synchronization requests from client"""
        try:
            sync_type = data.get('type', 'status')
            
            if sync_type == 'settings':
                # Request settings sync from GUI
                user_id = session.get('user_id')
                if user_id:
                    cached_settings = app.dual_bridge.get_cached_settings(InterfaceType.GUI)
                    emit('settings_sync_response', {
                        'success': True,
                        'settings': cached_settings,
                        'timestamp': datetime.now().isoformat()
                    })
                else:
                    emit('settings_sync_response', {
                        'success': False,
                        'message': '사용자 인증이 필요합니다.'
                    })
                    
            elif sync_type == 'status':
                emit('status_sync_response', {
                    'system_status': app.system_manager.get_system_status(),
                    'bridge_status': app.dual_bridge.get_bridge_status()
                })
                
        except Exception as e:
            app.sys_logger.error(f"Sync request error: {e}")
            emit('sync_error', {'message': str(e)})


def setup_dual_version_integration(app: Flask):
    """Setup dual-version integration callbacks and monitoring."""
    
    def on_system_event(event):
        """Handle system events from event manager"""
        try:
            # Emit to all web clients
            app.socketio.emit('system_event', {
                'type': event.type.value,
                'data': event.data,
                'source': event.source,
                'timestamp': event.timestamp.isoformat()
            })
        except Exception as e:
            app.sys_logger.error(f"System event broadcast error: {e}")
    
    def on_config_change(change_event):
        """Handle configuration changes"""
        try:
            # Broadcast config changes to web clients
            app.socketio.emit('config_changed', {
                'section': change_event.section,
                'key': change_event.key,
                'old_value': change_event.old_value,
                'new_value': change_event.new_value,
                'source': change_event.source,
                'timestamp': change_event.timestamp.isoformat()
            })
        except Exception as e:
            app.sys_logger.error(f"Config change broadcast error: {e}")
    
    # Register callbacks
    app.event_manager.register_callback(EventType.SYSTEM_START, on_system_event)
    app.event_manager.register_callback(EventType.SYSTEM_STOP, on_system_event)
    app.event_manager.register_callback(EventType.TRADE_SIGNAL, on_system_event)
    app.event_manager.register_callback(EventType.POSITION_OPEN, on_system_event)
    app.event_manager.register_callback(EventType.POSITION_CLOSE, on_system_event)
    app.event_manager.register_callback(EventType.CONFIG_CHANGED, on_system_event)
    
    app.config_manager.register_change_callback(on_config_change)
    
    app.sys_logger.info("Dual-version integration setup completed")


# Redis connection for real-time data (kept for backward compatibility)
try:
    redis_client = redis.Redis(
        host=os.getenv('REDIS_HOST', 'localhost'),
        port=int(os.getenv('REDIS_PORT', 6379)),
        db=0,
        decode_responses=True
    )
except Exception as e:
    logger.warning(f"Redis connection failed: {e}")
    redis_client = None

class DatabaseManager:
    """Database manager for user authentication and settings"""
    
    def __init__(self, db_path: str = "web_dashboard.db"):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize database tables"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Users table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        username TEXT UNIQUE NOT NULL,
                        password_hash TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        last_login TIMESTAMP,
                        is_active BOOLEAN DEFAULT 1
                    )
                ''')
                
                # Trading settings table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS trading_settings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER,
                        settings_json TEXT NOT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users (id)
                    )
                ''')
                
                # Create default admin user if not exists
                cursor.execute('SELECT COUNT(*) FROM users WHERE username = ?', ('admin',))
                if cursor.fetchone()[0] == 0:
                    admin_password = generate_password_hash('admin123')
                    cursor.execute(
                        'INSERT INTO users (username, password_hash) VALUES (?, ?)',
                        ('admin', admin_password)
                    )
                
                conn.commit()
                logger.info("Database initialized successfully")
                
        except Exception as e:
            logger.error(f"Database initialization failed: {e}")
    
    def authenticate_user(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """Authenticate user credentials"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    'SELECT id, username, password_hash FROM users WHERE username = ? AND is_active = 1',
                    (username,)
                )
                user_data = cursor.fetchone()
                
                if user_data and check_password_hash(user_data[2], password):
                    # Update last login
                    cursor.execute(
                        'UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?',
                        (user_data[0],)
                    )
                    conn.commit()
                    
                    return {
                        'id': user_data[0],
                        'username': user_data[1]
                    }
                return None
                
        except Exception as e:
            logger.error(f"Authentication error: {e}")
            return None
    
    def get_trading_settings(self, user_id: int) -> Dict[str, Any]:
        """Get user trading settings"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    'SELECT settings_json FROM trading_settings WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1',
                    (user_id,)
                )
                result = cursor.fetchone()
                
                if result:
                    return json.loads(result[0])
                else:
                    # Return default settings
                    return self.get_default_settings()
                    
        except Exception as e:
            logger.error(f"Error getting trading settings: {e}")
            return self.get_default_settings()
    
    def save_trading_settings(self, user_id: int, settings: Dict[str, Any]) -> bool:
        """Save user trading settings"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute(
                    'INSERT INTO trading_settings (user_id, settings_json) VALUES (?, ?)',
                    (user_id, json.dumps(settings))
                )
                conn.commit()
                return True
                
        except Exception as e:
            logger.error(f"Error saving trading settings: {e}")
            return False
    
    def get_default_settings(self) -> Dict[str, Any]:
        """Get default trading settings"""
        return {
            'entry_conditions': {
                'moving_average': {'enabled': True, 'period': 20, 'type': 'SMA'},
                'price_channel': {'enabled': True, 'period': 20, 'threshold': 0.1},
                'order_flow': {'enabled': True, 'threshold': 1000},
                'tick_based': {'enabled': False, 'min_ticks': 3},
                'candle_pattern': {'enabled': False, 'patterns': ['hammer', 'doji']}
            },
            'exit_conditions': {
                'pcs_exit': {'enabled': True, 'levels': [0.5, 1.0, 1.5]},
                'pc_trailing': {'enabled': True, 'distance': 0.2},
                'order_exit': {'enabled': False, 'profit_target': 2.0},
                'pc_breakeven': {'enabled': True, 'threshold': 0.5}
            },
            'system_settings': {
                'exchange': 'binance',
                'symbols': ['BTCUSDT', 'ETHUSDT'],
                'leverage': 10,
                'position_size': 0.1,
                'max_positions': 2,
                'time_control': {'enabled': True, 'start': '09:00', 'end': '17:00'},
                'risk_management': {
                    'max_daily_loss': 5.0,
                    'max_drawdown': 10.0,
                    'stop_loss': 2.0
                }
            }
        }

# Initialize database manager
db_manager = DatabaseManager()

class TradingSystemInterface:
    """Interface to connect with the actual trading system"""
    
    def __init__(self):
        self.is_connected = False
        self.auto_trading_enabled = False
        self.positions = {}
        self.market_data = {}
        self.daily_stats = {
            'total_trades': 0,
            'win_rate': 0.0,
            'pnl': 0.0,
            'max_profit': 0.0,
            'max_loss': 0.0
        }
        self.conditions_status = {
            'moving_average': False,
            'price_channel': False,
            'order_flow': False,
            'tick_based': False,
            'candle_pattern': False
        }
        
        # Start background data simulation
        self.start_data_simulation()
    
    def start_data_simulation(self):
        """Start background thread to simulate real-time data"""
        def simulate_data():
            import random
            while True:
                try:
                    # Simulate market data
                    self.market_data = {
                        'BTCUSDT': {
                            'price': 45000 + random.uniform(-1000, 1000),
                            'change_24h': random.uniform(-5, 5),
                            'volume': random.uniform(1000, 10000)
                        },
                        'ETHUSDT': {
                            'price': 3000 + random.uniform(-200, 200),
                            'change_24h': random.uniform(-5, 5),
                            'volume': random.uniform(500, 5000)
                        }
                    }
                    
                    # Simulate positions
                    if random.random() > 0.7:  # 30% chance of having positions
                        self.positions = {
                            'BTCUSDT': {
                                'side': 'LONG',
                                'size': 0.1,
                                'entry_price': self.market_data['BTCUSDT']['price'] * 0.99,
                                'mark_price': self.market_data['BTCUSDT']['price'],
                                'pnl': random.uniform(-100, 200),
                                'pcs_level': random.choice([0, 1, 2, 3])
                            }
                        }
                    else:
                        self.positions = {}
                    
                    # Update connection status
                    self.is_connected = random.random() > 0.1  # 90% uptime
                    
                    # Simulate condition status
                    for condition in self.conditions_status:
                        self.conditions_status[condition] = random.random() > 0.6
                    
                    # Update daily stats
                    self.daily_stats.update({
                        'total_trades': random.randint(0, 50),
                        'win_rate': random.uniform(40, 80),
                        'pnl': random.uniform(-500, 1000),
                        'max_profit': random.uniform(100, 500),
                        'max_loss': random.uniform(-200, -50)
                    })
                    
                    # Emit real-time updates via WebSocket
                    socketio.emit('market_data_update', self.market_data)
                    socketio.emit('positions_update', self.positions)
                    socketio.emit('system_status_update', {
                        'connected': self.is_connected,
                        'auto_trading': self.auto_trading_enabled,
                        'last_update': datetime.now().isoformat()
                    })
                    socketio.emit('conditions_update', self.conditions_status)
                    socketio.emit('stats_update', self.daily_stats)
                    
                except Exception as e:
                    logger.error(f"Data simulation error: {e}")
                
                time.sleep(2)  # Update every 2 seconds
        
        thread = threading.Thread(target=simulate_data, daemon=True)
        thread.start()
    
    def toggle_auto_trading(self) -> bool:
        """Toggle auto trading on/off"""
        self.auto_trading_enabled = not self.auto_trading_enabled
        logger.info(f"Auto trading {'enabled' if self.auto_trading_enabled else 'disabled'}")
        return self.auto_trading_enabled
    
    def get_system_status(self) -> Dict[str, Any]:
        """Get current system status"""
        return {
            'connected': self.is_connected,
            'auto_trading': self.auto_trading_enabled,
            'last_update': datetime.now().isoformat(),
            'positions': self.positions,
            'market_data': self.market_data,
            'daily_stats': self.daily_stats,
            'conditions_status': self.conditions_status
        }

# Initialize trading system interface (kept for backward compatibility)
trading_system = TradingSystemInterface()

# Initialize database manager (kept for backward compatibility) 
db_manager = DatabaseManager()

# Legacy routes removed - now handled by create_app factory function above
# All Flask routes, API endpoints, and WebSocket handlers are now integrated
# into the unified system through the create_app() factory function.

# Legacy standalone execution (replaced by unified main.py entry point)
if __name__ == '__main__':
    # This is kept for backward compatibility only
    # Use: python main.py --mode web for unified system
    port = int(os.getenv('PORT', 5000))
    debug = os.getenv('FLASK_ENV') == 'development'
    
    logger.warning("Running in legacy standalone mode")
    logger.warning("For full functionality, use: python main.py --mode web")
    
    # Create a basic app for legacy support
    from core import SystemLogger, SecurityModule, ConfigManager
    
    basic_logger = SystemLogger("LegacyWebApp")
    basic_security = SecurityModule(basic_logger)
    basic_config = ConfigManager(basic_security, basic_logger)
    
    basic_components = {
        'system_manager': None,
        'event_manager': None,
        'config_manager': basic_config,
        'dual_bridge': None,
        'trading_engine': None,
        'logger': basic_logger
    }
    
    app = create_app(basic_components)
    logger.info(f"Starting legacy Flask web dashboard on port {port}")
    app.socketio.run(app, host='0.0.0.0', port=port, debug=debug)