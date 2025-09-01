"""Main Web Routes."""
from flask import Blueprint, render_template

main_bp = Blueprint('main', __name__)

@main_bp.route('/')
def dashboard():
    return render_template('dashboard.html')

@main_bp.route('/trading')
def trading():
    return render_template('trading.html')

@main_bp.route('/positions')
def positions():
    return render_template('positions.html')