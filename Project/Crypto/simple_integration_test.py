"""
간단한 통합 테스트

시스템의 핵심 기능들이 정상 작동하는지 빠르게 확인.
"""

import sys
import os
import time
from datetime import datetime

def test_imports():
    """모듈 임포트 테스트"""
    print("📦 모듈 임포트 테스트...")
    
    success_count = 0
    total_count = 0
    
    # Core 모듈들
    core_modules = [
        'core.constants',
        'core.exceptions', 
        'core.method_helpers',
        'core.backtesting_engine',
        'core.performance_optimizer'
    ]
    
    for module in core_modules:
        total_count += 1
        try:
            __import__(module)
            print(f"  ✅ {module}")
            success_count += 1
        except ImportError as e:
            print(f"  ❌ {module}: {e}")
    
    print(f"\n📊 임포트 결과: {success_count}/{total_count} 성공 ({success_count/total_count*100:.1f}%)")
    return success_count == total_count

def test_constants():
    """상수 정의 테스트"""
    print("\n🔢 상수 정의 테스트...")
    
    try:
        from core.constants import (
            TradingConstants, PCSConstants, RiskConstants,
            PerformanceTargets, APIConstants
        )
        
        # 주요 상수들 확인
        assert TradingConstants.DEFAULT_CHANNEL_PERIOD == 20
        assert PCSConstants.STAGE1_PROFIT_THRESHOLD == 0.02
        assert len(RiskConstants.PROFIT_LEVELS) == 6
        assert PerformanceTargets.SIGNAL_GENERATION_TARGET_MS == 10
        
        print("  ✅ 모든 상수 정의 확인")
        return True
        
    except Exception as e:
        print(f"  ❌ 상수 테스트 실패: {e}")
        return False

def test_exceptions():
    """예외 클래스 테스트"""
    print("\n⚠️ 예외 클래스 테스트...")
    
    try:
        from core.exceptions import (
            TradingSystemError, APIError, RiskLimitExceededError,
            PCSError, SecurityError
        )
        
        # 기본 예외 테스트
        error = TradingSystemError("테스트 오류", "TEST_ERROR", {'detail': 'test'})
        assert error.message == "테스트 오류"
        assert error.error_code == "TEST_ERROR"
        
        # 딕셔너리 변환 테스트
        error_dict = error.to_dict()
        assert 'error' in error_dict
        assert 'message' in error_dict
        
        print("  ✅ 예외 클래스 정상 작동")
        return True
        
    except Exception as e:
        print(f"  ❌ 예외 테스트 실패: {e}")
        return False

def test_performance():
    """성능 측정 테스트"""
    print("\n⚡ 성능 측정 테스트...")
    
    try:
        # 간단한 성능 측정
        start_time = time.perf_counter()
        
        # 시뮬레이션된 계산 작업
        result = sum(i * i for i in range(1000))
        
        execution_time_ms = (time.perf_counter() - start_time) * 1000
        
        print(f"  📊 샘플 계산 시간: {execution_time_ms:.2f}ms")
        
        # 메모리 사용량 확인 (가능한 경우)
        try:
            import psutil
            process = psutil.Process()
            memory_mb = process.memory_info().rss / 1024 / 1024
            print(f"  💾 메모리 사용량: {memory_mb:.1f}MB")
        except ImportError:
            print("  ⚠️ psutil 없음 - 메모리 측정 불가")
        
        print("  ✅ 성능 측정 정상")
        return True
        
    except Exception as e:
        print(f"  ❌ 성능 테스트 실패: {e}")
        return False

def test_file_structure():
    """파일 구조 테스트"""
    print("\n📁 파일 구조 테스트...")
    
    required_dirs = [
        'core', 'desktop', 'web', 'strategies', 
        'strategies/exit_strategies', 'tests', 'config'
    ]
    
    required_files = [
        'main.py', 'requirements.txt', 'setup.py',
        'core/trading_engine.py', 'core/api_connector.py',
        'desktop/main_gui.py', 'web/app.py'
    ]
    
    missing_items = []
    
    # 디렉토리 확인
    for dir_path in required_dirs:
        if not os.path.exists(dir_path):
            missing_items.append(f"DIR: {dir_path}")
    
    # 파일 확인
    for file_path in required_files:
        if not os.path.exists(file_path):
            missing_items.append(f"FILE: {file_path}")
    
    if missing_items:
        print(f"  ❌ 누락된 항목들:")
        for item in missing_items:
            print(f"    - {item}")
        return False
    else:
        print("  ✅ 파일 구조 완전")
        return True

def main():
    """메인 테스트 실행"""
    
    print("Crypto Trading System - Quick Integration Test")
    print("=" * 60)
    print(f"테스트 시작 시간: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    test_start = time.time()
    
    # 테스트 실행
    tests = [
        ('파일 구조', test_file_structure),
        ('모듈 임포트', test_imports),
        ('상수 정의', test_constants),
        ('예외 클래스', test_exceptions),
        ('성능 측정', test_performance)
    ]
    
    passed_tests = 0
    total_tests = len(tests)
    
    for test_name, test_func in tests:
        try:
            if test_func():
                passed_tests += 1
        except Exception as e:
            print(f"  💥 {test_name} 테스트 중 예외: {e}")
    
    test_duration = time.time() - test_start
    
    # 결과 요약
    print("\n" + "=" * 60)
    print("📊 테스트 결과 요약")
    print("=" * 60)
    print(f"총 테스트: {total_tests}개")
    print(f"✅ 통과: {passed_tests}개")
    print(f"❌ 실패: {total_tests - passed_tests}개")
    print(f"🎯 성공률: {passed_tests/total_tests*100:.1f}%")
    print(f"⏱️ 소요시간: {test_duration:.1f}초")
    
    if passed_tests == total_tests:
        print("\n🎉 모든 테스트 통과! 시스템 준비 완료!")
        return True
    else:
        print(f"\n⚠️ {total_tests - passed_tests}개 테스트 실패 - 수정 필요")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)