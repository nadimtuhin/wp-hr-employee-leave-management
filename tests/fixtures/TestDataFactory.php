<?php
/**
 * Test Data Factory for WP Employee Leaves Plugin
 */

class WP_Employee_Leaves_Test_Data_Factory {
    
    /**
     * Create test leave types
     */
    public static function create_leave_types() {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_types';
        
        $leave_types = [
            ['name' => 'Annual Leave', 'allocation' => 25, 'active' => 1],
            ['name' => 'Sick Leave', 'allocation' => 10, 'active' => 1],
            ['name' => 'Personal Leave', 'allocation' => 5, 'active' => 1],
            ['name' => 'Emergency Leave', 'allocation' => 3, 'active' => 1],
        ];
        
        $ids = [];
        foreach ($leave_types as $type) {
            $wpdb->insert($table, $type);
            $ids[] = $wpdb->insert_id;
        }
        
        return $ids;
    }
    
    /**
     * Create test leave request
     */
    public static function create_leave_request($user_id = null, $status = 'pending') {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_requests';
        
        if (!$user_id) {
            $user_id = self::create_test_user();
        }
        
        $request_data = [
            'user_id' => $user_id,
            'employee_id' => 'EMP' . str_pad($user_id, 3, '0', STR_PAD_LEFT),
            'manager_emails' => 'manager@test.com',
            'reliever_emails' => 'reliever@test.com',
            'reason' => 'Test leave request',
            'status' => $status,
            'created_at' => current_time('mysql'),
        ];
        
        if ($status === 'approved') {
            $request_data['approved_at'] = current_time('mysql');
        }
        
        $wpdb->insert($table, $request_data);
        return $wpdb->insert_id;
    }
    
    /**
     * Create test leave dates
     */
    public static function create_leave_dates($request_id, $dates = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_dates';
        
        if (!$dates) {
            $dates = [
                ['date' => date('Y-m-d', strtotime('+1 day')), 'leave_type_id' => 1],
                ['date' => date('Y-m-d', strtotime('+2 days')), 'leave_type_id' => 1],
            ];
        }
        
        $ids = [];
        foreach ($dates as $date_data) {
            $date_data['request_id'] = $request_id;
            $wpdb->insert($table, $date_data);
            $ids[] = $wpdb->insert_id;
        }
        
        return $ids;
    }
    
    /**
     * Create test user
     */
    public static function create_test_user($role = 'subscriber') {
        return wp_insert_user([
            'user_login' => 'testuser_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@test.com',
            'user_pass' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => $role,
        ]);
    }
    
    /**
     * Create admin user
     */
    public static function create_admin_user() {
        return self::create_test_user('administrator');
    }
    
    /**
     * Clean up test data
     */
    public static function cleanup() {
        global $wpdb;
        
        // Clean up tables
        $tables = [
            $wpdb->prefix . 'employee_leaves_requests',
            $wpdb->prefix . 'employee_leaves_dates', 
            $wpdb->prefix . 'employee_leaves_types',
            $wpdb->prefix . 'employee_leaves_balances',
            $wpdb->prefix . 'employee_leaves_logs',
            $wpdb->prefix . 'employee_leaves_notifications',
            $wpdb->prefix . 'employee_leaves_email_templates',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$table}");
        }
        
        // Clean up test users
        $test_users = get_users(['meta_key' => 'test_user', 'meta_value' => '1']);
        foreach ($test_users as $user) {
            wp_delete_user($user->ID);
        }
    }
    
    /**
     * Create email template
     */
    public static function create_email_template($type = 'submission') {
        global $wpdb;
        $table = $wpdb->prefix . 'employee_leaves_email_templates';
        
        $template_data = [
            'template_type' => $type,
            'subject' => 'Test Email Subject',
            'body' => 'Test email body with {{employee_name}} placeholder',
            'active' => 1,
        ];
        
        $wpdb->insert($table, $template_data);
        return $wpdb->insert_id;
    }
}