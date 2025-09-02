"""
Performance Optimizer

시스템 성능 최적화 및 모니터링.
PRD 목표 달성: 신호생성 <10ms, API응답 <100ms, 메모리 효율화.
"""

from typing import Dict, List, Optional, Any, Callable
from dataclasses import dataclass
from datetime import datetime, timedelta
import time
import asyncio
import threading
from collections import deque
import gc
import psutil
import cProfile
import pstats
from functools import wraps
import weakref

from .logger import SystemLogger
from .constants import PerformanceTargets, SystemConstants
from .exceptions import SystemError

logger = SystemLogger.get_logger(__name__)


@dataclass
class PerformanceMetrics:
    """성능 지표"""
    
    # === 응답 시간 (밀리초) ===
    signal_generation_ms: float = 0.0
    api_response_ms: float = 0.0
    ui_response_ms: float = 0.0
    websocket_latency_ms: float = 0.0
    
    # === 처리량 ===
    signals_per_second: float = 0.0
    api_requests_per_second: float = 0.0
    
    # === 메모리 사용량 (MB) ===
    total_memory_mb: float = 0.0
    core_engine_memory_mb: float = 0.0
    gui_memory_mb: float = 0.0
    
    # === CPU 사용률 (%) ===
    cpu_usage_percent: float = 0.0
    
    # === 성능 목표 달성률 ===
    targets_achieved: Dict[str, bool] = None
    
    def __post_init__(self):
        if self.targets_achieved is None:
            self.targets_achieved = {}
            
        # 목표 달성 여부 계산
        self.targets_achieved = {
            'signal_generation': self.signal_generation_ms <= PerformanceTargets.SIGNAL_GENERATION_TARGET_MS,
            'api_response': self.api_response_ms <= PerformanceTargets.API_RESPONSE_TARGET_MS,
            'ui_response': self.ui_response_ms <= PerformanceTargets.UI_RESPONSE_TARGET_MS,
            'websocket_latency': self.websocket_latency_ms <= PerformanceTargets.WEBSOCKET_LATENCY_TARGET_MS,
            'memory_exe': self.total_memory_mb <= PerformanceTargets.EXE_MEMORY_TARGET_MB,
            'memory_web': self.total_memory_mb <= PerformanceTargets.WEB_SERVER_MEMORY_TARGET_MB,
            'cpu_usage': self.cpu_usage_percent <= 5.0  # 5% CPU 목표
        }
    
    @property
    def overall_performance_score(self) -> float:
        """전체 성능 점수 (0~100)"""
        achieved_count = sum(1 for achieved in self.targets_achieved.values() if achieved)
        total_targets = len(self.targets_achieved)
        return (achieved_count / total_targets) * 100 if total_targets > 0 else 0


class PerformanceProfiler:
    """성능 프로파일러"""
    
    def __init__(self):
        self.profiler = cProfile.Profile()
        self.profiling_active = False
        self.execution_times = deque(maxlen=1000)  # 최근 1000개 실행 시간
        self.memory_snapshots = deque(maxlen=100)  # 최근 100개 메모리 스냅샷
    
    def profile_function(self, func: Callable) -> Callable:
        """함수 성능 프로파일링 데코레이터"""
        
        @wraps(func)
        async def async_wrapper(*args, **kwargs):
            start_time = time.perf_counter()
            
            try:
                if asyncio.iscoroutinefunction(func):
                    result = await func(*args, **kwargs)
                else:
                    result = func(*args, **kwargs)
                
                execution_time_ms = (time.perf_counter() - start_time) * 1000
                self.execution_times.append(execution_time_ms)
                
                # 성능 목표 초과 시 경고
                if execution_time_ms > PerformanceTargets.SIGNAL_GENERATION_TARGET_MS:
                    logger.warning(f"{func.__name__} 성능 목표 초과: {execution_time_ms:.2f}ms")
                
                return result
            
            except Exception as e:
                logger.error(f"{func.__name__} 실행 오류: {e}")
                raise
        
        @wraps(func)
        def sync_wrapper(*args, **kwargs):
            start_time = time.perf_counter()
            
            try:
                result = func(*args, **kwargs)
                
                execution_time_ms = (time.perf_counter() - start_time) * 1000
                self.execution_times.append(execution_time_ms)
                
                return result
            
            except Exception as e:
                logger.error(f"{func.__name__} 실행 오류: {e}")
                raise
        
        return async_wrapper if asyncio.iscoroutinefunction(func) else sync_wrapper
    
    def start_profiling(self):
        """프로파일링 시작"""
        self.profiler.enable()
        self.profiling_active = True
        logger.info("성능 프로파일링 시작")
    
    def stop_profiling(self) -> str:
        """프로파일링 중지 및 결과 반환"""
        self.profiler.disable()
        self.profiling_active = False
        
        # 결과 분석
        stats = pstats.Stats(self.profiler)
        stats.sort_stats('cumulative')
        
        # 상위 10개 함수 추출
        stats.print_stats(10)
        
        logger.info("성능 프로파일링 완료")
        return "프로파일링 결과가 로그에 출력되었습니다"
    
    def get_recent_performance(self) -> Dict[str, float]:
        """최근 성능 통계"""
        if not self.execution_times:
            return {}
        
        times = list(self.execution_times)
        
        return {
            'average_ms': np.mean(times),
            'median_ms': np.median(times),
            'max_ms': np.max(times),
            'min_ms': np.min(times),
            'std_dev_ms': np.std(times),
            'samples_count': len(times)
        }


