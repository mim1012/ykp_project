# Crypto Trading Web Dashboard

Professional web-based dashboard for cryptocurrency trading system with real-time monitoring, position management, and remote control capabilities.

## Features

### Core Functionality
- **Real-time Dashboard**: Live market data, position monitoring, and trading system status
- **Authentication System**: JWT-based secure login with session management
- **Trading Control**: Start/stop trading, emergency stop, and position management
- **Settings Management**: Complete trading strategy configuration interface
- **WebSocket Integration**: Real-time updates for all data streams

### Technical Features
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **PWA Support**: Installable as a progressive web app
- **Docker Ready**: Containerized deployment with docker-compose
- **Production Ready**: Nginx reverse proxy, SSL support, rate limiting
- **Real-time Updates**: Socket.IO for live data streaming
- **Auto-save Settings**: Configuration changes saved automatically

## Quick Start

### Prerequisites
- Docker and Docker Compose
- Python 3.11+ (for local development)
- Git

### Production Deployment (VPS)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd crypto-trading-dashboard/web
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your actual values
   nano .env
   ```

3. **Deploy with Docker**
   ```bash
   ./deploy.sh production
   ```

4. **Access the dashboard**
   - HTTP: http://your-server-ip
   - HTTPS: https://your-domain.com (after SSL setup)

### Local Development

1. **Install dependencies**
   ```bash
   pip install -r requirements.txt
   ```

2. **Run Redis (required)**
   ```bash
   docker run -d -p 6379:6379 redis:alpine
   ```

3. **Start the application**
   ```bash
   python app.py
   ```

4. **Access locally**
   - http://localhost:5000

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
# Security
SECRET_KEY=your-super-secret-key
JWT_SECRET_KEY=your-jwt-secret-key

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Trading System
TRADING_SYSTEM_HOST=localhost
TRADING_SYSTEM_PORT=8888
```

### Default Credentials

- **Username**: `admin`
- **Password**: `admin123`

⚠️ **Change these immediately after first login!**

## Architecture

### Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     Nginx       │    │   Flask App     │    │     Redis       │
│  (Reverse Proxy)│◄──►│  (Web Server)   │◄──►│    (Cache)      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐
                       │     SQLite      │
                       │   (Database)    │
                       └─────────────────┘
```

### Technology Stack

- **Backend**: Flask + Flask-SocketIO + Flask-JWT-Extended
- **Frontend**: HTML5 + CSS3 + JavaScript ES6+ + Bootstrap 5
- **Real-time**: WebSocket (Socket.IO)
- **Database**: SQLite (settings) + Redis (cache)
- **Deployment**: Docker + docker-compose + Nginx
- **Charts**: Chart.js for real-time price visualization

## API Endpoints

### Authentication
- `POST /login` - User authentication
- `GET /logout` - User logout

### System Control
- `GET /api/system/status` - Get system status
- `POST /api/system/toggle-trading` - Toggle auto trading

### Settings
- `GET /api/settings` - Get trading settings
- `POST /api/settings` - Update trading settings

### Data
- `GET /api/positions` - Get current positions
- `GET /api/market-data` - Get market data

## WebSocket Events

### Client → Server
- `connect` - Establish connection
- `ping` - Connection keepalive

### Server → Client
- `market_data_update` - Real-time market prices
- `positions_update` - Position changes
- `system_status_update` - System status changes
- `conditions_update` - Trading condition status
- `stats_update` - Daily performance statistics

## Deployment Guide

### VPS Deployment Steps

1. **Server Preparation**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Install Docker
   curl -fsSL https://get.docker.com | sh
   sudo usermod -aG docker $USER
   
   # Install Docker Compose
   sudo apt install docker-compose-plugin -y
   ```

2. **SSL Certificate Setup (Production)**
   ```bash
   # Using Let's Encrypt (recommended)
   sudo apt install certbot -y
   sudo certbot certonly --standalone -d your-domain.com
   
   # Copy certificates to ssl/ directory
   sudo cp /etc/letsencrypt/live/your-domain.com/fullchain.pem ssl/
   sudo cp /etc/letsencrypt/live/your-domain.com/privkey.pem ssl/
   ```

3. **Firewall Configuration**
   ```bash
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

4. **Deploy Application**
   ```bash
   ./deploy.sh production
   ```

### Docker Commands

```bash
# View logs
docker-compose logs -f

# Restart services
docker-compose restart

# Update application
docker-compose down
docker-compose pull
docker-compose up -d

# Backup data
./deploy.sh backup

# Clean up old images
./deploy.sh cleanup
```

## Security Features

### Authentication & Authorization
- JWT token-based authentication
- Session timeout (30 minutes default)
- Remember me functionality (7 days)
- Secure password hashing

### Network Security
- Rate limiting on login and API endpoints
- CORS protection
- Security headers (XSS, CSP, HSTS)
- Nginx reverse proxy

### Data Protection
- Database encryption support
- Secure cookie settings
- Environment variable configuration
- Input validation and sanitization

## Monitoring & Logging

### Health Checks
- Application health endpoint
- Database connectivity check
- Redis connectivity check
- Nginx status monitoring

### Logging
- Structured logging with levels
- Access logs via Nginx
- Error tracking and alerts
- Performance monitoring

## Troubleshooting

### Common Issues

1. **Connection refused**
   ```bash
   # Check if services are running
   docker-compose ps
   
   # Check logs
   docker-compose logs web
   ```

2. **Redis connection failed**
   ```bash
   # Restart Redis
   docker-compose restart redis
   
   # Check Redis logs
   docker-compose logs redis
   ```

3. **SSL certificate errors**
   ```bash
   # Verify certificate files
   ls -la ssl/
   
   # Test certificate validity
   openssl x509 -in ssl/fullchain.pem -text -noout
   ```

### Performance Optimization

1. **Enable Redis persistence**
   ```yaml
   # In docker-compose.yml
   command: redis-server --appendonly yes
   ```

2. **Nginx caching**
   ```nginx
   # Add to nginx config
   proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=app_cache:10m;
   ```

3. **Database optimization**
   ```python
   # Enable WAL mode for SQLite
   PRAGMA journal_mode=WAL;
   ```

## Development

### Local Setup

1. **Virtual Environment**
   ```bash
   python -m venv venv
   source venv/bin/activate  # Linux/Mac
   venv\Scripts\activate     # Windows
   ```

2. **Install Dependencies**
   ```bash
   pip install -r requirements.txt
   ```

3. **Run Development Server**
   ```bash
   export FLASK_ENV=development
   python app.py
   ```

### Code Structure

```
web/
├── app.py              # Main Flask application
├── templates/          # HTML templates
│   ├── base.html      # Base template
│   ├── login.html     # Login page
│   ├── dashboard.html # Main dashboard
│   ├── settings.html  # Settings page
│   └── error.html     # Error pages
├── static/            # Static assets (auto-generated)
├── nginx/             # Nginx configuration
├── ssl/               # SSL certificates
├── data/              # Database files
├── logs/              # Application logs
└── requirements.txt   # Python dependencies
```

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:
- Create an issue in the repository
- Check the troubleshooting guide
- Review the logs for error details

## Version History

- **v1.0.0** - Initial release with core functionality
  - Real-time dashboard
  - JWT authentication
  - Trading system integration
  - Docker deployment
  - Mobile-responsive design

---

**Built for professional crypto trading operations** 🚀