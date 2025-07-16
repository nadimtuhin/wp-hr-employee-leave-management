<?php
/**
 * Plugin Name: WP Employee Leaves
 * Description: A comprehensive employee leave management system for WordPress
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-employee-leaves
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_EMPLOYEE_LEAVES_VERSION', '1.0.0');
define('WP_EMPLOYEE_LEAVES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EMPLOYEE_LEAVES_PLUGIN_URL', plugin_dir_url(__FILE__));

class WPEmployeeLeaves {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Ensure database structure is up to date
        add_action('plugins_loaded', array($this, 'update_database_structure'));
    }
    
    public function init() {
        load_plugin_textdomain('wp-employee-leaves', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('wp_ajax_approve_leave_request', array($this, 'handle_approve_request'));
            add_action('wp_ajax_reject_leave_request', array($this, 'handle_reject_request'));
            add_action('wp_ajax_create_leave_page', array($this, 'handle_create_leave_page'));
            add_action('wp_ajax_add_shortcode_to_page', array($this, 'handle_add_shortcode_to_page'));
        } else {
            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        }
        
        // AJAX handlers should be registered for both admin and frontend
        add_action('wp_ajax_submit_leave_request', array($this, 'handle_leave_request_submission'));
        add_action('wp_ajax_nopriv_submit_leave_request', array($this, 'handle_leave_request_submission'));
        
        
        add_shortcode('employee_leave_form', array($this, 'leave_form_shortcode'));
    }
    
    public function frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-employee-leaves-frontend', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'frontend/css/style.css', array(), WP_EMPLOYEE_LEAVES_VERSION);
        wp_enqueue_script('wp-employee-leaves-frontend', WP_EMPLOYEE_LEAVES_PLUGIN_URL . 'frontend/js/script.js', array('jquery'), WP_EMPLOYEE_LEAVES_VERSION, true);
        
        wp_localize_script('wp-employee-leaves-frontend', 'wp_employee_leaves_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('employee_leave_nonce')
        ));
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
                'reject_nonce' => wp_create_nonce('reject_leave_nonce')
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
            <h1><?php echo esc_html__('Employee Leaves Dashboard', 'wp-employee-leaves'); ?></h1>
            
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2><?php echo esc_html__('Welcome to Employee Leaves Management', 'wp-employee-leaves'); ?></h2>
                    <p><?php echo esc_html__('Manage your employee leave requests efficiently. Current year: ', 'wp-employee-leaves') . $current_year; ?></p>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="number"><?php echo $total_requests; ?></div>
                    <div class="label"><?php _e('Total Requests', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item pending">
                    <div class="number"><?php echo $pending_requests; ?></div>
                    <div class="label"><?php _e('Pending Approval', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item approved">
                    <div class="number"><?php echo $approved_requests; ?></div>
                    <div class="label"><?php _e('Approved', 'wp-employee-leaves'); ?></div>
                </div>
                <div class="stat-item rejected">
                    <div class="number"><?php echo $rejected_requests; ?></div>
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
                                                    <?php echo esc_html(ucfirst($request->status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(date('M j, Y', strtotime($request->created_at))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="text-align: center; margin-top: 15px;">
                                <a href="<?php echo admin_url('admin.php?page=wp-employee-leaves-requests'); ?>" class="button button-primary">
                                    <?php _e('View All Requests', 'wp-employee-leaves'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3><?php _e('Quick Actions', 'wp-employee-leaves'); ?></h3>
                    <div class="card-content">
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=wp-employee-leaves-requests'); ?>" class="button button-primary">
                                <?php _e('Manage Requests', 'wp-employee-leaves'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=wp-employee-leaves-settings'); ?>" class="button button-secondary">
                                <?php _e('Settings', 'wp-employee-leaves'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=wp-employee-leaves-employees'); ?>" class="button button-secondary">
                                <?php _e('Employee Management', 'wp-employee-leaves'); ?>
                            </a>
                        </p>
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
        
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        
        // Get all leave requests for the year
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM $requests_table r 
             JOIN {$wpdb->users} u ON r.employee_id = u.ID 
             WHERE YEAR(r.created_at) = %d 
             ORDER BY r.created_at DESC",
            $year
        ));
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Leave Requests', 'wp-employee-leaves'); ?></h1>
            
            <div class="year-filter">
                <label for="year-select"><?php _e('Year:', 'wp-employee-leaves'); ?></label>
                <select id="year-select" onchange="location = this.value;">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo admin_url('admin.php?page=wp-employee-leaves-requests&year=' . $y); ?>" 
                                <?php selected($year, $y); ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
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
                                        <?php echo esc_html(ucfirst($request->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($request->created_at))); ?></td>
                                <td>
                                    <?php if ($request->status === 'pending'): ?>
                                        <button class="button button-primary approve-request" 
                                                data-request-id="<?php echo $request->id; ?>">
                                            <?php _e('Approve', 'wp-employee-leaves'); ?>
                                        </button>
                                        <button class="button button-secondary reject-request" 
                                                data-request-id="<?php echo $request->id; ?>">
                                            <?php _e('Reject', 'wp-employee-leaves'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-<?php echo $request->status === 'approved' ? 'yes' : 'no'; ?>"></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        
        <style>
        .year-filter {
            margin: 20px 0;
        }
        .status-pending {
            color: #856404;
            background: #fff3cd;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .status-approved {
            color: #155724;
            background: #d4edda;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .status-rejected {
            color: #721c24;
            background: #f8d7da;
            padding: 2px 8px;
            border-radius: 3px;
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
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Employee Leaves Settings', 'wp-employee-leaves'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wp-employee-leaves-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=leave-types" class="nav-tab <?php echo $active_tab == 'leave-types' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Leave Types', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=email-templates" class="nav-tab <?php echo $active_tab == 'email-templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email Templates', 'wp-employee-leaves'); ?>
                </a>
                <a href="?page=wp-employee-leaves-settings&tab=page-management" class="nav-tab <?php echo $active_tab == 'page-management' ? 'nav-tab-active' : ''; ?>">
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
        
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        
        if ($active_tab == 'general') {
            $hr_email = sanitize_email($_POST['hr_email']);
            update_option('wp_employee_leaves_hr_email', $hr_email);
            
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
                            <input type="text" name="leave_types[<?php echo $type->id; ?>][name]" value="<?php echo esc_attr($type->name); ?>" class="regular-text" readonly>
                        </td>
                        <td>
                            <input type="number" name="leave_types[<?php echo $type->id; ?>][yearly_allocation]" value="<?php echo esc_attr($type->yearly_allocation); ?>" step="0.5" min="0" class="small-text">
                        </td>
                        <td>
                            <input type="checkbox" name="leave_types[<?php echo $type->id; ?>][active]" value="1" <?php checked($type->active, 1); ?>>
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
                            <label for="template_<?php echo $template->id; ?>_subject"><?php _e('Subject', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="template_<?php echo $template->id; ?>_subject" name="email_templates[<?php echo $template->id; ?>][subject]" value="<?php echo esc_attr($template->subject); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_<?php echo $template->id; ?>_body"><?php _e('Body', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <textarea id="template_<?php echo $template->id; ?>_body" name="email_templates[<?php echo $template->id; ?>][body]" rows="10" class="large-text"><?php echo esc_textarea($template->body); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="template_<?php echo $template->id; ?>_active"><?php _e('Active', 'wp-employee-leaves'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="template_<?php echo $template->id; ?>_active" name="email_templates[<?php echo $template->id; ?>][active]" value="1" <?php checked($template->active, 1); ?>>
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
            <h3><?php _e('Create New Leave Request Page', 'wp-employee-leaves'); ?></h3>
            <p><?php _e('Create a new page with the leave request form automatically added.', 'wp-employee-leaves'); ?></p>
            
            <div class="page-creation-controls">
                <input type="text" id="leave-page-title" placeholder="<?php _e('Enter page title (e.g., Employee Leave Request)', 'wp-employee-leaves'); ?>" class="regular-text">
                <button type="button" id="create-leave-page" class="button button-primary">
                    <?php _e('Create Page', 'wp-employee-leaves'); ?>
                </button>
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
                        <option value="<?php echo $page->ID; ?>"><?php echo esc_html($page->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="add-shortcode-to-page" class="button button-secondary">
                    <?php _e('Add Shortcode', 'wp-employee-leaves'); ?>
                </button>
            </div>
        </div>
        
        <div class="shortcode-info">
            <h3><?php _e('Manual Shortcode Usage', 'wp-employee-leaves'); ?></h3>
            <p><?php _e('You can manually add the leave request form to any page or post using this shortcode:', 'wp-employee-leaves'); ?></p>
            <p>
                <code>[employee_leave_form]</code>
                <button type="button" id="copy-shortcode" class="button button-small">
                    <?php _e('Copy Shortcode', 'wp-employee-leaves'); ?>
                </button>
            </p>
            
            <h4><?php _e('Shortcode Attributes (Optional)', 'wp-employee-leaves'); ?></h4>
            <ul>
                <li><code>[employee_leave_form title="Custom Title"]</code> - <?php _e('Custom form title', 'wp-employee-leaves'); ?></li>
                <li><code>[employee_leave_form show_balance="false"]</code> - <?php _e('Hide balance information', 'wp-employee-leaves'); ?></li>
                <li><code>[employee_leave_form redirect_url="/thank-you"]</code> - <?php _e('Custom redirect after submission', 'wp-employee-leaves'); ?></li>
            </ul>
        </div>
        <?php
    }
    
    public function activate() {
        $this->create_tables();
        $this->insert_default_leave_types();
        $this->insert_default_email_templates();
        flush_rewrite_rules();
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_leave_types);
        dbDelta($sql_requests);
        dbDelta($sql_dates);
        dbDelta($sql_balances);
        dbDelta($sql_logs);
        dbDelta($sql_notifications);
        dbDelta($sql_templates);
        
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
                'subject' => 'New Leave Request Submitted - {{employee_name}}',
                'body' => 'Dear HR Team,

A new leave request has been submitted by {{employee_name}} ({{employee_email}}).

Leave Details:
- Employee: {{employee_name}}
- Leave Dates: {{leave_dates}}
- Leave Types: {{leave_types}}
- Reason: {{reason}}
- Manager Emails: {{manager_emails}}
- Reliever Emails: {{reliever_emails}}

Please review and approve/reject this request in the admin dashboard.

Best regards,
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
                    <label for="manager_emails"><?php _e('Line Manager Emails (Optional)', 'wp-employee-leaves'); ?></label>
                    <textarea id="manager_emails" name="manager_emails" rows="3" placeholder="<?php _e('e.g., manager1@company.com, manager2@company.com', 'wp-employee-leaves'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="reliever_emails"><?php _e('Leave Reliever Emails (Optional)', 'wp-employee-leaves'); ?></label>
                    <textarea id="reliever_emails" name="reliever_emails" rows="3" placeholder="<?php _e('e.g., colleague1@company.com, colleague2@company.com', 'wp-employee-leaves'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label><?php _e('Leave Dates and Types', 'wp-employee-leaves'); ?></label>
                    <div id="leave-dates-container">
                        <div class="date-type-row">
                            <input type="date" class="leave-date-picker" name="leave_dates[]" min="<?php echo date('Y-m-d'); ?>">
                            <select name="leave_types[]">
                                <option value=""><?php _e('Select leave type', 'wp-employee-leaves'); ?></option>
                                <?php foreach ($leave_types as $type): ?>
                                    <option value="<?php echo $type->id; ?>"><?php echo esc_html($type->name); ?></option>
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
                <button class="success-modal-close" onclick="closeSuccessModal()"><?php _e('Close', 'wp-employee-leaves'); ?></button>
            </div>
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
        $leave_dates = $_POST['leave_dates'];
        $leave_types = $_POST['leave_types'];
        
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
        
        // Log the action
        $this->log_leave_action($user_id, $request_id, 'submitted', 'Leave request submitted');
        
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
        check_ajax_referer('approve_leave_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        $request_id = intval($_POST['request_id']);
        $this->approve_leave_request($request_id);
        
        wp_die(json_encode(array('success' => true, 'message' => 'Leave request approved successfully')));
    }
    
    public function handle_reject_request() {
        check_ajax_referer('reject_leave_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
        }
        
        $request_id = intval($_POST['request_id']);
        $this->reject_leave_request($request_id);
        
        wp_die(json_encode(array('success' => true, 'message' => 'Leave request rejected successfully')));
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
    
    // Page Management AJAX Handlers
    public function handle_create_leave_page() {
        // Log the start of function for debugging
        error_log('handle_create_leave_page called');
        
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
}

new WPEmployeeLeaves();