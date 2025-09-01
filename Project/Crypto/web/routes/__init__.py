"""Web Routes Package."""

from .main_routes import main_bp
from .api_routes import api_bp  
from .auth_routes import auth_bp

__all__ = ['main_bp', 'api_bp', 'auth_bp']