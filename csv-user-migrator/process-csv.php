<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$csvFile = $_FILES['csv_file']['tmp_name'];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle); // First row = column names
    $count  = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);

        $old_id     = trim($data['old_id']);
        $membership = trim($data['membership']);
        $login      = trim($data['user_login']);
        $email      = trim($data['user_email']);
        $first_name = trim($data['first_name']);
        $last_name  = trim($data['last_name']);

        // 1. Check if user exists by email
        $user_id = $wpdb->get_var(
            $wpdb->prepare("SELECT ID FROM {$wpdb->prefix}users WHERE user_email = %s", $email)
        );

        if ($user_id) {
            // Update login if needed
            $wpdb->update(
                $wpdb->prefix . 'users',
                ['user_login' => $login],
                ['ID' => $user_id]
            );
        } else {
            // Insert new user
            $wpdb->insert(
                $wpdb->prefix . 'users',
                [
                    'user_login'   => $login,
                    'user_pass'    => wp_hash_password('Default@123'),
                    'user_email'   => $email,
                    'display_name' => $first_name . ' ' . $last_name
                ]
            );
            $user_id = $wpdb->insert_id;
        }

        // 2. Insert/Update usermeta
        if ($user_id) {
            // Ensure core keys
            if (!get_user_meta($user_id, $wpdb->prefix.'capabilities', true)) {
                update_user_meta($user_id, $wpdb->prefix.'capabilities', ['subscriber' => true]);
            }
            if (!get_user_meta($user_id, $wpdb->prefix.'user_level', true)) {
                update_user_meta($user_id, $wpdb->prefix.'user_level', 0);
            }

            // Rainmaker keys
            update_user_meta($user_id, 'rainmaker_original_userid', $old_id);
            update_user_meta($user_id, 'rainmaker_user_membership', $membership);

            $count++;
        }
    }

    fclose($handle);
    echo "<div class='updated'><p>✅ Successfully processed {$count} users!</p></div>";
}
