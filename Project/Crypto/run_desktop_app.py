#!/usr/bin/env python3
"""
Desktop Application Launcher
Professional Crypto Trading System - PRD Compliant Desktop EXE Version
"""

import sys
import os
import traceback
from pathlib import Path

# Add project root to path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

# Import required modules
try:
    from PyQt5.QtWidgets import QApplication, QMessageBox, QSplashScreen
    from PyQt5.QtCore import Qt, QTimer
    from PyQt5.QtGui import QPixmap, QFont
    
    # Import our main GUI
    from desktop.main_gui import MainGUI
    
    # Import core modules
    from core.logger import SystemLogger
    
except ImportError as e:
    print(f"Import Error: {e}")
    print("Please ensure all required dependencies are installed:")
    print("pip install PyQt5 pyqtgraph cryptography psutil numpy")
    sys.exit(1)


class SplashScreen(QSplashScreen):
    """Professional splash screen for application startup"""
    
    def __init__(self):
        # Create splash screen without image (text-based)
        super().__init__()
        
        self.setFixedSize(500, 300)
        
        # Set background color
        self.setStyleSheet("""
            QSplashScreen {
                background: qlineargradient(x1: 0, y1: 0, x2: 0, y2: 1,
                                          stop: 0 #007bff, stop: 1 #0056b3);
                border: 3px solid #004085;
                border-radius: 15px;
            }
        """)
        
        # Show splash
        self.show()
        self.showMessage("전문 가상화폐 자동매매 시스템 v1.0", 
                        Qt.AlignCenter | Qt.AlignBottom, Qt.white)
        
    def showMessage(self, message, alignment=Qt.AlignCenter, color=Qt.white):
        """Show message on splash screen"""
        super().showMessage(f"\n\n\n\n{message}\n\n로딩 중...", alignment, color)
        QApplication.processEvents()


def check_system_requirements():
    """Check system requirements"""
    requirements = {
        'python_version': (3, 7),
        'required_modules': ['PyQt5', 'pyqtgraph', 'cryptography', 'numpy']
    }
    
    # Check Python version
    if sys.version_info < requirements['python_version']:
        return False, f"Python {'.'.join(map(str, requirements['python_version']))} 또는 상위 버전이 필요합니다."
    
    # Check required modules
    missing_modules = []
    for module in requirements['required_modules']:
        try:
            __import__(module)
        except ImportError:
            missing_modules.append(module)
    
    if missing_modules:
        return False, f"누락된 모듈: {', '.join(missing_modules)}"
    
    return True, "시스템 요구사항을 모두 만족합니다."


def setup_application():
    """Setup application properties"""
    app = QApplication(sys.argv)
    app.setQuitOnLastWindowClosed(True)
    
    # Set application properties
    app.setApplicationName("전문 가상화폐 자동매매 시스템")
    app.setApplicationVersion("1.0.0")
    app.setApplicationDisplayName("Professional Crypto Trading System")
    app.setOrganizationName("Professional Trading Team")
    app.setOrganizationDomain("crypto-trading.com")
    
    # Set application font
    app.setFont(QFont("Malgun Gothic", 9))
    
    # Set application style
    app.setStyle('Fusion')  # Modern look
    
    return app


def create_error_dialog(title, message, details=None):
    """Create error dialog"""
    app = QApplication(sys.argv) if not QApplication.instance() else QApplication.instance()
    
    error_box = QMessageBox()
    error_box.setIcon(QMessageBox.Critical)
    error_box.setWindowTitle(title)
    error_box.setText(message)
    
    if details:
        error_box.setDetailedText(details)
        
    error_box.setStandardButtons(QMessageBox.Ok)
    error_box.setDefaultButton(QMessageBox.Ok)
    
    return error_box.exec_()


def main():
    """Main application entry point"""
    
    # Set up error handling
    def handle_exception(exc_type, exc_value, exc_traceback):
        """Handle uncaught exceptions"""
        if issubclass(exc_type, KeyboardInterrupt):
            sys.__excepthook__(exc_type, exc_value, exc_traceback)
            return
            
        error_msg = "".join(traceback.format_exception(exc_type, exc_value, exc_traceback))
        print(f"Uncaught exception: {error_msg}")
        
        create_error_dialog(
            "치명적 오류",
            "예상치 못한 오류가 발생했습니다.",
            error_msg
        )
        
    sys.excepthook = handle_exception
    
    try:
        # Check system requirements
        req_ok, req_msg = check_system_requirements()
        if not req_ok:
            create_error_dialog("시스템 요구사항 부족", req_msg)
            return 1
            
        print("🚀 전문 가상화폐 자동매매 시스템 시작")
        print("=" * 60)
        print(f"Python 버전: {sys.version}")
        print(f"작업 디렉토리: {os.getcwd()}")
        print(f"프로젝트 루트: {project_root}")
        print("=" * 60)
        
        # Setup application
        app = setup_application()
        
        # Show splash screen
        splash = SplashScreen()
        splash.showMessage("시스템 초기화 중...")
        
        # Initialize logger
        try:
            logger = SystemLogger(
                name="DesktopApp",
                log_level="INFO",
                console_output=True
            )
            logger.info("Desktop application starting...")
            
        except Exception as e:
            splash.close()
            create_error_dialog("로거 초기화 실패", f"로깅 시스템을 초기화할 수 없습니다: {e}")
            return 1
            
        # Load main window
        splash.showMessage("메인 인터페이스 로딩 중...")
        
        try:
            # Create main window
            main_window = MainGUI()
            
            # Setup window properties for professional look
            main_window.setWindowTitle("전문 가상화폐 자동매매 시스템 v1.0 - Professional Edition")
            
            # Close splash screen
            splash.showMessage("시스템 준비 완료!")
            QTimer.singleShot(1000, splash.close)
            
            # Show main window after splash closes
            QTimer.singleShot(1200, main_window.show)
            
            logger.info("Main window created successfully")
            
        except Exception as e:
            splash.close()
            logger.error(f"Failed to create main window: {e}")
            create_error_dialog(
                "메인 윈도우 생성 실패",
                f"메인 인터페이스를 생성할 수 없습니다: {e}",
                traceback.format_exc()
            )
            return 1
            
        # Start application event loop
        logger.info("Starting application event loop")
        result = app.exec_()
        
        logger.info(f"Application exited with code: {result}")
        return result
        
    except Exception as e:
        error_msg = f"Application startup failed: {e}"
        print(error_msg)
        print(traceback.format_exc())
        
        create_error_dialog(
            "애플리케이션 시작 실패",
            error_msg,
            traceback.format_exc()
        )
        return 1


if __name__ == "__main__":
    # Print startup banner
    print("""
    ╔══════════════════════════════════════════════════════════════╗
    ║              전문 가상화폐 자동매매 시스템 v1.0                 ║
    ║              Professional Crypto Trading System              ║
    ║                                                              ║
    ║  🎯 진입 조건: MA, Price Channel, 호가감지, 캔들패턴             ║
    ║  📈 청산 조건: PCS(12단계), PC트레일링, 호가청산, PC본절        ║
    ║  ⚙️ 시스템: 거래소연동, 시간제어, 리스크관리                   ║
    ║  🔒 보안: 암호화, 인증, 자동잠금, 실시간모니터링                 ║
    ║                                                              ║
    ║  Copyright © 2024 Professional Trading Team                 ║
    ╚══════════════════════════════════════════════════════════════╝
    """)
    
    exit_code = main()
    sys.exit(exit_code)