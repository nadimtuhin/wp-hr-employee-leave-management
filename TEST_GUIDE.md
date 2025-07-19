# WP Employee Leaves Plugin - Testing Guide

This guide provides comprehensive instructions for testing the WP Employee Leaves plugin without manual testing.

## 🚀 Quick Start

### Prerequisites
- PHP 7.4 or higher
- Composer
- Node.js 16+ (for JavaScript tests)
- MySQL/MariaDB (for integration tests)

### Setup Test Environment
```bash
# Make scripts executable
chmod +x bin/*.sh

# Install test environment
./bin/install-test-env.sh

# Or use Make
make install
```

### Run All Tests
```bash
# Using the test runner script
./bin/run-tests.sh

# Or using Make
make test
```

## 📋 Test Categories

### 1. Unit Tests (PHP)
**Location**: `tests/unit/`

**What's Tested**:
- Plugin initialization and constants
- Database table creation
- Shortcode functionality
- Helper functions
- Data validation

**Run Command**:
```bash
make test-php
# OR
vendor/bin/phpunit tests/unit/
```

**Test Files**:
- `PluginTest.php` - Core plugin functionality
- `ShortcodeTest.php` - Shortcode rendering and security
- `DatabaseTest.php` - Database operations and integrity

### 2. Integration Tests (PHP)
**Location**: `tests/integration/`

**What's Tested**:
- AJAX handlers end-to-end
- Form submissions
- Database interactions
- WordPress integration

**Run Command**:
```bash
vendor/bin/phpunit tests/integration/
```

**Test Files**:
- `AjaxTest.php` - All AJAX endpoint testing

### 3. Security Tests (PHP)
**Location**: `tests/security/`

**What's Tested**:
- SQL injection protection
- XSS vulnerability prevention
- CSRF protection
- Input sanitization
- Authentication and authorization

**Run Command**:
```bash
make test-security
# OR
vendor/bin/phpunit tests/security/
```

**Test Files**:
- `SecurityTest.php` - Comprehensive security testing

### 4. JavaScript Tests
**Location**: `tests/js/`

**What's Tested**:
- Frontend form validation
- AJAX functionality
- Admin interface interactions
- Error handling
- XSS protection

**Run Command**:
```bash
make test-js
# OR
npm test
```

**Test Files**:
- `frontend.test.js` - Frontend JavaScript functionality
- `admin.test.js` - Admin JavaScript functionality

## 🛠 Available Commands

### Make Commands
```bash
make help              # Show all available commands
make install           # Install test environment
make test              # Run all tests
make test-php          # Run PHP tests only
make test-js           # Run JavaScript tests only
make test-security     # Run security tests only
make test-coverage     # Run tests with coverage
make test-watch        # Run tests in watch mode
make lint              # Run code linting
make clean             # Clean test artifacts
make serve-coverage    # Serve coverage reports
```

### Script Commands
```bash
./bin/run-tests.sh                 # Run all tests
./bin/run-tests.sh --phpunit-only  # PHP tests only
./bin/run-tests.sh --js-only       # JavaScript tests only
./bin/run-tests.sh --skip-js       # Skip JavaScript tests
./bin/install-test-env.sh          # Setup test environment
```

### NPM Commands
```bash
npm test               # Run JavaScript tests
npm run test:watch     # Run tests in watch mode
npm run test:coverage  # Run with coverage
```

### Composer Commands
```bash
composer test          # Run PHPUnit tests
composer test-coverage # Run with coverage
```

## 📊 Coverage Reports

### Generate Coverage Reports
```bash
make test-coverage
```

### View Coverage Reports
- **PHP Coverage**: `tests/coverage/php/index.html`
- **JavaScript Coverage**: `tests/coverage/js/index.html`

### Serve Coverage Reports
```bash
make serve-coverage
# Opens PHP coverage at http://localhost:8080
```

## 🧪 Test Structure

