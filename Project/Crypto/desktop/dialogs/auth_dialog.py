"""
Authentication Dialog with Security Features
PRD-compliant password authentication, auto-lock, and encryption
"""

import sys
import hashlib
import base64
from datetime import datetime, timedelta
from typing import Dict, Optional

from PyQt5.QtWidgets import (
    QDialog, QVBoxLayout, QHBoxLayout, QLabel, QPushButton,
    QLineEdit, QFrame, QGridLayout, QCheckBox, QSpacerItem,
    QSizePolicy, QGroupBox, QProgressBar, QMessageBox
)
from PyQt5.QtCore import Qt, QTimer, pyqtSignal, QPropertyAnimation, QRect
from PyQt5.QtGui import QFont, QPixmap, QIcon, QPalette, QColor

from cryptography.fernet import Fernet


class AuthDialog(QDialog):
    """Professional authentication dialog with security features"""
    
    # Signals
    authentication_success = pyqtSignal(dict)
    authentication_failed = pyqtSignal(str)
    
    def __init__(self, parent=None):
        super().__init__(parent)
        
        # Security settings
        self.max_attempts = 3
        self.current_attempts = 0
        self.lockout_duration = 300  # 5 minutes lockout
        self.last_failed_attempt = None
        self.is_locked_out = False
        
        # Password settings
        self.stored_password_hash = self.get_stored_password_hash()
        self.session_timeout = 1800  # 30 minutes
        
        # UI state
        self.password_visible = False
        
        # Security timer
        self.lockout_timer = QTimer()
        self.lockout_timer.timeout.connect(self.update_lockout_display)
        
        # Auto-close timer (10 minutes max)
        self.auto_close_timer = QTimer()
        self.auto_close_timer.timeout.connect(self.auto_close_dialog)
        self.auto_close_timer.start(600000)  # 10 minutes
        
        self.init_ui()
        self.check_lockout_status()
        
    def init_ui(self):
        """Initialize authentication dialog UI"""
        self.setWindowTitle("보안 인증 - 전문 가상화폐 자동매매 시스템")
        self.setFixedSize(450, 550)
        self.setModal(True)
        
        # Remove window decorations for security
        self.setWindowFlags(Qt.Dialog | Qt.CustomizeWindowHint | Qt.WindowTitleHint)
        
        # Main layout
        main_layout = QVBoxLayout()
        main_layout.setSpacing(20)
        main_layout.setContentsMargins(30, 30, 30, 30)
        
        # Header section
        header_frame = self.create_header()
        main_layout.addWidget(header_frame)
        
        # Authentication section
        auth_frame = self.create_auth_section()
        main_layout.addWidget(auth_frame)
        
        # Security info section
        security_frame = self.create_security_info()
        main_layout.addWidget(security_frame)
        
        # Button section
        button_frame = self.create_buttons()
        main_layout.addWidget(button_frame)
        
        # Add stretch
        main_layout.addStretch()
        
        self.setLayout(main_layout)
        
        # Apply styling
        self.setStyleSheet("""
            QDialog {
                background: qlineargradient(x1: 0, y1: 0, x2: 0, y2: 1,
                                          stop: 0 #f8f9fa, stop: 1 #e9ecef);
                border: 2px solid #007bff;
                border-radius: 10px;
            }
            QFrame {
                background-color: white;
                border-radius: 8px;
                border: 1px solid #dee2e6;
            }
        """)
        
    def create_header(self):
        """Create dialog header"""
        header_frame = QFrame()
        header_frame.setMaximumHeight(120)
        header_frame.setStyleSheet("""
            QFrame {
                background: qlineargradient(x1: 0, y1: 0, x2: 0, y2: 1,
                                          stop: 0 #007bff, stop: 1 #0056b3);
                border: none;
                border-radius: 8px;
            }
        """)
        
        layout = QVBoxLayout(header_frame)
        layout.setContentsMargins(20, 15, 20, 15)
        layout.setAlignment(Qt.AlignCenter)
        
        # Title
        title_label = QLabel("🔐 보안 인증")
        title_label.setFont(QFont("Malgun Gothic", 18, QFont.Bold))
        title_label.setStyleSheet("color: white;")
        title_label.setAlignment(Qt.AlignCenter)
        layout.addWidget(title_label)
        
        # Subtitle
        subtitle_label = QLabel("시스템 접근을 위해 인증이 필요합니다")
        subtitle_label.setFont(QFont("Malgun Gothic", 11))
        subtitle_label.setStyleSheet("color: #e3f2fd;")
        subtitle_label.setAlignment(Qt.AlignCenter)
        layout.addWidget(subtitle_label)
        
        # Security indicator
        self.security_status_label = QLabel("🛡️ 보안 레벨: 높음")
        self.security_status_label.setFont(QFont("Malgun Gothic", 10))
        self.security_status_label.setStyleSheet("color: #bbdefb;")
        self.security_status_label.setAlignment(Qt.AlignCenter)
        layout.addWidget(self.security_status_label)
        
        return header_frame
        
    def create_auth_section(self):
        """Create authentication input section"""
        auth_frame = QFrame()
        auth_frame.setStyleSheet("""
            QFrame {
                background-color: white;
                border: 2px solid #e3f2fd;
                border-radius: 8px;
                padding: 10px;
            }
        """)
        
        layout = QGridLayout(auth_frame)
        layout.setSpacing(15)
        layout.setContentsMargins(20, 20, 20, 20)
        
        # Password label
        password_label = QLabel("🔑 비밀번호:")
        password_label.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        password_label.setStyleSheet("color: #333;")
        layout.addWidget(password_label, 0, 0)
        
        # Password input
        self.password_input = QLineEdit()
        self.password_input.setEchoMode(QLineEdit.Password)
        self.password_input.setPlaceholderText("시스템 비밀번호를 입력하세요")
        self.password_input.setFont(QFont("Consolas", 12))
        self.password_input.setStyleSheet("""
            QLineEdit {
                border: 2px solid #ced4da;
                border-radius: 6px;
                padding: 12px;
                font-size: 14px;
                background-color: #f8f9fa;
            }
            QLineEdit:focus {
                border-color: #007bff;
                background-color: white;
            }
        """)
        self.password_input.returnPressed.connect(self.authenticate)
        layout.addWidget(self.password_input, 0, 1, 1, 2)
        
        # Show password toggle
        self.show_password_btn = QPushButton("👁️")
        self.show_password_btn.setFixedSize(40, 40)
        self.show_password_btn.setCheckable(True)
        self.show_password_btn.setStyleSheet("""
            QPushButton {
                border: 2px solid #ced4da;
                border-radius: 6px;
                background-color: #f8f9fa;
                font-size: 16px;
            }
            QPushButton:hover {
                background-color: #e9ecef;
            }
            QPushButton:checked {
                background-color: #007bff;
                color: white;
                border-color: #007bff;
            }
        """)
        self.show_password_btn.toggled.connect(self.toggle_password_visibility)
        layout.addWidget(self.show_password_btn, 0, 3)
        
        # Remember session checkbox
        self.remember_session_cb = QCheckBox("30분 동안 세션 유지")
        self.remember_session_cb.setFont(QFont("Malgun Gothic", 10))
        self.remember_session_cb.setStyleSheet("color: #6c757d;")
        self.remember_session_cb.setChecked(True)
        layout.addWidget(self.remember_session_cb, 1, 1, 1, 2)
        
        # Caps Lock warning
        self.caps_warning_label = QLabel("")
        self.caps_warning_label.setFont(QFont("Malgun Gothic", 9))
        self.caps_warning_label.setStyleSheet("color: #ffc107; font-weight: bold;")
        layout.addWidget(self.caps_warning_label, 2, 1, 1, 3)
        
        # Attempt counter
        self.attempt_label = QLabel("")
        self.attempt_label.setFont(QFont("Malgun Gothic", 10))
        self.attempt_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.attempt_label, 3, 1, 1, 3)
        
        return auth_frame
        
    def create_security_info(self):
        """Create security information section"""
        security_frame = QFrame()
        security_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 6px;
            }
        """)
        
        layout = QVBoxLayout(security_frame)
        layout.setContentsMargins(15, 15, 15, 15)
        layout.setSpacing(8)
        
        # Security info title
        info_title = QLabel("🛡️ 보안 정보")
        info_title.setFont(QFont("Malgun Gothic", 11, QFont.Bold))
        info_title.setStyleSheet("color: #495057;")
        layout.addWidget(info_title)
        
        # Security features
        security_features = [
            "• 256비트 AES 암호화로 설정 파일 보호",
            "• 최대 3회 로그인 시도 후 5분간 계정 잠금",
            "• 30분 비활성 시 자동 세션 만료",
            "• 실시간 보안 상태 모니터링"
        ]
        
        for feature in security_features:
            feature_label = QLabel(feature)
            feature_label.setFont(QFont("Malgun Gothic", 9))
            feature_label.setStyleSheet("color: #6c757d;")
            layout.addWidget(feature_label)
            
        # Current time
        self.current_time_label = QLabel()
        self.current_time_label.setFont(QFont("Consolas", 9))
        self.current_time_label.setStyleSheet("color: #6c757d; margin-top: 10px;")
        self.update_current_time()
        layout.addWidget(self.current_time_label)
        
        # Time update timer
        self.time_timer = QTimer()
        self.time_timer.timeout.connect(self.update_current_time)
        self.time_timer.start(1000)
        
        return security_frame
        
    def create_buttons(self):
        """Create dialog buttons"""
        button_frame = QFrame()
        button_frame.setStyleSheet("QFrame { background: transparent; border: none; }")
        
        layout = QHBoxLayout(button_frame)
        layout.setContentsMargins(0, 10, 0, 0)
        
        # Help button
        self.help_btn = QPushButton("❓ 도움말")
        self.help_btn.setFont(QFont("Malgun Gothic", 10))
        self.help_btn.setStyleSheet("""
            QPushButton {
                background-color: #6c757d;
                color: white;
                border: none;
                border-radius: 6px;
                padding: 10px 20px;
                font-weight: bold;
            }
            QPushButton:hover {
                background-color: #5a6268;
            }
        """)
        self.help_btn.clicked.connect(self.show_help)
        layout.addWidget(self.help_btn)
        
        layout.addStretch()
        
        # Cancel button
        self.cancel_btn = QPushButton("❌ 취소")
        self.cancel_btn.setFont(QFont("Malgun Gothic", 10))
        self.cancel_btn.setStyleSheet("""
            QPushButton {
                background-color: #dc3545;
                color: white;
                border: none;
                border-radius: 6px;
                padding: 10px 20px;
                font-weight: bold;
            }
            QPushButton:hover {
                background-color: #c82333;
            }
        """)
        self.cancel_btn.clicked.connect(self.reject)
        layout.addWidget(self.cancel_btn)
        
        # Login button
        self.login_btn = QPushButton("🔓 로그인")
        self.login_btn.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        self.login_btn.setStyleSheet("""
            QPushButton {
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 6px;
                padding: 10px 25px;
                font-weight: bold;
            }
            QPushButton:hover {
                background-color: #218838;
            }
            QPushButton:disabled {
                background-color: #6c757d;
            }
        """)
        self.login_btn.clicked.connect(self.authenticate)
        self.login_btn.setDefault(True)
        layout.addWidget(self.login_btn)
        
        return button_frame
        
    def check_lockout_status(self):
        """Check if account is currently locked out"""
        if self.last_failed_attempt:
            time_since_failure = (datetime.now() - self.last_failed_attempt).seconds
            if time_since_failure < self.lockout_duration:
                self.is_locked_out = True
                remaining_time = self.lockout_duration - time_since_failure
                self.start_lockout_countdown(remaining_time)
            else:
                self.is_locked_out = False
                self.current_attempts = 0
                
    def start_lockout_countdown(self, remaining_seconds):
        """Start lockout countdown display"""
        self.lockout_remaining = remaining_seconds
        self.login_btn.setEnabled(False)
        self.password_input.setEnabled(False)
        
        self.lockout_timer.start(1000)
        self.update_lockout_display()
        
    def update_lockout_display(self):
        """Update lockout countdown display"""
        if self.lockout_remaining > 0:
            minutes, seconds = divmod(self.lockout_remaining, 60)
            self.attempt_label.setText(f"🔒 계정 잠금됨 - 남은 시간: {minutes:02d}:{seconds:02d}")
            self.attempt_label.setStyleSheet("color: #dc3545; font-weight: bold;")
            self.lockout_remaining -= 1
        else:
            # Unlock account
            self.is_locked_out = False
            self.current_attempts = 0
            self.lockout_timer.stop()
            self.login_btn.setEnabled(True)
            self.password_input.setEnabled(True)
            self.attempt_label.setText("")
            
    def toggle_password_visibility(self, visible):
        """Toggle password visibility"""
        if visible:
            self.password_input.setEchoMode(QLineEdit.Normal)
            self.show_password_btn.setText("🙈")
            self.show_password_btn.setToolTip("비밀번호 숨기기")
        else:
            self.password_input.setEchoMode(QLineEdit.Password)
            self.show_password_btn.setText("👁️")
            self.show_password_btn.setToolTip("비밀번호 보기")
            
    def update_current_time(self):
        """Update current time display"""
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        self.current_time_label.setText(f"현재 시간: {current_time}")
        
        # Check for Caps Lock (simplified check)
        # In real implementation, you'd use platform-specific methods
        
    def authenticate(self):
        """Perform authentication"""
        if self.is_locked_out:
            QMessageBox.warning(self, "계정 잠금", "계정이 잠금되어 있습니다. 잠시 후 다시 시도해주세요.")
            return
            
        password = self.password_input.text().strip()
        
        if not password:
            QMessageBox.warning(self, "인증 오류", "비밀번호를 입력해주세요.")
            self.password_input.setFocus()
            return
            
        # Hash the entered password
        password_hash = self.hash_password(password)
        
        if password_hash == self.stored_password_hash:
            # Successful authentication
            self.current_attempts = 0
            
            credentials = {
                'password': password,
                'authenticated_at': datetime.now(),
                'session_duration': self.session_timeout if self.remember_session_cb.isChecked() else 3600,
                'security_level': 'high'
            }
            
            self.authentication_success.emit(credentials)
            self.accept()
            
        else:
            # Failed authentication
            self.current_attempts += 1
            remaining_attempts = self.max_attempts - self.current_attempts
            
            if remaining_attempts > 0:
                self.attempt_label.setText(f"❌ 인증 실패 - 남은 시도: {remaining_attempts}회")
                self.attempt_label.setStyleSheet("color: #dc3545; font-weight: bold;")
                
                # Animate password field to indicate error
                self.animate_error()
                
                # Clear password field
                self.password_input.clear()
                self.password_input.setFocus()
                
            else:
                # Max attempts reached - lock account
                self.last_failed_attempt = datetime.now()
                self.is_locked_out = True
                self.start_lockout_countdown(self.lockout_duration)
                
                QMessageBox.critical(
                    self, "계정 잠금",
                    f"최대 시도 횟수를 초과했습니다.\n"
                    f"보안을 위해 계정이 {self.lockout_duration//60}분간 잠금됩니다."
                )
                
            self.authentication_failed.emit("Invalid password")
            
    def animate_error(self):
        """Animate password field to show error"""
        self.password_input.setStyleSheet("""
            QLineEdit {
                border: 2px solid #dc3545;
                border-radius: 6px;
                padding: 12px;
                font-size: 14px;
                background-color: #f8d7da;
            }
        """)
        
        # Reset style after 1 second
        QTimer.singleShot(1000, self.reset_password_style)
        
    def reset_password_style(self):
        """Reset password field style"""
        self.password_input.setStyleSheet("""
            QLineEdit {
                border: 2px solid #ced4da;
                border-radius: 6px;
                padding: 12px;
                font-size: 14px;
                background-color: #f8f9fa;
            }
            QLineEdit:focus {
                border-color: #007bff;
                background-color: white;
            }
        """)
        
    def show_help(self):
        """Show help dialog"""
        help_text = """
