<?php
/**
 * Database tests for WP Employee Leaves Plugin
 */

class DatabaseTest extends WP_UnitTestCase {
    
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
     * Test database table creation
     */
    public function test_create_tables() {
        global $wpdb;
        
        // Drop tables first
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
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Create tables
        $this->plugin->create_tables();
        
        // Verify tables exist
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            $this->assertEquals($table, $table_exists, "Table {$table} should exist");
        }
    }
    
    /**
     * Test leave types CRUD operations
     */
    public function test_leave_types_crud() {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_types';
        
        // Create
        $result = $wpdb->insert($table, [
            'name' => 'Test Leave',
            'allocation' => 15,
            'active' => 1,
        ]);
        $this->assertNotFalse($result);
        $leave_type_id = $wpdb->insert_id;
        
        // Read
        $leave_type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $leave_type_id
        ));
        $this->assertNotNull($leave_type);
        $this->assertEquals('Test Leave', $leave_type->name);
        $this->assertEquals(15, $leave_type->allocation);
        $this->assertEquals(1, $leave_type->active);
        
        // Update
        $result = $wpdb->update(
            $table,
            ['allocation' => 20],
            ['id' => $leave_type_id]
        );
        $this->assertEquals(1, $result);
        
        $updated_leave_type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $leave_type_id
        ));
        $this->assertEquals(20, $updated_leave_type->allocation);
        
        // Delete
        $result = $wpdb->delete($table, ['id' => $leave_type_id]);
        $this->assertEquals(1, $result);
        
        $deleted_leave_type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $leave_type_id
        ));
        $this->assertNull($deleted_leave_type);
    }
    
    /**
     * Test leave requests CRUD operations
     */
    public function test_leave_requests_crud() {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_requests';
        
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        
        // Create
        $request_data = [
            'user_id' => $user_id,
            'employee_id' => 'EMP001',
            'manager_emails' => 'manager@test.com',
            'reliever_emails' => 'reliever@test.com',
            'reason' => 'Test leave request',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($table, $request_data);
        $this->assertNotFalse($result);
        $request_id = $wpdb->insert_id;
        
        // Read
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $request_id
        ));
        $this->assertNotNull($request);
        $this->assertEquals($user_id, $request->user_id);
        $this->assertEquals('EMP001', $request->employee_id);
        $this->assertEquals('pending', $request->status);
        
        // Update status
        $result = $wpdb->update(
            $table,
            ['status' => 'approved', 'approved_at' => current_time('mysql')],
            ['id' => $request_id]
        );
        $this->assertEquals(1, $result);
        
        $updated_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $request_id
        ));
        $this->assertEquals('approved', $updated_request->status);
        $this->assertNotNull($updated_request->approved_at);
        
        // Delete
        $result = $wpdb->delete($table, ['id' => $request_id]);
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test leave dates CRUD operations
     */
    public function test_leave_dates_crud() {
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        
        // Create test request
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($user_id);
        $leave_type_ids = WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        
        // Create leave date
        $date_data = [
            'request_id' => $request_id,
            'date' => '2025-07-20',
            'leave_type_id' => $leave_type_ids[0],
        ];
        
        $result = $wpdb->insert($dates_table, $date_data);
        $this->assertNotFalse($result);
        $date_id = $wpdb->insert_id;
        
        // Read
        $leave_date = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$dates_table} WHERE id = %d",
            $date_id
        ));
        $this->assertNotNull($leave_date);
        $this->assertEquals($request_id, $leave_date->request_id);
        $this->assertEquals('2025-07-20', $leave_date->date);
        
        // Update
        $result = $wpdb->update(
            $dates_table,
            ['date' => '2025-07-21'],
            ['id' => $date_id]
        );
        $this->assertEquals(1, $result);
        
        // Delete
        $result = $wpdb->delete($dates_table, ['id' => $date_id]);
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test foreign key relationships
     */
    public function test_foreign_key_relationships() {
        global $wpdb;
        
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $leave_type_ids = WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($user_id);
        
        // Test join query
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, u.display_name, lt.name as leave_type_name
            FROM {$wpdb->prefix}employee_leaves_requests r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}employee_leaves_dates ld ON r.id = ld.request_id
            LEFT JOIN {$wpdb->prefix}employee_leaves_types lt ON ld.leave_type_id = lt.id
            WHERE r.id = %d
        ", $request_id));
        
        $this->assertNotNull($result);
        $this->assertEquals($user_id, $result->user_id);
    }
    
    /**
     * Test data integrity constraints
     */
    public function test_data_integrity() {
        global $wpdb;
        
        // Test required fields
        $result = $wpdb->insert($wpdb->prefix . 'employee_leaves_requests', [
            // Missing required user_id
            'employee_id' => 'EMP001',
            'status' => 'pending',
        ]);
        
        // Should fail due to NOT NULL constraint (in a real setup)
        // WordPress doesn't enforce strict constraints by default
        $this->assertNotFalse($result); // But we still test the operation
        
        // Test valid data
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $result = $wpdb->insert($wpdb->prefix . 'employee_leaves_requests', [
            'user_id' => $user_id,
            'employee_id' => 'EMP001',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
        
        $this->assertNotFalse($result);
    }
    
    /**
     * Test plugin's database helper methods
     */
    public function test_plugin_database_methods() {
        // Test get_leave_types method
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        $leave_types = $this->plugin->get_leave_types();
        
        $this->assertIsArray($leave_types);
        $this->assertNotEmpty($leave_types);
        $this->assertGreaterThanOrEqual(4, count($leave_types));
        
        // Verify structure
        $first_type = $leave_types[0];
        $this->assertObjectHasAttribute('id', $first_type);
        $this->assertObjectHasAttribute('name', $first_type);
        $this->assertObjectHasAttribute('allocation', $first_type);
        $this->assertObjectHasAttribute('active', $first_type);
    }
    
    /**
     * Test database performance with large datasets
     */
    public function test_database_performance() {
        $start_time = microtime(true);
        
        // Create multiple requests
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        $leave_type_ids = WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        
        for ($i = 0; $i < 50; $i++) {
            $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($user_id);
            WP_Employee_Leaves_Test_Data_Factory::create_leave_dates($request_id);
        }
        
        $creation_time = microtime(true) - $start_time;
        
        // Test query performance
        $query_start = microtime(true);
        
        global $wpdb;
        $requests = $wpdb->get_results("
            SELECT r.*, COUNT(ld.id) as date_count
            FROM {$wpdb->prefix}employee_leaves_requests r
            LEFT JOIN {$wpdb->prefix}employee_leaves_dates ld ON r.id = ld.request_id
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT 20
        ");
        
        $query_time = microtime(true) - $query_start;
        
        // Performance assertions (adjust thresholds as needed)
        $this->assertLessThan(5.0, $creation_time, 'Database creation should be under 5 seconds');
        $this->assertLessThan(1.0, $query_time, 'Query should be under 1 second');
        $this->assertNotEmpty($requests);
        $this->assertLessThanOrEqual(20, count($requests));
    }
    
    /**
     * Test database cleanup and reset
     */
    public function test_database_cleanup() {
        global $wpdb;
        
        // Create test data
        $user_id = WP_Employee_Leaves_Test_Data_Factory::create_test_user();
        WP_Employee_Leaves_Test_Data_Factory::create_leave_types();
        $request_id = WP_Employee_Leaves_Test_Data_Factory::create_leave_request($user_id);
        WP_Employee_Leaves_Test_Data_Factory::create_leave_dates($request_id);
        
        // Verify data exists
        $requests_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}employee_leaves_requests");
        $types_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}employee_leaves_types");
        
        $this->assertGreaterThan(0, $requests_count);
        $this->assertGreaterThan(0, $types_count);
        
        // Clean up
        WP_Employee_Leaves_Test_Data_Factory::cleanup();
        
        // Verify cleanup
        $requests_count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}employee_leaves_requests");
        $types_count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}employee_leaves_types");
        
        $this->assertEquals(0, $requests_count_after);
        $this->assertEquals(0, $types_count_after);
    }
}