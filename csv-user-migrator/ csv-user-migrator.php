<?php
/**
 * Plugin Name: CSV User Migrator
 * Description: Import users from CSV and update wp_users + wp_usermeta with Rainmaker data.
 * Version: 1.0
 * Author: LDS Engineers
 */

if (!defined('ABSPATH')) exit;

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'CSV User Migrator',
        'User Migrator',
        'manage_options',
        'csv-user-migrator',
        'csv_user_migrator_admin_page',
        'dashicons-upload',
        20
    );
});

// Load admin page
function csv_user_migrator_admin_page() {
    include plugin_dir_path(__FILE__) . 'admin-page.php';
}
    