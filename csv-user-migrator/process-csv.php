<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$csvFile = $_FILES['csv_file']['tmp_name'];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle); // First row = column names
    $count  = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        # Example CSV columns: 
        // ID, user_login, user_pass, user_nicename, user_email, 
        // user_url, user_registered, user_activation_key, user_status, display_name

        // Relevant wp_users columns:
        $rainMaker_user_id = trim($data['id']) ?? '';
        $login = trim($data['user_login']) ?? '';
        // $user_pass = trim($data['user_pass']) ?? '';
        $user_nicename = trim($data['user_nicename']) ?? '';
        $email = trim($data['user_email']) ?? '';
        $user_url = trim($data['user_url']) ?? '';
        $user_registered = trim($data['user_registered']) ?? '';
        $user_activation_key = trim($data['user_activation_key']) ?? '';
        $user_status = trim($data['user_status']) ?? '';
        $display_name = trim($data['display_name']) ?? '';

        // Check if user exists already
        $user_id = $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}users WHERE user_email = %s", $email)
        );

        if (!$user_id) {
            // Insert new user into wp_users
            $wpdb->insert(
                $wpdb->prefix . 'users',
                [
                    'user_login' => $login,
                    // 'user_pass' => $user_pass,
                    'user_pass'    => wp_hash_password('Default@123'), // default password
                    'user_nicename' => $user_nicename,
                    'user_email' => $email,
                    'user_url' => $user_url,
                    'user_registered' => $user_registered,
                    'user_activation_key' => $user_activation_key,
                    'user_status' => $user_status,
                    'display_name' => $display_name,
                ]
            );
            $user_id = $wpdb->insert_id;

            // Relevant wp_usermeta keys:
            // -> wp_capabilities
            // -> wp_user_level
            
            // Insert meta keys (wp_capabilities + wp_user_level)
            update_user_meta($user_id, $wpdb->prefix.'capabilities', ['subscriber' => true]);
            update_user_meta($user_id, $wpdb->prefix.'user_level', 0);

            $count++;
        }
    }

    fclose($handle);
    echo "<div class='updated'><p>✅ Created {$count} new users with default meta keys!</p></div>";
}
