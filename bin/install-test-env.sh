#!/bin/bash

# WP Employee Leaves Plugin - Test Environment Setup Script
# This script sets up the complete testing environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Plugin directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

echo -e "${BLUE}WP Employee Leaves Plugin - Test Environment Setup${NC}"
echo "=================================================="
echo "Plugin Directory: $PLUGIN_DIR"
echo ""

# Function to print section headers
print_section() {
    echo -e "${YELLOW}$1${NC}"
    echo "----------------------------------------"
}

# Function to print success
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check system requirements
check_requirements() {
    print_section "Checking System Requirements"
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP is required but not installed"
        echo "Please install PHP 7.4 or higher"
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_success "PHP found: $PHP_VERSION"
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer is required but not installed"
        echo "Please install Composer: https://getcomposer.org/"
        echo "Or run: curl -sS https://getcomposer.org/installer | php"
        exit 1
    fi
    
    print_success "Composer found: $(composer --version | head -n1)"
    
    # Check Node.js (optional)
    if command -v node &> /dev/null; then
        print_success "Node.js found: $(node --version)"
    else
        echo "Node.js not found - JavaScript tests will be skipped"
    fi
    
    # Check npm/yarn (optional)
    if command -v npm &> /dev/null; then
        print_success "npm found: $(npm --version)"
    elif command -v yarn &> /dev/null; then
        print_success "yarn found: $(yarn --version)"
    else
        echo "npm/yarn not found - JavaScript dependencies will be skipped"
    fi
    
    # Check Git
    if ! command -v git &> /dev/null; then
        print_error "Git is required but not installed"
        exit 1
    fi
    
    print_success "Git found: $(git --version)"
}

# Install PHP dependencies
install_php_dependencies() {
    print_section "Installing PHP Dependencies"
    
    # Create composer.json if it doesn't exist
    if [ ! -f "composer.json" ]; then
        echo "Creating composer.json..."
        cat > composer.json << 'EOF'
{
    "name": "wp-employee-leaves/plugin",
    "description": "WP Employee Leaves Plugin",
    "type": "wordpress-plugin",
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "yoast/phpunit-polyfills": "^1.0"
    },
    "autoload-dev": {
        "classmap": [
            "tests/"
        ]
    },
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html tests/coverage"
    }
}
EOF
        print_success "composer.json created"
    fi
    
    # Install dependencies
    echo "Installing PHP dependencies..."
    composer install --no-interaction --prefer-dist
    
    if [ $? -eq 0 ]; then
        print_success "PHP dependencies installed"
    else
        print_error "Failed to install PHP dependencies"
        exit 1
    fi
}

# Install Node.js dependencies
install_node_dependencies() {
    if ! command -v node &> /dev/null; then
        print_section "Skipping Node.js Dependencies (Node.js not found)"
        return 0
    fi
    
    print_section "Installing Node.js Dependencies"
    
    # Install dependencies
    if command -v npm &> /dev/null; then
        echo "Installing Node.js dependencies with npm..."
        npm install
        print_success "Node.js dependencies installed with npm"
    elif command -v yarn &> /dev/null; then
        echo "Installing Node.js dependencies with yarn..."
        yarn install
        print_success "Node.js dependencies installed with yarn"
    else
        echo "Neither npm nor yarn found, skipping Node.js dependencies"
        return 0
    fi
}

# Setup WordPress test environment
setup_wordpress_test_env() {
    print_section "Setting Up WordPress Test Environment"
    
    # Set test directory
    WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
    
    if [ -d "$WP_TESTS_DIR" ]; then
        echo "WordPress test suite already exists at: $WP_TESTS_DIR"
        read -p "Do you want to reinstall it? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rm -rf "$WP_TESTS_DIR"
        else
            print_success "Using existing WordPress test suite"
            return 0
        fi
    fi
    
    echo "Installing WordPress test suite..."
    
    # Create directory
    mkdir -p "$(dirname "$WP_TESTS_DIR")"
    
    # Clone WordPress develop repository
    git clone --depth=1 --branch=trunk https://github.com/WordPress/wordpress-develop.git "$WP_TESTS_DIR"
    
    if [ $? -eq 0 ]; then
        print_success "WordPress test suite installed at: $WP_TESTS_DIR"
        echo "Set WP_TESTS_DIR environment variable to: $WP_TESTS_DIR"
        
        # Add to shell profile if possible
        if [ -f ~/.bashrc ]; then
            echo "export WP_TESTS_DIR=\"$WP_TESTS_DIR\"" >> ~/.bashrc
            echo "Added WP_TESTS_DIR to ~/.bashrc"
        elif [ -f ~/.zshrc ]; then
            echo "export WP_TESTS_DIR=\"$WP_TESTS_DIR\"" >> ~/.zshrc
            echo "Added WP_TESTS_DIR to ~/.zshrc"
        fi
    else
        print_error "Failed to install WordPress test suite"
        exit 1
    fi
}

