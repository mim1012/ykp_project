"""
Position Widget with Real-Time PnL and PCS Stage Display
PRD-compliant implementation showing positions, PCS stages, and P&L
"""

import sys
from datetime import datetime, timedelta
from typing import Dict, List, Optional
import threading

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton,
    QFrame, QGridLayout, QProgressBar, QTableWidget, QTableWidgetItem,
    QHeaderView, QGroupBox, QScrollArea, QSplitter
)
from PyQt5.QtCore import QTimer, pyqtSignal, Qt, QThread, pyqtSlot
from PyQt5.QtGui import QFont, QColor, QPalette


class PositionDataThread(QThread):
    """Background thread for position data updates"""
    
    position_update = pyqtSignal(dict)
    pcs_update = pyqtSignal(dict)
    
    def __init__(self):
        super().__init__()
        self.running = False
        self.positions = {}
        self.demo_mode = True
        
        # Demo position data
        if self.demo_mode:
            self.positions = {
                'BTC/USDT': {
                    'symbol': 'BTC/USDT',
                    'side': 'LONG',
                    'size': 0.1,
                    'entry_price': 49500.0,
                    'current_price': 50000.0,
                    'unrealized_pnl': 50.0,
                    'realized_pnl': 0.0,
                    'margin': 495.0,
                    'leverage': 10,
                    'entry_time': datetime.now() - timedelta(minutes=15),
                    'pcs_stages': {
                        1: {'triggered': False, 'target': 49750.0, 'profit_pct': 0.5},
                        2: {'triggered': False, 'target': 50000.0, 'profit_pct': 1.0},
                        3: {'triggered': True, 'target': 50250.0, 'profit_pct': 1.5},
                        4: {'triggered': False, 'target': 50500.0, 'profit_pct': 2.0},
                    }
                }
            }
            
    def start_updates(self):
        """Start position updates"""
        self.running = True
        self.start()
        
    def stop_updates(self):
        """Stop position updates"""
        self.running = False
        self.quit()
        self.wait()
        
    def run(self):
        """Main update loop"""
        while self.running:
            if self.demo_mode:
                self.update_demo_positions()
            
            # Emit updates
            for symbol, position in self.positions.items():
                self.position_update.emit(position)
                self.pcs_update.emit({
                    'symbol': symbol,
                    'stages': position['pcs_stages']
                })
                
            self.msleep(1000)  # Update every second
            
    def update_demo_positions(self):
        """Update demo position data"""
        import random
        
        for symbol, position in self.positions.items():
            # Simulate price movement
            price_change = random.uniform(-50, 50)
            position['current_price'] += price_change
            
            # Update unrealized PnL
            if position['side'] == 'LONG':
                pnl = (position['current_price'] - position['entry_price']) * position['size']
            else:
                pnl = (position['entry_price'] - position['current_price']) * position['size']
                
            position['unrealized_pnl'] = pnl
            
            # Update PCS stages based on current price
            for stage_num, stage in position['pcs_stages'].items():
                if position['side'] == 'LONG':
                    if position['current_price'] >= stage['target'] and not stage['triggered']:
                        stage['triggered'] = True
                else:
                    if position['current_price'] <= stage['target'] and not stage['triggered']:
                        stage['triggered'] = True


