<?php
if (!defined('ABSPATH')) exit;

function npm_section_page() {
    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }
    global $wpdb;
    $table = $wpdb->prefix . 'npm_sections';

    // Create table if not exists
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_name VARCHAR(100) NOT NULL,
            section_in_charge VARCHAR(100) NULL
        )
    ");

    // Add new section
    if (isset($_POST['add_section'])) {
        $wpdb->insert($table, [
            'section_name' => sanitize_text_field($_POST['section_name'])
        ]);
    }

    // Assign section in-charge
    if (isset($_POST['assign_incharge'])) {
        $section_id = intval($_POST['section_id']);
        $in_charge  = sanitize_text_field($_POST['section_in_charge']);

        $wpdb->update(
            $table,
            ['section_in_charge' => $in_charge],
            ['id' => $section_id]
        );
    }

    // Fetch sections
    $sections = $wpdb->get_results("SELECT * FROM $table");

    // Fetch users
    $users = get_users([
        'orderby' => 'display_name',
        'order'   => 'ASC'
    ]);
    ?>

    <div class="wrap">
        <h1>Sections</h1>

        <!-- Add Section -->
        <form method="post" style="margin-bottom:15px;">
            <input type="text" name="section_name" required placeholder="Section Name">
            <button class="button button-primary" name="add_section">Add Section</button>
        </form>

        <hr>

        <!-- Section List -->
        <table class="widefat striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Section Name</th>
                    <th>Section In-Charge</th>
                    <th width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $s): ?>
                    <tr>
                        <td><?php echo intval($s->id); ?></td>
                        <td><?php echo esc_html($s->section_name); ?></td>

                        <td>
                            <form method="post" style="display:flex; gap:6px;">
                                <input type="hidden" name="section_id" value="<?php echo intval($s->id); ?>">

                                <select name="section_in_charge" required>
                                    <option value="">— Select User —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo esc_attr($u->display_name); ?>"
                                            <?php selected($s->section_in_charge, $u->display_name); ?>>
                                            <?php echo esc_html($u->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                        </td>

                        <td>
                                <button class="button button-primary" name="assign_incharge">
                                    Assign
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
}
