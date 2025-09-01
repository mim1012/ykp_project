"""
System Manager

중앙 시스템 상태 관리자. 듀얼 버전 시스템의 핵심 조정자로서
EXE와 웹 대시보드 버전 간 완벽한 동기화와 상태 관리를 담당.
"""

from typing import Dict, List, Optional, Any, Callable
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from enum import Enum
import asyncio
import threading
import json
import signal
import os
from pathlib import Path

from .logger import SystemLogger
from .config_manager import ConfigManager
from .trading_engine import TradingEngine
from .risk_manager import RiskManager
from .api_connector import APIConnector
from .event_manager import EventManager

logger = SystemLogger.get_logger(__name__)


class SystemMode(Enum):
    """시스템 실행 모드"""
    GUI = "gui"          # PyQt5 데스크톱 모드
    WEB = "web"          # Flask 웹 대시보드 모드
    CLI = "cli"          # 명령줄 인터페이스 모드
    SERVICE = "service"  # 백그라운드 서비스 모드


class SystemState(Enum):
    """시스템 상태"""
    STARTING = "시작중"
    RUNNING = "실행중"
    PAUSED = "일시정지"
    STOPPING = "종료중"
    STOPPED = "정지됨"
    ERROR = "오류"


@dataclass
class SystemStatus:
    """시스템 상태 정보"""
    state: SystemState
    mode: SystemMode
    start_time: datetime
    last_update: datetime
    
    # 연결 상태
    api_connected: bool = False
    websocket_connected: bool = False
    database_connected: bool = False
    
    # 거래 상태
    trading_active: bool = False
    auto_trading_enabled: bool = False
    positions_count: int = 0
    daily_pnl: float = 0.0
    
    # 시스템 리소스
    memory_usage_mb: float = 0.0
    cpu_usage_percent: float = 0.0
    
    # 성능 지표
    api_response_time_ms: float = 0.0
    signal_generation_time_ms: float = 0.0
    
    def to_dict(self) -> Dict[str, Any]:
        """딕셔너리 변환 (API/GUI 전송용)"""
        return {
            'state': self.state.value,
            'mode': self.mode.value,
            'start_time': self.start_time.isoformat(),
            'last_update': self.last_update.isoformat(),
            'uptime_seconds': (self.last_update - self.start_time).total_seconds(),
            'api_connected': self.api_connected,
            'websocket_connected': self.websocket_connected,
            'database_connected': self.database_connected,
            'trading_active': self.trading_active,
            'auto_trading_enabled': self.auto_trading_enabled,
            'positions_count': self.positions_count,
            'daily_pnl': self.daily_pnl,
            'memory_usage_mb': self.memory_usage_mb,
            'cpu_usage_percent': self.cpu_usage_percent,
            'api_response_time_ms': self.api_response_time_ms,
            'signal_generation_time_ms': self.signal_generation_time_ms
        }


