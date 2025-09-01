"""
Main Application Entry Point

Crypto Trading System - Comprehensive trading platform with GUI and web interface.
"""

import sys
import asyncio
import argparse
from pathlib import Path

# Add project root to path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

from core import (
    SystemLogger, SecurityModule, ConfigManager, TradingEngine,
    RiskManager, BinanceConnector, BybitConnector, DataProcessor,
    TimeController
)
from core.config_manager import Environment
from core.system_manager import SystemManager, SystemMode, initialize_system_manager
from core.event_manager import EventManager, EventType, initialize_event_manager
from utils.dual_version_bridge import DualVersionBridge, InterfaceType, initialize_dual_version_bridge


def parse_arguments():
    """Parse command line arguments."""
    parser = argparse.ArgumentParser(description='Crypto Trading System')
    parser.add_argument(
        '--mode', 
        choices=['gui', 'web', 'cli', 'service'],
        default='gui',
        help='Application mode (default: gui)'
    )
    parser.add_argument(
        '--env',
        choices=['development', 'testing', 'production'],
        default='development',
        help='Environment (default: development)'
    )
    parser.add_argument(
        '--config',
        type=str,
        help='Custom configuration file path'
    )
    parser.add_argument(
        '--log-level',
        choices=['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'],
        default='INFO',
        help='Logging level (default: INFO)'
    )
    parser.add_argument(
        '--testnet',
        action='store_true',
        help='Use testnet (override config)'
    )
    parser.add_argument(
        '--port',
        type=int,
        help='Port for web mode (default: from config)'
    )
    parser.add_argument(
        '--host',
        type=str,
        help='Host for web mode (default: from config)'
    )
    parser.add_argument(
        '--background',
        action='store_true',
        help='Run in background (service mode)'
    )
    parser.add_argument(
        '--dual',
        action='store_true',
        help='Enable dual-version mode (GUI + Web simultaneously)'
    )
    
    return parser.parse_args()


