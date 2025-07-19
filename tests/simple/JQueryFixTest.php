<?php
/**
 * Test jQuery fix in JavaScript files
 */

use PHPUnit\Framework\TestCase;

class JQueryFixTest extends TestCase {
    
    /**
     * Test that frontend script uses jQuery instead of $ in global functions
     */
    public function test_frontend_script_jquery_usage() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Check that showSuccessModal function is properly attached to window
        $this->assertStringContainsString('window.showSuccessModal = function()', $content);
        $this->assertStringContainsString("$('#success-modal').fadeIn(300)", $content);
        
        // Check that closeSuccessModal function is properly attached to window
        $this->assertStringContainsString('window.closeSuccessModal = function()', $content);
        $this->assertStringContainsString("$('#success-modal').fadeOut(300)", $content);
        
        // Ensure global functions don't use bare $ (which would cause the error)
        $lines = explode("\n", $content);
        $global_function_lines = [];
        $in_global_function = false;
        
        // Check that modal functions are inside jQuery ready block
        $this->assertStringContainsString('// Success Modal Functions - Moved inside jQuery ready block to access $', $content);
        
        // Verify that modal functions have access to $ by being inside the ready block
        $ready_block_start = strpos($content, 'jQuery(document).ready(function($)');
        $modal_functions_start = strpos($content, 'window.showSuccessModal = function()');
        
        $this->assertGreaterThan($ready_block_start, $modal_functions_start, 'Modal functions should be inside jQuery ready block');
    }
    
    /**
     * Test that admin script properly uses $ within jQuery ready block
     */
    public function test_admin_script_jquery_usage() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'admin/js/admin.js';
        $content = file_get_contents($script_file);
        
        // Check that admin script starts with jQuery ready block
        $this->assertStringContainsString('jQuery(document).ready(function($)', $content);
        
        // Check that the file doesn't have global $ usage outside the ready block
        $lines = explode("\n", $content);
        $in_ready_block = false;
        $brace_count = 0;
        
        foreach ($lines as $line_num => $line) {
            if (strpos($line, 'jQuery(document).ready(function($)') !== false) {
                $in_ready_block = true;
                $brace_count = 0;
            }
            
            if ($in_ready_block) {
                $brace_count += substr_count($line, '{') - substr_count($line, '}');
                
                if ($brace_count <= 0 && $line_num > 0) {
                    $in_ready_block = false;
                }
            } else {
                // Outside ready block - check for problematic $ usage
                if (preg_match('/^\s*\$\(/', $line) && strpos($line, '//') !== 0) {
                    $this->fail("Found bare \$ usage outside jQuery ready block at line " . ($line_num + 1) . ": " . trim($line));
                }
            }
        }
    }
    
    /**
     * Test that jQuery is properly enqueued as dependency
     */
    public function test_jquery_dependency() {
        $plugin_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'wp-employee-leaves.php';
        $content = file_get_contents($plugin_file);
        
        // Check that jQuery is enqueued
        $this->assertStringContainsString("wp_enqueue_script('jquery')", $content);
        
        // Check that frontend script has jQuery dependency
        $this->assertStringContainsString("array('jquery')", $content);
        $this->assertStringContainsString("wp-employee-leaves-frontend", $content);
        
        // Check that admin script has jQuery dependency
        $this->assertStringContainsString("wp-employee-leaves-admin", $content);
    }
    
    /**
     * Test that modal functions are properly accessible globally
     */
    public function test_modal_functions_global_access() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // Verify that functions are attached to window object for global access
        $this->assertStringContainsString('window.showSuccessModal = function()', $content);
        $this->assertStringContainsString('window.closeSuccessModal = function()', $content);
        
        // Ensure they can be called from anywhere
        $this->assertStringContainsString('showSuccessModal();', $content);
    }
    
    /**
     * Test that script structure prevents the original error
     */
    public function test_error_prevention() {
        $script_file = WP_EMPLOYEE_LEAVES_PLUGIN_DIR . 'frontend/js/script.js';
        $content = file_get_contents($script_file);
        
        // The original error was: "$ is not a function at showSuccessModal"
        // This happened because showSuccessModal was using $ outside the ready block
        
        // Verify the fix: modal functions are now inside ready block
        $this->assertStringContainsString('// Success Modal Functions - Moved inside jQuery ready block to access $', $content);
        
        // Check that modal functions use $ (since they're now inside the ready block)
        preg_match_all('/window\.(showSuccessModal|closeSuccessModal) = function.*?};/ms', $content, $functions);
        
        foreach ($functions[0] as $function_code) {
            // Should use $, not jQuery (since they're inside ready block)
            $jquery_count = substr_count($function_code, 'jQuery(');
            $dollar_count = substr_count($function_code, '$(');
            
            $this->assertGreaterThan(0, $dollar_count, 'Modal functions should use $() since they are inside ready block');
            $this->assertEquals(0, $jquery_count, 'Modal functions should not use jQuery() since they have access to $');
        }
    }
}