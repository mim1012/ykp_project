"""Performance Widget - Trading performance metrics."""
from PyQt5.QtWidgets import QWidget, QVBoxLayout, QLabel, QGridLayout
from PyQt5.QtGui import QFont

class PerformanceWidget(QWidget):
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        title = QLabel("Performance")
        title.setFont(QFont("Arial", 12, QFont.Bold))
        layout.addWidget(title)
        
        metrics_layout = QGridLayout()
        layout.addLayout(metrics_layout)
        
    def update_data(self):
        pass