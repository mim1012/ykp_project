# Crypto Trading System - Project Structure

## Complete Directory Structure

```
D:\Project\Crypto\
├── main.py                          # Main application entry point
├── requirements.txt                 # Python dependencies
├── setup.py                        # Package setup configuration
├── README.md                       # Project documentation
├── .gitignore                      # Git ignore patterns
├── PROJECT_STRUCTURE.md           # This file
│
├── core/                           # Business logic modules
│   ├── __init__.py                # Core package initialization
│   ├── trading_engine.py         # 5 entry + 4 exit conditions
│   ├── risk_manager.py           # 12-level risk management
│   ├── api_connector.py          # Binance + Bybit integration
│   ├── config_manager.py         # Encrypted configuration
│   ├── security_module.py        # Fernet encryption, JWT
│   ├── time_controller.py        # Weekly/daily time control
│   ├── data_processor.py         # Real-time data processing
│   └── logger.py                 # Comprehensive logging
│
├── desktop/                       # PyQt5 GUI application
│   ├── __init__.py               # Desktop package init
│   ├── main_window.py            # Main application window
│   ├── widgets/                  # Custom widgets
│   │   ├── __init__.py          
│   │   ├── trading_panel.py     # Order entry interface
│   │   ├── positions_widget.py  # Position display
│   │   ├── orderbook_widget.py  # Order book display
│   │   ├── chart_widget.py      # Price charts
│   │   ├── log_widget.py        # System logs
│   │   ├── performance_widget.py # Performance metrics
│   │   ├── risk_widget.py       # Risk management
│   │   └── config_widget.py     # Configuration UI
│   ├── dialogs/                 # Dialog windows
│   │   ├── __init__.py
│   │   ├── login_dialog.py      # User authentication
│   │   ├── settings_dialog.py   # Application settings
│   │   └── about_dialog.py      # About information
│   └── utils/                   # Desktop utilities
│
├── web/                          # Flask web dashboard
│   ├── __init__.py              # Web package init
│   ├── app.py                   # Flask application factory
│   ├── routes/                  # Web routes
│   │   ├── __init__.py
│   │   ├── main_routes.py       # Main web pages
│   │   ├── api_routes.py        # REST API endpoints
│   │   └── auth_routes.py       # Authentication routes
│   ├── templates/               # HTML templates
│   │   ├── base.html           # Base template
│   │   ├── dashboard.html      # Dashboard page
│   │   ├── trading.html        # Trading interface
│   │   └── positions.html      # Positions page
│   ├── static/                 # Static assets
│   │   ├── css/               # Stylesheets
│   │   └── js/                # JavaScript files
│   └── utils/                  # Web utilities
│
├── tests/                       # Test suite
│   ├── __init__.py             # Test package init
│   ├── conftest.py             # Pytest configuration
│   ├── unit/                   # Unit tests
│   │   ├── test_trading_engine.py
│   │   ├── test_risk_manager.py
│   │   └── (other unit tests)
│   ├── integration/            # Integration tests
│   └── fixtures/               # Test fixtures
│
├── config/                     # Configuration files
│   ├── templates/             # Configuration templates
│   │   ├── development.json   # Development settings
│   │   ├── testing.json       # Testing settings
│   │   └── production.json    # Production settings
│   └── environments/          # Environment-specific configs
│
└── docs/                      # Documentation
    ├── api/                   # API documentation
    ├── user_guide/           # User guides
    └── developer/            # Developer documentation
```

## Core Modules Overview

### 1. Trading Engine (`core/trading_engine.py`)
- **5 Entry Conditions:**
  1. Funding Rate Arbitrage
  2. Momentum Breakout
  3. Mean Reversion
  4. Sentiment Signal
  5. Whale Flow Signal

- **4 Exit Conditions:**
  1. Take Profit
  2. Stop Loss
  3. Time-based Exit
  4. Signal Reversal

### 2. Risk Manager (`core/risk_manager.py`)
- **12-Level Risk Management:**
  - Levels 1-3: Conservative (Low risk)
  - Levels 4-6: Moderate (Medium risk)
  - Levels 7-9: Aggressive (High risk)
  - Levels 10-12: Emergency (Critical protocols)

### 3. API Connectors (`core/api_connector.py`)
- Binance Futures API integration
- Bybit API integration
- WebSocket real-time data
- Order management
- Position tracking

