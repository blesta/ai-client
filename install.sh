#!/bin/bash

# Blesta AI PHP Client - Installation Script
# This script helps you quickly set up the library for development/testing

echo "================================"
echo "Blesta AI PHP Client Installer"
echo "================================"
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Error: Composer is not installed."
    echo "Please install Composer from https://getcomposer.org"
    exit 1
fi

echo "✓ Composer found"
echo ""

# Install dependencies
echo "Installing dependencies..."
composer install

if [ $? -eq 0 ]; then
    echo "✓ Dependencies installed successfully"
else
    echo "✗ Failed to install dependencies"
    exit 1
fi

echo ""
echo "================================"
echo "Installation Complete!"
echo "================================"
echo ""
echo "Next steps:"
echo "1. Get your API key from account.blesta.com"
echo "2. Update the examples with your API key"
echo "3. Run an example: php examples/chat_completion.php"
echo ""
echo "Documentation:"
echo "- README.md - Full API documentation"
echo "- USAGE.md - Blesta integration guide"
echo "- examples/ - Working code examples"
echo ""
echo "Happy coding!"
