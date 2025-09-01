"""
System Constants

시스템 전체에서 사용되는 상수들을 중앙 집중식으로 관리.
매직 넘버를 제거하고 설정 값들의 의미를 명확히 한다.
"""

from typing import Dict, List, Tuple
from enum import Enum


class TradingConstants:
    """거래 관련 상수"""
    
    # === 이동평균선 조건 상수 ===
    DEFAULT_MA_PERIODS = [20, 50, 200]  # 기본 이동평균 기간
    MA_MIN_STRENGTH_THRESHOLD = 0.001  # 0.1% 최소 편차
    MA_CONFIRMATION_CANDLES = 2  # 확인 캔들 수
    
    # === Price Channel 상수 ===
    DEFAULT_CHANNEL_PERIOD = 20  # 기본 채널 계산 기간
    CHANNEL_BREAKOUT_THRESHOLD = 0.001  # 0.1% 돌파 확인
    MIN_CHANNEL_WIDTH = 0.005  # 0.5% 최소 채널 폭
    CHANNEL_CONFIRMATION_CANDLES = 2  # 돌파 확인 캔들
    
    # === 호가 감지 상수 ===
    DEFAULT_RISING_TICKS = 3  # 기본 상승 틱 수
    DEFAULT_FALLING_TICKS = 2  # 기본 하락 틱 수
    ORDERBOOK_DEPTH = 20  # 오더북 분석 깊이
    TICK_SIZE_THRESHOLD = 0.0001  # 최소 틱 크기
    VOLUME_IMBALANCE_THRESHOLD = 2.0  # 물량 불균형 임계값
    LARGE_ORDER_THRESHOLD_USDT = 10000  # 대량 주문 감지 (USDT)
    
    # === 틱 패턴 상수 ===
    PATTERN_UP_TICKS = 5  # 패턴 상승 틱
    PATTERN_DOWN_TICKS = 2  # 패턴 하락 틱
    PATTERN_ADDITIONAL_ENTRY_PERCENT = 0.30  # 30% 추가 진입
    PATTERN_TIMEOUT_SECONDS = 10  # 패턴 유효 시간 (초)
    
    # === 캔들 상태 상수 ===
    MIN_CANDLE_BODY_PERCENT = 0.001  # 0.1% 최소 캔들 몸통
    CANDLE_CONFIRMATION_THRESHOLD = 0.001  # 0.1% 변화 확인


class PCSConstants:
    """PCS (Price Channel System) 청산 상수"""
    
    # === 단계별 청산 비율 ===
    STAGE1_PROFIT_THRESHOLD = 0.02  # 2% 수익 시 1단 청산
    STAGE1_EXIT_RATIO = 0.30  # 30% 청산
    
    STAGE2_CHANNEL_BREAK_THRESHOLD = 0.005  # 0.5% 채널 이탈
    STAGE2_EXIT_RATIO = 0.50  # 잔여의 50% 청산
    
    STAGE3_REVERSAL_CANDLES = 2  # 연속 반전 캔들
    STAGE3_EXIT_RATIO = 1.0  # 100% 완전 청산
    
    # === 공통 설정 ===
    MIN_HOLDING_TIME_MINUTES = 5  # 최소 보유 시간
    CHANNEL_CALCULATION_PERIOD = 20  # 채널 계산 기간
    
    # === 리스크 관리 ===
    BREAKEVEN_AFTER_STAGE1 = True  # 1단 후 무손실 설정
    TRAILING_STOP_AFTER_STAGE2 = True  # 2단 후 트레일링
    TRAILING_STOP_PERCENT = 0.02  # 2% 트레일링 스톱


class RiskConstants:
    """리스크 관리 상수"""
    
    # === 12단계 익절/손절 비율 ===
    PROFIT_LEVELS = [0.02, 0.04, 0.06, 0.08, 0.10, 0.12]  # 2%, 4%, 6%, 8%, 10%, 12%
    LOSS_LEVELS = [0.01, 0.02, 0.03, 0.04, 0.05, 0.06]    # 1%, 2%, 3%, 4%, 5%, 6%
    
    # === 포지션 관리 ===
    DEFAULT_POSITION_SIZE_USDT = 1000  # 기본 포지션 크기
    MAX_POSITIONS = 3  # 최대 포지션 수
    MAX_LEVERAGE_EXPOSURE_USDT = 10000  # 최대 레버리지 노출
    
    # === 일일 리스크 한계 ===
    MAX_DAILY_LOSS_PERCENT = 0.05  # -5.0% 일일 최대 손실
    MAX_DRAWDOWN_PERCENT = 0.15  # -15.0% 최대 드로우다운
    
    # === 거래 빈도 제한 ===
    MAX_TRADES_PER_HOUR = 10  # 시간당 최대 거래
    MAX_TRADES_PER_DAY = 50  # 일일 최대 거래
    
    # === 연속 손실 제한 ===
    MAX_CONSECUTIVE_LOSSES = 5  # 최대 연속 손실
    CONSECUTIVE_LOSS_COOLDOWN_HOURS = 2  # 연속 손실 후 대기 시간
    
    # === 포지션 계산 ===
    MAINTENANCE_MARGIN_RATE = 0.005  # 0.5% 유지증거금
    KELLY_FRACTION_MAX = 1.0  # Kelly Criterion 최대값
    KELLY_FRACTION_MIN = 0.1  # Kelly Criterion 최소값