async def initialize_core_components(env: Environment, log_level: str):
    """Initialize core system components with unified architecture."""
    print(f"Initializing Crypto Trading System (Environment: {env.value})")
    
    # Initialize logger
    logger = SystemLogger(
        name="CryptoTradingSystem",
        log_level=log_level,
        console_output=True,
        json_format=False
    )
    logger.info("Starting Crypto Trading System with unified architecture...")
    
    try:
        # Initialize core infrastructure
        logger.info("Initializing core infrastructure...")
        
        # Initialize system manager
        system_manager = initialize_system_manager(logger)
        
        # Initialize event manager
        event_manager = initialize_event_manager(logger)
        await event_manager.start()
        
        # Initialize dual version bridge
        dual_bridge = initialize_dual_version_bridge(logger, system_manager, event_manager)
        
        # Register core components with system manager
        system_manager.register_component("event_manager", event_manager)
        system_manager.register_component("dual_bridge", dual_bridge)
        
        # Initialize security module
        logger.info("Initializing security module...")
        security_module = SecurityModule(logger)
        system_manager.register_component("security_module", security_module)
        
        # Initialize enhanced configuration manager
        logger.info("Loading configuration with synchronization...")
        config_manager = ConfigManager(security_module, logger)
        config = config_manager.load_config(env)
        
        # Start configuration synchronization monitoring
        await config_manager.start_sync_monitoring()
        
        # Register configuration change callback for event broadcasting
        def on_config_change(change_event):
            event_manager.emit_event(
                EventType.CONFIG_CHANGED,
                {
                    'section': change_event.section,
                    'key': change_event.key,
                    'old_value': change_event.old_value,
                    'new_value': change_event.new_value,
                    'source': change_event.source
                },
                source="config_manager"
            )
        
        config_manager.register_change_callback(on_config_change)
        system_manager.register_component("config_manager", config_manager)
        
        # Validate configuration
        is_valid, errors = config_manager.validate_config(config)
        if not is_valid:
            logger.error("Configuration validation failed:")
            for error in errors:
                logger.error(f"  - {error}")
            return None
            
        logger.info("Configuration loaded and validated successfully")
        
        # Initialize time controller
        logger.info("Initializing time controller...")
        time_controller = TimeController(logger)
        
        # Initialize data processor
        logger.info("Initializing data processor...")
        data_processor = DataProcessor(logger)
        
        # Initialize API connectors
        logger.info("Initializing exchange connectors...")
        binance_config = config_manager.get_exchange_config("binance")
        bybit_config = config_manager.get_exchange_config("bybit")
        
        if not binance_config or not bybit_config:
            logger.error("Exchange configurations not found")
            return None
            
        binance_connector = BinanceConnector(
            binance_config.api_key,
            binance_config.api_secret,
            logger,
            binance_config.testnet
        )
        
        bybit_connector = BybitConnector(
            bybit_config.api_key,
            bybit_config.api_secret,
            logger,
            bybit_config.testnet
        )
        
        # Test connections
        logger.info("Testing exchange connections...")
        binance_connected = await binance_connector.connect()
        bybit_connected = await bybit_connector.connect()
        
        if not binance_connected:
            logger.warning("Failed to connect to Binance")
        if not bybit_connected:
            logger.warning("Failed to connect to Bybit")
            
        # Initialize risk manager
        logger.info("Initializing risk manager...")
        risk_manager = RiskManager(logger, config.trading.position_size_pct * 10000)
        
        # Initialize trading engine
        logger.info("Initializing trading engine...")
        trading_engine = TradingEngine(
            risk_manager,
            binance_connector,
            bybit_connector,
            data_processor,
            logger
        )
        
        # Register trading engine with system manager
        system_manager.register_component("trading_engine", trading_engine, 
                                         dependencies=["risk_manager", "data_processor"])
        
        # Register trading event callbacks with event manager
        def on_trade_signal(signal_data):
            event_manager.emit_event(
                EventType.TRADE_SIGNAL,
                signal_data,
                source="trading_engine"
            )
        
        # Connect trading engine events (this would be done in the actual trading engine)
        # trading_engine.register_signal_callback(on_trade_signal)
        
        # Start the unified system
        system_mode = SystemMode.SERVICE  # Default mode, will be updated by run functions
        await system_manager.start(system_mode)
        
        logger.info("Unified core system initialized successfully")
        
        return {
            'logger': logger,
            'system_manager': system_manager,
            'event_manager': event_manager,
            'dual_bridge': dual_bridge,
            'security_module': security_module,
            'config_manager': config_manager,
            'time_controller': time_controller,
            'data_processor': data_processor,
            'binance_connector': binance_connector,
            'bybit_connector': bybit_connector,
            'risk_manager': risk_manager,
            'trading_engine': trading_engine
        }
        
    except Exception as e:
        logger.error(f"Failed to initialize core components: {e}")
        return None


async def run_gui_mode(components):
    """Run application in GUI mode with unified system integration."""
    logger = components['logger']
    system_manager = components['system_manager']
    dual_bridge = components['dual_bridge']
    
    logger.info("Starting GUI mode with unified integration...")
    
    try:
        # Register GUI version with dual bridge
        dual_bridge.register_version(
            InterfaceType.GUI,
            "1.0.0",
            ["trading", "monitoring", "settings", "real_time_updates"]
        )
        
        # Update system manager mode
        await system_manager.start(SystemMode.GUI)
        
        from desktop.main_window import main
        
        # Create enhanced GUI with system integration
        def create_gui():
            app_components = {
                'system_manager': system_manager,
                'event_manager': components['event_manager'],
                'config_manager': components['config_manager'],
                'dual_bridge': dual_bridge,
                'logger': logger
            }
            return main(app_components)
        
        # Run GUI in a separate thread to avoid blocking async event loop
        import threading
        gui_thread = threading.Thread(target=create_gui)
        gui_thread.start()
        
        # Monitor GUI thread
        while gui_thread.is_alive():
            await asyncio.sleep(1)
            
    except ImportError as e:
        logger.error(f"GUI dependencies not available: {e}")
        logger.info("Please install PyQt5: pip install PyQt5")
        return False
    except Exception as e:
        logger.error(f"GUI mode failed: {e}")
        return False
    finally:
        # Cleanup
        dual_bridge.unregister_version(InterfaceType.GUI)
        
    return True


