#!/bin/bash
echo "Starting yoSSO on http://localhost:8001"
# Ensure data directory exists
mkdir -p data
if [ ! -f data/users.json ]; then
    echo '{"admin": {"password": "password", "name": "Admin User"}}' > data/users.json
fi
if [ ! -f data/codes.json ]; then
    echo '{}' > data/codes.json
fi
php -S 0.0.0.0:8001
