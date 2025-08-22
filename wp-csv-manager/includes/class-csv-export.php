<?php
class WP_CSV_Export {
    private $table;

    public function __construct($table) {
        $this->table = sanitize_text_field($table);
    }

    // Show data in WordPress admin as table
    public function display_table() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$this->table}", ARRAY_A);

        if (empty($results)) {
            echo "<div class='notice notice-error'><p>No data found in table: {$this->table}</p></div>";
            return;
        }

        echo "<h3>Exported Data from {$this->table}</h3>";
        echo "<table class='widefat striped'>";
        echo "<thead><tr>";

        // Table headers
        foreach (array_keys($results[0]) as $col) {
            echo "<th>" . esc_html($col) . "</th>";
        }
        echo "</tr></thead><tbody>";

        // Table rows
        foreach ($results as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . esc_html($cell) . "</td>";
            }
            echo "</tr>";
        }

        echo "</tbody></table>";
    }
}
