#!/bin/bash

# Frontend Preview Script
# This script starts a development server for frontend preview

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed. Please install PHP first."
    echo "On macOS, you can install it using: brew install php"
    exit 1
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed. Please install Composer first."
    echo "On macOS, you can install it using: brew install composer"
    exit 1
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "Error: Node.js is not installed. Please install Node.js first."
    echo "On macOS, you can install it using: brew install node"
    exit 1
fi

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    echo "Installing PHP dependencies..."
    composer install
fi

if [ ! -d "node_modules" ]; then
    echo "Installing Node.js dependencies..."
    npm install
fi

# Build frontend assets
echo "Building frontend assets..."
npm run production
npm run build:tailwind

# Start the development server
echo "Starting development server..."
echo "Preview available at: http://localhost:8000"
echo "Press Ctrl+C to stop the server"

# Start PHP development server with specific configuration for frontend development
php -S localhost:8000 -t public 