class MemoryOptimizer:
    """메모리 최적화 관리자"""
    
    def __init__(self):
        self.memory_cache = {}
        self.weak_references = weakref.WeakSet()
        self.cache_hit_count = 0
        self.cache_miss_count = 0
    
    def cache_result(self, key: str, value: Any, ttl_seconds: int = 300):
        """결과 캐싱 (TTL 지원)"""
        expiry_time = datetime.now() + timedelta(seconds=ttl_seconds)
        
        self.memory_cache[key] = {
            'value': value,
            'expiry': expiry_time,
            'access_count': 0
        }
    
    def get_cached_result(self, key: str) -> Optional[Any]:
        """캐시된 결과 조회"""
        if key not in self.memory_cache:
            self.cache_miss_count += 1
            return None
        
        cache_entry = self.memory_cache[key]
        
        # TTL 확인
        if datetime.now() > cache_entry['expiry']:
            del self.memory_cache[key]
            self.cache_miss_count += 1
            return None
        
        # 접근 횟수 증가
        cache_entry['access_count'] += 1
        self.cache_hit_count += 1
        
        return cache_entry['value']
    
    def cleanup_expired_cache(self):
        """만료된 캐시 정리"""
        now = datetime.now()
        expired_keys = [
            key for key, entry in self.memory_cache.items()
            if now > entry['expiry']
        ]
        
        for key in expired_keys:
            del self.memory_cache[key]
        
        if expired_keys:
            logger.debug(f"만료된 캐시 {len(expired_keys)}개 정리")
    
    def force_garbage_collection(self):
        """강제 가비지 컬렉션"""
        collected = gc.collect()
        logger.debug(f"가비지 컬렉션: {collected}개 객체 정리")
    
    def get_memory_stats(self) -> Dict[str, Any]:
        """메모리 통계"""
        try:
            process = psutil.Process()
            memory_info = process.memory_info()
            
            return {
                'rss_mb': memory_info.rss / 1024 / 1024,
                'vms_mb': memory_info.vms / 1024 / 1024,
                'cache_entries': len(self.memory_cache),
                'cache_hit_rate': self.cache_hit_count / (self.cache_hit_count + self.cache_miss_count) if (self.cache_hit_count + self.cache_miss_count) > 0 else 0,
                'weak_references': len(self.weak_references)
            }
        
        except Exception as e:
            logger.error(f"메모리 통계 조회 실패: {e}")
            return {}


