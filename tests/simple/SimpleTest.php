<?php
/**
 * Simple tests to verify the test infrastructure is working
 */

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase {
    
    /**
     * Test that PHPUnit is working
     */
    public function test_phpunit_is_working() {
        $this->assertTrue(true);
        $this->assertEquals(2, 1 + 1);
        $this->assertIsString('hello world');
    }
    
    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants_defined() {
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_VERSION'));
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_PLUGIN_DIR'));
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_PLUGIN_URL'));
        $this->assertEquals('1.6.0', WP_EMPLOYEE_LEAVES_VERSION);
    }
    
    /**
     * Test plugin file exists
     */
    public function test_plugin_file_exists() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $this->assertFileExists($plugin_file);
    }
    
    /**
     * Test plugin file has correct header
     */
    public function test_plugin_header() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        $this->assertStringContainsString('Plugin Name: WP HR Employee Leave Management', $content);
        $this->assertStringContainsString('Version: 1.6.0', $content);
        $this->assertStringContainsString('Text Domain: wp-employee-leaves', $content);
    }
    
    /**
     * Test ABSPATH security check
     */
    public function test_abspath_security_check() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        $this->assertStringContainsString("if (!defined('ABSPATH'))", $content);
        $this->assertStringContainsString('exit;', $content);
    }
    
    /**
     * Test mock WordPress functions work
     */
    public function test_mock_wordpress_functions() {
        $this->assertEquals('Hello World', __('Hello World'));
        $this->assertEquals('&lt;script&gt;', esc_html('<script>'));
        $this->assertEquals('test&amp;param', esc_attr('test&param'));
        $this->assertIsString(wp_create_nonce('test'));
        $this->assertTrue(wp_verify_nonce(wp_create_nonce('test'), 'test'));
    }
    
    /**
     * Test directory structure exists
     */
    public function test_directory_structure() {
        $base_dir = WP_EMPLOYEE_LEAVES_PLUGIN_DIR;
        
        $this->assertDirectoryExists($base_dir . 'admin');
        $this->assertDirectoryExists($base_dir . 'frontend');
        $this->assertDirectoryExists($base_dir . 'languages');
        $this->assertDirectoryExists($base_dir . 'tests');
    }
    
    /**
     * Test required files exist
     */
    public function test_required_files_exist() {
        $base_dir = WP_EMPLOYEE_LEAVES_PLUGIN_DIR;
        
        $this->assertFileExists($base_dir . 'wp-employee-leaves.php');
        $this->assertFileExists($base_dir . 'README.md');
        $this->assertFileExists($base_dir . 'phpunit.xml');
        $this->assertFileExists($base_dir . 'package.json');
        $this->assertFileExists($base_dir . 'composer.json');
    }
    
    /**
     * Test CSS and JS files exist
     */
    public function test_asset_files_exist() {
        $base_dir = WP_EMPLOYEE_LEAVES_PLUGIN_DIR;
        
        $this->assertFileExists($base_dir . 'admin/css/admin.css');
        $this->assertFileExists($base_dir . 'admin/js/admin.js');
        $this->assertFileExists($base_dir . 'frontend/css/style.css');
        $this->assertFileExists($base_dir . 'frontend/js/script.js');
    }
    
    /**
     * Test language files exist
     */
    public function test_language_files_exist() {
        $base_dir = WP_EMPLOYEE_LEAVES_PLUGIN_DIR;
        
        $this->assertFileExists($base_dir . 'languages/wp-employee-leaves.pot');
        $this->assertFileExists($base_dir . 'languages/wp-employee-leaves-es_ES.po');
        $this->assertFileExists($base_dir . 'languages/wp-employee-leaves-fr_FR.po');
    }
    
    /**
     * Test that plugin follows WordPress coding standards structure
     */
    public function test_wordpress_plugin_structure() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Check for WordPress plugin header format
        $this->assertMatchesRegularExpression('/\/\*\*[\s\S]*Plugin Name:/', $content);
        
        // Check for proper PHP opening tag
        $this->assertStringStartsWith('<?php', $content);
        
        // Check for class definition
        $this->assertStringContainsString('class WPEmployeeLeaves', $content);
    }
}