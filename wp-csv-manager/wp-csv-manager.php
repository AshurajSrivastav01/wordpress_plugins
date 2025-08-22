<?php
/**
 * Plugin Name: WP CSV Manager
 * Description: Export and import database tables to/from CSV.
 * Version: 1.1.0
 * Author: Ashu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin path
define( 'WP_CSV_MANAGER_PATH', plugin_dir_path( __FILE__ ) );

// Include files
require_once WP_CSV_MANAGER_PATH . 'includes/class-csv-export.php';
require_once WP_CSV_MANAGER_PATH . 'includes/class-csv-import.php';
require_once WP_CSV_MANAGER_PATH . 'admin/admin-page.php';
