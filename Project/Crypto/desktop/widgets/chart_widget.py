"""
Professional Real-Time Chart Widget
PRD-compliant implementation with pyqtgraph for high-performance real-time trading charts
"""

import sys
import numpy as np
from datetime import datetime, timedelta
from typing import List, Dict, Optional
import threading
import time

from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QPushButton, 
    QComboBox, QCheckBox, QFrame, QSpinBox
)
from PyQt5.QtCore import QTimer, pyqtSignal, QThread, pyqtSlot
from PyQt5.QtGui import QFont, QColor

import pyqtgraph as pg
from pyqtgraph import PlotWidget, PlotDataItem


class RealTimeDataThread(QThread):
    """Background thread for generating real-time market data"""
    
    new_data = pyqtSignal(dict)  # Signal for new market data
    
    def __init__(self):
        super().__init__()
        self.running = False
        self.current_price = 50000.0
        self.tick_count = 0
        
    def start_data_feed(self):
        """Start real-time data feed"""
        self.running = True
        self.start()
        
    def stop_data_feed(self):
        """Stop real-time data feed"""
        self.running = False
        self.quit()
        self.wait()
        
    def run(self):
        """Main data generation loop"""
        while self.running:
            # Generate realistic price movement
            change = np.random.normal(0, 10)  # Random price change
            self.current_price += change
            
            # Ensure price stays in reasonable range
            if self.current_price < 30000:
                self.current_price = 30000
            elif self.current_price > 80000:
                self.current_price = 80000
                
            timestamp = datetime.now()
            
            # Generate tick data
            tick_direction = 1 if change > 0 else -1
            volume = np.random.randint(10, 1000)
            
            data_point = {
                'timestamp': timestamp,
                'price': self.current_price,
                'volume': volume,
                'tick_direction': tick_direction,
                'high': self.current_price + abs(change),
                'low': self.current_price - abs(change),
                'open': self.current_price - change,
                'close': self.current_price
            }
            
            self.new_data.emit(data_point)
            self.tick_count += 1
            
            time.sleep(0.1)  # 100ms update rate


