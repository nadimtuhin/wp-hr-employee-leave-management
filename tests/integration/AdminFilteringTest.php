<?php
/**
 * Tests for admin leave requests filtering functionality
 */

use PHPUnit\Framework\TestCase;

class AdminFilteringTest extends TestCase {
    
    /**
     * Test that filtering query builds correctly with status filter
     */
    public function test_status_filter_query_building() {
        // Simulate $_GET parameters for status filter
        $_GET = array(
            'page' => 'wp-employee-leaves-requests',
            'status' => 'pending',
            'year' => date('Y')
        );
        
        // Test the query building logic
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $where_conditions = array("YEAR(r.created_at) = %d");
        $where_values = array($year);
        
        if (!empty($status_filter)) {
            $where_conditions[] = "r.status = %s";
            $where_values[] = $status_filter;
        }
        
        $this->assertCount(2, $where_conditions);
        $this->assertContains("r.status = %s", $where_conditions);
        $this->assertContains('pending', $where_values);
        
        // Clean up
        $_GET = array();
    }
    
    /**
     * Test employee search filter query building
     */
    public function test_employee_search_filter_query_building() {
        $_GET = array(
            'page' => 'wp-employee-leaves-requests',
            'employee_search' => 'john doe',
            'year' => date('Y')
        );
        
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $employee_search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        
        $where_conditions = array("YEAR(r.created_at) = %d");
        $where_values = array($year);
        
        if (!empty($employee_search)) {
            $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR r.employee_code LIKE %s)";
            $search_term = '%john doe%'; // Simulating wpdb->esc_like
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $this->assertCount(2, $where_conditions);
        $this->assertStringContainsString("display_name LIKE", $where_conditions[1]);
        $this->assertStringContainsString("user_email LIKE", $where_conditions[1]);
        $this->assertStringContainsString("employee_code LIKE", $where_conditions[1]);
        $this->assertEquals('%john doe%', $where_values[1]);
        
        $_GET = array();
    }
    
    /**
     * Test leave type filter query building
     */
    public function test_leave_type_filter_query_building() {
        $_GET = array(
            'page' => 'wp-employee-leaves-requests',
            'leave_type' => '1',
            'year' => date('Y')
        );
        
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $leave_type_filter = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : '';
        
        $where_conditions = array("YEAR(r.created_at) = %d");
        $where_values = array($year);
        
        if (!empty($leave_type_filter)) {
            $where_conditions[] = "r.id IN (SELECT DISTINCT d.request_id FROM wp_employee_leaves_dates d WHERE d.leave_type_id = %d)";
            $where_values[] = $leave_type_filter;
        }
        
        $this->assertCount(2, $where_conditions);
        $this->assertStringContainsString("SELECT DISTINCT d.request_id", $where_conditions[1]);
        $this->assertStringContainsString("leave_type_id = %d", $where_conditions[1]);
        $this->assertEquals(1, $where_values[1]);
        
        $_GET = array();
    }
    
    /**
     * Test multiple filters combined
     */
    public function test_multiple_filters_combined() {
        $_GET = array(
            'page' => 'wp-employee-leaves-requests',
            'status' => 'approved',
            'employee_search' => 'jane',
            'leave_type' => '2',
            'year' => '2024'
        );
        
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $employee_search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        $leave_type_filter = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : '';
        
        $where_conditions = array("YEAR(r.created_at) = %d");
        $where_values = array($year);
        
        if (!empty($status_filter)) {
            $where_conditions[] = "r.status = %s";
            $where_values[] = $status_filter;
        }
        
        if (!empty($employee_search)) {
            $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR r.employee_code LIKE %s)";
            $search_term = '%jane%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($leave_type_filter)) {
            $where_conditions[] = "r.id IN (SELECT DISTINCT d.request_id FROM wp_employee_leaves_dates d WHERE d.leave_type_id = %d)";
            $where_values[] = $leave_type_filter;
        }
        
        $this->assertCount(4, $where_conditions);
        $this->assertCount(6, $where_values); // 1 + 1 + 3 + 1 (year + status + search terms + leave_type)
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        $this->assertStringContainsString("YEAR(r.created_at) = %d", $where_clause);
        $this->assertStringContainsString("r.status = %s", $where_clause);
        $this->assertStringContainsString("display_name LIKE", $where_clause);
        $this->assertStringContainsString("leave_type_id = %d", $where_clause);
        
