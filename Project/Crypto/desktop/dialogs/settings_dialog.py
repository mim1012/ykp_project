"""Settings Dialog - Application configuration."""
from PyQt5.QtWidgets import QDialog, QVBoxLayout, QTabWidget, QWidget, QPushButton

class SettingsDialog(QDialog):
    def __init__(self, config_manager, parent=None):
        super().__init__(parent)
        self.config_manager = config_manager
        self.setWindowTitle("Settings")
        self.setModal(True)
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        
        tabs = QTabWidget()
        
        # Trading tab
        trading_tab = QWidget()
        tabs.addTab(trading_tab, "Trading")
        
        # Risk tab
        risk_tab = QWidget()
        tabs.addTab(risk_tab, "Risk")
        
        # API tab
        api_tab = QWidget()
        tabs.addTab(api_tab, "API")
        
        layout.addWidget(tabs)
        
        # Buttons
        save_button = QPushButton("Save")
        save_button.clicked.connect(self.accept)
        layout.addWidget(save_button)