class APIConstants:
    """API 관련 상수"""
    
    # === 타임아웃 설정 ===
    API_REQUEST_TIMEOUT_SECONDS = 10  # API 요청 타임아웃
    WEBSOCKET_RECONNECT_DELAY_SECONDS = 5  # WebSocket 재연결 지연
    MAX_RECONNECTION_ATTEMPTS = 5  # 최대 재연결 시도
    
    # === Rate Limiting ===
    BINANCE_RATE_LIMIT_PER_MINUTE = 1200  # 바이낸스 분당 요청 한도
    BYBIT_RATE_LIMIT_PER_5_SECONDS = 120  # 바이비트 5초당 요청 한도
    
    # === 데이터 관리 ===
    MAX_PRICE_HISTORY_SIZE = 1000  # 최대 가격 히스토리
    ORDERBOOK_UPDATE_INTERVAL_MS = 100  # 오더북 업데이트 간격
    CACHE_EXPIRY_SECONDS = 300  # 캐시 만료 시간 (5분)
    
    # === 성능 목표 ===
    SIGNAL_GENERATION_TARGET_MS = 10  # 신호 생성 목표 시간
    API_RESPONSE_TARGET_MS = 100  # API 응답 목표 시간
    UI_RESPONSE_TARGET_MS = 50  # UI 응답 목표 시간


class SystemConstants:
    """시스템 레벨 상수"""
    
    # === 시스템 모니터링 ===
    STATUS_UPDATE_INTERVAL_SECONDS = 5  # 상태 업데이트 간격
    PERFORMANCE_MONITOR_INTERVAL_SECONDS = 30  # 성능 모니터링 간격
    HEALTH_CHECK_INTERVAL_SECONDS = 30  # 헬스 체크 간격
    
    # === 리소스 한계 ===
    MAX_MEMORY_USAGE_MB_EXE = 200  # EXE 버전 최대 메모리
    MAX_MEMORY_USAGE_MB_WEB = 500  # 웹 버전 최대 메모리
    MAX_CPU_USAGE_PERCENT = 5  # 최대 CPU 사용률
    
    # === 로깅 설정 ===
    LOG_ROTATION_SIZE_MB = 10  # 로그 파일 회전 크기
    LOG_RETENTION_DAYS = 30  # 로그 보관 일수
    
    # === 보안 설정 ===
    PASSWORD_MIN_LENGTH = 8  # 최소 비밀번호 길이
    MAX_LOGIN_ATTEMPTS = 3  # 최대 로그인 시도
    ACCOUNT_LOCKOUT_MINUTES = 5  # 계정 잠금 시간
    AUTO_LOGOUT_MINUTES = 30  # 자동 로그아웃 시간
    
    # === 캐시 관리 ===
    MAX_CACHE_ENTRIES = 1000  # 최대 캐시 항목
    CACHE_CLEANUP_INTERVAL_SECONDS = 60  # 캐시 정리 간격


class UIConstants:
    """UI/UX 관련 상수"""
    
    # === GUI 설정 ===
    MAIN_WINDOW_MIN_WIDTH = 1200
    MAIN_WINDOW_MIN_HEIGHT = 800
    CHART_UPDATE_INTERVAL_MS = 100  # 차트 업데이트 간격
    
    # === 웹 대시보드 ===
    WEBSOCKET_HEARTBEAT_SECONDS = 20  # WebSocket 하트비트
    SESSION_TIMEOUT_MINUTES = 30  # 세션 타임아웃
    
    # === 반응형 브레이크포인트 ===
    MOBILE_BREAKPOINT_PX = 768
    TABLET_BREAKPOINT_PX = 1024
    DESKTOP_BREAKPOINT_PX = 1200


