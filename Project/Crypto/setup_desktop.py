"""
Desktop Application Setup Script
For creating Windows EXE package using PyInstaller
"""

import os
import sys
import shutil
from pathlib import Path
import subprocess

# Configuration
APP_NAME = "CryptoTradingSystem"
APP_VERSION = "1.0.0"
MAIN_SCRIPT = "run_desktop_app.py"
ICON_FILE = "assets/app_icon.ico"  # If available


def check_pyinstaller():
    """Check if PyInstaller is installed"""
    try:
        import PyInstaller
        return True
    except ImportError:
        return False


def install_pyinstaller():
    """Install PyInstaller"""
    print("Installing PyInstaller...")
    try:
        subprocess.check_call([sys.executable, "-m", "pip", "install", "pyinstaller"])
        print("✅ PyInstaller installed successfully")
        return True
    except subprocess.CalledProcessError:
        print("❌ Failed to install PyInstaller")
        return False


def create_spec_file():
    """Create PyInstaller spec file"""
    spec_content = f'''# -*- mode: python ; coding: utf-8 -*-

block_cipher = None

# Analysis phase - collect all Python files and dependencies
a = Analysis(
    ['{MAIN_SCRIPT}'],
    pathex=[],
    binaries=[],
    datas=[
        # Include all necessary data files
        ('desktop/tabs/*.py', 'desktop/tabs'),
        ('desktop/widgets/*.py', 'desktop/widgets'),
        ('desktop/dialogs/*.py', 'desktop/dialogs'),
        ('core/*.py', 'core'),
        ('config/*.json', 'config'),
        ('config/*.yaml', 'config'),
        ('strategies/*.py', 'strategies'),
        ('docs/*.md', 'docs'),
    ],
    hiddenimports=[
        'PyQt5.QtCore',
        'PyQt5.QtGui', 
        'PyQt5.QtWidgets',
        'pyqtgraph',
        'numpy',
        'pandas',
        'cryptography.fernet',
        'psutil',
        'datetime',
        'json',
        'yaml',
        'threading',
        'asyncio',
        'websockets',
        'requests',
        'ccxt',
    ],
    hookspath=[],
    hooksconfig={{}},
    runtime_hooks=[],
    excludes=[
        'matplotlib',  # Exclude if not needed to reduce size
        'scipy',
        'sklearn',
        'tensorflow',
        'torch',
        'PIL',
    ],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=block_cipher,
    noarchive=False,
)

# Remove duplicate files
pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

# Create executable
exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name='{APP_NAME}',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,  # Compress executable
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,  # Hide console window for GUI app
    disable_windowed_traceback=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    version='version_info.txt',  # Version information
    icon='{ICON_FILE if os.path.exists(ICON_FILE) else None}',  # Application icon
)

# Create distribution folder
coll = COLLECT(
    exe,
    a.binaries,
    a.zipfiles,
    a.datas,
    strip=False,
    upx=True,
    upx_exclude=[],
    name='{APP_NAME}_v{APP_VERSION}',
)
'''
    
    with open(f"{APP_NAME}.spec", "w", encoding="utf-8") as f:
        f.write(spec_content)
        
    print(f"✅ Created {APP_NAME}.spec")


def create_version_info():
    """Create version information file for Windows executable"""
    version_info = f'''# UTF-8
#
# For more details about fixed file info 'ffi' see:
# http://msdn.microsoft.com/en-us/library/ms646997.aspx
VSVersionInfo(
  ffi=FixedFileInfo(
    filevers=(1,0,0,0),
    prodvers=(1,0,0,0),
    mask=0x3f,
    flags=0x0,
    OS=0x40004,
    fileType=0x1,
    subtype=0x0,
    date=(0, 0)
  ),
  kids=[
    StringFileInfo(
      [
      StringTable(
        u'040904B0',
        [StringStruct(u'CompanyName', u'Professional Trading Team'),
        StringStruct(u'FileDescription', u'전문 가상화폐 자동매매 시스템'),
        StringStruct(u'FileVersion', u'{APP_VERSION}'),
        StringStruct(u'InternalName', u'{APP_NAME}'),
        StringStruct(u'LegalCopyright', u'Copyright © 2024 Professional Trading Team'),
        StringStruct(u'OriginalFilename', u'{APP_NAME}.exe'),
        StringStruct(u'ProductName', u'Professional Crypto Trading System'),
        StringStruct(u'ProductVersion', u'{APP_VERSION}')])
      ]), 
    VarFileInfo([VarStruct(u'Translation', [1033, 1200])])
  ]
)
'''
    
    with open("version_info.txt", "w", encoding="utf-8") as f:
        f.write(version_info)
        
    print("✅ Created version_info.txt")


