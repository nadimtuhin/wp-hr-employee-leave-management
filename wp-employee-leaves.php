<?php
/**
 * Plugin Name: WP HR Employee Leave Management
 * Description: A comprehensive employee leave management system for WordPress
 * Version: 1.6.0
 * Author: HR Management Solutions
 * License: GPL v2 or later
 * Text Domain: wp-employee-leaves
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_EMPLOYEE_LEAVES_VERSION', '1.6.0');
define('WP_EMPLOYEE_LEAVES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EMPLOYEE_LEAVES_PLUGIN_URL', plugin_dir_url(__FILE__));

class WPEmployeeLeaves {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Ensure database structure is up to date
        add_action('plugins_loaded', array($this, 'update_database_structure'));
        
        // Check for plugin updates
        add_action('plugins_loaded', array($this, 'check_version_update'));
    }
    
    public function init() {
        load_plugin_textdomain('wp-employee-leaves', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('wp_ajax_approve_leave_request', array($this, 'handle_approve_request'));
            add_action('wp_ajax_reject_leave_request', array($this, 'handle_reject_request'));
            add_action('wp_ajax_create_leave_page', array($this, 'handle_create_leave_page'));
            add_action('wp_ajax_create_my_requests_page', array($this, 'handle_create_my_requests_page'));
            add_action('wp_ajax_add_shortcode_to_page', array($this, 'handle_add_shortcode_to_page'));
        } else {
            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        }
        
        // AJAX handlers should be registered for both admin and frontend
        add_action('wp_ajax_submit_leave_request', array($this, 'handle_leave_request_submission'));
        add_action('wp_ajax_nopriv_submit_leave_request', array($this, 'handle_leave_request_submission'));
        
        // Contact suggestions AJAX endpoint
        add_action('wp_ajax_get_contact_suggestions', array($this, 'handle_contact_suggestions'));
        
        // REST API endpoints for email approval links
        add_action('rest_api_init', array($this, 'register_approval_endpoints'));
        
        // Schedule token cleanup
        add_action('wp_employee_leaves_cleanup_tokens', array($this, 'cleanup_expired_tokens'));
        if (!wp_next_scheduled('wp_employee_leaves_cleanup_tokens')) {
            wp_schedule_event(time(), 'daily', 'wp_employee_leaves_cleanup_tokens');
        }
        
        add_shortcode('employee_leave_form', array($this, 'leave_form_shortcode'));
        add_shortcode('my_leave_requests', array($this, 'my_leave_requests_shortcode'));
    }
    
    public function frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-employee-leaves-frontend', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'frontend/css/style.css', array(), WP_EMPLOYEE_LEAVES_VERSION);
        wp_enqueue_script('wp-employee-leaves-frontend', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'frontend/js/script.js', array('jquery'), WP_EMPLOYEE_LEAVES_VERSION, true);
        
        wp_localize_script('wp-employee-leaves-frontend', 'wp_employee_leaves_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('employee_leave_nonce'),
            'strings' => array(
                'submitting' => __('Submitting...', 'wp-employee-leaves'),
                'submit_leave_request' => __('Submit Leave Request', 'wp-employee-leaves'),
                'duplicate_dates' => __('Duplicate dates found:', 'wp-employee-leaves'),
                'remove_duplicates' => __('Please remove duplicate entries.', 'wp-employee-leaves'),
                'enter_employee_id' => __('Please enter your employee ID.', 'wp-employee-leaves'),
                'select_date_type' => __('Please select at least one date and leave type.', 'wp-employee-leaves'),
                'provide_reason' => __('Please provide a reason for your leave.', 'wp-employee-leaves'),
                'fix_email_errors' => __('Please fix the email validation errors before submitting.', 'wp-employee-leaves'),
                'submission_error' => __('An error occurred while submitting your request. Please try again.', 'wp-employee-leaves'),
                'invalid_emails' => __('Invalid email addresses in', 'wp-employee-leaves')
            )
        ));
    }
    
    /**
     * Get translated status label
     */
    private function get_status_label($status) {
        switch($status) {
            case 'pending':
                return __('Pending', 'wp-employee-leaves');
            case 'approved':
                return __('Approved', 'wp-employee-leaves');
            case 'rejected':
                return __('Rejected', 'wp-employee-leaves');
            default:
                return ucfirst($status);
        }
    }
    
    public function admin_menu() {
        add_menu_page(
            __('Employee Leaves', 'wp-employee-leaves'),
            __('Employee Leaves', 'wp-employee-leaves'),
            'manage_options',
            'wp-employee-leaves',
            array($this, 'admin_page_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'wp-employee-leaves',
            __('Dashboard', 'wp-employee-leaves'),
            __('Dashboard', 'wp-employee-leaves'),
            'manage_options',
            'wp-employee-leaves',
            array($this, 'admin_page_dashboard')
        );
        
        add_submenu_page(
            'wp-employee-leaves',
            __('Leave Requests', 'wp-employee-leaves'),
            __('Leave Requests', 'wp-employee-leaves'),
            'manage_options',
            'wp-employee-leaves-requests',
            array($this, 'admin_page_requests')
        );
        
        add_submenu_page(
            'wp-employee-leaves',
            __('Employees', 'wp-employee-leaves'),
            __('Employees', 'wp-employee-leaves'),
            'manage_options',
            'wp-employee-leaves-employees',
            array($this, 'admin_page_employees')
        );
        
        add_submenu_page(
            'wp-employee-leaves',
            __('Settings', 'wp-employee-leaves'),
            __('Settings', 'wp-employee-leaves'),
            'manage_options',
            'wp-employee-leaves-settings',
            array($this, 'admin_page_settings')
        );
    }
    
    public function admin_scripts($hook) {
        if (strpos($hook, 'wp-employee-leaves') !== false) {
            wp_enqueue_style('wp-employee-leaves-admin', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'admin/css/admin.css', array(), WP_EMPLOYEE_LEAVES_VERSION);
            wp_enqueue_script('wp-employee-leaves-admin', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), WP_EMPLOYEE_LEAVES_VERSION, true);
            
            wp_localize_script('wp-employee-leaves-admin', 'wp_employee_leaves_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_employee_leaves_admin'),
                'approve_nonce' => wp_create_nonce('approve_leave_nonce'),
                'reject_nonce' => wp_create_nonce('reject_leave_nonce'),
                'strings' => array(
                    'please_enter_page_title' => __('Please enter a page title', 'wp-employee-leaves'),
                    'creating' => __('Creating...', 'wp-employee-leaves'),
                    'error' => __('Error:', 'wp-employee-leaves'),
                    'unknown_error' => __('Unknown error occurred', 'wp-employee-leaves'),
                    'create_page' => __('Create Page', 'wp-employee-leaves'),
                    'page_creation_error' => __('An error occurred while creating the page. Details:', 'wp-employee-leaves'),
                    'please_select_page' => __('Please select a page', 'wp-employee-leaves'),
                    'adding' => __('Adding...', 'wp-employee-leaves'),
                    'add_shortcode' => __('Add Shortcode', 'wp-employee-leaves'),
                    'shortcode_error' => __('An error occurred while adding the shortcode.', 'wp-employee-leaves'),
                    'approve_confirm' => __('Are you sure you want to approve this leave request?', 'wp-employee-leaves'),
                    'approving' => __('Approving...', 'wp-employee-leaves'),
                    'approved' => __('Approved', 'wp-employee-leaves'),
                    'approve_success' => __('Leave request approved successfully!', 'wp-employee-leaves'),
                    'network_error' => __('Network error occurred while processing the request.', 'wp-employee-leaves'),
                    'approve' => __('Approve', 'wp-employee-leaves'),
                    'reject_confirm' => __('Are you sure you want to reject this leave request?', 'wp-employee-leaves'),
                    'rejecting' => __('Rejecting...', 'wp-employee-leaves'),
                    'rejected' => __('Rejected', 'wp-employee-leaves'),
                    'reject_success' => __('Leave request rejected successfully!', 'wp-employee-leaves'),
                    'reject' => __('Reject', 'wp-employee-leaves'),
                    'invalid_request_id' => __('Invalid request ID.', 'wp-employee-leaves'),
                    'fill_required_fields' => __('Please fill in all required fields.', 'wp-employee-leaves'),
                    'saving' => __('Saving...', 'wp-employee-leaves'),
                    'copy_shortcode' => __('Copy Shortcode', 'wp-employee-leaves'),
                    'copied' => __('Copied!', 'wp-employee-leaves')
                )
            ));
        }
    }
    
    public function admin_page_dashboard() {
        global $wpdb;
        
        $current_year = date('Y');
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        // Get statistics
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $requests_table WHERE YEAR(created_at) = %d",
            $current_year
        ));
        
        $pending_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $requests_table WHERE status = 'pending' AND YEAR(created_at) = %d",
            $current_year
        ));
        
        $approved_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $requests_table WHERE status = 'approved' AND YEAR(created_at) = %d",
            $current_year
        ));
        
        $rejected_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $requests_table WHERE status = 'rejected' AND YEAR(created_at) = %d",
            $current_year
        ));
        
        // Get recent requests
        $recent_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM $requests_table r 
             JOIN {$wpdb->users} u ON r.employee_id = u.ID 
             WHERE YEAR(r.created_at) = %d 
             ORDER BY r.created_at DESC 
             LIMIT 5",
            $current_year
        ));
        
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-calendar-alt" style="margin-right: 10px; color: #3498db;"></span>
                <?php echo esc_html__('Employee Leaves Dashboard', 'wp-employee-leaves'); ?>
            </h1>
            
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>
                        <span class="dashicons dashicons-welcome-view-site" style="margin-right: 10px; font-size: 28px; vertical-align: middle;"></span>
                        <?php echo esc_html__('Welcome to Employee Leaves Management', 'wp-employee-leaves'); ?>
                    </h2>
                    <p><?php echo esc_html__('Manage your employee leave requests efficiently with our comprehensive dashboard. Current year: ', 'wp-employee-leaves') . '<strong>' . $current_year . '</strong>'; ?></p>
                    <div style="margin-top: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-requests')); ?>" class="button button-hero" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 15px; transition: all 0.3s ease;">
                            <?php _e('View All Requests', 'wp-employee-leaves'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-settings')); ?>" class="button button-hero" style="background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                            <?php _e('Settings', 'wp-employee-leaves'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="number"><?php echo esc_html($total_requests); ?></div>
                    <div class="label"><?php _e('Total Requests', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item pending">
                    <div class="number"><?php echo esc_html($pending_requests); ?></div>
                    <div class="label"><?php _e('Pending Approval', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item approved">
                    <div class="number"><?php echo esc_html($approved_requests); ?></div>
                    <div class="label"><?php _e('Approved', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item rejected">
                    <div class="number"><?php echo esc_html($rejected_requests); ?></div>
                    <div class="label"><?php _e('Rejected', 'wp-employee-leaves'); ?></div>
                </div>
            </div>
            
            <div class="employee-leaves-dashboard">
                <div class="dashboard-card">
                    <h3><?php _e('Recent Leave Requests', 'wp-employee-leaves'); ?></h3>
                    <div class="card-content">
                        <?php if (empty($recent_requests)): ?>
                            <p><?php _e('No recent leave requests.', 'wp-employee-leaves'); ?></p>
                        <?php else: ?>
                            <table class="wp-list-table widefat">
                                <thead>
                                    <tr>
                                        <th><?php _e('Employee', 'wp-employee-leaves'); ?></th>
                                        <th><?php _e('Status', 'wp-employee-leaves'); ?></th>
                                        <th><?php _e('Date', 'wp-employee-leaves'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <tr>
                                            <td><?php echo esc_html($request->display_name); ?></td>
                                            <td>
                                                <span class="status-<?php echo esc_attr($request->status); ?>">
                                                    <?php echo esc_html($this->get_status_label($request->status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(date('M j, Y', strtotime($request->created_at))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="text-align: center; margin-top: 15px;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-requests')); ?>" class="button button-primary">
                                    <?php _e('View All Requests', 'wp-employee-leaves'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3><?php _e('Quick Actions', 'wp-employee-leaves'); ?></h3>
                    <div class="card-content">
                        <div class="quick-actions-grid">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-requests')); ?>" class="button button-primary">
                                <span class="dashicons dashicons-list-view" style="margin-right: 8px;"></span>
                                <?php _e('Manage Requests', 'wp-employee-leaves'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-settings')); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-admin-settings" style="margin-right: 8px;"></span>
                                <?php _e('Settings', 'wp-employee-leaves'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-employees')); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-admin-users" style="margin-right: 8px;"></span>
                                <?php _e('Employee Management', 'wp-employee-leaves'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3><?php _e('System Overview', 'wp-employee-leaves'); ?></h3>
                    <div class="card-content">
                        <?php 
                        $total_employees = count_users()['total_users'];
                        $hr_email = get_option('wp_employee_leaves_hr_email', get_option('admin_email'));
                        $email_notifications = get_option('wp_employee_leaves_email_notifications_enabled', 1);
                        ?>
                        <div style="display: grid; gap: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: linear-gradient(135deg, #e8f4f8, #d1ecf1); border-radius: 8px;">
                                <span class="dashicons dashicons-admin-users" style="color: #3498db; font-size: 20px;"></span>
                                <div>
                                    <strong><?php echo esc_html($total_employees); ?></strong> <?php _e('Total Users', 'wp-employee-leaves'); ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: linear-gradient(135deg, <?php echo esc_attr($hr_email ? '#d4edda' : '#f8d7da'); ?>, <?php echo esc_attr($hr_email ? '#c3e6cb' : '#f5c6cb'); ?>); border-radius: 8px;">
                                <span class="dashicons dashicons-email" style="color: <?php echo esc_attr($hr_email ? '#27ae60' : '#e74c3c'); ?>; font-size: 20px;"></span>
                                <div>
                                    <strong><?php echo esc_html($hr_email ? __('HR Email Configured', 'wp-employee-leaves') : __('HR Email Missing', 'wp-employee-leaves')); ?></strong>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: linear-gradient(135deg, <?php echo esc_attr($email_notifications ? '#d4edda' : '#fff3cd'); ?>, <?php echo esc_attr($email_notifications ? '#c3e6cb' : '#ffeaa7'); ?>); border-radius: 8px;">
                                <span class="dashicons dashicons-email-alt" style="color: <?php echo esc_attr($email_notifications ? '#27ae60' : '#f39c12'); ?>; font-size: 20px;"></span>
                                <div>
                                    <strong><?php echo esc_html($email_notifications ? __('Notifications Enabled', 'wp-employee-leaves') : __('Notifications Disabled', 'wp-employee-leaves')); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-settings')); ?>" class="button button-secondary">
                                <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
                                <?php _e('Configure System', 'wp-employee-leaves'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_page_requests() {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        // Get filter parameters
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $employee_search = isset($_GET['employee_search']) ? sanitize_text_field($_GET['employee_search']) : '';
        $leave_type_filter = isset($_GET['leave_type']) ? intval($_GET['leave_type']) : '';
        
        // Pagination setup
        $per_page_options = array(5, 10, 20, 50);
        $per_page = isset($_GET['per_page']) && in_array(intval($_GET['per_page']), $per_page_options) ? intval($_GET['per_page']) : 10;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Build WHERE clause with filters
        $where_conditions = array("YEAR(r.created_at) = %d");
        $where_values = array($year);
        
        if (!empty($status_filter)) {
            $where_conditions[] = "r.status = %s";
            $where_values[] = $status_filter;
        }
        
        if (!empty($employee_search)) {
            $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR r.employee_code LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($employee_search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($leave_type_filter)) {
            $where_conditions[] = "r.id IN (SELECT DISTINCT d.request_id FROM $dates_table d WHERE d.leave_type_id = %d)";
            $where_values[] = $leave_type_filter;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) 
                       FROM $requests_table r 
                       JOIN {$wpdb->users} u ON r.employee_id = u.ID 
                       $where_clause";
        
        $total_requests = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        $total_pages = ceil($total_requests / $per_page);
        
        // Get paginated leave requests with filters
        $main_query = "SELECT r.*, u.display_name, u.user_email 
                      FROM $requests_table r 
                      JOIN {$wpdb->users} u ON r.employee_id = u.ID 
                      $where_clause 
                      ORDER BY r.created_at DESC
                      LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $requests = $wpdb->get_results($wpdb->prepare($main_query, $query_values));
        
        // Get all leave types for filter dropdown
        $leave_types = $wpdb->get_results("SELECT * FROM $leave_types_table WHERE active = 1 ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Leave Requests', 'wp-employee-leaves'); ?></h1>
            
            <!-- Enhanced Filters Section -->
            <form method="get" action="" class="requests-filters-form">
                <input type="hidden" name="page" value="wp-employee-leaves-requests">
                <div class="requests-filters">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label for="year-filter"><?php _e('Year:', 'wp-employee-leaves'); ?></label>
                            <select name="year" id="year-filter">
                                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo esc_attr($y); ?>" <?php selected($year, $y); ?>>
                                        <?php echo esc_html($y); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="status-filter"><?php _e('Status:', 'wp-employee-leaves'); ?></label>
                            <select name="status" id="status-filter">
                                <option value=""><?php _e('All Statuses', 'wp-employee-leaves'); ?></option>
                                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'wp-employee-leaves'); ?></option>
                                <option value="approved" <?php selected($status_filter, 'approved'); ?>><?php _e('Approved', 'wp-employee-leaves'); ?></option>
                                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'wp-employee-leaves'); ?></option>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="leave-type-filter"><?php _e('Leave Type:', 'wp-employee-leaves'); ?></label>
                            <select name="leave_type" id="leave-type-filter">
                                <option value=""><?php _e('All Leave Types', 'wp-employee-leaves'); ?></option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>" <?php selected($leave_type_filter, $type->id); ?>>
                                        <?php echo esc_html($type->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label for="employee-search"><?php _e('Search Employee:', 'wp-employee-leaves'); ?></label>
                            <input type="text" name="employee_search" id="employee-search" 
                                   value="<?php echo esc_attr($employee_search); ?>" 
                                   placeholder="<?php esc_attr_e('Name, email, or ID', 'wp-employee-leaves'); ?>">
                        </div>
                        
                        <div class="filter-item">
                            <label for="per-page-select"><?php _e('Per Page:', 'wp-employee-leaves'); ?></label>
                            <select name="per_page" id="per-page-select">
                                <?php foreach ($per_page_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>" <?php selected($per_page, $option); ?>>
                                        <?php echo esc_html($option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="button button-primary"><?php _e('Filter', 'wp-employee-leaves'); ?></button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-employee-leaves-requests')); ?>" 
                               class="button button-secondary"><?php _e('Clear', 'wp-employee-leaves'); ?></a>
                        </div>
                    </div>
                    
                    <div class="results-summary">
                        <?php 
                        $start = $total_requests > 0 ? $offset + 1 : 0;
                        $end = min($offset + $per_page, $total_requests);
                        
                        $filter_text = '';
                        if (!empty($status_filter) || !empty($employee_search) || !empty($leave_type_filter)) {
                            $filter_text = ' ' . __('(filtered)', 'wp-employee-leaves');
                        }
                        
                        printf(
                            __('Showing %d-%d of %d requests%s', 'wp-employee-leaves'),
                            $start,
                            $end,
                            $total_requests,
                            $filter_text
                        );
                        ?>
                    </div>
                </div>
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Employee', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Employee ID', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Leave Dates', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Leave Types', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Reason', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Status', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Submitted', 'wp-employee-leaves'); ?></th>
                        <th><?php _e('Actions', 'wp-employee-leaves'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="8"><?php _e('No leave requests found for this year.', 'wp-employee-leaves'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            // Get leave dates and types for this request
                            $leave_details = $wpdb->get_results($wpdb->prepare(
                                "SELECT d.leave_date, lt.name as leave_type_name 
                                 FROM $dates_table d 
                                 JOIN $leave_types_table lt ON d.leave_type_id = lt.id 
                                 WHERE d.request_id = %d 
                                 ORDER BY d.leave_date",
                                $request->id
                            ));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($request->display_name); ?></strong><br>
                                    <small><?php echo esc_html($request->user_email); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($request->employee_code ? $request->employee_code : 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <?php foreach ($leave_details as $detail): ?>
                                        <?php echo esc_html(date('M j, Y', strtotime($detail->leave_date))); ?><br>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ($leave_details as $detail): ?>
                                        <?php echo esc_html($detail->leave_type_name); ?><br>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo esc_html(wp_trim_words($request->reason, 10)); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($request->status); ?>">
                                        <?php echo esc_html($this->get_status_label($request->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($request->created_at))); ?></td>
                                <td>
                                    <?php if ($request->status === 'pending'): ?>
                                        <button class="button button-primary approve-request" 
                                                data-request-id="<?php echo esc_attr($request->id); ?>">
                                            <?php _e('Approve', 'wp-employee-leaves'); ?>
                                        </button>
                                        <button class="button button-secondary reject-request" 
                                                data-request-id="<?php echo esc_attr($request->id); ?>">
                                            <?php _e('Reject', 'wp-employee-leaves'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-<?php echo esc_attr($request->status === 'approved' ? 'yes' : 'no'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
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
                        
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo; Previous', 'wp-employee-leaves'),
                            'next_text' => __('Next &raquo;', 'wp-employee-leaves'),
                            'total' => $total_pages,
                            'current' => $current_page,
                            'show_all' => false,
                            'end_size' => 1,
                            'mid_size' => 2,
                            'type' => 'list',
                            'add_args' => $pagination_args
                        ));
                        
                        if ($page_links) {
                            echo '<span class="displaying-num">' . 
                                 sprintf(_n('%d item', '%d items', $total_requests, 'wp-employee-leaves'), $total_requests) . 
                                 '</span>';
                            echo wp_kses_post($page_links);
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        
        <style>
        .requests-filters-form {
            background: #f9f9f9;
            border: 1px solid #e1e1e1;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .requests-filters .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .requests-filters .filter-item {
            display: flex;
            flex-direction: column;
            min-width: 120px;
        }
        
        .requests-filters .filter-item label {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        .requests-filters .filter-item input,
        .requests-filters .filter-item select {
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .requests-filters .filter-item input {
            min-width: 180px;
        }
        
        .requests-filters .filter-actions {
            display: flex;
            gap: 8px;
            align-items: end;
        }
        
        .requests-filters .filter-actions .button {
            height: 30px;
            line-height: 28px;
            padding: 0 12px;
            font-size: 13px;
        }
        
        .results-summary {
            font-style: italic;
            color: #666;
            margin-top: 10px;
            font-size: 13px;
        }
        
        .status-pending {
            color: #856404;
            background: #fff3cd;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-approved {
            color: #155724;
            background: #d4edda;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-rejected {
            color: #721c24;
            background: #f8d7da;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .requests-filters .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .requests-filters .filter-item {
                min-width: auto;
            }
            
            .requests-filters .filter-actions {
                justify-content: flex-start;
            }
        }
        </style>
        <?php
    }
    
    public function admin_page_employees() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Employees', 'wp-employee-leaves'); ?></h1>
            <p><?php echo esc_html__('Manage employee records and leave balances.', 'wp-employee-leaves'); ?></p>
        </div>
        <?php
    }
    
    public function admin_page_settings() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $hr_email = get_option('wp_employee_leaves_hr_email', get_option('admin_email'));
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Employee Leaves Settings', 'wp-employee-leaves'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wp-employee-leaves-settings&tab=general" class="nav-tab <?php echo esc_attr($active_tab == 'general' ? 'nav-tab-active' : ''); ?>">
                    <?php _e('General', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=leave-types" class="nav-tab <?php echo esc_attr($active_tab == 'leave-types' ? 'nav-tab-active' : ''); ?>">
                    <?php _e('Leave Types', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=email-templates" class="nav-tab <?php echo esc_attr($active_tab == 'email-templates' ? 'nav-tab-active' : ''); ?>">
                    <?php _e('Email Templates', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=page-management" class="nav-tab <?php echo esc_attr($active_tab == 'page-management' ? 'nav-tab-active' : ''); ?>">
                    <?php _e('Page Management', 'wp-employee-leaves'); ?>
                </a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_employee_leaves_settings', 'wp_employee_leaves_settings_nonce'); ?>
                
                <?php if ($active_tab == 'general'): ?>
                    <div class="tab-content">
                        <h2><?php _e('General Settings', 'wp-employee-leaves'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="hr_email"><?php _e('HR Email Address', 'wp-employee-leaves'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="hr_email" name="hr_email" value="<?php echo esc_attr($hr_email); ?>" class="regular-text" required>
                                    <p class="description"><?php _e('This email address will receive all leave request notifications.', 'wp-employee-leaves'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h3><?php _e('Email Notifications', 'wp-employee-leaves'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Email Notifications', 'wp-employee-leaves'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="email_notifications_enabled">
                                            <input type="checkbox" id="email_notifications_enabled" name="email_notifications_enabled" value="1" <?php checked(get_option('wp_employee_leaves_email_notifications_enabled', 1), 1); ?>>
                                            <?php _e('Enable email notifications', 'wp-employee-leaves'); ?>
                                        </label>
                                        <p class="description"><?php _e('Master switch for all email notifications. When disabled, no emails will be sent.', 'wp-employee-leaves'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Leave Request Submitted', 'wp-employee-leaves'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="notify_on_submission">
                                            <input type="checkbox" id="notify_on_submission" name="notify_on_submission" value="1" <?php checked(get_option('wp_employee_leaves_notify_on_submission', 1), 1); ?>>
                                            <?php _e('Send notifications when leave request is submitted', 'wp-employee-leaves'); ?>
                                        </label>
                                        <p class="description"><?php _e('Notifies HR, managers, and relievers when a new leave request is submitted.', 'wp-employee-leaves'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Leave Request Approved', 'wp-employee-leaves'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="notify_on_approval">
                                            <input type="checkbox" id="notify_on_approval" name="notify_on_approval" value="1" <?php checked(get_option('wp_employee_leaves_notify_on_approval', 1), 1); ?>>
                                            <?php _e('Send notifications when leave request is approved', 'wp-employee-leaves'); ?>
                                        </label>
                                        <p class="description"><?php _e('Notifies employee, managers, and relievers when a leave request is approved.', 'wp-employee-leaves'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Leave Request Rejected', 'wp-employee-leaves'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="notify_on_rejection">
                                            <input type="checkbox" id="notify_on_rejection" name="notify_on_rejection" value="1" <?php checked(get_option('wp_employee_leaves_notify_on_rejection', 1), 1); ?>>
                                            <?php _e('Send notifications when leave request is rejected', 'wp-employee-leaves'); ?>
                                        </label>
                                        <p class="description"><?php _e('Notifies employee when a leave request is rejected.', 'wp-employee-leaves'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </div>
                    
                <?php elseif ($active_tab == 'leave-types'): ?>
                    <div class="tab-content">
                        <?php $this->render_leave_types_tab(); ?>
                    </div>
                    
                <?php elseif ($active_tab == 'email-templates'): ?>
                    <div class="tab-content">
                        <?php $this->render_email_templates_tab(); ?>
                    </div>
                    
                <?php elseif ($active_tab == 'page-management'): ?>
                    <div class="tab-content">
                        <?php $this->render_page_management_tab(); ?>
                    </div>
                    
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!isset($_POST['wp_employee_leaves_settings_nonce']) || !wp_verify_nonce($_POST['wp_employee_leaves_settings_nonce'], 'wp_employee_leaves_settings')) {
            return;
        }
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        if ($active_tab == 'general') {
            $hr_email = sanitize_email($_POST['hr_email']);
            update_option('wp_employee_leaves_hr_email', $hr_email);
            
            // Save email notification settings
            update_option('wp_employee_leaves_email_notifications_enabled', isset($_POST['email_notifications_enabled']) ? 1 : 0);
            update_option('wp_employee_leaves_notify_on_submission', isset($_POST['notify_on_submission']) ? 1 : 0);
            update_option('wp_employee_leaves_notify_on_approval', isset($_POST['notify_on_approval']) ? 1 : 0);
            update_option('wp_employee_leaves_notify_on_rejection', isset($_POST['notify_on_rejection']) ? 1 : 0);
            
            add_settings_error('wp_employee_leaves_settings', 'settings_updated', __('Settings saved successfully!', 'wp-employee-leaves'), 'updated');
        } elseif ($active_tab == 'leave-types') {
            $this->save_leave_types();
        } elseif ($active_tab == 'email-templates') {
            $this->save_email_templates();
        }
        
        settings_errors('wp_employee_leaves_settings');
    }
    
    private function render_leave_types_tab() {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        $leave_types = $wpdb->get_results("SELECT * FROM $leave_types_table ORDER BY name");
        ?>
        <h2><?php _e('Leave Types Management', 'wp-employee-leaves'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Leave Type', 'wp-employee-leaves'); ?></th>
                    <th><?php _e('Yearly Allocation (Days)', 'wp-employee-leaves'); ?></th>
                    <th><?php _e('Active', 'wp-employee-leaves'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leave_types as $type): ?>
                    <tr>
                        <td>
                            <input type="text" name="leave_types[<?php echo esc_attr($type->id); ?>][name]" value="<?php echo esc_attr($type->name); ?>" class="regular-text" readonly>
                        </td>
                        <td>
                            <input type="number" name="leave_types[<?php echo esc_attr($type->id); ?>][yearly_allocation]" value="<?php echo esc_attr($type->yearly_allocation); ?>" step="0.5" min="0" class="small-text">
                        </td>
                        <td>
                            <input type="checkbox" name="leave_types[<?php echo esc_attr($type->id); ?>][active]" value="1" <?php checked($type->active, 1); ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
        <?php
    }
    
    private function render_email_templates_tab() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY template_type");
        
        $template_descriptions = array(
            'leave_request_submitted' => __('Sent to HR when a new leave request is submitted', 'wp-employee-leaves'),
            'leave_request_approved' => __('Sent to employee when their leave request is approved', 'wp-employee-leaves'),
            'leave_request_rejected' => __('Sent to employee when their leave request is rejected', 'wp-employee-leaves'),
            'leave_notification_manager' => __('Sent to managers when their team member submits a leave request', 'wp-employee-leaves'),
            'leave_notification_reliever' => __('Sent to leave relievers when they are assigned to cover someone', 'wp-employee-leaves')
        );
        ?>
        <h2><?php _e('Email Templates', 'wp-employee-leaves'); ?></h2>
        <p><?php _e('Use these placeholders in your templates: {{employee_name}}, {{employee_email}}, {{leave_dates}}, {{leave_types}}, {{reason}}, {{manager_emails}}, {{reliever_emails}}, {{status}}, {{approved_date}}', 'wp-employee-leaves'); ?></p>
        
        <?php foreach ($templates as $template): ?>
            <div class="email-template-section">
                <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $template->template_type))); ?></h3>
                <p class="description"><?php echo isset($template_descriptions[$template->template_type]) ? $template_descriptions[$template->template_type] : ''; ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="template_<?php echo esc_attr($template->id); ?>_subject"><?php _e('Subject', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="template_<?php echo esc_attr($template->id); ?>_subject" name="email_templates[<?php echo esc_attr($template->id); ?>][subject]" value="<?php echo esc_attr($template->subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_<?php echo esc_attr($template->id); ?>_body"><?php _e('Body', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <textarea id="template_<?php echo esc_attr($template->id); ?>_body" name="email_templates[<?php echo esc_attr($template->id); ?>][body]" rows="10" class="large-text"><?php echo esc_textarea($template->body); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_<?php echo esc_attr($template->id); ?>_active"><?php _e('Active', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="template_<?php echo esc_attr($template->id); ?>_active" name="email_templates[<?php echo esc_attr($template->id); ?>][active]" value="1" <?php checked($template->active, 1); ?>>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
        
        <?php submit_button(); ?>
        
        <style>
        .email-template-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .email-template-section h3 {
            margin-top: 0;
            color: #333;
        }
        </style>
        <?php
    }
    
    private function save_leave_types() {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        if (isset($_POST['leave_types']) && is_array($_POST['leave_types'])) {
            foreach ($_POST['leave_types'] as $type_id => $type_data) {
                $wpdb->update(
                    $leave_types_table,
                    array(
                        'yearly_allocation' => floatval($type_data['yearly_allocation']),
                        'active' => isset($type_data['active']) ? 1 : 0
                    ),
                    array('id' => intval($type_id))
                );
            }
        }
        
        add_settings_error('wp_employee_leaves_settings', 'leave_types_updated', __('Leave types updated successfully!', 'wp-employee-leaves'), 'updated');
    }
    
    private function save_email_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        
        if (isset($_POST['email_templates']) && is_array($_POST['email_templates'])) {
            foreach ($_POST['email_templates'] as $template_id => $template_data) {
                $wpdb->update(
                    $templates_table,
                    array(
                        'subject' => sanitize_text_field($template_data['subject']),
                        'body' => sanitize_textarea_field($template_data['body']),
                        'active' => isset($template_data['active']) ? 1 : 0
                    ),
                    array('id' => intval($template_id))
                );
            }
        }
        
        add_settings_error('wp_employee_leaves_settings', 'email_templates_updated', __('Email templates updated successfully!', 'wp-employee-leaves'), 'updated');
    }
    
    private function render_page_management_tab() {
        $pages = get_pages(array('post_status' => 'publish,draft'));
        ?>
        <h2><?php _e('Page Management', 'wp-employee-leaves'); ?></h2>
        
        <div class="page-creation-section">
            <h3><?php _e('Create New Pages', 'wp-employee-leaves'); ?></h3>
            <p><?php _e('Create pages with leave management shortcodes automatically added.', 'wp-employee-leaves'); ?></p>
            
            <div class="page-creation-row">
                <div class="page-creation-column">
                    <h4><?php _e('Leave Request Form', 'wp-employee-leaves'); ?></h4>
                    <p><?php _e('Create a page where employees can submit leave requests.', 'wp-employee-leaves'); ?></p>
                    <div class="page-creation-controls">
                        <input type="text" id="leave-page-title" placeholder="<?php _e('Enter page title (e.g., Submit Leave Request)', 'wp-employee-leaves'); ?>" class="regular-text">
                        <button type="button" id="create-leave-page" class="button button-primary">
                            <?php _e('Create Page', 'wp-employee-leaves'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="page-creation-column">
                    <h4><?php _e('My Leave Requests', 'wp-employee-leaves'); ?></h4>
                    <p><?php _e('Create a page where employees can view their leave request history.', 'wp-employee-leaves'); ?></p>
                    <div class="page-creation-controls">
                        <input type="text" id="my-requests-page-title" placeholder="<?php _e('Enter page title (e.g., My Leave Requests)', 'wp-employee-leaves'); ?>" class="regular-text">
                        <button type="button" id="create-my-requests-page" class="button button-primary">
                            <?php _e('Create Page', 'wp-employee-leaves'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="page-creation-result"></div>
        </div>
        
        <div class="page-creation-section">
            <h3><?php _e('Add Shortcode to Existing Page', 'wp-employee-leaves'); ?></h3>
            <p><?php _e('Select an existing page to add the leave request form shortcode.', 'wp-employee-leaves'); ?></p>
            
            <div class="page-creation-controls">
                <select id="leave-page-select">
                    <option value=""><?php _e('Select a page...', 'wp-employee-leaves'); ?></option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo esc_attr($page->ID); ?>"><?php echo esc_html($page->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="add-shortcode-to-page" class="button button-secondary">
                    <?php _e('Add Shortcode', 'wp-employee-leaves'); ?>
                </button>
            </div>
        </div>
        
        <div class="shortcode-info">
            <h3><?php _e('Manual Shortcode Usage', 'wp-employee-leaves'); ?></h3>
            <p><?php _e('You can manually add these shortcodes to any page or post:', 'wp-employee-leaves'); ?></p>
            
            <div class="shortcode-section">
                <h4><?php _e('Leave Request Form', 'wp-employee-leaves'); ?></h4>
                <p>
                    <code>[employee_leave_form]</code>
                    <button type="button" id="copy-shortcode" class="button button-small">
                        <?php _e('Copy Shortcode', 'wp-employee-leaves'); ?>
                    </button>
                </p>
                
                <h5><?php _e('Attributes (Optional)', 'wp-employee-leaves'); ?></h5>
                <ul>
                    <li><code>[employee_leave_form title="Custom Title"]</code> - <?php _e('Custom form title', 'wp-employee-leaves'); ?></li>
                    <li><code>[employee_leave_form show_balance="false"]</code> - <?php _e('Hide balance information', 'wp-employee-leaves'); ?></li>
                    <li><code>[employee_leave_form redirect_url="/thank-you"]</code> - <?php _e('Custom redirect after submission', 'wp-employee-leaves'); ?></li>
                </ul>
            </div>
            
            <div class="shortcode-section">
                <h4><?php _e('My Leave Requests', 'wp-employee-leaves'); ?></h4>
                <p>
                    <code>[my_leave_requests]</code>
                    <button type="button" id="copy-my-requests-shortcode" class="button button-small">
                        <?php _e('Copy Shortcode', 'wp-employee-leaves'); ?>
                    </button>
                </p>
                
                <h5><?php _e('Attributes (Optional)', 'wp-employee-leaves'); ?></h5>
                <ul>
                    <li><code>[my_leave_requests per_page="5"]</code> - <?php _e('Number of requests per page (default: 10)', 'wp-employee-leaves'); ?></li>
                    <li><code>[my_leave_requests show_year_filter="false"]</code> - <?php _e('Hide year filter', 'wp-employee-leaves'); ?></li>
                    <li><code>[my_leave_requests show_status_filter="false"]</code> - <?php _e('Hide status filter', 'wp-employee-leaves'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function activate() {
        $this->create_tables();
        $this->insert_default_leave_types();
        $this->insert_default_email_templates();
        $this->update_hr_email_template();
        
        // Initialize HR email with admin email if not set
        if (!get_option('wp_employee_leaves_hr_email')) {
            update_option('wp_employee_leaves_hr_email', get_option('admin_email'));
        }
        
        // Set plugin version
        update_option('wp_employee_leaves_version', WP_EMPLOYEE_LEAVES_VERSION);
        
        flush_rewrite_rules();
    }
    
    /**
     * Check for plugin version updates and run necessary upgrades
     */
    public function check_version_update() {
        $saved_version = get_option('wp_employee_leaves_version', '1.0.0');
        $current_version = WP_EMPLOYEE_LEAVES_VERSION;
        
        if (version_compare($saved_version, $current_version, '<')) {
            // Run update for versions before 1.5.1 (when approval links were added)
            if (version_compare($saved_version, '1.5.1', '<')) {
                $this->update_hr_email_template();
            }
            
            // Run update for versions before 1.5.4 (when "Click here" text was added)
            if (version_compare($saved_version, '1.5.4', '<')) {
                $this->update_hr_email_template();
            }
            
            // Run update for versions before 1.5.5 (when Unicode characters were replaced)
            if (version_compare($saved_version, '1.5.5', '<')) {
                $this->update_hr_email_template();
            }
            
            // Run update for versions before 1.5.6 (when anchor tags were added)
            if (version_compare($saved_version, '1.5.6', '<')) {
                $this->update_hr_email_template();
            }
            
            // Update the stored version
            update_option('wp_employee_leaves_version', $current_version);
        }
    }
    
    /**
     * Update HR email template with approval links for existing installations
     */
    private function update_hr_email_template() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        
        // Updated template with approval links
        $updated_template_body = 'Dear HR Team,

A new leave request requires your approval:

Employee Information:
====================================================
* Name: {{employee_name}}
* Email: {{employee_email}}
* Employee ID: {{employee_id}}

Leave Details:
====================================================
* Dates: {{leave_dates}}
* Types: {{leave_types}}
* Reason: {{reason}}
* Manager(s): {{manager_emails}}
* Reliever(s): {{reliever_emails}}

Quick Actions (Valid until {{expires_at}}):
====================================================

[APPROVE] APPROVE REQUEST
<a href="{{approval_link}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">Click here to APPROVE</a>

[REJECT] REJECT REQUEST  
<a href="{{rejection_link}}" style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">Click here to REJECT</a>

IMPORTANT: These links work only ONCE. After clicking, use the admin dashboard for any additional actions.

Admin Dashboard: <a href="{{admin_dashboard_url}}" style="color: #007cba; text-decoration: underline;">{{admin_dashboard_url}}</a>

====================================================
This email was generated automatically. Do not reply.
Leave Management System';

        // Check if template contains approval links
        $current_template = $wpdb->get_row($wpdb->prepare(
            "SELECT body FROM $templates_table WHERE template_type = %s",
            'leave_request_submitted'
        ));
        
        if ($current_template && strpos($current_template->body, '{{approval_link}}') === false) {
            // Update the template to include approval links
            $wpdb->update(
                $templates_table,
                array(
                    'subject' => 'Leave Request Approval Required - {{employee_name}}',
                    'body' => $updated_template_body
                ),
                array('template_type' => 'leave_request_submitted')
            );
        }
    }
    
    // Add method to update database structure
    public function update_database_structure() {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        // Check if employee_code column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $requests_table LIKE %s",
            'employee_code'
        ));
        
        if (empty($column_exists)) {
            // Add employee_code column
            $wpdb->query("ALTER TABLE $requests_table ADD COLUMN employee_code varchar(100) DEFAULT NULL AFTER employee_id");
        }
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Leave types table
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        $sql_leave_types = "CREATE TABLE $leave_types_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            yearly_allocation decimal(5,2) DEFAULT 0.00,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Leave requests table
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $sql_requests = "CREATE TABLE $requests_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            employee_code varchar(100) DEFAULT NULL,
            manager_emails text DEFAULT NULL,
            reliever_emails text DEFAULT NULL,
            reason text DEFAULT '',
            status varchar(20) DEFAULT 'pending',
            approved_by bigint(20) UNSIGNED DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY employee_code (employee_code),
            KEY status (status)
        ) $charset_collate;";
        
        // Leave dates table
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $sql_dates = "CREATE TABLE $dates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id mediumint(9) NOT NULL,
            leave_date date NOT NULL,
            leave_type_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY leave_type_id (leave_type_id),
            KEY leave_date (leave_date)
        ) $charset_collate;";
        
        // Leave balances table
        $balances_table = $wpdb->prefix . 'employee_leaves_balances';
        $sql_balances = "CREATE TABLE $balances_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            leave_type_id mediumint(9) NOT NULL,
            year int(4) NOT NULL,
            allocated decimal(5,2) DEFAULT 0.00,
            used decimal(5,2) DEFAULT 0.00,
            remaining decimal(5,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_type_year (employee_id, leave_type_id, year),
            KEY employee_id (employee_id),
            KEY leave_type_id (leave_type_id),
            KEY year (year)
        ) $charset_collate;";
        
        // Leave logs table
        $logs_table = $wpdb->prefix . 'employee_leaves_logs';
        $sql_logs = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) UNSIGNED NOT NULL,
            request_id mediumint(9) DEFAULT NULL,
            action varchar(50) NOT NULL,
            details text DEFAULT '',
            year int(4) NOT NULL,
            performed_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY request_id (request_id),
            KEY action (action),
            KEY year (year),
            KEY performed_by (performed_by)
        ) $charset_collate;";
        
        // Notifications table
        $notifications_table = $wpdb->prefix . 'employee_leaves_notifications';
        $sql_notifications = "CREATE TABLE $notifications_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id mediumint(9) NOT NULL,
            email_type varchar(50) NOT NULL,
            email_address varchar(255) NOT NULL,
            sent_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY email_type (email_type),
            KEY status (status)
        ) $charset_collate;";
        
        // Email templates table
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        $sql_templates = "CREATE TABLE $templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_type varchar(50) NOT NULL,
            subject varchar(255) NOT NULL,
            body text NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_type (template_type)
        ) $charset_collate;";
        
        // Action tokens table for email approval links
        $tokens_table = $wpdb->prefix . 'employee_leaves_action_tokens';
        $sql_tokens = "CREATE TABLE $tokens_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id mediumint(9) NOT NULL,
            token varchar(64) NOT NULL,
            action varchar(20) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY request_id (request_id),
            KEY expires_at (expires_at),
            KEY used_at (used_at)
        ) $charset_collate;";
        
        // User contacts table for auto-fill functionality
        $contacts_table = $wpdb->prefix . 'employee_leaves_user_contacts';
        $sql_contacts = "CREATE TABLE $contacts_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            contact_type varchar(20) NOT NULL,
            email_address varchar(255) NOT NULL,
            display_name varchar(255) DEFAULT NULL,
            usage_count int DEFAULT 1,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_contact (user_id, contact_type, email_address),
            KEY user_id (user_id),
            KEY contact_type (contact_type),
            KEY last_used (last_used)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_leave_types);
        dbDelta($sql_requests);
        dbDelta($sql_dates);
        dbDelta($sql_balances);
        dbDelta($sql_logs);
        dbDelta($sql_notifications);
        dbDelta($sql_templates);
        dbDelta($sql_tokens);
        dbDelta($sql_contacts);
        
        // Insert default leave types
        $this->insert_default_leave_types();
        
        // Insert default email templates
        $this->insert_default_email_templates();
    }
    
    private function insert_default_leave_types() {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        $default_types = array(
            array('name' => 'Annual Leave', 'yearly_allocation' => 21.00),
            array('name' => 'Casual Leave', 'yearly_allocation' => 10.00),
            array('name' => 'Sick Leave', 'yearly_allocation' => 14.00),
            array('name' => 'Emergency Leave (Probation)', 'yearly_allocation' => 5.00)
        );
        
        foreach ($default_types as $type) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $leave_types_table WHERE name = %s",
                $type['name']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $leave_types_table,
                    array(
                        'name' => $type['name'],
                        'yearly_allocation' => $type['yearly_allocation'],
                        'active' => 1
                    )
                );
            }
        }
    }
    
    private function insert_default_email_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        
        $default_templates = array(
            array(
                'template_type' => 'leave_request_submitted',
                'subject' => 'Leave Request Approval Required - {{employee_name}}',
                'body' => 'Dear HR Team,

A new leave request requires your approval:

Employee Information:
====================================================
* Name: {{employee_name}}
* Email: {{employee_email}}
* Employee ID: {{employee_id}}

Leave Details:
====================================================
* Dates: {{leave_dates}}
* Types: {{leave_types}}
* Reason: {{reason}}
* Manager(s): {{manager_emails}}
* Reliever(s): {{reliever_emails}}

Quick Actions (Valid until {{expires_at}}):
====================================================

[APPROVE] APPROVE REQUEST
<a href="{{approval_link}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">Click here to APPROVE</a>

[REJECT] REJECT REQUEST  
<a href="{{rejection_link}}" style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0;">Click here to REJECT</a>

IMPORTANT: These links work only ONCE. After clicking, use the admin dashboard for any additional actions.

Admin Dashboard: <a href="{{admin_dashboard_url}}" style="color: #007cba; text-decoration: underline;">{{admin_dashboard_url}}</a>

====================================================
This email was generated automatically. Do not reply.
Leave Management System'
            ),
            array(
                'template_type' => 'leave_request_approved',
                'subject' => 'Leave Request Approved - {{employee_name}}',
                'body' => 'Dear {{employee_name}},

Your leave request has been approved by HR.

Leave Details:
- Leave Dates: {{leave_dates}}
- Leave Types: {{leave_types}}
- Reason: {{reason}}
- Approved On: {{approved_date}}

Your leave balances have been updated accordingly.

Best regards,
HR Team'
            ),
            array(
                'template_type' => 'leave_request_rejected',
                'subject' => 'Leave Request Rejected - {{employee_name}}',
                'body' => 'Dear {{employee_name}},

Unfortunately, your leave request has been rejected by HR.

Leave Details:
- Leave Dates: {{leave_dates}}
- Leave Types: {{leave_types}}
- Reason: {{reason}}
- Rejected On: {{approved_date}}

Please contact HR if you need further clarification.

Best regards,
HR Team'
            ),
            array(
                'template_type' => 'leave_notification_manager',
                'subject' => 'Leave Notification - {{employee_name}}',
                'body' => 'Dear Manager,

This is to inform you that {{employee_name}} has submitted a leave request.

Leave Details:
- Employee: {{employee_name}}
- Leave Dates: {{leave_dates}}
- Leave Types: {{leave_types}}
- Reason: {{reason}}
- Status: {{status}}

Best regards,
Leave Management System'
            ),
            array(
                'template_type' => 'leave_notification_reliever',
                'subject' => 'Leave Coverage Request - {{employee_name}}',
                'body' => 'Dear Team Member,

You have been listed as a leave reliever for {{employee_name}}.

Leave Details:
- Employee: {{employee_name}}
- Leave Dates: {{leave_dates}}
- Leave Types: {{leave_types}}
- Reason: {{reason}}
- Status: {{status}}

Please prepare to cover their responsibilities during this period.

Best regards,
Leave Management System'
            )
        );
        
        foreach ($default_templates as $template) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $templates_table WHERE template_type = %s",
                $template['template_type']
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $templates_table,
                    array(
                        'template_type' => $template['template_type'],
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'active' => 1
                    )
                );
            }
        }
    }
    
    // Leave Balance Management
    public function get_employee_balance($employee_id, $leave_type_id, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        $balances_table = $wpdb->prefix . 'employee_leaves_balances';
        
        $balance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $balances_table WHERE employee_id = %d AND leave_type_id = %d AND year = %d",
            $employee_id, $leave_type_id, $year
        ));
        
        if (!$balance) {
            $this->initialize_employee_balance($employee_id, $leave_type_id, $year);
            $balance = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $balances_table WHERE employee_id = %d AND leave_type_id = %d AND year = %d",
                $employee_id, $leave_type_id, $year
            ));
        }
        
        return $balance;
    }
    
    public function initialize_employee_balance($employee_id, $leave_type_id, $year) {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        $balances_table = $wpdb->prefix . 'employee_leaves_balances';
        
        $leave_type = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $leave_types_table WHERE id = %d",
            $leave_type_id
        ));
        
        if ($leave_type) {
            $allocated = $leave_type->yearly_allocation;
            
            $wpdb->insert(
                $balances_table,
                array(
                    'employee_id' => $employee_id,
                    'leave_type_id' => $leave_type_id,
                    'year' => $year,
                    'allocated' => $allocated,
                    'used' => 0,
                    'remaining' => $allocated
                )
            );
        }
    }
    
    public function update_employee_balance($employee_id, $leave_type_id, $days_used, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        $balances_table = $wpdb->prefix . 'employee_leaves_balances';
        
        $balance = $this->get_employee_balance($employee_id, $leave_type_id, $year);
        
        if ($balance) {
            $new_used = $balance->used + $days_used;
            $new_remaining = $balance->allocated - $new_used;
            
            $wpdb->update(
                $balances_table,
                array(
                    'used' => $new_used,
                    'remaining' => $new_remaining
                ),
                array(
                    'employee_id' => $employee_id,
                    'leave_type_id' => $leave_type_id,
                    'year' => $year
                )
            );
            
            return true;
        }
        
        return false;
    }
    
    public function get_all_employee_balances($employee_id, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        $balances_table = $wpdb->prefix . 'employee_leaves_balances';
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        $balances = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, lt.name as leave_type_name 
             FROM $balances_table b 
             JOIN $leave_types_table lt ON b.leave_type_id = lt.id 
             WHERE b.employee_id = %d AND b.year = %d 
             ORDER BY lt.name",
            $employee_id, $year
        ));
        
        // Initialize balances for any missing leave types
        $leave_types = $wpdb->get_results("SELECT * FROM $leave_types_table WHERE active = 1");
        foreach ($leave_types as $leave_type) {
            $has_balance = false;
            foreach ($balances as $balance) {
                if ($balance->leave_type_id == $leave_type->id) {
                    $has_balance = true;
                    break;
                }
            }
            if (!$has_balance) {
                $this->initialize_employee_balance($employee_id, $leave_type->id, $year);
            }
        }
        
        // Re-fetch balances after initialization
        $balances = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, lt.name as leave_type_name 
             FROM $balances_table b 
             JOIN $leave_types_table lt ON b.leave_type_id = lt.id 
             WHERE b.employee_id = %d AND b.year = %d 
             ORDER BY lt.name",
            $employee_id, $year
        ));
        
        return $balances;
    }
    
    // Leave Logging System
    public function log_leave_action($employee_id, $request_id, $action, $details, $performed_by = null, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        if (!$performed_by) {
            $performed_by = get_current_user_id();
        }
        
        $logs_table = $wpdb->prefix . 'employee_leaves_logs';
        
        $wpdb->insert(
            $logs_table,
            array(
                'employee_id' => $employee_id,
                'request_id' => $request_id,
                'action' => $action,
                'details' => $details,
                'year' => $year,
                'performed_by' => $performed_by
            )
        );
        
        return $wpdb->insert_id;
    }
    
    // Frontend Leave Form
    public function leave_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Submit Leave Request', 'wp-employee-leaves'),
            'show_balance' => 'true',
            'redirect_url' => ''
        ), $atts, 'employee_leave_form');
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to submit a leave request.', 'wp-employee-leaves') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $leave_types = $this->get_active_leave_types();
        $balances = $this->get_all_employee_balances($user_id);
        
        ob_start();
        ?>
        <div id="employee-leave-form-container">
            
            <?php if ($atts['show_balance'] === 'true'): ?>
                <div id="leave-balance-info" style="display: none;">
                    <h4><?php _e('Your Current Leave Balance', 'wp-employee-leaves'); ?></h4>
                    <div class="balance-grid">
                        <?php foreach ($balances as $balance): ?>
                            <div class="balance-item">
                                <strong><?php echo esc_html($balance->leave_type_name); ?></strong>
                                <span><?php echo esc_html($balance->remaining); ?> / <?php echo esc_html($balance->allocated); ?> <?php _e('days', 'wp-employee-leaves'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form id="employee-leave-form" method="post" data-redirect="<?php echo esc_attr($atts['redirect_url']); ?>">
                <div class="form-group">
                    <label for="employee_id"><?php _e('Employee ID', 'wp-employee-leaves'); ?></label>
                    <input type="text" id="employee_id" name="employee_id" class="regular-text" required placeholder="<?php _e('Enter your employee ID', 'wp-employee-leaves'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="employee_email"><?php _e('Employee Email', 'wp-employee-leaves'); ?></label>
                    <input type="email" id="employee_email" name="employee_email" class="regular-text" value="<?php echo esc_attr($user_email); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label><?php _e('Line Manager Emails (Optional)', 'wp-employee-leaves'); ?></label>
                    <div id="manager-emails-container" class="email-fields-container">
                        <div class="email-field-row">
                            <input type="email" class="email-field manager-email" name="manager_emails[]" 
                                   placeholder="<?php esc_attr_e('manager@company.com', 'wp-employee-leaves'); ?>">
                            <button type="button" class="remove-email-field" style="display: none;">×</button>
                        </div>
                    </div>
                    <button type="button" class="add-email-field" data-target="manager">
                        <?php _e('+ Add Manager Email', 'wp-employee-leaves'); ?>
                    </button>
                </div>
                
                <div class="form-group">
                    <label><?php _e('Leave Reliever Emails (Optional)', 'wp-employee-leaves'); ?></label>
                    <div id="reliever-emails-container" class="email-fields-container">
                        <div class="email-field-row">
                            <input type="email" class="email-field reliever-email" name="reliever_emails[]" 
                                   placeholder="<?php esc_attr_e('colleague@company.com', 'wp-employee-leaves'); ?>">
                            <button type="button" class="remove-email-field" style="display: none;">×</button>
                        </div>
                    </div>
                    <button type="button" class="add-email-field" data-target="reliever">
                        <?php _e('+ Add Reliever Email', 'wp-employee-leaves'); ?>
                    </button>
                </div>
                
                <div class="form-group">
                    <label><?php _e('Leave Dates and Types', 'wp-employee-leaves'); ?></label>
                    <div id="leave-dates-container">
                        <div class="date-type-row">
                            <input type="date" class="leave-date-picker" name="leave_dates[]" min="<?php echo date('Y-m-d'); ?>">
                            <select name="leave_types[]">
                                <option value=""><?php _e('Select leave type', 'wp-employee-leaves'); ?></option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>"><?php echo esc_html($type->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="remove-date-row">×</button>
                        </div>
                    </div>
                    <button type="button" id="add-date-row"><?php _e('Add Another Date', 'wp-employee-leaves'); ?></button>
                </div>
                
                <div class="form-group">
                    <label for="reason"><?php _e('Reason for Leave', 'wp-employee-leaves'); ?></label>
                    <textarea id="reason" name="reason" rows="4" required placeholder="<?php _e('Please provide a brief description of your leave reason', 'wp-employee-leaves'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" id="submit-leave-request"><?php _e('Submit Leave Request', 'wp-employee-leaves'); ?></button>
                </div>
            </form>
            
            <div id="leave-form-message"></div>
        </div>
        
        <!-- Success Modal -->
        <div id="success-modal" class="success-modal">
            <div class="success-modal-content">
                <div class="success-modal-header">
                    <div class="success-modal-icon">✓</div>
                    <h2 class="success-modal-title"><?php _e('Request Submitted Successfully!', 'wp-employee-leaves'); ?></h2>
                </div>
                <div class="success-modal-message">
                    <p><?php _e('Your leave request has been submitted and is pending approval. You will receive an email notification once it has been reviewed.', 'wp-employee-leaves'); ?></p>
                </div>
                <button class="success-modal-close"><?php _e('Close', 'wp-employee-leaves'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // My Leave Requests Shortcode
    public function my_leave_requests_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 10,
            'show_year_filter' => 'true',
            'show_status_filter' => 'true'
        ), $atts, 'my_leave_requests');
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your leave requests.', 'wp-employee-leaves') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $current_page = max(1, get_query_var('paged', 1));
        $per_page = intval($atts['per_page']);
        $offset = ($current_page - 1) * $per_page;
        
        // Get filter values
        $year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $types_table = $wpdb->prefix . 'employee_leaves_types';
        
        // Build WHERE clause
        $where_conditions = array("r.employee_id = %d", "YEAR(r.created_at) = %d");
        $where_values = array($user_id, $year_filter);
        
        if ($status_filter !== 'all') {
            $where_conditions[] = "r.status = %s";
            $where_values[] = $status_filter;
        }
        
        $where_clause = "WHERE " . implode(' AND ', $where_conditions);
        
        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM $requests_table r $where_clause";
        $total_requests = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
        
        // Get requests with pagination
        $requests_query = "
            SELECT r.*, GROUP_CONCAT(DISTINCT CONCAT(d.leave_date, ':', t.name) ORDER BY d.leave_date SEPARATOR '|') as leave_details
            FROM $requests_table r
            LEFT JOIN $dates_table d ON r.id = d.request_id
            LEFT JOIN $types_table t ON d.leave_type_id = t.id
            $where_clause
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT %d OFFSET %d
        ";
        
        $requests = $wpdb->get_results($wpdb->prepare(
            $requests_query,
            array_merge($where_values, array($per_page, $offset))
        ));
        
        // Calculate pagination
        $total_pages = ceil($total_requests / $per_page);
        
        ob_start();
        ?>
        <div id="my-leave-requests-container">
            <h2><?php _e('My Leave Requests', 'wp-employee-leaves'); ?></h2>
            
            <!-- Filters -->
            <div class="leave-filters">
                <form method="get" class="filters-form">
                    <?php if ($atts['show_year_filter'] === 'true'): ?>
                        <div class="filter-group">
                            <label for="year-filter"><?php _e('Year:', 'wp-employee-leaves'); ?></label>
                            <select name="year" id="year-filter">
                                <?php
                                $current_year = date('Y');
                                for ($year = $current_year + 1; $year >= $current_year - 5; $year--) {
                                    $selected = ($year == $year_filter) ? 'selected' : '';
                                    echo "<option value='$year' $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_status_filter'] === 'true'): ?>
                        <div class="filter-group">
                            <label for="status-filter"><?php _e('Status:', 'wp-employee-leaves'); ?></label>
                            <select name="status" id="status-filter">
                                <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All', 'wp-employee-leaves'); ?></option>
                                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'wp-employee-leaves'); ?></option>
                                <option value="approved" <?php selected($status_filter, 'approved'); ?>><?php _e('Approved', 'wp-employee-leaves'); ?></option>
                                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>><?php _e('Rejected', 'wp-employee-leaves'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="filter-submit"><?php _e('Filter', 'wp-employee-leaves'); ?></button>
                </form>
            </div>
            
            <!-- Results Summary -->
            <div class="results-summary">
                <p><?php printf(__('Showing %d of %d requests for %d', 'wp-employee-leaves'), count($requests), $total_requests, $year_filter); ?></p>
            </div>
            
            <!-- Requests List -->
            <div class="leave-requests-list">
                <?php if (empty($requests)): ?>
                    <div class="no-requests">
                        <p><?php _e('No leave requests found.', 'wp-employee-leaves'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="leave-request-card status-<?php echo esc_attr($request->status); ?>">
                            <div class="request-header">
                                <div class="request-id">
                                    <strong><?php _e('Request #', 'wp-employee-leaves'); ?><?php echo esc_html($request->id); ?></strong>
                                </div>
                                <div class="request-status">
                                    <span class="status-badge status-<?php echo esc_attr($request->status); ?>">
                                        <?php echo esc_html($this->get_status_label($request->status)); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="request-details">
                                <div class="detail-row">
                                    <span class="label"><?php _e('Employee ID:', 'wp-employee-leaves'); ?></span>
                                    <span class="value"><?php echo esc_html($request->employee_code); ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="label"><?php _e('Leave Dates:', 'wp-employee-leaves'); ?></span>
                                    <span class="value">
                                        <?php
                                        if ($request->leave_details) {
                                            $details = explode('|', $request->leave_details);
                                            $formatted_details = array();
                                            foreach ($details as $detail) {
                                                $parts = explode(':', $detail);
                                                if (count($parts) == 2) {
                                                    $formatted_details[] = date('M j, Y', strtotime($parts[0])) . ' (' . $parts[1] . ')';
                                                }
                                            }
                                            echo implode(', ', $formatted_details);
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="label"><?php _e('Reason:', 'wp-employee-leaves'); ?></span>
                                    <span class="value"><?php echo esc_html($request->reason); ?></span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="label"><?php _e('Submitted:', 'wp-employee-leaves'); ?></span>
                                    <span class="value"><?php echo date('M j, Y g:i A', strtotime($request->created_at)); ?></span>
                                </div>
                                
                                <?php if ($request->approved_at): ?>
                                    <div class="detail-row">
                                        <span class="label"><?php _e('Processed:', 'wp-employee-leaves'); ?></span>
                                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($request->approved_at)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="leave-pagination">
                    <?php
                    $base_url = remove_query_arg('paged');
                    $base_url = add_query_arg(array('year' => $year_filter, 'status' => $status_filter), $base_url);
                    
                    if ($current_page > 1): ?>
                        <a href="<?php echo add_query_arg('paged', $current_page - 1, $base_url); ?>" class="page-link prev"><?php _e('« Previous', 'wp-employee-leaves'); ?></a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="page-link current"><?php echo esc_html($i); ?></span>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>" class="page-link"><?php echo esc_html($i); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo add_query_arg('paged', $current_page + 1, $base_url); ?>" class="page-link next"><?php _e('Next »', 'wp-employee-leaves'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function get_active_leave_types() {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        return $wpdb->get_results("SELECT * FROM $leave_types_table WHERE active = 1 ORDER BY name");
    }
    
    public function handle_leave_request_submission() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'employee_leave_nonce')) {
            wp_send_json_error(__('Security check failed. Please refresh the page and try again.', 'wp-employee-leaves'));
            return;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to submit a leave request.', 'wp-employee-leaves'));
            return;
        }
        
        $user_id = get_current_user_id();
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $manager_emails = sanitize_textarea_field($_POST['manager_emails']);
        $reliever_emails = sanitize_textarea_field($_POST['reliever_emails']);
        $reason = sanitize_textarea_field($_POST['reason']);
        $leave_dates = array_map('sanitize_text_field', $_POST['leave_dates']);
        $leave_types = array_map('intval', $_POST['leave_types']);
        
        // Validate employee ID
        if (empty($employee_id)) {
            wp_send_json_error(__('Employee ID is required.', 'wp-employee-leaves'));
            return;
        }
        
        // Validate dates and types
        if (empty($leave_dates) || empty($leave_types)) {
            wp_send_json_error(__('Please select at least one leave date and type.', 'wp-employee-leaves'));
            return;
        }
        
        // Validate balance
        $leave_type_counts = array();
        for ($i = 0; $i < count($leave_dates); $i++) {
            if (!empty($leave_dates[$i]) && !empty($leave_types[$i])) {
                $leave_type_id = intval($leave_types[$i]);
                if (!isset($leave_type_counts[$leave_type_id])) {
                    $leave_type_counts[$leave_type_id] = 0;
                }
                $leave_type_counts[$leave_type_id]++;
            }
        }
        
        // Check if user has sufficient balance
        foreach ($leave_type_counts as $leave_type_id => $days_requested) {
            $balance = $this->get_employee_balance($user_id, $leave_type_id);
            if ($balance->remaining < $days_requested) {
                $leave_type_name = $this->get_leave_type_name($leave_type_id);
                wp_send_json_error(sprintf(__('Insufficient %s balance. You have %s days remaining.', 'wp-employee-leaves'), $leave_type_name, $balance->remaining));
                return;
            }
        }
        
        // Create leave request
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        $result = $wpdb->insert(
            $requests_table,
            array(
                'employee_id' => $user_id,
                'employee_code' => $employee_id,
                'manager_emails' => $manager_emails,
                'reliever_emails' => $reliever_emails,
                'reason' => $reason,
                'status' => 'pending'
            )
        );
        
        if ($result === false) {
            wp_send_json_error(__('Failed to submit leave request. Please try again.', 'wp-employee-leaves'));
            return;
        }
        
        $request_id = $wpdb->insert_id;
        
        // Insert leave dates
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        for ($i = 0; $i < count($leave_dates); $i++) {
            if (!empty($leave_dates[$i]) && !empty($leave_types[$i])) {
                $wpdb->insert(
                    $dates_table,
                    array(
                        'request_id' => $request_id,
                        'leave_date' => sanitize_text_field($leave_dates[$i]),
                        'leave_type_id' => intval($leave_types[$i])
                    )
                );
            }
        }
        
        // Save contacts for future auto-fill
        $this->process_form_contacts($user_id, $manager_emails, $reliever_emails);
        
        // Log the action
        $this->log_leave_action($user_id, $request_id, 'submitted', 'Leave request submitted');
        
        // Send email notifications (includes token generation for HR)
        $this->send_leave_submission_notifications($request_id);
        
        wp_send_json_success(__('Leave request submitted successfully!', 'wp-employee-leaves'));
    }
    
    public function get_leave_type_name($leave_type_id) {
        global $wpdb;
        
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $leave_types_table WHERE id = %d",
            $leave_type_id
        ));
    }
    
    // Approval Workflow
    public function handle_approve_request() {
        try {
            check_ajax_referer('approve_leave_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access.');
                return;
            }
            
            if (!isset($_POST['request_id'])) {
                wp_send_json_error('Request ID is required.');
                return;
            }
            
            $request_id = intval($_POST['request_id']);
            
            if ($request_id <= 0) {
                wp_send_json_error('Invalid request ID.');
                return;
            }
            
            $result = $this->approve_leave_request($request_id);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Leave request approved successfully!',
                    'request_id' => $request_id,
                    'new_status' => 'approved'
                ));
            } else {
                wp_send_json_error('Failed to approve leave request. It may have already been processed.');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
    
    public function handle_reject_request() {
        try {
            check_ajax_referer('reject_leave_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access.');
                return;
            }
            
            if (!isset($_POST['request_id'])) {
                wp_send_json_error('Request ID is required.');
                return;
            }
            
            $request_id = intval($_POST['request_id']);
            
            if ($request_id <= 0) {
                wp_send_json_error('Invalid request ID.');
                return;
            }
            
            $result = $this->reject_leave_request($request_id);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Leave request rejected successfully!',
                    'request_id' => $request_id,
                    'new_status' => 'rejected'
                ));
            } else {
                wp_send_json_error('Failed to reject leave request. It may have already been processed.');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
    
    public function approve_leave_request($request_id) {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        
        // Get request details
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request || $request->status !== 'pending') {
            return false;
        }
        
        // Get leave dates and types
        $leave_dates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $dates_table WHERE request_id = %d",
            $request_id
        ));
        
        // Count days per leave type
        $leave_type_counts = array();
        foreach ($leave_dates as $date) {
            if (!isset($leave_type_counts[$date->leave_type_id])) {
                $leave_type_counts[$date->leave_type_id] = 0;
            }
            $leave_type_counts[$date->leave_type_id]++;
        }
        
        // Update leave balances
        foreach ($leave_type_counts as $leave_type_id => $days_used) {
            $this->update_employee_balance($request->employee_id, $leave_type_id, $days_used);
        }
        
        // Update request status
        $wpdb->update(
            $requests_table,
            array(
                'status' => 'approved',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ),
            array('id' => $request_id)
        );
        
        // Log the action
        $this->log_leave_action($request->employee_id, $request_id, 'approved', 'Leave request approved by HR');
        
        // Send email notifications
        $this->send_leave_approval_notifications($request_id);
        
        return true;
    }
    
    public function reject_leave_request($request_id) {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        // Get request details
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request || $request->status !== 'pending') {
            return false;
        }
        
        // Update request status
        $wpdb->update(
            $requests_table,
            array(
                'status' => 'rejected',
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql')
            ),
            array('id' => $request_id)
        );
        
        // Log the action
        $this->log_leave_action($request->employee_id, $request_id, 'rejected', 'Leave request rejected by HR');
        
        // Send email notifications
        $this->send_leave_rejection_notifications($request_id);
        
        return true;
    }
    
    // Email Template Functions
    public function get_email_template($template_type) {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'employee_leaves_email_templates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $templates_table WHERE template_type = %s AND active = 1",
            $template_type
        ));
    }
    
    public function process_email_template($template_type, $variables) {
        $template = $this->get_email_template($template_type);
        
        if (!$template) {
            return false;
        }
        
        $subject = $template->subject;
        $body = $template->body;
        
        // Replace variables in subject and body
        foreach ($variables as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        return array(
            'subject' => $subject,
            'body' => $body
        );
    }
    
    public function send_email_notification($to, $template_type, $variables) {
        $processed_template = $this->process_email_template($template_type, $variables);
        
        if (!$processed_template) {
            return false;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Convert line breaks to HTML
        $body = nl2br($processed_template['body']);
        
        return wp_mail($to, $processed_template['subject'], $body, $headers);
    }
    
    public function collect_unique_emails($manager_emails_string, $reliever_emails_string, $additional_emails = array()) {
        $all_emails = array();
        
        // Add additional emails (like HR)
        foreach ($additional_emails as $email) {
            if (!empty($email) && is_email($email)) {
                $all_emails[strtolower(trim($email))] = trim($email);
            }
        }
        
        // Process manager emails
        if (!empty($manager_emails_string)) {
            $manager_emails = array_map('trim', explode(',', $manager_emails_string));
            foreach ($manager_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $all_emails[strtolower(trim($email))] = trim($email);
                }
            }
        }
        
        // Process reliever emails
        if (!empty($reliever_emails_string)) {
            $reliever_emails = array_map('trim', explode(',', $reliever_emails_string));
            foreach ($reliever_emails as $email) {
                if (!empty($email) && is_email($email)) {
                    $all_emails[strtolower(trim($email))] = trim($email);
                }
            }
        }
        
        return array_values($all_emails); // Return only unique emails
    }
    
    public function send_leave_submission_notifications($request_id) {
        // Check if email notifications are enabled
        if (!get_option('wp_employee_leaves_email_notifications_enabled', 1)) {
            return;
        }
        
        // Check if submission notifications are enabled
        if (!get_option('wp_employee_leaves_notify_on_submission', 1)) {
            return;
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $types_table = $wpdb->prefix . 'employee_leaves_types';
        
        // Get request details
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            return;
        }
        
        // Get employee details
        $employee = get_user_by('id', $request->employee_id);
        if (!$employee) {
            return;
        }
        
        // Get leave dates and types
        $leave_details = $wpdb->get_results($wpdb->prepare(
            "SELECT d.leave_date, t.name as leave_type
             FROM $dates_table d
             JOIN $types_table t ON d.leave_type_id = t.id
             WHERE d.request_id = %d
             ORDER BY d.leave_date",
            $request_id
        ));
        
        // Format leave dates and types
        $leave_dates = array();
        $leave_types = array();
        foreach ($leave_details as $detail) {
            $leave_dates[] = date('M j, Y', strtotime($detail->leave_date));
            $leave_types[] = $detail->leave_type;
        }
        
        // Prepare template variables
        $template_vars = array(
            'employee_name' => $employee->display_name,
            'employee_email' => $employee->user_email,
            'employee_id' => $request->employee_code,
            'leave_dates' => implode(', ', $leave_dates),
            'leave_types' => implode(', ', array_unique($leave_types)),
            'reason' => $request->reason,
            'status' => $this->get_status_label($request->status),
            'approved_date' => '',
            'manager_emails' => $request->manager_emails,
            'reliever_emails' => $request->reliever_emails
        );
        
        // Send notification to HR with approval links
        $hr_email = get_option('wp_employee_leaves_hr_email', get_option('admin_email'));
        if ($hr_email) {
            // Generate approval tokens for HR
            $tokens = $this->generate_approval_tokens($request_id, $hr_email);
            
            // Add token variables to template
            $hr_template_vars = $template_vars;
            $hr_template_vars['approve_token'] = $tokens['approve_token'];
            $hr_template_vars['reject_token'] = $tokens['reject_token'];
            $hr_template_vars['approval_link'] = home_url('/wp-json/wp-employee-leaves/v1/approve/' . $tokens['approve_token']);
            $hr_template_vars['rejection_link'] = home_url('/wp-json/wp-employee-leaves/v1/reject/' . $tokens['reject_token']);
            $hr_template_vars['admin_dashboard_url'] = admin_url('admin.php?page=wp-employee-leaves-requests');
            $hr_template_vars['site_url'] = home_url();
            $hr_template_vars['expires_at'] = date('M j, Y', strtotime($tokens['expires_at']));
            
            $this->send_email_notification($hr_email, 'leave_request_submitted', $hr_template_vars);
            $this->log_email_notification($request_id, 'leave_request_submitted', 'Leave request submitted notification sent to HR with approval links', $hr_email);
        }
        
        // Collect unique emails for managers and relievers to prevent duplicates
        $hr_emails = !empty($hr_email) ? array($hr_email) : array();
        $unique_emails = $this->collect_unique_emails($request->manager_emails, $request->reliever_emails, $hr_emails);
        
        // Send notifications to unique managers and relievers
        foreach ($unique_emails as $email) {
            // Skip HR email as it was already sent above
            if ($email === $hr_email) {
                continue;
            }
            
            // Determine if this email is a manager, reliever, or both
            $is_manager = false;
            $is_reliever = false;
            
            if (!empty($request->manager_emails)) {
                $manager_emails = array_map('strtolower', array_map('trim', explode(',', $request->manager_emails)));
                $is_manager = in_array(strtolower($email), $manager_emails);
            }
            
            if (!empty($request->reliever_emails)) {
                $reliever_emails = array_map('strtolower', array_map('trim', explode(',', $request->reliever_emails)));
                $is_reliever = in_array(strtolower($email), $reliever_emails);
            }
            
            // Send appropriate notification (prefer manager template if person has both roles)
            if ($is_manager) {
                $this->send_email_notification($email, 'leave_notification_manager', $template_vars);
                $role_description = $is_reliever ? 'manager/reliever' : 'manager';
                $this->log_email_notification($request_id, 'leave_notification_manager', "Leave request notification sent to {$role_description}", $email);
            } elseif ($is_reliever) {
                $this->send_email_notification($email, 'leave_notification_reliever', $template_vars);
                $this->log_email_notification($request_id, 'leave_notification_reliever', 'Leave request notification sent to reliever', $email);
            }
        }
    }
    
    public function send_leave_approval_notifications($request_id) {
        // Check if email notifications are enabled
        if (!get_option('wp_employee_leaves_email_notifications_enabled', 1)) {
            return;
        }
        
        // Check if approval notifications are enabled
        if (!get_option('wp_employee_leaves_notify_on_approval', 1)) {
            return;
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $types_table = $wpdb->prefix . 'employee_leaves_types';
        
        // Get request details
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            return;
        }
        
        // Get employee details
        $employee = get_user_by('id', $request->employee_id);
        if (!$employee) {
            return;
        }
        
        // Get leave dates and types
        $leave_details = $wpdb->get_results($wpdb->prepare(
            "SELECT d.leave_date, t.name as leave_type
             FROM $dates_table d
             JOIN $types_table t ON d.leave_type_id = t.id
             WHERE d.request_id = %d
             ORDER BY d.leave_date",
            $request_id
        ));
        
        // Format leave dates and types
        $leave_dates = array();
        $leave_types = array();
        foreach ($leave_details as $detail) {
            $leave_dates[] = date('M j, Y', strtotime($detail->leave_date));
            $leave_types[] = $detail->leave_type;
        }
        
        // Prepare template variables
        $template_vars = array(
            'employee_name' => $employee->display_name,
            'employee_email' => $employee->user_email,
            'employee_id' => $request->employee_code,
            'leave_dates' => implode(', ', $leave_dates),
            'leave_types' => implode(', ', array_unique($leave_types)),
            'reason' => $request->reason,
            'status' => $this->get_status_label($request->status),
            'approved_date' => date('M j, Y g:i A', strtotime($request->approved_at)),
            'manager_emails' => $request->manager_emails,
            'reliever_emails' => $request->reliever_emails
        );
        
        // Send notification to employee
        $this->send_email_notification($employee->user_email, 'leave_approved', $template_vars);
        $this->log_email_notification($request_id, 'leave_approved', 'Leave request approved notification sent to employee', $employee->user_email);
        
        // Collect unique emails for managers and relievers to prevent duplicates
        $employee_emails = array($employee->user_email);
        $unique_emails = $this->collect_unique_emails($request->manager_emails, $request->reliever_emails, $employee_emails);
        
        // Send notifications to unique managers and relievers
        foreach ($unique_emails as $email) {
            // Skip employee email as it was already sent above
            if ($email === $employee->user_email) {
                continue;
            }
            
            // Determine if this email is a manager, reliever, or both
            $is_manager = false;
            $is_reliever = false;
            
            if (!empty($request->manager_emails)) {
                $manager_emails = array_map('strtolower', array_map('trim', explode(',', $request->manager_emails)));
                $is_manager = in_array(strtolower($email), $manager_emails);
            }
            
            if (!empty($request->reliever_emails)) {
                $reliever_emails = array_map('strtolower', array_map('trim', explode(',', $request->reliever_emails)));
                $is_reliever = in_array(strtolower($email), $reliever_emails);
            }
            
            // Send appropriate notification (prefer manager template if person has both roles)
            if ($is_manager) {
                $this->send_email_notification($email, 'leave_notification_manager', $template_vars);
                $role_description = $is_reliever ? 'manager/reliever' : 'manager';
                $this->log_email_notification($request_id, 'leave_notification_manager', "Leave request approved notification sent to {$role_description}", $email);
            } elseif ($is_reliever) {
                $this->send_email_notification($email, 'leave_notification_reliever', $template_vars);
                $this->log_email_notification($request_id, 'leave_notification_reliever', 'Leave request approved notification sent to reliever', $email);
            }
        }
    }
    
    public function send_leave_rejection_notifications($request_id) {
        // Check if email notifications are enabled
        if (!get_option('wp_employee_leaves_email_notifications_enabled', 1)) {
            return;
        }
        
        // Check if rejection notifications are enabled
        if (!get_option('wp_employee_leaves_notify_on_rejection', 1)) {
            return;
        }
        
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $types_table = $wpdb->prefix . 'employee_leaves_types';
        
        // Get request details
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $requests_table WHERE id = %d",
            $request_id
        ));
        
        if (!$request) {
            return;
        }
        
        // Get employee details
        $employee = get_user_by('id', $request->employee_id);
        if (!$employee) {
            return;
        }
        
        // Get leave dates and types
        $leave_details = $wpdb->get_results($wpdb->prepare(
            "SELECT d.leave_date, t.name as leave_type
             FROM $dates_table d
             JOIN $types_table t ON d.leave_type_id = t.id
             WHERE d.request_id = %d
             ORDER BY d.leave_date",
            $request_id
        ));
        
        // Format leave dates and types
        $leave_dates = array();
        $leave_types = array();
        foreach ($leave_details as $detail) {
            $leave_dates[] = date('M j, Y', strtotime($detail->leave_date));
            $leave_types[] = $detail->leave_type;
        }
        
        // Prepare template variables
        $template_vars = array(
            'employee_name' => $employee->display_name,
            'employee_email' => $employee->user_email,
            'employee_id' => $request->employee_code,
            'leave_dates' => implode(', ', $leave_dates),
            'leave_types' => implode(', ', array_unique($leave_types)),
            'reason' => $request->reason,
            'status' => $this->get_status_label($request->status),
            'approved_date' => date('M j, Y g:i A', strtotime($request->approved_at)),
            'manager_emails' => $request->manager_emails,
            'reliever_emails' => $request->reliever_emails
        );
        
        // Send notification to employee
        $this->send_email_notification($employee->user_email, 'leave_rejected', $template_vars);
        $this->log_email_notification($request_id, 'leave_rejected', 'Leave request rejected notification sent to employee', $employee->user_email);
        
        // Collect unique emails for managers and relievers to prevent duplicates
        $employee_emails = array($employee->user_email);
        $unique_emails = $this->collect_unique_emails($request->manager_emails, $request->reliever_emails, $employee_emails);
        
        // Send notifications to unique managers and relievers
        foreach ($unique_emails as $email) {
            // Skip employee email as it was already sent above
            if ($email === $employee->user_email) {
                continue;
            }
            
            // Determine if this email is a manager, reliever, or both
            $is_manager = false;
            $is_reliever = false;
            
            if (!empty($request->manager_emails)) {
                $manager_emails = array_map('strtolower', array_map('trim', explode(',', $request->manager_emails)));
                $is_manager = in_array(strtolower($email), $manager_emails);
            }
            
            if (!empty($request->reliever_emails)) {
                $reliever_emails = array_map('strtolower', array_map('trim', explode(',', $request->reliever_emails)));
                $is_reliever = in_array(strtolower($email), $reliever_emails);
            }
            
            // Send appropriate notification (prefer manager template if person has both roles)
            if ($is_manager) {
                $this->send_email_notification($email, 'leave_notification_manager', $template_vars);
                $role_description = $is_reliever ? 'manager/reliever' : 'manager';
                $this->log_email_notification($request_id, 'leave_notification_manager', "Leave request rejected notification sent to {$role_description}", $email);
            } elseif ($is_reliever) {
                $this->send_email_notification($email, 'leave_notification_reliever', $template_vars);
                $this->log_email_notification($request_id, 'leave_notification_reliever', 'Leave request rejected notification sent to reliever', $email);
            }
        }
    }
    
    public function log_email_notification($request_id, $template_type, $details, $email_address = '') {
        global $wpdb;
        $notifications_table = $wpdb->prefix . 'employee_leaves_notifications';
        
        $wpdb->insert(
            $notifications_table,
            array(
                'request_id' => $request_id,
                'email_type' => $template_type,
                'email_address' => $email_address,
                'sent_at' => current_time('mysql'),
                'status' => 'sent'
            )
        );
    }
    
    // Page Management AJAX Handlers
    public function handle_create_leave_page() {
        
        try {
            // Check if nonce is present
            if (!isset($_POST['nonce'])) {
                wp_send_json_error('Nonce missing');
                return;
            }
            
            check_ajax_referer('wp_employee_leaves_admin', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access');
                return;
            }
            
            if (!isset($_POST['page_title']) || empty($_POST['page_title'])) {
                wp_send_json_error('Page title is required');
                return;
            }
            
            $page_title = sanitize_text_field($_POST['page_title']);
            
            // Check if page with same title exists
            $existing_page = get_page_by_title($page_title);
            if ($existing_page) {
                wp_send_json_error('A page with this title already exists');
                return;
            }
            
            $page_content = '<h2>' . esc_html__('Employee Leave Request', 'wp-employee-leaves') . '</h2>
<p>' . esc_html__('Please fill out the form below to submit your leave request.', 'wp-employee-leaves') . '</p>

[employee_leave_form]

<p><em>' . esc_html__('Note: You must be logged in to submit a leave request.', 'wp-employee-leaves') . '</em></p>';
            
            $page_data = array(
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
                'post_excerpt' => ''
            );
            
            $page_id = wp_insert_post($page_data, true);
            
            if (is_wp_error($page_id)) {
                wp_send_json_error('Failed to create page: ' . $page_id->get_error_message());
                return;
            }
            
            if (!$page_id) {
                wp_send_json_error('Failed to create page: Unknown error');
                return;
            }
            
            wp_send_json_success(array(
                'page_id' => $page_id,
                'edit_url' => get_edit_post_link($page_id),
                'view_url' => get_permalink($page_id),
                'message' => 'Page created successfully!'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
        }
    }
    
    public function handle_create_my_requests_page() {
        
        try {
            // Check if nonce is present
            if (!isset($_POST['nonce'])) {
                wp_send_json_error('Nonce missing');
                return;
            }
            
            check_ajax_referer('wp_employee_leaves_admin', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access');
                return;
            }
            
            if (!isset($_POST['page_title']) || empty($_POST['page_title'])) {
                wp_send_json_error('Page title is required');
                return;
            }
            
            $page_title = sanitize_text_field($_POST['page_title']);
            
            // Create the page
            $page_data = array(
                'post_title' => $page_title,
                'post_content' => '[my_leave_requests]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            );
            
            $page_id = wp_insert_post($page_data);
            
            if (is_wp_error($page_id)) {
                wp_send_json_error('Failed to create page: ' . $page_id->get_error_message());
                return;
            }
            
            // Return success response
            wp_send_json_success(array(
                'message' => 'My Leave Requests page created successfully!',
                'page_id' => $page_id,
                'edit_url' => admin_url('post.php?post=' . $page_id . '&action=edit'),
                'view_url' => get_permalink($page_id)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
    
    public function handle_add_shortcode_to_page() {
        try {
            check_ajax_referer('wp_employee_leaves_admin', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Unauthorized access');
                return;
            }
            
            if (!isset($_POST['page_id']) || empty($_POST['page_id'])) {
                wp_send_json_error('Page ID is required');
                return;
            }
            
            $page_id = intval($_POST['page_id']);
            $page = get_post($page_id);
            
            if (!$page) {
                wp_send_json_error('Page not found');
                return;
            }
            
            // Check if shortcode already exists
            if (strpos($page->post_content, '[employee_leave_form]') !== false) {
                wp_send_json_error('Shortcode already exists on this page');
                return;
            }
            
            $updated_content = $page->post_content . "\n\n[employee_leave_form]";
            
            $result = wp_update_post(array(
                'ID' => $page_id,
                'post_content' => $updated_content
            ));
            
            if (is_wp_error($result)) {
                wp_send_json_error('Failed to update page: ' . $result->get_error_message());
                return;
            }
            
            if (!$result) {
                wp_send_json_error('Failed to update page: Unknown error');
                return;
            }
            
            wp_send_json_success(array(
                'page_id' => $page_id,
                'edit_url' => get_edit_post_link($page_id),
                'view_url' => get_permalink($page_id),
                'message' => 'Shortcode added successfully!'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
        }
    }
    
    // ============================================================================
    // REST API ENDPOINTS FOR EMAIL APPROVAL LINKS
    // ============================================================================
    
    /**
     * Register REST API endpoints for approval links
     */
    public function register_approval_endpoints() {
        register_rest_route('wp-employee-leaves/v1', '/approve/(?P<token>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_approval_endpoint'),
            'permission_callback' => '__return_true' // Public endpoint
        ));
        
        register_rest_route('wp-employee-leaves/v1', '/reject/(?P<token>[a-zA-Z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_rejection_endpoint'),
            'permission_callback' => '__return_true' // Public endpoint
        ));
    }
    
    /**
     * Handle approval endpoint
     */
    public function handle_approval_endpoint($request) {
        $token = $request->get_param('token');
        
        // Validate token
        $validation = $this->validate_approval_token($token);
        
        if (!$validation['valid']) {
            return $this->show_token_error_page($validation);
        }
        
        // Mark token as used
        $this->mark_token_used($token);
        
        // Process approval
        $result = $this->process_leave_action($validation['token_data']->request_id, 'approved');
        
        if ($result['success']) {
            return $this->show_success_page('approved', $validation['token_data']);
        } else {
            return $this->show_error_page($result['error']);
        }
    }
    
    /**
     * Handle rejection endpoint
     */
    public function handle_rejection_endpoint($request) {
        $token = $request->get_param('token');
        
        // Validate token
        $validation = $this->validate_approval_token($token);
        
        if (!$validation['valid']) {
            return $this->show_token_error_page($validation);
        }
        
        // Mark token as used
        $this->mark_token_used($token);
        
        // Process rejection
        $result = $this->process_leave_action($validation['token_data']->request_id, 'rejected');
        
        if ($result['success']) {
            return $this->show_success_page('rejected', $validation['token_data']);
        } else {
            return $this->show_error_page($result['error']);
        }
    }
    
    /**
     * Process leave approval/rejection
     */
    private function process_leave_action($request_id, $new_status) {
        global $wpdb;
        
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        try {
            // Update request status
            $result = $wpdb->update(
                $requests_table,
                array(
                    'status' => $new_status,
                    'approved_at' => current_time('mysql'),
                    'approved_by' => 0 // Email-based approval
                ),
                array('id' => $request_id)
            );
            
            if ($result === false) {
                return array('success' => false, 'error' => 'Database update failed');
            }
            
            // Send notification emails
            if ($new_status === 'approved') {
                $this->send_leave_approval_notifications($request_id);
            } else {
                $this->send_leave_rejection_notifications($request_id);
            }
            
            // Update leave balances if approved
            if ($new_status === 'approved') {
                $this->update_leave_balances_for_request($request_id);
            }
            
            return array('success' => true);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Show success page after approval/rejection
     */
    private function show_success_page($action, $token_data) {
        // Get request details for display
        global $wpdb;
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM $requests_table r 
             JOIN {$wpdb->users} u ON r.employee_id = u.ID 
             WHERE r.id = %d",
            $token_data->request_id
        ));
        
        $action_title = ucfirst($action);
        $current_timestamp = current_time('M j, Y g:i A');
        $admin_dashboard_url = admin_url('admin.php?page=wp-employee-leaves-requests');
        
        // Get leave dates for display
        $dates_table = $wpdb->prefix . 'employee_leaves_dates';
        $leave_types_table = $wpdb->prefix . 'employee_leaves_types';
        
        $leave_details = $wpdb->get_results($wpdb->prepare(
            "SELECT d.leave_date, lt.name as leave_type_name 
             FROM $dates_table d 
             JOIN $leave_types_table lt ON d.leave_type_id = lt.id 
             WHERE d.request_id = %d 
             ORDER BY d.leave_date",
            $token_data->request_id
        ));
        
        $leave_dates = array();
        foreach ($leave_details as $detail) {
            $leave_dates[] = date('M j, Y', strtotime($detail->leave_date)) . ' (' . $detail->leave_type_name . ')';
        }
        $leave_dates_string = implode(', ', $leave_dates);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Leave Request <?php echo esc_html($action_title); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
                .container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; 
                           border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .success-header { color: #28a745; font-size: 24px; margin-bottom: 20px; text-align: center; }
                .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .warning-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; 
                              border-radius: 5px; margin: 20px 0; color: #856404; }
                .dashboard-link { display: inline-block; padding: 12px 24px; background: #007cba; 
                                color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .dashboard-link:hover { background: #005a87; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="success-header">✅ Leave Request <?php echo esc_html($action_title); ?>!</div>
                
                <div class="info-box">
                    <strong>Employee:</strong> <?php echo esc_html($request->display_name); ?><br>
                    <strong>Leave Dates:</strong> <?php echo esc_html($leave_dates_string); ?><br>
                    <strong>Action:</strong> <?php echo esc_html($action_title); ?> on <?php echo esc_html($current_timestamp); ?><br>
                    <strong>Processed by:</strong> Email link
                </div>
                
                <div class="warning-box">
                    <strong>⚠️ Important:</strong> This approval link has been used and is now expired. 
                    Any future actions for this or other leave requests must be done through the admin dashboard.
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($admin_dashboard_url); ?>" class="dashboard-link">
                        Go to Admin Dashboard
                    </a>
                </div>
                
                <div class="footer">
                    <p>Notifications have been sent to the employee and relevant contacts.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $output = ob_get_clean();
        
        return new WP_REST_Response($output, 200, array('Content-Type' => 'text/html'));
    }
    
    /**
     * Show error page for invalid/expired tokens
     */
    private function show_token_error_page($validation) {
        $admin_dashboard_url = admin_url('admin.php?page=wp-employee-leaves-requests');
        
        // Determine error message based on reason
        $error_message = '';
        $error_details = '';
        
        switch ($validation['reason']) {
            case 'already_used':
                $error_message = 'This approval link has already been used.';
                $error_details = 'This link was used on ' . date('M j, Y g:i A', strtotime($validation['used_at'])) . ' and cannot be used again.';
                break;
            case 'expired':
                $error_message = 'This approval link has expired.';
                $error_details = 'This link expired on ' . date('M j, Y g:i A', strtotime($validation['expires_at'])) . ' and is no longer valid.';
                break;
            case 'request_not_pending':
                $error_message = 'The leave request is no longer pending approval.';
                $current_status = isset($validation['current_status']) ? $validation['current_status'] : 'unknown';
                $error_details = 'Current status: ' . ucfirst($current_status);
                break;
            default:
                $error_message = 'This approval link is invalid or malformed.';
                $error_details = 'The link may have been corrupted or is not from a valid source.';
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Approval Link Expired - Employee Leaves</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
                .container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; 
                           border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-header { color: #dc3545; font-size: 24px; margin-bottom: 20px; text-align: center; }
                .error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; 
                            border-radius: 5px; margin: 15px 0; color: #721c24; }
                .dashboard-link { display: inline-block; padding: 12px 24px; background: #007cba; 
                                color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .dashboard-link:hover { background: #005a87; }
                .instructions { margin: 20px 0; }
                .instructions ul { padding-left: 20px; }
                .instructions li { margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-header">🔒 Approval Link Expired</div>
                
                <div class="error-box">
                    <strong><?php echo esc_html($error_message); ?></strong><br><br>
                    <?php echo esc_html($error_details); ?>
                </div>
                
                <div class="instructions">
                    <p><strong>To approve or reject leave requests:</strong></p>
                    <ul>
                        <li>Log into your WordPress admin dashboard</li>
                        <li>Navigate to Employee Leaves → Leave Requests</li>
                        <li>Process requests through the admin interface</li>
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($admin_dashboard_url); ?>" class="dashboard-link">
                        Go to Admin Dashboard
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        $output = ob_get_clean();
        
        return new WP_REST_Response($output, 410, array('Content-Type' => 'text/html')); // 410 = Gone
    }
    
    /**
     * Show generic error page
     */
    private function show_error_page($error_message) {
        $admin_dashboard_url = admin_url('admin.php?page=wp-employee-leaves-requests');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Error - Employee Leaves</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f1f1f1; }
                .container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; 
                           border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-header { color: #dc3545; font-size: 24px; margin-bottom: 20px; text-align: center; }
                .error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; 
                            border-radius: 5px; margin: 15px 0; color: #721c24; }
                .dashboard-link { display: inline-block; padding: 12px 24px; background: #007cba; 
                                color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                .dashboard-link:hover { background: #005a87; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-header">❌ Error Processing Request</div>
                
                <div class="error-box">
                    <strong>An error occurred while processing your request:</strong><br><br>
                    <?php echo esc_html($error_message); ?>
                </div>
                
                <p>Please try again through the admin dashboard or contact your system administrator.</p>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($admin_dashboard_url); ?>" class="dashboard-link">
                        Go to Admin Dashboard
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        $output = ob_get_clean();
        
        return new WP_REST_Response($output, 500, array('Content-Type' => 'text/html'));
    }
    
    /**
     * Handle contact suggestions AJAX endpoint
     */
    public function handle_contact_suggestions() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
            return;
        }
        
        $user_id = get_current_user_id();
        $contact_type = sanitize_text_field($_POST['contact_type']);
        $query = sanitize_text_field($_POST['query']);
        
        // Validate contact type
        if (!in_array($contact_type, array('manager', 'reliever'))) {
            wp_send_json_error('Invalid contact type');
            return;
        }
        
        $suggestions = $this->get_contact_suggestions($user_id, $contact_type, $query);
        
        wp_send_json_success($suggestions);
    }
    
    // ============================================================================
    // TOKEN MANAGEMENT FOR EMAIL APPROVAL LINKS
    // ============================================================================
    
    /**
     * Generate secure approval/rejection tokens for HR email
     */
    public function generate_approval_tokens($request_id, $hr_email) {
        global $wpdb;
        
        $tokens_table = $wpdb->prefix . 'employee_leaves_action_tokens';
        
        // Generate cryptographically secure tokens
        $approve_token = wp_generate_password(64, false);
        $reject_token = wp_generate_password(64, false);
        
        // Set expiration (7 days from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store approve token
        $wpdb->insert($tokens_table, array(
            'request_id' => $request_id,
            'token' => $approve_token,
            'action' => 'approve',
            'recipient_email' => $hr_email,
            'expires_at' => $expires_at
        ));
        
        // Store reject token
        $wpdb->insert($tokens_table, array(
            'request_id' => $request_id,
            'token' => $reject_token,
            'action' => 'reject',
            'recipient_email' => $hr_email,
            'expires_at' => $expires_at
        ));
        
        return array(
            'approve_token' => $approve_token,
            'reject_token' => $reject_token,
            'expires_at' => $expires_at
        );
    }
    
    /**
     * Validate approval token
     */
    public function validate_approval_token($token) {
        global $wpdb;
        
        $tokens_table = $wpdb->prefix . 'employee_leaves_action_tokens';
        $requests_table = $wpdb->prefix . 'employee_leaves_requests';
        
        // Get token data
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tokens_table WHERE token = %s",
            $token
        ));
        
        // Token doesn't exist
        if (!$token_data) {
            return array('valid' => false, 'reason' => 'invalid_token');
        }
        
        // Token already used
        if ($token_data->used_at !== null) {
            return array(
                'valid' => false, 
                'reason' => 'already_used', 
                'used_at' => $token_data->used_at
            );
        }
        
        // Token expired
        if (strtotime($token_data->expires_at) < time()) {
            return array(
                'valid' => false, 
                'reason' => 'expired', 
                'expires_at' => $token_data->expires_at
            );
        }
        
        // Check if request still pending
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM $requests_table WHERE id = %d",
            $token_data->request_id
        ));
        
        if (!$request || $request->status !== 'pending') {
            return array(
                'valid' => false, 
                'reason' => 'request_not_pending',
                'current_status' => $request ? $request->status : 'not_found'
            );
        }
        
        return array('valid' => true, 'token_data' => $token_data);
    }
    
    /**
     * Mark token as used
     */
    public function mark_token_used($token) {
        global $wpdb;
        
        $tokens_table = $wpdb->prefix . 'employee_leaves_action_tokens';
        
        // Get client info for audit trail
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        return $wpdb->update(
            $tokens_table,
            array(
                'used_at' => current_time('mysql'),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('token' => $token)
        );
    }
    
    /**
     * Get client IP address for audit trail
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Cleanup expired tokens (called via wp-cron)
     */
    public function cleanup_expired_tokens() {
        global $wpdb;
        
        $tokens_table = $wpdb->prefix . 'employee_leaves_action_tokens';
        
        // Delete tokens expired more than 30 days ago
        return $wpdb->query("
            DELETE FROM $tokens_table 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    // ============================================================================
    // CONTACT MEMORY SYSTEM
    // ============================================================================
    
    /**
     * Save contact for future auto-fill
     */
    public function save_user_contact($user_id, $email, $contact_type, $display_name = null) {
        global $wpdb;
        
        if (!is_email($email)) {
            return false;
        }
        
        $contacts_table = $wpdb->prefix . 'employee_leaves_user_contacts';
        
        // Check if contact already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $contacts_table WHERE user_id = %d AND contact_type = %s AND email_address = %s",
            $user_id, $contact_type, $email
        ));
        
        if ($existing) {
            // Update usage count and last used
            return $wpdb->update(
                $contacts_table,
                array(
                    'usage_count' => $existing->usage_count + 1,
                    'last_used' => current_time('mysql'),
                    'display_name' => $display_name ?: $existing->display_name
                ),
                array('id' => $existing->id)
            );
        } else {
            // Insert new contact
            return $wpdb->insert(
                $contacts_table,
                array(
                    'user_id' => $user_id,
                    'contact_type' => $contact_type,
                    'email_address' => $email,
                    'display_name' => $display_name,
                    'usage_count' => 1,
                    'last_used' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Get contact suggestions for auto-fill
     */
    public function get_contact_suggestions($user_id, $contact_type, $query = '') {
        global $wpdb;
        
        $contacts_table = $wpdb->prefix . 'employee_leaves_user_contacts';
        
        $where_clause = "WHERE user_id = %d AND contact_type = %s";
        $params = array($user_id, $contact_type);
        
        if (!empty($query)) {
            $where_clause .= " AND (email_address LIKE %s OR display_name LIKE %s)";
            $like_query = '%' . $wpdb->esc_like($query) . '%';
            $params[] = $like_query;
            $params[] = $like_query;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT email_address, display_name, usage_count, last_used 
             FROM $contacts_table 
             $where_clause 
             ORDER BY usage_count DESC, last_used DESC 
             LIMIT 10",
            $params
        ));
        
        return $results;
    }
    
    /**
     * Process contacts from form submission
     */
    public function process_form_contacts($user_id, $manager_emails_string, $reliever_emails_string) {
        if (!empty($manager_emails_string)) {
            $manager_emails = array_map('trim', explode(',', $manager_emails_string));
            foreach ($manager_emails as $email) {
                if (is_email($email)) {
                    $this->save_user_contact($user_id, $email, 'manager');
                }
            }
        }
        
        if (!empty($reliever_emails_string)) {
            $reliever_emails = array_map('trim', explode(',', $reliever_emails_string));
            foreach ($reliever_emails as $email) {
                if (is_email($email)) {
                    $this->save_user_contact($user_id, $email, 'reliever');
                }
            }
        }
    }
}

new WPEmployeeLeaves();