class MessageConstants:
    """사용자 메시지 및 알림 상수"""
    
    # === 성공 메시지 ===
    TRADING_STARTED = "자동매매가 성공적으로 시작되었습니다"
    TRADING_STOPPED = "자동매매가 안전하게 중지되었습니다"
    POSITION_OPENED = "포지션이 성공적으로 개설되었습니다"
    POSITION_CLOSED = "포지션이 성공적으로 청산되었습니다"
    
    # === 경고 메시지 ===
    RISK_LIMIT_WARNING = "리스크 한계에 근접했습니다"
    API_CONNECTION_LOST = "거래소 연결이 끊어졌습니다"
    LOW_BALANCE_WARNING = "계좌 잔고가 부족합니다"
    
    # === 오류 메시지 ===
    INVALID_API_KEYS = "API 키가 유효하지 않습니다"
    INSUFFICIENT_BALANCE = "잔고가 부족하여 주문을 실행할 수 없습니다"
    NETWORK_ERROR = "네트워크 연결 오류가 발생했습니다"
    SYSTEM_ERROR = "시스템 오류가 발생했습니다"
    
    # === 긴급 상황 ===
    EMERGENCY_STOP_EXECUTED = "긴급 정지가 실행되었습니다"
    CRITICAL_RISK_DETECTED = "심각한 리스크가 감지되었습니다"


class ValidationConstants:
    """검증 관련 상수"""
    
    # === 가격 검증 ===
    MIN_PRICE = 0.00000001  # 최소 가격
    MAX_PRICE = 1000000.0  # 최대 가격
    
    # === 수량 검증 ===  
    MIN_QUANTITY = 0.00000001  # 최소 수량
    MAX_QUANTITY = 1000000.0  # 최대 수량
    
    # === 비율 검증 ===
    MIN_PERCENTAGE = 0.0001  # 0.01% 최소 비율
    MAX_PERCENTAGE = 1.0  # 100% 최대 비율
    
    # === 시간 검증 ===
    MIN_TIMEFRAME_MINUTES = 1  # 최소 시간 프레임
    MAX_TIMEFRAME_DAYS = 365  # 최대 시간 프레임


# === 거래소별 상수 ===
class BinanceConstants:
    """바이낸스 거래소 상수"""
    
    # === API 엔드포인트 ===
    FUTURES_BASE_URL = "https://fapi.binance.com"
    SPOT_BASE_URL = "https://api.binance.com"
    WEBSOCKET_BASE_URL = "wss://fstream.binance.com/ws"
    
    # === 레버리지 한계 ===
    MIN_LEVERAGE = 1
    MAX_LEVERAGE = 125
    
    # === 주문 한계 ===
    MIN_NOTIONAL_USDT = 5.0  # 최소 주문 금액
    MAX_POSITION_SIZE = 1000000.0  # 최대 포지션 크기


class BybitConstants:
    """바이비트 거래소 상수"""
    
    # === API 엔드포인트 ===
    BASE_URL = "https://api.bybit.com"
    TESTNET_URL = "https://api-testnet.bybit.com"
    WEBSOCKET_URL = "wss://stream.bybit.com/v5/public/linear"
    
    # === 레버리지 한계 ===
    MIN_LEVERAGE = 1
    MAX_LEVERAGE = 100
    
    # === 주문 한계 ===
    MIN_ORDER_QTY = 0.001  # 최소 주문 수량
    MAX_ACTIVE_ORDERS = 500  # 최대 활성 주문


# === 파일 및 경로 상수 ===
class PathConstants:
    """파일 및 경로 상수"""
    
    # === 설정 파일 ===
    CONFIG_DIR = "config"
    DESKTOP_CONFIG_FILE = "desktop_config.json"
    WEB_CONFIG_FILE = "web_config.json"
    PRODUCTION_CONFIG_FILE = "production_config.json"
    
    # === 로그 파일 ===
    LOG_DIR = "logs"
    MAIN_LOG_FILE = "trading_system.log"
    ERROR_LOG_FILE = "errors.log"
    PERFORMANCE_LOG_FILE = "performance.log"
    
    # === 데이터 디렉토리 ===
    DATA_DIR = "data"
    BACKTEST_DATA_DIR = "data/backtest"
    MARKET_DATA_DIR = "data/market"
    
    # === 임시 파일 ===
    TEMP_DIR = "temp"
    CACHE_DIR = "cache"


# === 성능 목표 상수 ===
class PerformanceTargets:
    """성능 목표 상수"""
    
    # === 응답 시간 목표 (밀리초) ===
    SIGNAL_GENERATION_TARGET_MS = 10
    API_RESPONSE_TARGET_MS = 100
    UI_RESPONSE_TARGET_MS = 50
    WEBSOCKET_LATENCY_TARGET_MS = 10
    
    # === 처리량 목표 ===
    SIGNALS_PER_SECOND = 100
    API_REQUESTS_PER_SECOND = 20
    
    # === 메모리 목표 (MB) ===
    EXE_MEMORY_TARGET_MB = 200
    WEB_SERVER_MEMORY_TARGET_MB = 500
    CORE_ENGINE_MEMORY_TARGET_MB = 100
    
    # === 네트워크 목표 ===
    MAX_BANDWIDTH_USAGE_MBPS = 1.0


