<?php
/**
 * Unit tests for WP Employee Leaves Plugin Core
 */

class PluginTest extends WP_UnitTestCase {
    
    protected $plugin;
    
    public function setUp(): void {
        parent::setUp();
        
        global $wp_employee_leaves;
        $this->plugin = $wp_employee_leaves;
        
        // Clean slate for each test
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
    }
    
    public function tearDown(): void {
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        parent::tearDown();
    }
    
    /**
     * Test plugin initialization
     */
    public function test_plugin_initialized() {
        $this->assertInstanceOf('WPEmployeeLeaves', $this->plugin);
        $this->assertTrue(class_exists('WPEmployeeLeaves'));
    }
    
    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants() {
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_VERSION'));
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_PLUGIN_DIR'));
        $this->assertTrue(defined('WP_EMPLOYEE_LEAVES_PLUGIN_URL'));
        $this->assertEquals('1.3.0', WP_EMPLOYEE_LEAVES_VERSION);
    }
    
    /**
     * Test database tables are created
     */
    public function test_database_tables_exist() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'employee_leaves_types',
            $wpdb->prefix . 'employee_leaves_requests',
            $wpdb->prefix . 'employee_leaves_dates',
            $wpdb->prefix . 'employee_leaves_balances',
            $wpdb->prefix . 'employee_leaves_logs',
            $wpdb->prefix . 'employee_leaves_notifications',
            $wpdb->prefix . 'employee_leaves_email_templates',
        ];
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            $this->assertEquals($table, $table_exists, "Table {$table} should exist");
        }
    }
    
    /**
     * Test admin menu is registered
     */
    public function test_admin_menu_registered() {
        global $_wp_submenu_nopriv;
        
        // Set current user as admin
        $admin_user = WP_Employee_Leaves_Test_Data_Factory::create_admin_user();
        wp_set_current_user($admin_user);
        
        // Trigger admin menu registration
        do_action('admin_menu');
        
        // Check if menu items exist
        global $submenu;
        $this->assertArrayHasKey('wp-employee-leaves', $submenu);
    }
    
    /**
     * Test shortcodes are registered
     */
    public function test_shortcodes_registered() {
        $this->assertTrue(shortcode_exists('employee_leave_form'));
        $this->assertTrue(shortcode_exists('my_leave_requests'));
    }
    
    /**
     * Test AJAX actions are registered
     */
    public function test_ajax_actions_registered() {
        $actions = [
            'submit_leave_request',
            'approve_leave_request', 
            'reject_leave_request',
            'create_leave_page',
            'create_my_requests_page',
            'add_shortcode_to_page',
        ];
        
        foreach ($actions as $action) {
            $this->assertTrue(has_action("wp_ajax_{$action}"));
            if ($action === 'submit_leave_request') {
                $this->assertTrue(has_action("wp_ajax_nopriv_{$action}"));
            }
        }
    }
    
    /**
     * Test scripts and styles are enqueued
     */
    public function test_scripts_and_styles_enqueued() {
        // Test admin scripts
        set_current_screen('wp-employee-leaves');
        do_action('admin_enqueue_scripts', 'wp-employee-leaves');
        
        $this->assertTrue(wp_script_is('wp-employee-leaves-admin', 'enqueued'));
        $this->assertTrue(wp_style_is('wp-employee-leaves-admin', 'enqueued'));
        
        // Test frontend scripts
        do_action('wp_enqueue_scripts');
        
        $this->assertTrue(wp_script_is('wp-employee-leaves-frontend', 'enqueued'));
        $this->assertTrue(wp_style_is('wp-employee-leaves-frontend', 'enqueued'));
    }
    
    /**
     * Test text domain is loaded
     */
    public function test_text_domain_loaded() {
        $this->assertTrue(is_textdomain_loaded('wp-employee-leaves'));
    }
    
    /**
     * Test default leave types are created on activation
     */
    public function test_default_leave_types_created() {
        // Trigger activation
        $this->plugin->create_tables();
        $this->plugin->insert_default_data();
        
        $leave_types = $this->plugin->get_leave_types();
        $this->assertNotEmpty($leave_types);
        $this->assertGreaterThanOrEqual(4, count($leave_types));
        
        // Check for required leave types
        $type_names = wp_list_pluck($leave_types, 'name');
        $this->assertContains('Annual Leave', $type_names);
        $this->assertContains('Sick Leave', $type_names);
        $this->assertContains('Casual Leave', $type_names);
        $this->assertContains('Emergency Leave (Probation)', $type_names);
    }
    
    /**
     * Test status label helper function
     */
    public function test_get_status_label() {
        $this->assertEquals('Pending', $this->plugin->get_status_label('pending'));
        $this->assertEquals('Approved', $this->plugin->get_status_label('approved'));
        $this->assertEquals('Rejected', $this->plugin->get_status_label('rejected'));
        $this->assertEquals('Unknown', $this->plugin->get_status_label('invalid'));
    }
}