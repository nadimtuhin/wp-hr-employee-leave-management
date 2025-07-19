<?php
/**
 * Mock WordPress User for Testing
 */

class Mock_WP_User {
    
    public $ID;
    public $user_login;
    public $user_email;
    public $user_nicename;
    public $user_url;
    public $user_registered;
    public $user_activation_key;
    public $user_status;
    public $display_name;
    public $caps;
    public $cap_key;
    public $roles;
    public $allcaps;
    
    public function __construct($user_id = 1, $user_login = 'testuser', $user_email = 'test@test.com') {
        $this->ID = $user_id;
        $this->user_login = $user_login;
        $this->user_email = $user_email;
        $this->user_nicename = $user_login;
        $this->user_url = '';
        $this->user_registered = current_time('mysql');
        $this->user_activation_key = '';
        $this->user_status = 0;
        $this->display_name = $user_login;
        $this->caps = [];
        $this->cap_key = 'wp_capabilities';
        $this->roles = ['subscriber'];
        $this->allcaps = [
            'read' => true,
            'level_0' => true,
        ];
    }
    
    /**
     * Set user as administrator
     */
    public function set_admin() {
        $this->roles = ['administrator'];
        $this->allcaps = [
            'read' => true,
            'manage_options' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'publish_posts' => true,
            'manage_categories' => true,
            'moderate_comments' => true,
            'manage_links' => true,
            'upload_files' => true,
            'import' => true,
            'unfiltered_html' => true,
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            'delete_posts' => true,
            'delete_others_posts' => true,
            'delete_published_posts' => true,
            'delete_private_posts' => true,
            'edit_private_posts' => true,
            'read_private_posts' => true,
            'delete_private_pages' => true,
            'edit_private_pages' => true,
            'read_private_pages' => true,
            'delete_users' => true,
            'create_users' => true,
            'unfiltered_upload' => true,
            'edit_dashboard' => true,
            'update_plugins' => true,
            'delete_plugins' => true,
            'install_plugins' => true,
            'update_themes' => true,
            'install_themes' => true,
            'update_core' => true,
            'list_users' => true,
            'remove_users' => true,
            'promote_users' => true,
            'edit_theme_options' => true,
            'delete_themes' => true,
            'export' => true,
            'administrator' => true,
        ];
        return $this;
    }
    
    /**
     * Check if user has capability
     */
    public function has_cap($capability) {
        return isset($this->allcaps[$capability]) && $this->allcaps[$capability];
    }
    
    /**
     * Get user data
     */
    public function get($key) {
        return isset($this->$key) ? $this->$key : null;
    }
    
    /**
     * Check if user exists
     */
    public function exists() {
        return !empty($this->ID);
    }
}