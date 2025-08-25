<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if a file was uploaded
if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    global $wpdb;

    $fileTmpPath = $_FILES['csv_file']['tmp_name'];

    $user_ids = []; // To Store created user IDs

    if (($handle = fopen($fileTmpPath, 'r')) !== false) {
        $header = fgetcsv($handle); // Read header row
        $row_number = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;

            // Map CSV row to header names
            $data = array_combine($header, $row);

            // Extract user info
            $username     = sanitize_user($data['user_login']);
            $email        = sanitize_email($data['user_email']);
            $nicename     = sanitize_text_field($data['user_nicename']);
            $display_name = sanitize_text_field($data['display_name']);

            // Use default password if not given
            $password = wp_generate_password();

            // Skip if user exists
            if (username_exists($username) || email_exists($email)) {
                error_log("Row $row_number skipped: User already exists ($username / $email).");
                continue;
            }

            // Create user
            $user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($user_id)) {
                // Store created user ID
                $user_ids[] = $user_id;

                // Update extra fields
                wp_update_user([
                    'ID'           => $user_id,
                    'user_nicename'=> $nicename,
                    'display_name' => $display_name,
                ]);

                // Default WordPress meta
                update_user_meta($user_id, $wpdb->prefix.'capabilities', ['subscriber' => true]);
                update_user_meta($user_id, $wpdb->prefix.'user_level', 0);

                // ✅ New meta keys from CSV
                update_user_meta($user_id, 'rainmaker_user_id', $data['rainmaker_user_id']);
                update_user_meta($user_id, 'rainmaker_user_membership', $data['rainmaker_user_membership']);

                error_log("Row $row_number imported successfully: User ID $user_id ($username)");
            } else {
                error_log("Row $row_number failed: " . $user_id->get_error_message());
            }
        }

        fclose($handle);
    } else {
        echo "<p style='color:red;'>Error opening CSV file.</p>";
    }

    // Now we are going to enable Course enrolment.
    foreach ($user_ids as $user_id){
        // Get Membership ID's from user meta
        $membership_ids = get_user_meta($user_id, 'rainmaker_user_membership', true);
        
        // old
        // ---
        // -> User id -> membership-> courseids

        // New
        // ---
        // -> Get all User id from (wp_users) and start -- foreach loop
        // -- under the Loop
        // -> get Memebership from wp_usermeta by using "rainmaker_user_membership" key and store in to an array.
        // -> start membership foreach loop for course ids and store into an array
        // -> start again an new loop to insert data in wp_usermeta table and the key and value.

        // Requirments:
        // -> $user_id = [];
        // -> $courses_id = []; 
    }

    echo "<p style='color:green;'>CSV processing finished. Check debug.log for details.</p>";
} else {
    echo "<p style='color:red;'>No CSV file uploaded.</p>";
}

die;
