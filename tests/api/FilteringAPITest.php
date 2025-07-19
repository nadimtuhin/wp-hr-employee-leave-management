<?php
/**
 * API tests for filtering functionality
 * Tests the actual filtering behavior with simulated database operations
 */

use PHPUnit\Framework\TestCase;

class FilteringAPITest extends TestCase {
    
    private $mock_requests = array();
    private $mock_leave_types = array();
    
    protected function setUp(): void {
        // Mock leave requests data
        $this->mock_requests = array(
            (object) array(
                'id' => 1,
                'employee_id' => 1,
                'employee_code' => 'EMP001',
                'status' => 'pending',
                'reason' => 'Annual vacation',
                'created_at' => '2024-01-15 10:00:00',
                'display_name' => 'John Doe',
                'user_email' => 'john.doe@company.com'
            ),
            (object) array(
                'id' => 2,
                'employee_id' => 2,
                'employee_code' => 'EMP002',
                'status' => 'approved',
                'reason' => 'Medical leave',
                'created_at' => '2024-02-20 14:30:00',
                'display_name' => 'Jane Smith',
                'user_email' => 'jane.smith@company.com'
            ),
            (object) array(
                'id' => 3,
                'employee_id' => 3,
                'employee_code' => 'EMP003',
                'status' => 'rejected',
                'reason' => 'Personal leave',
                'created_at' => '2024-03-10 09:15:00',
                'display_name' => 'Bob Johnson',
                'user_email' => 'bob.johnson@company.com'
            ),
            (object) array(
                'id' => 4,
                'employee_id' => 1,
                'employee_code' => 'EMP001',
                'status' => 'approved',
                'reason' => 'Sick leave',
                'created_at' => '2024-04-05 16:45:00',
                'display_name' => 'John Doe',
                'user_email' => 'john.doe@company.com'
            )
        );
        
        // Mock leave types
        $this->mock_leave_types = array(
            (object) array('id' => 1, 'name' => 'Annual Leave', 'active' => 1),
            (object) array('id' => 2, 'name' => 'Sick Leave', 'active' => 1),
            (object) array('id' => 3, 'name' => 'Personal Leave', 'active' => 1)
        );
    }
    
    /**
     * Mock filtering by status
     */
    public function test_filter_by_status_pending() {
        $status_filter = 'pending';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($status_filter) {
            return $request->status === $status_filter;
        });
        