def build_executable():
    """Build executable using PyInstaller"""
    print(f"🔨 Building {APP_NAME} executable...")
    
    try:
        # Run PyInstaller with spec file
        cmd = [
            "pyinstaller",
            "--clean",  # Clean cache and temporary files
            "--noconfirm",  # Replace output directory without confirmation
            f"{APP_NAME}.spec"
        ]
        
        print(f"Running command: {' '.join(cmd)}")
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode == 0:
            print("✅ Build completed successfully!")
            print(f"📁 Executable created in: dist/{APP_NAME}_v{APP_VERSION}/")
            return True
        else:
            print("❌ Build failed!")
            print("STDOUT:", result.stdout)
            print("STDERR:", result.stderr)
            return False
            
    except subprocess.CalledProcessError as e:
        print(f"❌ Build failed with error: {e}")
        return False


def create_installer_script():
    """Create NSIS installer script"""
    nsis_script = f'''
; Professional Crypto Trading System Installer
; Created with NSIS (Nullsoft Scriptable Install System)

!define APP_NAME "{APP_NAME}"
!define APP_VERSION "{APP_VERSION}"
!define PUBLISHER "Professional Trading Team"
!define WEB_SITE "https://crypto-trading.com"
!define INSTALL_DIR "$PROGRAMFILES\\Professional Crypto Trading System"

; Includes
!include "MUI2.nsh"

; General
Name "${{APP_NAME}} v${{APP_VERSION}}"
OutFile "Setup_${{APP_NAME}}_v${{APP_VERSION}}.exe"
InstallDir "${{INSTALL_DIR}}"
InstallDirRegKey HKLM "Software\\${{APP_NAME}}" "InstallDir"
ShowInstDetails show
ShowUninstDetails show

; Interface Settings
!define MUI_ABORTWARNING
!define MUI_ICON "${{NSISDIR}}\\Contrib\\Graphics\\Icons\\modern-install.ico"
!define MUI_UNICON "${{NSISDIR}}\\Contrib\\Graphics\\Icons\\modern-uninstall.ico"

; Pages
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "LICENSE.txt"
!insertmacro MUI_PAGE_COMPONENTS
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_WELCOME
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_UNPAGE_FINISH

; Languages
!insertmacro MUI_LANGUAGE "Korean"
!insertmacro MUI_LANGUAGE "English"

; Installation
Section "Core Application" SecMain
    SectionIn RO ; Required section
    
    SetOutPath "$INSTDIR"
    File /r "dist\\${{APP_NAME}}_v${{APP_VERSION}}\\*"
    
    ; Create shortcuts
    CreateDirectory "$SMPROGRAMS\\${{APP_NAME}}"
    CreateShortCut "$SMPROGRAMS\\${{APP_NAME}}\\${{APP_NAME}}.lnk" "$INSTDIR\\${{APP_NAME}}.exe"
    CreateShortCut "$DESKTOP\\Professional Crypto Trading.lnk" "$INSTDIR\\${{APP_NAME}}.exe"
    
    ; Registry entries
    WriteRegStr HKLM "Software\\${{APP_NAME}}" "InstallDir" "$INSTDIR"
    WriteRegStr HKLM "Software\\${{APP_NAME}}" "Version" "${{APP_VERSION}}"
    
    ; Uninstaller
    WriteUninstaller "$INSTDIR\\Uninstall.exe"
    WriteRegStr HKLM "Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\${{APP_NAME}}" "DisplayName" "${{APP_NAME}}"
    WriteRegStr HKLM "Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\${{APP_NAME}}" "UninstallString" "$INSTDIR\\Uninstall.exe"
    
SectionEnd

Section "Desktop Shortcut" SecDesktop
    CreateShortCut "$DESKTOP\\Professional Crypto Trading.lnk" "$INSTDIR\\${{APP_NAME}}.exe"
SectionEnd

; Uninstaller
Section "Uninstall"
    Delete "$INSTDIR\\*.*"
    RMDir /r "$INSTDIR"
    
    Delete "$SMPROGRAMS\\${{APP_NAME}}\\*.*"
    RMDir "$SMPROGRAMS\\${{APP_NAME}}"
    Delete "$DESKTOP\\Professional Crypto Trading.lnk"
    
    DeleteRegKey HKLM "Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\${{APP_NAME}}"
    DeleteRegKey HKLM "Software\\${{APP_NAME}}"
SectionEnd
'''
    
    with open(f"{APP_NAME}_installer.nsi", "w", encoding="utf-8") as f:
        f.write(nsis_script)
        
    print(f"✅ Created {APP_NAME}_installer.nsi")


