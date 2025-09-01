"""OrderBook Widget - Displays order book data."""
from PyQt5.QtWidgets import QWidget, QVBoxLayout, QTableWidget, QLabel
from PyQt5.QtGui import QFont

class OrderBookWidget(QWidget):
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        title = QLabel("Order Book")
        title.setFont(QFont("Arial", 12, QFont.Bold))
        layout.addWidget(title)
        
        self.table = QTableWidget()
        layout.addWidget(self.table)