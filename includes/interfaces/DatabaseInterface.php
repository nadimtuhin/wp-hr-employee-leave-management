<?php
/**
 * Database Interface for Dependency Injection and Testing
 */

if (!defined('ABSPATH')) {
    exit;
}

interface WP_Employee_Leaves_Database_Interface {
    
    /**
     * Insert data into table
     */
    public function insert($table, $data);
    
    /**
     * Get single row from database
     */
    public function get_row($query, $params = array());
    
    /**
     * Get multiple rows from database
     */
    public function get_results($query, $params = array());
    
    /**
     * Get single variable from database
     */
    public function get_var($query, $params = array());
    
    /**
     * Update data in table
     */
    public function update($table, $data, $where);
    
    /**
     * Delete data from table
     */
    public function delete($table, $where);
    
    /**
     * Execute raw query
     */
    public function query($query);
    
    /**
     * Prepare SQL statement
     */
    public function prepare($query, ...$args);
    
    /**
     * Get table prefix
     */
    public function get_prefix();
    
    /**
     * Escape string for LIKE queries
     */
    public function esc_like($text);
}