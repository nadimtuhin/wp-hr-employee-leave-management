<?php
/**
 * PHPUnit bootstrap file for WP Employee Leaves Plugin Tests
 */

// Composer autoloader if available
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// Define constants
define('WP_EMPLOYEE_LEAVES_TESTS_DIR', __DIR__);
define('WP_EMPLOYEE_LEAVES_PLUGIN_DIR', dirname(__DIR__));

// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Check if WordPress test suite is available
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "WordPress test suite not found. Please install wordpress-develop or set WP_TESTS_DIR environment variable.\n";
    echo "You can install it with: git clone --depth=1 --branch=trunk https://github.com/WordPress/wordpress-develop.git /tmp/wordpress-develop\n";
    echo "Then set WP_TESTS_DIR=/tmp/wordpress-develop/tests/phpunit\n";
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    require WP_EMPLOYEE_LEAVES_PLUGIN_DIR . '/wp-employee-leaves.php';
    
    // Initialize the plugin
    if (class_exists('WPEmployeeLeaves')) {
        global $wp_employee_leaves;
        $wp_employee_leaves = new WPEmployeeLeaves();
        $wp_employee_leaves->init();
    }
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Helper classes for testing
require_once WP_EMPLOYEE_LEAVES_TESTS_DIR . '/fixtures/TestDataFactory.php';
require_once WP_EMPLOYEE_LEAVES_TESTS_DIR . '/fixtures/MockWPUser.php';