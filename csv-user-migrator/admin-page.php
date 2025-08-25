<?php
if (!defined('ABSPATH')) exit;

if (isset($_POST['submit_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
    require_once plugin_dir_path(__FILE__) . 'process-csv.php';
}
?>

<div class="wrap">
    <h1>CSV User Migrator</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required />
        <br><br>
        <input type="submit" name="submit_csv" class="button button-primary" value="Upload & Import" />
    </form>
</div>
