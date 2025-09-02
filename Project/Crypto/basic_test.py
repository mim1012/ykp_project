"""
Basic System Test

Simple test without Unicode characters to verify core functionality
"""

import sys
import os
import time
from datetime import datetime

def test_core_imports():
    """Test core module imports"""
    print("Testing core module imports...")
    
    try:
        from core.constants import TradingConstants, PCSConstants, RiskConstants
        print("  OK: Constants imported")
        
        from core.exceptions import TradingSystemError, APIError
        print("  OK: Exceptions imported")
        
        from core.method_helpers import validate_market_data
        print("  OK: Method helpers imported")
        
        return True
    except Exception as e:
        print(f"  FAIL: {e}")
        return False

def test_constants_values():
    """Test constant values"""
    print("Testing constant values...")
    
    try:
        from core.constants import TradingConstants, PCSConstants
        
        assert TradingConstants.DEFAULT_CHANNEL_PERIOD == 20
        assert PCSConstants.STAGE1_PROFIT_THRESHOLD == 0.02
        assert len(TradingConstants.DEFAULT_MA_PERIODS) == 3
        
        print("  OK: All constants verified")
        return True
    except Exception as e:
        print(f"  FAIL: {e}")
        return False

def test_file_structure():
    """Test file structure"""
    print("Testing file structure...")
    
    required_files = [
        'core/constants.py',
        'core/exceptions.py', 
        'core/method_helpers.py',
        'core/backtesting_engine.py',
        'core/performance_optimizer.py'
    ]
    
    missing = []
    for file_path in required_files:
        if not os.path.exists(file_path):
            missing.append(file_path)
    
    if missing:
        print(f"  FAIL: Missing files: {missing}")
        return False
    else:
        print("  OK: All required files present")
        return True

def test_performance():
    """Test basic performance"""
    print("Testing performance...")
    
    start = time.perf_counter()
    
    # Simple calculation
    result = sum(i * i for i in range(1000))
    
    duration_ms = (time.perf_counter() - start) * 1000
    
    print(f"  Sample calculation: {duration_ms:.2f}ms")
    
    try:
        import psutil
        memory_mb = psutil.Process().memory_info().rss / 1024 / 1024
        print(f"  Memory usage: {memory_mb:.1f}MB")
    except ImportError:
        print("  No psutil - memory check skipped")
    
    print("  OK: Performance test completed")
    return True

def main():
    """Main test execution"""
    
    print("Crypto Trading System - Basic Test")
    print("=" * 50)
    print(f"Start time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    tests = [
        ("File Structure", test_file_structure),
        ("Core Imports", test_core_imports), 
        ("Constants", test_constants_values),
        ("Performance", test_performance)
    ]
    
    passed = 0
    total = len(tests)
    
    for test_name, test_func in tests:
        try:
            if test_func():
                passed += 1
                print(f"PASSED: {test_name}")
            else:
                print(f"FAILED: {test_name}")
        except Exception as e:
            print(f"ERROR in {test_name}: {e}")
        print()
    
    print("=" * 50)
    print("Test Summary")
    print("=" * 50)
    print(f"Total: {total}")
    print(f"Passed: {passed}")
    print(f"Failed: {total - passed}")
    print(f"Success rate: {passed/total*100:.1f}%")
    
    if passed == total:
        print("\nALL TESTS PASSED! System is ready!")
        return True
    else:
        print(f"\n{total-passed} tests failed - fixes needed")
        return False

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)