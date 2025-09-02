"""
Trading Panel Widget

Main trading interface for order entry and execution.
"""

from typing import Dict, Any, Optional
from PyQt5.QtWidgets import (
    QWidget, QVBoxLayout, QHBoxLayout, QLabel, QLineEdit,
    QPushButton, QComboBox, QSpinBox, QDoubleSpinBox,
    QGroupBox, QGridLayout, QCheckBox
)
from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtGui import QFont

from ...core.logger import SystemLogger


class TradingPanel(QWidget):
    """Trading panel for order entry and execution."""
    
    # Signals
    order_submitted = pyqtSignal(dict)
    
    def __init__(self, trading_engine, logger: SystemLogger, parent=None):
        """Initialize trading panel."""
        super().__init__(parent)
        self.trading_engine = trading_engine
        self.logger = logger
        
        self.init_ui()
        
    def init_ui(self) -> None:
        """Initialize user interface."""
        layout = QVBoxLayout(self)
        
        # Title
        title = QLabel("Trading Panel")
        title.setFont(QFont("Arial", 14, QFont.Bold))
        title.setAlignment(Qt.AlignCenter)
        layout.addWidget(title)
        
        # Order Entry Group
        order_group = QGroupBox("Order Entry")
        order_layout = QGridLayout(order_group)
        
        # Symbol selection
        order_layout.addWidget(QLabel("Symbol:"), 0, 0)
        self.symbol_combo = QComboBox()
        self.symbol_combo.addItems([
            "BTCUSDT", "ETHUSDT", "ADAUSDT", "DOTUSDT", "LINKUSDT"
        ])
        order_layout.addWidget(self.symbol_combo, 0, 1)
        
        # Order type
        order_layout.addWidget(QLabel("Type:"), 1, 0)
        self.order_type_combo = QComboBox()
        self.order_type_combo.addItems(["Market", "Limit", "Stop"])
        order_layout.addWidget(self.order_type_combo, 1, 1)
        
        # Side selection
        order_layout.addWidget(QLabel("Side:"), 2, 0)
        self.side_combo = QComboBox()
        self.side_combo.addItems(["Buy", "Sell"])
        order_layout.addWidget(self.side_combo, 2, 1)
        
        # Quantity
        order_layout.addWidget(QLabel("Quantity:"), 3, 0)
        self.quantity_spin = QDoubleSpinBox()
        self.quantity_spin.setDecimals(6)
        self.quantity_spin.setMaximum(1000000)
        self.quantity_spin.setValue(0.001)
        order_layout.addWidget(self.quantity_spin, 3, 1)
        
        # Price (for limit orders)
        order_layout.addWidget(QLabel("Price:"), 4, 0)
        self.price_spin = QDoubleSpinBox()
        self.price_spin.setDecimals(2)
        self.price_spin.setMaximum(1000000)
        self.price_spin.setEnabled(False)  # Disabled for market orders
        order_layout.addWidget(self.price_spin, 4, 1)
        
        # Connect order type change
        self.order_type_combo.currentTextChanged.connect(self.on_order_type_changed)
        
        layout.addWidget(order_group)
        
        # Order buttons
        button_layout = QHBoxLayout()
        
        self.buy_button = QPushButton("BUY")
        self.buy_button.setStyleSheet("background-color: #4CAF50; color: white; font-weight: bold;")
        self.buy_button.clicked.connect(lambda: self.submit_order("buy"))
        button_layout.addWidget(self.buy_button)
        
        self.sell_button = QPushButton("SELL")
        self.sell_button.setStyleSheet("background-color: #F44336; color: white; font-weight: bold;")
        self.sell_button.clicked.connect(lambda: self.submit_order("sell"))
        button_layout.addWidget(self.sell_button)
        
        layout.addLayout(button_layout)
        
        # Quick Actions Group
        quick_group = QGroupBox("Quick Actions")
        quick_layout = QVBoxLayout(quick_group)
        
        # Close all positions button
        close_all_button = QPushButton("Close All Positions")
        close_all_button.clicked.connect(self.close_all_positions)
        quick_layout.addWidget(close_all_button)
        
        # Cancel all orders button
        cancel_all_button = QPushButton("Cancel All Orders")
        cancel_all_button.clicked.connect(self.cancel_all_orders)
        quick_layout.addWidget(cancel_all_button)
        
        layout.addWidget(quick_group)
        
        # Trading Controls Group
        controls_group = QGroupBox("Trading Controls")
        controls_layout = QVBoxLayout(controls_group)
        
        # Auto trading checkbox
        self.auto_trading_check = QCheckBox("Enable Auto Trading")
        controls_layout.addWidget(self.auto_trading_check)
        
        # Risk management checkbox
        self.risk_management_check = QCheckBox("Enable Risk Management")
        self.risk_management_check.setChecked(True)
        controls_layout.addWidget(self.risk_management_check)
        
        layout.addWidget(controls_group)
        
        # Status
        self.status_label = QLabel("Ready")
        self.status_label.setStyleSheet("color: green;")
        layout.addWidget(self.status_label)
        
        # Stretch
        layout.addStretch()
        
    def on_order_type_changed(self, order_type: str) -> None:
        """Handle order type change."""
        if order_type == "Market":
            self.price_spin.setEnabled(False)
        else:
            self.price_spin.setEnabled(True)
            
    def submit_order(self, side: str) -> None:
        """Submit order."""
        try:
            symbol = self.symbol_combo.currentText()
            order_type = self.order_type_combo.currentText().lower()
            quantity = self.quantity_spin.value()
            price = self.price_spin.value() if self.price_spin.isEnabled() else None
            
            # Validate order
            if quantity <= 0:
                self.status_label.setText("Error: Invalid quantity")
                self.status_label.setStyleSheet("color: red;")
                return
                
            if order_type != "market" and (price is None or price <= 0):
                self.status_label.setText("Error: Invalid price")
                self.status_label.setStyleSheet("color: red;")
                return
                
            # Create order data
            order_data = {
                'symbol': symbol,
                'side': side,
                'type': order_type,
                'quantity': quantity,
                'price': price,
                'timestamp': None  # Will be set by trading engine
            }
            
            # TODO: Submit order through trading engine
            self.logger.info(f"Order submitted: {order_data}")
            self.order_submitted.emit(order_data)
            
            self.status_label.setText(f"Order submitted: {side.upper()} {quantity} {symbol}")
            self.status_label.setStyleSheet("color: green;")
            
        except Exception as e:
            self.logger.error(f"Error submitting order: {e}")
            self.status_label.setText(f"Error: {e}")
            self.status_label.setStyleSheet("color: red;")
            
    def close_all_positions(self) -> None:
        """Close all open positions."""
        try:
            # TODO: Implement through trading engine
            self.logger.info("Closing all positions")
            self.status_label.setText("Closing all positions...")
            self.status_label.setStyleSheet("color: orange;")
        except Exception as e:
            self.logger.error(f"Error closing positions: {e}")
            
    def cancel_all_orders(self) -> None:
        """Cancel all pending orders."""
        try:
            # TODO: Implement through trading engine
            self.logger.info("Cancelling all orders")
            self.status_label.setText("Cancelling all orders...")
            self.status_label.setStyleSheet("color: orange;")
        except Exception as e:
            self.logger.error(f"Error cancelling orders: {e}")
            
    def update_status(self, message: str, color: str = "black") -> None:
        """Update status message."""
        self.status_label.setText(message)
        self.status_label.setStyleSheet(f"color: {color};")