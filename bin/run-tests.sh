#!/bin/bash

# WP Employee Leaves Plugin - Test Runner Script
# This script runs all tests for the plugin

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

echo -e "${BLUE}WP Employee Leaves Plugin - Test Runner${NC}"
echo "========================================"
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

# Check if we're in a WordPress environment
check_wordpress_environment() {
    print_section "Checking WordPress Environment"
    
    if [ ! -f "wp-employee-leaves.php" ]; then
        print_error "Plugin main file not found. Please run from plugin directory."
        exit 1
    fi
    
    # Check for WordPress installation
    WP_ROOT=""
    CURRENT_DIR="$PLUGIN_DIR"
    
    # Go up directories to find WordPress installation
    while [ "$CURRENT_DIR" != "/" ]; do
        if [ -f "$CURRENT_DIR/wp-config.php" ] || [ -f "$CURRENT_DIR/wp-load.php" ]; then
            WP_ROOT="$CURRENT_DIR"
            break
        fi
        CURRENT_DIR="$(dirname "$CURRENT_DIR")"
    done
    
    if [ -z "$WP_ROOT" ]; then
        print_error "WordPress installation not found"
        print_error "Please ensure this plugin is in a WordPress installation"
        exit 1
    fi
    
    print_success "WordPress installation found at: $WP_ROOT"
    export WP_ROOT
}

# Setup test environment
setup_test_environment() {
    print_section "Setting Up Test Environment"
    
    # Check for required tools
    if ! command -v php &> /dev/null; then
        print_error "PHP is required but not installed"
        exit 1
    fi
    
    if ! command -v composer &> /dev/null; then
        print_error "Composer is required but not installed"
        print_error "Please install Composer: https://getcomposer.org/"
        exit 1
    fi
    
    print_success "PHP found: $(php --version | head -n1)"
    print_success "Composer found: $(composer --version | head -n1)"
    
    # Install dependencies if composer.json exists
    if [ -f "composer.json" ]; then
        print_section "Installing PHP Dependencies"
        composer install --no-interaction --prefer-dist
        print_success "PHP dependencies installed"
    fi
    
    # Install Node.js dependencies if package.json exists
    if [ -f "package.json" ]; then
        print_section "Installing Node.js Dependencies"
        
        if command -v npm &> /dev/null; then
            npm install
            print_success "Node.js dependencies installed"
        elif command -v yarn &> /dev/null; then
            yarn install
            print_success "Node.js dependencies installed (yarn)"
        else
            print_error "npm or yarn is required for JavaScript tests"
            print_error "Skipping JavaScript tests..."
            SKIP_JS_TESTS=1
        fi
    fi
}

# Setup WordPress test environment
setup_wordpress_tests() {
    print_section "Setting Up WordPress Test Environment"
    
    # Set default test directory
    WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
    
    if [ ! -d "$WP_TESTS_DIR" ]; then
        print_section "Installing WordPress Test Suite"
        
        # Create temporary directory
        mkdir -p "$(dirname "$WP_TESTS_DIR")"
        
        # Download WordPress test suite
        git clone --depth=1 --branch=trunk https://github.com/WordPress/wordpress-develop.git "$WP_TESTS_DIR"
        
        if [ $? -eq 0 ]; then
            print_success "WordPress test suite installed at: $WP_TESTS_DIR"
        else
            print_error "Failed to install WordPress test suite"
            exit 1
        fi
    else
        print_success "WordPress test suite found at: $WP_TESTS_DIR"
    fi
    
    export WP_TESTS_DIR
}

# Run PHPUnit tests
run_phpunit_tests() {
    print_section "Running PHPUnit Tests"
    
    if [ ! -f "phpunit.xml" ]; then
        print_error "phpunit.xml not found"
        return 1
    fi
    
    # Check if PHPUnit is available
    if command -v phpunit &> /dev/null; then
        PHPUNIT_CMD="phpunit"
    elif [ -f "vendor/bin/phpunit" ]; then
        PHPUNIT_CMD="vendor/bin/phpunit"
    else
        print_error "PHPUnit not found. Please install PHPUnit or run 'composer install'"
        return 1
    fi
    
    echo "Running PHPUnit tests..."
    
    # Run tests with coverage if possible
    if $PHPUNIT_CMD --coverage-text --coverage-html tests/coverage tests/; then
        print_success "PHPUnit tests passed"
        return 0
    else
        print_error "PHPUnit tests failed"
        return 1
    fi
}

# Run JavaScript tests
run_javascript_tests() {
    if [ "$SKIP_JS_TESTS" = "1" ]; then
        print_section "Skipping JavaScript Tests"
        return 0
    fi
    
    print_section "Running JavaScript Tests"
    
    if [ ! -f "package.json" ]; then
        print_error "package.json not found"
        return 1
    fi
    
    # Check if Jest is available
    if command -v npm &> /dev/null; then
        echo "Running Jest tests..."
        if npm test; then
            print_success "JavaScript tests passed"
            return 0
        else
            print_error "JavaScript tests failed"
            return 1
        fi
    elif command -v yarn &> /dev/null; then
        echo "Running Jest tests..."
        if yarn test; then
            print_success "JavaScript tests passed"
            return 0
        else
            print_error "JavaScript tests failed"
            return 1
        fi
    else
        print_error "npm or yarn not found"
        return 1
    fi
}