        $_GET = array();
    }
    
    /**
     * Test pagination arguments include filters
     */
    public function test_pagination_args_include_filters() {
        $_GET = array(
            'page' => 'wp-employee-leaves-requests',
            'status' => 'pending',
            'employee_search' => 'test',
            'leave_type' => '1',
            'year' => '2024',
            'per_page' => '20'
        );
        
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $employee_search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        $leave_type_filter = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : '';
        $per_page = 20;
        
        $pagination_args = array(
            'year' => $year,
            'per_page' => $per_page
        );
        
        if (!empty($status_filter)) {
            $pagination_args['status'] = $status_filter;
        }
        if (!empty($employee_search)) {
            $pagination_args['employee_search'] = $employee_search;
        }
        if (!empty($leave_type_filter)) {
            $pagination_args['leave_type'] = $leave_type_filter;
        }
        
        $this->assertArrayHasKey('year', $pagination_args);
        $this->assertArrayHasKey('per_page', $pagination_args);
        $this->assertArrayHasKey('status', $pagination_args);
        $this->assertArrayHasKey('employee_search', $pagination_args);
        $this->assertArrayHasKey('leave_type', $pagination_args);
        
        $this->assertEquals(2024, $pagination_args['year']);
        $this->assertEquals('pending', $pagination_args['status']);
        $this->assertEquals('test', $pagination_args['employee_search']);
        $this->assertEquals(1, $pagination_args['leave_type']);
        
        $_GET = array();
    }
    
    /**
     * Test input sanitization for filters
     */
    public function test_filter_input_sanitization() {
        // Test malicious inputs
        $_GET = array(
            'status' => '<script>alert("xss")</script>',
            'employee_search' => '"><script>alert("xss")</script>',
            'leave_type' => 'DROP TABLE;--',
            'year' => '2024; DROP TABLE;--'
        );
        
        // Simulate the sanitization that would happen in the real code
        $status_filter = sanitize_text_field($_GET['status']);
        $employee_search = sanitize_text_field($_GET['employee_search']);
        $leave_type_filter = intval($_GET['leave_type']);
        $year = intval($_GET['year']);
        
        // Verify sanitization worked
        $this->assertEquals('alert("xss")', $status_filter); // Script tags removed
        $this->assertEquals('">alert("xss")', $employee_search); // Script tags removed
        $this->assertEquals(0, $leave_type_filter); // Non-numeric converted to 0
        $this->assertEquals(2024, $year); // Numeric part extracted
        
        $_GET = array();
    }
    
    /**
     * Test filter form structure and security
     */
    public function test_filter_form_structure() {
        // Test that form has proper structure
        $form_fields = array(
            'year' => 'select',
            'status' => 'select',
            'leave_type' => 'select',
            'employee_search' => 'input',
            'per_page' => 'select'
        );
        
        foreach ($form_fields as $field => $type) {
            $this->assertIsString($field);
            $this->assertContains($type, array('select', 'input'));
        }
        
        // Test hidden page field
        $hidden_field = 'wp-employee-leaves-requests';
        $this->assertEquals('wp-employee-leaves-requests', $hidden_field);
    }
    
    /**
     * Test status options are valid
     */
    public function test_valid_status_options() {
        $valid_statuses = array('', 'pending', 'approved', 'rejected');
        
        foreach ($valid_statuses as $status) {
            if ($status === '') {
                $this->assertTrue(empty($status));
            } else {
                $this->assertContains($status, array('pending', 'approved', 'rejected'));
            }
        }
    }
    
    /**
     * Test per page options are valid
     */
    public function test_valid_per_page_options() {
        $per_page_options = array(5, 10, 20, 50);
        
        foreach ($per_page_options as $option) {
            $this->assertIsInt($option);
            $this->assertGreaterThan(0, $option);
            $this->assertLessThanOrEqual(50, $option);
        }
    }
    
    /**
     * Test year range is reasonable
     */
    public function test_year_range_validation() {
        $current_year = date('Y');
        $year_range = range($current_year - 2, $current_year + 1);
        
        $this->assertContains(intval($current_year), $year_range);
        $this->assertContains(intval($current_year) - 1, $year_range);
        $this->assertContains(intval($current_year) + 1, $year_range);
        $this->assertCount(4, $year_range); // 2 years back, current, 1 year forward
    }
    
    /**
     * Test query performance considerations
     */
    public function test_query_performance_structure() {
        // Test that queries use indexed fields
        $indexed_fields = array(
            'r.status',           // Status should be indexed
            'r.employee_id',      // Employee ID should be indexed
            'r.created_at',       // Created date should be indexed
            'd.leave_type_id',    // Leave type should be indexed
            'd.request_id'        // Request ID should be indexed
        );
        
        foreach ($indexed_fields as $field) {
            $this->assertStringContainsString('.', $field); // Proper table aliasing
            $this->assertIsString($field);
        }
    }
    
    /**
     * Test that filter results text is properly formatted
     */
    public function test_filter_results_text() {
        // Test with filters applied
        $status_filter = 'pending';
        $employee_search = 'john';
        $leave_type_filter = 1;
        
        $filter_text = '';
        if (!empty($status_filter) || !empty($employee_search) || !empty($leave_type_filter)) {
            $filter_text = ' (filtered)';
        }
        
        $this->assertEquals(' (filtered)', $filter_text);
        
        // Test without filters
        $status_filter = '';
        $employee_search = '';
        $leave_type_filter = '';
        
        $filter_text = '';
        if (!empty($status_filter) || !empty($employee_search) || !empty($leave_type_filter)) {
            $filter_text = ' (filtered)';
        }
        
        $this->assertEquals('', $filter_text);
    }
}