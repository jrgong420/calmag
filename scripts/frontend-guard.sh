#!/bin/bash

# Frontend Guard Script
# This script helps enforce frontend-only development rules

# Load configuration
CONFIG_FILE=".frontendrc"
if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: .frontendrc configuration file not found"
    exit 1
fi

# Function to check if a file is in a protected directory
check_protected() {
    local file="$1"
    local protected_dirs=$(jq -r '.frontend.protected_directories[]' "$CONFIG_FILE")
    
    for dir in $protected_dirs; do
        if [[ "$file" == "$dir"* ]]; then
            echo "Error: Attempting to modify protected file: $file"
            echo "This file is part of the backend logic and should not be modified during frontend development."
            exit 1
        fi
    done
}

# Function to check if a file is in a safe directory
check_safe() {
    local file="$1"
    local safe_dirs=$(jq -r '.frontend.safe_directories[]' "$CONFIG_FILE")
    
    for dir in $safe_dirs; do
        if [[ "$file" == "$dir"* ]]; then
            return 0
        fi
    done
    return 1
}

# Function to check file extension
check_extension() {
    local file="$1"
    local type="$2"
    local allowed_extensions=$(jq -r ".frontend.development_rules.$type.allowed_extensions[]" "$CONFIG_FILE")
    
    for ext in $allowed_extensions; do
        if [[ "$file" == *"$ext" ]]; then
            return 0
        fi
    done
    return 1
}

# Main function to process git changes
process_changes() {
    # Get list of changed files
    local changed_files=$(git diff --cached --name-only)
    
    for file in $changed_files; do
        # Skip if file doesn't exist
        if [ ! -f "$file" ]; then
            continue
        fi
        
        # Check if file is protected
        check_protected "$file"
        
        # Determine file type
        if [[ "$file" == *.css || "$file" == *.scss || "$file" == *.sass ]]; then
            check_extension "$file" "css"
        elif [[ "$file" == *.js ]]; then
            check_extension "$file" "js"
        elif [[ "$file" == *.php || "$file" == *.html ]]; then
            check_extension "$file" "views"
        elif [[ "$file" == *.png || "$file" == *.jpg || "$file" == *.jpeg || "$file" == *.gif || "$file" == *.svg || "$file" == *.woff || "$file" == *.woff2 || "$file" == *.ttf || "$file" == *.eot ]]; then
            check_extension "$file" "assets"
        fi
        
        # Check if file is in safe directory
        if ! check_safe "$file"; then
            echo "Warning: File $file is not in a designated frontend directory"
            echo "Please ensure this file should be modified during frontend development."
        fi
    done
}

# Run the check
process_changes

# If we get here, all checks passed
echo "Frontend development rules check passed successfully!" 