```
tests/
├── bootstrap.php           # Test bootstrap
├── fixtures/              # Test data and helpers
│   ├── TestDataFactory.php
│   └── MockWPUser.php
├── unit/                  # Unit tests
│   ├── PluginTest.php
│   ├── ShortcodeTest.php
│   └── DatabaseTest.php
├── integration/           # Integration tests
│   └── AjaxTest.php
├── security/             # Security tests
│   └── SecurityTest.php
├── js/                   # JavaScript tests
│   ├── setup.js
│   ├── frontend.test.js
│   └── admin.test.js
├── coverage/             # Coverage reports
└── reports/              # Test reports
```

## 🔧 Configuration Files

- `phpunit.xml` - PHPUnit configuration
- `package.json` - JavaScript test configuration
- `composer.json` - PHP dependencies
- `Makefile` - Build automation

## 🐛 Troubleshooting

### Common Issues

**1. WordPress Test Suite Not Found**
```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
git clone --depth=1 --branch=trunk https://github.com/WordPress/wordpress-develop.git $WP_TESTS_DIR
```

**2. Database Connection Issues**
- Ensure MySQL/MariaDB is running
- Check database credentials in `wp-tests-config.php`
- Create test database: `CREATE DATABASE wp_test;`

**3. Permission Issues**
```bash
chmod +x bin/*.sh
```

**4. Missing Dependencies**
```bash
composer install
npm install
```

### Test Environment Variables
```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_TESTS_DOMAIN=example.org
export WP_TESTS_EMAIL=admin@example.org
export WP_TESTS_TITLE="Test Blog"
```

## 📈 Test Metrics

### What Gets Tested

#### Core Functionality ✅
- Plugin activation/deactivation
- Database table creation
- Leave type management
- Request submission
- Approval/rejection workflow
- Email notifications
- Shortcode rendering

#### Security ✅
- CSRF protection (nonces)
- SQL injection prevention
- XSS protection
- Input sanitization
- Authentication checks
- Authorization validation

#### Integration ✅
- AJAX endpoints
- WordPress hooks
- Database operations
- Email functionality
- File operations

#### JavaScript ✅
- Form validation
- AJAX requests
- UI interactions
- Error handling
- Security escaping

### Test Coverage Goals
- **PHP Code**: >90%
- **JavaScript Code**: >85%
- **Security Tests**: 100% of vulnerabilities
- **Integration Tests**: All AJAX endpoints

## 📝 Writing New Tests

### Adding PHP Tests
1. Create test file in appropriate directory
2. Extend `WP_UnitTestCase` or `WP_Ajax_UnitTestCase`
3. Use `WP_Employee_Leaves_Test_Data_Factory` for test data
4. Follow existing test patterns

### Adding JavaScript Tests
1. Create `.test.js` file in `tests/js/`
2. Use Jest testing framework
3. Mock WordPress globals via `setup.js`
4. Test both success and error scenarios

### Test Data Factory Usage
```php
// Create test user
$user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();

// Create leave types
$leave_type_ids = WP_Employee_Leaves_Test_Data_Factory::create_leave_types();

// Create leave request
$request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($user_id);

// Clean up
WP_Employee_Leaves_Test_Data_Factory::cleanup();
```

## 🚀 Automated Testing Workflow

1. **Setup**: `make install`
2. **Development**: `make test-watch` (for continuous testing)
3. **Pre-commit**: `make test` (run all tests)
4. **Coverage**: `make test-coverage` (generate reports)
5. **Quality**: `make lint` (code standards)

## 📋 Test Checklist

Before releasing:
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] All security tests pass
- [ ] JavaScript tests pass
- [ ] Code coverage >90%
- [ ] No security vulnerabilities
- [ ] Code passes linting
- [ ] Manual smoke test performed

---

**Happy Testing!** 🎉

For questions or issues, check the troubleshooting section or review test logs in `tests/reports/`.