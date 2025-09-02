"""
Entry Conditions Tab
PRD-compliant implementation of entry condition settings
"""

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QGroupBox, QGridLayout,
    QLabel, QComboBox, QSpinBox, QDoubleSpinBox, QCheckBox,
    QPushButton, QFrame, QScrollArea, QButtonGroup, QRadioButton,
    QSlider, QProgressBar
)
from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtGui import QFont


class EntryTab(QWidget):
    """Entry conditions configuration tab"""
    
    # Signals for configuration changes
    config_changed = pyqtSignal(dict)
    
    def __init__(self, config_manager, logger):
        super().__init__()
        self.config = config_manager
        self.logger = logger
        
        # Entry condition settings
        self.entry_conditions = {
            'moving_average': {
                'enabled': False,
                'condition': 'open_above_ma',  # 8 different conditions
                'period': 20,
                'ma_type': 'SMA'
            },
            'price_channel': {
                'enabled': False,
                'breakout_direction': 'upper',  # upper/lower
                'period': 20,
                'offset_percent': 0.1
            },
            'tick_detection': {
                'enabled': False,
                'direction': 'up',  # up/down
                'tick_count': 1,
                'zero_bid_entry': False
            },
            'tick_additional': {
                'enabled': False,
                'up_ticks': 5,
                'down_ticks': 2,
                'additional_percent': 30
            },
            'candle_pattern': {
                'enabled': False,
                'pattern': 'green_candle',  # green_candle/red_candle
                'confirmation_bars': 1
            }
        }
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize the entry tab UI"""
        # Main scroll area for all content
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setHorizontalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        scroll.setVerticalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        
        # Main content widget
        content_widget = QWidget()
        main_layout = QVBoxLayout(content_widget)
        main_layout.setSpacing(15)
        main_layout.setContentsMargins(15, 15, 15, 15)
        
        # Title
        title_label = QLabel("🎯 진입 조건 설정")
        title_label.setFont(QFont("Malgun Gothic", 16, QFont.Bold))
        title_label.setStyleSheet("color: #007bff; margin-bottom: 10px;")
        main_layout.addWidget(title_label)
        
        # Create condition groups
        main_layout.addWidget(self.create_moving_average_group())
        main_layout.addWidget(self.create_price_channel_group())
        main_layout.addWidget(self.create_tick_detection_group())
        main_layout.addWidget(self.create_tick_additional_group())
        main_layout.addWidget(self.create_candle_pattern_group())
        main_layout.addWidget(self.create_control_buttons())
        
        # Add stretch at bottom
        main_layout.addStretch()
        
        # Set scroll widget
        scroll.setWidget(content_widget)
        
        # Main layout
        layout = QVBoxLayout()
        layout.addWidget(scroll)
        self.setLayout(layout)
        
    def create_moving_average_group(self):
        """Create moving average condition group"""
        group = QGroupBox("이동평균선 조건")
        group.setCheckable(True)
        group.setChecked(False)
        group.toggled.connect(lambda checked: self.update_condition('moving_average', 'enabled', checked))
        
        layout = QGridLayout()
        layout.setSpacing(10)
        
        # MA Condition selection (8 types as per PRD)
        layout.addWidget(QLabel("조건 선택:"), 0, 0)
        self.ma_condition_combo = QComboBox()
        self.ma_condition_combo.addItems([
            "시가 > 이평선 (상승 진입)",
            "시가 < 이평선 (하락 진입)", 
            "현재가 > 이평선 (상승 진입)",
            "현재가 < 이평선 (하락 진입)",
            "시가 > 이평선 && 현재가 > 이평선",
            "시가 < 이평선 && 현재가 < 이평선",
            "이평선 골든크로스 진입",
            "이평선 데드크로스 진입"
        ])
        self.ma_condition_combo.currentTextChanged.connect(
            lambda text: self.update_ma_condition(text)
        )
        layout.addWidget(self.ma_condition_combo, 0, 1, 1, 2)
        
        # MA Period
        layout.addWidget(QLabel("이평선 기간:"), 1, 0)
        self.ma_period_spin = QSpinBox()
        self.ma_period_spin.setRange(5, 200)
        self.ma_period_spin.setValue(20)
        self.ma_period_spin.setSuffix(" 봉")
        self.ma_period_spin.valueChanged.connect(
            lambda value: self.update_condition('moving_average', 'period', value)
        )
        layout.addWidget(self.ma_period_spin, 1, 1)
        
        # MA Type
        layout.addWidget(QLabel("이평선 종류:"), 1, 2)
        self.ma_type_combo = QComboBox()
        self.ma_type_combo.addItems(["SMA (단순)", "EMA (지수)", "WMA (가중)", "HMA (Hull)"])
        self.ma_type_combo.currentTextChanged.connect(
            lambda text: self.update_condition('moving_average', 'ma_type', text.split()[0])
        )
        layout.addWidget(self.ma_type_combo, 1, 3)
        
        # Visual indicator
        self.ma_status_label = QLabel("상태: 비활성")
        self.ma_status_label.setStyleSheet("color: #6c757d; font-style: italic;")
        layout.addWidget(self.ma_status_label, 2, 0, 1, 4)
        
        group.setLayout(layout)
        return group
        
    def create_price_channel_group(self):
        """Create Price Channel condition group"""
        group = QGroupBox("Price Channel 조건")
        group.setCheckable(True)
        group.setChecked(False)
        group.toggled.connect(lambda checked: self.update_condition('price_channel', 'enabled', checked))
        
        layout = QGridLayout()
        layout.setSpacing(10)
        
        # Breakout direction
        layout.addWidget(QLabel("돌파 방향:"), 0, 0)
        self.pc_direction_combo = QComboBox()
        self.pc_direction_combo.addItems(["상단 돌파 (매수)", "하단 돌파 (매도)"])
        self.pc_direction_combo.currentTextChanged.connect(self.update_pc_direction)
        layout.addWidget(self.pc_direction_combo, 0, 1)
        
        # PC Period (default 20 days as per PRD)
        layout.addWidget(QLabel("기간 설정:"), 0, 2)
        self.pc_period_spin = QSpinBox()
        self.pc_period_spin.setRange(5, 100)
        self.pc_period_spin.setValue(20)  # PRD default
        self.pc_period_spin.setSuffix(" 일")
        self.pc_period_spin.valueChanged.connect(
            lambda value: self.update_condition('price_channel', 'period', value)
        )
        layout.addWidget(self.pc_period_spin, 0, 3)
        
        # Offset percentage for entry
        layout.addWidget(QLabel("진입 오프셋:"), 1, 0)
        self.pc_offset_spin = QDoubleSpinBox()
        self.pc_offset_spin.setRange(0.0, 2.0)
        self.pc_offset_spin.setValue(0.1)
        self.pc_offset_spin.setSingleStep(0.05)
        self.pc_offset_spin.setSuffix(" %")
        self.pc_offset_spin.valueChanged.connect(
            lambda value: self.update_condition('price_channel', 'offset_percent', value)
        )
        layout.addWidget(self.pc_offset_spin, 1, 1)
        
        # Real-time channel display
        self.pc_display_frame = QFrame()
        self.pc_display_frame.setFrameStyle(QFrame.Box)
        self.pc_display_frame.setMinimumHeight(60)
        self.pc_display_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        """)
        pc_display_layout = QVBoxLayout(self.pc_display_frame)
        pc_display_layout.addWidget(QLabel("실시간 채널 상태"))
        self.pc_status_label = QLabel("상단선: -- | 하단선: -- | 현재가: --")
        self.pc_status_label.setStyleSheet("font-family: monospace; color: #495057;")
        pc_display_layout.addWidget(self.pc_status_label)
        
        layout.addWidget(self.pc_display_frame, 2, 0, 1, 4)
        
        group.setLayout(layout)
        return group
        
    def create_tick_detection_group(self):
        """Create tick detection condition group"""
        group = QGroupBox("호가 감지 조건")
        group.setCheckable(True)
        group.setChecked(False)
        group.toggled.connect(lambda checked: self.update_condition('tick_detection', 'enabled', checked))
        
        layout = QGridLayout()
        layout.setSpacing(10)
        
        # Tick direction
        layout.addWidget(QLabel("틱 방향:"), 0, 0)
        
        # Radio buttons for tick direction
        self.tick_direction_group = QButtonGroup()
        self.tick_up_radio = QRadioButton("상승 틱")
        self.tick_down_radio = QRadioButton("하락 틱")
        self.tick_up_radio.setChecked(True)
        
        self.tick_direction_group.addButton(self.tick_up_radio, 0)
        self.tick_direction_group.addButton(self.tick_down_radio, 1)
        self.tick_direction_group.buttonClicked.connect(self.update_tick_direction)
        
        tick_radio_layout = QHBoxLayout()
        tick_radio_layout.addWidget(self.tick_up_radio)
        tick_radio_layout.addWidget(self.tick_down_radio)
        tick_radio_layout.addStretch()
        
        layout.addLayout(tick_radio_layout, 0, 1, 1, 3)
        
        # Tick count threshold
        layout.addWidget(QLabel("틱 수 설정:"), 1, 0)
        self.tick_count_spin = QSpinBox()
        self.tick_count_spin.setRange(1, 20)
        self.tick_count_spin.setValue(1)
        self.tick_count_spin.setSuffix(" 틱")
        self.tick_count_spin.valueChanged.connect(
            lambda value: self.update_condition('tick_detection', 'tick_count', value)
        )
        layout.addWidget(self.tick_count_spin, 1, 1)
        
        # Zero bid immediate entry (PRD specification)
        self.zero_bid_checkbox = QCheckBox("0호가 즉시 진입")
        self.zero_bid_checkbox.setToolTip("매수/매도 호가가 0이 되면 즉시 진입")
        self.zero_bid_checkbox.toggled.connect(
            lambda checked: self.update_condition('tick_detection', 'zero_bid_entry', checked)
        )
        layout.addWidget(self.zero_bid_checkbox, 1, 2, 1, 2)
        
        # Real-time tick monitor
        self.tick_monitor_frame = QFrame()
        self.tick_monitor_frame.setFrameStyle(QFrame.Box)
        self.tick_monitor_frame.setMinimumHeight(80)
        self.tick_monitor_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        """)
        
        tick_monitor_layout = QVBoxLayout(self.tick_monitor_frame)
        tick_monitor_layout.addWidget(QLabel("실시간 틱 모니터"))
        
        self.tick_counter_label = QLabel("상승틱: 0 | 하락틱: 0")
        self.tick_counter_label.setStyleSheet("font-family: monospace; font-weight: bold;")
        tick_monitor_layout.addWidget(self.tick_counter_label)
        
        # Tick progress bar
        self.tick_progress = QProgressBar()
        self.tick_progress.setRange(0, 10)
        self.tick_progress.setValue(0)
        tick_monitor_layout.addWidget(self.tick_progress)
        
        layout.addWidget(self.tick_monitor_frame, 2, 0, 1, 4)
        
        group.setLayout(layout)
        return group
        
    def create_tick_additional_group(self):
        """Create tick-based additional entry group (PRD specific)"""
        group = QGroupBox("틱 기반 추가 진입 (PRD 명세)")
        group.setCheckable(True)
        group.setChecked(False)
        group.toggled.connect(lambda checked: self.update_condition('tick_additional', 'enabled', checked))
        
        layout = QGridLayout()
        layout.setSpacing(10)
        
        # Description
        desc_label = QLabel("조건: 5틱 상승 후 2틱 하락 시 30% 추가 진입")
        desc_label.setStyleSheet("color: #6c757d; font-style: italic;")
        layout.addWidget(desc_label, 0, 0, 1, 4)
        
        # Up ticks setting
        layout.addWidget(QLabel("상승 틱 수:"), 1, 0)
        self.up_ticks_spin = QSpinBox()
        self.up_ticks_spin.setRange(1, 20)
        self.up_ticks_spin.setValue(5)  # PRD default
        self.up_ticks_spin.setSuffix(" 틱")
        self.up_ticks_spin.valueChanged.connect(
            lambda value: self.update_condition('tick_additional', 'up_ticks', value)
        )
        layout.addWidget(self.up_ticks_spin, 1, 1)
        
        # Down ticks setting
        layout.addWidget(QLabel("하락 틱 수:"), 1, 2)
        self.down_ticks_spin = QSpinBox()
        self.down_ticks_spin.setRange(1, 10)
        self.down_ticks_spin.setValue(2)  # PRD default
        self.down_ticks_spin.setSuffix(" 틱")
        self.down_ticks_spin.valueChanged.connect(
            lambda value: self.update_condition('tick_additional', 'down_ticks', value)
        )
        layout.addWidget(self.down_ticks_spin, 1, 3)
        
        # Additional entry percentage
        layout.addWidget(QLabel("추가 진입 비율:"), 2, 0)
        self.additional_percent_spin = QSpinBox()
        self.additional_percent_spin.setRange(10, 100)
        self.additional_percent_spin.setValue(30)  # PRD default
        self.additional_percent_spin.setSuffix(" %")
        self.additional_percent_spin.valueChanged.connect(
            lambda value: self.update_condition('tick_additional', 'additional_percent', value)
        )
        layout.addWidget(self.additional_percent_spin, 2, 1)
        
        # Status indicator
        self.additional_status_label = QLabel("대기 중...")
        self.additional_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
        layout.addWidget(self.additional_status_label, 2, 2, 1, 2)
        
        group.setLayout(layout)
        return group
        
    def create_candle_pattern_group(self):
        """Create candle pattern condition group"""
        group = QGroupBox("캔들 상태 조건")
        group.setCheckable(True)
        group.setChecked(False)
        group.toggled.connect(lambda checked: self.update_condition('candle_pattern', 'enabled', checked))
        
        layout = QGridLayout()
        layout.setSpacing(10)
        
        # Pattern selection
        layout.addWidget(QLabel("패턴 선택:"), 0, 0)
        self.pattern_combo = QComboBox()
        self.pattern_combo.addItems([
            "양봉 시 매수 진입",
            "음봉 시 매도 진입",
            "연속 양봉 (2개 이상)",
            "연속 음봉 (2개 이상)",
            "도지 캔들 후 방향성",
            "해머 패턴 매수",
            "슈팅스타 패턴 매도"
        ])
        self.pattern_combo.currentTextChanged.connect(self.update_candle_pattern)
        layout.addWidget(self.pattern_combo, 0, 1, 1, 2)
        
        # Confirmation bars
        layout.addWidget(QLabel("확인 봉 수:"), 1, 0)
        self.confirm_bars_spin = QSpinBox()
        self.confirm_bars_spin.setRange(1, 5)
        self.confirm_bars_spin.setValue(1)
        self.confirm_bars_spin.setSuffix(" 봉")
        self.confirm_bars_spin.valueChanged.connect(
            lambda value: self.update_condition('candle_pattern', 'confirmation_bars', value)
        )
        layout.addWidget(self.confirm_bars_spin, 1, 1)
        
        # Real-time candle status
        self.candle_status_label = QLabel("현재 캔들: 대기 중")
        self.candle_status_label.setStyleSheet("color: #495057; font-weight: bold;")
        layout.addWidget(self.candle_status_label, 1, 2, 1, 2)
        
        group.setLayout(layout)
        return group
        
    def create_control_buttons(self):
        """Create control buttons for the entry tab"""
        button_frame = QFrame()
        button_layout = QHBoxLayout(button_frame)
        button_layout.setContentsMargins(0, 10, 0, 10)
        
        # Test conditions button
        self.test_btn = QPushButton("🧪 조건 테스트")
        self.test_btn.setStyleSheet("""
            QPushButton {
                background-color: #17a2b8;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 100px;
            }
            QPushButton:hover {
                background-color: #138496;
            }
        """)
        self.test_btn.clicked.connect(self.test_conditions)
        button_layout.addWidget(self.test_btn)
        
        # Save configuration button
        self.save_btn = QPushButton("💾 설정 저장")
        self.save_btn.setStyleSheet("""
            QPushButton {
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 100px;
            }
            QPushButton:hover {
                background-color: #218838;
            }
        """)
        self.save_btn.clicked.connect(self.save_configuration)
        button_layout.addWidget(self.save_btn)
        
        # Reset to defaults button
        self.reset_btn = QPushButton("🔄 기본값으로")
        self.reset_btn.setStyleSheet("""
            QPushButton {
                background-color: #6c757d;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 100px;
            }
            QPushButton:hover {
                background-color: #5a6268;
            }
        """)
        self.reset_btn.clicked.connect(self.reset_to_defaults)
        button_layout.addWidget(self.reset_btn)
        
        button_layout.addStretch()
        
        # Real-time status indicator
        self.overall_status_label = QLabel("전체 진입 조건: 비활성")
        self.overall_status_label.setStyleSheet("""
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
            border: 2px solid #dc3545;
            border-radius: 4px;
            padding: 5px 10px;
        """)
        button_layout.addWidget(self.overall_status_label)
        
        return button_frame
        
    # Event handlers
    def update_condition(self, category, key, value):
        """Update a specific condition setting"""
        self.entry_conditions[category][key] = value
        self.logger.info(f"Entry condition updated: {category}.{key} = {value}")
        self.update_status_displays()
        self.config_changed.emit(self.entry_conditions)
        
    def update_ma_condition(self, condition_text):
        """Update moving average condition"""
        condition_map = {
            "시가 > 이평선 (상승 진입)": "open_above_ma",
            "시가 < 이평선 (하락 진입)": "open_below_ma",
            "현재가 > 이평선 (상승 진입)": "close_above_ma",
            "현재가 < 이평선 (하락 진입)": "close_below_ma",
            "시가 > 이평선 && 현재가 > 이평선": "both_above_ma",
            "시가 < 이평선 && 현재가 < 이평선": "both_below_ma",
            "이평선 골든크로스 진입": "golden_cross",
            "이평선 데드크로스 진입": "death_cross"
        }
        self.update_condition('moving_average', 'condition', condition_map.get(condition_text, 'open_above_ma'))
        
    def update_pc_direction(self, direction_text):
        """Update price channel direction"""
        direction = 'upper' if '상단' in direction_text else 'lower'
        self.update_condition('price_channel', 'breakout_direction', direction)
        
    def update_tick_direction(self, button):
        """Update tick direction"""
        direction = 'up' if button.text() == '상승 틱' else 'down'
        self.update_condition('tick_detection', 'direction', direction)
        
    def update_candle_pattern(self, pattern_text):
        """Update candle pattern"""
        pattern_map = {
            "양봉 시 매수 진입": "green_candle",
            "음봉 시 매도 진입": "red_candle",
            "연속 양봉 (2개 이상)": "consecutive_green",
            "연속 음봉 (2개 이상)": "consecutive_red",
            "도지 캔들 후 방향성": "doji_direction",
            "해머 패턴 매수": "hammer_buy",
            "슈팅스타 패턴 매도": "shooting_star_sell"
        }
        self.update_condition('candle_pattern', 'pattern', pattern_map.get(pattern_text, 'green_candle'))
        
    def update_status_displays(self):
        """Update all status displays"""
        # Update individual status labels
        if hasattr(self, 'ma_status_label'):
            status = "활성" if self.entry_conditions['moving_average']['enabled'] else "비활성"
            color = "#28a745" if self.entry_conditions['moving_average']['enabled'] else "#6c757d"
            self.ma_status_label.setText(f"상태: {status}")
            self.ma_status_label.setStyleSheet(f"color: {color}; font-style: italic;")
            
        # Update overall status
        active_conditions = sum(1 for condition in self.entry_conditions.values() if condition.get('enabled', False))
        if active_conditions > 0:
            self.overall_status_label.setText(f"전체 진입 조건: 활성 ({active_conditions}개 조건)")
            self.overall_status_label.setStyleSheet("""
                color: #28a745;
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #28a745;
                border-radius: 4px;
                padding: 5px 10px;
            """)
        else:
            self.overall_status_label.setText("전체 진입 조건: 비활성")
            self.overall_status_label.setStyleSheet("""
                color: #dc3545;
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #dc3545;
                border-radius: 4px;
                padding: 5px 10px;
            """)
            
    def test_conditions(self):
        """Test current entry conditions"""
        from PyQt5.QtWidgets import QMessageBox
        
        # Simulate condition testing
        active_conditions = [name for name, config in self.entry_conditions.items() if config.get('enabled', False)]
        
        if not active_conditions:
            QMessageBox.information(self, "테스트 결과", "활성화된 진입 조건이 없습니다.")
            return
            
        test_results = []
        for condition in active_conditions:
            # Simulate test results
            test_results.append(f"✅ {condition}: 조건 만족")
            
        result_text = "진입 조건 테스트 결과:\n\n" + "\n".join(test_results)
        QMessageBox.information(self, "테스트 결과", result_text)
        
    def save_configuration(self):
        """Save current configuration"""
        from PyQt5.QtWidgets import QMessageBox
        
        try:
            # Save to config manager
            self.config.update_section('entry_conditions', self.entry_conditions)
            QMessageBox.information(self, "저장 완료", "진입 조건 설정이 저장되었습니다.")
            self.logger.info("Entry conditions configuration saved")
        except Exception as e:
            QMessageBox.critical(self, "저장 실패", f"설정 저장 중 오류가 발생했습니다:\n{str(e)}")
            self.logger.error(f"Failed to save entry conditions: {e}")
            
    def reset_to_defaults(self):
        """Reset all settings to defaults"""
        from PyQt5.QtWidgets import QMessageBox
        
        reply = QMessageBox.question(
            self, '기본값 복원',
            '모든 설정을 기본값으로 복원하시겠습니까?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.load_default_settings()
            QMessageBox.information(self, "복원 완료", "모든 설정이 기본값으로 복원되었습니다.")
            
    def load_default_settings(self):
        """Load default settings for all controls"""
        # Reset moving average
        self.ma_condition_combo.setCurrentIndex(0)
        self.ma_period_spin.setValue(20)
        self.ma_type_combo.setCurrentIndex(0)
        
        # Reset price channel
        self.pc_direction_combo.setCurrentIndex(0)
        self.pc_period_spin.setValue(20)
        self.pc_offset_spin.setValue(0.1)
        
        # Reset tick detection
        self.tick_up_radio.setChecked(True)
        self.tick_count_spin.setValue(1)
        self.zero_bid_checkbox.setChecked(False)
        
        # Reset tick additional
        self.up_ticks_spin.setValue(5)
        self.down_ticks_spin.setValue(2)
        self.additional_percent_spin.setValue(30)
        
        # Reset candle pattern
        self.pattern_combo.setCurrentIndex(0)
        self.confirm_bars_spin.setValue(1)
        
        # Reset all checkboxes to unchecked
        for child in self.findChildren(QGroupBox):
            if child.isCheckable():
                child.setChecked(False)
                
        self.update_status_displays()
        
    def get_configuration(self):
        """Get current configuration"""
        return self.entry_conditions.copy()
        
    def set_configuration(self, config):
        """Set configuration from external source"""
        self.entry_conditions.update(config)
        self.update_status_displays()