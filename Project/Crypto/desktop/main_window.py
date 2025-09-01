"""
Main Window Module

Main PyQt5 application window with comprehensive trading interface.
"""

from typing import Dict, Any, Optional
import sys
from datetime import datetime

from PyQt5.QtWidgets import (
    QApplication, QMainWindow, QWidget, QVBoxLayout, QHBoxLayout,
    QTabWidget, QMenuBar, QStatusBar, QToolBar, QSplitter,
    QAction, QMessageBox, QSystemTrayIcon, QMenu
)
from PyQt5.QtCore import QTimer, QThread, pyqtSignal, Qt
from PyQt5.QtGui import QIcon, QPixmap

from ..core import (
    TradingEngine, RiskManager, ConfigManager, SystemLogger,
    BinanceConnector, BybitConnector, SecurityModule, DataProcessor
)
from .widgets import (
    TradingPanel, PositionsWidget, OrderBookWidget, ChartWidget,
    LogWidget, PerformanceWidget, RiskWidget, ConfigWidget
)
from .dialogs import LoginDialog, SettingsDialog, AboutDialog


class MainWindow(QMainWindow):
    """Main application window for the crypto trading system."""
    
    # Signals
    shutdown_signal = pyqtSignal()
    status_update_signal = pyqtSignal(str)
    
    def __init__(self):
        """Initialize main window."""
        super().__init__()
        
        # Core components (initialized after login)
        self.logger: Optional[SystemLogger] = None
        self.config_manager: Optional[ConfigManager] = None
        self.trading_engine: Optional[TradingEngine] = None
        self.risk_manager: Optional[RiskManager] = None
        
        # UI components
        self.central_widget = None
        self.status_bar = None
        self.system_tray = None
        
        # Threads for background operations
        self.worker_threads: Dict[str, QThread] = {}
        
        # Timers
        self.update_timer = QTimer()
        self.update_timer.timeout.connect(self.update_ui)
        
        # Application state
        self.is_authenticated = False
        self.is_trading_active = False
        
        # Setup UI
        self.init_ui()
        self.setup_system_tray()
        
        # Show login dialog
        self.show_login()
        
    def init_ui(self) -> None:
        """Initialize user interface."""
        self.setWindowTitle("Crypto Trading System v1.0")
        self.setGeometry(100, 100, 1400, 900)
        
        # Set application icon
        # self.setWindowIcon(QIcon("assets/icon.png"))
        
        # Create menu bar
        self.create_menu_bar()
        
        # Create tool bar
        self.create_tool_bar()
        
        # Create status bar
        self.status_bar = QStatusBar()
        self.setStatusBar(self.status_bar)
        self.status_bar.showMessage("Initializing...")
        
        # Create central widget (will be populated after login)
        self.central_widget = QWidget()
        self.setCentralWidget(self.central_widget)
        
    def create_menu_bar(self) -> None:
        """Create application menu bar."""
        menubar = self.menuBar()
        
        # File menu
        file_menu = menubar.addMenu('File')
        
        # New strategy action
        new_action = QAction('New Strategy', self)
        new_action.setShortcut('Ctrl+N')
        new_action.triggered.connect(self.new_strategy)
        file_menu.addAction(new_action)
        
        # Open strategy action
        open_action = QAction('Open Strategy', self)
        open_action.setShortcut('Ctrl+O')
        open_action.triggered.connect(self.open_strategy)
        file_menu.addAction(open_action)
        
        # Save strategy action
        save_action = QAction('Save Strategy', self)
        save_action.setShortcut('Ctrl+S')
        save_action.triggered.connect(self.save_strategy)
        file_menu.addAction(save_action)
        
        file_menu.addSeparator()
        
        # Exit action
        exit_action = QAction('Exit', self)
        exit_action.setShortcut('Ctrl+Q')
        exit_action.triggered.connect(self.close_application)
        file_menu.addAction(exit_action)
        
        # Trading menu
        trading_menu = menubar.addMenu('Trading')
        
        # Start trading action
        start_trading_action = QAction('Start Trading', self)
        start_trading_action.triggered.connect(self.start_trading)
        trading_menu.addAction(start_trading_action)
        
        # Stop trading action
        stop_trading_action = QAction('Stop Trading', self)
        stop_trading_action.triggered.connect(self.stop_trading)
        trading_menu.addAction(stop_trading_action)
        
        # Emergency stop action
        emergency_stop_action = QAction('Emergency Stop', self)
        emergency_stop_action.triggered.connect(self.emergency_stop)
        trading_menu.addAction(emergency_stop_action)
        
        # Tools menu
        tools_menu = menubar.addMenu('Tools')
        
        # Settings action
        settings_action = QAction('Settings', self)
        settings_action.triggered.connect(self.show_settings)
        tools_menu.addAction(settings_action)
        
        # Risk manager action
        risk_action = QAction('Risk Manager', self)
        risk_action.triggered.connect(self.show_risk_manager)
        tools_menu.addAction(risk_action)
        
        # Help menu
        help_menu = menubar.addMenu('Help')
        
        # Documentation action
        docs_action = QAction('Documentation', self)
        docs_action.triggered.connect(self.show_documentation)
        help_menu.addAction(docs_action)
        
        # About action
        about_action = QAction('About', self)
        about_action.triggered.connect(self.show_about)
        help_menu.addAction(about_action)
        
    def create_tool_bar(self) -> None:
        """Create application toolbar."""
        toolbar = QToolBar()
        self.addToolBar(toolbar)
        
        # Start trading button
        start_action = QAction('Start', self)
        start_action.triggered.connect(self.start_trading)
        toolbar.addAction(start_action)
        
        # Stop trading button
        stop_action = QAction('Stop', self)
        stop_action.triggered.connect(self.stop_trading)
        toolbar.addAction(stop_action)
        
        toolbar.addSeparator()
        
        # Emergency stop button
        emergency_action = QAction('EMERGENCY STOP', self)
        emergency_action.triggered.connect(self.emergency_stop)
        toolbar.addAction(emergency_action)
        
        toolbar.addSeparator()
        
        # Settings button
        settings_action = QAction('Settings', self)
        settings_action.triggered.connect(self.show_settings)
        toolbar.addAction(settings_action)
        
    def setup_system_tray(self) -> None:
        """Setup system tray functionality."""
        if QSystemTrayIcon.isSystemTrayAvailable():
            self.system_tray = QSystemTrayIcon(self)
            # self.system_tray.setIcon(QIcon("assets/tray_icon.png"))
            
            # Create tray menu
            tray_menu = QMenu()
            
            show_action = tray_menu.addAction("Show")
            show_action.triggered.connect(self.show)
            
            hide_action = tray_menu.addAction("Hide")
            hide_action.triggered.connect(self.hide)
            
            tray_menu.addSeparator()
            
            quit_action = tray_menu.addAction("Quit")
            quit_action.triggered.connect(self.close_application)
            
            self.system_tray.setContextMenu(tray_menu)
            self.system_tray.activated.connect(self.tray_icon_activated)
            
    def show_login(self) -> None:
        """Show login dialog."""
        login_dialog = LoginDialog(self)
        if login_dialog.exec_() == LoginDialog.Accepted:
            credentials = login_dialog.get_credentials()
            if self.authenticate(credentials):
                self.post_login_setup()
            else:
                QMessageBox.critical(self, "Login Failed", "Invalid credentials")
                self.show_login()
        else:
            sys.exit(0)
            
    def authenticate(self, credentials: Dict[str, str]) -> bool:
        """Authenticate user and initialize core components."""
        try:
            # Initialize logger first
            self.logger = SystemLogger(
                name="CryptoTradingGUI",
                log_level="INFO",
                console_output=True
            )
            
            # Initialize security module
            security_module = SecurityModule(self.logger)
            
            # Initialize configuration manager
            self.config_manager = ConfigManager(security_module, self.logger)
            config = self.config_manager.load_config()
            
            # Initialize API connectors
            binance_config = self.config_manager.get_exchange_config("binance")
            bybit_config = self.config_manager.get_exchange_config("bybit")
            
            binance_connector = BinanceConnector(
                binance_config.api_key,
                binance_config.api_secret,
                self.logger,
                binance_config.testnet
            )
            
            bybit_connector = BybitConnector(
                bybit_config.api_key,
                bybit_config.api_secret,
                self.logger,
                bybit_config.testnet
            )
            
            # Initialize data processor
            data_processor = DataProcessor(self.logger)
            
            # Initialize risk manager
            self.risk_manager = RiskManager(self.logger)
            
            # Initialize trading engine
            self.trading_engine = TradingEngine(
                self.risk_manager,
                binance_connector,
                bybit_connector,
                data_processor,
                self.logger
            )
            
            self.is_authenticated = True
            self.logger.info("Authentication successful")
            return True
            
        except Exception as e:
            if self.logger:
                self.logger.error(f"Authentication failed: {e}")
            return False
            
    def post_login_setup(self) -> None:
        """Setup UI after successful login."""
        self.create_main_interface()
        self.start_background_tasks()
        self.status_bar.showMessage("Ready")
        
        # Start UI update timer
        self.update_timer.start(1000)  # Update every second
        
    def create_main_interface(self) -> None:
        """Create main interface after authentication."""
        # Create main layout
        main_layout = QHBoxLayout()
        
        # Create main splitter
        main_splitter = QSplitter(Qt.Horizontal)
        
        # Left panel - Trading controls and positions
        left_widget = QWidget()
        left_layout = QVBoxLayout(left_widget)
        
        # Trading panel
        self.trading_panel = TradingPanel(self.trading_engine, self.logger)
        left_layout.addWidget(self.trading_panel)
        
        # Positions widget
        self.positions_widget = PositionsWidget(self.trading_engine, self.logger)
        left_layout.addWidget(self.positions_widget)
        
        main_splitter.addWidget(left_widget)
        
        # Center panel - Charts and order book
        center_widget = QWidget()
        center_layout = QVBoxLayout(center_widget)
        
        # Chart widget
        self.chart_widget = ChartWidget(self.logger)
        center_layout.addWidget(self.chart_widget)
        
        # Order book widget
        self.orderbook_widget = OrderBookWidget(self.logger)
        center_layout.addWidget(self.orderbook_widget)
        
        main_splitter.addWidget(center_widget)
        
        # Right panel - Risk and performance
        right_widget = QWidget()
        right_layout = QVBoxLayout(right_widget)
        
        # Risk widget
        self.risk_widget = RiskWidget(self.risk_manager, self.logger)
        right_layout.addWidget(self.risk_widget)
        
        # Performance widget
        self.performance_widget = PerformanceWidget(self.logger)
        right_layout.addWidget(self.performance_widget)
        
        main_splitter.addWidget(right_widget)
        
        # Set splitter proportions
        main_splitter.setStretchFactor(0, 1)  # Left panel
        main_splitter.setStretchFactor(1, 2)  # Center panel
        main_splitter.setStretchFactor(2, 1)  # Right panel
        
        main_layout.addWidget(main_splitter)
        
        # Bottom panel - Logs and status
        bottom_splitter = QSplitter(Qt.Vertical)
        
        # Main content
        main_content = QWidget()
        main_content.setLayout(main_layout)
        bottom_splitter.addWidget(main_content)
        
        # Log widget
        self.log_widget = LogWidget(self.logger)
        bottom_splitter.addWidget(self.log_widget)
        
        # Set vertical splitter proportions
        bottom_splitter.setStretchFactor(0, 3)  # Main content
        bottom_splitter.setStretchFactor(1, 1)  # Logs
        
        # Set central widget
        self.central_widget.setLayout(QVBoxLayout())
        self.central_widget.layout().addWidget(bottom_splitter)
        
    def start_background_tasks(self) -> None:
        """Start background worker threads."""
        # Data processing thread
        if hasattr(self, 'trading_engine') and self.trading_engine:
            data_thread = QThread()
            # Setup data processing worker
            self.worker_threads['data'] = data_thread
            data_thread.start()
            
    def update_ui(self) -> None:
        """Update UI elements periodically."""
        if not self.is_authenticated:
            return
            
        try:
            # Update status bar
            if self.trading_engine:
                engine_status = self.trading_engine.get_engine_status()
                status_text = f"Engine: {'Running' if engine_status['is_running'] else 'Stopped'}"
                status_text += f" | Positions: {engine_status['active_positions_count']}"
                self.status_bar.showMessage(status_text)
                
            # Update widgets
            if hasattr(self, 'positions_widget'):
                self.positions_widget.update_data()
                
            if hasattr(self, 'risk_widget'):
                self.risk_widget.update_data()
                
            if hasattr(self, 'performance_widget'):
                self.performance_widget.update_data()
                
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error updating UI: {e}")
                
    # Menu action methods
    
    def new_strategy(self) -> None:
        """Create new trading strategy."""
        # TODO: Implement strategy creation
        QMessageBox.information(self, "New Strategy", "Strategy creation not implemented yet")
        
    def open_strategy(self) -> None:
        """Open existing strategy."""
        # TODO: Implement strategy loading
        QMessageBox.information(self, "Open Strategy", "Strategy loading not implemented yet")
        
    def save_strategy(self) -> None:
        """Save current strategy."""
        # TODO: Implement strategy saving
        QMessageBox.information(self, "Save Strategy", "Strategy saving not implemented yet")
        
    def start_trading(self) -> None:
        """Start trading engine."""
        if self.trading_engine and not self.is_trading_active:
            try:
                # Start trading in background thread
                import asyncio
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                loop.run_until_complete(self.trading_engine.start())
                
                self.is_trading_active = True
                self.logger.info("Trading started")
                QMessageBox.information(self, "Trading", "Trading started successfully")
            except Exception as e:
                self.logger.error(f"Failed to start trading: {e}")
                QMessageBox.critical(self, "Error", f"Failed to start trading: {e}")
                
    def stop_trading(self) -> None:
        """Stop trading engine."""
        if self.trading_engine and self.is_trading_active:
            try:
                import asyncio
                loop = asyncio.get_event_loop()
                loop.run_until_complete(self.trading_engine.stop())
                
                self.is_trading_active = False
                self.logger.info("Trading stopped")
                QMessageBox.information(self, "Trading", "Trading stopped successfully")
            except Exception as e:
                self.logger.error(f"Failed to stop trading: {e}")
                QMessageBox.critical(self, "Error", f"Failed to stop trading: {e}")
                
    def emergency_stop(self) -> None:
        """Emergency stop all trading activities."""
        reply = QMessageBox.question(
            self, 'Emergency Stop',
            'This will immediately stop all trading and close positions. Continue?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            try:
                if self.risk_manager:
                    self.risk_manager.activate_emergency_stop("User initiated emergency stop")
                if self.trading_engine:
                    import asyncio
                    loop = asyncio.get_event_loop()
                    loop.run_until_complete(self.trading_engine.stop())
                    
                self.is_trading_active = False
                self.logger.critical("Emergency stop activated")
                QMessageBox.warning(self, "Emergency Stop", "Emergency stop activated")
            except Exception as e:
                self.logger.error(f"Emergency stop failed: {e}")
                QMessageBox.critical(self, "Error", f"Emergency stop failed: {e}")
                
    def show_settings(self) -> None:
        """Show settings dialog."""
        settings_dialog = SettingsDialog(self.config_manager, self)
        settings_dialog.exec_()
        
    def show_risk_manager(self) -> None:
        """Show risk manager dialog."""
        # TODO: Implement risk manager dialog
        QMessageBox.information(self, "Risk Manager", "Risk manager dialog not implemented yet")
        
    def show_documentation(self) -> None:
        """Show documentation."""
        # TODO: Open documentation
        QMessageBox.information(self, "Documentation", "Documentation not available yet")
        
    def show_about(self) -> None:
        """Show about dialog."""
        about_dialog = AboutDialog(self)
        about_dialog.exec_()
        
    def tray_icon_activated(self, reason) -> None:
        """Handle system tray icon activation."""
        if reason == QSystemTrayIcon.DoubleClick:
            self.show()
            self.raise_()
            self.activateWindow()
            
    def close_application(self) -> None:
        """Close application gracefully."""
        reply = QMessageBox.question(
            self, 'Exit Application',
            'Are you sure you want to exit? Trading will be stopped.',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            # Stop trading if active
            if self.is_trading_active:
                self.stop_trading()
                
            # Stop background threads
            for thread in self.worker_threads.values():
                thread.quit()
                thread.wait()
                
            # Close application
            QApplication.quit()
            
    def closeEvent(self, event) -> None:
        """Handle window close event."""
        if self.system_tray and self.system_tray.isVisible():
            self.hide()
            self.system_tray.showMessage(
                "Crypto Trading System",
                "Application was minimized to tray",
                QSystemTrayIcon.Information,
                2000
            )
            event.ignore()
        else:
            self.close_application()
            event.accept()


def main():
    """Main application entry point."""
    app = QApplication(sys.argv)
    app.setQuitOnLastWindowClosed(False)
    
    # Set application properties
    app.setApplicationName("Crypto Trading System")
    app.setApplicationVersion("1.0.0")
    app.setOrganizationName("Crypto Trading Team")
    
    # Create and show main window
    window = MainWindow()
    window.show()
    
    # Start event loop
    sys.exit(app.exec_())


if __name__ == "__main__":
    main()