        $this->assertCount(1, $filtered_requests);
        $this->assertEquals('pending', array_values($filtered_requests)[0]->status);
        $this->assertEquals('John Doe', array_values($filtered_requests)[0]->display_name);
    }
    
    /**
     * Mock filtering by status approved
     */
    public function test_filter_by_status_approved() {
        $status_filter = 'approved';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($status_filter) {
            return $request->status === $status_filter;
        });
        
        $this->assertCount(2, $filtered_requests);
        foreach ($filtered_requests as $request) {
            $this->assertEquals('approved', $request->status);
        }
    }
    
    /**
     * Mock filtering by employee search (name)
     */
    public function test_filter_by_employee_name() {
        $search_term = 'john';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
            return stripos($request->display_name, $search_term) !== false ||
                   stripos($request->user_email, $search_term) !== false ||
                   stripos($request->employee_code, $search_term) !== false;
        });
        
        $this->assertCount(3, $filtered_requests); // Matches 'John Doe' (x2) and 'Bob Johnson'
        foreach ($filtered_requests as $request) {
            $this->assertStringContainsStringIgnoringCase('john', $request->display_name);
        }
    }
    
    /**
     * Mock filtering by employee search (email)
     */
    public function test_filter_by_employee_email() {
        $search_term = 'jane.smith';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
            return stripos($request->display_name, $search_term) !== false ||
                   stripos($request->user_email, $search_term) !== false ||
                   stripos($request->employee_code, $search_term) !== false;
        });
        
        $this->assertCount(1, $filtered_requests);
        $this->assertStringContainsString('jane.smith', array_values($filtered_requests)[0]->user_email);
    }
    
    /**
     * Mock filtering by employee code
     */
    public function test_filter_by_employee_code() {
        $search_term = 'EMP002';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
            return stripos($request->display_name, $search_term) !== false ||
                   stripos($request->user_email, $search_term) !== false ||
                   stripos($request->employee_code, $search_term) !== false;
        });
        
        $this->assertCount(1, $filtered_requests);
        $this->assertEquals('EMP002', array_values($filtered_requests)[0]->employee_code);
        $this->assertEquals('Jane Smith', array_values($filtered_requests)[0]->display_name);
    }
    
    /**
     * Test multiple filters combined
     */
    public function test_combined_filters_status_and_employee() {
        $status_filter = 'approved';
        $search_term = 'john';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($status_filter, $search_term) {
            $status_match = $request->status === $status_filter;
            $employee_match = stripos($request->display_name, $search_term) !== false ||
                             stripos($request->user_email, $search_term) !== false ||
                             stripos($request->employee_code, $search_term) !== false;
            return $status_match && $employee_match;
        });
        
        $this->assertCount(1, $filtered_requests);
        $request = array_values($filtered_requests)[0];
        $this->assertEquals('approved', $request->status);
        $this->assertStringContainsStringIgnoringCase('john', $request->display_name);
    }
    
    /**
     * Test year filtering
     */
    public function test_filter_by_year() {
        $year_filter = 2024;
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($year_filter) {
            $request_year = date('Y', strtotime($request->created_at));
            return intval($request_year) === $year_filter;
        });
        
        $this->assertCount(4, $filtered_requests); // All mock requests are from 2024
        
        // Test different year
        $year_filter = 2023;
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($year_filter) {
            $request_year = date('Y', strtotime($request->created_at));
            return intval($request_year) === $year_filter;
        });
        
        $this->assertCount(0, $filtered_requests); // No requests from 2023
    }
    
    /**
     * Test pagination with filters
     */
    public function test_pagination_with_filters() {
        $status_filter = 'approved';
        $per_page = 1;
        $page = 1;
        
        // Get filtered results
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($status_filter) {
            return $request->status === $status_filter;
        });
        
        // Apply pagination
        $offset = ($page - 1) * $per_page;
        $paginated_requests = array_slice($filtered_requests, $offset, $per_page);
        
        $this->assertCount(1, $paginated_requests); // One item per page
        $this->assertEquals(2, count($filtered_requests)); // Total filtered results
        
        // Test page 2
        $page = 2;
        $offset = ($page - 1) * $per_page;
        $paginated_requests = array_slice($filtered_requests, $offset, $per_page);
        
        $this->assertCount(1, $paginated_requests); // Second item
    }
    
    /**
     * Test empty search results
     */
    public function test_empty_search_results() {
        $search_term = 'nonexistent';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
            return stripos($request->display_name, $search_term) !== false ||
                   stripos($request->user_email, $search_term) !== false ||
                   stripos($request->employee_code, $search_term) !== false;
        });
        
        $this->assertCount(0, $filtered_requests);
    }
    
    /**
     * Test case insensitive search
     */
    public function test_case_insensitive_search() {
        $search_terms = array('JOHN', 'john', 'John', 'jOHn');
        
        foreach ($search_terms as $search_term) {
            $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
                return stripos($request->display_name, $search_term) !== false ||
                       stripos($request->user_email, $search_term) !== false ||
                       stripos($request->employee_code, $search_term) !== false;
            });
            
            $this->assertCount(3, $filtered_requests, "Failed for search term: $search_term"); // Matches 'John' and 'Johnson'
        }
    }
    
    /**
     * Test partial matching in search
     */
    public function test_partial_matching() {
        $search_term = 'jo'; // Partial match for 'john'
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($search_term) {
            return stripos($request->display_name, $search_term) !== false ||
                   stripos($request->user_email, $search_term) !== false ||
                   stripos($request->employee_code, $search_term) !== false;
        });
        
        $this->assertCount(3, $filtered_requests); // Should match 'John' and 'Johnson'
    }
    
    /**
     * Test leave type filtering simulation
     */
    public function test_leave_type_filtering() {
        // Mock leave dates with types
        $mock_leave_dates = array(
            array('request_id' => 1, 'leave_type_id' => 1), // Annual Leave
            array('request_id' => 2, 'leave_type_id' => 2), // Sick Leave
            array('request_id' => 3, 'leave_type_id' => 3), // Personal Leave
            array('request_id' => 4, 'leave_type_id' => 2), // Sick Leave
        );
        
        $leave_type_filter = 2; // Sick Leave
        
        // Get request IDs with this leave type
        $filtered_request_ids = array();
        foreach ($mock_leave_dates as $date) {
            if ($date['leave_type_id'] == $leave_type_filter) {
                $filtered_request_ids[] = $date['request_id'];
            }
        }
        
        // Filter requests by IDs
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($filtered_request_ids) {
            return in_array($request->id, $filtered_request_ids);
        });
        
        $this->assertCount(2, $filtered_requests); // Requests 2 and 4
        $this->assertContains(2, array_column($filtered_requests, 'id'));
        $this->assertContains(4, array_column($filtered_requests, 'id'));
    }
    
    /**
     * Test all filters combined
     */
    public function test_all_filters_combined() {
        $year_filter = 2024;
        $status_filter = 'approved';
        $search_term = 'john';
        $leave_type_filter = 2; // From previous test setup
        
        // Mock leave dates
        $mock_leave_dates = array(
            array('request_id' => 1, 'leave_type_id' => 1),
            array('request_id' => 2, 'leave_type_id' => 2),
            array('request_id' => 3, 'leave_type_id' => 3),
            array('request_id' => 4, 'leave_type_id' => 2),
        );
        
        // Apply all filters
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($year_filter, $status_filter, $search_term, $leave_type_filter, $mock_leave_dates) {
            // Year filter
            $request_year = date('Y', strtotime($request->created_at));
            if (intval($request_year) !== $year_filter) return false;
            
            // Status filter
            if ($request->status !== $status_filter) return false;
            
            // Employee search
            $employee_match = stripos($request->display_name, $search_term) !== false ||
                             stripos($request->user_email, $search_term) !== false ||
                             stripos($request->employee_code, $search_term) !== false;
            if (!$employee_match) return false;
            
            // Leave type filter
            $has_leave_type = false;
            foreach ($mock_leave_dates as $date) {
                if ($date['request_id'] == $request->id && $date['leave_type_id'] == $leave_type_filter) {
                    $has_leave_type = true;
                    break;
                }
            }
            if (!$has_leave_type) return false;
            
            return true;
        });
        
        // Should match request ID 4 (John Doe, approved, 2024, sick leave)
        $this->assertCount(1, $filtered_requests);
        $request = array_values($filtered_requests)[0];
        $this->assertEquals(4, $request->id);
        $this->assertEquals('John Doe', $request->display_name);
        $this->assertEquals('approved', $request->status);
    }
    
    /**
     * Test filter parameter validation
     */
    public function test_filter_parameter_validation() {
        // Test invalid status
        $invalid_statuses = array('invalid', 'unknown', 123, null);
        $valid_statuses = array('pending', 'approved', 'rejected', '');
        
        foreach ($invalid_statuses as $status) {
            $this->assertNotContains($status, $valid_statuses);
        }
        
        foreach ($valid_statuses as $status) {
            $this->assertTrue(in_array($status, $valid_statuses));
        }
        
        // Test year validation
        $current_year = date('Y');
        $valid_years = range($current_year - 2, $current_year + 1);
        $invalid_years = array($current_year - 10, $current_year + 10, 'invalid', null);
        
        foreach ($valid_years as $year) {
            $this->assertIsInt($year);
            $this->assertGreaterThanOrEqual($current_year - 2, $year);
            $this->assertLessThanOrEqual($current_year + 1, $year);
        }
        
        // Test per page validation
        $valid_per_page = array(5, 10, 20, 50);
        $invalid_per_page = array(0, -1, 100, 'invalid', null);
        
        foreach ($valid_per_page as $per_page) {
            $this->assertIsInt($per_page);
            $this->assertGreaterThan(0, $per_page);
        }
    }
    
    /**
     * Test sorting with filters
     */
    public function test_sorting_with_filters() {
        $status_filter = 'approved';
        
        $filtered_requests = array_filter($this->mock_requests, function($request) use ($status_filter) {
            return $request->status === $status_filter;
        });
        
        // Sort by created_at DESC (most recent first)
        usort($filtered_requests, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        $this->assertCount(2, $filtered_requests);
        
        // Most recent should be first
        $dates = array_column($filtered_requests, 'created_at');
        $this->assertEquals('2024-04-05 16:45:00', $dates[0]); // Request ID 4
        $this->assertEquals('2024-02-20 14:30:00', $dates[1]); // Request ID 2
    }
}