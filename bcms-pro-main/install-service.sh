#!/bin/bash
# Attendance Sync Service Installer for Linux
# This script must be run as root or with sudo

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SERVICE_NAME="attendance-sync"
SERVICE_FILE="attendance-sync.service"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SYSTEMD_DIR="/etc/systemd/system"
PHP_PATH=$(which php)
ARTISAN_PATH="$PROJECT_DIR/artisan"

echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}Attendance Sync Service Installer (Linux)${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}ERROR: This script must be run as root or with sudo!${NC}"
    echo -e "${YELLOW}Usage: sudo ./install-service.sh${NC}"
    exit 1
fi

echo -e "${GREEN}Running as root: OK${NC}"
echo ""

# Verify PHP
echo -e "${YELLOW}Verifying PHP installation...${NC}"
if [ -z "$PHP_PATH" ]; then
    echo -e "${RED}ERROR: PHP not found in PATH${NC}"
    echo -e "${YELLOW}Please install PHP or update the PHP_PATH in the service file${NC}"
    exit 1
fi
echo -e "${GREEN}  PHP Path: $PHP_PATH${NC}"
$PHP_PATH --version | head -n 1
echo ""

# Verify Artisan
echo -e "${YELLOW}Verifying Laravel installation...${NC}"
if [ ! -f "$ARTISAN_PATH" ]; then
    echo -e "${RED}ERROR: Artisan not found at: $ARTISAN_PATH${NC}"
    exit 1
fi
echo -e "${GREEN}  Artisan Path: $ARTISAN_PATH${NC}"
echo ""

# Detect web server user
echo -e "${YELLOW}Detecting web server user...${NC}"
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
else
    WEB_USER=$(ps aux | grep -E 'apache|httpd|nginx|php-fpm' | grep -v grep | head -n 1 | awk '{print $1}')
    if [ -z "$WEB_USER" ]; then
        WEB_USER="www-data"
        echo -e "${YELLOW}  Warning: Could not detect web user, defaulting to www-data${NC}"
        echo -e "${YELLOW}  You may need to update the service file manually${NC}"
    fi
fi
echo -e "${GREEN}  Web User: $WEB_USER${NC}"
echo ""

# Create service file with correct paths
echo -e "${YELLOW}Creating systemd service file...${NC}"
cat > /tmp/$SERVICE_FILE << EOF
[Unit]
Description=BMS Attendance Sync Service
Documentation=https://laravel.com
After=network.target mysql.service

[Service]
Type=simple
User=$WEB_USER
Group=$WEB_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=$PHP_PATH $ARTISAN_PATH attendance:sync-service --interval=120 --minutes=5
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=attendance-sync

# Security settings
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=$PROJECT_DIR/storage $PROJECT_DIR/bootstrap/cache

# Resource limits
LimitNOFILE=65536
MemoryLimit=512M

[Install]
WantedBy=multi-user.target
EOF

echo -e "${GREEN}Service file created${NC}"
echo ""

# Check if service already exists
if systemctl list-unit-files | grep -q "^$SERVICE_NAME.service"; then
    echo -e "${YELLOW}Service already exists. Stopping and removing...${NC}"
    systemctl stop $SERVICE_NAME.service 2>/dev/null || true
    systemctl disable $SERVICE_NAME.service 2>/dev/null || true
    rm -f $SYSTEMD_DIR/$SERVICE_NAME.service
    echo -e "${GREEN}Old service removed${NC}"
    echo ""
fi

# Copy service file to systemd directory
echo -e "${YELLOW}Installing service file...${NC}"
cp /tmp/$SERVICE_FILE $SYSTEMD_DIR/$SERVICE_NAME.service
chmod 644 $SYSTEMD_DIR/$SERVICE_NAME.service
echo -e "${GREEN}Service file installed to $SYSTEMD_DIR/$SERVICE_NAME.service${NC}"
echo ""

# Set proper permissions on project directory
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R $WEB_USER:$WEB_USER $PROJECT_DIR/storage $PROJECT_DIR/bootstrap/cache
chmod -R 775 $PROJECT_DIR/storage $PROJECT_DIR/bootstrap/cache
echo -e "${GREEN}Permissions set${NC}"
echo ""

# Reload systemd
echo -e "${YELLOW}Reloading systemd daemon...${NC}"
systemctl daemon-reload
echo -e "${GREEN}Systemd reloaded${NC}"
echo ""

# Enable service
echo -e "${YELLOW}Enabling service to start on boot...${NC}"
systemctl enable $SERVICE_NAME.service
echo -e "${GREEN}Service enabled${NC}"
echo ""

# Start the service
echo -e "${YELLOW}Starting service...${NC}"
systemctl start $SERVICE_NAME.service
sleep 2

# Check service status
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""
systemctl status $SERVICE_NAME.service --no-pager -l || true
echo ""

echo -e "${CYAN}Service Management Commands:${NC}"
echo -e "  ${GREEN}Start:${NC}   sudo systemctl start $SERVICE_NAME"
echo -e "  ${GREEN}Stop:${NC}    sudo systemctl stop $SERVICE_NAME"
echo -e "  ${GREEN}Restart:${NC} sudo systemctl restart $SERVICE_NAME"
echo -e "  ${GREEN}Status:${NC}  sudo systemctl status $SERVICE_NAME"
echo -e "  ${GREEN}Enable:${NC}  sudo systemctl enable $SERVICE_NAME"
echo -e "  ${GREEN}Disable:${NC} sudo systemctl disable $SERVICE_NAME"
echo -e "  ${GREEN}Logs:${NC}    sudo journalctl -u $SERVICE_NAME -f"
echo ""

echo -e "${CYAN}View Logs:${NC}"
echo -e "  ${GREEN}Live logs:${NC}    sudo journalctl -u $SERVICE_NAME -f"
echo -e "  ${GREEN}Last 50 lines:${NC} sudo journalctl -u $SERVICE_NAME -n 50"
echo -e "  ${GREEN}Laravel logs:${NC} tail -f $PROJECT_DIR/storage/logs/laravel.log"
echo ""

# Cleanup
rm -f /tmp/$SERVICE_FILE

echo -e "${GREEN}Service is installed and running!${NC}"
echo ""

