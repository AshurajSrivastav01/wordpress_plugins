<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$csvFile = $_FILES['csv_file']['tmp_name'];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle); // First row = column names
    $count  = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        // Example CSV columns: user_login, user_email, first_name, last_name
        $login      = trim($data['user_login']);
        $email      = trim($data['user_email']);
        $first_name = trim($data['first_name']);
        $last_name  = trim($data['last_name']);

        // Skip empty emails
        if (empty($email)) {
            continue;
        }

        // Check if user exists already
        $user_id = $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}users WHERE user_email = %s", $email)
        );

        if (!$user_id) {
            // Insert new user into wp_users
            $wpdb->insert(
                $wpdb->prefix . 'users',
                [
                    'user_login'   => $login,
                    'user_pass'    => wp_hash_password('Default@123'), // default password
                    'user_email'   => $email,
                    'display_name' => $first_name . ' ' . $last_name
                ]
            );
            $user_id = $wpdb->insert_id;

            // Insert meta keys (wp_capabilities + wp_user_level)
            update_user_meta($user_id, $wpdb->prefix.'capabilities', ['subscriber' => true]);
            update_user_meta($user_id, $wpdb->prefix.'user_level', 0);

            $count++;
        }
    }

    fclose($handle);
    echo "<div class='updated'><p>✅ Created {$count} new users with default meta keys!</p></div>";
}