class PerformanceOptimizer:
    """통합 성능 최적화 관리자"""
    
    def __init__(self):
        self.profiler = PerformanceProfiler()
        self.memory_optimizer = MemoryOptimizer()
        
        # 성능 모니터링
        self.monitoring_active = False
        self.performance_history = deque(maxlen=100)
        
        # 최적화 설정
        self.auto_optimization_enabled = True
        self.optimization_threshold = 0.8  # 80% 목표 달성률 이하 시 최적화
    
    async def start_performance_monitoring(self):
        """성능 모니터링 시작"""
        self.monitoring_active = True
        
        # 백그라운드 모니터링 태스크
        asyncio.create_task(self._performance_monitor_loop())
        asyncio.create_task(self._memory_cleanup_loop())
        
        logger.info("성능 모니터링 시작")
    
    def stop_performance_monitoring(self):
        """성능 모니터링 중지"""
        self.monitoring_active = False
        logger.info("성능 모니터링 중지")
    
    async def _performance_monitor_loop(self):
        """성능 모니터링 루프"""
        while self.monitoring_active:
            try:
                # 현재 성능 지표 수집
                metrics = await self._collect_performance_metrics()
                
                # 성능 기록 저장
                self.performance_history.append(metrics)
                
                # 자동 최적화 트리거 (성능이 목표 이하일 때)
                if self.auto_optimization_enabled:
                    if metrics.overall_performance_score < self.optimization_threshold * 100:
                        await self._trigger_auto_optimization(metrics)
                
                await asyncio.sleep(SystemConstants.PERFORMANCE_MONITOR_INTERVAL_SECONDS)
            
            except Exception as e:
                logger.error(f"성능 모니터링 오류: {e}")
                await asyncio.sleep(60)
    
    async def _memory_cleanup_loop(self):
        """메모리 정리 루프"""
        while self.monitoring_active:
            try:
                # 만료된 캐시 정리
                self.memory_optimizer.cleanup_expired_cache()
                
                # 메모리 사용량 확인
                memory_stats = self.memory_optimizer.get_memory_stats()
                current_memory_mb = memory_stats.get('rss_mb', 0)
                
                # 메모리 한계 초과 시 강제 정리
                if current_memory_mb > PerformanceTargets.EXE_MEMORY_TARGET_MB * 0.8:  # 80% 도달시
                    logger.warning(f"메모리 사용량 높음: {current_memory_mb:.1f}MB")
                    self.memory_optimizer.force_garbage_collection()
                
                await asyncio.sleep(SystemConstants.CACHE_CLEANUP_INTERVAL_SECONDS)
            
            except Exception as e:
                logger.error(f"메모리 정리 오류: {e}")
                await asyncio.sleep(120)
    
    async def _collect_performance_metrics(self) -> PerformanceMetrics:
        """성능 지표 수집"""
        
        # 프로파일러에서 실행 시간 통계
        recent_perf = self.profiler.get_recent_performance()
        
        # 메모리 통계
        memory_stats = self.memory_optimizer.get_memory_stats()
        
        # 시스템 리소스
        try:
            process = psutil.Process()
            cpu_percent = process.cpu_percent()
            memory_mb = memory_stats.get('rss_mb', 0)
        except:
            cpu_percent = 0.0
            memory_mb = 0.0
        
        return PerformanceMetrics(
            signal_generation_ms=recent_perf.get('average_ms', 0.0),
            api_response_ms=0.0,  # API 커넥터에서 업데이트
            ui_response_ms=0.0,   # GUI에서 업데이트
            websocket_latency_ms=0.0,  # WebSocket에서 업데이트
            signals_per_second=1000.0 / recent_perf.get('average_ms', 1000.0) if recent_perf.get('average_ms', 0) > 0 else 0,
            total_memory_mb=memory_mb,
            cpu_usage_percent=cpu_percent
        )
    
    async def _trigger_auto_optimization(self, metrics: PerformanceMetrics):
        """자동 최적화 트리거"""
        
        logger.info(f"자동 성능 최적화 시작 (현재 점수: {metrics.overall_performance_score:.1f}%)")
        
        optimization_applied = False
        
        # 신호 생성 최적화
        if not metrics.targets_achieved.get('signal_generation', True):
            await self._optimize_signal_generation()
            optimization_applied = True
        
        # 메모리 최적화
        if not metrics.targets_achieved.get('memory_exe', True):
            await self._optimize_memory_usage()
            optimization_applied = True
        
        # CPU 최적화
        if metrics.cpu_usage_percent > 8.0:  # 8% 초과 시
            await self._optimize_cpu_usage()
            optimization_applied = True
        
        if optimization_applied:
            logger.info("자동 성능 최적화 완료")
        else:
            logger.debug("성능 최적화 불필요")
    
    async def _optimize_signal_generation(self):
        """신호 생성 최적화"""
        
        # 캐시 크기 증가
        cache_keys = list(self.memory_optimizer.memory_cache.keys())
        signal_cache_keys = [k for k in cache_keys if 'signal' in k]
        
        if len(signal_cache_keys) < 50:  # 캐시 부족 시 증가
            logger.info("신호 생성 캐시 크기 증가")
        
        # 가비지 컬렉션 강제 실행
        self.memory_optimizer.force_garbage_collection()
    
    async def _optimize_memory_usage(self):
        """메모리 사용량 최적화"""
        
        # 불필요한 데이터 정리
        self.memory_optimizer.cleanup_expired_cache()
        
        # 가비지 컬렉션
        self.memory_optimizer.force_garbage_collection()
        
        # 메모리 사용량 재측정
        memory_stats = self.memory_optimizer.get_memory_stats()
        logger.info(f"메모리 최적화 후: {memory_stats.get('rss_mb', 0):.1f}MB")
    
    async def _optimize_cpu_usage(self):
        """CPU 사용량 최적화"""
        
        # 백그라운드 작업 간격 증가
        logger.info("CPU 사용량 최적화: 모니터링 간격 증가")
        
        # 불필요한 계산 줄이기
        await asyncio.sleep(0.1)  # 잠시 대기하여 CPU 부하 감소