# === 비즈니스 규칙 상수 ===
class BusinessRules:
    """비즈니스 규칙 상수"""
    
    # === 거래 시간 ===
    CRYPTO_MARKET_24_7 = True  # 암호화폐는 24시간 거래
    DEFAULT_TIMEZONE = "UTC"
    
    # === 수수료 계산 ===
    BINANCE_MAKER_FEE = 0.0002  # 0.02%
    BINANCE_TAKER_FEE = 0.0004  # 0.04%
    BYBIT_MAKER_FEE = 0.0001  # 0.01%
    BYBIT_TAKER_FEE = 0.0006  # 0.06%
    
    # === 슬리피지 보호 ===
    MAX_SLIPPAGE_PERCENT = 0.01  # 1% 최대 슬리피지
    MARKET_ORDER_SLIPPAGE_BUFFER = 0.005  # 0.5% 시장가 버퍼
    
    # === 포지션 관리 규칙 ===
    MIN_POSITION_HOLD_SECONDS = 60  # 최소 1분 보유
    MAX_POSITION_HOLD_HOURS = 24  # 최대 24시간 보유
    
    # === 자동 정리 규칙 ===
    AUTO_CLEANUP_AFTER_HOURS = 72  # 72시간 후 자동 정리
    MAX_COMPLETED_POSITIONS_HISTORY = 1000  # 최대 완료 포지션 기록


# === 알림 및 메시지 레벨 ===
class AlertLevels:
    """알림 레벨 상수"""
    
    DEBUG = "debug"
    INFO = "info" 
    WARNING = "warning"
    ERROR = "error"
    CRITICAL = "critical"
    
    # === 알림 우선순위 ===
    PRIORITY_LOW = 1
    PRIORITY_NORMAL = 2
    PRIORITY_HIGH = 3
    PRIORITY_CRITICAL = 4


# === 정규표현식 패턴 ===
class RegexPatterns:
    """정규표현식 패턴 상수"""
    
    # === 거래 심볼 검증 ===
    CRYPTO_SYMBOL_PATTERN = r'^[A-Z]{3,10}USDT?$'
    
    # === API 키 형식 ===
    BINANCE_API_KEY_PATTERN = r'^[A-Za-z0-9]{64}$'
    BYBIT_API_KEY_PATTERN = r'^[A-Za-z0-9]{20,}$'
    
    # === 이메일 검증 ===
    EMAIL_PATTERN = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'


# === 환경별 설정 매핑 ===
ENVIRONMENT_CONFIGS = {
    'development': {
        'log_level': 'DEBUG',
        'api_timeout': APIConstants.API_REQUEST_TIMEOUT_SECONDS,
        'max_positions': 1,  # 개발 환경에서는 포지션 제한
        'testnet': True
    },
    'testing': {
        'log_level': 'INFO',
        'api_timeout': APIConstants.API_REQUEST_TIMEOUT_SECONDS * 2,
        'max_positions': RiskConstants.MAX_POSITIONS,
        'testnet': True
    },
    'production': {
        'log_level': 'WARNING',
        'api_timeout': APIConstants.API_REQUEST_TIMEOUT_SECONDS,
        'max_positions': RiskConstants.MAX_POSITIONS,
        'testnet': False
    }
}


# === 지원되는 거래 심볼 목록 ===
SUPPORTED_SYMBOLS = [
    'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT', 'DOTUSDT',
    'LINKUSDT', 'LTCUSDT', 'BCHUSDT', 'XLMUSDT', 'EOSUSDT',
    'TRXUSDT', 'ETCUSDT', 'XRPUSDT', 'SOLUSDT', 'AVAXUSDT'
]


# === 시간대별 설정 ===
TIMEFRAME_CONFIGS = {
    '1m': {'period': 20, 'threshold': 0.001, 'candles': 2},
    '5m': {'period': 20, 'threshold': 0.002, 'candles': 2},
    '15m': {'period': 14, 'threshold': 0.003, 'candles': 1},
    '1h': {'period': 10, 'threshold': 0.005, 'candles': 1},
    '4h': {'period': 7, 'threshold': 0.008, 'candles': 1},
    '1d': {'period': 5, 'threshold': 0.01, 'candles': 1}
}


# === 모듈 익스포트 ===
__all__ = [
    'TradingConstants',
    'PCSConstants', 
    'RiskConstants',
    'APIConstants',
    'SystemConstants',
    'UIConstants',
    'MessageConstants',
    'ValidationConstants',
    'BinanceConstants',
    'BybitConstants',
    'PathConstants',
    'PerformanceTargets',
    'BusinessRules',
    'AlertLevels',
    'RegexPatterns',
    'ENVIRONMENT_CONFIGS',
    'SUPPORTED_SYMBOLS',
    'TIMEFRAME_CONFIGS'
]