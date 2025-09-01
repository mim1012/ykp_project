"""Log Widget - System log display."""
from PyQt5.QtWidgets import QWidget, QVBoxLayout, QTextEdit, QLabel
from PyQt5.QtGui import QFont

class LogWidget(QWidget):
    def __init__(self, logger, parent=None):
        super().__init__(parent)
        self.logger = logger
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        title = QLabel("System Logs")
        title.setFont(QFont("Arial", 10, QFont.Bold))
        layout.addWidget(title)
        
        self.log_text = QTextEdit()
        self.log_text.setMaximumHeight(150)
        layout.addWidget(self.log_text)