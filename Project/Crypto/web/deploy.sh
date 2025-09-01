#!/bin/bash

# Crypto Trading Web Dashboard Deployment Script
# Usage: ./deploy.sh [environment]

set -e

# Configuration
ENVIRONMENT=${1:-production}
PROJECT_NAME="crypto-trading-dashboard"
DOCKER_REGISTRY=${DOCKER_REGISTRY:-""}
VERSION=${VERSION:-$(git rev-parse --short HEAD 2>/dev/null || echo "latest")}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check dependencies
check_dependencies() {
    log_info "Checking dependencies..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    log_success "Dependencies check passed"
}

# Create necessary directories
create_directories() {
    log_info "Creating necessary directories..."
    
    mkdir -p data
    mkdir -p logs
    mkdir -p ssl
    
    # Set proper permissions
    chmod 755 data logs
    
    log_success "Directories created"
}

# Generate environment file
generate_env_file() {
    log_info "Generating environment file..."
    
    if [ ! -f .env ]; then
        if [ -f .env.example ]; then
            cp .env.example .env
            log_warning "Created .env from .env.example. Please update it with your actual values."
        else
            log_error ".env.example not found. Cannot create .env file."
            exit 1
        fi
    else
        log_info ".env file already exists"
    fi
}

# Generate SSL certificates (self-signed for development)
generate_ssl_certificates() {
    log_info "Checking SSL certificates..."
    
    if [ "$ENVIRONMENT" = "development" ] && [ ! -f ssl/cert.pem ]; then
        log_info "Generating self-signed SSL certificates for development..."
        
        openssl req -x509 -newkey rsa:4096 -keyout ssl/privkey.pem -out ssl/fullchain.pem \
            -days 365 -nodes -subj "/CN=localhost"
        
        # Create chain file (same as fullchain for self-signed)
        cp ssl/fullchain.pem ssl/chain.pem
        
        log_warning "Self-signed SSL certificates generated. Not suitable for production!"
    elif [ "$ENVIRONMENT" = "production" ] && [ ! -f ssl/fullchain.pem ]; then
        log_warning "SSL certificates not found. Please obtain valid SSL certificates for production."
        log_info "Place your certificates in:"
        log_info "  - ssl/fullchain.pem (certificate + intermediate)"
        log_info "  - ssl/privkey.pem (private key)"
        log_info "  - ssl/chain.pem (intermediate certificates)"
    fi
}

# Build Docker images
build_images() {
    log_info "Building Docker images..."
    
    # Build the main application image
    docker build -t ${PROJECT_NAME}:${VERSION} .
    docker tag ${PROJECT_NAME}:${VERSION} ${PROJECT_NAME}:latest
    
    log_success "Docker images built successfully"
}

# Deploy the application
deploy() {
    log_info "Deploying $PROJECT_NAME in $ENVIRONMENT mode..."
    
    # Export environment variables for docker-compose
    export PROJECT_NAME
    export VERSION
    export ENVIRONMENT
    
    # Choose the appropriate docker-compose file
    COMPOSE_FILE="docker-compose.yml"
    if [ -f "docker-compose.${ENVIRONMENT}.yml" ]; then
        COMPOSE_FILE="docker-compose.yml -f docker-compose.${ENVIRONMENT}.yml"
    fi
    
    # Stop existing containers
    log_info "Stopping existing containers..."
    docker-compose -f $COMPOSE_FILE down --remove-orphans
    
    # Start services
    log_info "Starting services..."
    docker-compose -f $COMPOSE_FILE up -d
    
    # Wait for services to be ready
    log_info "Waiting for services to be ready..."
    sleep 10
    
    # Check service health
    check_health
    
    log_success "$PROJECT_NAME deployed successfully in $ENVIRONMENT mode"
}

# Check service health
check_health() {
    log_info "Checking service health..."
    
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if curl -f http://localhost:5000/ &>/dev/null; then
            log_success "Web service is healthy"
            break
        fi
        
        attempt=$((attempt + 1))
        log_info "Waiting for web service... (attempt $attempt/$max_attempts)"
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        log_error "Web service health check failed"
        show_logs
        exit 1
    fi
}

# Show service logs
show_logs() {
    log_info "Showing recent logs..."
    docker-compose logs --tail=20
}

# Cleanup old images
cleanup() {
    log_info "Cleaning up old Docker images..."
    
    # Remove dangling images
    docker image prune -f
    
    # Remove old versions (keep last 3)
    docker images ${PROJECT_NAME} --format "table {{.Tag}}" | tail -n +4 | head -n -3 | xargs -r docker rmi ${PROJECT_NAME}: 2>/dev/null || true
    
    log_success "Cleanup completed"
}

# Backup data
backup_data() {
    log_info "Creating data backup..."
    
    local backup_dir="backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$backup_dir"
    
    # Backup database
    if [ -f data/web_dashboard.db ]; then
        cp data/web_dashboard.db "$backup_dir/"
        log_success "Database backed up to $backup_dir"
    fi
    
    # Keep only last 7 days of backups
    find backups/ -type d -mtime +7 -exec rm -rf {} + 2>/dev/null || true
}

# Main deployment function
main() {
    log_info "Starting deployment of $PROJECT_NAME"
    log_info "Environment: $ENVIRONMENT"
    log_info "Version: $VERSION"
    echo
    
    check_dependencies
    create_directories
    generate_env_file
    
    if [ "$ENVIRONMENT" != "development" ]; then
        generate_ssl_certificates
        backup_data
    fi
    
    build_images
    deploy
    
    if [ "$ENVIRONMENT" = "production" ]; then
        cleanup
    fi
    
    echo
    log_success "Deployment completed successfully!"
    log_info "Web dashboard is available at:"
    log_info "  - HTTP: http://localhost"
    log_info "  - HTTPS: https://localhost (if SSL is configured)"
    echo
    log_info "Default login credentials:"
    log_info "  - Username: admin"
    log_info "  - Password: admin123"
    echo
    log_warning "Please change the default credentials after first login!"
    echo
    log_info "To view logs: docker-compose logs -f"
    log_info "To stop: docker-compose down"
}

# Handle script arguments
case "$1" in
    logs)
        show_logs
        exit 0
        ;;
    cleanup)
        cleanup
        exit 0
        ;;
    backup)
        backup_data
        exit 0
        ;;
    *)
        main
        ;;
esac