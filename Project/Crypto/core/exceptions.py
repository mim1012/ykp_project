"""
Custom Exception Classes

암호화폐 자동매매 시스템의 모든 도메인별 예외 클래스들.
표준화된 예외 처리 패턴과 명확한 에러 메시지를 제공.
"""

from typing import Dict, List, Optional, Any


# === 기본 예외 클래스 ===
class TradingSystemError(Exception):
    """거래 시스템 기본 예외"""
    
    def __init__(self, message: str, error_code: Optional[str] = None, details: Optional[Dict] = None):
        self.message = message
        self.error_code = error_code
        self.details = details or {}
        super().__init__(self.message)
    
    def to_dict(self) -> Dict[str, Any]:
        """딕셔너리 변환 (API 응답용)"""
        return {
            'error': self.__class__.__name__,
            'message': self.message,
            'error_code': self.error_code,
            'details': self.details
        }


# === API 관련 예외 ===
class APIError(TradingSystemError):
    """API 관련 기본 예외"""
    pass


class APIConnectionError(APIError):
    """API 연결 실패"""
    
    def __init__(self, exchange: str, message: str = None):
        self.exchange = exchange
        message = message or f"{exchange} 거래소 연결 실패"
        super().__init__(message, "API_CONNECTION_FAILED", {'exchange': exchange})


class APIAuthenticationError(APIError):
    """API 인증 실패"""
    
    def __init__(self, exchange: str, reason: str = None):
        self.exchange = exchange
        self.reason = reason
        message = f"{exchange} API 인증 실패"
        if reason:
            message += f": {reason}"
        super().__init__(message, "API_AUTH_FAILED", {'exchange': exchange, 'reason': reason})


class APIRateLimitError(APIError):
    """API 요청 한도 초과"""
    
    def __init__(self, exchange: str, retry_after_seconds: Optional[int] = None):
        self.exchange = exchange
        self.retry_after = retry_after_seconds
        message = f"{exchange} API 요청 한도 초과"
        if retry_after_seconds:
            message += f" (재시도 가능: {retry_after_seconds}초 후)"
        super().__init__(message, "API_RATE_LIMIT", {
            'exchange': exchange, 
            'retry_after_seconds': retry_after_seconds
        })


class APITimeoutError(APIError):
    """API 응답 시간 초과"""
    
    def __init__(self, exchange: str, timeout_seconds: int):
        self.exchange = exchange
        self.timeout_seconds = timeout_seconds
        message = f"{exchange} API 응답 시간 초과 ({timeout_seconds}초)"
        super().__init__(message, "API_TIMEOUT", {
            'exchange': exchange,
            'timeout_seconds': timeout_seconds
        })


class InvalidAPIResponseError(APIError):
    """유효하지 않은 API 응답"""
    
    def __init__(self, exchange: str, response_data: Any = None):
        self.exchange = exchange
        self.response_data = response_data
        message = f"{exchange}에서 유효하지 않은 응답 수신"
        super().__init__(message, "INVALID_API_RESPONSE", {
            'exchange': exchange,
            'response': str(response_data)
        })


# === 거래 관련 예외 ===
class TradingError(TradingSystemError):
    """거래 관련 기본 예외"""
    pass


class InsufficientBalanceError(TradingError):
    """잔고 부족"""
    
    def __init__(self, required_amount: float, available_amount: float, currency: str = "USDT"):
        self.required_amount = required_amount
        self.available_amount = available_amount
        self.currency = currency
        
        message = f"잔고 부족: 필요 {required_amount:.2f} {currency}, 사용가능 {available_amount:.2f} {currency}"
        super().__init__(message, "INSUFFICIENT_BALANCE", {
            'required_amount': required_amount,
            'available_amount': available_amount,
            'currency': currency
        })


class InvalidOrderParametersError(TradingError):
    """잘못된 주문 파라미터"""
    
    def __init__(self, parameter: str, value: Any, reason: str = None):
        self.parameter = parameter
        self.value = value
        self.reason = reason
        
        message = f"잘못된 주문 파라미터 '{parameter}': {value}"
        if reason:
            message += f" ({reason})"
        
        super().__init__(message, "INVALID_ORDER_PARAMS", {
            'parameter': parameter,
            'value': str(value),
            'reason': reason
        })


