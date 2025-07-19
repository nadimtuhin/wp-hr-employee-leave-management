<?php
/**
 * Simple PHPUnit bootstrap for basic testing without WordPress
 */

// Define constants that would normally be defined by WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WP_EMPLOYEE_LEAVES_VERSION')) {
    define('WP_EMPLOYEE_LEAVES_VERSION', '1.4.0');
}

if (!defined('WP_EMPLOYEE_LEAVES_PLUGIN_DIR')) {
    define('WP_EMPLOYEE_LEAVES_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('WP_EMPLOYEE_LEAVES_PLUGIN_URL')) {
    define('WP_EMPLOYEE_LEAVES_PLUGIN_URL', 'http://example.com/wp-content/plugins/wp-employee-leaves/');
}

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Mock WordPress functions that our tests might need
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return $nonce === wp_create_nonce($action);
    }
}

// Mock WordPress constants
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

echo "Simple test bootstrap loaded successfully.\n";