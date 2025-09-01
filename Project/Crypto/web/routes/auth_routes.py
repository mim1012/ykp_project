"""Authentication Routes."""
from flask import Blueprint, request, jsonify
from flask_jwt_extended import create_access_token

auth_bp = Blueprint('auth', __name__)

@auth_bp.route('/login', methods=['POST'])
def login():
    username = request.json.get('username')
    password = request.json.get('password')
    
    # TODO: Implement authentication
    if username == 'admin' and password == 'admin':
        token = create_access_token(identity=username)
        return jsonify({'token': token})
    
    return jsonify({'error': 'Invalid credentials'}), 401