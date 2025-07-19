<?php
/**
 * Tests for single email fields functionality
 */

use PHPUnit\Framework\TestCase;

class EmailFieldsTest extends TestCase {
    
    /**
     * Test that HTML structure is updated correctly
     */
    public function test_email_fields_html_structure() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Check for new email container structure
        $this->assertStringContainsString('manager-emails-container', $content);
        $this->assertStringContainsString('reliever-emails-container', $content);
        $this->assertStringContainsString('email-fields-container', $content);
        
        // Check for email field inputs instead of textareas
        $this->assertStringContainsString('type="email"', $content);
        $this->assertStringContainsString('class="email-field manager-email"', $content);
        $this->assertStringContainsString('class="email-field reliever-email"', $content);
        
        // Check for add buttons
        $this->assertStringContainsString('add-email-field', $content);
        $this->assertStringContainsString('data-target="manager"', $content);
        $this->assertStringContainsString('data-target="reliever"', $content);
        
        // Check for remove buttons
        $this->assertStringContainsString('remove-email-field', $content);
        
        // Ensure old textarea structure is removed
        $this->assertStringNotContainsString('<textarea id="manager_emails"', $content);
        $this->assertStringNotContainsString('<textarea id="reliever_emails"', $content);
    }
    
    /**
     * Test JavaScript email management functions
     */
    public function test_javascript_email_functions() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Check for new email management functions
        $this->assertStringContainsString('collectEmails', $content);
        $this->assertStringContainsString('validateEmailInput', $content);
        $this->assertStringContainsString('validateAllEmails', $content);
        $this->assertStringContainsString('updateEmailRemoveButtons', $content);
        
        // Check for event handlers
        $this->assertStringContainsString("$('.add-email-field').on('click'", $content);
        $this->assertStringContainsString("$(document).on('click', '.remove-email-field'", $content);
        $this->assertStringContainsString("$(document).on('blur', '.email-field'", $content);
        $this->assertStringContainsString("$(document).on('input', '.email-field'", $content);
        
        // Check that old validateEmailField function is removed
        $this->assertStringNotContainsString('function validateEmailField', $content);
        
        // Check form data collection uses new method
        $this->assertStringContainsString("manager_emails: collectEmails('#manager-emails-container')", $content);
        $this->assertStringContainsString("reliever_emails: collectEmails('#reliever-emails-container')", $content);
    }
    
    /**
     * Test CSS styling for email fields
     */
    public function test_email_fields_css() {
        $css_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/css/style.css';
        $content = file_get_contents($css_file);
        
        // Check for email field container styles
        $this->assertStringContainsString('.email-fields-container', $content);
        $this->assertStringContainsString('.email-field-row', $content);
        $this->assertStringContainsString('.email-field', $content);
        
        // Check for button styles
        $this->assertStringContainsString('.remove-email-field', $content);
        $this->assertStringContainsString('.add-email-field', $content);
        
        // Check for validation styles
        $this->assertStringContainsString('.email-field.valid-email', $content);
        $this->assertStringContainsString('.email-field.invalid-email', $content);
        
        // Check for responsive design
        $this->assertStringContainsString('display: flex', $content);
        $this->assertStringContainsString('gap: 10px', $content);
    }
    
    /**
     * Test email validation regex
     */
    public function test_email_validation_regex() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Check that email validation regex is present
        $this->assertStringContainsString('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $content);
        
        // Test the regex pattern (extracted from the code)
        $pattern = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
        
        // Valid emails
        $this->assertEquals(1, preg_match($pattern, 'test@example.com'));
        $this->assertEquals(1, preg_match($pattern, 'user.name@company.co.uk'));
        $this->assertEquals(1, preg_match($pattern, 'email123@test-domain.org'));
        
        // Invalid emails
        $this->assertEquals(0, preg_match($pattern, 'invalid.email'));
        $this->assertEquals(0, preg_match($pattern, '@example.com'));
        $this->assertEquals(0, preg_match($pattern, 'test@'));
        $this->assertEquals(0, preg_match($pattern, 'test @example.com'));
    }
    
    /**
     * Test collectEmails function logic
     */
    public function test_collect_emails_function() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Check that collectEmails joins emails with comma
        $this->assertStringContainsString("return emails.join(', ')", $content);
        
        // Check that it validates emails before collecting
        $this->assertStringContainsString('if (email && validateEmail(email))', $content);
    }
    
    /**
     * Test backend compatibility
     */
    public function test_backend_compatibility() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Backend should still use sanitize_textarea_field for processing comma-separated emails
        $this->assertStringContainsString('sanitize_textarea_field($_POST[\'manager_emails\'])', $content);
        $this->assertStringContainsString('sanitize_textarea_field($_POST[\'reliever_emails\'])', $content);
        
        // Database structure should remain unchanged (text fields)
        $this->assertStringContainsString('manager_emails text DEFAULT NULL', $content);
        $this->assertStringContainsString('reliever_emails text DEFAULT NULL', $content);
    }
    
    /**
     * Test form submission handling
     */
    public function test_form_submission_handling() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Check validation before submission
        $this->assertStringContainsString('var managerEmailsValid = validateAllEmails(\'#manager-emails-container\')', $content);
        $this->assertStringContainsString('var relieverEmailsValid = validateAllEmails(\'#reliever-emails-container\')', $content);
        
        // Check error handling for invalid emails
        $this->assertStringContainsString('if (!managerEmailsValid || !relieverEmailsValid)', $content);
        $this->assertStringContainsString('Please fix the email validation errors before submitting', $content);
    }
    
    /**
     * Test mobile responsiveness considerations
     */
    public function test_mobile_responsiveness() {
        $css_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/css/style.css';
        $content = file_get_contents($css_file);
        
        // Check for flexible layout
        $this->assertStringContainsString('flex: 1', $content);
        
        // Check for appropriate sizing
        $this->assertStringContainsString('min-width: 35px', $content); // Remove button
        $this->assertStringContainsString('padding: 8px 12px', $content); // Email field padding
    }
    
    /**
     * Test accessibility features
     */
    public function test_accessibility_features() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Check for proper input types
        $this->assertStringContainsString('type="email"', $content);
        
        // Check for placeholder text
        $this->assertStringContainsString('placeholder="<?php esc_attr_e(\'manager@company.com\'', $content);
        $this->assertStringContainsString('placeholder="<?php esc_attr_e(\'colleague@company.com\'', $content);
        
        // Check for label associations
        $this->assertStringContainsString('Line Manager Emails (Optional)', $content);
        $this->assertStringContainsString('Leave Reliever Emails (Optional)', $content);
    }
    
    /**
     * Test internationalization support
     */
    public function test_internationalization() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Check for translatable strings
        $this->assertStringContainsString('_e(\'+ Add Manager Email\'', $content);
        $this->assertStringContainsString('_e(\'+ Add Reliever Email\'', $content);
        $this->assertStringContainsString('esc_attr_e(\'manager@company.com\'', $content);
        $this->assertStringContainsString('esc_attr_e(\'colleague@company.com\'', $content);
    }
}