class SystemManager:
    """
    중앙 시스템 관리자
    
    듀얼 버전 시스템의 핵심 조정자로서 다음 기능을 담당:
    - 시스템 생명주기 관리 (시작, 일시정지, 재시작, 종료)
    - 구성 요소 간 조정 및 동기화
    - 상태 모니터링 및 브로드캐스팅  
    - 리소스 관리 및 최적화
    - 오류 처리 및 복구
    """
    
    _instance = None
    _lock = threading.Lock()
    
    def __new__(cls, *args, **kwargs):
        """싱글톤 패턴 구현"""
        with cls._lock:
            if cls._instance is None:
                cls._instance = super().__new__(cls)
            return cls._instance
    
    def __init__(self, mode: SystemMode, config_path: Optional[str] = None):
        # 이미 초기화된 경우 스킵
        if hasattr(self, '_initialized'):
            return
            
        self.mode = mode
        self.status = SystemStatus(
            state=SystemState.STARTING,
            mode=mode,
            start_time=datetime.now(),
            last_update=datetime.now()
        )
        
        # 핵심 구성 요소들
        self.config_manager = ConfigManager(config_path or self._get_default_config_path())
        self.event_manager = EventManager()
        
        # 거래 시스템 구성 요소들 (지연 초기화)
        self.trading_engine: Optional[TradingEngine] = None
        self.risk_manager: Optional[RiskManager] = None
        self.api_connector: Optional[APIConnector] = None
        
        # 비동기 작업 관리
        self.background_tasks: List[asyncio.Task] = []
        self.shutdown_event = asyncio.Event()
        self.status_update_task: Optional[asyncio.Task] = None
        
        # GUI/웹 인터페이스 참조 (선택적)
        self.gui_interface = None
        self.web_interface = None
        
        self._initialized = True
        logger.info(f"SystemManager 초기화: {mode.value} 모드")
    
    @classmethod
    def get_instance(cls) -> 'SystemManager':
        """싱글톤 인스턴스 반환"""
        if cls._instance is None:
            raise RuntimeError("SystemManager가 초기화되지 않았습니다")
        return cls._instance
    
    async def initialize_system(self) -> bool:
        """시스템 초기화"""
        try:
            logger.info("시스템 초기화 시작...")
            
            # 설정 로드 및 검증
            await self._load_and_validate_config()
            
            # 핵심 구성 요소 초기화
            await self._initialize_core_components()
            
            # 이벤트 시스템 설정
            self._setup_event_handlers()
            
            # 백그라운드 작업 시작
            await self._start_background_tasks()
            
            # 시스템 상태 업데이트
            self.status.state = SystemState.RUNNING
            self.status.last_update = datetime.now()
            
            logger.info("시스템 초기화 완료")
            
            # 초기화 완료 이벤트 발생
            await self.event_manager.emit('system_initialized', {
                'mode': self.mode.value,
                'timestamp': datetime.now().isoformat()
            })
            
            return True
            
        except Exception as e:
            logger.error(f"시스템 초기화 실패: {e}")
            self.status.state = SystemState.ERROR
            return False
    
    async def _load_and_validate_config(self):
        """설정 로드 및 검증"""
        logger.info("설정 로드 중...")
        
        # 환경별 설정 로드
        env = os.getenv('TRADING_ENV', 'development')
        config = await self.config_manager.load_config(env)
        
        # 설정 검증
        validation_result = self.config_manager.validate_config(config)
        if not validation_result['valid']:
            raise ValueError(f"설정 검증 실패: {validation_result['errors']}")
        
        # API 키 검증
        api_keys_valid = await self._validate_api_keys(config)
        if not api_keys_valid:
            logger.warning("API 키 검증 실패 - 모의 거래 모드로 실행")
        
        logger.info(f"설정 로드 완료: {env} 환경")
    
    async def _initialize_core_components(self):
        """핵심 구성 요소 초기화"""
        config = self.config_manager.get_config()
        
        # API 커넥터 초기화
        logger.info("API 커넥터 초기화...")
        self.api_connector = APIConnector(config['api'])
        await self.api_connector.initialize()
        self.status.api_connected = await self.api_connector.test_connection()
        
        # 리스크 매니저 초기화
        logger.info("리스크 매니저 초기화...")
        self.risk_manager = RiskManager(
            limits=config['risk']['limits'],
            initial_balance=config['risk']['initial_balance']
        )
        
        # 거래 엔진 초기화
        logger.info("거래 엔진 초기화...")
        self.trading_engine = TradingEngine(
            config=config['trading'],
            api_connector=self.api_connector,
            risk_manager=self.risk_manager
        )
        
        logger.info("핵심 구성 요소 초기화 완료")
    
    def _setup_event_handlers(self):
        """이벤트 핸들러 설정"""
        
        # 거래 엔진 이벤트
        self.event_manager.subscribe('signal_generated', self._on_signal_generated)
        self.event_manager.subscribe('position_opened', self._on_position_opened)
        self.event_manager.subscribe('position_closed', self._on_position_closed)
        self.event_manager.subscribe('risk_limit_exceeded', self._on_risk_limit_exceeded)
        
        # 시스템 이벤트
        self.event_manager.subscribe('config_changed', self._on_config_changed)
        self.event_manager.subscribe('api_connection_lost', self._on_api_connection_lost)
        self.event_manager.subscribe('emergency_stop', self._on_emergency_stop)
        
        logger.info("이벤트 핸들러 설정 완료")
    
    async def _start_background_tasks(self):
        """백그라운드 작업 시작"""
        
        # 시스템 상태 모니터링
        self.status_update_task = asyncio.create_task(self._system_status_monitor())
        self.background_tasks.append(self.status_update_task)
        
        # 설정 동기화 작업 (웹 모드에서만)
        if self.mode == SystemMode.WEB:
            sync_task = asyncio.create_task(self._config_sync_monitor())
            self.background_tasks.append(sync_task)
        
        # 성능 모니터링
        performance_task = asyncio.create_task(self._performance_monitor())
        self.background_tasks.append(performance_task)
        
        logger.info(f"백그라운드 작업 {len(self.background_tasks)}개 시작")
    
    async def _system_status_monitor(self):
        """시스템 상태 모니터링"""
        while not self.shutdown_event.is_set():
            try:
                # 상태 업데이트
                await self._update_system_status()
                
                # 상태 브로드캐스트
                await self.event_manager.emit('status_updated', self.status.to_dict())
                
                await asyncio.sleep(5)  # 5초마다 업데이트
                
            except Exception as e:
                logger.error(f"시스템 상태 모니터링 오류: {e}")
                await asyncio.sleep(10)
    
    async def _update_system_status(self):
        """시스템 상태 업데이트"""
        self.status.last_update = datetime.now()
        
        # 연결 상태 확인
        if self.api_connector:
            self.status.api_connected = await self.api_connector.is_connected()
        
        # 거래 상태 확인
        if self.trading_engine:
            self.status.trading_active = self.trading_engine.is_running()
            self.status.auto_trading_enabled = self.trading_engine.auto_trading_enabled
            self.status.positions_count = len(self.trading_engine.get_active_positions())
        
        # 일일 손익 계산
        if self.risk_manager:
            self.status.daily_pnl = self.risk_manager.get_daily_pnl()
        
        # 시스템 리소스 모니터링
        self._update_resource_usage()
    
    def _update_resource_usage(self):
        """시스템 리소스 사용량 업데이트"""
        try:
            import psutil
            process = psutil.Process()
            
            # 메모리 사용량 (MB)
            memory_info = process.memory_info()
            self.status.memory_usage_mb = memory_info.rss / 1024 / 1024
            
            # CPU 사용률 (%)
            self.status.cpu_usage_percent = process.cpu_percent()
            
        except ImportError:
            # psutil이 없으면 기본값 사용
            self.status.memory_usage_mb = 0.0
            self.status.cpu_usage_percent = 0.0
        except Exception as e:
            logger.debug(f"리소스 모니터링 오류: {e}")
    
    async def start_trading(self) -> bool:
        """자동매매 시작"""
        if not self.trading_engine:
            logger.error("거래 엔진이 초기화되지 않음")
            return False
        
        try:
            result = await self.trading_engine.start()
            if result:
                self.status.trading_active = True
                self.status.auto_trading_enabled = True
                
                await self.event_manager.emit('trading_started', {
                    'timestamp': datetime.now().isoformat(),
                    'mode': self.mode.value
                })
                
                logger.info("자동매매 시작됨")
            
            return result
            
        except Exception as e:
            logger.error(f"자동매매 시작 실패: {e}")
            return False
    
    async def stop_trading(self) -> bool:
        """자동매매 중지"""
        if not self.trading_engine:
            return False
        
        try:
            result = await self.trading_engine.stop()
            if result:
                self.status.trading_active = False
                self.status.auto_trading_enabled = False
                
                await self.event_manager.emit('trading_stopped', {
                    'timestamp': datetime.now().isoformat(),
                    'mode': self.mode.value
                })
                
                logger.info("자동매매 중지됨")
            
            return result
            
        except Exception as e:
            logger.error(f"자동매매 중지 실패: {e}")
            return False
    
    async def emergency_stop_all(self) -> bool:
        """긴급 전체 정지"""
        logger.warning("긴급 전체 정지 실행")
        
        try:
            # 거래 즉시 중지
            if self.trading_engine:
                await self.trading_engine.emergency_stop()
            
            # 모든 포지션 즉시 청산
            if self.api_connector:
                await self.api_connector.emergency_close_all_positions()
            
            # 시스템 상태 업데이트
            self.status.state = SystemState.PAUSED
            self.status.trading_active = False
            self.status.auto_trading_enabled = False
            
            # 긴급정지 이벤트 발생
            await self.event_manager.emit('emergency_stop_executed', {
                'timestamp': datetime.now().isoformat(),
                'reason': 'user_initiated',
                'positions_closed': self.status.positions_count
            })
            
            logger.warning("긴급 전체 정지 완료")
            return True
            
        except Exception as e:
            logger.error(f"긴급 정지 실행 실패: {e}")
            return False
    
    async def pause_system(self) -> bool:
        """시스템 일시정지"""
        try:
            self.status.state = SystemState.PAUSED
            
            if self.trading_engine:
                await self.trading_engine.pause()
            
            await self.event_manager.emit('system_paused', {
                'timestamp': datetime.now().isoformat()
            })
            
            logger.info("시스템 일시정지")
            return True
            
        except Exception as e:
            logger.error(f"시스템 일시정지 실패: {e}")
            return False
    
    async def resume_system(self) -> bool:
        """시스템 재시작"""
        try:
            self.status.state = SystemState.RUNNING
            
            if self.trading_engine:
                await self.trading_engine.resume()
            
            await self.event_manager.emit('system_resumed', {
                'timestamp': datetime.now().isoformat()
            })
            
            logger.info("시스템 재시작")
            return True
            
        except Exception as e:
            logger.error(f"시스템 재시작 실패: {e}")
            return False
    
    async def graceful_shutdown(self):
        """안전한 시스템 종료"""
        logger.info("시스템 종료 시작...")
        self.status.state = SystemState.STOPPING
        
        try:
            # 거래 중지
            if self.trading_engine and self.trading_engine.is_running():
                await self.trading_engine.stop()
            
            # 백그라운드 작업 종료
            self.shutdown_event.set()
            
            if self.background_tasks:
                logger.info(f"백그라운드 작업 {len(self.background_tasks)}개 종료 중...")
                for task in self.background_tasks:
                    if not task.done():
                        task.cancel()
                
                # 작업 완료 대기 (최대 10초)
                try:
                    await asyncio.wait_for(
                        asyncio.gather(*self.background_tasks, return_exceptions=True),
                        timeout=10.0
                    )
                except asyncio.TimeoutError:
                    logger.warning("일부 백그라운드 작업 강제 종료")
            
            # API 연결 정리
            if self.api_connector:
                await self.api_connector.close_all_connections()
            
            # 설정 저장
            await self.config_manager.save_config()
            
            # 종료 완료 이벤트
            await self.event_manager.emit('system_shutdown', {
                'timestamp': datetime.now().isoformat(),
                'mode': self.mode.value
            })
            
            self.status.state = SystemState.STOPPED
            logger.info("시스템 종료 완료")
            
        except Exception as e:
            logger.error(f"시스템 종료 중 오류: {e}")
            self.status.state = SystemState.ERROR
    
    def register_gui_interface(self, gui_interface):
        """GUI 인터페이스 등록"""
        self.gui_interface = gui_interface
        logger.info("GUI 인터페이스 등록됨")
    
    def register_web_interface(self, web_interface):
        """웹 인터페이스 등록"""
        self.web_interface = web_interface
        logger.info("웹 인터페이스 등록됨")
    
    # 이벤트 핸들러들
    async def _on_signal_generated(self, event_data: Dict):
        """거래 신호 생성 이벤트"""
        logger.info(f"거래 신호 생성: {event_data}")
        
        # GUI 업데이트
        if self.gui_interface:
            self.gui_interface.update_signal_display(event_data)
        
        # 웹 브로드캐스트
        if self.web_interface:
            await self.web_interface.broadcast_signal(event_data)
    
    async def _on_position_opened(self, event_data: Dict):
        """포지션 개설 이벤트"""
        logger.info(f"포지션 개설: {event_data}")
        self.status.positions_count += 1
        
        # 인터페이스 업데이트
        await self._broadcast_to_interfaces('position_opened', event_data)
    
    async def _on_position_closed(self, event_data: Dict):
        """포지션 청산 이벤트"""
        logger.info(f"포지션 청산: {event_data}")
        self.status.positions_count -= 1
        
        # 인터페이스 업데이트
        await self._broadcast_to_interfaces('position_closed', event_data)
    
    async def _on_risk_limit_exceeded(self, event_data: Dict):
        """리스크 한계 초과 이벤트"""
        logger.warning(f"리스크 한계 초과: {event_data}")
        
        # 자동 보호 조치
        if event_data.get('severity') == 'CRITICAL':
            await self.emergency_stop_all()
    
    async def _on_config_changed(self, event_data: Dict):
        """설정 변경 이벤트"""
        logger.info(f"설정 변경됨: {event_data}")
        
        # 관련 구성 요소 재시작 (필요 시)
        if 'trading' in event_data.get('changed_sections', []):
            if self.trading_engine:
                await self.trading_engine.reload_config()
    
    async def _on_api_connection_lost(self, event_data: Dict):
        """API 연결 손실 이벤트"""
        logger.warning(f"API 연결 손실: {event_data}")
        self.status.api_connected = False
        
        # 자동 재연결 시도
        if self.api_connector:
            asyncio.create_task(self.api_connector.reconnect())
    
    async def _on_emergency_stop(self, event_data: Dict):
        """긴급 정지 이벤트"""
        logger.critical(f"긴급 정지: {event_data}")
        await self.emergency_stop_all()
    
    async def _broadcast_to_interfaces(self, event_type: str, event_data: Dict):
        """모든 인터페이스에 이벤트 브로드캐스트"""
        
        # GUI 업데이트
        if self.gui_interface and hasattr(self.gui_interface, f'on_{event_type}'):
            try:
                getattr(self.gui_interface, f'on_{event_type}')(event_data)
            except Exception as e:
                logger.error(f"GUI 이벤트 처리 오류: {e}")
        
        # 웹 브로드캐스트
        if self.web_interface and hasattr(self.web_interface, 'broadcast_event'):
            try:
                await self.web_interface.broadcast_event(event_type, event_data)
            except Exception as e:
                logger.error(f"웹 이벤트 브로드캐스트 오류: {e}")
    
    async def _performance_monitor(self):
        """성능 모니터링"""
        while not self.shutdown_event.is_set():
            try:
                # API 응답 시간 측정
                if self.api_connector:
                    response_time = await self.api_connector.measure_response_time()
                    self.status.api_response_time_ms = response_time
                
                # 신호 생성 시간 측정
                if self.trading_engine:
                    signal_time = self.trading_engine.get_last_signal_generation_time()
                    self.status.signal_generation_time_ms = signal_time
                
                await asyncio.sleep(30)  # 30초마다 측정
                
            except Exception as e:
                logger.debug(f"성능 모니터링 오류: {e}")
                await asyncio.sleep(60)
    
    def _get_default_config_path(self) -> str:
        """기본 설정 파일 경로"""
        if self.mode == SystemMode.WEB:
            return "config/web_config.json"
        else:
            return "config/desktop_config.json"
    
    async def _validate_api_keys(self, config: Dict) -> bool:
        """API 키 유효성 검증"""
        try:
            # 임시 API 커넥터로 테스트
            temp_connector = APIConnector(config['api'])
            await temp_connector.initialize()
            result = await temp_connector.test_connection()
            await temp_connector.close_all_connections()
            return result
        except Exception as e:
            logger.error(f"API 키 검증 실패: {e}")
            return False
    
    # 공용 메서드들
    def get_system_status(self) -> Dict[str, Any]:
        """시스템 상태 반환"""
        return self.status.to_dict()
    
    def get_config(self) -> Dict[str, Any]:
        """현재 설정 반환"""
        return self.config_manager.get_config()
    
    async def update_config(self, section: str, data: Dict) -> bool:
        """설정 업데이트"""
        try:
            result = await self.config_manager.update_config_section(section, data)
            
            if result:
                await self.event_manager.emit('config_changed', {
                    'section': section,
                    'changed_sections': [section],
                    'timestamp': datetime.now().isoformat()
                })
            
            return result
            
        except Exception as e:
            logger.error(f"설정 업데이트 실패: {e}")
            return False
    
    def is_running(self) -> bool:
        """시스템 실행 상태 확인"""
        return self.status.state == SystemState.RUNNING
    
    def is_trading_active(self) -> bool:
        """거래 활성 상태 확인"""
        return self.status.trading_active


# 전역 시스템 관리자 참조 (편의 함수들)
def get_system_manager() -> SystemManager:
    """전역 시스템 관리자 인스턴스 반환"""
    return SystemManager.get_instance()

def initialize_system(mode: SystemMode, config_path: Optional[str] = None) -> SystemManager:
    """시스템 초기화 (진입점에서 사용)"""
    return SystemManager(mode, config_path)


# 시그널 핸들러 (안전한 종료를 위한)
def setup_signal_handlers(system_manager: SystemManager):
    """시그널 핸들러 설정"""
    
    def signal_handler(signum, frame):
        logger.info(f"종료 신호 수신: {signum}")
        
        # 비동기 종료 실행
        loop = asyncio.get_event_loop()
        if loop.is_running():
            asyncio.create_task(system_manager.graceful_shutdown())
        else:
            loop.run_until_complete(system_manager.graceful_shutdown())
    
    # SIGINT (Ctrl+C), SIGTERM 처리
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)


# 모듈 익스포트
__all__ = [
    'SystemMode',
    'SystemState', 
    'SystemStatus',
    'SystemManager',
    'get_system_manager',
    'initialize_system',
    'setup_signal_handlers'
]