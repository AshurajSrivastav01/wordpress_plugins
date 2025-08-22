<?php
// Add menu
add_action('admin_menu', function () {
    add_menu_page(
        'CSV Manager',
        'CSV Manager',
        'manage_options',
        'wp-csv-manager',
        'wp_csv_manager_admin_page',
        'dashicons-database-import',
        80
    );
});

function wp_csv_manager_admin_page() {
    ?>
    <div class="wrap">
        <h1>WP CSV Manager</h1>

        <h2>Export Data</h2>
        <form method="post" action="">
            <input type="text" name="table_name" placeholder="Table Name" required>
            <input type="submit" name="export_csv" class="button button-primary" value="Export CSV">
        </form>

        <?php
        // Show table if export button clicked
        if (isset($_POST['export_csv'])) {
            $exporter = new WP_CSV_Export($_POST['table_name']);
            $exporter->display_table();
        }
        ?>

        <hr>

        <h2>Import Data</h2>
        <form method="post" enctype="multipart/form-data" action="">
            <input type="file" name="csv_file" required>
            <input type="text" name="table_name" placeholder="Table Name" required>
            <input type="submit" name="import_csv" class="button button-primary" value="Import CSV">
        </form>

        <?php
        // Handle import
        if (isset($_POST['import_csv'])) {
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $importer = new WP_CSV_Import($_POST['table_name'], $_FILES['csv_file']['tmp_name']);
                $importer->import();
            }
        }
        ?>
    </div>
    <?php
}
