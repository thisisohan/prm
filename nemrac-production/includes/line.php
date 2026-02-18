<?php
if (!defined('ABSPATH')) exit;


function npm_line_page() {
    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }
    global $wpdb;

    $section_table = $wpdb->prefix . 'npm_sections';
    $line_table = $wpdb->prefix . 'npm_lines';

    $wpdb->query("
        CREATE TABLE IF NOT EXISTS $line_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_id INT,
            line_name VARCHAR(100)
        )
    ");

    if (isset($_POST['add_line'])) {
        $wpdb->insert($line_table, [
            'section_id' => intval($_POST['section_id']),
            'line_name' => sanitize_text_field($_POST['line_name'])
        ]);
    }

    $sections = $wpdb->get_results("SELECT * FROM $section_table");
    $lines = $wpdb->get_results("
        SELECT l.*, s.section_name 
        FROM $line_table l
        LEFT JOIN $section_table s ON s.id = l.section_id
    ");
    ?>
    <div class="wrap">
        <h1>Lines</h1>

        <form method="post">
            <select name="section_id" required>
                <option value="">Select Section</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?= $s->id ?>"><?= $s->section_name ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="line_name" placeholder="Line Name" required>
            <button class="button button-primary" name="add_line">Add Line</button>
        </form>

        <hr>

        <table class="widefat">
            <tr><th>ID</th><th>Section</th><th>Line</th></tr>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= $l->id ?></td>
                    <td><?= esc_html($l->section_name) ?></td>
                    <td><?= esc_html($l->line_name) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}
