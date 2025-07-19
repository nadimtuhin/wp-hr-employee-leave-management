<?php
/**
 * Unit tests for Shortcodes
 */

class ShortcodeTest extends WP_UnitTestCase {
    
    protected $plugin;
    protected $user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        global $wp_employee_leaves;
        $this->plugin = $wp_employee_leaves;
        
        // Create test user and set as current
        $this->user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        wp_set_current_user($this->user_id);
        
        // Create test data
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        
        // Clean slate for each test
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
    }
    
    public function tearDown(): void {
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        parent::tearDown();
    }
    
    /**
     * Test employee leave form shortcode output
     */
    public function test_employee_leave_form_shortcode() {
        $output = do_shortcode('[employee_leave_form]');
        
        // Check for form elements
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('id="employee-leave-form"', $output);
        $this->assertStringContainsString('name="employee_id"', $output);
        $this->assertStringContainsString('name="manager_emails"', $output);
        $this->assertStringContainsString('name="reliever_emails"', $output);
        $this->assertStringContainsString('name="reason"', $output);
        $this->assertStringContainsString('wp_employee_leaves_ajax', $output);
        
        // Check for nonce
        $this->assertStringContainsString('employee_leave_nonce', $output);
        
        // Check for JavaScript
        $this->assertStringContainsString('wp-employee-leaves-frontend', $output);
    }
    
    /**
     * Test employee leave form shortcode when user not logged in
     */
    public function test_employee_leave_form_not_logged_in() {
        wp_set_current_user(0); // Log out
        
        $output = do_shortcode('[employee_leave_form]');
        
        $this->assertStringContainsString('You must be logged in', $output);
        $this->assertStringNotContainsString('<form', $output);
    }
    
    /**
     * Test employee leave form shortcode with custom attributes
     */
    public function test_employee_leave_form_with_attributes() {
        $output = do_shortcode('[employee_leave_form title="Custom Title" show_balance="false"]');
        
        $this->assertStringContainsString('Custom Title', $output);
        $this->assertStringNotContainsString('Leave Balance', $output);
    }
    
    /**
     * Test my leave requests shortcode output
     */
    public function test_my_leave_requests_shortcode() {
        // Create some test requests
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        WP_Employee_Leaves_Test_Data_Factory::create_leave_dates($request_id);
        
        $output = do_shortcode('[my_leave_requests]');
        
        // Check for container
        $this->assertStringContainsString('class="my-leave-requests"', $output);
        
        // Check for pagination
        $this->assertStringContainsString('pagination', $output);
        
        // Check for request data (if any exists)
        if (strpos($output, 'No leave requests found') === false) {
            $this->assertStringContainsString('Request #', $output);
            $this->assertStringContainsString('Status:', $output);
        }
    }
    
    /**
     * Test my leave requests shortcode when user not logged in
     */
    public function test_my_leave_requests_not_logged_in() {
        wp_set_current_user(0); // Log out
        
        $output = do_shortcode('[my_leave_requests]');
        
        $this->assertStringContainsString('You must be logged in', $output);
        $this->assertStringNotContainsString('class="my-leave-requests"', $output);
    }
    
    /**
     * Test my leave requests shortcode with custom per_page
     */
    public function test_my_leave_requests_with_per_page() {
        $output = do_shortcode('[my_leave_requests per_page="5"]');
        
        $this->assertStringContainsString('class="my-leave-requests"', $output);
        // per_page should affect pagination if there are enough requests
    }
    
    /**
     * Test shortcode with no leave types available
     */
    public function test_leave_form_no_leave_types() {
        // Clear leave types
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}employee_leaves_types");
        
        $output = do_shortcode('[employee_leave_form]');
        
        $this->assertStringContainsString('No leave types available', $output);
    }
    
    /**
     * Test leave balance display in form
     */
    public function test_leave_balance_display() {
        $output = do_shortcode('[employee_leave_form]');
        
        // Should contain balance information
        $this->assertStringContainsString('Leave Balance', $output);
        $this->assertStringContainsString('Available:', $output);
    }
    
    /**
     * Test shortcode security - output escaping
     */
    public function test_shortcode_output_escaping() {
        // Test with malicious user data
        $malicious_user_id = wp_insert_user([
            'user_login' => 'malicious<script>alert("xss")</script>',
            'user_email' => 'malicious@test.com',
            'user_pass' => 'password123',
            'first_name' => '<script>alert("xss")</script>',
            'last_name' => 'User',
        ]);
        
        wp_set_current_user($malicious_user_id);
        
        $output = do_shortcode('[employee_leave_form]');
        
        // Ensure no script tags in output
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert("xss")', $output);
        
        // Clean up
        wp_delete_user($malicious_user_id);
    }
}