class PositionNotFoundError(TradingError):
    """포지션을 찾을 수 없음"""
    
    def __init__(self, position_id: str, symbol: str = None):
        self.position_id = position_id
        self.symbol = symbol
        
        message = f"포지션을 찾을 수 없음: {position_id}"
        if symbol:
            message += f" (심볼: {symbol})"
        
        super().__init__(message, "POSITION_NOT_FOUND", {
            'position_id': position_id,
            'symbol': symbol
        })


class OrderExecutionError(TradingError):
    """주문 실행 실패"""
    
    def __init__(self, symbol: str, side: str, quantity: float, reason: str = None):
        self.symbol = symbol
        self.side = side
        self.quantity = quantity
        self.reason = reason
        
        message = f"주문 실행 실패: {symbol} {side} {quantity}"
        if reason:
            message += f" - {reason}"
        
        super().__init__(message, "ORDER_EXECUTION_FAILED", {
            'symbol': symbol,
            'side': side,
            'quantity': quantity,
            'reason': reason
        })


# === 리스크 관리 예외 ===
class RiskManagementError(TradingSystemError):
    """리스크 관리 기본 예외"""
    pass


class RiskLimitExceededError(RiskManagementError):
    """리스크 한계 초과"""
    
    def __init__(self, risk_type: str, current_value: float, limit_value: float):
        self.risk_type = risk_type
        self.current_value = current_value
        self.limit_value = limit_value
        
        message = f"{risk_type} 리스크 한계 초과: {current_value:.2f} > {limit_value:.2f}"
        super().__init__(message, "RISK_LIMIT_EXCEEDED", {
            'risk_type': risk_type,
            'current_value': current_value,
            'limit_value': limit_value
        })


class ExcessiveDrawdownError(RiskManagementError):
    """과도한 드로우다운"""
    
    def __init__(self, current_drawdown: float, max_allowed: float):
        self.current_drawdown = current_drawdown
        self.max_allowed = max_allowed
        
        message = f"과도한 드로우다운: {current_drawdown:.2f}% > {max_allowed:.2f}%"
        super().__init__(message, "EXCESSIVE_DRAWDOWN", {
            'current_drawdown': current_drawdown,
            'max_allowed_drawdown': max_allowed
        })


class ConsecutiveLossLimitError(RiskManagementError):
    """연속 손실 한계 초과"""
    
    def __init__(self, consecutive_losses: int, max_allowed: int):
        self.consecutive_losses = consecutive_losses
        self.max_allowed = max_allowed
        
        message = f"연속 손실 한계 초과: {consecutive_losses}회 > {max_allowed}회"
        super().__init__(message, "CONSECUTIVE_LOSS_LIMIT", {
            'consecutive_losses': consecutive_losses,
            'max_allowed': max_allowed
        })


# === 설정 관련 예외 ===
class ConfigurationError(TradingSystemError):
    """설정 관련 기본 예외"""
    pass


class InvalidConfigurationError(ConfigurationError):
    """유효하지 않은 설정"""
    
    def __init__(self, config_section: str, field: str, value: Any, reason: str = None):
        self.config_section = config_section
        self.field = field
        self.value = value
        self.reason = reason
        
        message = f"유효하지 않은 설정 [{config_section}].{field} = {value}"
        if reason:
            message += f": {reason}"
        
        super().__init__(message, "INVALID_CONFIGURATION", {
            'section': config_section,
            'field': field,
            'value': str(value),
            'reason': reason
        })


class ConfigurationLoadError(ConfigurationError):
    """설정 파일 로드 실패"""
    
    def __init__(self, file_path: str, reason: str = None):
        self.file_path = file_path
        self.reason = reason
        
        message = f"설정 파일 로드 실패: {file_path}"
        if reason:
            message += f" - {reason}"
        
        super().__init__(message, "CONFIG_LOAD_FAILED", {
            'file_path': file_path,
            'reason': reason
        })


# === 보안 관련 예외 ===
class SecurityError(TradingSystemError):
    """보안 관련 기본 예외"""
    pass


