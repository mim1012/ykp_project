"""Risk Widget - Risk management display."""
from PyQt5.QtWidgets import QWidget, QVBoxLayout, QLabel
from PyQt5.QtGui import QFont

class RiskWidget(QWidget):
    def __init__(self, risk_manager, logger, parent=None):
        super().__init__(parent)
        self.risk_manager = risk_manager
        self.logger = logger
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        title = QLabel("Risk Management")
        title.setFont(QFont("Arial", 12, QFont.Bold))
        layout.addWidget(title)
        
    def update_data(self):
        pass