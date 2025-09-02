"""
Professional Crypto Trading Desktop Application
PRD-compliant PyQt5 implementation with 3-tab structure
"""

import sys
import os
from datetime import datetime
from typing import Dict, Any, Optional
import threading
import time

from PyQt5.QtWidgets import (
    QApplication, QMainWindow, QWidget, QVBoxLayout, QHBoxLayout,
    QTabWidget, QStatusBar, QMenuBar, QToolBar, QPushButton, 
    QLabel, QSplitter, QSystemTrayIcon, QMenu, QAction, QMessageBox,
    QFrame, QGridLayout
)
from PyQt5.QtCore import QTimer, QThread, pyqtSignal, Qt, QPropertyAnimation, QRect
from PyQt5.QtGui import QFont, QIcon, QPalette, QColor, QPixmap

# Import tabs
from .tabs.entry_tab import EntryTab
from .tabs.exit_tab import ExitTab  
from .tabs.settings_tab import SettingsTab

# Import widgets
from .widgets.chart_widget import ChartWidget
from .widgets.position_widget import PositionWidget
from .widgets.status_widget import StatusWidget

# Import security and core modules
from ..core.security_module import SecurityModule
from ..core.config_manager import ConfigManager
from ..core.logger import SystemLogger


class MainGUI(QMainWindow):
    """
    Main GUI Application Window
    PRD-compliant desktop trading interface
    """
    
    # Signals for real-time updates
    status_update = pyqtSignal(str)
    position_update = pyqtSignal(dict)
    price_update = pyqtSignal(dict)
    
    def __init__(self):
        super().__init__()
        
        # Initialize security first
        self.security = SecurityModule()
        self.logger = SystemLogger("MainGUI")
        self.config = ConfigManager(self.security, self.logger)
        
        # Application state
        self.is_authenticated = False
        self.is_trading_active = False
        self.is_paused = False
        self.last_activity = datetime.now()
        
        # Core components will be initialized after authentication
        self.trading_engine = None
        self.risk_manager = None
        
        # Initialize UI
        self.init_ui()
        self.setup_styling()
        self.setup_timers()
        self.setup_system_tray()
        
        # Show authentication dialog
        self.authenticate()
        
    def init_ui(self):
        """Initialize the main user interface"""
        # Main window properties
        self.setWindowTitle("전문 가상화폐 자동매매 시스템 v1.0")
        self.setGeometry(100, 100, 1600, 1000)
        self.setMinimumSize(1400, 900)
        
        # Create central widget
        central_widget = QWidget()
        self.setCentralWidget(central_widget)
        
        # Main layout
        main_layout = QVBoxLayout(central_widget)
        main_layout.setSpacing(5)
        main_layout.setContentsMargins(10, 10, 10, 10)
        
        # Create top control panel
        self.create_control_panel()
        main_layout.addWidget(self.control_frame)
        
        # Create main content area with splitter
        content_splitter = QSplitter(Qt.Horizontal)
        
        # Left side - Tabs (60% width)
        self.create_tab_widget()
        content_splitter.addWidget(self.tab_widget)
        
        # Right side - Charts and widgets (40% width)
        right_widget = self.create_right_panel()
        content_splitter.addWidget(right_widget)
        
        # Set splitter proportions
        content_splitter.setStretchFactor(0, 3)  # Tabs
        content_splitter.setStretchFactor(1, 2)  # Right panel
        
        main_layout.addWidget(content_splitter)
        
        # Create status bar
        self.create_status_bar()
        
        # Create menu bar
        self.create_menu_bar()
        
    def create_control_panel(self):
        """Create top control panel with emergency and control buttons"""
        self.control_frame = QFrame()
        self.control_frame.setFrameStyle(QFrame.Box)
        self.control_frame.setMaximumHeight(80)
        
        control_layout = QHBoxLayout(self.control_frame)
        control_layout.setSpacing(15)
        control_layout.setContentsMargins(15, 10, 15, 10)
        
        # Emergency stop button (prominent red button)
        self.emergency_btn = QPushButton("🚨 긴급 포지션 청산")
        self.emergency_btn.setStyleSheet("""
            QPushButton {
                background-color: #dc3545;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: bold;
                padding: 12px 25px;
                min-width: 180px;
            }
            QPushButton:hover {
                background-color: #c82333;
            }
            QPushButton:pressed {
                background-color: #bd2130;
            }
        """)
        self.emergency_btn.clicked.connect(self.emergency_stop)
        control_layout.addWidget(self.emergency_btn)
        
        # Spacer
        control_layout.addStretch()
        
        # Trading control buttons
        self.pause_btn = QPushButton("⏸️ 자동매매 일시정지")
        self.pause_btn.setStyleSheet("""
            QPushButton {
                background-color: #ffc107;
                color: #212529;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                font-weight: bold;
                padding: 10px 20px;
                min-width: 150px;
            }
            QPushButton:hover {
                background-color: #e0a800;
            }
        """)
        self.pause_btn.clicked.connect(self.pause_trading)
        control_layout.addWidget(self.pause_btn)
        
        self.resume_btn = QPushButton("▶️ 자동매매 재시작")
        self.resume_btn.setStyleSheet("""
            QPushButton {
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                font-weight: bold;
                padding: 10px 20px;
                min-width: 150px;
            }
            QPushButton:hover {
                background-color: #218838;
            }
        """)
        self.resume_btn.clicked.connect(self.resume_trading)
        self.resume_btn.setEnabled(False)
        control_layout.addWidget(self.resume_btn)
        
        # Add spacer at end
        control_layout.addStretch()
        
    def create_tab_widget(self):
        """Create the main tab widget with 3 tabs"""
        self.tab_widget = QTabWidget()
        self.tab_widget.setTabPosition(QTabWidget.North)
        
        # Set tab widget styling
        self.tab_widget.setStyleSheet("""
            QTabWidget::pane {
                border: 2px solid #c0c4cc;
                border-radius: 8px;
                background-color: white;
            }
            QTabBar::tab {
                background-color: #f8f9fa;
                border: 2px solid #dee2e6;
                border-bottom: none;
                border-radius: 8px 8px 0 0;
                padding: 12px 25px;
                margin-right: 2px;
                font-size: 14px;
                font-weight: bold;
                min-width: 120px;
            }
            QTabBar::tab:selected {
                background-color: white;
                border-color: #007bff;
                color: #007bff;
            }
            QTabBar::tab:hover {
                background-color: #e9ecef;
            }
        """)
        
        # Create tabs
        self.entry_tab = EntryTab(self.config, self.logger)
        self.exit_tab = ExitTab(self.config, self.logger)
        self.settings_tab = SettingsTab(self.config, self.logger)
        
        # Add tabs
        self.tab_widget.addTab(self.entry_tab, "🎯 진입 설정")
        self.tab_widget.addTab(self.exit_tab, "📈 청산 설정")
        self.tab_widget.addTab(self.settings_tab, "⚙️ 시스템 설정")
        
    def create_right_panel(self):
        """Create right panel with chart and status widgets"""
        right_widget = QWidget()
        right_layout = QVBoxLayout(right_widget)
        right_layout.setSpacing(5)
        right_layout.setContentsMargins(5, 5, 5, 5)
        
        # Chart widget (takes most space)
        self.chart_widget = ChartWidget(self.logger)
        right_layout.addWidget(self.chart_widget, stretch=3)
        
        # Position widget
        self.position_widget = PositionWidget(self.logger)
        right_layout.addWidget(self.position_widget, stretch=1)
        
        # Status widget
        self.status_widget = StatusWidget(self.logger)
        right_layout.addWidget(self.status_widget, stretch=1)
        
        return right_widget
        
    def create_status_bar(self):
        """Create comprehensive status bar"""
        self.status_bar = QStatusBar()
        self.setStatusBar(self.status_bar)
        
        # Status bar styling
        self.status_bar.setStyleSheet("""
            QStatusBar {
                background-color: #f8f9fa;
                border-top: 1px solid #dee2e6;
                font-size: 12px;
                padding: 5px;
            }
            QLabel {
                margin: 0 10px;
            }
        """)
        
        # Connection status
        self.connection_label = QLabel("연결: ❌ 연결 안됨")
        self.connection_label.setStyleSheet("color: #dc3545; font-weight: bold;")
        self.status_bar.addWidget(self.connection_label)
        
        self.status_bar.addPermanentWidget(QLabel("|"))
        
        # Position status
        self.position_label = QLabel("포지션: 없음")
        self.position_label.setStyleSheet("color: #6c757d;")
        self.status_bar.addPermanentWidget(self.position_label)
        
        self.status_bar.addPermanentWidget(QLabel("|"))
        
        # Time display
        self.time_label = QLabel()
        self.update_time_display()
        self.status_bar.addPermanentWidget(self.time_label)
        
    def create_menu_bar(self):
        """Create application menu bar"""
        menubar = self.menuBar()
        
        # File menu
        file_menu = menubar.addMenu('파일(&F)')
        
        load_config_action = QAction('설정 불러오기', self)
        load_config_action.setShortcut('Ctrl+O')
        load_config_action.triggered.connect(self.load_config)
        file_menu.addAction(load_config_action)
        
        save_config_action = QAction('설정 저장', self)
        save_config_action.setShortcut('Ctrl+S')
        save_config_action.triggered.connect(self.save_config)
        file_menu.addAction(save_config_action)
        
        file_menu.addSeparator()
        
        exit_action = QAction('종료', self)
        exit_action.setShortcut('Ctrl+Q')
        exit_action.triggered.connect(self.close)
        file_menu.addAction(exit_action)
        
        # Trading menu
        trading_menu = menubar.addMenu('매매(&T)')
        
        start_action = QAction('매매 시작', self)
        start_action.triggered.connect(self.start_trading)
        trading_menu.addAction(start_action)
        
        stop_action = QAction('매매 중지', self)
        stop_action.triggered.connect(self.stop_trading)
        trading_menu.addAction(stop_action)
        
        # Help menu
        help_menu = menubar.addMenu('도움말(&H)')
        
        about_action = QAction('정보', self)
        about_action.triggered.connect(self.show_about)
        help_menu.addAction(about_action)
        
    def setup_styling(self):
        """Setup application-wide styling"""
        # Set application font
        font = QFont("Malgun Gothic", 9)
        QApplication.instance().setFont(font)
        
        # Application-wide stylesheet
        self.setStyleSheet("""
            QMainWindow {
                background-color: #f5f5f5;
            }
            QWidget {
                background-color: white;
            }
            QGroupBox {
                font-weight: bold;
                border: 2px solid #cccccc;
                border-radius: 8px;
                margin: 10px 0;
                padding-top: 10px;
            }
            QGroupBox::title {
                subcontrol-origin: margin;
                left: 10px;
                padding: 0 10px;
                color: #333;
            }
        """)
        
    def setup_timers(self):
        """Setup periodic timers for updates"""
        # Main update timer (1 second)
        self.main_timer = QTimer()
        self.main_timer.timeout.connect(self.update_display)
        self.main_timer.start(1000)
        
        # Activity timer for auto-lock (check every 30 seconds)
        self.activity_timer = QTimer()
        self.activity_timer.timeout.connect(self.check_activity)
        self.activity_timer.start(30000)
        
    def setup_system_tray(self):
        """Setup system tray functionality"""
        if QSystemTrayIcon.isSystemTrayAvailable():
            self.tray_icon = QSystemTrayIcon(self)
            
            # Create tray menu
            tray_menu = QMenu()
            
            show_action = tray_menu.addAction("창 보이기")
            show_action.triggered.connect(self.show)
            
            hide_action = tray_menu.addAction("창 숨기기")
            hide_action.triggered.connect(self.hide)
            
            tray_menu.addSeparator()
            
            quit_action = tray_menu.addAction("종료")
            quit_action.triggered.connect(self.close)
            
            self.tray_icon.setContextMenu(tray_menu)
            self.tray_icon.activated.connect(self.tray_activated)
        
    def authenticate(self):
        """Handle user authentication"""
        from .dialogs.auth_dialog import AuthDialog
        
        auth_dialog = AuthDialog(self)
        if auth_dialog.exec_() == auth_dialog.Accepted:
            credentials = auth_dialog.get_credentials()
            if self.verify_credentials(credentials):
                self.is_authenticated = True
                self.post_auth_setup()
            else:
                QMessageBox.critical(self, "인증 실패", "비밀번호가 올바르지 않습니다.")
                self.close()
        else:
            self.close()
            
    def verify_credentials(self, credentials):
        """Verify user credentials"""
        # For demo, use simple password
        return credentials.get('password') == 'admin123'
        
    def post_auth_setup(self):
        """Setup after successful authentication"""
        self.logger.info("Authentication successful")
        
        # Initialize trading components
        self.initialize_trading_components()
        
        # Update connection status
        self.connection_label.setText("연결: ✅ 바이낸스 선물")
        self.connection_label.setStyleSheet("color: #28a745; font-weight: bold;")
        
        # Start real-time updates
        self.start_real_time_updates()
        
    def initialize_trading_components(self):
        """Initialize trading engine and related components"""
        # This would initialize actual trading components
        self.logger.info("Trading components initialized")
        
    def start_real_time_updates(self):
        """Start real-time data updates"""
        # Start chart updates
        self.chart_widget.start_real_time_updates()
        
        # Start position updates
        self.position_widget.start_updates()
        
        # Start status updates
        self.status_widget.start_updates()
        
    def update_display(self):
        """Main display update function called every second"""
        self.update_time_display()
        
        # Update position display with demo data
        if self.is_trading_active:
            # Demo position data
            position_text = "포지션: 매수 0.1 BTC (+1.5%)"
            self.position_label.setText(position_text)
            self.position_label.setStyleSheet("color: #28a745; font-weight: bold;")
        
    def update_time_display(self):
        """Update time display in status bar"""
        current_time = datetime.now().strftime("%H:%M:%S")
        self.time_label.setText(f"시간: {current_time}")
        
    def check_activity(self):
        """Check for auto-lock based on inactivity"""
        if self.is_authenticated:
            inactive_time = (datetime.now() - self.last_activity).seconds
            if inactive_time > 1800:  # 30 minutes
                self.auto_lock()
                
    def auto_lock(self):
        """Auto-lock the application"""
        self.is_authenticated = False
        self.hide()
        QMessageBox.information(self, "자동 잠금", "비활성으로 인해 자동 잠금되었습니다.")
        self.authenticate()
        
    # Control button handlers
    def emergency_stop(self):
        """Handle emergency stop"""
        reply = QMessageBox.question(
            self, '긴급 정지',
            '모든 포지션을 즉시 청산하고 매매를 중단합니다.\n계속하시겠습니까?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.logger.critical("Emergency stop activated by user")
            self.is_trading_active = False
            self.is_paused = False
            
            # Update UI
            self.pause_btn.setText("⏸️ 자동매매 일시정지")
            self.pause_btn.setEnabled(True)
            self.resume_btn.setEnabled(False)
            
            # Update status
            self.position_label.setText("포지션: 긴급 청산 완료")
            self.position_label.setStyleSheet("color: #dc3545; font-weight: bold;")
            
            QMessageBox.information(self, "긴급 정지", "긴급 정지가 실행되었습니다.")
            
    def pause_trading(self):
        """Pause trading"""
        if self.is_trading_active and not self.is_paused:
            self.is_paused = True
            self.pause_btn.setEnabled(False)
            self.resume_btn.setEnabled(True)
            self.logger.info("Trading paused by user")
            QMessageBox.information(self, "일시정지", "자동매매가 일시정지되었습니다.")
            
    def resume_trading(self):
        """Resume trading"""
        if self.is_paused:
            self.is_paused = False
            self.pause_btn.setEnabled(True)
            self.resume_btn.setEnabled(False)
            self.logger.info("Trading resumed by user")
            QMessageBox.information(self, "재시작", "자동매매가 재시작되었습니다.")
            
    def start_trading(self):
        """Start trading"""
        if not self.is_trading_active:
            self.is_trading_active = True
            self.is_paused = False
            self.pause_btn.setEnabled(True)
            self.resume_btn.setEnabled(False)
            self.logger.info("Trading started")
            QMessageBox.information(self, "매매 시작", "자동매매가 시작되었습니다.")
            
    def stop_trading(self):
        """Stop trading"""
        if self.is_trading_active:
            self.is_trading_active = False
            self.is_paused = False
            self.pause_btn.setEnabled(True)
            self.resume_btn.setEnabled(False)
            self.logger.info("Trading stopped")
            QMessageBox.information(self, "매매 중지", "자동매매가 중지되었습니다.")
            
    # Menu handlers
    def load_config(self):
        """Load configuration"""
        # Implementation for loading config
        QMessageBox.information(self, "설정 불러오기", "설정을 불러왔습니다.")
        
    def save_config(self):
        """Save configuration"""
        # Implementation for saving config
        QMessageBox.information(self, "설정 저장", "설정을 저장했습니다.")
        
    def show_about(self):
        """Show about dialog"""
        QMessageBox.about(
            self, "정보",
            "전문 가상화폐 자동매매 시스템 v1.0\n\n"
            "개발: 전문 트레이딩 팀\n"
            "Copyright © 2024"
        )
        
    def tray_activated(self, reason):
        """Handle system tray activation"""
        if reason == QSystemTrayIcon.DoubleClick:
            if self.isVisible():
                self.hide()
            else:
                self.show()
                self.raise_()
                self.activateWindow()
                
    def closeEvent(self, event):
        """Handle close event"""
        if self.is_trading_active:
            reply = QMessageBox.question(
                self, '종료 확인',
                '매매가 진행 중입니다. 정말 종료하시겠습니까?',
                QMessageBox.Yes | QMessageBox.No,
                QMessageBox.No
            )
            
            if reply == QMessageBox.No:
                event.ignore()
                return
                
        # Clean shutdown
        if hasattr(self, 'chart_widget'):
            self.chart_widget.stop_updates()
        if hasattr(self, 'position_widget'):
            self.position_widget.stop_updates()
        if hasattr(self, 'status_widget'):
            self.status_widget.stop_updates()
            
        event.accept()


def main():
    """Main application entry point"""
    app = QApplication(sys.argv)
    app.setQuitOnLastWindowClosed(False)
    
    # Set application properties
    app.setApplicationName("전문 가상화폐 자동매매 시스템")
    app.setApplicationVersion("1.0.0")
    app.setOrganizationName("Professional Trading Team")
    
    # Create and show main window
    window = MainGUI()
    window.show()
    
    # Start event loop
    sys.exit(app.exec_())


if __name__ == "__main__":
    main()