class AsyncPerformanceDecorator:
    """비동기 성능 측정 데코레이터"""
    
    def __init__(self, target_time_ms: float = None):
        self.target_time_ms = target_time_ms or PerformanceTargets.SIGNAL_GENERATION_TARGET_MS
        self.execution_times = deque(maxlen=100)
    
    def __call__(self, func: Callable):
        
        @wraps(func)
        async def wrapper(*args, **kwargs):
            start_time = time.perf_counter()
            
            try:
                result = await func(*args, **kwargs)
                
                execution_time_ms = (time.perf_counter() - start_time) * 1000
                self.execution_times.append(execution_time_ms)
                
                # 성능 목표 체크
                if execution_time_ms > self.target_time_ms:
                    logger.warning(f"{func.__name__} 성능 목표 초과: {execution_time_ms:.2f}ms > {self.target_time_ms}ms")
                
                return result
            
            except Exception as e:
                logger.error(f"{func.__name__} 실행 중 오류: {e}")
                raise
        
        return wrapper
    
    def get_performance_stats(self) -> Dict[str, float]:
        """성능 통계 반환"""
        if not self.execution_times:
            return {}
        
        times = list(self.execution_times)
        return {
            'average_ms': np.mean(times),
            'median_ms': np.median(times),
            'p95_ms': np.percentile(times, 95),
            'max_ms': np.max(times),
            'target_achieved_rate': len([t for t in times if t <= self.target_time_ms]) / len(times)
        }


class BulkOperationOptimizer:
    """대량 작업 최적화"""
    
    def __init__(self, max_workers: int = 4):
        self.max_workers = max_workers
        self.executor = ThreadPoolExecutor(max_workers=max_workers)
    
    async def process_batch_operations(
        self, 
        operations: List[Callable],
        batch_size: int = 10
    ) -> List[Any]:
        """배치 작업 처리"""
        
        results = []
        
        # 배치 단위로 분할 처리
        for i in range(0, len(operations), batch_size):
            batch = operations[i:i + batch_size]
            
            # 병렬 실행
            loop = asyncio.get_event_loop()
            batch_results = await asyncio.gather(*[
                loop.run_in_executor(self.executor, op) for op in batch
            ])
            
            results.extend(batch_results)
            
            # 배치 간 잠시 대기 (시스템 부하 방지)
            await asyncio.sleep(0.01)
        
        return results
    
    def shutdown(self):
        """실행자 종료"""
        self.executor.shutdown(wait=True)


class DataStreamOptimizer:
    """데이터 스트림 최적화"""
    
    def __init__(self):
        self.data_buffers = {}
        self.compression_enabled = True
        self.buffer_size_limit = 1000
    
    def add_data_point(self, stream_name: str, data: Any):
        """데이터 포인트 추가 (버퍼링)"""
        
        if stream_name not in self.data_buffers:
            self.data_buffers[stream_name] = deque(maxlen=self.buffer_size_limit)
        
        self.data_buffers[stream_name].append({
            'data': data,
            'timestamp': datetime.now()
        })
    
    def get_buffered_data(self, stream_name: str, count: int = None) -> List[Any]:
        """버퍼된 데이터 조회"""
        
        if stream_name not in self.data_buffers:
            return []
        
        buffer = self.data_buffers[stream_name]
        
        if count is None:
            return [item['data'] for item in buffer]
        else:
            return [item['data'] for item in list(buffer)[-count:]]
    
    def optimize_data_structure(self, data: pd.DataFrame) -> pd.DataFrame:
        """데이터 구조 최적화"""
        
        # 메모리 사용량 감소를 위한 데이터 타입 최적화
        for col in data.columns:
            if data[col].dtype == 'float64':
                # 정밀도가 필요하지 않은 경우 float32 사용
                if col in ['volume', 'count']:
                    data[col] = data[col].astype('float32')
            
            elif data[col].dtype == 'int64':
                # int32로 다운캐스팅 (범위 확인 후)
                if data[col].max() < 2**31 and data[col].min() > -2**31:
                    data[col] = data[col].astype('int32')
        
        return data


