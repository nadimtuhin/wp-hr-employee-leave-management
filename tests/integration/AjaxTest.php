<?php
/**
 * Integration tests for AJAX handlers
 */

class AjaxTest extends WP_Ajax_UnitTestCase {
    
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
        
        // Create test data
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
    }
    
    public function tearDown(): void {
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        parent::tearDown();
    }
    
    /**
     * Test submit leave request AJAX handler - success
     */
    public function test_submit_leave_request_success() {
        wp_set_current_user($this->user_id);
        
        $_POST = [
            'action' => 'submit_leave_request',
            'nonce' => wp_create_nonce('employee_leave_nonce'),
            'employee_id' => 'EMP001',
            'manager_emails' => 'manager@test.com',
            'reliever_emails' => 'reliever@test.com',
            'reason' => 'Medical appointment',
            'leave_dates' => ['2025-07-20', '2025-07-21'],
            'leave_types' => [1, 1],
        ];
        
        try {
            $this->_handleAjax('submit_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('submitted successfully', $response['data']['message']);
    }
    
    /**
     * Test submit leave request AJAX handler - invalid nonce
     */
    public function test_submit_leave_request_invalid_nonce() {
        wp_set_current_user($this->user_id);
        
        $_POST = [
            'action' => 'submit_leave_request',
            'nonce' => 'invalid_nonce',
            'employee_id' => 'EMP001',
            'manager_emails' => 'manager@test.com',
            'reliever_emails' => 'reliever@test.com',
            'reason' => 'Medical appointment',
            'leave_dates' => ['2025-07-20'],
            'leave_types' => [1],
        ];
        
        try {
            $this->_handleAjax('submit_leave_request');
        } catch (WPAjaxDieStopException $e) {
            $this->assertEquals('-1', $e->getMessage());
        }
    }
    
    /**
     * Test submit leave request AJAX handler - not logged in
     */
    public function test_submit_leave_request_not_logged_in() {
        wp_set_current_user(0);
        
        $_POST = [
            'action' => 'submit_leave_request',
            'nonce' => wp_create_nonce('employee_leave_nonce'),
            'employee_id' => 'EMP001',
            'manager_emails' => 'manager@test.com',
            'reliever_emails' => 'reliever@test.com',
            'reason' => 'Medical appointment',
            'leave_dates' => ['2025-07-20'],
            'leave_types' => [1],
        ];
        
        try {
            $this->_handleAjax('submit_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('must be logged in', $response['data']);
    }
    
    /**
     * Test submit leave request AJAX handler - missing required fields
     */
    public function test_submit_leave_request_missing_fields() {
        wp_set_current_user($this->user_id);
        
        $_POST = [
            'action' => 'submit_leave_request',
            'nonce' => wp_create_nonce('employee_leave_nonce'),
            // Missing employee_id
            'manager_emails' => 'manager@test.com',
            'reason' => 'Medical appointment',
            'leave_dates' => ['2025-07-20'],
            'leave_types' => [1],
        ];
        
        try {
            $this->_handleAjax('submit_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Employee ID is required', $response['data']);
    }
    
    /**
     * Test approve leave request AJAX handler - success
     */
    public function test_approve_leave_request_success() {
        wp_set_current_user($this->admin_id);
        
        // Create a test request
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        
        $_POST = [
            'action' => 'approve_leave_request',
            'nonce' => wp_create_nonce('approve_leave_nonce'),
            'request_id' => $request_id,
            'comment' => 'Approved by manager',
        ];
        
        try {
            $this->_handleAjax('approve_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('approved successfully', $response['data']['message']);
        
        // Verify database update
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}employee_leaves_requests WHERE id = %d",
            $request_id
        ));
        $this->assertEquals('approved', $status);
    }
    
    /**
     * Test approve leave request AJAX handler - unauthorized
     */
    public function test_approve_leave_request_unauthorized() {
        wp_set_current_user($this->user_id); // Regular user, not admin
        
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        
        $_POST = [
            'action' => 'approve_leave_request',
            'nonce' => wp_create_nonce('approve_leave_nonce'),
            'request_id' => $request_id,
        ];
        
        try {
            $this->_handleAjax('approve_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Unauthorized', $response['data']);
    }
    
    /**
     * Test reject leave request AJAX handler - success
     */
    public function test_reject_leave_request_success() {
        wp_set_current_user($this->admin_id);
        
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($this->user_id);
        
        $_POST = [
            'action' => 'reject_leave_request',
            'nonce' => wp_create_nonce('reject_leave_nonce'),
            'request_id' => $request_id,
            'comment' => 'Rejected due to staffing',
        ];
        
        try {
            $this->_handleAjax('reject_leave_request');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('rejected successfully', $response['data']['message']);
        
        // Verify database update
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}employee_leaves_requests WHERE id = %d",
            $request_id
        ));
        $this->assertEquals('rejected', $status);
    }
    
    /**
     * Test create leave page AJAX handler - success
     */
    public function test_create_leave_page_success() {
        wp_set_current_user($this->admin_id);
        
        $_POST = [
            'action' => 'create_leave_page',
            'nonce' => wp_create_nonce('wp_employee_leaves_admin'),
            'page_title' => 'Test Leave Request Page',
        ];
        
        try {
            $this->_handleAjax('create_leave_page');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Page created successfully', $response['data']['message']);
        $this->assertArrayHasKey('page_id', $response['data']);
        $this->assertArrayHasKey('edit_url', $response['data']);
        $this->assertArrayHasKey('view_url', $response['data']);
        
        // Verify page was created
        $page = get_post($response['data']['page_id']);
        $this->assertNotNull($page);
        $this->assertEquals('Test Leave Request Page', $page->post_title);
        $this->assertStringContainsString('[employee_leave_form]', $page->post_content);
    }
    
    /**
     * Test create my requests page AJAX handler - success
     */
    public function test_create_my_requests_page_success() {
        wp_set_current_user($this->admin_id);
        
        $_POST = [
            'action' => 'create_my_requests_page',
            'nonce' => wp_create_nonce('wp_employee_leaves_admin'),
            'page_title' => 'My Leave Requests',
        ];
        
        try {
            $this->_handleAjax('create_my_requests_page');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('page created successfully', $response['data']['message']);
        
        // Verify page was created
        $page = get_post($response['data']['page_id']);
        $this->assertNotNull($page);
        $this->assertEquals('My Leave Requests', $page->post_title);
        $this->assertStringContainsString('[my_leave_requests]', $page->post_content);
    }
    
    /**
     * Test add shortcode to page AJAX handler - success
     */
    public function test_add_shortcode_to_page_success() {
        wp_set_current_user($this->admin_id);
        
        // Create a test page
        $page_id = wp_insert_post([
            'post_title' => 'Test Page',
            'post_content' => 'Existing content',
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
        
        $_POST = [
            'action' => 'add_shortcode_to_page',
            'nonce' => wp_create_nonce('wp_employee_leaves_admin'),
            'page_id' => $page_id,
            'shortcode' => '[employee_leave_form]',
        ];
        
        try {
            $this->_handleAjax('add_shortcode_to_page');
        } catch (WPAjaxDieContinueException $e) {
            unset($e);
        }
        
        $response = json_decode($this->_last_response, true);
        
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Shortcode added successfully', $response['data']['message']);
        
        // Verify shortcode was added
        $page = get_post($page_id);
        $this->assertStringContainsString('[employee_leave_form]', $page->post_content);
    }
    
    /**
     * Test CSRF protection on all AJAX handlers
     */
    public function test_csrf_protection() {
        wp_set_current_user($this->admin_id);
        
        $handlers = [
            'approve_leave_request' => ['nonce' => 'approve_leave_nonce', 'request_id' => 1],
            'reject_leave_request' => ['nonce' => 'reject_leave_nonce', 'request_id' => 1],
            'create_leave_page' => ['nonce' => 'wp_employee_leaves_admin', 'page_title' => 'Test'],
            'create_my_requests_page' => ['nonce' => 'wp_employee_leaves_admin', 'page_title' => 'Test'],
        ];
        
        foreach ($handlers as $action => $data) {
            $_POST = array_merge(['action' => $action, 'nonce' => 'invalid'], $data);
            
            try {
                $this->_handleAjax($action);
            } catch (WPAjaxDieStopException $e) {
                $this->assertEquals('-1', $e->getMessage(), "CSRF protection failed for {$action}");
            }
        }
    }
}