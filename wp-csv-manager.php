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

    // ==================== IMPORT CSV ====================
    if (isset($_POST['import_csv']) && !empty($_FILES['import_file']['tmp_name'])) {
        $table = sanitize_text_field($_POST['import_table']);
        $file  = $_FILES['import_file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {
            $columns = fgetcsv($handle, 1000, ","); // first row = headers

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row = array_combine($columns, $data);
                $wpdb->insert($table, $row);
            }
            fclose($handle);

            echo "<p style='color:green;'><strong>CSV Imported successfully into {$table}.</strong></p>";
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
