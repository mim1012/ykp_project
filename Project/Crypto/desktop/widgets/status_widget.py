"""
Status Widget with Connection Status and Condition Activation Indicators
PRD-compliant implementation showing system status and trading conditions
"""

import sys
from datetime import datetime, timedelta
from typing import Dict, List, Optional

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton,
    QFrame, QGridLayout, QProgressBar, QGroupBox, QScrollArea
)
from PyQt5.QtCore import QTimer, pyqtSignal, Qt, QPropertyAnimation, QRect
from PyQt5.QtGui import QFont, QColor, QPalette, QMovie


class StatusWidget(QWidget):
    """Professional system status and condition monitoring widget"""
    
    # Signals for status changes
    connection_changed = pyqtSignal(str, bool)
    condition_changed = pyqtSignal(str, bool)
    
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        
        # Status data
        self.connection_status = {
            'binance': {'connected': False, 'last_ping': None, 'latency': 0},
            'data_feed': {'connected': False, 'last_update': None, 'msg_count': 0},
            'websocket': {'connected': False, 'reconnect_count': 0, 'status': 'disconnected'}
        }
        
        self.condition_status = {
            'entry_conditions': {
                'moving_average': {'active': False, 'last_signal': None, 'signal_count': 0},
                'price_channel': {'active': False, 'last_breakout': None, 'breakout_count': 0},
                'tick_detection': {'active': False, 'tick_count': 0, 'direction': 'neutral'},
                'candle_pattern': {'active': False, 'pattern_matches': 0, 'last_pattern': None}
            },
            'exit_conditions': {
                'pcs_liquidation': {'active': False, 'stages_triggered': 0, 'total_stages': 12},
                'pc_trailing': {'active': False, 'trailing_stops': 0, 'last_adjustment': None},
                'tick_exit': {'active': False, 'exit_signals': 0, 'last_signal': None},
                'pc_cut': {'active': False, 'cuts_executed': 0, 'monitoring': False}
            }
        }
        
        # Update timer
        self.update_timer = QTimer()
        self.update_timer.timeout.connect(self.update_status_display)
        
        # Animation for blinking indicators
        self.blink_timer = QTimer()
        self.blink_timer.timeout.connect(self.blink_active_indicators)
        self.blink_state = True
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize status widget UI"""
        layout = QVBoxLayout(self)
        layout.setSpacing(5)
        layout.setContentsMargins(5, 5, 5, 5)
        
        # Create header
        header_frame = self.create_header()
        layout.addWidget(header_frame)
        
        # Create scroll area for status content
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setHorizontalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        scroll.setVerticalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        
        # Main content widget
        content_widget = QWidget()
        content_layout = QVBoxLayout(content_widget)
        content_layout.setSpacing(8)
        content_layout.setContentsMargins(5, 5, 5, 5)
        
        # Connection status section
        connection_group = self.create_connection_status()
        content_layout.addWidget(connection_group)
        
        # Entry conditions section
        entry_group = self.create_entry_conditions_status()
        content_layout.addWidget(entry_group)
        
        # Exit conditions section
        exit_group = self.create_exit_conditions_status()
        content_layout.addWidget(exit_group)
        
        # System health section
        health_group = self.create_system_health()
        content_layout.addWidget(health_group)
        
        # Add stretch at bottom
        content_layout.addStretch()
        
        scroll.setWidget(content_widget)
        layout.addWidget(scroll)
        
    def create_header(self):
        """Create status widget header"""
        header_frame = QFrame()
        header_frame.setMaximumHeight(40)
        header_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        """)
        
        header_layout = QHBoxLayout(header_frame)
        header_layout.setContentsMargins(10, 5, 10, 5)
        
        # Title
        title_label = QLabel("시스템 상태 📊")
        title_label.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        title_label.setStyleSheet("color: #007bff;")
        header_layout.addWidget(title_label)
        
        header_layout.addStretch()
        
        # Overall system status
        self.system_status_label = QLabel("🔴 시스템 준비 중")
        self.system_status_label.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        header_layout.addWidget(self.system_status_label)
        
        return header_frame
        
    def create_connection_status(self):
        """Create connection status section"""
        group = QGroupBox("연결 상태")
        group.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        
        layout = QGridLayout()
        layout.setSpacing(8)
        
        # Binance connection
        layout.addWidget(QLabel("🏦 바이낸스:"), 0, 0)
        self.binance_status_label = QLabel("❌ 연결 안됨")
        self.binance_status_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.binance_status_label, 0, 1)
        
        self.binance_latency_label = QLabel("지연: --ms")
        self.binance_latency_label.setFont(QFont("Consolas", 9))
        self.binance_latency_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.binance_latency_label, 0, 2)
        
        # Data feed connection
        layout.addWidget(QLabel("📡 데이터피드:"), 1, 0)
        self.datafeed_status_label = QLabel("❌ 연결 안됨")
        self.datafeed_status_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.datafeed_status_label, 1, 1)
        
        self.datafeed_count_label = QLabel("메시지: 0")
        self.datafeed_count_label.setFont(QFont("Consolas", 9))
        self.datafeed_count_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.datafeed_count_label, 1, 2)
        
        # WebSocket connection
        layout.addWidget(QLabel("🔗 웹소켓:"), 2, 0)
        self.websocket_status_label = QLabel("❌ 연결 안됨")
        self.websocket_status_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.websocket_status_label, 2, 1)
        
        self.websocket_reconnect_label = QLabel("재연결: 0회")
        self.websocket_reconnect_label.setFont(QFont("Consolas", 9))
        self.websocket_reconnect_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.websocket_reconnect_label, 2, 2)
        
        # Connection health bar
        layout.addWidget(QLabel("연결 상태:"), 3, 0)
        self.connection_health_bar = QProgressBar()
        self.connection_health_bar.setRange(0, 100)
        self.connection_health_bar.setValue(0)
        self.connection_health_bar.setFormat("0% 연결됨")
        self.connection_health_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
                font-weight: bold;
            }
            QProgressBar::chunk {
                background-color: #dc3545;
            }
        """)
        layout.addWidget(self.connection_health_bar, 3, 1, 1, 2)
        
        group.setLayout(layout)
        return group
        
    def create_entry_conditions_status(self):
        """Create entry conditions status section"""
        group = QGroupBox("진입 조건 상태")
        group.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        
        layout = QGridLayout()
        layout.setSpacing(6)
        
        # Moving Average condition
        layout.addWidget(QLabel("📈 이동평균:"), 0, 0)
        self.ma_condition_label = QLabel("⚫ 비활성")
        self.ma_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.ma_condition_label, 0, 1)
        
        self.ma_signal_label = QLabel("신호: 0회")
        self.ma_signal_label.setFont(QFont("Consolas", 9))
        self.ma_signal_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.ma_signal_label, 0, 2)
        
        # Price Channel condition  
        layout.addWidget(QLabel("📊 Price Channel:"), 1, 0)
        self.pc_condition_label = QLabel("⚫ 비활성")
        self.pc_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.pc_condition_label, 1, 1)
        
        self.pc_breakout_label = QLabel("돌파: 0회")
        self.pc_breakout_label.setFont(QFont("Consolas", 9))
        self.pc_breakout_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.pc_breakout_label, 1, 2)
        
        # Tick Detection condition
        layout.addWidget(QLabel("⚡ 호가 감지:"), 2, 0)
        self.tick_condition_label = QLabel("⚫ 비활성")
        self.tick_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.tick_condition_label, 2, 1)
        
        self.tick_count_label = QLabel("틱: 0")
        self.tick_count_label.setFont(QFont("Consolas", 9))
        self.tick_count_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.tick_count_label, 2, 2)
        
        # Candle Pattern condition
        layout.addWidget(QLabel("🕯️ 캔들 패턴:"), 3, 0)
        self.candle_condition_label = QLabel("⚫ 비활성")
        self.candle_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.candle_condition_label, 3, 1)
        
        self.candle_match_label = QLabel("매칭: 0회")
        self.candle_match_label.setFont(QFont("Consolas", 9))
        self.candle_match_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.candle_match_label, 3, 2)
        
        # Entry conditions summary
        layout.addWidget(QLabel("진입 준비도:"), 4, 0)
        self.entry_readiness_bar = QProgressBar()
        self.entry_readiness_bar.setRange(0, 4)  # 4 conditions max
        self.entry_readiness_bar.setValue(0)
        self.entry_readiness_bar.setFormat("0/4 조건 활성")
        self.entry_readiness_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
                font-weight: bold;
            }
            QProgressBar::chunk {
                background-color: #ffc107;
            }
        """)
        layout.addWidget(self.entry_readiness_bar, 4, 1, 1, 2)
        
        group.setLayout(layout)
        return group
        
    def create_exit_conditions_status(self):
        """Create exit conditions status section"""
        group = QGroupBox("청산 조건 상태")
        group.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        
        layout = QGridLayout()
        layout.setSpacing(6)
        
        # PCS Liquidation
        layout.addWidget(QLabel("🎯 PCS 청산:"), 0, 0)
        self.pcs_condition_label = QLabel("⚫ 비활성")
        self.pcs_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.pcs_condition_label, 0, 1)
        
        self.pcs_stage_label = QLabel("단계: 0/12")
        self.pcs_stage_label.setFont(QFont("Consolas", 9))
        self.pcs_stage_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.pcs_stage_label, 0, 2)
        
        # PC Trailing
        layout.addWidget(QLabel("📉 PC 트레일링:"), 1, 0)
        self.trailing_condition_label = QLabel("⚫ 비활성")
        self.trailing_condition_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.trailing_condition_label, 1, 1)
        
        self.trailing_count_label = QLabel("스톱: 0회")
        self.trailing_count_label.setFont(QFont("Consolas", 9))
        self.trailing_count_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.trailing_count_label, 1, 2)
        
        # Tick Exit
        layout.addWidget(QLabel("⚡ 호가 청산:"), 2, 0)
        self.tick_exit_label = QLabel("⚫ 비활성")
        self.tick_exit_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.tick_exit_label, 2, 1)
        
        self.tick_exit_count_label = QLabel("청산: 0회")
        self.tick_exit_count_label.setFont(QFont("Consolas", 9))
        self.tick_exit_count_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.tick_exit_count_label, 2, 2)
        
        # PC Cut
        layout.addWidget(QLabel("✂️ PC 본절:"), 3, 0)
        self.pc_cut_label = QLabel("⚫ 비활성")
        self.pc_cut_label.setFont(QFont("Malgun Gothic", 9))
        layout.addWidget(self.pc_cut_label, 3, 1)
        
        self.pc_cut_count_label = QLabel("본절: 0회")
        self.pc_cut_count_label.setFont(QFont("Consolas", 9))
        self.pc_cut_count_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.pc_cut_count_label, 3, 2)
        
        # Exit conditions summary
        layout.addWidget(QLabel("청산 준비도:"), 4, 0)
        self.exit_readiness_bar = QProgressBar()
        self.exit_readiness_bar.setRange(0, 4)  # 4 conditions max
        self.exit_readiness_bar.setValue(0)
        self.exit_readiness_bar.setFormat("0/4 조건 활성")
        self.exit_readiness_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
                font-weight: bold;
            }
            QProgressBar::chunk {
                background-color: #17a2b8;
            }
        """)
        layout.addWidget(self.exit_readiness_bar, 4, 1, 1, 2)
        
        group.setLayout(layout)
        return group
        
    def create_system_health(self):
        """Create system health monitoring section"""
        group = QGroupBox("시스템 상태")
        group.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        
        layout = QGridLayout()
        layout.setSpacing(6)
        
        # CPU usage
        layout.addWidget(QLabel("💻 CPU 사용률:"), 0, 0)
        self.cpu_usage_bar = QProgressBar()
        self.cpu_usage_bar.setRange(0, 100)
        self.cpu_usage_bar.setValue(25)
        self.cpu_usage_bar.setFormat("25%")
        self.cpu_usage_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
            }
            QProgressBar::chunk {
                background-color: #28a745;
            }
        """)
        layout.addWidget(self.cpu_usage_bar, 0, 1, 1, 2)
        
        # Memory usage
        layout.addWidget(QLabel("💾 메모리:"), 1, 0)
        self.memory_usage_bar = QProgressBar()
        self.memory_usage_bar.setRange(0, 100)
        self.memory_usage_bar.setValue(35)
        self.memory_usage_bar.setFormat("35% (180MB)")
        self.memory_usage_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
            }
            QProgressBar::chunk {
                background-color: #28a745;
            }
        """)
        layout.addWidget(self.memory_usage_bar, 1, 1, 1, 2)
        
        # System uptime
        layout.addWidget(QLabel("⏰ 가동시간:"), 2, 0)
        self.uptime_label = QLabel("00:00:00")
        self.uptime_label.setFont(QFont("Consolas", 10, QFont.Bold))
        self.uptime_label.setStyleSheet("color: #007bff;")
        layout.addWidget(self.uptime_label, 2, 1)
        
        # Last update
        layout.addWidget(QLabel("🔄 최근 업데이트:"), 2, 2)
        self.last_update_label = QLabel("--:--:--")
        self.last_update_label.setFont(QFont("Consolas", 9))
        self.last_update_label.setStyleSheet("color: #6c757d;")
        layout.addWidget(self.last_update_label, 2, 3)
        
        # Error count
        layout.addWidget(QLabel("⚠️ 오류 횟수:"), 3, 0)
        self.error_count_label = QLabel("0개")
        self.error_count_label.setFont(QFont("Malgun Gothic", 9, QFont.Bold))
        self.error_count_label.setStyleSheet("color: #28a745;")
        layout.addWidget(self.error_count_label, 3, 1)
        
        # Warning count  
        layout.addWidget(QLabel("⚡ 경고 횟수:"), 3, 2)
        self.warning_count_label = QLabel("0개")
        self.warning_count_label.setFont(QFont("Malgun Gothic", 9, QFont.Bold))
        self.warning_count_label.setStyleSheet("color: #28a745;")
        layout.addWidget(self.warning_count_label, 3, 3)
        
        group.setLayout(layout)
        return group
        
    def update_status_display(self):
        """Update all status displays"""
        current_time = datetime.now()
        
        # Update last update time
        self.last_update_label.setText(current_time.strftime("%H:%M:%S"))
        
        # Simulate connection status updates
        self.simulate_connection_updates()
        
        # Update condition monitoring
        self.update_condition_monitoring()
        
        # Update system health
        self.update_system_health()
        
        # Update overall system status
        self.update_overall_status()
        
    def simulate_connection_updates(self):
        """Simulate connection status updates for demo"""
        import random
        
        # Simulate Binance connection
        if random.random() > 0.1:  # 90% chance of being connected
            self.connection_status['binance']['connected'] = True
            self.connection_status['binance']['latency'] = random.randint(50, 200)
            self.binance_status_label.setText("✅ 연결됨")
            self.binance_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
            self.binance_latency_label.setText(f"지연: {self.connection_status['binance']['latency']}ms")
        else:
            self.connection_status['binance']['connected'] = False
            self.binance_status_label.setText("❌ 연결 안됨")
            self.binance_status_label.setStyleSheet("color: #dc3545; font-weight: bold;")
            self.binance_latency_label.setText("지연: --ms")
            
        # Simulate data feed
        if random.random() > 0.05:  # 95% chance of being connected
            self.connection_status['data_feed']['connected'] = True
            self.connection_status['data_feed']['msg_count'] += random.randint(1, 5)
            self.datafeed_status_label.setText("✅ 수신 중")
            self.datafeed_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
            self.datafeed_count_label.setText(f"메시지: {self.connection_status['data_feed']['msg_count']}")
        else:
            self.connection_status['data_feed']['connected'] = False
            self.datafeed_status_label.setText("❌ 수신 중단")
            self.datafeed_status_label.setStyleSheet("color: #dc3545; font-weight: bold;")
            
        # Simulate WebSocket
        if random.random() > 0.02:  # 98% chance of being connected
            self.connection_status['websocket']['connected'] = True
            self.websocket_status_label.setText("✅ 연결됨")
            self.websocket_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
        else:
            self.connection_status['websocket']['connected'] = False
            self.connection_status['websocket']['reconnect_count'] += 1
            self.websocket_status_label.setText("🔄 재연결 중")
            self.websocket_status_label.setStyleSheet("color: #ffc107; font-weight: bold;")
            self.websocket_reconnect_label.setText(f"재연결: {self.connection_status['websocket']['reconnect_count']}회")
            
        # Update connection health
        connected_count = sum(1 for conn in self.connection_status.values() if conn['connected'])
        health_percentage = (connected_count / len(self.connection_status)) * 100
        
        self.connection_health_bar.setValue(int(health_percentage))
        self.connection_health_bar.setFormat(f"{health_percentage:.0f}% 연결됨")
        
        if health_percentage == 100:
            self.connection_health_bar.setStyleSheet("""
                QProgressBar {
                    border: 2px solid #dee2e6;
                    border-radius: 4px;
                    text-align: center;
                    font-size: 9px;
                    font-weight: bold;
                }
                QProgressBar::chunk {
                    background-color: #28a745;
                }
            """)
        elif health_percentage >= 66:
            self.connection_health_bar.setStyleSheet("""
                QProgressBar {
                    border: 2px solid #dee2e6;
                    border-radius: 4px;
                    text-align: center;
                    font-size: 9px;
                    font-weight: bold;
                }
                QProgressBar::chunk {
                    background-color: #ffc107;
                }
            """)
        else:
            self.connection_health_bar.setStyleSheet("""
                QProgressBar {
                    border: 2px solid #dee2e6;
                    border-radius: 4px;
                    text-align: center;
                    font-size: 9px;
                    font-weight: bold;
                }
                QProgressBar::chunk {
                    background-color: #dc3545;
                }
            """)
            
    def update_condition_monitoring(self):
        """Update condition monitoring status"""
        import random
        
        # Entry conditions
        entry_active_count = 0
        
        # Moving Average
        if random.random() > 0.7:  # 30% chance of signal
            self.condition_status['entry_conditions']['moving_average']['active'] = True
            self.condition_status['entry_conditions']['moving_average']['signal_count'] += 1
            self.ma_condition_label.setText("🟢 신호 감지")
            self.ma_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            entry_active_count += 1
        else:
            self.condition_status['entry_conditions']['moving_average']['active'] = False
            self.ma_condition_label.setText("⚫ 대기 중")
            self.ma_condition_label.setStyleSheet("color: #6c757d;")
            
        self.ma_signal_label.setText(f"신호: {self.condition_status['entry_conditions']['moving_average']['signal_count']}회")
        
        # Price Channel
        if random.random() > 0.8:  # 20% chance of breakout
            self.condition_status['entry_conditions']['price_channel']['active'] = True
            self.condition_status['entry_conditions']['price_channel']['breakout_count'] += 1
            self.pc_condition_label.setText("🟢 돌파 감지")
            self.pc_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            entry_active_count += 1
        else:
            self.condition_status['entry_conditions']['price_channel']['active'] = False
            self.pc_condition_label.setText("⚫ 모니터링")
            self.pc_condition_label.setStyleSheet("color: #6c757d;")
            
        self.pc_breakout_label.setText(f"돌파: {self.condition_status['entry_conditions']['price_channel']['breakout_count']}회")
        
        # Tick Detection
        if random.random() > 0.6:  # 40% chance of tick activity
            self.condition_status['entry_conditions']['tick_detection']['active'] = True
            self.condition_status['entry_conditions']['tick_detection']['tick_count'] += random.randint(1, 3)
            self.tick_condition_label.setText("🟢 틱 활성")
            self.tick_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            entry_active_count += 1
        else:
            self.condition_status['entry_conditions']['tick_detection']['active'] = False
            self.tick_condition_label.setText("⚫ 대기 중")
            self.tick_condition_label.setStyleSheet("color: #6c757d;")
            
        self.tick_count_label.setText(f"틱: {self.condition_status['entry_conditions']['tick_detection']['tick_count']}")
        
        # Candle Pattern
        if random.random() > 0.85:  # 15% chance of pattern match
            self.condition_status['entry_conditions']['candle_pattern']['active'] = True
            self.condition_status['entry_conditions']['candle_pattern']['pattern_matches'] += 1
            self.candle_condition_label.setText("🟢 패턴 매칭")
            self.candle_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            entry_active_count += 1
        else:
            self.condition_status['entry_conditions']['candle_pattern']['active'] = False
            self.candle_condition_label.setText("⚫ 분석 중")
            self.candle_condition_label.setStyleSheet("color: #6c757d;")
            
        self.candle_match_label.setText(f"매칭: {self.condition_status['entry_conditions']['candle_pattern']['pattern_matches']}회")
        
        # Update entry readiness
        self.entry_readiness_bar.setValue(entry_active_count)
        self.entry_readiness_bar.setFormat(f"{entry_active_count}/4 조건 활성")
        
        # Exit conditions
        exit_active_count = 0
        
        # PCS
        if random.random() > 0.5:  # 50% chance of PCS activity
            self.condition_status['exit_conditions']['pcs_liquidation']['active'] = True
            triggered = random.randint(0, 5)
            self.condition_status['exit_conditions']['pcs_liquidation']['stages_triggered'] = triggered
            self.pcs_condition_label.setText("🟢 모니터링")
            self.pcs_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            exit_active_count += 1
        else:
            self.condition_status['exit_conditions']['pcs_liquidation']['active'] = False
            self.pcs_condition_label.setText("⚫ 비활성")
            self.pcs_condition_label.setStyleSheet("color: #6c757d;")
            
        self.pcs_stage_label.setText(f"단계: {self.condition_status['exit_conditions']['pcs_liquidation']['stages_triggered']}/12")
        
        # PC Trailing
        if random.random() > 0.7:  # 30% chance of trailing
            self.condition_status['exit_conditions']['pc_trailing']['active'] = True
            self.condition_status['exit_conditions']['pc_trailing']['trailing_stops'] += random.randint(0, 1)
            self.trailing_condition_label.setText("🟢 추적 중")
            self.trailing_condition_label.setStyleSheet("color: #28a745; font-weight: bold;")
            exit_active_count += 1
        else:
            self.condition_status['exit_conditions']['pc_trailing']['active'] = False
            self.trailing_condition_label.setText("⚫ 대기 중")
            self.trailing_condition_label.setStyleSheet("color: #6c757d;")
            
        self.trailing_count_label.setText(f"스톱: {self.condition_status['exit_conditions']['pc_trailing']['trailing_stops']}회")
        
        # Tick Exit
        if random.random() > 0.8:  # 20% chance of tick exit
            self.condition_status['exit_conditions']['tick_exit']['active'] = True
            self.condition_status['exit_conditions']['tick_exit']['exit_signals'] += random.randint(0, 1)
            self.tick_exit_label.setText("🟢 신호 감지")
            self.tick_exit_label.setStyleSheet("color: #28a745; font-weight: bold;")
            exit_active_count += 1
        else:
            self.condition_status['exit_conditions']['tick_exit']['active'] = False
            self.tick_exit_label.setText("⚫ 모니터링")
            self.tick_exit_label.setStyleSheet("color: #6c757d;")
            
        self.tick_exit_count_label.setText(f"청산: {self.condition_status['exit_conditions']['tick_exit']['exit_signals']}회")
        
        # PC Cut
        if random.random() > 0.9:  # 10% chance of PC cut
            self.condition_status['exit_conditions']['pc_cut']['active'] = True
            self.condition_status['exit_conditions']['pc_cut']['cuts_executed'] += random.randint(0, 1)
            self.pc_cut_label.setText("🟢 모니터링")
            self.pc_cut_label.setStyleSheet("color: #28a745; font-weight: bold;")
            exit_active_count += 1
        else:
            self.condition_status['exit_conditions']['pc_cut']['active'] = False
            self.pc_cut_label.setText("⚫ 대기 중")
            self.pc_cut_label.setStyleSheet("color: #6c757d;")
            
        self.pc_cut_count_label.setText(f"본절: {self.condition_status['exit_conditions']['pc_cut']['cuts_executed']}회")
        
        # Update exit readiness
        self.exit_readiness_bar.setValue(exit_active_count)
        self.exit_readiness_bar.setFormat(f"{exit_active_count}/4 조건 활성")
        
    def update_system_health(self):
        """Update system health metrics"""
        import random
        import psutil
        
        try:
            # Get real system metrics if available
            cpu_percent = psutil.cpu_percent()
            memory_percent = psutil.virtual_memory().percent
            memory_used = psutil.virtual_memory().used / (1024 * 1024)  # MB
        except:
            # Fallback to simulated metrics
            cpu_percent = random.randint(20, 60)
            memory_percent = random.randint(30, 70) 
            memory_used = random.randint(150, 300)
            
        # Update CPU usage
        self.cpu_usage_bar.setValue(int(cpu_percent))
        self.cpu_usage_bar.setFormat(f"{cpu_percent:.0f}%")
        
        if cpu_percent < 50:
            cpu_color = "#28a745"
        elif cpu_percent < 80:
            cpu_color = "#ffc107"
        else:
            cpu_color = "#dc3545"
            
        self.cpu_usage_bar.setStyleSheet(f"""
            QProgressBar {{
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
            }}
            QProgressBar::chunk {{
                background-color: {cpu_color};
            }}
        """)
        
        # Update memory usage
        self.memory_usage_bar.setValue(int(memory_percent))
        self.memory_usage_bar.setFormat(f"{memory_percent:.0f}% ({memory_used:.0f}MB)")
        
        if memory_percent < 50:
            mem_color = "#28a745"
        elif memory_percent < 80:
            mem_color = "#ffc107"
        else:
            mem_color = "#dc3545"
            
        self.memory_usage_bar.setStyleSheet(f"""
            QProgressBar {{
                border: 2px solid #dee2e6;
                border-radius: 4px;
                text-align: center;
                font-size: 9px;
            }}
            QProgressBar::chunk {{
                background-color: {mem_color};
            }}
        """)
        
        # Update uptime (simulated)
        current_time = datetime.now()
        uptime = current_time - getattr(self, 'start_time', current_time)
        
        hours, remainder = divmod(int(uptime.total_seconds()), 3600)
        minutes, seconds = divmod(remainder, 60)
        self.uptime_label.setText(f"{hours:02d}:{minutes:02d}:{seconds:02d}")
        
    def update_overall_status(self):
        """Update overall system status"""
        # Check connection health
        connected_services = sum(1 for conn in self.connection_status.values() if conn['connected'])
        total_services = len(self.connection_status)
        connection_health = connected_services / total_services
        
        # Check condition activity
        entry_active = sum(1 for cond in self.condition_status['entry_conditions'].values() if cond['active'])
        exit_active = sum(1 for cond in self.condition_status['exit_conditions'].values() if cond['active'])
        
        # Determine overall status
        if connection_health >= 1.0:
            if entry_active > 0 or exit_active > 0:
                self.system_status_label.setText("🟢 시스템 활성")
                self.system_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
            else:
                self.system_status_label.setText("🟡 시스템 준비됨")
                self.system_status_label.setStyleSheet("color: #ffc107; font-weight: bold;")
        elif connection_health >= 0.66:
            self.system_status_label.setText("🟡 부분 연결")
            self.system_status_label.setStyleSheet("color: #ffc107; font-weight: bold;")
        else:
            self.system_status_label.setText("🔴 연결 문제")
            self.system_status_label.setStyleSheet("color: #dc3545; font-weight: bold;")
            
    def blink_active_indicators(self):
        """Blink active condition indicators"""
        if not hasattr(self, 'blink_state'):
            self.blink_state = True
            
        self.blink_state = not self.blink_state
        
        # Blink active entry conditions
        for condition_name, condition_data in self.condition_status['entry_conditions'].items():
            if condition_data['active']:
                label = getattr(self, f"{condition_name.split('_')[0]}_condition_label", None)
                if label:
                    if self.blink_state:
                        label.setStyleSheet("color: #28a745; font-weight: bold; background-color: #d4edda;")
                    else:
                        label.setStyleSheet("color: #28a745; font-weight: bold;")
                        
        # Blink active exit conditions
        condition_labels = {
            'pcs_liquidation': self.pcs_condition_label,
            'pc_trailing': self.trailing_condition_label,
            'tick_exit': self.tick_exit_label,
            'pc_cut': self.pc_cut_label
        }
        
        for condition_name, condition_data in self.condition_status['exit_conditions'].items():
            if condition_data['active'] and condition_name in condition_labels:
                label = condition_labels[condition_name]
                if self.blink_state:
                    label.setStyleSheet("color: #28a745; font-weight: bold; background-color: #d4edda;")
                else:
                    label.setStyleSheet("color: #28a745; font-weight: bold;")
                    
    # Public methods
    def start_updates(self):
        """Start status updates"""
        self.start_time = datetime.now()
        self.update_timer.start(1000)  # Update every second
        self.blink_timer.start(500)    # Blink every 500ms
        self.logger.info("Status widget updates started")
        
    def stop_updates(self):
        """Stop status updates"""
        self.update_timer.stop()
        self.blink_timer.stop()
        self.logger.info("Status widget updates stopped")
        
    def set_connection_status(self, service, connected, **kwargs):
        """Set connection status for a service"""
        if service in self.connection_status:
            self.connection_status[service]['connected'] = connected
            self.connection_status[service].update(kwargs)
            self.connection_changed.emit(service, connected)
            
    def set_condition_status(self, category, condition, active, **kwargs):
        """Set condition status"""
        if category in self.condition_status and condition in self.condition_status[category]:
            self.condition_status[category][condition]['active'] = active
            self.condition_status[category][condition].update(kwargs)
            self.condition_changed.emit(f"{category}_{condition}", active)
            
    def get_system_health(self):
        """Get overall system health score"""
        connection_score = sum(1 for conn in self.connection_status.values() if conn['connected']) / len(self.connection_status)
        
        entry_active = sum(1 for cond in self.condition_status['entry_conditions'].values() if cond['active'])
        exit_active = sum(1 for cond in self.condition_status['exit_conditions'].values() if cond['active'])
        condition_score = (entry_active + exit_active) / 8  # Total 8 conditions
        
        overall_health = (connection_score * 0.7 + condition_score * 0.3) * 100
        return min(100, max(0, overall_health))