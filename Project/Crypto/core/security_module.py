"""
Security Module

Implements comprehensive security features including Fernet encryption,
JWT token management, password hashing, and security utilities.
"""

from typing import Dict, Any, Optional, Union
from datetime import datetime, timedelta
import secrets
import hashlib
import hmac
import base64
import json
from pathlib import Path

from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
import jwt
from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError, HashingError

from .logger import SystemLogger


class SecurityModule:
    """
    Comprehensive security module for the trading system.
    
    Features:
    - Fernet encryption for sensitive data
    - JWT token generation and validation
    - Argon2 password hashing
    - API key validation
    - Rate limiting utilities
    - Secure random generation
    """
    
    def __init__(self, logger: SystemLogger, master_key: Optional[str] = None):
        """Initialize security module."""
        self.logger = logger
        self.password_hasher = PasswordHasher()
        
        # Initialize encryption
        self.master_key = master_key or self._get_or_create_master_key()
        self.fernet = Fernet(self.master_key)
        
        # JWT configuration
        self.jwt_algorithm = 'HS256'
        self.jwt_secret_key = None
        
        # Rate limiting storage
        self.rate_limit_storage: Dict[str, Dict[str, Any]] = {}
        
    def _get_or_create_master_key(self) -> bytes:
        """Get or create master encryption key."""
        key_file = Path("config/master.key")
        key_file.parent.mkdir(parents=True, exist_ok=True)
        
        try:
            if key_file.exists():
                with open(key_file, 'rb') as f:
                    key = f.read()
                self.logger.info("Loaded existing master key")
                return key
            else:
                key = Fernet.generate_key()
                with open(key_file, 'wb') as f:
                    f.write(key)
                # Set restrictive permissions on key file
                key_file.chmod(0o600)
                self.logger.info("Generated new master key")
                return key
        except Exception as e:
            self.logger.error(f"Failed to handle master key: {e}")
            # Fallback to in-memory key
            return Fernet.generate_key()
            
    # Encryption/Decryption Methods
    
    def encrypt_data(self, data: Union[str, bytes]) -> bytes:
        """Encrypt data using Fernet."""
        try:
            if isinstance(data, str):
                data = data.encode('utf-8')
            encrypted = self.fernet.encrypt(data)
            return encrypted
        except Exception as e:
            self.logger.error(f"Encryption failed: {e}")
            raise
            
    def decrypt_data(self, encrypted_data: bytes) -> bytes:
        """Decrypt data using Fernet."""
        try:
            decrypted = self.fernet.decrypt(encrypted_data)
            return decrypted
        except Exception as e:
            self.logger.error(f"Decryption failed: {e}")
            raise
            
    def encrypt_string(self, plaintext: str) -> str:
        """Encrypt string and return base64 encoded result."""
        try:
            encrypted_bytes = self.encrypt_data(plaintext)
            return base64.b64encode(encrypted_bytes).decode('utf-8')
        except Exception as e:
            self.logger.error(f"String encryption failed: {e}")
            raise
            
    def decrypt_string(self, encrypted_string: str) -> str:
        """Decrypt base64 encoded string."""
        try:
            encrypted_bytes = base64.b64decode(encrypted_string.encode('utf-8'))
            decrypted_bytes = self.decrypt_data(encrypted_bytes)
            return decrypted_bytes.decode('utf-8')
        except Exception as e:
            self.logger.error(f"String decryption failed: {e}")
            raise
            
    def encrypt_json(self, data: Dict[str, Any]) -> str:
        """Encrypt JSON data and return base64 encoded result."""
        try:
            json_string = json.dumps(data, separators=(',', ':'))
            return self.encrypt_string(json_string)
        except Exception as e:
            self.logger.error(f"JSON encryption failed: {e}")
            raise
            
    def decrypt_json(self, encrypted_json: str) -> Dict[str, Any]:
        """Decrypt base64 encoded JSON data."""
        try:
            json_string = self.decrypt_string(encrypted_json)
            return json.loads(json_string)
        except Exception as e:
            self.logger.error(f"JSON decryption failed: {e}")
            raise
            
    # Password Hashing Methods
    
    def hash_password(self, password: str) -> str:
        """Hash password using Argon2."""
        try:
            hashed = self.password_hasher.hash(password)
            return hashed
        except HashingError as e:
            self.logger.error(f"Password hashing failed: {e}")
            raise
            
    def verify_password(self, password: str, hashed_password: str) -> bool:
        """Verify password against Argon2 hash."""
        try:
            self.password_hasher.verify(hashed_password, password)
            return True
        except VerifyMismatchError:
            return False
        except Exception as e:
            self.logger.error(f"Password verification failed: {e}")
            return False
            
    def check_password_strength(self, password: str) -> Dict[str, Any]:
        """Check password strength and return analysis."""
        analysis = {
            'length': len(password),
            'has_uppercase': any(c.isupper() for c in password),
            'has_lowercase': any(c.islower() for c in password), 
            'has_digits': any(c.isdigit() for c in password),
            'has_special': any(not c.isalnum() for c in password),
            'score': 0,
            'feedback': []
        }
        
        # Calculate strength score
        if analysis['length'] >= 8:
            analysis['score'] += 1
        else:
            analysis['feedback'].append('Password should be at least 8 characters long')
            
        if analysis['length'] >= 12:
            analysis['score'] += 1
            
        if analysis['has_uppercase']:
            analysis['score'] += 1
        else:
            analysis['feedback'].append('Password should contain uppercase letters')
            
        if analysis['has_lowercase']:
            analysis['score'] += 1
        else:
            analysis['feedback'].append('Password should contain lowercase letters')
            
        if analysis['has_digits']:
            analysis['score'] += 1
        else:
            analysis['feedback'].append('Password should contain numbers')
            
        if analysis['has_special']:
            analysis['score'] += 1
        else:
            analysis['feedback'].append('Password should contain special characters')
            
        # Determine strength level
        if analysis['score'] >= 6:
            analysis['strength'] = 'Strong'
        elif analysis['score'] >= 4:
            analysis['strength'] = 'Medium'
        else:
            analysis['strength'] = 'Weak'
            
        return analysis
        
    # JWT Token Methods
    
    def set_jwt_secret(self, secret_key: str) -> None:
        """Set JWT secret key."""
        self.jwt_secret_key = secret_key
        
    def generate_jwt_token(self, payload: Dict[str, Any], expiry_hours: int = 24) -> str:
        """Generate JWT token with expiration."""
        if not self.jwt_secret_key:
            raise ValueError("JWT secret key not set")
            
        try:
            # Add expiration time
            payload['exp'] = datetime.utcnow() + timedelta(hours=expiry_hours)
            payload['iat'] = datetime.utcnow()
            
            token = jwt.encode(payload, self.jwt_secret_key, algorithm=self.jwt_algorithm)
            return token
        except Exception as e:
            self.logger.error(f"JWT token generation failed: {e}")
            raise
            
    def validate_jwt_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Validate JWT token and return payload."""
        if not self.jwt_secret_key:
            self.logger.error("JWT secret key not set")
            return None
            
        try:
            payload = jwt.decode(token, self.jwt_secret_key, algorithms=[self.jwt_algorithm])
            return payload
        except jwt.ExpiredSignatureError:
            self.logger.warning("JWT token has expired")
            return None
        except jwt.InvalidTokenError as e:
            self.logger.warning(f"Invalid JWT token: {e}")
            return None
        except Exception as e:
            self.logger.error(f"JWT token validation failed: {e}")
            return None
            
    def refresh_jwt_token(self, token: str, expiry_hours: int = 24) -> Optional[str]:
        """Refresh JWT token if valid."""
        payload = self.validate_jwt_token(token)
        if payload:
            # Remove old timestamps
            payload.pop('exp', None)
            payload.pop('iat', None)
            return self.generate_jwt_token(payload, expiry_hours)
        return None
        
    # API Key Methods
    
    def generate_api_key(self, length: int = 32) -> str:
        """Generate secure API key."""
        return secrets.token_urlsafe(length)
        
    def generate_api_secret(self, length: int = 64) -> str:
        """Generate secure API secret."""
        return secrets.token_urlsafe(length)
        
    def validate_api_signature(self, 
                             api_secret: str,
                             method: str,
                             path: str,
                             timestamp: str,
                             body: str = "") -> str:
        """Generate API signature for validation."""
        message = f"{method}{path}{timestamp}{body}"
        signature = hmac.new(
            api_secret.encode('utf-8'),
            message.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
        return signature
        
    def verify_api_signature(self,
                           api_secret: str,
                           method: str,
                           path: str,
                           timestamp: str,
                           received_signature: str,
                           body: str = "") -> bool:
        """Verify API signature."""
        try:
            expected_signature = self.validate_api_signature(
                api_secret, method, path, timestamp, body
            )
            return hmac.compare_digest(expected_signature, received_signature)
        except Exception as e:
            self.logger.error(f"API signature verification failed: {e}")
            return False
            
    # Rate Limiting Methods
    
    def check_rate_limit(self, 
                        identifier: str,
                        limit: int,
                        window_seconds: int = 60) -> tuple[bool, Dict[str, Any]]:
        """Check if request is within rate limit."""
        now = datetime.utcnow()
        
        if identifier not in self.rate_limit_storage:
            self.rate_limit_storage[identifier] = {
                'requests': [],
                'blocked_until': None
            }
            
        storage = self.rate_limit_storage[identifier]
        
        # Check if currently blocked
        if storage['blocked_until'] and now < storage['blocked_until']:
            remaining_block = (storage['blocked_until'] - now).seconds
            return False, {
                'allowed': False,
                'remaining': 0,
                'reset_time': storage['blocked_until'],
                'blocked_seconds': remaining_block
            }
            
        # Clean old requests outside window
        window_start = now - timedelta(seconds=window_seconds)
        storage['requests'] = [req_time for req_time in storage['requests'] 
                             if req_time > window_start]
        
        # Check if within limit
        current_requests = len(storage['requests'])
        
        if current_requests >= limit:
            # Block for remaining window time
            storage['blocked_until'] = now + timedelta(seconds=window_seconds)
            return False, {
                'allowed': False,
                'remaining': 0,
                'reset_time': storage['blocked_until'],
                'blocked_seconds': window_seconds
            }
        else:
            # Allow request
            storage['requests'].append(now)
            return True, {
                'allowed': True,
                'remaining': limit - current_requests - 1,
                'reset_time': now + timedelta(seconds=window_seconds),
                'blocked_seconds': 0
            }
            
    def reset_rate_limit(self, identifier: str) -> None:
        """Reset rate limit for identifier."""
        if identifier in self.rate_limit_storage:
            del self.rate_limit_storage[identifier]
            
    # Utility Methods
    
    def generate_random_key(self, length: int = 32) -> bytes:
        """Generate random key for various uses."""
        return secrets.token_bytes(length)
        
    def generate_random_string(self, length: int = 16) -> str:
        """Generate random string."""
        return secrets.token_urlsafe(length)
        
    def generate_nonce(self) -> str:
        """Generate cryptographic nonce."""
        return secrets.token_hex(16)
        
    def hash_data(self, data: str, algorithm: str = 'sha256') -> str:
        """Hash data using specified algorithm."""
        try:
            if algorithm == 'sha256':
                return hashlib.sha256(data.encode('utf-8')).hexdigest()
            elif algorithm == 'sha512':
                return hashlib.sha512(data.encode('utf-8')).hexdigest()
            elif algorithm == 'md5':
                return hashlib.md5(data.encode('utf-8')).hexdigest()
            else:
                raise ValueError(f"Unsupported hash algorithm: {algorithm}")
        except Exception as e:
            self.logger.error(f"Data hashing failed: {e}")
            raise
            
    def verify_data_integrity(self, data: str, hash_value: str, algorithm: str = 'sha256') -> bool:
        """Verify data integrity using hash."""
        try:
            computed_hash = self.hash_data(data, algorithm)
            return hmac.compare_digest(computed_hash, hash_value)
        except Exception as e:
            self.logger.error(f"Data integrity verification failed: {e}")
            return False
            
    def create_checksum(self, file_path: str, algorithm: str = 'sha256') -> str:
        """Create checksum for file."""
        try:
            hash_obj = hashlib.new(algorithm)
            with open(file_path, 'rb') as f:
                for chunk in iter(lambda: f.read(4096), b""):
                    hash_obj.update(chunk)
            return hash_obj.hexdigest()
        except Exception as e:
            self.logger.error(f"Checksum creation failed: {e}")
            raise
            
    def sanitize_input(self, input_string: str, max_length: int = 1000) -> str:
        """Sanitize user input."""
        if not isinstance(input_string, str):
            input_string = str(input_string)
            
        # Truncate to max length
        sanitized = input_string[:max_length]
        
        # Remove potential script injection characters
        dangerous_chars = ['<', '>', '"', "'", '&', '\x00', '\n', '\r']
        for char in dangerous_chars:
            sanitized = sanitized.replace(char, '')
            
        return sanitized.strip()
        
    def mask_sensitive_data(self, data: str, show_chars: int = 4) -> str:
        """Mask sensitive data showing only first/last characters."""
        if len(data) <= show_chars * 2:
            return '*' * len(data)
        return f"{data[:show_chars]}{'*' * (len(data) - show_chars * 2)}{data[-show_chars:]}"
        
    def constant_time_compare(self, a: str, b: str) -> bool:
        """Constant time string comparison to prevent timing attacks."""
        return hmac.compare_digest(a, b)
        
    # Security Event Logging
    
    def log_security_event(self, 
                         event_type: str, 
                         details: Dict[str, Any],
                         severity: str = "INFO") -> None:
        """Log security events."""
        event = {
            'timestamp': datetime.utcnow().isoformat(),
            'event_type': event_type,
            'severity': severity,
            'details': details
        }
        
        log_message = f"Security Event [{event_type}]: {json.dumps(event)}"
        
        if severity == "CRITICAL":
            self.logger.critical(log_message)
        elif severity == "ERROR":
            self.logger.error(log_message)
        elif severity == "WARNING":
            self.logger.warning(log_message)
        else:
            self.logger.info(log_message)
            
    # Key Derivation
    
    def derive_key(self, password: str, salt: bytes, iterations: int = 100000) -> bytes:
        """Derive key from password using PBKDF2."""
        try:
            kdf = PBKDF2HMAC(
                algorithm=hashes.SHA256(),
                length=32,
                salt=salt,
                iterations=iterations,
            )
            key = kdf.derive(password.encode('utf-8'))
            return key
        except Exception as e:
            self.logger.error(f"Key derivation failed: {e}")
            raise
            
    def get_security_status(self) -> Dict[str, Any]:
        """Get current security module status."""
        return {
            'encryption_enabled': True,
            'jwt_configured': self.jwt_secret_key is not None,
            'rate_limits_active': len(self.rate_limit_storage),
            'master_key_loaded': self.master_key is not None,
            'password_hasher_ready': self.password_hasher is not None
        }