# Run security checks
run_security_checks() {
    print_section "Running Security Checks"
    
    # Check for common security issues
    echo "Checking for hardcoded secrets..."
    
    # Look for potential secrets
    SECRET_PATTERNS=("password" "secret" "api_key" "private_key" "access_token")
    FOUND_ISSUES=0
    
    for pattern in "${SECRET_PATTERNS[@]}"; do
        if grep -r -i "$pattern" --include="*.php" --include="*.js" . --exclude-dir=tests --exclude-dir=node_modules --exclude-dir=vendor; then
            print_error "Found potential secret: $pattern"
            FOUND_ISSUES=1
        fi
    done
    
    # Check for XSS vulnerabilities
    echo "Checking for potential XSS vulnerabilities..."
    if grep -r "echo \$_" --include="*.php" . --exclude-dir=tests --exclude-dir=node_modules --exclude-dir=vendor; then
        print_error "Found potential XSS vulnerability: unescaped echo"
        FOUND_ISSUES=1
    fi
    
    # Check for SQL injection vulnerabilities
    echo "Checking for potential SQL injection vulnerabilities..."
    if grep -r "\$wpdb->query.*\$_" --include="*.php" . --exclude-dir=tests --exclude-dir=node_modules --exclude-dir=vendor; then
        print_error "Found potential SQL injection vulnerability"
        FOUND_ISSUES=1
    fi
    
    if [ $FOUND_ISSUES -eq 0 ]; then
        print_success "No obvious security issues found"
        return 0
    else
        print_error "Security issues found - please review"
        return 1
    fi
}

# Run code quality checks
run_code_quality_checks() {
    print_section "Running Code Quality Checks"
    
    # Check PHP syntax
    echo "Checking PHP syntax..."
    find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -exec php -l {} \; | grep -v "No syntax errors detected"
    
    if [ ${PIPESTATUS[0]} -eq 0 ]; then
        print_success "PHP syntax check passed"
    else
        print_error "PHP syntax errors found"
        return 1
    fi
    
    # Check for WordPress coding standards if PHPCS is available
    if command -v phpcs &> /dev/null; then
        echo "Checking WordPress coding standards..."
        if phpcs --standard=WordPress --ignore=vendor/,node_modules/,tests/ .; then
            print_success "WordPress coding standards check passed"
        else
            print_error "WordPress coding standards violations found"
            return 1
        fi
    else
        echo "PHPCS not found, skipping coding standards check"
    fi
    
    return 0
}

# Generate test report
generate_test_report() {
    print_section "Generating Test Report"
    
    REPORT_DIR="tests/reports"
    mkdir -p "$REPORT_DIR"
    
    REPORT_FILE="$REPORT_DIR/test-report-$(date +%Y%m%d-%H%M%S).txt"
    
    {
        echo "WP Employee Leaves Plugin - Test Report"
        echo "Generated: $(date)"
        echo "Plugin Version: 1.3.0"
        echo "WordPress Environment: $WP_ROOT"
        echo ""
        echo "Test Results:"
        echo "- PHPUnit Tests: $PHPUNIT_RESULT"
        echo "- JavaScript Tests: $JS_RESULT"
        echo "- Security Checks: $SECURITY_RESULT"
        echo "- Code Quality: $QUALITY_RESULT"
        echo ""
        echo "Coverage Reports:"
        if [ -d "tests/coverage" ]; then
            echo "- PHP Coverage: tests/coverage/index.html"
        fi
        if [ -d "tests/coverage/js" ]; then
            echo "- JavaScript Coverage: tests/coverage/js/index.html"
        fi
    } > "$REPORT_FILE"
    
    print_success "Test report generated: $REPORT_FILE"
}

# Main execution
main() {
    echo "Starting test suite..."
    echo ""
    
    # Initialize result variables
    PHPUNIT_RESULT="SKIPPED"
    JS_RESULT="SKIPPED"
    SECURITY_RESULT="SKIPPED"
    QUALITY_RESULT="SKIPPED"
    
    # Check environment
    check_wordpress_environment
    setup_test_environment
    setup_wordpress_tests
    
    echo ""
    
    # Run tests
    TOTAL_FAILURES=0
    
    if run_phpunit_tests; then
        PHPUNIT_RESULT="PASSED"
    else
        PHPUNIT_RESULT="FAILED"
        TOTAL_FAILURES=$((TOTAL_FAILURES + 1))
    fi
    
    echo ""
    
    if run_javascript_tests; then
        JS_RESULT="PASSED"
    else
        JS_RESULT="FAILED"
        TOTAL_FAILURES=$((TOTAL_FAILURES + 1))
    fi
    
    echo ""
    
    if run_security_checks; then
        SECURITY_RESULT="PASSED"
    else
        SECURITY_RESULT="FAILED"
        TOTAL_FAILURES=$((TOTAL_FAILURES + 1))
    fi
    
    echo ""
    
    if run_code_quality_checks; then
        QUALITY_RESULT="PASSED"
    else
        QUALITY_RESULT="FAILED"
        TOTAL_FAILURES=$((TOTAL_FAILURES + 1))
    fi
    
    echo ""
    
    # Generate report
    generate_test_report
    
    echo ""
    print_section "Test Summary"
    echo "PHPUnit Tests: $PHPUNIT_RESULT"
    echo "JavaScript Tests: $JS_RESULT"
    echo "Security Checks: $SECURITY_RESULT"
    echo "Code Quality: $QUALITY_RESULT"
    echo ""
    
    if [ $TOTAL_FAILURES -eq 0 ]; then
        print_success "All tests passed!"
        exit 0
    else
        print_error "Tests failed ($TOTAL_FAILURES failure(s))"
        exit 1
    fi
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-js)
            SKIP_JS_TESTS=1
            shift
            ;;
        --phpunit-only)
            PHPUNIT_ONLY=1
            shift
            ;;
        --js-only)
            JS_ONLY=1
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-js       Skip JavaScript tests"
            echo "  --phpunit-only  Run only PHPUnit tests"
            echo "  --js-only       Run only JavaScript tests"
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