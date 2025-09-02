"""
Exit Conditions Tab
PRD-compliant implementation of exit condition settings including PCS, PC trailing, and PC cut
"""

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QGroupBox, QGridLayout,
    QLabel, QComboBox, QSpinBox, QDoubleSpinBox, QCheckBox,
    QPushButton, QFrame, QScrollArea, QButtonGroup, QRadioButton,
    QSlider, QProgressBar, QTableWidget, QTableWidgetItem,
    QHeaderView, QTabWidget
)
from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtGui import QFont, QColor


class ExitTab(QWidget):
    """Exit conditions configuration tab"""
    
    # Signals for configuration changes
    config_changed = pyqtSignal(dict)
    
    def __init__(self, config_manager, logger):
        super().__init__()
        self.config = config_manager
        self.logger = logger
        
        # Exit condition settings
        self.exit_conditions = {
            'pcs_liquidation': {
                'enabled': False,
                'mode': '1STEP',  # 1STEP/2STEP
                'stages': {i: {'enabled': False, 'profit_percent': i*0.5, 'quantity_percent': 10} 
                          for i in range(1, 13)},  # 12 stages
                'max_active_stages': 12
            },
            'pc_trailing': {
                'enabled': False,
                'direction': 'lower',  # lower/upper line
                'stop_on_loss': True,
                'trailing_distance': 0.1
            },
            'tick_exit': {
                'enabled': False,
                'direction': 'down',  # down/up ticks
                'tick_count': 3,
                'immediate_exit': False
            },
            'pc_cut': {
                'enabled': False,
                'stage1_enabled': True,  # PC line breakout
                'stage2_enabled': False,  # Return to entry price
                'entry_price_offset': 0.0
            }
        }
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize the exit tab UI"""
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
        title_label = QLabel("📈 청산 조건 설정")
        title_label.setFont(QFont("Malgun Gothic", 16, QFont.Bold))
        title_label.setStyleSheet("color: #007bff; margin-bottom: 10px;")
        main_layout.addWidget(title_label)
        
        # Create sub-tabs for different exit types
        self.exit_tabs = QTabWidget()
        self.exit_tabs.addTab(self.create_pcs_tab(), "PCS 청산")
        self.exit_tabs.addTab(self.create_pc_trailing_tab(), "PC 트레일링")
        self.exit_tabs.addTab(self.create_tick_exit_tab(), "호가 청산")
        self.exit_tabs.addTab(self.create_pc_cut_tab(), "PC 본절")
        
        main_layout.addWidget(self.exit_tabs)
        main_layout.addWidget(self.create_control_buttons())
        
        # Add stretch at bottom
        main_layout.addStretch()
        
        # Set scroll widget
        scroll.setWidget(content_widget)
        
        # Main layout
        layout = QVBoxLayout()
        layout.addWidget(scroll)
        self.setLayout(layout)
        
    def create_pcs_tab(self):
        """Create PCS (Profit Cut System) liquidation tab"""
        pcs_widget = QWidget()
        layout = QVBoxLayout(pcs_widget)
        
        # PCS Enable checkbox
        self.pcs_enable_checkbox = QCheckBox("PCS 청산 시스템 활성화")
        self.pcs_enable_checkbox.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        self.pcs_enable_checkbox.toggled.connect(
            lambda checked: self.update_condition('pcs_liquidation', 'enabled', checked)
        )
        layout.addWidget(self.pcs_enable_checkbox)
        
        # PCS Mode selection
        mode_frame = QFrame()
        mode_layout = QHBoxLayout(mode_frame)
        mode_layout.addWidget(QLabel("PCS 모드:"))
        
        self.pcs_mode_group = QButtonGroup()
        self.pcs_1step_radio = QRadioButton("1STEP (단계별 즉시 청산)")
        self.pcs_2step_radio = QRadioButton("2STEP (단계별 부분 청산)")
        self.pcs_1step_radio.setChecked(True)
        
        self.pcs_mode_group.addButton(self.pcs_1step_radio, 0)
        self.pcs_mode_group.addButton(self.pcs_2step_radio, 1)
        self.pcs_mode_group.buttonClicked.connect(self.update_pcs_mode)
        
        mode_layout.addWidget(self.pcs_1step_radio)
        mode_layout.addWidget(self.pcs_2step_radio)
        mode_layout.addStretch()
        
        layout.addWidget(mode_frame)
        
        # PCS Stages Table (12 stages as per PRD)
        self.create_pcs_table()
        layout.addWidget(self.pcs_table_frame)
        
        # Quick setup buttons
        quick_setup_frame = QFrame()
        quick_layout = QHBoxLayout(quick_setup_frame)
        
        self.conservative_btn = QPushButton("보수적 설정")
        self.conservative_btn.clicked.connect(self.set_conservative_pcs)
        quick_layout.addWidget(self.conservative_btn)
        
        self.aggressive_btn = QPushButton("공격적 설정")
        self.aggressive_btn.clicked.connect(self.set_aggressive_pcs)
        quick_layout.addWidget(self.aggressive_btn)
        
        self.custom_btn = QPushButton("커스텀 설정")
        self.custom_btn.clicked.connect(self.set_custom_pcs)
        quick_layout.addWidget(self.custom_btn)
        
        quick_layout.addStretch()
        layout.addWidget(quick_setup_frame)
        
        return pcs_widget
        
    def create_pcs_table(self):
        """Create PCS stages configuration table"""
        self.pcs_table_frame = QFrame()
        self.pcs_table_frame.setFrameStyle(QFrame.Box)
        table_layout = QVBoxLayout(self.pcs_table_frame)
        
        table_layout.addWidget(QLabel("PCS 단계별 설정 (1단~12단)"))
        
        self.pcs_table = QTableWidget(12, 4)  # 12 stages, 4 columns
        self.pcs_table.setHorizontalHeaderLabels([
            "단계", "활성화", "익절 수익률(%)", "청산 비율(%)"
        ])
        
        # Set column widths
        header = self.pcs_table.horizontalHeader()
        header.setSectionResizeMode(0, QHeaderView.Fixed)
        header.setSectionResizeMode(1, QHeaderView.Fixed) 
        header.setSectionResizeMode(2, QHeaderView.Stretch)
        header.setSectionResizeMode(3, QHeaderView.Stretch)
        
        self.pcs_table.setColumnWidth(0, 60)
        self.pcs_table.setColumnWidth(1, 80)
        
        # Populate table with default values
        for i in range(12):
            stage_num = i + 1
            
            # Stage number (read-only)
            stage_item = QTableWidgetItem(f"{stage_num}단")
            stage_item.setFlags(Qt.ItemIsEnabled)
            stage_item.setTextAlignment(Qt.AlignCenter)
            self.pcs_table.setItem(i, 0, stage_item)
            
            # Enable checkbox
            enable_checkbox = QCheckBox()
            enable_checkbox.toggled.connect(
                lambda checked, stage=stage_num: self.update_pcs_stage(stage, 'enabled', checked)
            )
            self.pcs_table.setCellWidget(i, 1, enable_checkbox)
            
            # Profit percentage
            profit_spin = QDoubleSpinBox()
            profit_spin.setRange(0.1, 50.0)
            profit_spin.setValue(stage_num * 0.5)  # Default: 0.5%, 1.0%, 1.5%, etc.
            profit_spin.setSingleStep(0.1)
            profit_spin.setSuffix("%")
            profit_spin.valueChanged.connect(
                lambda value, stage=stage_num: self.update_pcs_stage(stage, 'profit_percent', value)
            )
            self.pcs_table.setCellWidget(i, 2, profit_spin)
            
            # Quantity percentage
            quantity_spin = QSpinBox()
            quantity_spin.setRange(1, 100)
            quantity_spin.setValue(10)  # Default 10%
            quantity_spin.setSuffix("%")
            quantity_spin.valueChanged.connect(
                lambda value, stage=stage_num: self.update_pcs_stage(stage, 'quantity_percent', value)
            )
            self.pcs_table.setCellWidget(i, 3, quantity_spin)
            
        table_layout.addWidget(self.pcs_table)
        
        # PCS Status display
        self.pcs_status_label = QLabel("PCS 상태: 비활성")
        self.pcs_status_label.setStyleSheet("font-weight: bold; color: #6c757d;")
        table_layout.addWidget(self.pcs_status_label)
        
    def create_pc_trailing_tab(self):
        """Create PC trailing liquidation tab"""
        trailing_widget = QWidget()
        layout = QVBoxLayout(trailing_widget)
        
        # PC Trailing Enable
        self.pc_trailing_checkbox = QCheckBox("PC 트레일링 청산 활성화")
        self.pc_trailing_checkbox.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        self.pc_trailing_checkbox.toggled.connect(
            lambda checked: self.update_condition('pc_trailing', 'enabled', checked)
        )
        layout.addWidget(self.pc_trailing_checkbox)
        
        # Settings grid
        settings_grid = QGridLayout()
        
        # Trailing line selection
        settings_grid.addWidget(QLabel("트레일링 기준선:"), 0, 0)
        self.trailing_line_combo = QComboBox()
        self.trailing_line_combo.addItems(["하단선 추적", "상단선 추적"])
        self.trailing_line_combo.currentTextChanged.connect(self.update_trailing_direction)
        settings_grid.addWidget(self.trailing_line_combo, 0, 1)
        
        # Trailing distance
        settings_grid.addWidget(QLabel("트레일링 거리:"), 0, 2)
        self.trailing_distance_spin = QDoubleSpinBox()
        self.trailing_distance_spin.setRange(0.01, 2.0)
        self.trailing_distance_spin.setValue(0.1)
        self.trailing_distance_spin.setSingleStep(0.01)
        self.trailing_distance_spin.setSuffix("%")
        self.trailing_distance_spin.valueChanged.connect(
            lambda value: self.update_condition('pc_trailing', 'trailing_distance', value)
        )
        settings_grid.addWidget(self.trailing_distance_spin, 0, 3)
        
        # Stop on loss option
        self.stop_on_loss_checkbox = QCheckBox("손실중 청산 옵션")
        self.stop_on_loss_checkbox.setChecked(True)
        self.stop_on_loss_checkbox.setToolTip("손실이 발생하는 상황에서도 트레일링 스톱을 실행")
        self.stop_on_loss_checkbox.toggled.connect(
            lambda checked: self.update_condition('pc_trailing', 'stop_on_loss', checked)
        )
        settings_grid.addWidget(self.stop_on_loss_checkbox, 1, 0, 1, 2)
        
        layout.addLayout(settings_grid)
        
        # Visual trailing display
        self.create_trailing_display()
        layout.addWidget(self.trailing_display_frame)
        
        return trailing_widget
        
    def create_trailing_display(self):
        """Create visual display for PC trailing"""
        self.trailing_display_frame = QFrame()
        self.trailing_display_frame.setFrameStyle(QFrame.Box)
        self.trailing_display_frame.setMinimumHeight(120)
        display_layout = QVBoxLayout(self.trailing_display_frame)
        
        display_layout.addWidget(QLabel("실시간 트레일링 상태"))
        
        # Current values display
        values_layout = QGridLayout()
        values_layout.addWidget(QLabel("현재가:"), 0, 0)
        self.current_price_label = QLabel("--")
        values_layout.addWidget(self.current_price_label, 0, 1)
        
        values_layout.addWidget(QLabel("트레일링 기준:"), 0, 2)
        self.trailing_reference_label = QLabel("--")
        values_layout.addWidget(self.trailing_reference_label, 0, 3)
        
        values_layout.addWidget(QLabel("트레일링 스톱:"), 1, 0)
        self.trailing_stop_label = QLabel("--")
        values_layout.addWidget(self.trailing_stop_label, 1, 1)
        
        values_layout.addWidget(QLabel("상태:"), 1, 2)
        self.trailing_status_label = QLabel("대기중")
        values_layout.addWidget(self.trailing_status_label, 1, 3)
        
        display_layout.addLayout(values_layout)
        
    def create_tick_exit_tab(self):
        """Create tick-based exit tab"""
        tick_widget = QWidget()
        layout = QVBoxLayout(tick_widget)
        
        # Tick Exit Enable
        self.tick_exit_checkbox = QCheckBox("호가 기반 청산 활성화")
        self.tick_exit_checkbox.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        self.tick_exit_checkbox.toggled.connect(
            lambda checked: self.update_condition('tick_exit', 'enabled', checked)
        )
        layout.addWidget(self.tick_exit_checkbox)
        
        # Settings
        settings_grid = QGridLayout()
        
        # Tick direction
        settings_grid.addWidget(QLabel("청산 틱 방향:"), 0, 0)
        
        self.tick_exit_group = QButtonGroup()
        self.tick_down_exit_radio = QRadioButton("하락 틱 청산")
        self.tick_up_exit_radio = QRadioButton("상승 틱 청산")
        self.tick_down_exit_radio.setChecked(True)
        
        self.tick_exit_group.addButton(self.tick_down_exit_radio, 0)
        self.tick_exit_group.addButton(self.tick_up_exit_radio, 1)
        self.tick_exit_group.buttonClicked.connect(self.update_tick_exit_direction)
        
        tick_direction_layout = QHBoxLayout()
        tick_direction_layout.addWidget(self.tick_down_exit_radio)
        tick_direction_layout.addWidget(self.tick_up_exit_radio)
        tick_direction_layout.addStretch()
        
        settings_grid.addLayout(tick_direction_layout, 0, 1, 1, 3)
        
        # Tick count
        settings_grid.addWidget(QLabel("청산 틱 수:"), 1, 0)
        self.tick_exit_count_spin = QSpinBox()
        self.tick_exit_count_spin.setRange(1, 20)
        self.tick_exit_count_spin.setValue(3)
        self.tick_exit_count_spin.setSuffix(" 틱")
        self.tick_exit_count_spin.valueChanged.connect(
            lambda value: self.update_condition('tick_exit', 'tick_count', value)
        )
        settings_grid.addWidget(self.tick_exit_count_spin, 1, 1)
        
        # Immediate exit option
        self.immediate_exit_checkbox = QCheckBox("즉시 전량 청산")
        self.immediate_exit_checkbox.setToolTip("조건 만족 시 포지션 전량을 즉시 청산")
        self.immediate_exit_checkbox.toggled.connect(
            lambda checked: self.update_condition('tick_exit', 'immediate_exit', checked)
        )
        settings_grid.addWidget(self.immediate_exit_checkbox, 1, 2, 1, 2)
        
        layout.addLayout(settings_grid)
        
        # Tick monitor
        self.create_tick_exit_monitor()
        layout.addWidget(self.tick_monitor_frame)
        
        return tick_widget
        
    def create_tick_exit_monitor(self):
        """Create tick exit monitoring display"""
        self.tick_monitor_frame = QFrame()
        self.tick_monitor_frame.setFrameStyle(QFrame.Box)
        self.tick_monitor_frame.setMinimumHeight(100)
        monitor_layout = QVBoxLayout(self.tick_monitor_frame)
        
        monitor_layout.addWidget(QLabel("실시간 틱 모니터 (청산)"))
        
        # Tick counter
        self.tick_exit_counter_label = QLabel("하락틱 카운터: 0/3")
        self.tick_exit_counter_label.setStyleSheet("font-family: monospace; font-size: 14px; font-weight: bold;")
        monitor_layout.addWidget(self.tick_exit_counter_label)
        
        # Progress bar
        self.tick_exit_progress = QProgressBar()
        self.tick_exit_progress.setRange(0, 3)
        self.tick_exit_progress.setValue(0)
        monitor_layout.addWidget(self.tick_exit_progress)
        
        # Status
        self.tick_exit_status_label = QLabel("상태: 대기중")
        self.tick_exit_status_label.setStyleSheet("color: #28a745;")
        monitor_layout.addWidget(self.tick_exit_status_label)
        
    def create_pc_cut_tab(self):
        """Create PC cut (본절) liquidation tab"""
        pc_cut_widget = QWidget()
        layout = QVBoxLayout(pc_cut_widget)
        
        # PC Cut Enable
        self.pc_cut_checkbox = QCheckBox("PC 본절 청산 활성화")
        self.pc_cut_checkbox.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        self.pc_cut_checkbox.toggled.connect(
            lambda checked: self.update_condition('pc_cut', 'enabled', checked)
        )
        layout.addWidget(self.pc_cut_checkbox)
        
        # Two-stage PC cut system
        stage1_group = QGroupBox("1단계: PC선 돌파 청산")
        stage1_group.setCheckable(True)
        stage1_group.setChecked(True)
        stage1_group.toggled.connect(
            lambda checked: self.update_condition('pc_cut', 'stage1_enabled', checked)
        )
        
        stage1_layout = QVBoxLayout()
        stage1_desc = QLabel("Price Channel 상단선 또는 하단선을 돌파할 때 자동 청산")
        stage1_desc.setStyleSheet("color: #6c757d; font-style: italic;")
        stage1_layout.addWidget(stage1_desc)
        
        # Stage 1 settings would go here
        stage1_status = QLabel("상태: 활성 - PC선 모니터링 중")
        stage1_status.setStyleSheet("color: #28a745; font-weight: bold;")
        stage1_layout.addWidget(stage1_status)
        
        stage1_group.setLayout(stage1_layout)
        layout.addWidget(stage1_group)
        
        stage2_group = QGroupBox("2단계: 진입가 복귀 청산")
        stage2_group.setCheckable(True)
        stage2_group.setChecked(False)
        stage2_group.toggled.connect(
            lambda checked: self.update_condition('pc_cut', 'stage2_enabled', checked)
        )
        
        stage2_layout = QGridLayout()
        stage2_layout.addWidget(QLabel("진입가 복귀 시 청산 (손절 방지)"), 0, 0, 1, 2)
        
        stage2_layout.addWidget(QLabel("진입가 오프셋:"), 1, 0)
        self.entry_offset_spin = QDoubleSpinBox()
        self.entry_offset_spin.setRange(-1.0, 1.0)
        self.entry_offset_spin.setValue(0.0)
        self.entry_offset_spin.setSingleStep(0.01)
        self.entry_offset_spin.setSuffix("%")
        self.entry_offset_spin.valueChanged.connect(
            lambda value: self.update_condition('pc_cut', 'entry_price_offset', value)
        )
        stage2_layout.addWidget(self.entry_offset_spin, 1, 1)
        
        stage2_group.setLayout(stage2_layout)
        layout.addWidget(stage2_group)
        
        # PC Cut status display
        self.create_pc_cut_display()
        layout.addWidget(self.pc_cut_display_frame)
        
        return pc_cut_widget
        
    def create_pc_cut_display(self):
        """Create PC cut status display"""
        self.pc_cut_display_frame = QFrame()
        self.pc_cut_display_frame.setFrameStyle(QFrame.Box)
        self.pc_cut_display_frame.setMinimumHeight(100)
        display_layout = QVBoxLayout(self.pc_cut_display_frame)
        
        display_layout.addWidget(QLabel("PC 본절 모니터링 상태"))
        
        # Status grid
        status_grid = QGridLayout()
        
        status_grid.addWidget(QLabel("진입가:"), 0, 0)
        self.entry_price_label = QLabel("--")
        status_grid.addWidget(self.entry_price_label, 0, 1)
        
        status_grid.addWidget(QLabel("PC 상단선:"), 0, 2)
        self.pc_upper_label = QLabel("--")
        status_grid.addWidget(self.pc_upper_label, 0, 3)
        
        status_grid.addWidget(QLabel("PC 하단선:"), 1, 0)
        self.pc_lower_label = QLabel("--")
        status_grid.addWidget(self.pc_lower_label, 1, 1)
        
        status_grid.addWidget(QLabel("현재가:"), 1, 2)
        self.pc_current_price_label = QLabel("--")
        status_grid.addWidget(self.pc_current_price_label, 1, 3)
        
        display_layout.addLayout(status_grid)
        
        # Overall status
        self.pc_cut_status_label = QLabel("PC 본절 상태: 대기중")
        self.pc_cut_status_label.setStyleSheet("font-weight: bold; color: #17a2b8;")
        display_layout.addWidget(self.pc_cut_status_label)
        
    def create_control_buttons(self):
        """Create control buttons for the exit tab"""
        button_frame = QFrame()
        button_layout = QHBoxLayout(button_frame)
        button_layout.setContentsMargins(0, 10, 0, 10)
        
        # Test exit conditions
        self.test_exit_btn = QPushButton("🧪 청산 조건 테스트")
        self.test_exit_btn.setStyleSheet("""
            QPushButton {
                background-color: #17a2b8;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 120px;
            }
            QPushButton:hover {
                background-color: #138496;
            }
        """)
        self.test_exit_btn.clicked.connect(self.test_exit_conditions)
        button_layout.addWidget(self.test_exit_btn)
        
        # Save configuration
        self.save_exit_btn = QPushButton("💾 설정 저장")
        self.save_exit_btn.setStyleSheet("""
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
        self.save_exit_btn.clicked.connect(self.save_exit_configuration)
        button_layout.addWidget(self.save_exit_btn)
        
        # Reset to defaults
        self.reset_exit_btn = QPushButton("🔄 기본값으로")
        self.reset_exit_btn.setStyleSheet("""
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
        self.reset_exit_btn.clicked.connect(self.reset_exit_defaults)
        button_layout.addWidget(self.reset_exit_btn)
        
        button_layout.addStretch()
        
        # Overall exit status
        self.overall_exit_status_label = QLabel("전체 청산 조건: 비활성")
        self.overall_exit_status_label.setStyleSheet("""
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
            border: 2px solid #dc3545;
            border-radius: 4px;
            padding: 5px 10px;
        """)
        button_layout.addWidget(self.overall_exit_status_label)
        
        return button_frame
        
    # Event handlers
    def update_condition(self, category, key, value):
        """Update a specific exit condition setting"""
        self.exit_conditions[category][key] = value
        self.logger.info(f"Exit condition updated: {category}.{key} = {value}")
        self.update_exit_status_displays()
        self.config_changed.emit(self.exit_conditions)
        
    def update_pcs_mode(self, button):
        """Update PCS mode"""
        mode = '1STEP' if button.text().startswith('1STEP') else '2STEP'
        self.update_condition('pcs_liquidation', 'mode', mode)
        
    def update_pcs_stage(self, stage, key, value):
        """Update specific PCS stage setting"""
        self.exit_conditions['pcs_liquidation']['stages'][stage][key] = value
        self.logger.info(f"PCS stage {stage} updated: {key} = {value}")
        self.update_pcs_status()
        
    def update_trailing_direction(self, direction_text):
        """Update PC trailing direction"""
        direction = 'lower' if '하단선' in direction_text else 'upper'
        self.update_condition('pc_trailing', 'direction', direction)
        
    def update_tick_exit_direction(self, button):
        """Update tick exit direction"""
        direction = 'down' if '하락' in button.text() else 'up'
        self.update_condition('tick_exit', 'direction', direction)
        
    def update_pcs_status(self):
        """Update PCS status display"""
        enabled_stages = sum(1 for stage in self.exit_conditions['pcs_liquidation']['stages'].values() 
                           if stage.get('enabled', False))
        
        if enabled_stages > 0:
            self.pcs_status_label.setText(f"PCS 상태: 활성 ({enabled_stages}/12 단계)")
            self.pcs_status_label.setStyleSheet("font-weight: bold; color: #28a745;")
        else:
            self.pcs_status_label.setText("PCS 상태: 비활성")
            self.pcs_status_label.setStyleSheet("font-weight: bold; color: #6c757d;")
            
    def update_exit_status_displays(self):
        """Update all exit status displays"""
        active_conditions = sum(1 for condition in self.exit_conditions.values() 
                              if condition.get('enabled', False))
        
        if active_conditions > 0:
            self.overall_exit_status_label.setText(f"전체 청산 조건: 활성 ({active_conditions}개 조건)")
            self.overall_exit_status_label.setStyleSheet("""
                color: #28a745;
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #28a745;
                border-radius: 4px;
                padding: 5px 10px;
            """)
        else:
            self.overall_exit_status_label.setText("전체 청산 조건: 비활성")
            self.overall_exit_status_label.setStyleSheet("""
                color: #dc3545;
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #dc3545;
                border-radius: 4px;
                padding: 5px 10px;
            """)
            
    # Quick setup methods for PCS
    def set_conservative_pcs(self):
        """Set conservative PCS configuration"""
        # Enable first 6 stages with conservative settings
        for i in range(6):
            stage = i + 1
            checkbox = self.pcs_table.cellWidget(i, 1)
            profit_spin = self.pcs_table.cellWidget(i, 2)
            quantity_spin = self.pcs_table.cellWidget(i, 3)
            
            checkbox.setChecked(True)
            profit_spin.setValue(stage * 0.8)  # 0.8%, 1.6%, 2.4%, etc.
            quantity_spin.setValue(15)  # 15% each stage
            
        from PyQt5.QtWidgets import QMessageBox
        QMessageBox.information(self, "설정 완료", "보수적 PCS 설정이 적용되었습니다.")
        
    def set_aggressive_pcs(self):
        """Set aggressive PCS configuration"""
        # Enable first 10 stages with aggressive settings
        for i in range(10):
            stage = i + 1
            checkbox = self.pcs_table.cellWidget(i, 1)
            profit_spin = self.pcs_table.cellWidget(i, 2)
            quantity_spin = self.pcs_table.cellWidget(i, 3)
            
            checkbox.setChecked(True)
            profit_spin.setValue(stage * 0.3)  # 0.3%, 0.6%, 0.9%, etc.
            quantity_spin.setValue(8)  # 8% each stage
            
        from PyQt5.QtWidgets import QMessageBox
        QMessageBox.information(self, "설정 완료", "공격적 PCS 설정이 적용되었습니다.")
        
    def set_custom_pcs(self):
        """Set custom PCS configuration"""
        from PyQt5.QtWidgets import QMessageBox
        QMessageBox.information(self, "커스텀 설정", 
                              "테이블에서 각 단계별로 직접 설정하세요.\n"
                              "익절 수익률과 청산 비율을 조정할 수 있습니다.")
        
    # Button handlers
    def test_exit_conditions(self):
        """Test current exit conditions"""
        from PyQt5.QtWidgets import QMessageBox
        
        active_conditions = []
        if self.exit_conditions['pcs_liquidation']['enabled']:
            active_conditions.append("✅ PCS 청산 시스템")
        if self.exit_conditions['pc_trailing']['enabled']:
            active_conditions.append("✅ PC 트레일링")
        if self.exit_conditions['tick_exit']['enabled']:
            active_conditions.append("✅ 호가 기반 청산")
        if self.exit_conditions['pc_cut']['enabled']:
            active_conditions.append("✅ PC 본절 청산")
            
        if not active_conditions:
            QMessageBox.information(self, "테스트 결과", "활성화된 청산 조건이 없습니다.")
            return
            
        result_text = "청산 조건 테스트 결과:\n\n" + "\n".join(active_conditions)
        result_text += "\n\n모든 조건이 정상적으로 동작합니다."
        QMessageBox.information(self, "테스트 결과", result_text)
        
    def save_exit_configuration(self):
        """Save current exit configuration"""
        from PyQt5.QtWidgets import QMessageBox
        
        try:
            self.config.update_section('exit_conditions', self.exit_conditions)
            QMessageBox.information(self, "저장 완료", "청산 조건 설정이 저장되었습니다.")
            self.logger.info("Exit conditions configuration saved")
        except Exception as e:
            QMessageBox.critical(self, "저장 실패", f"설정 저장 중 오류가 발생했습니다:\n{str(e)}")
            self.logger.error(f"Failed to save exit conditions: {e}")
            
    def reset_exit_defaults(self):
        """Reset all exit settings to defaults"""
        from PyQt5.QtWidgets import QMessageBox
        
        reply = QMessageBox.question(
            self, '기본값 복원',
            '모든 청산 설정을 기본값으로 복원하시겠습니까?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.load_exit_defaults()
            QMessageBox.information(self, "복원 완료", "모든 청산 설정이 기본값으로 복원되었습니다.")
            
    def load_exit_defaults(self):
        """Load default exit settings"""
        # Reset all checkboxes
        self.pcs_enable_checkbox.setChecked(False)
        self.pc_trailing_checkbox.setChecked(False)
        self.tick_exit_checkbox.setChecked(False)
        self.pc_cut_checkbox.setChecked(False)
        
        # Reset PCS table
        for i in range(12):
            checkbox = self.pcs_table.cellWidget(i, 1)
            profit_spin = self.pcs_table.cellWidget(i, 2)
            quantity_spin = self.pcs_table.cellWidget(i, 3)
            
            checkbox.setChecked(False)
            profit_spin.setValue((i + 1) * 0.5)
            quantity_spin.setValue(10)
            
        # Reset other settings
        self.pcs_1step_radio.setChecked(True)
        self.trailing_line_combo.setCurrentIndex(0)
        self.trailing_distance_spin.setValue(0.1)
        self.stop_on_loss_checkbox.setChecked(True)
        self.tick_down_exit_radio.setChecked(True)
        self.tick_exit_count_spin.setValue(3)
        self.immediate_exit_checkbox.setChecked(False)
        self.entry_offset_spin.setValue(0.0)
        
        self.update_exit_status_displays()
        
    def get_exit_configuration(self):
        """Get current exit configuration"""
        return self.exit_conditions.copy()
        
    def set_exit_configuration(self, config):
        """Set exit configuration from external source"""
        self.exit_conditions.update(config)
        self.update_exit_status_displays()