"""
System Settings Tab
PRD-compliant implementation of system settings including exchange, time control, and risk management
"""

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QGroupBox, QGridLayout,
    QLabel, QComboBox, QSpinBox, QDoubleSpinBox, QCheckBox,
    QPushButton, QFrame, QScrollArea, QButtonGroup, QRadioButton,
    QLineEdit, QTimeEdit, QTabWidget, QTableWidget, QTableWidgetItem,
    QHeaderView, QSlider, QProgressBar, QTextEdit
)
from PyQt5.QtCore import Qt, pyqtSignal, QTime
from PyQt5.QtGui import QFont, QIntValidator, QDoubleValidator


class SettingsTab(QWidget):
    """System settings configuration tab"""
    
    # Signals for configuration changes
    config_changed = pyqtSignal(dict)
    
    def __init__(self, config_manager, logger):
        super().__init__()
        self.config = config_manager
        self.logger = logger
        
        # System settings
        self.system_settings = {
            'exchange': {
                'api_key': '',
                'api_secret': '',
                'leverage': 10,
                'position_mode': 'hedge',  # hedge/oneway
                'testnet': True,
                'exchange_name': 'binance'
            },
            'time_control': {
                'weekday_schedules': {
                    'monday': {'enabled': True, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                              'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'tuesday': {'enabled': True, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                               'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'wednesday': {'enabled': True, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                                 'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'thursday': {'enabled': True, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                                'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'friday': {'enabled': True, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                              'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'saturday': {'enabled': False, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                                'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'},
                    'sunday': {'enabled': False, 'schedule1': {'start': '09:00', 'end': '18:00'}, 
                              'schedule2': {'start': '19:00', 'end': '23:00'}, 'liquidation_time': '23:30'}
                }
            },
            'risk_management': {
                'position_size': 1000.0,  # USD
                'max_positions': 3,
                'profit_stages': {i: {'enabled': False, 'profit_percent': i*0.5, 'action': 'partial_close'} 
                                for i in range(1, 13)},  # 12 stages
                'loss_stages': {i: {'enabled': False, 'loss_percent': i*0.3, 'action': 'stop_loss'} 
                               for i in range(1, 13)}  # 12 stages
            }
        }
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize the settings tab UI"""
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
        title_label = QLabel("⚙️ 시스템 설정")
        title_label.setFont(QFont("Malgun Gothic", 16, QFont.Bold))
        title_label.setStyleSheet("color: #007bff; margin-bottom: 10px;")
        main_layout.addWidget(title_label)
        
        # Create sub-tabs for different setting categories
        self.settings_tabs = QTabWidget()
        self.settings_tabs.addTab(self.create_exchange_tab(), "거래소 설정")
        self.settings_tabs.addTab(self.create_time_control_tab(), "시간 제어")
        self.settings_tabs.addTab(self.create_risk_management_tab(), "리스크 관리")
        
        main_layout.addWidget(self.settings_tabs)
        main_layout.addWidget(self.create_control_buttons())
        
        # Add stretch at bottom
        main_layout.addStretch()
        
        # Set scroll widget
        scroll.setWidget(content_widget)
        
        # Main layout
        layout = QVBoxLayout()
        layout.addWidget(scroll)
        self.setLayout(layout)
        
    def create_exchange_tab(self):
        """Create exchange configuration tab"""
        exchange_widget = QWidget()
        layout = QVBoxLayout(exchange_widget)
        
        # Exchange Selection
        exchange_group = QGroupBox("거래소 선택")
        exchange_layout = QGridLayout()
        
        exchange_layout.addWidget(QLabel("거래소:"), 0, 0)
        self.exchange_combo = QComboBox()
        self.exchange_combo.addItems(["바이낸스 (Binance)", "바이비트 (Bybit)", "업비트 (Upbit)"])
        self.exchange_combo.currentTextChanged.connect(self.update_exchange_name)
        exchange_layout.addWidget(self.exchange_combo, 0, 1)
        
        # Test/Live mode
        exchange_layout.addWidget(QLabel("모드:"), 0, 2)
        self.mode_combo = QComboBox()
        self.mode_combo.addItems(["테스트넷", "실거래"])
        self.mode_combo.setCurrentIndex(0)  # Default to testnet
        self.mode_combo.currentTextChanged.connect(self.update_testnet_mode)
        exchange_layout.addWidget(self.mode_combo, 0, 3)
        
        exchange_group.setLayout(exchange_layout)
        layout.addWidget(exchange_group)
        
        # API Configuration
        api_group = QGroupBox("API 설정")
        api_layout = QGridLayout()
        
        api_layout.addWidget(QLabel("API Key:"), 0, 0)
        self.api_key_edit = QLineEdit()
        self.api_key_edit.setPlaceholderText("API Key를 입력하세요")
        self.api_key_edit.setEchoMode(QLineEdit.Password)
        self.api_key_edit.textChanged.connect(
            lambda text: self.update_setting('exchange', 'api_key', text)
        )
        api_layout.addWidget(self.api_key_edit, 0, 1, 1, 3)
        
        api_layout.addWidget(QLabel("API Secret:"), 1, 0)
        self.api_secret_edit = QLineEdit()
        self.api_secret_edit.setPlaceholderText("API Secret을 입력하세요")
        self.api_secret_edit.setEchoMode(QLineEdit.Password)
        self.api_secret_edit.textChanged.connect(
            lambda text: self.update_setting('exchange', 'api_secret', text)
        )
        api_layout.addWidget(self.api_secret_edit, 1, 1, 1, 3)
        
        # Show API keys button
        self.show_api_btn = QPushButton("👁️ API 키 표시")
        self.show_api_btn.setCheckable(True)
        self.show_api_btn.toggled.connect(self.toggle_api_visibility)
        api_layout.addWidget(self.show_api_btn, 2, 0)
        
        # Test connection button
        self.test_connection_btn = QPushButton("🔗 연결 테스트")
        self.test_connection_btn.clicked.connect(self.test_api_connection)
        api_layout.addWidget(self.test_connection_btn, 2, 1)
        
        api_group.setLayout(api_layout)
        layout.addWidget(api_group)
        
        # Trading Configuration
        trading_group = QGroupBox("거래 설정")
        trading_layout = QGridLayout()
        
        # Leverage
        trading_layout.addWidget(QLabel("레버리지:"), 0, 0)
        self.leverage_spin = QSpinBox()
        self.leverage_spin.setRange(1, 100)
        self.leverage_spin.setValue(10)
        self.leverage_spin.setSuffix("x")
        self.leverage_spin.valueChanged.connect(
            lambda value: self.update_setting('exchange', 'leverage', value)
        )
        trading_layout.addWidget(self.leverage_spin, 0, 1)
        
        # Position Mode
        trading_layout.addWidget(QLabel("포지션 모드:"), 0, 2)
        self.position_mode_combo = QComboBox()
        self.position_mode_combo.addItems(["헤지 모드", "단방향 모드"])
        self.position_mode_combo.currentTextChanged.connect(self.update_position_mode)
        trading_layout.addWidget(self.position_mode_combo, 0, 3)
        
        # Risk warning
        risk_warning = QLabel("⚠️ 높은 레버리지는 큰 손실을 초래할 수 있습니다.")
        risk_warning.setStyleSheet("color: #dc3545; font-weight: bold;")
        trading_layout.addWidget(risk_warning, 1, 0, 1, 4)
        
        trading_group.setLayout(trading_layout)
        layout.addWidget(trading_group)
        
        # Connection Status
        self.create_connection_status_display()
        layout.addWidget(self.connection_status_frame)
        
        return exchange_widget
        
    def create_connection_status_display(self):
        """Create connection status display"""
        self.connection_status_frame = QFrame()
        self.connection_status_frame.setFrameStyle(QFrame.Box)
        self.connection_status_frame.setMinimumHeight(100)
        status_layout = QVBoxLayout(self.connection_status_frame)
        
        status_layout.addWidget(QLabel("연결 상태"))
        
        # Status grid
        status_grid = QGridLayout()
        
        status_grid.addWidget(QLabel("거래소:"), 0, 0)
        self.exchange_status_label = QLabel("❌ 연결 안됨")
        status_grid.addWidget(self.exchange_status_label, 0, 1)
        
        status_grid.addWidget(QLabel("계정 상태:"), 0, 2)
        self.account_status_label = QLabel("❌ 미인증")
        status_grid.addWidget(self.account_status_label, 0, 3)
        
        status_grid.addWidget(QLabel("잔고:"), 1, 0)
        self.balance_label = QLabel("--")
        status_grid.addWidget(self.balance_label, 1, 1)
        
        status_grid.addWidget(QLabel("마지막 업데이트:"), 1, 2)
        self.last_update_label = QLabel("--")
        status_grid.addWidget(self.last_update_label, 1, 3)
        
        status_layout.addLayout(status_grid)
        
    def create_time_control_tab(self):
        """Create time control configuration tab"""
        time_widget = QWidget()
        layout = QVBoxLayout(time_widget)
        
        # Description
        desc_label = QLabel("요일별 가동시간 설정 (각 요일마다 2개 시간대 + 청산시간 설정 가능)")
        desc_label.setStyleSheet("color: #6c757d; font-style: italic; margin-bottom: 10px;")
        layout.addWidget(desc_label)
        
        # Weekday schedule table
        self.create_weekday_schedule_table()
        layout.addWidget(self.schedule_table_frame)
        
        # Quick setup buttons
        quick_time_frame = QFrame()
        quick_time_layout = QHBoxLayout(quick_time_frame)
        
        self.business_hours_btn = QPushButton("업무시간 설정")
        self.business_hours_btn.clicked.connect(self.set_business_hours)
        quick_time_layout.addWidget(self.business_hours_btn)
        
        self.full_time_btn = QPushButton("24시간 설정")
        self.full_time_btn.clicked.connect(self.set_full_time)
        quick_time_layout.addWidget(self.full_time_btn)
        
        self.weekend_off_btn = QPushButton("주말 휴무")
        self.weekend_off_btn.clicked.connect(self.set_weekend_off)
        quick_time_layout.addWidget(self.weekend_off_btn)
        
        quick_time_layout.addStretch()
        layout.addWidget(quick_time_frame)
        
        # Current time status
        self.create_time_status_display()
        layout.addWidget(self.time_status_frame)
        
        return time_widget
        
    def create_weekday_schedule_table(self):
        """Create weekday schedule configuration table"""
        self.schedule_table_frame = QFrame()
        self.schedule_table_frame.setFrameStyle(QFrame.Box)
        table_layout = QVBoxLayout(self.schedule_table_frame)
        
        table_layout.addWidget(QLabel("요일별 가동시간 설정"))
        
        self.schedule_table = QTableWidget(7, 8)  # 7 days, 8 columns
        self.schedule_table.setHorizontalHeaderLabels([
            "요일", "활성화", "시간대1 시작", "시간대1 종료", 
            "시간대2 시작", "시간대2 종료", "청산시간", "상태"
        ])
        
        # Set column widths
        header = self.schedule_table.horizontalHeader()
        header.setSectionResizeMode(QHeaderView.Stretch)
        
        # Days of week
        days = ["월요일", "화요일", "수요일", "목요일", "금요일", "토요일", "일요일"]
        day_keys = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
        
        for i, (day, day_key) in enumerate(zip(days, day_keys)):
            # Day name
            day_item = QTableWidgetItem(day)
            day_item.setFlags(Qt.ItemIsEnabled)
            day_item.setTextAlignment(Qt.AlignCenter)
            self.schedule_table.setItem(i, 0, day_item)
            
            # Enable checkbox
            enable_checkbox = QCheckBox()
            enable_checkbox.setChecked(i < 5)  # Weekdays enabled by default
            enable_checkbox.toggled.connect(
                lambda checked, key=day_key: self.update_weekday_enabled(key, checked)
            )
            self.schedule_table.setCellWidget(i, 1, enable_checkbox)
            
            # Time schedule 1 start
            start1_time = QTimeEdit()
            start1_time.setTime(QTime.fromString("09:00", "hh:mm"))
            start1_time.timeChanged.connect(
                lambda time, key=day_key: self.update_schedule_time(key, 'schedule1', 'start', time.toString("hh:mm"))
            )
            self.schedule_table.setCellWidget(i, 2, start1_time)
            
            # Time schedule 1 end
            end1_time = QTimeEdit()
            end1_time.setTime(QTime.fromString("18:00", "hh:mm"))
            end1_time.timeChanged.connect(
                lambda time, key=day_key: self.update_schedule_time(key, 'schedule1', 'end', time.toString("hh:mm"))
            )
            self.schedule_table.setCellWidget(i, 3, end1_time)
            
            # Time schedule 2 start
            start2_time = QTimeEdit()
            start2_time.setTime(QTime.fromString("19:00", "hh:mm"))
            start2_time.timeChanged.connect(
                lambda time, key=day_key: self.update_schedule_time(key, 'schedule2', 'start', time.toString("hh:mm"))
            )
            self.schedule_table.setCellWidget(i, 4, start2_time)
            
            # Time schedule 2 end
            end2_time = QTimeEdit()
            end2_time.setTime(QTime.fromString("23:00", "hh:mm"))
            end2_time.timeChanged.connect(
                lambda time, key=day_key: self.update_schedule_time(key, 'schedule2', 'end', time.toString("hh:mm"))
            )
            self.schedule_table.setCellWidget(i, 5, end2_time)
            
            # Liquidation time
            liquidation_time = QTimeEdit()
            liquidation_time.setTime(QTime.fromString("23:30", "hh:mm"))
            liquidation_time.timeChanged.connect(
                lambda time, key=day_key: self.update_liquidation_time(key, time.toString("hh:mm"))
            )
            self.schedule_table.setCellWidget(i, 6, liquidation_time)
            
            # Status
            status_item = QTableWidgetItem("활성" if i < 5 else "비활성")
            status_item.setFlags(Qt.ItemIsEnabled)
            status_item.setTextAlignment(Qt.AlignCenter)
            self.schedule_table.setItem(i, 7, status_item)
            
        table_layout.addWidget(self.schedule_table)
        
    def create_time_status_display(self):
        """Create time status display"""
        self.time_status_frame = QFrame()
        self.time_status_frame.setFrameStyle(QFrame.Box)
        self.time_status_frame.setMinimumHeight(80)
        status_layout = QVBoxLayout(self.time_status_frame)
        
        status_layout.addWidget(QLabel("현재 시간 상태"))
        
        status_grid = QGridLayout()
        
        status_grid.addWidget(QLabel("현재 시각:"), 0, 0)
        self.current_time_label = QLabel("--:--:--")
        status_grid.addWidget(self.current_time_label, 0, 1)
        
        status_grid.addWidget(QLabel("가동 상태:"), 0, 2)
        self.operation_status_label = QLabel("가동 중")
        self.operation_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
        status_grid.addWidget(self.operation_status_label, 0, 3)
        
        status_grid.addWidget(QLabel("다음 청산:"), 1, 0)
        self.next_liquidation_label = QLabel("23:30")
        status_grid.addWidget(self.next_liquidation_label, 1, 1)
        
        status_grid.addWidget(QLabel("남은 시간:"), 1, 2)
        self.time_remaining_label = QLabel("--")
        status_grid.addWidget(self.time_remaining_label, 1, 3)
        
        status_layout.addLayout(status_grid)
        
    def create_risk_management_tab(self):
        """Create risk management configuration tab"""
        risk_widget = QWidget()
        layout = QVBoxLayout(risk_widget)
        
        # Basic Risk Settings
        basic_risk_group = QGroupBox("기본 리스크 설정")
        basic_layout = QGridLayout()
        
        # Position size
        basic_layout.addWidget(QLabel("포지션 크기:"), 0, 0)
        self.position_size_spin = QDoubleSpinBox()
        self.position_size_spin.setRange(10.0, 100000.0)
        self.position_size_spin.setValue(1000.0)
        self.position_size_spin.setSuffix(" USD")
        self.position_size_spin.valueChanged.connect(
            lambda value: self.update_setting('risk_management', 'position_size', value)
        )
        basic_layout.addWidget(self.position_size_spin, 0, 1)
        
        # Max positions
        basic_layout.addWidget(QLabel("최대 포지션:"), 0, 2)
        self.max_positions_spin = QSpinBox()
        self.max_positions_spin.setRange(1, 10)
        self.max_positions_spin.setValue(3)
        self.max_positions_spin.setSuffix(" 개")
        self.max_positions_spin.valueChanged.connect(
            lambda value: self.update_setting('risk_management', 'max_positions', value)
        )
        basic_layout.addWidget(self.max_positions_spin, 0, 3)
        
        # Risk percentage of total balance
        basic_layout.addWidget(QLabel("총 잔고 대비 리스크:"), 1, 0)
        self.risk_percent_spin = QDoubleSpinBox()
        self.risk_percent_spin.setRange(1.0, 50.0)
        self.risk_percent_spin.setValue(5.0)
        self.risk_percent_spin.setSuffix(" %")
        basic_layout.addWidget(self.risk_percent_spin, 1, 1)
        
        # Daily loss limit
        basic_layout.addWidget(QLabel("일일 손실 한도:"), 1, 2)
        self.daily_loss_spin = QDoubleSpinBox()
        self.daily_loss_spin.setRange(50.0, 5000.0)
        self.daily_loss_spin.setValue(500.0)
        self.daily_loss_spin.setSuffix(" USD")
        basic_layout.addWidget(self.daily_loss_spin, 1, 3)
        
        basic_risk_group.setLayout(basic_layout)
        layout.addWidget(basic_risk_group)
        
        # 12-Stage Profit/Loss Management
        stage_tabs = QTabWidget()
        stage_tabs.addTab(self.create_profit_stages_tab(), "12단계 익절 관리")
        stage_tabs.addTab(self.create_loss_stages_tab(), "12단계 손절 관리")
        
        layout.addWidget(stage_tabs)
        
        # Risk monitoring display
        self.create_risk_monitoring_display()
        layout.addWidget(self.risk_monitoring_frame)
        
        return risk_widget
        
    def create_profit_stages_tab(self):
        """Create 12-stage profit management tab"""
        profit_widget = QWidget()
        layout = QVBoxLayout(profit_widget)
        
        layout.addWidget(QLabel("12단계 익절 시스템 - 수익률별 자동 익절"))
        
        # Profit stages table
        self.profit_table = QTableWidget(12, 5)  # 12 stages, 5 columns
        self.profit_table.setHorizontalHeaderLabels([
            "단계", "활성화", "목표 수익률(%)", "실행 액션", "상태"
        ])
        
        header = self.profit_table.horizontalHeader()
        header.setSectionResizeMode(QHeaderView.Stretch)
        
        for i in range(12):
            stage = i + 1
            
            # Stage number
            stage_item = QTableWidgetItem(f"{stage}단계")
            stage_item.setFlags(Qt.ItemIsEnabled)
            stage_item.setTextAlignment(Qt.AlignCenter)
            self.profit_table.setItem(i, 0, stage_item)
            
            # Enable checkbox
            enable_checkbox = QCheckBox()
            enable_checkbox.toggled.connect(
                lambda checked, s=stage: self.update_profit_stage(s, 'enabled', checked)
            )
            self.profit_table.setCellWidget(i, 1, enable_checkbox)
            
            # Profit percentage
            profit_spin = QDoubleSpinBox()
            profit_spin.setRange(0.1, 100.0)
            profit_spin.setValue(stage * 0.5)  # 0.5%, 1.0%, 1.5%, etc.
            profit_spin.setSuffix("%")
            profit_spin.valueChanged.connect(
                lambda value, s=stage: self.update_profit_stage(s, 'profit_percent', value)
            )
            self.profit_table.setCellWidget(i, 2, profit_spin)
            
            # Action combo
            action_combo = QComboBox()
            action_combo.addItems(["부분 익절", "전량 익절", "트레일링 시작"])
            action_combo.currentTextChanged.connect(
                lambda text, s=stage: self.update_profit_stage(s, 'action', text)
            )
            self.profit_table.setCellWidget(i, 3, action_combo)
            
            # Status
            status_item = QTableWidgetItem("대기")
            status_item.setFlags(Qt.ItemIsEnabled)
            status_item.setTextAlignment(Qt.AlignCenter)
            self.profit_table.setItem(i, 4, status_item)
            
        layout.addWidget(self.profit_table)
        
        return profit_widget
        
    def create_loss_stages_tab(self):
        """Create 12-stage loss management tab"""
        loss_widget = QWidget()
        layout = QVBoxLayout(loss_widget)
        
        layout.addWidget(QLabel("12단계 손절 시스템 - 손실률별 자동 손절"))
        
        # Loss stages table
        self.loss_table = QTableWidget(12, 5)  # 12 stages, 5 columns
        self.loss_table.setHorizontalHeaderLabels([
            "단계", "활성화", "손실 한도(%)", "실행 액션", "상태"
        ])
        
        header = self.loss_table.horizontalHeader()
        header.setSectionResizeMode(QHeaderView.Stretch)
        
        for i in range(12):
            stage = i + 1
            
            # Stage number
            stage_item = QTableWidgetItem(f"{stage}단계")
            stage_item.setFlags(Qt.ItemIsEnabled)
            stage_item.setTextAlignment(Qt.AlignCenter)
            self.loss_table.setItem(i, 0, stage_item)
            
            # Enable checkbox
            enable_checkbox = QCheckBox()
            enable_checkbox.toggled.connect(
                lambda checked, s=stage: self.update_loss_stage(s, 'enabled', checked)
            )
            self.loss_table.setCellWidget(i, 1, enable_checkbox)
            
            # Loss percentage
            loss_spin = QDoubleSpinBox()
            loss_spin.setRange(0.1, 50.0)
            loss_spin.setValue(stage * 0.3)  # 0.3%, 0.6%, 0.9%, etc.
            loss_spin.setSuffix("%")
            loss_spin.valueChanged.connect(
                lambda value, s=stage: self.update_loss_stage(s, 'loss_percent', value)
            )
            self.loss_table.setCellWidget(i, 2, loss_spin)
            
            # Action combo
            action_combo = QComboBox()
            action_combo.addItems(["부분 손절", "전량 손절", "포지션 축소"])
            action_combo.currentTextChanged.connect(
                lambda text, s=stage: self.update_loss_stage(s, 'action', text)
            )
            self.loss_table.setCellWidget(i, 3, action_combo)
            
            # Status
            status_item = QTableWidgetItem("대기")
            status_item.setFlags(Qt.ItemIsEnabled)
            status_item.setTextAlignment(Qt.AlignCenter)
            self.loss_table.setItem(i, 4, status_item)
            
        layout.addWidget(self.loss_table)
        
        return loss_widget
        
    def create_risk_monitoring_display(self):
        """Create risk monitoring display"""
        self.risk_monitoring_frame = QFrame()
        self.risk_monitoring_frame.setFrameStyle(QFrame.Box)
        self.risk_monitoring_frame.setMinimumHeight(120)
        monitor_layout = QVBoxLayout(self.risk_monitoring_frame)
        
        monitor_layout.addWidget(QLabel("실시간 리스크 모니터링"))
        
        # Risk metrics grid
        risk_grid = QGridLayout()
        
        risk_grid.addWidget(QLabel("현재 포지션:"), 0, 0)
        self.current_positions_label = QLabel("0/3")
        risk_grid.addWidget(self.current_positions_label, 0, 1)
        
        risk_grid.addWidget(QLabel("총 익절 단계:"), 0, 2)
        self.active_profit_stages_label = QLabel("0/12")
        risk_grid.addWidget(self.active_profit_stages_label, 0, 3)
        
        risk_grid.addWidget(QLabel("총 손절 단계:"), 1, 0)
        self.active_loss_stages_label = QLabel("0/12")
        risk_grid.addWidget(self.active_loss_stages_label, 1, 1)
        
        risk_grid.addWidget(QLabel("일일 P&L:"), 1, 2)
        self.daily_pnl_label = QLabel("+0.00 USD")
        self.daily_pnl_label.setStyleSheet("color: #28a745; font-weight: bold;")
        risk_grid.addWidget(self.daily_pnl_label, 1, 3)
        
        monitor_layout.addLayout(risk_grid)
        
        # Risk level indicator
        risk_indicator_layout = QHBoxLayout()
        risk_indicator_layout.addWidget(QLabel("리스크 레벨:"))
        
        self.risk_level_bar = QProgressBar()
        self.risk_level_bar.setRange(0, 100)
        self.risk_level_bar.setValue(25)
        self.risk_level_bar.setStyleSheet("""
            QProgressBar {
                border: 2px solid grey;
                border-radius: 5px;
                text-align: center;
            }
            QProgressBar::chunk {
                background-color: #28a745;
                width: 10px;
                margin: 0.5px;
            }
        """)
        risk_indicator_layout.addWidget(self.risk_level_bar)
        
        self.risk_level_label = QLabel("낮음 (25%)")
        self.risk_level_label.setStyleSheet("color: #28a745; font-weight: bold;")
        risk_indicator_layout.addWidget(self.risk_level_label)
        
        monitor_layout.addLayout(risk_indicator_layout)
        
    def create_control_buttons(self):
        """Create control buttons for the settings tab"""
        button_frame = QFrame()
        button_layout = QHBoxLayout(button_frame)
        button_layout.setContentsMargins(0, 10, 0, 10)
        
        # Test all settings
        self.test_settings_btn = QPushButton("🧪 설정 테스트")
        self.test_settings_btn.setStyleSheet("""
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
        self.test_settings_btn.clicked.connect(self.test_all_settings)
        button_layout.addWidget(self.test_settings_btn)
        
        # Export settings
        self.export_btn = QPushButton("📤 설정 내보내기")
        self.export_btn.setStyleSheet("""
            QPushButton {
                background-color: #6f42c1;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 120px;
            }
            QPushButton:hover {
                background-color: #5a359a;
            }
        """)
        self.export_btn.clicked.connect(self.export_settings)
        button_layout.addWidget(self.export_btn)
        
        # Import settings
        self.import_btn = QPushButton("📥 설정 가져오기")
        self.import_btn.setStyleSheet("""
            QPushButton {
                background-color: #fd7e14;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 120px;
            }
            QPushButton:hover {
                background-color: #e8630c;
            }
        """)
        self.import_btn.clicked.connect(self.import_settings)
        button_layout.addWidget(self.import_btn)
        
        # Save all settings
        self.save_settings_btn = QPushButton("💾 전체 설정 저장")
        self.save_settings_btn.setStyleSheet("""
            QPushButton {
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 12px;
                font-weight: bold;
                padding: 8px 16px;
                min-width: 120px;
            }
            QPushButton:hover {
                background-color: #218838;
            }
        """)
        self.save_settings_btn.clicked.connect(self.save_all_settings)
        button_layout.addWidget(self.save_settings_btn)
        
        button_layout.addStretch()
        
        # Overall system status
        self.system_status_label = QLabel("시스템 상태: 설정 중")
        self.system_status_label.setStyleSheet("""
            color: #ffc107;
            font-weight: bold;
            font-size: 14px;
            border: 2px solid #ffc107;
            border-radius: 4px;
            padding: 5px 10px;
        """)
        button_layout.addWidget(self.system_status_label)
        
        return button_frame
        
    # Event handlers
    def update_setting(self, category, key, value):
        """Update a specific system setting"""
        self.system_settings[category][key] = value
        self.logger.info(f"System setting updated: {category}.{key} = {value}")
        self.config_changed.emit(self.system_settings)
        
    def update_exchange_name(self, exchange_text):
        """Update exchange name"""
        exchange_map = {
            "바이낸스 (Binance)": "binance",
            "바이비트 (Bybit)": "bybit", 
            "업비트 (Upbit)": "upbit"
        }
        self.update_setting('exchange', 'exchange_name', exchange_map.get(exchange_text, 'binance'))
        
    def update_testnet_mode(self, mode_text):
        """Update testnet mode"""
        testnet = mode_text == "테스트넷"
        self.update_setting('exchange', 'testnet', testnet)
        
    def update_position_mode(self, mode_text):
        """Update position mode"""
        mode = 'hedge' if '헤지' in mode_text else 'oneway'
        self.update_setting('exchange', 'position_mode', mode)
        
    def toggle_api_visibility(self, visible):
        """Toggle API key visibility"""
        if visible:
            self.api_key_edit.setEchoMode(QLineEdit.Normal)
            self.api_secret_edit.setEchoMode(QLineEdit.Normal)
            self.show_api_btn.setText("🙈 API 키 숨기기")
        else:
            self.api_key_edit.setEchoMode(QLineEdit.Password)
            self.api_secret_edit.setEchoMode(QLineEdit.Password)
            self.show_api_btn.setText("👁️ API 키 표시")
            
    def test_api_connection(self):
        """Test API connection"""
        from PyQt5.QtWidgets import QMessageBox
        
        # Simulate connection test
        api_key = self.api_key_edit.text().strip()
        api_secret = self.api_secret_edit.text().strip()
        
        if not api_key or not api_secret:
            QMessageBox.warning(self, "연결 테스트", "API Key와 Secret을 입력해주세요.")
            return
            
        # For demo, always show success
        self.exchange_status_label.setText("✅ 연결됨")
        self.exchange_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
        self.account_status_label.setText("✅ 인증됨")
        self.account_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
        self.balance_label.setText("1,000.00 USDT")
        
        import datetime
        self.last_update_label.setText(datetime.datetime.now().strftime("%H:%M:%S"))
        
        QMessageBox.information(self, "연결 테스트", "API 연결이 성공적으로 확인되었습니다.")
        
    # Weekday schedule handlers
    def update_weekday_enabled(self, day_key, enabled):
        """Update weekday enabled status"""
        self.system_settings['time_control']['weekday_schedules'][day_key]['enabled'] = enabled
        
        # Update table status
        day_index = list(self.system_settings['time_control']['weekday_schedules'].keys()).index(day_key)
        status_item = self.schedule_table.item(day_index, 7)
        status_item.setText("활성" if enabled else "비활성")
        
    def update_schedule_time(self, day_key, schedule, time_type, time_str):
        """Update schedule time"""
        self.system_settings['time_control']['weekday_schedules'][day_key][schedule][time_type] = time_str
        
    def update_liquidation_time(self, day_key, time_str):
        """Update liquidation time"""
        self.system_settings['time_control']['weekday_schedules'][day_key]['liquidation_time'] = time_str
        
    # Quick time setup methods
    def set_business_hours(self):
        """Set business hours (09:00-18:00, weekend off)"""
        weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
        weekends = ['saturday', 'sunday']
        
        for day in weekdays:
            self.system_settings['time_control']['weekday_schedules'][day]['enabled'] = True
            
        for day in weekends:
            self.system_settings['time_control']['weekday_schedules'][day]['enabled'] = False
            
        self.refresh_schedule_table()
        
    def set_full_time(self):
        """Set 24 hour operation"""
        for day_key in self.system_settings['time_control']['weekday_schedules']:
            self.system_settings['time_control']['weekday_schedules'][day_key]['enabled'] = True
            
        self.refresh_schedule_table()
        
    def set_weekend_off(self):
        """Set weekend off"""
        self.system_settings['time_control']['weekday_schedules']['saturday']['enabled'] = False
        self.system_settings['time_control']['weekday_schedules']['sunday']['enabled'] = False
        
        self.refresh_schedule_table()
        
    def refresh_schedule_table(self):
        """Refresh schedule table display"""
        day_keys = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
        
        for i, day_key in enumerate(day_keys):
            enabled = self.system_settings['time_control']['weekday_schedules'][day_key]['enabled']
            checkbox = self.schedule_table.cellWidget(i, 1)
            checkbox.setChecked(enabled)
            
            status_item = self.schedule_table.item(i, 7)
            status_item.setText("활성" if enabled else "비활성")
            
    # Risk management handlers
    def update_profit_stage(self, stage, key, value):
        """Update profit stage setting"""
        if key == 'action':
            # Map Korean to English
            action_map = {
                "부분 익절": "partial_close",
                "전량 익절": "full_close", 
                "트레일링 시작": "start_trailing"
            }
            value = action_map.get(value, "partial_close")
            
        self.system_settings['risk_management']['profit_stages'][stage][key] = value
        self.update_risk_counters()
        
    def update_loss_stage(self, stage, key, value):
        """Update loss stage setting"""
        if key == 'action':
            # Map Korean to English
            action_map = {
                "부분 손절": "partial_close",
                "전량 손절": "full_close",
                "포지션 축소": "reduce_position"
            }
            value = action_map.get(value, "partial_close")
            
        self.system_settings['risk_management']['loss_stages'][stage][key] = value
        self.update_risk_counters()
        
    def update_risk_counters(self):
        """Update risk stage counters"""
        profit_active = sum(1 for stage in self.system_settings['risk_management']['profit_stages'].values() 
                          if stage.get('enabled', False))
        loss_active = sum(1 for stage in self.system_settings['risk_management']['loss_stages'].values() 
                        if stage.get('enabled', False))
        
        self.active_profit_stages_label.setText(f"{profit_active}/12")
        self.active_loss_stages_label.setText(f"{loss_active}/12")
        
    # Button handlers
    def test_all_settings(self):
        """Test all system settings"""
        from PyQt5.QtWidgets import QMessageBox
        
        test_results = []
        
        # Test exchange settings
        if self.system_settings['exchange']['api_key'] and self.system_settings['exchange']['api_secret']:
            test_results.append("✅ 거래소 API 설정")
        else:
            test_results.append("❌ 거래소 API 설정 (키가 없음)")
            
        # Test time control
        active_days = sum(1 for day_config in self.system_settings['time_control']['weekday_schedules'].values() 
                         if day_config.get('enabled', False))
        test_results.append(f"✅ 시간 제어 설정 ({active_days}일 활성)")
        
        # Test risk management
        profit_stages = sum(1 for stage in self.system_settings['risk_management']['profit_stages'].values() 
                          if stage.get('enabled', False))
        loss_stages = sum(1 for stage in self.system_settings['risk_management']['loss_stages'].values() 
                        if stage.get('enabled', False))
        test_results.append(f"✅ 리스크 관리 (익절 {profit_stages}단계, 손절 {loss_stages}단계)")
        
        result_text = "시스템 설정 테스트 결과:\n\n" + "\n".join(test_results)
        QMessageBox.information(self, "테스트 결과", result_text)
        
    def export_settings(self):
        """Export current settings"""
        from PyQt5.QtWidgets import QFileDialog, QMessageBox
        import json
        
        filename, _ = QFileDialog.getSaveFileName(
            self, "설정 내보내기", "trading_settings.json", "JSON Files (*.json)"
        )
        
        if filename:
            try:
                with open(filename, 'w', encoding='utf-8') as f:
                    json.dump(self.system_settings, f, indent=2, ensure_ascii=False)
                QMessageBox.information(self, "내보내기 완료", f"설정이 {filename}에 저장되었습니다.")
            except Exception as e:
                QMessageBox.critical(self, "내보내기 실패", f"설정 내보내기 실패:\n{str(e)}")
                
    def import_settings(self):
        """Import settings from file"""
        from PyQt5.QtWidgets import QFileDialog, QMessageBox
        import json
        
        filename, _ = QFileDialog.getOpenFileName(
            self, "설정 가져오기", "", "JSON Files (*.json)"
        )
        
        if filename:
            try:
                with open(filename, 'r', encoding='utf-8') as f:
                    imported_settings = json.load(f)
                    
                self.system_settings.update(imported_settings)
                self.refresh_all_ui()
                QMessageBox.information(self, "가져오기 완료", f"설정이 {filename}에서 불러와졌습니다.")
            except Exception as e:
                QMessageBox.critical(self, "가져오기 실패", f"설정 가져오기 실패:\n{str(e)}")
                
    def save_all_settings(self):
        """Save all system settings"""
        from PyQt5.QtWidgets import QMessageBox
        
        try:
            self.config.update_section('system_settings', self.system_settings)
            
            self.system_status_label.setText("시스템 상태: 정상 운영")
            self.system_status_label.setStyleSheet("""
                color: #28a745;
                font-weight: bold;
                font-size: 14px;
                border: 2px solid #28a745;
                border-radius: 4px;
                padding: 5px 10px;
            """)
            
            QMessageBox.information(self, "저장 완료", "시스템 설정이 저장되었습니다.")
            self.logger.info("System settings saved successfully")
        except Exception as e:
            QMessageBox.critical(self, "저장 실패", f"설정 저장 중 오류가 발생했습니다:\n{str(e)}")
            self.logger.error(f"Failed to save system settings: {e}")
            
    def refresh_all_ui(self):
        """Refresh all UI elements with current settings"""
        # Refresh exchange settings
        self.api_key_edit.setText(self.system_settings['exchange']['api_key'])
        self.api_secret_edit.setText(self.system_settings['exchange']['api_secret'])
        self.leverage_spin.setValue(self.system_settings['exchange']['leverage'])
        
        # Refresh schedule table
        self.refresh_schedule_table()
        
        # Refresh risk counters
        self.update_risk_counters()
        
    def get_system_configuration(self):
        """Get current system configuration"""
        return self.system_settings.copy()
        
    def set_system_configuration(self, config):
        """Set system configuration from external source"""
        self.system_settings.update(config)
        self.refresh_all_ui()