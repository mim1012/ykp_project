"""
EXE Build Script

PyInstaller를 사용하여 Windows용 독립 실행 파일 생성.
PRD 명세에 따른 완전한 EXE 버전 패키징.
"""

import os
import sys
import shutil
import subprocess
from pathlib import Path
from datetime import datetime

def create_exe():
    """EXE 파일 생성"""
    
    print("Building Crypto Trading System EXE...")
    print("=" * 50)
    
    # PyInstaller 설치 확인
    try:
        import PyInstaller
        print(f"PyInstaller version: {PyInstaller.__version__}")
    except ImportError:
        print("Installing PyInstaller...")
        subprocess.run([sys.executable, "-m", "pip", "install", "pyinstaller"])
    
    # 빌드 디렉토리 정리
    build_dirs = ['build', 'dist', '__pycache__']
    for dir_name in build_dirs:
        if os.path.exists(dir_name):
            shutil.rmtree(dir_name)
            print(f"Cleaned: {dir_name}")
    
    # PyInstaller 명령어 구성
    pyinstaller_cmd = [
        "pyinstaller",
        "--onefile",                    # 단일 파일
        "--windowed",                   # 콘솔 창 숨김
        "--name", "CryptoTradingSystem", # 실행 파일 이름
        "--icon", "icon.ico",           # 아이콘 (있는 경우)
        "--add-data", "config;config",  # 설정 폴더 포함
        "--add-data", "templates;templates",  # 템플릿 포함
        "--hidden-import", "PyQt5",     # 숨겨진 임포트
        "--hidden-import", "pandas",
        "--hidden-import", "numpy", 
        "--hidden-import", "cryptography",
        "--collect-all", "PyQt5",
        "main.py"                       # 메인 스크립트
    ]
    
    print("Executing PyInstaller...")
    print(" ".join(pyinstaller_cmd))
    print()
    
    # PyInstaller 실행
    try:
        result = subprocess.run(pyinstaller_cmd, capture_output=True, text=True)
        
        if result.returncode == 0:
            print("✅ EXE 빌드 성공!")
            
            # 생성된 파일 확인
            exe_path = Path("dist/CryptoTradingSystem.exe")
            if exe_path.exists():
                file_size_mb = exe_path.stat().st_size / 1024 / 1024
                print(f"EXE 파일 크기: {file_size_mb:.1f}MB")
                print(f"파일 위치: {exe_path.absolute()}")
            
        else:
            print("❌ EXE 빌드 실패!")
            print("STDOUT:", result.stdout)
            print("STDERR:", result.stderr)
            
    except Exception as e:
        print(f"빌드 중 오류: {e}")

def create_installer():
    """설치 프로그램 생성 (선택적)"""
    print("\nCreating installer package...")
    
    # 배포용 폴더 생성
    dist_folder = Path("distribution")
    if dist_folder.exists():
        shutil.rmtree(dist_folder)
    
    dist_folder.mkdir()
    
    # 필요한 파일들 복사
    files_to_copy = [
        ("dist/CryptoTradingSystem.exe", "CryptoTradingSystem.exe"),
        ("README.md", "README.md"),
        ("requirements.txt", "requirements.txt"),
        ("config", "config")
    ]
    
    for src, dst in files_to_copy:
        src_path = Path(src)
        dst_path = dist_folder / dst
        
        if src_path.exists():
            if src_path.is_dir():
                shutil.copytree(src_path, dst_path)
            else:
                shutil.copy2(src_path, dst_path)
            print(f"Copied: {src} -> {dst}")
    
    # 설치 가이드 생성
    install_guide = f"""
암호화폐 자동매매 시스템 설치 가이드
========================================

설치 방법:
1. CryptoTradingSystem.exe를 원하는 폴더에 복사
2. config/ 폴더를 같은 위치에 복사
3. CryptoTradingSystem.exe 실행

시스템 요구사항:
- Windows 10/11 (64bit)
- 메모리: 최소 4GB (권장 8GB)
- 저장공간: 200MB
- 인터넷 연결 (거래 시에만)

빌드 정보:
- 빌드 일시: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
- 버전: 1.0.0
- 포함된 모듈: 전체 거래 시스템

사용법:
1. 프로그램 실행 후 비밀번호 입력 (기본: admin123)
2. 시스템 설정 탭에서 API 키 입력
3. 진입/청산 조건 설정
4. 자동매매 시작

주의사항:
- 실제 거래 전 반드시 모의 거래로 테스트
- API 키는 안전하게 보관
- 정기적인 백업 권장
"""
    
    with open(dist_folder / "설치가이드.txt", "w", encoding="utf-8") as f:
        f.write(install_guide)
    
    print(f"✅ 배포 패키지 생성 완료: {dist_folder.absolute()}")

if __name__ == "__main__":
    print("Crypto Trading System Builder")
    print("Building Windows EXE version...")
    print()
    
    create_exe()
    create_installer()
    
    print("\n" + "=" * 50)
    print("Build process completed!")
    print("Check the 'distribution' folder for the final package.")