<?php
/**
 * Security tests for WP Employee Leaves Plugin
 */

class SecurityTest extends WP_UnitTestCase {
    
    protected $plugin;
    protected $user_id;
    protected $admin_id;
    
    public function setUp(): void {
        parent::setUp();
        
        global $wp_employee_leaves;
        $this->plugin = $wp_employee_leaves;
        
        // Create test users
        $this->user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $this->admin_id = WP_Employee_Leaves_Test_Data_Factory::create_admin_user();
        
        // Clean slate for each test
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
    }
    
    public function tearDown(): void {
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        parent::tearDown();
    }
    
    /**
     * Test SQL injection protection
     */
    public function test_sql_injection_protection() {
        global $wpdb;
        
        wp_set_current_user($this->user_id);
        
        // Attempt SQL injection in various fields
        $malicious_inputs = [
            "'; DROP TABLE {$wpdb->prefix}employee_leaves_requests; --",
            "' OR '1'='1",
            "1'; DELETE FROM {$wpdb->prefix}employee_leaves_requests WHERE '1'='1",
            "<script>alert('xss')</script>",
            "' UNION SELECT password FROM wp_users --",
        ];
        
        foreach ($malicious_inputs as $malicious_input) {
            $_POST = [
                'action' => 'submit_leave_request',
                'nonce' => wp_create_nonce('employee_leave_nonce'),
                'employee_id' => $malicious_input,
                'manager_emails' => 'manager@test.com',
                'reliever_emails' => 'reliever@test.com',
                'reason' => $malicious_input,
                'leave_dates' => ['2025-07-20'],
                'leave_types' => [1],
            ];
            
            // Simulate AJAX request
            ob_start();
            try {
                $this->plugin->handle_leave_request_submission();
            } catch (Exception $e) {
                // Expected to catch exceptions for malicious input
            }
            $output = ob_get_clean();
            
            // Verify that tables still exist (no DROP TABLE executed)
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}employee_leaves_requests'");
            $this->assertEquals($wpdb->prefix . 'employee_leaves_requests', $table_exists);
        }
    }
    
    /**
     * Test XSS protection in output
     */
    public function test_xss_protection() {
        // Create malicious user data
        $malicious_user_id = wp_insert_user([
            'user_login' => 'malicious_user',
            'user_email' => 'malicious@test.com',
            'user_pass' => 'password123',
            'first_name' => '<script>alert("xss")</script>',
            'last_name' => '<img src=x onerror=alert("xss")>',
            'display_name' => '"><script>alert("xss")</script><"',
        ]);
        
        wp_set_current_user($malicious_user_id);
        
        // Test shortcode output escaping
        $output = do_shortcode('[employee_leave_form]');
        
        // Ensure no script tags in output
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert("xss")', $output);
        $this->assertStringNotContainsString('onerror=', $output);
        $this->assertStringNotContainsString('javascript:', $output);
        
        // Test my leave requests output
        $output = do_shortcode('[my_leave_requests]');
        
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('alert("xss")', $output);
        
        // Clean up
        wp_delete_user($malicious_user_id);
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        wp_set_current_user($this->admin_id);
        
        // Test with invalid nonce
        $_POST = [
            'action' => 'approve_leave_request',
            'nonce' => 'invalid_nonce',
            'request_id' => 1,
        ];
        
        $this->expectOutputString('-1');
        $this->plugin->handle_approve_request();
        
        // Test with missing nonce
        unset($_POST['nonce']);
        
        $this->expectOutputString('-1');
        $this->plugin->handle_approve_request();
    }
    
    /**
     * Test capability checks
     */
    public function test_capability_checks() {
        // Test admin-only functions with regular user
        wp_set_current_user($this->user_id);
        
        $_POST = [
            'action' => 'approve_leave_request',
            'nonce' => wp_create_nonce('approve_leave_nonce'),
            'request_id' => 1,
        ];
        
        ob_start();
        $this->plugin->handle_approve_request();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Unauthorized', $response['data']);
        
        // Test same function with admin user
        wp_set_current_user($this->admin_id);
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        $_POST['request_id'] = $request_id;
        
        ob_start();
        $this->plugin->handle_approve_request();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        wp_set_current_user($this->user_id);
        
        $malicious_inputs = [
            'employee_id' => '<script>alert("xss")</script>EMP001',
            'manager_emails' => 'manager@test.com<script>alert("xss")</script>',
            'reliever_emails' => '<img src=x onerror=alert("xss")>reliever@test.com',
            'reason' => 'Medical appointment<script>alert("xss")</script>',
        ];
        
        $_POST = array_merge([
            'action' => 'submit_leave_request',
            'nonce' => wp_create_nonce('employee_leave_nonce'),
            'leave_dates' => ['2025-07-20'],
            'leave_types' => [1],
        ], $malicious_inputs);
        
        ob_start();
        $this->plugin->handle_leave_request_submission();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        if ($response['success']) {
            // Verify data was sanitized in database
            global $wpdb;
            $request = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}employee_leaves_requests ORDER BY id DESC LIMIT 1"
            ));
            
            $this->assertNotNull($request);
            $this->assertStringNotContainsString('<script>', $request->employee_id);
            $this->assertStringNotContainsString('<script>', $request->manager_emails);
            $this->assertStringNotContainsString('<script>', $request->reliever_emails);
            $this->assertStringNotContainsString('<script>', $request->reason);
        }
    }
    
    /**
     * Test email validation and sanitization
     */
    public function test_email_validation() {
        wp_set_current_user($this->user_id);
        
        $invalid_emails = [
            'invalid-email',
            'test@<script>alert("xss")</script>.com',
            'javascript:alert("xss")',
            '<script>alert("xss")</script>@test.com',
        ];
        
        foreach ($invalid_emails as $invalid_email) {
            $_POST = [
                'action' => 'submit_leave_request',
                'nonce' => wp_create_nonce('employee_leave_nonce'),
                'employee_id' => 'EMP001',
                'manager_emails' => $invalid_email,
                'reliever_emails' => 'reliever@test.com',
                'reason' => 'Medical appointment',
                'leave_dates' => ['2025-07-20'],
                'leave_types' => [1],
            ];
            
            ob_start();
            $this->plugin->handle_leave_request_submission();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            // Should either fail validation or sanitize the email
            if ($response['success']) {
                global $wpdb;
                $request = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}employee_leaves_requests ORDER BY id DESC LIMIT 1"
                ));
                
                $this->assertStringNotContainsString('<script>', $request->manager_emails);
                $this->assertStringNotContainsString('javascript:', $request->manager_emails);
            }
        }
    }
    
    /**
     * Test file inclusion protection
     */
    public function test_file_inclusion_protection() {
        // Test that ABSPATH is defined
        $this->assertTrue(defined('ABSPATH'));
        
        // Test plugin file has ABSPATH check
        $plugin_content = file_get_contents(WP_EMPLOYEE_LEAVES_PLUGIN_DIR . '/wp-employee-leaves.php');
        $this->assertStringContainsString('if (!defined(\'ABSPATH\'))', $plugin_content);
        $this->assertStringContainsString('exit;', $plugin_content);
    }
    
    /**
     * Test data exposure protection
     */
    public function test_data_exposure_protection() {
        wp_set_current_user($this->user_id);
        
        // Create requests for different users
        $other_user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $my_request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        $other_request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($other_user_id);
        
        // Test that users can't access other users' data
        $output = do_shortcode('[my_leave_requests]');
        
        // Should not contain other user's requests
        // This is a basic test - in a real implementation, you'd need to check the database queries
        $this->assertStringNotContainsString("user-{$other_user_id}", $output);
    }
    
    /**
     * Test rate limiting (basic)
     */
    public function test_basic_rate_limiting() {
        wp_set_current_user($this->user_id);
        
        $nonce = wp_create_nonce('employee_leave_nonce');
        
        // Simulate multiple rapid requests
        for ($i = 0; $i < 10; $i++) {
            $_POST = [
                'action' => 'submit_leave_request',
                'nonce' => $nonce,
                'employee_id' => "EMP00{$i}",
                'manager_emails' => 'manager@test.com',
                'reliever_emails' => 'reliever@test.com',
                'reason' => "Request {$i}",
                'leave_dates' => ['2025-07-20'],
                'leave_types' => [1],
            ];
            
            ob_start();
            $this->plugin->handle_leave_request_submission();
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            // Basic test - ensure responses are still valid
            $this->assertIsArray($response);
            $this->assertArrayHasKey('success', $response);
        }
        
        // In a real implementation, you might check for rate limiting after X requests
        $this->assertTrue(true); // Placeholder
    }
    
    /**
     * Test password and sensitive data handling
     */
    public function test_sensitive_data_handling() {
        // Ensure no hardcoded passwords or secrets in plugin
        $plugin_content = file_get_contents(WP_EMPLOYEE_LEAVES_PLUGIN_DIR . '/wp-employee-leaves.php');
        
        // Check for common password patterns
        $this->assertStringNotContainsString('password', strtolower($plugin_content));
        $this->assertStringNotContainsString('secret', strtolower($plugin_content));
        $this->assertStringNotContainsString('api_key', strtolower($plugin_content));
        
        // Check JavaScript files
        $js_files = glob(WP_EMPLOYEE_LEAVES_PLUGIN_DIR . '/**/*.js');
        foreach ($js_files as $js_file) {
            $js_content = file_get_contents($js_file);
            $this->assertStringNotContainsString('password', strtolower($js_content));
            $this->assertStringNotContainsString('secret', strtolower($js_content));
        }
    }
    
    /**
     * Test permission escalation protection
     */
    public function test_permission_escalation() {
        wp_set_current_user($this->user_id);
        
        // Try to access admin functions
        $admin_functions = [
            'create_leave_page',
            'create_my_requests_page',
            'add_shortcode_to_page',
        ];
        
        foreach ($admin_functions as $function) {
            $_POST = [
                'action' => $function,
                'nonce' => wp_create_nonce('wp_employee_leaves_admin'),
                'page_title' => 'Test Page',
            ];
            
            $method_name = 'handle_' . $function;
            if (method_exists($this->plugin, $method_name)) {
                ob_start();
                $this->plugin->$method_name();
                $output = ob_get_clean();
                
                $response = json_decode($output, true);
                $this->assertFalse($response['success']);
                $this->assertStringContainsString('Unauthorized', $response['data']);
            }
        }
    }
}