async def run_web_mode(components, host_override=None, port_override=None):
    """Run application in web mode with unified system integration."""
    logger = components['logger']
    system_manager = components['system_manager']
    config_manager = components['config_manager']
    event_manager = components['event_manager']
    dual_bridge = components['dual_bridge']
    trading_engine = components['trading_engine']
    
    logger.info("Starting web mode with unified integration...")
    
    try:
        # Register web version with dual bridge
        dual_bridge.register_version(
            InterfaceType.WEB,
            "1.0.0",
            ["web_interface", "api", "websockets", "real_time_updates", "settings_sync"]
        )
        
        # Update system manager mode
        await system_manager.start(SystemMode.WEB)
        
        from web.app import create_app
        
        # Create enhanced web app with unified system integration
        app_components = {
            'system_manager': system_manager,
            'event_manager': event_manager,
            'config_manager': config_manager,
            'dual_bridge': dual_bridge,
            'trading_engine': trading_engine,
            'logger': logger
        }
        
        app = create_app(app_components)
        
        # Get configuration
        web_config = config_manager.get_config().web
        host = host_override or web_config.host
        port = port_override or web_config.port
        debug = web_config.debug
        
        # Register Flask-SocketIO with event manager for real-time broadcasting
        event_manager.add_socketio_handler('/', app.socketio)
        
        logger.info(f"Starting enhanced web server on {host}:{port}")
        
        # Run web server in background thread to allow async operation
        def run_flask_app():
            app.socketio.run(app, host=host, port=port, debug=debug)
        
        import threading
        web_thread = threading.Thread(target=run_flask_app, daemon=True)
        web_thread.start()
        
        # Keep the async loop alive
        try:
            while web_thread.is_alive():
                await asyncio.sleep(1)
                # Emit periodic status updates
                if system_manager:
                    status = system_manager.get_system_status()
                    event_manager.emit_event(
                        EventType.STATUS_UPDATE,
                        status,
                        source="web_mode"
                    )
                await asyncio.sleep(5)  # Every 5 seconds
        except KeyboardInterrupt:
            logger.info("Web mode interrupted by user")
        
    except ImportError as e:
        logger.error(f"Web dependencies not available: {e}")
        logger.info("Please install Flask dependencies: pip install Flask Flask-SocketIO")
        return False
    except Exception as e:
        logger.error(f"Web mode failed: {e}")
        return False
    finally:
        # Cleanup
        dual_bridge.unregister_version(InterfaceType.WEB)
        
    return True


async def run_cli_mode(components):
    """Run application in CLI mode."""
    logger = components['logger']
    trading_engine = components['trading_engine']
    
    logger.info("Starting CLI mode...")
    print("\n=== Crypto Trading System CLI ===")
    print("Type 'help' for available commands, 'exit' to quit\n")
    
    try:
        while True:
            try:
                command = input("trading> ").strip().lower()
                
                if command == 'exit' or command == 'quit':
                    break
                elif command == 'help':
                    print("Available commands:")
                    print("  status  - Show system status")
                    print("  start   - Start trading engine")
                    print("  stop    - Stop trading engine")
                    print("  positions - Show active positions")
                    print("  risk    - Show risk status")
                    print("  help    - Show this help")
                    print("  exit    - Exit application")
                elif command == 'status':
                    status = trading_engine.get_engine_status()
                    print(f"Engine Status: {'Running' if status['is_running'] else 'Stopped'}")
                    print(f"Active Positions: {status['active_positions_count']}")
                    print(f"Total Signals: {status['total_signals_generated']}")
                elif command == 'start':
                    await trading_engine.start()
                    print("Trading engine started")
                elif command == 'stop':
                    await trading_engine.stop()
                    print("Trading engine stopped")
                elif command == 'positions':
                    positions = trading_engine.get_active_positions()
                    if positions:
                        print("Active Positions:")
                        for key, pos in positions.items():
                            print(f"  {pos.symbol}: {pos.side} {pos.size} @ {pos.entry_price}")
                    else:
                        print("No active positions")
                elif command == 'risk':
                    risk_status = components['risk_manager'].get_risk_status()
                    print(f"Risk Level: {risk_status['risk_level']}")
                    print(f"Current Drawdown: {risk_status['current_drawdown']:.2%}")
                    print(f"Emergency Stop: {risk_status['emergency_stop']}")
                else:
                    print(f"Unknown command: {command}")
                    
            except KeyboardInterrupt:
                break
            except Exception as e:
                logger.error(f"CLI command error: {e}")
                print(f"Error: {e}")
                
    except Exception as e:
        logger.error(f"CLI mode failed: {e}")
        return False
        
    return True


