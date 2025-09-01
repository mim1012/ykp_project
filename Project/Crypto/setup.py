"""
Setup script for Crypto Trading System.
"""

from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

with open("requirements.txt", "r", encoding="utf-8") as fh:
    requirements = [line.strip() for line in fh if line.strip() and not line.startswith("#")]

setup(
    name="crypto-trading-system",
    version="1.0.0",
    author="Crypto Trading System Team",
    author_email="team@cryptotrading.com",
    description="Comprehensive cryptocurrency trading system with GUI and web interface",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/crypto-trading-system/crypto-trading-system",
    packages=find_packages(),
    classifiers=[
        "Development Status :: 4 - Beta",
        "Intended Audience :: Financial and Insurance Industry",
        "License :: OSI Approved :: MIT License",
        "Operating System :: OS Independent",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
        "Topic :: Office/Business :: Financial :: Investment",
        "Topic :: Scientific/Engineering :: Information Analysis",
    ],
    python_requires=">=3.8",
    install_requires=requirements,
    extras_require={
        "dev": [
            "pytest>=7.2.0",
            "pytest-cov>=4.0.0", 
            "pytest-asyncio>=0.21.0",
            "black>=22.0.0",
            "flake8>=5.0.0",
            "mypy>=1.0.0",
        ],
        "gui": [
            "PyQt5>=5.15.7",
            "pyqtgraph>=0.13.1",
        ],
        "web": [
            "Flask>=2.2.0",
            "Flask-SocketIO>=5.3.0",
            "Flask-JWT-Extended>=4.4.0",
        ],
    },
    entry_points={
        "console_scripts": [
            "crypto-trading=main:main",
        ],
    },
    include_package_data=True,
    package_data={
        "": ["*.json", "*.yaml", "*.yml", "*.html", "*.css", "*.js"],
    },
)