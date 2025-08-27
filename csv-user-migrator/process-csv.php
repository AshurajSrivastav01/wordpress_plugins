<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

try {
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
                $password_plain = "MySecret123"; // your own password
                $password = wp_hash_password($password_plain);

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

            if (!empty($membership_ids)) {
                $membership_ids_array = explode(',', $membership_ids);
                foreach ($membership_ids_array as $membership_id) {
                    $membership_id = trim($membership_id);
                    if (!empty($membership_id)) {
                        # Get Course IDs from Membership ID
                        $sql = $wpdb->prepare(
                            "SELECT id_courses FROM {$wpdb->prefix}membership_course_mapping WHERE id_membership = %d",
                            $membership_id
                        );
                        $result = $wpdb->get_row($sql);

                        if ($result && !empty($result->id_courses)) {
                            $course_ids_array = explode(',', $result->id_courses);

                            foreach ($course_ids_array as $course_id) {
                                $course_id = trim($course_id);
                                if (!empty($course_id)) {
                                    # Enroll user in the course
                                    $enrollment_result = ld_update_course_access($user_id, $course_id);

                                    if (is_wp_error($enrollment_result)) {
                                        error_log("User ID $user_id enrollment failed for Course ID $course_id: " . $enrollment_result->get_error_message());
                                    } else {
                                        error_log("User ID $user_id enrolled successfully in Course ID $course_id.");
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                error_log("User ID $user_id has no membership IDs to enroll.");
            }
        }

        echo "<p style='color:green;'>CSV processing finished. Check debug.log for details.</p>";
    } else {
        echo "<p style='color:red;'>No CSV file uploaded.</p>";
    }
} catch (\Throwable $th) {
            echo "<p style='color:red;'>Error: " . $th->getMessage() . "</p>";
}

die;