class AuthenticationFailedError(SecurityError):
    """인증 실패"""
    
    def __init__(self, username: str, reason: str = None):
        self.username = username
        self.reason = reason
        
        message = f"사용자 '{username}' 인증 실패"
        if reason:
            message += f": {reason}"
        
        super().__init__(message, "AUTH_FAILED", {
            'username': username,
            'reason': reason
        })


class EncryptionError(SecurityError):
    """암호화/복호화 실패"""
    
    def __init__(self, operation: str, data_type: str = None):
        self.operation = operation
        self.data_type = data_type
        
        message = f"{operation} 실패"
        if data_type:
            message += f" ({data_type} 데이터)"
        
        super().__init__(message, "ENCRYPTION_FAILED", {
            'operation': operation,
            'data_type': data_type
        })


class InvalidTokenError(SecurityError):
    """유효하지 않은 토큰"""
    
    def __init__(self, token_type: str = "JWT"):
        self.token_type = token_type
        message = f"유효하지 않은 {token_type} 토큰"
        super().__init__(message, "INVALID_TOKEN", {'token_type': token_type})


# === 데이터 관련 예외 ===
class DataError(TradingSystemError):
    """데이터 관련 기본 예외"""
    pass


class InsufficientDataError(DataError):
    """데이터 부족"""
    
    def __init__(self, data_type: str, required_count: int, actual_count: int):
        self.data_type = data_type
        self.required_count = required_count
        self.actual_count = actual_count
        
        message = f"{data_type} 데이터 부족: 필요 {required_count}개, 실제 {actual_count}개"
        super().__init__(message, "INSUFFICIENT_DATA", {
            'data_type': data_type,
            'required_count': required_count,
            'actual_count': actual_count
        })


class InvalidMarketDataError(DataError):
    """유효하지 않은 시장 데이터"""
    
    def __init__(self, symbol: str, field: str, value: Any = None):
        self.symbol = symbol
        self.field = field
        self.value = value
        
        message = f"유효하지 않은 시장 데이터 {symbol}.{field}"
        if value is not None:
            message += f": {value}"
        
        super().__init__(message, "INVALID_MARKET_DATA", {
            'symbol': symbol,
            'field': field,
            'value': str(value) if value is not None else None
        })


# === 전략 관련 예외 ===
class StrategyError(TradingSystemError):
    """거래 전략 기본 예외"""
    pass


class InvalidSignalError(StrategyError):
    """유효하지 않은 거래 신호"""
    
    def __init__(self, signal_type: str, reason: str, signal_data: Optional[Dict] = None):
        self.signal_type = signal_type
        self.reason = reason
        self.signal_data = signal_data or {}
        
        message = f"유효하지 않은 {signal_type} 신호: {reason}"
        super().__init__(message, "INVALID_SIGNAL", {
            'signal_type': signal_type,
            'reason': reason,
            'signal_data': signal_data
        })


class StrategyConfigurationError(StrategyError):
    """전략 설정 오류"""
    
    def __init__(self, strategy_name: str, config_error: str):
        self.strategy_name = strategy_name
        self.config_error = config_error
        
        message = f"전략 '{strategy_name}' 설정 오류: {config_error}"
        super().__init__(message, "STRATEGY_CONFIG_ERROR", {
            'strategy_name': strategy_name,
            'config_error': config_error
        })


# === PCS 청산 관련 예외 ===
class PCSError(TradingSystemError):
    """PCS 청산 시스템 예외"""
    pass


class InvalidPCSStageError(PCSError):
    """유효하지 않은 PCS 단계"""
    
    def __init__(self, current_stage: str, attempted_stage: str):
        self.current_stage = current_stage
        self.attempted_stage = attempted_stage
        
        message = f"PCS 단계 오류: 현재 {current_stage}단계에서 {attempted_stage}단계로 이동 불가"
        super().__init__(message, "INVALID_PCS_STAGE", {
            'current_stage': current_stage,
            'attempted_stage': attempted_stage
        })


class PCSExecutionError(PCSError):
    """PCS 청산 실행 실패"""
    
    def __init__(self, stage: str, symbol: str, reason: str):
        self.stage = stage
        self.symbol = symbol
        self.reason = reason
        
        message = f"PCS {stage}단계 청산 실행 실패 ({symbol}): {reason}"
        super().__init__(message, "PCS_EXECUTION_FAILED", {
            'stage': stage,
            'symbol': symbol,
            'reason': reason
        })


