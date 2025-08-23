<?php
/**
 * Plugin Name: CSV Manager
 * Description: Import and Export database tables to/from CSV with preview and download.
 * Version: 1.1
 * Author: Ashu
 */

if (!defined('ABSPATH')) exit;

// Add menu in WordPress Admin
add_action('admin_menu', function () {
    add_menu_page(
        'CSV Manager',
        'CSV Manager',
        'manage_options',
        'csv-manager',
        'csv_manager_page'
    );
});

// Admin Page
function csv_manager_page() {
    global $wpdb;

    // Fetch all tables
    $tables = $wpdb->get_col("SHOW TABLES");

    ?>
    <div class="wrap">
        <h1>CSV Manager</h1>

        <!-- Import Section -->
        <h2>Import CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="import_file" accept=".csv" required>
            <select name="import_table" required>
                <option value="">-- Select Table --</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo esc_attr($table); ?>">
                        <?php echo esc_html($table); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <button type="submit" name="import_csv" class="button button-primary">Import CSV</button>
        </form>
        <hr>

        <!-- Export Section -->
        <h2>Export CSV</h2>
        <form method="post">
            <select name="export_table" required>
                <option value="">-- Select Table --</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?php echo esc_attr($table); ?>">
                        <?php echo esc_html($table); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <button type="submit" name="export_csv" class="button button-primary">Preview & Download CSV</button>
        </form>
    </div>
    <?php

    // ==================== IMPORT CSV (batched + transaction + safe) ====================
    if (isset($_POST['import_csv']) && !empty($_FILES['import_file']['tmp_name'])) {
        global $wpdb;

        // Let long imports finish
        ignore_user_abort(true);
        @set_time_limit(0);                 // remove 30s limit
        @ini_set('memory_limit', '512M');   // bump memory for big CSVs

        $table = sanitize_text_field($_POST['import_table']);
        $file  = $_FILES['import_file']['tmp_name'];

        // 0) Resolve real table columns (prevents "Unknown column ' ... '")
        $dbcols = $wpdb->get_col("DESCRIBE {$table}", 0); // actual column names in the table
        if (empty($dbcols)) {
            echo "<p style='color:red;'>Cannot DESCRIBE table <code>{$table}</code>. Check DB permissions.</p>";
            return;
        }

        // Counters
        $inserted = 0;
        $failed   = 0;
        $skipped  = 0;
        $rownum   = 0;

        if (($handle = fopen($file, "r")) !== FALSE) {
            // 1) Read and sanitize headers
            // Use length = 0 (unlimited) so long lines don’t get truncated
            $rawHeaders = fgetcsv($handle, 0, ",");
            if ($rawHeaders === FALSE) {
                echo "<p style='color:red;'>Could not read CSV header row.</p>";
                fclose($handle);
                return;
            }

            // Trim BOM, spaces
            $headers = array_map(function($h) {
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip UTF-8 BOM
                return trim($h);
            }, $rawHeaders);

            // Keep only columns that actually exist in the DB table (order preserved)
            $columns = array_values(array_intersect($headers, $dbcols));
            if (empty($columns)) {
                echo "<p style='color:red;'>None of the CSV headers match columns in <code>{$table}</code>.</p>";
                fclose($handle);
                return;
            }

            // 2) Begin transaction
            $wpdb->query("START TRANSACTION");

            // Batch commit setup
            $batchSize = 500;
            $sinceCommit = 0;

            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                $rownum++;

                // Build an associative row using only allowed columns
                // Map header -> value first, then pick allowed columns in that order
                if (count($headers) !== count($data)) {
                    $skipped++;
                    // Optional: show a tiny hint (don’t flood screen for huge files)
                    if ($skipped <= 5) {
                        echo "<p style='color:#d98500;'>Row {$rownum}: header/data column count mismatch. Skipped.</p>";
                    }
                    continue;
                }

                $fullRow = array_combine($headers, $data);
                $fullRow = array_map('trim', $fullRow);

                // Keep only DB columns
                $row = [];
                foreach ($columns as $col) {
                    $row[$col] = array_key_exists($col, $fullRow) ? $fullRow[$col] : null;
                }

                // Special handling for wp_users-like imports
                if (isset($row['user_pass']) && $row['user_pass'] !== '') {
                    // Hash plain passwords
                    // If already hashed ($P$...), this still "works" but you can skip rehashing by checking prefix.
                    if (strpos($row['user_pass'], '$P$') !== 0) {
                        $row['user_pass'] = wp_hash_password($row['user_pass']);
                    }
                }

                if (isset($row['user_registered'])) {
                    if ($row['user_registered'] === '' || $row['user_registered'] === '0000-00-00 00:00:00') {
                        $row['user_registered'] = current_time('mysql');
                    }
                }

                // Let MySQL assign ID if blank to avoid duplicate key errors on wp_users
                if (array_key_exists('ID', $row) && ($row['ID'] === '' || $row['ID'] === null)) {
                    unset($row['ID']);
                }

                // Insert row (per-row insert inside a transaction)
                $ok = $wpdb->insert($table, $row);

                if ($ok === false) {
                    $failed++;
                    if ($failed <= 5) {
                        echo "<p style='color:red;'>
                            Row {$rownum} failed.<br>
                            <strong>Error:</strong> " . esc_html($wpdb->last_error) . "<br>
                            <strong>SQL:</strong> " . esc_html($wpdb->last_query) . "
                        </p>";
                    }
                } else {
                    $inserted++;
                }

                $sinceCommit++;

                // Commit every batch to avoid huge transactions and show progress
                if ($sinceCommit >= $batchSize) {
                    $wpdb->query("COMMIT");
                    $wpdb->query("START TRANSACTION");
                    $sinceCommit = 0;

                    // Light progress ping (don’t overdo it)
                    echo "<p>Progress: inserted {$inserted}, failed {$failed}, skipped {$skipped}…</p>";
                    @ob_flush(); @flush();
                }
            }

            // Final commit
            $wpdb->query("COMMIT");
            fclose($handle);

            echo "<p style='color:green; font-weight:bold;'>
                Import finished for <code>{$table}</code>.<br>
                Inserted: {$inserted} | Failed: {$failed} | Skipped: {$skipped}
            </p>";
        } else {
            echo "<p style='color:red;'>Could not open uploaded CSV file.</p>";
        }
    }

    // ==================== EXPORT CSV ====================
    if (isset($_POST['export_csv']) && !empty($_POST['export_table'])) {
        $table = sanitize_text_field($_POST['export_table']);

        $results = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        if (!empty($results)) {
            // Create CSV file in uploads
            $filename = $table . "_export_" . date("Y-m-d_H-i-s") . ".csv";
            $filepath = WP_CONTENT_DIR . "/uploads/" . $filename;
            $fileurl  = content_url("/uploads/" . $filename);

            $fp = fopen($filepath, 'w');
            fputcsv($fp, array_keys($results[0])); // headers
            foreach ($results as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);

            echo "<p><a href='{$fileurl}' class='button button-secondary'>Download CSV</a></p>";

            // Preview data
            echo "<h2>Preview of <code>{$table}</code></h2>";
            echo "<div style='overflow-x:auto; max-height:400px; border:1px solid #ccc; margin-top:10px;'>";
            echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse; width:100%;'>";
            echo "<tr>";
            foreach (array_keys($results[0]) as $col) {
                echo "<th style='background:#f9f9f9; text-align:left;'>".esc_html($col)."</th>";
            }
            echo "</tr>";
            foreach ($results as $row) {
                echo "<tr>";
                foreach ($row as $cell) {
                    echo "<td>".esc_html($cell)."</td>";
                }
                echo "</tr>";
            }
            echo "</table></div>";
        } else {
            echo "<p style='color:red;'>No data found in table <code>{$table}</code>.</p>";
        }
    }
}