### 4. Security Module (`core/security_module.py`)
- Fernet encryption for sensitive data
- JWT token management
- Argon2 password hashing
- API key validation
- Rate limiting utilities

### 5. Configuration Manager (`core/config_manager.py`)
- Encrypted configuration storage
- Environment-specific settings
- Hot-reload capability
- Configuration validation
- Backup and restore

### 6. Time Controller (`core/time_controller.py`)
- Weekly/daily trading schedules
- Market hours management
- Time-based restrictions
- Holiday calendar support
- Multiple timezone handling

### 7. Data Processor (`core/data_processor.py`)
- Real-time market data processing
- Technical indicators calculation
- Market sentiment analysis
- WebSocket data streaming
- Performance monitoring

### 8. System Logger (`core/logger.py`)
- Structured JSON logging
- File rotation and compression
- Multiple log categories
- Performance metrics tracking
- Security event logging

## User Interfaces

### Desktop Application (PyQt5)
- **Main Features:**
  - Real-time trading interface
  - Advanced charting with pyqtgraph
  - Position and order management
  - Risk monitoring dashboard
  - System logs and performance metrics
  - Configuration management
  - System tray integration

### Web Dashboard (Flask)
- **Main Features:**
  - Browser-based trading interface
  - Real-time updates via WebSocket
  - REST API for external integration
  - JWT authentication
  - Responsive design
  - Mobile-friendly interface

### CLI Mode
- **Main Features:**
  - Command-line interface for automation
  - Script-friendly operations
  - Monitoring and status commands
  - Background service capability

## Installation and Setup

### 1. Install Dependencies
```bash
pip install -r requirements.txt
```

### 2. Configure API Keys
Edit `config/templates/development.json` with your exchange API credentials.

### 3. Run Application

**GUI Mode (Default):**
```bash
python main.py --mode gui
```

**Web Dashboard:**
```bash
python main.py --mode web
```

**CLI Mode:**
```bash
python main.py --mode cli
```

**Background Service:**
```bash
python main.py --mode service
```

### 4. Environment Options
```bash
# Development (default)
python main.py --env development

# Production
python main.py --env production --log-level WARNING

# Testing with testnet
python main.py --env testing --testnet
```

## Key Features Implemented

### Trading System
- ✅ 5 entry conditions with customizable logic
- ✅ 4 exit conditions for risk management
- ✅ Multi-exchange support (Binance + Bybit)
- ✅ Real-time order execution
- ✅ Position management and tracking

### Risk Management
- ✅ 12-level dynamic risk assessment
- ✅ Automatic position sizing
- ✅ Drawdown monitoring
- ✅ Emergency stop functionality
- ✅ Portfolio exposure limits

### Security
- ✅ Fernet encryption for sensitive data
- ✅ JWT authentication for web interface
- ✅ Argon2 password hashing
- ✅ API signature validation
- ✅ Rate limiting protection

### Data Processing
- ✅ Real-time market data streaming
- ✅ Technical indicators (SMA, EMA, RSI, MACD, etc.)
- ✅ Market sentiment analysis
- ✅ WebSocket connection management
- ✅ Data caching and historical storage

### User Interfaces
- ✅ PyQt5 desktop application
- ✅ Flask web dashboard
- ✅ Command-line interface
- ✅ Background service mode
- ✅ Real-time updates via WebSocket

### Configuration & Management
- ✅ Encrypted configuration storage
- ✅ Environment-specific settings
- ✅ Hot-reload configuration changes
- ✅ Comprehensive logging system
- ✅ Performance monitoring

## Architecture Highlights

1. **Modular Design**: Each component is independently testable and replaceable
2. **Async/Await**: Full asynchronous support for high-performance trading
3. **Type Hints**: Complete type annotation for better code quality
4. **Error Handling**: Comprehensive exception handling and recovery
5. **Security First**: Encryption and security built into every layer
6. **Scalable**: Designed to handle multiple exchanges and trading pairs
7. **Testable**: Comprehensive test suite with unit and integration tests
8. **Configurable**: Flexible configuration system for different environments
9. **Monitoring**: Built-in performance and health monitoring
10. **Documentation**: Extensive inline documentation and type hints

This structure provides a solid foundation for a professional-grade cryptocurrency trading system with both desktop and web interfaces, comprehensive risk management, and production-ready security features.