class ChartWidget(QWidget):
    """Professional real-time trading chart widget"""
    
    # Signals for chart events
    price_update = pyqtSignal(float)
    tick_update = pyqtSignal(dict)
    
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        
        # Chart data storage
        self.price_data = []
        self.timestamp_data = []
        self.volume_data = []
        self.max_points = 1000  # Maximum points to display
        
        # Price Channel data
        self.pc_period = 20
        self.pc_upper_data = []
        self.pc_lower_data = []
        self.pc_enabled = True
        
        # Moving averages
        self.ma_data = {}
        self.ma_periods = [20, 50, 200]
        self.ma_enabled = {'20': True, '50': True, '200': False}
        
        # Real-time data thread
        self.data_thread = RealTimeDataThread()
        self.data_thread.new_data.connect(self.update_chart_data)
        
        # Chart update timer
        self.update_timer = QTimer()
        self.update_timer.timeout.connect(self.update_display)
        
        self.init_ui()
        
    def init_ui(self):
        """Initialize chart widget UI"""
        layout = QVBoxLayout(self)
        layout.setSpacing(5)
        layout.setContentsMargins(5, 5, 5, 5)
        
        # Create header with controls
        header_frame = self.create_chart_header()
        layout.addWidget(header_frame)
        
        # Create main chart area
        self.create_chart_area()
        layout.addWidget(self.chart_frame, stretch=1)
        
        # Create price info footer
        footer_frame = self.create_price_footer()
        layout.addWidget(footer_frame)
        
    def create_chart_header(self):
        """Create chart header with controls"""
        header_frame = QFrame()
        header_frame.setMaximumHeight(50)
        header_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        """)
        
        header_layout = QHBoxLayout(header_frame)
        header_layout.setContentsMargins(10, 5, 10, 5)
        
        # Chart title
        title_label = QLabel("실시간 가격 차트 📈")
        title_label.setFont(QFont("Malgun Gothic", 12, QFont.Bold))
        title_label.setStyleSheet("color: #007bff;")
        header_layout.addWidget(title_label)
        
        header_layout.addStretch()
        
        # Timeframe selector
        header_layout.addWidget(QLabel("시간프레임:"))
        self.timeframe_combo = QComboBox()
        self.timeframe_combo.addItems(["1분", "5분", "15분", "1시간", "4시간", "1일"])
        self.timeframe_combo.setCurrentText("1분")
        self.timeframe_combo.currentTextChanged.connect(self.change_timeframe)
        header_layout.addWidget(self.timeframe_combo)
        
        # Price Channel toggle
        self.pc_checkbox = QCheckBox("Price Channel")
        self.pc_checkbox.setChecked(True)
        self.pc_checkbox.toggled.connect(self.toggle_price_channel)
        header_layout.addWidget(self.pc_checkbox)
        
        # PC Period setting
        header_layout.addWidget(QLabel("PC 기간:"))
        self.pc_period_spin = QSpinBox()
        self.pc_period_spin.setRange(5, 100)
        self.pc_period_spin.setValue(20)
        self.pc_period_spin.valueChanged.connect(self.update_pc_period)
        header_layout.addWidget(self.pc_period_spin)
        
        # Moving averages
        self.ma20_checkbox = QCheckBox("MA20")
        self.ma20_checkbox.setChecked(True)
        self.ma20_checkbox.toggled.connect(lambda checked: self.toggle_ma(20, checked))
        header_layout.addWidget(self.ma20_checkbox)
        
        self.ma50_checkbox = QCheckBox("MA50")
        self.ma50_checkbox.setChecked(True)
        self.ma50_checkbox.toggled.connect(lambda checked: self.toggle_ma(50, checked))
        header_layout.addWidget(self.ma50_checkbox)
        
        return header_frame
        
    def create_chart_area(self):
        """Create main chart area with pyqtgraph"""
        self.chart_frame = QFrame()
        chart_layout = QVBoxLayout(self.chart_frame)
        chart_layout.setContentsMargins(0, 0, 0, 0)
        
        # Configure pyqtgraph
        pg.setConfigOptions(antialias=True, useOpenGL=False)
        
        # Create main price chart
        self.price_chart = PlotWidget()
        self.price_chart.setBackground('#ffffff')
        self.price_chart.showGrid(True, True, alpha=0.3)
        
        # Configure chart appearance
        self.price_chart.setLabel('left', '가격 (USD)', color='#333', size='12pt')
        self.price_chart.setLabel('bottom', '시간', color='#333', size='12pt')
        self.price_chart.setTitle('BTC/USDT', color='#333', size='14pt')
        
        # Configure axes
        self.price_chart.getAxis('left').setTextPen('#333')
        self.price_chart.getAxis('bottom').setTextPen('#333')
        
        chart_layout.addWidget(self.price_chart)
        
        # Initialize plot items
        self.price_line = self.price_chart.plot(
            pen=pg.mkPen(color='#007bff', width=2),
            name='Price'
        )
        
        # Price Channel lines
        self.pc_upper_line = self.price_chart.plot(
            pen=pg.mkPen(color='#dc3545', width=1, style=pg.QtCore.Qt.DashLine),
            name='PC Upper'
        )
        
        self.pc_lower_line = self.price_chart.plot(
            pen=pg.mkPen(color='#28a745', width=1, style=pg.QtCore.Qt.DashLine),
            name='PC Lower'
        )
        
        # Price Channel fill
        self.pc_fill = pg.FillBetweenItem(
            self.pc_upper_line, self.pc_lower_line,
            brush=pg.mkBrush(color=(0, 123, 255, 30))  # Light blue fill
        )
        self.price_chart.addItem(self.pc_fill)
        
        # Moving average lines
        self.ma_lines = {}
        ma_colors = {'20': '#ffc107', '50': '#fd7e14', '200': '#6f42c1'}
        
        for period in self.ma_periods:
            self.ma_lines[str(period)] = self.price_chart.plot(
                pen=pg.mkPen(color=ma_colors.get(str(period), '#999'), width=1),
                name=f'MA{period}'
            )
        
        # Add legend
        self.price_chart.addLegend()
        
        # Create volume chart
        self.volume_chart = PlotWidget()
        self.volume_chart.setBackground('#ffffff')
        self.volume_chart.setMaximumHeight(150)
        self.volume_chart.showGrid(True, True, alpha=0.3)
        self.volume_chart.setLabel('left', '거래량', color='#333', size='10pt')
        self.volume_chart.getAxis('left').setTextPen('#333')
        self.volume_chart.getAxis('bottom').setTextPen('#333')
        
        # Volume bars
        self.volume_bars = pg.BarGraphItem(
            x=[], height=[],
            width=0.8, brush='#17a2b8', pen='#17a2b8'
        )
        self.volume_chart.addItem(self.volume_bars)
        
        chart_layout.addWidget(self.volume_chart)
        
        # Link x-axes for synchronized scrolling
        self.volume_chart.setXLink(self.price_chart)
        
    def create_price_footer(self):
        """Create price information footer"""
        footer_frame = QFrame()
        footer_frame.setMaximumHeight(60)
        footer_frame.setStyleSheet("""
            QFrame {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
        """)
        
        footer_layout = QVBoxLayout(footer_frame)
        footer_layout.setContentsMargins(10, 5, 10, 5)
        
        # Current price display
        self.current_price_label = QLabel("현재가: --")
        self.current_price_label.setFont(QFont("Consolas", 14, QFont.Bold))
        self.current_price_label.setStyleSheet("color: #007bff;")
        footer_layout.addWidget(self.current_price_label)
        
        # Price change and tick info
        info_layout = QHBoxLayout()
        
        self.price_change_label = QLabel("변동: --")
        self.price_change_label.setFont(QFont("Consolas", 10))
        info_layout.addWidget(self.price_change_label)
        
        info_layout.addStretch()
        
        self.tick_info_label = QLabel("틱: --")
        self.tick_info_label.setFont(QFont("Consolas", 10))
        info_layout.addWidget(self.tick_info_label)
        
        self.volume_info_label = QLabel("거래량: --")
        self.volume_info_label.setFont(QFont("Consolas", 10))
        info_layout.addWidget(self.volume_info_label)
        
        # Price Channel info
        self.pc_info_label = QLabel("PC: 상단-- | 하단--")
        self.pc_info_label.setFont(QFont("Consolas", 10))
        self.pc_info_label.setStyleSheet("color: #6c757d;")
        info_layout.addWidget(self.pc_info_label)
        
        footer_layout.addLayout(info_layout)
        
        return footer_frame
        
    @pyqtSlot(dict)
    def update_chart_data(self, data_point):
        """Update chart data with new price point"""
        timestamp = data_point['timestamp']
        price = data_point['price']
        volume = data_point['volume']
        tick_direction = data_point['tick_direction']
        
        # Add to data arrays
        self.timestamp_data.append(timestamp.timestamp())
        self.price_data.append(price)
        self.volume_data.append(volume)
        
        # Maintain maximum points
        if len(self.price_data) > self.max_points:
            self.timestamp_data.pop(0)
            self.price_data.pop(0)
            self.volume_data.pop(0)
            
        # Calculate Price Channel
        if self.pc_enabled and len(self.price_data) >= self.pc_period:
            self.calculate_price_channel()
            
        # Calculate moving averages
        self.calculate_moving_averages()
        
        # Emit signals
        self.price_update.emit(price)
        self.tick_update.emit({
            'price': price,
            'direction': tick_direction,
            'volume': volume,
            'timestamp': timestamp
        })
        
    def calculate_price_channel(self):
        """Calculate Price Channel upper and lower bounds"""
        if len(self.price_data) < self.pc_period:
            return
            
        self.pc_upper_data = []
        self.pc_lower_data = []
        
        for i in range(len(self.price_data)):
            if i < self.pc_period - 1:
                # Not enough data for calculation
                self.pc_upper_data.append(self.price_data[i])
                self.pc_lower_data.append(self.price_data[i])
            else:
                # Calculate PC for last N periods
                period_data = self.price_data[i - self.pc_period + 1:i + 1]
                upper = max(period_data)
                lower = min(period_data)
                self.pc_upper_data.append(upper)
                self.pc_lower_data.append(lower)
                
    def calculate_moving_averages(self):
        """Calculate moving averages"""
        for period in self.ma_periods:
            if str(period) not in self.ma_data:
                self.ma_data[str(period)] = []
                
            if len(self.price_data) >= period:
                # Calculate MA for current window
                self.ma_data[str(period)] = []
                for i in range(len(self.price_data)):
                    if i < period - 1:
                        self.ma_data[str(period)].append(self.price_data[i])
                    else:
                        ma_value = sum(self.price_data[i - period + 1:i + 1]) / period
                        self.ma_data[str(period)].append(ma_value)
                        
    def update_display(self):
        """Update chart display with current data"""
        if not self.price_data:
            return
            
        # Update price line
        self.price_line.setData(self.timestamp_data, self.price_data)
        
        # Update Price Channel
        if self.pc_enabled and self.pc_upper_data and self.pc_lower_data:
            self.pc_upper_line.setData(self.timestamp_data, self.pc_upper_data)
            self.pc_lower_line.setData(self.timestamp_data, self.pc_lower_data)
            
            # Update PC info
            if self.pc_upper_data and self.pc_lower_data:
                current_upper = self.pc_upper_data[-1]
                current_lower = self.pc_lower_data[-1]
                self.pc_info_label.setText(f"PC: 상단{current_upper:,.0f} | 하단{current_lower:,.0f}")
        
        # Update moving averages
        for period_str, ma_data in self.ma_data.items():
            if self.ma_enabled.get(period_str, False) and ma_data:
                self.ma_lines[period_str].setData(self.timestamp_data, ma_data)
            else:
                self.ma_lines[period_str].clear()
                
        # Update volume chart
        if self.volume_data:
            x_data = list(range(len(self.volume_data)))
            self.volume_bars.setOpts(x=x_data, height=self.volume_data)
            
        # Update price info
        current_price = self.price_data[-1]
        self.current_price_label.setText(f"현재가: {current_price:,.0f} USDT")
        
        if len(self.price_data) > 1:
            price_change = self.price_data[-1] - self.price_data[-2]
            change_percent = (price_change / self.price_data[-2]) * 100
            
            change_color = "#28a745" if price_change >= 0 else "#dc3545"
            change_symbol = "+" if price_change >= 0 else ""
            
            self.price_change_label.setText(
                f"변동: {change_symbol}{price_change:+,.0f} ({change_percent:+.2f}%)"
            )
            self.price_change_label.setStyleSheet(f"color: {change_color}; font-weight: bold;")
            
        # Update additional info
        if self.volume_data:
            current_volume = self.volume_data[-1]
            self.volume_info_label.setText(f"거래량: {current_volume:,}")
            
        # Auto-scroll to latest data
        if len(self.timestamp_data) > 50:
            x_range = [self.timestamp_data[-50], self.timestamp_data[-1]]
            self.price_chart.setXRange(*x_range, padding=0)
            
    # Control event handlers
    def change_timeframe(self, timeframe):
        """Change chart timeframe"""
        self.logger.info(f"Chart timeframe changed to: {timeframe}")
        # In real implementation, this would request different data
        
    def toggle_price_channel(self, enabled):
        """Toggle Price Channel display"""
        self.pc_enabled = enabled
        if not enabled:
            self.pc_upper_line.clear()
            self.pc_lower_line.clear()
            self.pc_info_label.setText("PC: 비활성")
        else:
            self.calculate_price_channel()
            
    def update_pc_period(self, period):
        """Update Price Channel period"""
        self.pc_period = period
        if self.pc_enabled:
            self.calculate_price_channel()
            
    def toggle_ma(self, period, enabled):
        """Toggle moving average display"""
        self.ma_enabled[str(period)] = enabled
        if not enabled:
            self.ma_lines[str(period)].clear()
            
    # Public methods
    def start_real_time_updates(self):
        """Start real-time chart updates"""
        self.data_thread.start_data_feed()
        self.update_timer.start(100)  # Update display every 100ms
        self.logger.info("Chart real-time updates started")
        
    def stop_updates(self):
        """Stop chart updates"""
        self.update_timer.stop()
        self.data_thread.stop_data_feed()
        self.logger.info("Chart updates stopped")
        
    def get_current_price(self):
        """Get current price"""
        return self.price_data[-1] if self.price_data else None
        
    def get_price_channel_data(self):
        """Get current Price Channel data"""
        if self.pc_upper_data and self.pc_lower_data:
            return {
                'upper': self.pc_upper_data[-1],
                'lower': self.pc_lower_data[-1],
                'period': self.pc_period
            }
        return None
        
    def get_tick_count(self):
        """Get current tick count"""
        return len(self.price_data)