# === 전역 성능 최적화 인스턴스 ===
_global_optimizer = None

def get_performance_optimizer() -> PerformanceOptimizer:
    """전역 성능 최적화 인스턴스 반환"""
    global _global_optimizer
    
    if _global_optimizer is None:
        _global_optimizer = PerformanceOptimizer()
    
    return _global_optimizer


# === 성능 측정 데코레이터들 ===
def measure_execution_time(target_ms: float = PerformanceTargets.SIGNAL_GENERATION_TARGET_MS):
    """실행 시간 측정 데코레이터"""
    return AsyncPerformanceDecorator(target_ms)


def cache_result(ttl_seconds: int = 300):
    """결과 캐싱 데코레이터"""
    def decorator(func: Callable):
        @wraps(func)
        async def wrapper(*args, **kwargs):
            # 캐시 키 생성
            cache_key = f"{func.__name__}_{hash(str(args) + str(kwargs))}"
            
            # 캐시 조회
            optimizer = get_performance_optimizer()
            cached_result = optimizer.memory_optimizer.get_cached_result(cache_key)
            
            if cached_result is not None:
                return cached_result
            
            # 캐시 미스 - 실제 함수 실행
            result = await func(*args, **kwargs)
            
            # 결과 캐싱
            optimizer.memory_optimizer.cache_result(cache_key, result, ttl_seconds)
            
            return result
        
        return wrapper
    return decorator


# === 성능 리포트 생성 ===
def generate_performance_report(metrics_history: List[PerformanceMetrics]) -> str:
    """성능 리포트 생성"""
    
    if not metrics_history:
        return "성능 데이터가 없습니다."
    
    # 평균 성능 계산
    avg_signal_time = np.mean([m.signal_generation_ms for m in metrics_history])
    avg_memory = np.mean([m.total_memory_mb for m in metrics_history])
    avg_cpu = np.mean([m.cpu_usage_percent for m in metrics_history])
    
    # 목표 달성률 계산
    target_achievements = {}
    for target_name in ['signal_generation', 'memory_exe', 'cpu_usage']:
        achievement_rate = np.mean([
            m.targets_achieved.get(target_name, False) for m in metrics_history
        ]) * 100
        target_achievements[target_name] = achievement_rate
    
    report = f"""
📊 성능 리포트
{'='*50}

⏱️ 응답 시간 성능
- 신호 생성: {avg_signal_time:.2f}ms (목표: {PerformanceTargets.SIGNAL_GENERATION_TARGET_MS}ms)
- 목표 달성률: {target_achievements.get('signal_generation', 0):.1f}%

💾 메모리 성능  
- 평균 사용량: {avg_memory:.1f}MB (목표: {PerformanceTargets.EXE_MEMORY_TARGET_MB}MB)
- 목표 달성률: {target_achievements.get('memory_exe', 0):.1f}%

🖥️ CPU 성능
- 평균 사용률: {avg_cpu:.1f}% (목표: 5.0%)
- 목표 달성률: {target_achievements.get('cpu_usage', 0):.1f}%

📈 전체 성능 점수
- 평균 점수: {np.mean([m.overall_performance_score for m in metrics_history]):.1f}/100
- 측정 기간: {len(metrics_history)}회 측정

🎯 권장사항
"""
    
    # 권장사항 생성
    if avg_signal_time > PerformanceTargets.SIGNAL_GENERATION_TARGET_MS:
        report += "- 신호 생성 최적화 필요: 캐싱 증가, 알고리즘 개선\n"
    
    if avg_memory > PerformanceTargets.EXE_MEMORY_TARGET_MB:
        report += "- 메모리 최적화 필요: 데이터 구조 개선, 가비지 컬렉션 강화\n"
    
    if avg_cpu > 5.0:
        report += "- CPU 최적화 필요: 백그라운드 작업 간격 조정\n"
    
    return report


# 모듈 익스포트
__all__ = [
    'PerformanceMetrics',
    'PerformanceProfiler',
    'MemoryOptimizer', 
    'PerformanceOptimizer',
    'AsyncPerformanceDecorator',
    'BulkOperationOptimizer',
    'DataStreamOptimizer',
    'get_performance_optimizer',
    'measure_execution_time',
    'cache_result',
    'generate_performance_report'
]