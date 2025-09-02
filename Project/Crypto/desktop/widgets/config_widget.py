"""Config Widget - Configuration management."""
from PyQt5.QtWidgets import QWidget, QVBoxLayout, QLabel
from PyQt5.QtGui import QFont

class ConfigWidget(QWidget):
    def __init__(self, config_manager, parent=None):
        super().__init__(parent)
        self.config_manager = config_manager
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        title = QLabel("Configuration")
        title.setFont(QFont("Arial", 12, QFont.Bold))
        layout.addWidget(title)