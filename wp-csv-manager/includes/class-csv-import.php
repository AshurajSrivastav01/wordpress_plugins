<?php
class WP_CSV_Import {
    private $table;
    private $file;

    public function __construct($table, $file) {
        $this->table = sanitize_text_field($table);
        $this->file  = $file;
    }

    public function import() {
        global $wpdb;

        if (($handle = fopen($this->file, 'r')) !== false) {
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row);
                $wpdb->insert($this->table, $data);
            }

            fclose($handle);
            echo "<div class='notice notice-success'><p>Data imported successfully into {$this->table}</p></div>";
        } else {
            echo "<div class='notice notice-error'><p>Failed to open CSV file.</p></div>";
        }
    }
}