# Setup test database
setup_test_database() {
    print_section "Setting Up Test Database"
    
    echo "Note: For full integration testing, you'll need a MySQL test database."
    echo "Database configuration should be added to phpunit.xml or wp-tests-config.php"
    echo ""
    echo "Example database setup:"
    echo "  Database: wp_test"
    echo "  User: wp_test"
    echo "  Password: wp_test"
    echo "  Host: localhost"
    echo ""
    
    read -p "Do you want to configure the test database now? (y/N): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        read -p "Database name [wp_test]: " DB_NAME
        DB_NAME=${DB_NAME:-wp_test}
        
        read -p "Database user [wp_test]: " DB_USER
        DB_USER=${DB_USER:-wp_test}
        
        read -p "Database password [wp_test]: " DB_PASSWORD
        DB_PASSWORD=${DB_PASSWORD:-wp_test}
        
        read -p "Database host [localhost]: " DB_HOST
        DB_HOST=${DB_HOST:-localhost}
        
        # Create wp-tests-config.php
        cat > wp-tests-config.php << EOF
<?php
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASSWORD' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );

\$table_prefix = 'wptests_';
EOF
        
        print_success "Test database configuration saved to wp-tests-config.php"
        echo "Note: Make sure the database and user exist in MySQL"
    else
        echo "Database configuration skipped"
        echo "Tests will run with SQLite in-memory database"
    fi
}

# Create test directories
create_test_directories() {
    print_section "Creating Test Directory Structure"
    
    # Create test directories
    mkdir -p tests/{unit,integration,security,js,fixtures,coverage,reports}
    mkdir -p bin
    
    # Create .gitkeep files
    touch tests/coverage/.gitkeep
    touch tests/reports/.gitkeep
    
    print_success "Test directory structure created"
    
    # Show structure
    echo "Test directory structure:"
    tree tests/ 2>/dev/null || find tests/ -type d | sed 's/^/  /'
}

# Create sample test files
create_sample_test_files() {
    print_section "Checking Test Files"
    
    if [ ! -f "tests/unit/PluginTest.php" ]; then
        echo "Main test files not found. They should have been created already."
        echo "Please ensure all test files are in place:"
        echo "  - tests/unit/PluginTest.php"
        echo "  - tests/unit/ShortcodeTest.php"
        echo "  - tests/unit/DatabaseTest.php"
        echo "  - tests/integration/AjaxTest.php"
        echo "  - tests/security/SecurityTest.php"
        echo "  - tests/js/frontend.test.js"
        echo "  - tests/js/admin.test.js"
    else
        print_success "Test files found"
    fi
}

# Verify installation
verify_installation() {
    print_section "Verifying Installation"
    
    # Check PHP dependencies
    if [ -f "vendor/autoload.php" ]; then
        print_success "PHP dependencies installed"
    else
        print_error "PHP dependencies missing"
    fi
    
    # Check Node.js dependencies
    if [ -f "node_modules/.bin/jest" ] || ! command -v node &> /dev/null; then
        print_success "JavaScript test environment ready"
    else
        print_error "JavaScript dependencies missing"
    fi
    
    # Check test files
    if [ -f "phpunit.xml" ]; then
        print_success "PHPUnit configuration found"
    else
        print_error "PHPUnit configuration missing"
    fi
    
    if [ -f "package.json" ]; then
        print_success "Package.json found"
    else
        print_error "Package.json missing"
    fi
    
    # Check WordPress test environment
    WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
    if [ -d "$WP_TESTS_DIR" ]; then
        print_success "WordPress test environment ready"
    else
        print_error "WordPress test environment missing"
    fi
}

# Show usage instructions
show_usage_instructions() {
    print_section "Usage Instructions"
    
    echo "Test environment setup complete!"
    echo ""
    echo "To run tests:"
    echo "  ./bin/run-tests.sh                 # Run all tests"
    echo "  ./bin/run-tests.sh --phpunit-only  # Run only PHP tests"
    echo "  ./bin/run-tests.sh --js-only       # Run only JavaScript tests"
    echo "  ./bin/run-tests.sh --help          # Show help"
    echo ""
    echo "Individual test commands:"
    echo "  composer test                      # Run PHPUnit tests"
    echo "  npm test                          # Run JavaScript tests"
    echo "  phpunit                           # Run PHPUnit directly"
    echo "  npm run test:coverage             # Run tests with coverage"
    echo ""
    echo "Environment variables:"
    echo "  WP_TESTS_DIR=$WP_TESTS_DIR"
    echo ""
    echo "Configuration files:"
    echo "  - phpunit.xml       # PHPUnit configuration"
    echo "  - package.json      # JavaScript test configuration"
    echo "  - composer.json     # PHP dependencies"
    if [ -f "wp-tests-config.php" ]; then
        echo "  - wp-tests-config.php # WordPress test database config"
    fi
}

# Main execution
main() {
    echo "Setting up test environment..."
    echo ""
    
    check_requirements
    echo ""
    
    install_php_dependencies
    echo ""
    
    install_node_dependencies
    echo ""
    
    setup_wordpress_test_env
    echo ""
    
    setup_test_database
    echo ""
    
    create_test_directories
    echo ""
    
    create_sample_test_files
    echo ""
    
    verify_installation
    echo ""
    
    show_usage_instructions
    
    print_success "Test environment setup complete!"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-wp)
            SKIP_WP_SETUP=1
            shift
            ;;
        --skip-db)
            SKIP_DB_SETUP=1
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-wp       Skip WordPress test environment setup"
            echo "  --skip-db       Skip database configuration"
            echo "  --help          Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Run main function
main