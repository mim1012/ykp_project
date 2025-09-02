"""Login Dialog - User authentication."""
from PyQt5.QtWidgets import QDialog, QVBoxLayout, QLineEdit, QPushButton, QLabel
from typing import Dict

class LoginDialog(QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle("Login")
        self.setModal(True)
        self.init_ui()
        
    def init_ui(self):
        layout = QVBoxLayout(self)
        
        layout.addWidget(QLabel("Username:"))
        self.username_edit = QLineEdit()
        layout.addWidget(self.username_edit)
        
        layout.addWidget(QLabel("Password:"))
        self.password_edit = QLineEdit()
        self.password_edit.setEchoMode(QLineEdit.Password)
        layout.addWidget(self.password_edit)
        
        login_button = QPushButton("Login")
        login_button.clicked.connect(self.accept)
        layout.addWidget(login_button)
        
    def get_credentials(self) -> Dict[str, str]:
        return {
            'username': self.username_edit.text(),
            'password': self.password_edit.text()
        }