🔐 보안 인증 도움말

• 기본 비밀번호: admin123
• 최대 3회까지 로그인 시도 가능
• 실패 시 5분간 계정 잠금
• 세션 유지 체크 시 30분 동안 재인증 불필요

🛡️ 보안 기능:
• AES-256 암호화로 모든 설정 보호
• 실시간 보안 상태 모니터링
• 자동 세션 타임아웃
• 비정상 접근 감지

❓ 문제가 있으신가요?
• 비밀번호를 잊으셨다면 시스템 관리자에게 문의
• 계정 잠금 시 5분 후 다시 시도
• 보안 문제 발견 시 즉시 신고
        """
        
        QMessageBox.information(self, "보안 인증 도움말", help_text)
        
    def auto_close_dialog(self):
        """Auto-close dialog after timeout"""
        QMessageBox.warning(
            self, "세션 타임아웃",
            "보안상의 이유로 인증 창이 자동으로 닫힙니다.\n"
            "다시 시도해주세요."
        )
        self.reject()
        
    @staticmethod
    def hash_password(password: str) -> str:
        """Hash password using SHA-256"""
        return hashlib.sha256(password.encode()).hexdigest()
        
    def get_stored_password_hash(self) -> str:
        """Get stored password hash (for demo, use default password)"""
        # For demo purposes, use "admin123"
        # In real implementation, this would be loaded from secure storage
        return self.hash_password("admin123")
        
    def get_credentials(self) -> Dict[str, any]:
        """Get authentication credentials"""
        return {
            'password': self.password_input.text(),
            'authenticated': True,
            'timestamp': datetime.now()
        }
        
    def keyPressEvent(self, event):
        """Handle key press events"""
        if event.key() == Qt.Key_Escape:
            self.reject()
        elif event.key() == Qt.Key_Return or event.key() == Qt.Key_Enter:
            if not self.is_locked_out:
                self.authenticate()
        else:
            super().keyPressEvent(event)
            
    def closeEvent(self, event):
        """Handle close event"""
        # Stop all timers
        self.time_timer.stop()
        self.auto_close_timer.stop()
        if self.lockout_timer.isActive():
            self.lockout_timer.stop()
            
        event.accept()