class PositionWidget(QWidget):
    """Professional position monitoring widget"""
    
    # Signals
    position_closed = pyqtSignal(str)
    emergency_close = pyqtSignal(str)
    
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        
        # Position data
        self.positions = {}
        self.pcs_data = {}
        
        # Data thread
        self.data_thread = PositionDataThread()
        self.data_thread.position_update.connect(self.update_position_data)
        self.data_thread.pcs_update.connect(self.update_pcs_data)
        
        # Update timer
        self.display_timer = QTimer()
        self.display_timer.timeout.connect(self.update_display)
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize position widget UI"""
        layout = QVBoxLayout(self)
        layout.setSpacing(5)
        layout.setContentsMargins(5, 5, 5, 5)
        
        # Create header
        header_frame = self.create_header()
        layout.addWidget(header_frame)
        
        # Create main content area with splitter
        splitter = QSplitter(Qt.Vertical)
        
        # Position overview (top half)
        overview_widget = self.create_position_overview()
        splitter.addWidget(overview_widget)
        
        # PCS stages (bottom half)
        pcs_widget = self.create_pcs_stages()
        splitter.addWidget(pcs_widget)
        
        # Set splitter proportions
        splitter.setStretchFactor(0, 1)  # Overview
        splitter.setStretchFactor(1, 1)  # PCS stages
        
        layout.addWidget(splitter)
        
    def create_header(self):
        """Create position widget header"""
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
        title_label = QLabel("포지션 모니터 💼")
        title_label.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        title_label.setStyleSheet("color: #007bff;")
        header_layout.addWidget(title_label)
        
        header_layout.addStretch()
        
        # Total P&L
        self.total_pnl_label = QLabel("총 P&L: +0.00 USDT")
        self.total_pnl_label.setFont(QFont("Consolas", 11, QFont.Bold))
        self.total_pnl_label.setStyleSheet("color: #28a745;")
        header_layout.addWidget(self.total_pnl_label)
        
        # Position count
        self.position_count_label = QLabel("포지션: 0개")
        self.position_count_label.setFont(QFont("Malgun Gothic", 10))
        header_layout.addWidget(self.position_count_label)
        
        return header_frame
        
    def create_position_overview(self):
        """Create position overview section"""
        overview_widget = QWidget()
        layout = QVBoxLayout(overview_widget)
        layout.setContentsMargins(0, 0, 0, 0)
        
        # Position table
        self.position_table = QTableWidget()
        self.position_table.setColumnCount(8)
        self.position_table.setHorizontalHeaderLabels([
            "심볼", "방향", "크기", "진입가", "현재가", "미실현P&L", "수익률", "액션"
        ])
        
        # Configure table
        header = self.position_table.horizontalHeader()
        header.setSectionResizeMode(QHeaderView.Stretch)
        self.position_table.setAlternatingRowColors(True)
        self.position_table.setSelectionBehavior(QTableWidget.SelectRows)
        
        # Table styling
        self.position_table.setStyleSheet("""
            QTableWidget {
                gridline-color: #dee2e6;
                background-color: white;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
            QTableWidget::item {
                padding: 8px;
                border-bottom: 1px solid #f1f3f4;
            }
            QTableWidget::item:selected {
                background-color: #e3f2fd;
            }
            QHeaderView::section {
                background-color: #f8f9fa;
                border: none;
                border-right: 1px solid #dee2e6;
                border-bottom: 1px solid #dee2e6;
                padding: 8px;
                font-weight: bold;
            }
        """)
        
        layout.addWidget(self.position_table)
        
        # Quick action buttons
        button_frame = QFrame()
        button_layout = QHBoxLayout(button_frame)
        button_layout.setContentsMargins(5, 5, 5, 5)
        
        self.close_all_btn = QPushButton("🔴 모든 포지션 청산")
        self.close_all_btn.setStyleSheet("""
            QPushButton {
                background-color: #dc3545;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
                padding: 6px 12px;
            }
            QPushButton:hover {
                background-color: #c82333;
            }
        """)
        self.close_all_btn.clicked.connect(self.close_all_positions)
        button_layout.addWidget(self.close_all_btn)
        
        self.hedge_btn = QPushButton("⚖️ 헤지 포지션")
        self.hedge_btn.setStyleSheet("""
            QPushButton {
                background-color: #17a2b8;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
                padding: 6px 12px;
            }
            QPushButton:hover {
                background-color: #138496;
            }
        """)
        button_layout.addWidget(self.hedge_btn)
        
        button_layout.addStretch()
        
        # Real-time update indicator
        self.update_indicator = QLabel("🔄 실시간")
        self.update_indicator.setStyleSheet("color: #28a745; font-size: 10px;")
        button_layout.addWidget(self.update_indicator)
        
        layout.addWidget(button_frame)
        
        return overview_widget
        
    def create_pcs_stages(self):
        """Create PCS stages monitoring section"""
        pcs_widget = QWidget()
        layout = QVBoxLayout(pcs_widget)
        layout.setContentsMargins(0, 0, 0, 0)
        
        # PCS header
        pcs_header = QFrame()
        pcs_header.setMaximumHeight(30)
        pcs_header.setStyleSheet("""
            QFrame {
                background-color: #e9ecef;
                border: 1px solid #ced4da;
                border-radius: 4px;
            }
        """)
        
        pcs_header_layout = QHBoxLayout(pcs_header)
        pcs_header_layout.setContentsMargins(10, 5, 10, 5)
        
        pcs_title = QLabel("PCS 단계별 모니터링 📊")
        pcs_title.setFont(QFont("Malgun Gothic", 11, QFont.Bold))
        pcs_title.setStyleSheet("color: #495057;")
        pcs_header_layout.addWidget(pcs_title)
        
        pcs_header_layout.addStretch()
        
        self.pcs_status_label = QLabel("PCS: 비활성")
        self.pcs_status_label.setFont(QFont("Malgun Gothic", 10))
        pcs_header_layout.addWidget(self.pcs_status_label)
        
        layout.addWidget(pcs_header)
        
        # Scroll area for PCS stages
        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setHorizontalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        scroll.setVerticalScrollBarPolicy(Qt.ScrollBarAsNeeded)
        
        # PCS content widget
        self.pcs_content = QWidget()
        self.pcs_layout = QVBoxLayout(self.pcs_content)
        self.pcs_layout.setSpacing(5)
        
        # Initialize with empty state
        self.create_empty_pcs_display()
        
        scroll.setWidget(self.pcs_content)
        layout.addWidget(scroll)
        
        return pcs_widget
        
    def create_empty_pcs_display(self):
        """Create empty PCS display"""
        empty_label = QLabel("포지션이 없습니다.\nPCS 단계가 표시됩니다.")
        empty_label.setAlignment(Qt.AlignCenter)
        empty_label.setStyleSheet("color: #6c757d; font-style: italic; padding: 20px;")
        self.pcs_layout.addWidget(empty_label)
        
    def create_pcs_display_for_symbol(self, symbol, pcs_stages):
        """Create PCS display for specific symbol"""
        # Clear existing content
        self.clear_pcs_content()
        
        # Symbol header
        symbol_frame = QFrame()
        symbol_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                margin: 2px;
            }
        """)
        symbol_layout = QHBoxLayout(symbol_frame)
        symbol_layout.setContentsMargins(10, 5, 10, 5)
        
        symbol_label = QLabel(f"📈 {symbol}")
        symbol_label.setFont(QFont("Malgun Gothic", 11, QFont.Bold))
        symbol_layout.addWidget(symbol_label)
        
        symbol_layout.addStretch()
        
        triggered_count = sum(1 for stage in pcs_stages.values() if stage['triggered'])
        total_stages = len(pcs_stages)
        
        stage_progress = QProgressBar()
        stage_progress.setRange(0, total_stages)
        stage_progress.setValue(triggered_count)
        stage_progress.setFormat(f"{triggered_count}/{total_stages} 단계 달성")
        stage_progress.setStyleSheet("""
            QProgressBar {
                border: 2px solid grey;
                border-radius: 5px;
                text-align: center;
                font-size: 10px;
                font-weight: bold;
            }
            QProgressBar::chunk {
                background-color: #28a745;
                width: 10px;
                margin: 0.5px;
            }
        """)
        symbol_layout.addWidget(stage_progress)
        
        self.pcs_layout.addWidget(symbol_frame)
        
        # PCS stages grid
        stages_frame = QFrame()
        stages_frame.setStyleSheet("""
            QFrame {
                border: 1px solid #e9ecef;
                border-radius: 4px;
                background-color: white;
            }
        """)
        stages_layout = QGridLayout(stages_frame)
        stages_layout.setSpacing(8)
        stages_layout.setContentsMargins(10, 10, 10, 10)
        
        # Headers
        headers = ["단계", "목표가", "수익률", "상태", "시간"]
        for col, header in enumerate(headers):
            header_label = QLabel(header)
            header_label.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
            header_label.setStyleSheet("color: #495057; background-color: #f8f9fa; padding: 5px; border: 1px solid #dee2e6;")
            header_label.setAlignment(Qt.AlignCenter)
            stages_layout.addWidget(header_label, 0, col)
            
        # Stage rows
        for row, (stage_num, stage_data) in enumerate(sorted(pcs_stages.items()), 1):
            # Stage number
            stage_label = QLabel(f"{stage_num}단계")
            stage_label.setAlignment(Qt.AlignCenter)
            stage_label.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
            stages_layout.addWidget(stage_label, row, 0)
            
            # Target price
            target_label = QLabel(f"{stage_data['target']:,.0f}")
            target_label.setAlignment(Qt.AlignCenter)
            target_label.setFont(QFont("Consolas", 10))
            stages_layout.addWidget(target_label, row, 1)
            
            # Profit percentage
            profit_label = QLabel(f"{stage_data['profit_pct']:.1f}%")
            profit_label.setAlignment(Qt.AlignCenter)
            profit_label.setFont(QFont("Consolas", 10, QFont.Bold))
            stages_layout.addWidget(profit_label, row, 2)
            
            # Status
            if stage_data['triggered']:
                status_label = QLabel("✅ 달성")
                status_label.setStyleSheet("color: #28a745; font-weight: bold;")
            else:
                status_label = QLabel("⏳ 대기")
                status_label.setStyleSheet("color: #6c757d;")
                
            status_label.setAlignment(Qt.AlignCenter)
            stages_layout.addWidget(status_label, row, 3)
            
            # Time (simulated)
            time_label = QLabel("--:--")
            if stage_data['triggered']:
                time_label.setText(datetime.now().strftime("%H:%M"))
                
            time_label.setAlignment(Qt.AlignCenter)
            time_label.setFont(QFont("Consolas", 10))
            stages_layout.addWidget(time_label, row, 4)
            
        self.pcs_layout.addWidget(stages_frame)
        
        # Summary
        summary_frame = QFrame()
        summary_frame.setStyleSheet("""
            QFrame {
                background-color: #e8f5e8;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin: 2px;
            }
        """)
        summary_layout = QHBoxLayout(summary_frame)
        summary_layout.setContentsMargins(10, 5, 10, 5)
        
        summary_text = f"총 {total_stages}단계 중 {triggered_count}단계 달성"
        if triggered_count > 0:
            achievement_rate = (triggered_count / total_stages) * 100
            summary_text += f" ({achievement_rate:.0f}%)"
            
        summary_label = QLabel(summary_text)
        summary_label.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
        summary_label.setStyleSheet("color: #155724;")
        summary_layout.addWidget(summary_label)
        
        self.pcs_layout.addWidget(summary_frame)
        
        # Add stretch at bottom
        self.pcs_layout.addStretch()
        
    def clear_pcs_content(self):
        """Clear PCS content area"""
        while self.pcs_layout.count():
            child = self.pcs_layout.takeAt(0)
            if child.widget():
                child.widget().deleteLater()
                
    # Data update methods
    @pyqtSlot(dict)
    def update_position_data(self, position_data):
        """Update position data"""
        symbol = position_data['symbol']
        self.positions[symbol] = position_data
        
    @pyqtSlot(dict)
    def update_pcs_data(self, pcs_data):
        """Update PCS stage data"""
        symbol = pcs_data['symbol']
        self.pcs_data[symbol] = pcs_data['stages']
        
    def update_display(self):
        """Update all display elements"""
        self.update_position_table()
        self.update_pcs_display()
        self.update_summary()
        
    def update_position_table(self):
        """Update position table"""
        self.position_table.setRowCount(len(self.positions))
        
        total_pnl = 0.0
        
        for row, (symbol, position) in enumerate(self.positions.items()):
            # Symbol
            symbol_item = QTableWidgetItem(symbol)
            symbol_item.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
            self.position_table.setItem(row, 0, symbol_item)
            
            # Direction
            side_item = QTableWidgetItem(position['side'])
            side_color = "#28a745" if position['side'] == 'LONG' else "#dc3545"
            side_item.setForeground(QColor(side_color))
            side_item.setFont(QFont("Malgun Gothic", 10, QFont.Bold))
            self.position_table.setItem(row, 1, side_item)
            
            # Size
            size_item = QTableWidgetItem(f"{position['size']:.3f}")
            size_item.setFont(QFont("Consolas", 10))
            self.position_table.setItem(row, 2, size_item)
            
            # Entry price
            entry_item = QTableWidgetItem(f"{position['entry_price']:,.0f}")
            entry_item.setFont(QFont("Consolas", 10))
            self.position_table.setItem(row, 3, entry_item)
            
            # Current price
            current_item = QTableWidgetItem(f"{position['current_price']:,.0f}")
            current_item.setFont(QFont("Consolas", 10, QFont.Bold))
            self.position_table.setItem(row, 4, current_item)
            
            # Unrealized P&L
            pnl = position['unrealized_pnl']
            pnl_item = QTableWidgetItem(f"{pnl:+.2f}")
            pnl_color = "#28a745" if pnl >= 0 else "#dc3545"
            pnl_item.setForeground(QColor(pnl_color))
            pnl_item.setFont(QFont("Consolas", 10, QFont.Bold))
            self.position_table.setItem(row, 5, pnl_item)
            
            # Profit percentage
            profit_pct = (pnl / position['margin']) * 100
            pct_item = QTableWidgetItem(f"{profit_pct:+.2f}%")
            pct_item.setForeground(QColor(pnl_color))
            pct_item.setFont(QFont("Consolas", 10, QFont.Bold))
            self.position_table.setItem(row, 6, pct_item)
            
            # Action button
            close_btn = QPushButton("청산")
            close_btn.setStyleSheet("""
                QPushButton {
                    background-color: #dc3545;
                    color: white;
                    border: none;
                    border-radius: 3px;
                    padding: 4px 8px;
                    font-size: 9px;
                    font-weight: bold;
                }
                QPushButton:hover {
                    background-color: #c82333;
                }
            """)
            close_btn.clicked.connect(lambda checked, s=symbol: self.close_position(s))
            self.position_table.setCellWidget(row, 7, close_btn)
            
            total_pnl += pnl
            
        # Update total P&L
        pnl_color = "#28a745" if total_pnl >= 0 else "#dc3545"
        self.total_pnl_label.setText(f"총 P&L: {total_pnl:+.2f} USDT")
        self.total_pnl_label.setStyleSheet(f"color: {pnl_color}; font-weight: bold;")
        
    def update_pcs_display(self):
        """Update PCS stages display"""
        if not self.pcs_data:
            if self.pcs_layout.count() == 0:
                self.create_empty_pcs_display()
            return
            
        # Show PCS for first position (in real app, could select which one)
        if self.pcs_data:
            symbol = next(iter(self.pcs_data.keys()))
            stages = self.pcs_data[symbol]
            self.create_pcs_display_for_symbol(symbol, stages)
            
            # Update PCS status
            triggered_count = sum(1 for stage in stages.values() if stage['triggered'])
            total_stages = len(stages)
            self.pcs_status_label.setText(f"PCS: {triggered_count}/{total_stages} 활성")
            
            if triggered_count > 0:
                self.pcs_status_label.setStyleSheet("color: #28a745; font-weight: bold;")
            else:
                self.pcs_status_label.setStyleSheet("color: #6c757d;")
                
    def update_summary(self):
        """Update summary information"""
        position_count = len(self.positions)
        self.position_count_label.setText(f"포지션: {position_count}개")
        
        # Update indicators
        if position_count > 0:
            self.update_indicator.setText("🟢 실시간")
            self.update_indicator.setStyleSheet("color: #28a745; font-size: 10px;")
        else:
            self.update_indicator.setText("🔵 대기중")
            self.update_indicator.setStyleSheet("color: #17a2b8; font-size: 10px;")
            
    # Action methods
    def close_position(self, symbol):
        """Close specific position"""
        from PyQt5.QtWidgets import QMessageBox
        
        reply = QMessageBox.question(
            self, '포지션 청산',
            f'{symbol} 포지션을 청산하시겠습니까?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            self.logger.info(f"Position closed: {symbol}")
            if symbol in self.positions:
                del self.positions[symbol]
            if symbol in self.pcs_data:
                del self.pcs_data[symbol]
                
            self.position_closed.emit(symbol)
            QMessageBox.information(self, "청산 완료", f"{symbol} 포지션이 청산되었습니다.")
            
    def close_all_positions(self):
        """Close all positions"""
        from PyQt5.QtWidgets import QMessageBox
        
        if not self.positions:
            QMessageBox.information(self, "포지션 없음", "청산할 포지션이 없습니다.")
            return
            
        reply = QMessageBox.question(
            self, '전체 청산',
            '모든 포지션을 청산하시겠습니까?',
            QMessageBox.Yes | QMessageBox.No,
            QMessageBox.No
        )
        
        if reply == QMessageBox.Yes:
            symbols = list(self.positions.keys())
            self.positions.clear()
            self.pcs_data.clear()
            
            for symbol in symbols:
                self.position_closed.emit(symbol)
                
            self.logger.info("All positions closed")
            QMessageBox.information(self, "청산 완료", "모든 포지션이 청산되었습니다.")
            
    # Public methods
    def start_updates(self):
        """Start real-time updates"""
        self.data_thread.start_updates()
        self.display_timer.start(1000)  # Update display every second
        self.logger.info("Position widget updates started")
        
    def stop_updates(self):
        """Stop updates"""
        self.display_timer.stop()
        self.data_thread.stop_updates()
        self.logger.info("Position widget updates stopped")
        
    def add_position(self, position_data):
        """Add new position"""
        symbol = position_data['symbol']
        self.positions[symbol] = position_data
        self.logger.info(f"Position added: {symbol}")
        
    def get_positions(self):
        """Get all current positions"""
        return self.positions.copy()
        
    def get_total_pnl(self):
        """Get total unrealized P&L"""
        return sum(pos['unrealized_pnl'] for pos in self.positions.values())