def create_license_file():
    """Create license file for installer"""
    license_text = """
Professional Crypto Trading System v1.0
Copyright © 2024 Professional Trading Team

이 소프트웨어는 상용 라이선스 하에 배포됩니다.

IMPORTANT - READ CAREFULLY:

This software is licensed, not sold. By installing and using this software, 
you agree to the following terms:

1. License Grant: You are granted a non-exclusive, non-transferable license 
   to use this software for personal or commercial trading purposes.

2. Restrictions: You may not:
   - Reverse engineer, decompile, or disassemble the software
   - Redistribute or resell the software
   - Use the software for illegal activities

3. Disclaimer: This software is provided "as is" without warranty of any kind.
   Trading cryptocurrencies involves substantial risk of loss.

4. Liability: The developers are not liable for any losses incurred through 
   the use of this software.

5. Updates: Updates may be provided at the discretion of the developers.

For support and inquiries, please contact: support@crypto-trading.com
"""
    
    with open("LICENSE.txt", "w", encoding="utf-8") as f:
        f.write(license_text)
        
    print("✅ Created LICENSE.txt")


def cleanup_build_files():
    """Clean up temporary build files"""
    files_to_remove = [
        "build",
        "__pycache__",
        f"{APP_NAME}.spec",
        "version_info.txt"
    ]
    
    for item in files_to_remove:
        if os.path.isdir(item):
            shutil.rmtree(item, ignore_errors=True)
            print(f"🗑️ Removed directory: {item}")
        elif os.path.isfile(item):
            os.remove(item)
            print(f"🗑️ Removed file: {item}")


def main():
    """Main setup function"""
    print("🚀 Desktop Application Setup")
    print("=" * 50)
    
    # Check if main script exists
    if not os.path.exists(MAIN_SCRIPT):
        print(f"❌ Main script not found: {MAIN_SCRIPT}")
        return False
        
    # Check PyInstaller
    if not check_pyinstaller():
        if not install_pyinstaller():
            return False
            
    # Create necessary files
    create_version_info()
    create_spec_file()
    create_license_file()
    create_installer_script()
    
    # Build executable
    if build_executable():
        print("\n✅ Build completed successfully!")
        print(f"📁 Executable location: dist/{APP_NAME}_v{APP_VERSION}/{APP_NAME}.exe")
        print(f"📦 Installer script: {APP_NAME}_installer.nsi")
        print("\n📋 Next steps:")
        print("1. Test the executable in dist/ folder")
        print("2. Install NSIS (https://nsis.sourceforge.io/) to create installer")
        print(f"3. Compile {APP_NAME}_installer.nsi with NSIS")
        print("4. Distribute the generated Setup_*.exe file")
        
        # Ask about cleanup
        response = input("\n🗑️ Clean up temporary build files? [y/N]: ")
        if response.lower() in ['y', 'yes']:
            cleanup_build_files()
            
        return True
    else:
        print("\n❌ Build failed!")
        return False


if __name__ == "__main__":
    success = main()
    
    if success:
        print("\n🎉 Setup completed successfully!")
        print("Your professional crypto trading application is ready!")
    else:
        print("\n💥 Setup failed!")
        print("Please check the error messages above and try again.")
        
    input("\nPress Enter to exit...")