# === 시스템 관련 예외 ===
class SystemError(TradingSystemError):
    """시스템 레벨 예외"""
    pass


class SystemInitializationError(SystemError):
    """시스템 초기화 실패"""
    
    def __init__(self, component: str, reason: str):
        self.component = component
        self.reason = reason
        
        message = f"시스템 구성요소 '{component}' 초기화 실패: {reason}"
        super().__init__(message, "SYSTEM_INIT_FAILED", {
            'component': component,
            'reason': reason
        })


class EmergencyStopError(SystemError):
    """긴급 정지 관련 오류"""
    
    def __init__(self, reason: str, positions_affected: int = 0):
        self.reason = reason
        self.positions_affected = positions_affected
        
        message = f"긴급 정지 실행: {reason}"
        if positions_affected > 0:
            message += f" (영향받은 포지션: {positions_affected}개)"
        
        super().__init__(message, "EMERGENCY_STOP", {
            'reason': reason,
            'positions_affected': positions_affected
        })


# === 네트워크 관련 예외 ===
class NetworkError(TradingSystemError):
    """네트워크 관련 예외"""
    pass


class WebSocketConnectionError(NetworkError):
    """WebSocket 연결 실패"""
    
    def __init__(self, url: str, reason: str = None):
        self.url = url
        self.reason = reason
        
        message = f"WebSocket 연결 실패: {url}"
        if reason:
            message += f" - {reason}"
        
        super().__init__(message, "WEBSOCKET_CONNECTION_FAILED", {
            'url': url,
            'reason': reason
        })


class NetworkTimeoutError(NetworkError):
    """네트워크 타임아웃"""
    
    def __init__(self, operation: str, timeout_seconds: int):
        self.operation = operation
        self.timeout_seconds = timeout_seconds
        
        message = f"네트워크 타임아웃: {operation} ({timeout_seconds}초 초과)"
        super().__init__(message, "NETWORK_TIMEOUT", {
            'operation': operation,
            'timeout_seconds': timeout_seconds
        })


# === 검증 관련 예외 ===
class ValidationError(TradingSystemError):
    """검증 관련 예외"""
    pass


class ParameterValidationError(ValidationError):
    """파라미터 검증 실패"""
    
    def __init__(self, parameter_name: str, value: Any, expected_type: str = None, valid_range: str = None):
        self.parameter_name = parameter_name
        self.value = value
        self.expected_type = expected_type
        self.valid_range = valid_range
        
        message = f"파라미터 '{parameter_name}' 검증 실패: {value}"
        if expected_type:
            message += f" (예상 타입: {expected_type})"
        if valid_range:
            message += f" (유효 범위: {valid_range})"
        
        super().__init__(message, "PARAMETER_VALIDATION_FAILED", {
            'parameter_name': parameter_name,
            'value': str(value),
            'expected_type': expected_type,
            'valid_range': valid_range
        })


class SymbolValidationError(ValidationError):
    """심볼 검증 실패"""
    
    def __init__(self, symbol: str, reason: str = None):
        self.symbol = symbol
        self.reason = reason
        
        message = f"심볼 '{symbol}' 검증 실패"
        if reason:
            message += f": {reason}"
        
        super().__init__(message, "SYMBOL_VALIDATION_FAILED", {
            'symbol': symbol,
            'reason': reason
        })


# === 백테스팅 관련 예외 ===
class BacktestError(TradingSystemError):
    """백테스팅 관련 예외"""
    pass


class InsufficientBacktestDataError(BacktestError):
    """백테스트 데이터 부족"""
    
    def __init__(self, symbol: str, required_days: int, available_days: int):
        self.symbol = symbol
        self.required_days = required_days
        self.available_days = available_days
        
        message = f"백테스트 데이터 부족 ({symbol}): 필요 {required_days}일, 사용가능 {available_days}일"
        super().__init__(message, "INSUFFICIENT_BACKTEST_DATA", {
            'symbol': symbol,
            'required_days': required_days,
            'available_days': available_days
        })


