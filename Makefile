# WP Employee Leaves Plugin - Makefile
# Provides convenient commands for testing and development

.PHONY: help install test test-php test-js test-security test-coverage clean lint

# Colors
YELLOW := \033[1;33m
GREEN := \033[0;32m
RED := \033[0;31m
NC := \033[0m # No Color

# Default target
help:
	@echo "$(YELLOW)WP Employee Leaves Plugin - Available Commands$(NC)"
	@echo "================================================"
	@echo ""
	@echo "Setup Commands:"
	@echo "  make install       Install test environment and dependencies"
	@echo "  make install-php   Install PHP dependencies only"
	@echo "  make install-js    Install JavaScript dependencies only"
	@echo ""
	@echo "Test Commands:"
	@echo "  make test          Run all tests"
	@echo "  make test-php      Run PHPUnit tests"
	@echo "  make test-js       Run JavaScript tests"
	@echo "  make test-security Run security tests"
	@echo "  make test-coverage Run tests with coverage report"
	@echo "  make test-watch    Run tests in watch mode"
	@echo ""
	@echo "Quality Commands:"
	@echo "  make lint          Run code linting"
	@echo "  make lint-php      Run PHP code standards check"
	@echo "  make lint-js       Run JavaScript linting"
	@echo "  make format        Auto-format code"
	@echo ""
	@echo "Utility Commands:"
	@echo "  make clean         Clean test artifacts and dependencies"
	@echo "  make clean-coverage Clean coverage reports"
	@echo "  make serve-coverage Serve coverage reports"
	@echo "  make report        Generate test report"
	@echo ""

# Installation targets
install:
	@echo "$(YELLOW)Installing test environment...$(NC)"
	./bin/install-test-env.sh
	@echo "$(GREEN)✓ Test environment installed$(NC)"

install-php:
	@echo "$(YELLOW)Installing PHP dependencies...$(NC)"
	composer install --no-interaction --prefer-dist
	@echo "$(GREEN)✓ PHP dependencies installed$(NC)"

install-js:
	@echo "$(YELLOW)Installing JavaScript dependencies...$(NC)"
	@if command -v npm >/dev/null 2>&1; then \
		npm install; \
	elif command -v yarn >/dev/null 2>&1; then \
		yarn install; \
	else \
		echo "$(RED)✗ npm or yarn not found$(NC)"; \
		exit 1; \
	fi
	@echo "$(GREEN)✓ JavaScript dependencies installed$(NC)"

# Test targets
test:
	@echo "$(YELLOW)Running all tests...$(NC)"
	./bin/run-tests.sh

test-php:
	@echo "$(YELLOW)Running PHPUnit tests...$(NC)"
	@if [ -f vendor/bin/phpunit ]; then \
		vendor/bin/phpunit; \
	elif command -v phpunit >/dev/null 2>&1; then \
		phpunit; \
	else \
		echo "$(RED)✗ PHPUnit not found. Run 'make install-php' first$(NC)"; \
		exit 1; \
	fi

test-js:
	@echo "$(YELLOW)Running JavaScript tests...$(NC)"
	@if [ -f package.json ]; then \
		if command -v npm >/dev/null 2>&1; then \
			npm test; \
		elif command -v yarn >/dev/null 2>&1; then \
			yarn test; \
		else \
			echo "$(RED)✗ npm or yarn not found$(NC)"; \
			exit 1; \
		fi; \
	else \
		echo "$(RED)✗ package.json not found$(NC)"; \
		exit 1; \
	fi

test-security:
	@echo "$(YELLOW)Running security tests...$(NC)"
	./bin/run-tests.sh --phpunit-only
	@echo "$(YELLOW)Checking for security vulnerabilities...$(NC)"
	@grep -r -i "password\|secret\|api_key" --include="*.php" --include="*.js" . --exclude-dir=tests --exclude-dir=node_modules --exclude-dir=vendor || echo "$(GREEN)✓ No obvious secrets found$(NC)"

test-coverage:
	@echo "$(YELLOW)Running tests with coverage...$(NC)"
	@if [ -f vendor/bin/phpunit ]; then \
		vendor/bin/phpunit --coverage-html tests/coverage/php --coverage-text; \
	else \
		echo "$(RED)✗ PHPUnit not found$(NC)"; \
		exit 1; \
	fi
	@if [ -f package.json ]; then \
		if command -v npm >/dev/null 2>&1; then \
			npm run test:coverage; \
		elif command -v yarn >/dev/null 2>&1; then \
			yarn test:coverage; \
		fi; \
	fi
	@echo "$(GREEN)✓ Coverage reports generated$(NC)"
	@echo "Open tests/coverage/php/index.html for PHP coverage"
	@echo "Open tests/coverage/js/index.html for JavaScript coverage"

test-watch:
	@echo "$(YELLOW)Running tests in watch mode...$(NC)"
	@if [ -f package.json ]; then \
		if command -v npm >/dev/null 2>&1; then \
			npm run test:watch; \
		elif command -v yarn >/dev/null 2>&1; then \
			yarn test:watch; \
		else \
			echo "$(RED)✗ npm or yarn not found$(NC)"; \
			exit 1; \
		fi; \
	else \
		echo "$(RED)✗ package.json not found$(NC)"; \
		exit 1; \
	fi

# Linting targets
lint: lint-php lint-js

lint-php:
	@echo "$(YELLOW)Running PHP linting...$(NC)"
	@find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -exec php -l {} \; | grep -v "No syntax errors detected" || echo "$(GREEN)✓ PHP syntax check passed$(NC)"
	@if command -v phpcs >/dev/null 2>&1; then \
		echo "Checking WordPress coding standards..."; \
		phpcs --standard=WordPress --ignore=vendor/,node_modules/,tests/ . || echo "$(RED)✗ Coding standards violations found$(NC)"; \
	else \
		echo "PHPCS not found, skipping coding standards check"; \
	fi

lint-js:
	@echo "$(YELLOW)Running JavaScript linting...$(NC)"
	@if [ -f package.json ] && command -v npm >/dev/null 2>&1; then \
		if npm list eslint >/dev/null 2>&1; then \
			npm run lint 2>/dev/null || echo "ESLint not configured"; \
		else \
			echo "ESLint not installed"; \
		fi; \
	else \
		echo "JavaScript linting skipped"; \
	fi

format:
	@echo "$(YELLOW)Auto-formatting code...$(NC)"
	@if command -v phpcbf >/dev/null 2>&1; then \
		echo "Formatting PHP code..."; \
		phpcbf --standard=WordPress --ignore=vendor/,node_modules/,tests/ . || echo "PHP formatting complete"; \
	fi
	@if [ -f package.json ] && command -v npm >/dev/null 2>&1; then \
		if npm list prettier >/dev/null 2>&1; then \
			echo "Formatting JavaScript code..."; \
			npm run format 2>/dev/null || echo "Prettier not configured"; \
		fi; \
	fi

# Utility targets
clean:
	@echo "$(YELLOW)Cleaning test artifacts...$(NC)"
	rm -rf vendor/
	rm -rf node_modules/
	rm -rf tests/coverage/
	rm -rf tests/reports/
	rm -f composer.lock
	rm -f package-lock.json
	rm -f yarn.lock
	@echo "$(GREEN)✓ Cleaned$(NC)"

clean-coverage:
	@echo "$(YELLOW)Cleaning coverage reports...$(NC)"
	rm -rf tests/coverage/
	@echo "$(GREEN)✓ Coverage reports cleaned$(NC)"

serve-coverage:
	@echo "$(YELLOW)Serving coverage reports...$(NC)"
	@if [ -d tests/coverage/php ]; then \
		echo "PHP coverage available at: http://localhost:8080"; \
		python3 -m http.server 8080 -d tests/coverage/php 2>/dev/null || python -m SimpleHTTPServer 8080 -d tests/coverage/php; \
	else \
		echo "$(RED)✗ No coverage reports found. Run 'make test-coverage' first$(NC)"; \
	fi

report:
	@echo "$(YELLOW)Generating test report...$(NC)"
	@mkdir -p tests/reports
	@echo "WP Employee Leaves Plugin - Test Report" > tests/reports/latest-report.txt
	@echo "Generated: $$(date)" >> tests/reports/latest-report.txt
	@echo "Plugin Version: 1.3.0" >> tests/reports/latest-report.txt
	@echo "" >> tests/reports/latest-report.txt
	@echo "Test Status:" >> tests/reports/latest-report.txt
	@if [ -f vendor/bin/phpunit ]; then \
		echo "- PHPUnit: Available" >> tests/reports/latest-report.txt; \
	else \
		echo "- PHPUnit: Not Available" >> tests/reports/latest-report.txt; \
	fi
	@if [ -f package.json ]; then \
		echo "- JavaScript Tests: Available" >> tests/reports/latest-report.txt; \
	else \
		echo "- JavaScript Tests: Not Available" >> tests/reports/latest-report.txt; \
	fi
	@echo "" >> tests/reports/latest-report.txt
	@echo "Coverage Reports:" >> tests/reports/latest-report.txt
	@if [ -d tests/coverage/php ]; then \
		echo "- PHP Coverage: tests/coverage/php/index.html" >> tests/reports/latest-report.txt; \
	fi
	@if [ -d tests/coverage/js ]; then \
		echo "- JavaScript Coverage: tests/coverage/js/index.html" >> tests/reports/latest-report.txt; \
	fi
	@echo "$(GREEN)✓ Report generated: tests/reports/latest-report.txt$(NC)"

# Check if required tools are available
check-tools:
	@echo "$(YELLOW)Checking required tools...$(NC)"
	@command -v php >/dev/null 2>&1 && echo "$(GREEN)✓ PHP$(NC)" || echo "$(RED)✗ PHP$(NC)"
	@command -v composer >/dev/null 2>&1 && echo "$(GREEN)✓ Composer$(NC)" || echo "$(RED)✗ Composer$(NC)"
	@command -v node >/dev/null 2>&1 && echo "$(GREEN)✓ Node.js$(NC)" || echo "$(RED)✗ Node.js$(NC)"
	@command -v npm >/dev/null 2>&1 && echo "$(GREEN)✓ npm$(NC)" || (command -v yarn >/dev/null 2>&1 && echo "$(GREEN)✓ yarn$(NC)" || echo "$(RED)✗ npm/yarn$(NC)")
	@command -v git >/dev/null 2>&1 && echo "$(GREEN)✓ Git$(NC)" || echo "$(RED)✗ Git$(NC)"

# Development shortcuts
dev-setup: install
	@echo "$(GREEN)✓ Development environment ready$(NC)"

dev-test: test-php test-js
	@echo "$(GREEN)✓ Development tests complete$(NC)"

# CI/CD targets
ci-install:
	composer install --no-interaction --prefer-dist --no-dev
	@if [ -f package.json ]; then npm ci; fi

ci-test:
	./bin/run-tests.sh