async def run_service_mode(components):
    """Run application as a background service."""
    logger = components['logger']
    trading_engine = components['trading_engine']
    
    logger.info("Starting service mode...")
    
    try:
        # Start trading engine
        await trading_engine.start()
        
        # Keep running until interrupted
        while True:
            await asyncio.sleep(60)  # Check every minute
            
            # Could add health checks, monitoring, etc. here
            
    except KeyboardInterrupt:
        logger.info("Service mode interrupted")
    except Exception as e:
        logger.error(f"Service mode failed: {e}")
        return False
    finally:
        # Cleanup
        await trading_engine.stop()
        
    return True


async def run_dual_mode(components, host_override=None, port_override=None):
    """Run application in dual mode (GUI + Web simultaneously)."""
    logger = components['logger']
    system_manager = components['system_manager']
    dual_bridge = components['dual_bridge']
    
    logger.info("Starting dual mode (GUI + Web simultaneously)...")
    
    try:
        # Update system manager mode
        await system_manager.start(SystemMode.DUAL)
        
        # Start web mode in background
        web_task = asyncio.create_task(
            run_web_mode(components, host_override, port_override)
        )
        
        # Wait a moment for web server to start
        await asyncio.sleep(2)
        
        # Start GUI mode
        gui_task = asyncio.create_task(run_gui_mode(components))
        
        logger.info("Both GUI and Web interfaces are running")
        
        # Wait for either to complete (or fail)
        done, pending = await asyncio.wait(
            [gui_task, web_task],
            return_when=asyncio.FIRST_COMPLETED
        )
        
        # Cancel remaining tasks
        for task in pending:
            task.cancel()
            try:
                await task
            except asyncio.CancelledError:
                pass
        
        return True
        
    except Exception as e:
        logger.error(f"Dual mode failed: {e}")
        return False


async def main():
    """Main application entry point with enhanced dual-version support."""
    args = parse_arguments()
    
    # Convert environment string to enum
    env = Environment(args.env)
    
    # Initialize core components
    components = await initialize_core_components(env, args.log_level)
    if not components:
        print("Failed to initialize system components")
        return 1
        
    logger = components['logger']
    
    try:
        # Handle dual mode flag
        if args.dual:
            mode = 'dual'
        else:
            mode = args.mode
            
        # Run in specified mode
        success = False
        
        if mode == 'gui':
            success = await run_gui_mode(components)
        elif mode == 'web':
            success = await run_web_mode(components, args.host, args.port)
        elif mode == 'cli':
            success = await run_cli_mode(components)
        elif mode == 'service':
            success = await run_service_mode(components)
        elif mode == 'dual':
            success = await run_dual_mode(components, args.host, args.port)
            
        if not success:
            logger.error(f"Failed to run in {args.mode} mode")
            return 1
            
    except KeyboardInterrupt:
        logger.info("Application interrupted by user")
    except Exception as e:
        logger.critical(f"Unexpected error: {e}")
        return 1
    finally:
        # Enhanced cleanup with unified system shutdown
        logger.info("Shutting down unified system...")
        try:
            # Stop unified system components in proper order
            if 'system_manager' in components:
                await components['system_manager'].stop()
            
            if 'config_manager' in components:
                await components['config_manager'].stop_sync_monitoring()
            
            if 'event_manager' in components:
                await components['event_manager'].stop()
            
            # Stop trading components
            if 'trading_engine' in components:
                await components['trading_engine'].stop()
            if 'binance_connector' in components:
                await components['binance_connector'].disconnect()
            if 'bybit_connector' in components:
                await components['bybit_connector'].disconnect()
                
        except Exception as e:
            logger.error(f"Cleanup error: {e}")
            
        logger.info("Application shutdown complete")
        
    return 0


if __name__ == "__main__":
    try:
        # Run main with asyncio
        exit_code = asyncio.run(main())
        sys.exit(exit_code)
    except Exception as e:
        print(f"Fatal error: {e}")
        sys.exit(1)