# === 예외 처리 유틸리티 ===
class ExceptionHandler:
    """예외 처리 유틸리티"""
    
    @staticmethod
    def handle_api_exception(e: Exception, exchange: str) -> APIError:
        """API 예외를 적절한 커스텀 예외로 변환"""
        error_message = str(e).lower()
        
        if 'timeout' in error_message:
            return APITimeoutError(exchange, 10)
        elif 'rate limit' in error_message or 'too many' in error_message:
            return APIRateLimitError(exchange)
        elif 'unauthorized' in error_message or 'invalid' in error_message:
            return APIAuthenticationError(exchange)
        elif 'connection' in error_message:
            return APIConnectionError(exchange)
        else:
            return APIError(f"{exchange} API 오류: {str(e)}")
    
    @staticmethod
    def handle_trading_exception(e: Exception, context: Dict = None) -> TradingError:
        """거래 예외를 적절한 커스텀 예외로 변환"""
        error_message = str(e).lower()
        context = context or {}
        
        if 'insufficient' in error_message or 'balance' in error_message:
            return InsufficientBalanceError(
                context.get('required_amount', 0),
                context.get('available_amount', 0)
            )
        elif 'invalid' in error_message and 'parameter' in error_message:
            return InvalidOrderParametersError(
                context.get('parameter', 'unknown'),
                context.get('value', 'unknown')
            )
        else:
            return TradingError(f"거래 오류: {str(e)}")


# === 예외 로깅 헬퍼 ===
def log_exception(logger, exception: TradingSystemError, context: Optional[Dict] = None):
    """표준화된 예외 로깅"""
    
    log_data = {
        'exception_type': exception.__class__.__name__,
        'message': exception.message,
        'error_code': exception.error_code,
        'details': exception.details
    }
    
    if context:
        log_data['context'] = context
    
    # 심각도에 따른 로그 레벨 결정
    if isinstance(exception, (EmergencyStopError, ExcessiveDrawdownError)):
        logger.critical("심각한 오류 발생", extra=log_data)
    elif isinstance(exception, (RiskLimitExceededError, SecurityError)):
        logger.error("중요 오류 발생", extra=log_data)
    elif isinstance(exception, (APIError, NetworkError)):
        logger.warning("시스템 오류 발생", extra=log_data)
    else:
        logger.info("일반 오류 발생", extra=log_data)


# === 모듈 익스포트 ===
__all__ = [
    # 기본 예외
    'TradingSystemError',
    
    # API 예외
    'APIError',
    'APIConnectionError',
    'APIAuthenticationError', 
    'APIRateLimitError',
    'APITimeoutError',
    'InvalidAPIResponseError',
    
    # 거래 예외
    'TradingError',
    'InsufficientBalanceError',
    'InvalidOrderParametersError',
    'PositionNotFoundError',
    'OrderExecutionError',
    
    # 리스크 관리 예외
    'RiskManagementError',
    'RiskLimitExceededError',
    'ExcessiveDrawdownError',
    'ConsecutiveLossLimitError',
    
    # 설정 예외
    'ConfigurationError',
    'InvalidConfigurationError',
    'ConfigurationLoadError',
    
    # 보안 예외
    'SecurityError',
    'AuthenticationFailedError',
    'EncryptionError',
    'InvalidTokenError',
    
    # 데이터 예외
    'DataError',
    'InsufficientDataError', 
    'InvalidMarketDataError',
    
    # 전략 예외
    'StrategyError',
    'InvalidSignalError',
    'StrategyConfigurationError',
    
    # PCS 예외
    'PCSError',
    'InvalidPCSStageError',
    'PCSExecutionError',
    
    # 시스템 예외
    'SystemError',
    'SystemInitializationError',
    'EmergencyStopError',
    
    # 네트워크 예외
    'NetworkError',
    'WebSocketConnectionError',
    'NetworkTimeoutError',
    
    # 검증 예외
    'ValidationError',
    'ParameterValidationError',
    'SymbolValidationError',
    
    # 백테스팅 예외
    'BacktestError',
    'InsufficientBacktestDataError',
    
    # 유틸리티
    'ExceptionHandler',
    'log_exception'
]