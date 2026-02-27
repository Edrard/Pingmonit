#!/bin/bash

# PingMonit Log Rotation Setup Script
# This script sets up logrotate for PingMonit logs

# Configuration
LOG_DIR="/path/to/pingmonit/logs"
LOGROTATE_CONF="/etc/logrotate.d/pingmonit"
SERVICE_NAME="pingmonit"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}PingMonit Log Rotation Setup${NC}"
echo "================================"

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root${NC}"
   echo "Please run: sudo $0"
   exit 1
fi

# Check if log directory exists
if [[ ! -d "$LOG_DIR" ]]; then
    echo -e "${YELLOW}Warning: Log directory not found: $LOG_DIR${NC}"
    echo "Please update LOG_DIR variable in this script"
    exit 1
fi

# Install logrotate configuration
echo "Installing logrotate configuration..."
cp "$(dirname "$0")/logrotate.conf" "$LOGROTATE_CONF"

# Set correct permissions
chmod 644 "$LOGROTATE_CONF"

# Test configuration
echo "Testing logrotate configuration..."
if logrotate -d "$LOGROTATE_CONF"; then
    echo -e "${GREEN}✓ Logrotate configuration is valid${NC}"
else
    echo -e "${RED}✗ Logrotate configuration has errors${NC}"
    exit 1
fi

# Run initial rotation (dry run)
echo "Running initial logrotate (dry run)..."
logrotate -f "$LOGROTATE_CONF"

echo -e "${GREEN}✓ Log rotation setup completed!${NC}"
echo ""
echo "Configuration details:"
echo "- Log directory: $LOG_DIR"
echo "- Retention: 30 days"
echo "- Rotation: Daily"
echo "- Compression: Enabled"
echo ""
echo "To test manually: logrotate -f /etc/logrotate.d/pingmonit"
echo "To force rotation: logrotate -f /etc/logrotate.d/pingmonit --force"
