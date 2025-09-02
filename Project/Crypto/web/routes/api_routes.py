"""API Routes."""
from flask import Blueprint, jsonify, current_app

api_bp = Blueprint('api', __name__)

@api_bp.route('/status')
def status():
    return jsonify({'status': 'running'})

@api_bp.route('/positions')
def get_positions():
    return jsonify({'positions': []})

@api_bp.route('/orders')
def get_orders():
    return jsonify({'orders': []})