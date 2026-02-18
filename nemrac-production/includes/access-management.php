<?php
if (!defined('ABSPATH')) exit;

function npm_access_management_page() {
    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    global $wpdb;

    $section_table = $wpdb->prefix . 'npm_sections';
    $line_table    = $wpdb->prefix . 'npm_lines';
    $access_table  = $wpdb->prefix . 'npm_access';

    /* ================= LOAD SECTIONS & LINES ================= */
    $sections = $wpdb->get_results("SELECT * FROM $section_table");
    $lines    = $wpdb->get_results("SELECT * FROM $line_table");

    $lines_by_section = [];
    foreach ($lines as $l) {
        $lines_by_section[$l->section_id][] = $l;
    }

    /* ================= SECTION IN-CHARGE MAP ================= */
    $section_incharge_map = [];
    foreach ($sections as $s) {
        if (!empty($s->section_in_charge)) {
            $key = strtolower(trim($s->section_in_charge));
            $section_incharge_map[$key][] = $s->section_name;
        }
    }

    /* ================= LOAD USERS ================= */
    $users = get_users(['role__not_in' => ['administrator']]);

    /* ================= SAVE ACCESS ================= */
    if (isset($_POST['save_access'])) {

        foreach ($users as $u) {
            $user_id = (int) $u->ID;
            $posted_sections = $_POST['access'][$user_id] ?? [];

            $wpdb->delete($access_table, ['user_id' => $user_id]);

            foreach ($sections as $s) {
                $section_id = (int) $s->id;

                if (!isset($posted_sections[$section_id])) continue;

                $data = $posted_sections[$section_id];
                $can_change_past = isset($data['change_past']) ? 1 : 0;

                if (isset($data['_section'])) {
                    $wpdb->insert($access_table, [
                        'user_id'         => $user_id,
                        'section_id'      => $section_id,
                        'line_id'         => null,
                        'can_change_past' => $can_change_past
                    ]);
                }

                if (!empty($data['lines'])) {
                    foreach ($data['lines'] as $line_id) {
                        $wpdb->insert($access_table, [
                            'user_id'         => $user_id,
                            'section_id'      => $section_id,
                            'line_id'         => (int) $line_id,
                            'can_change_past' => $can_change_past
                        ]);
                    }
                }
            }
        }

        echo '<div class="updated notice"><p>Access updated successfully.</p></div>';
    }

    /* ================= LOAD ACCESS ================= */
    $raw_access = $wpdb->get_results("SELECT * FROM $access_table");

    $access_map = [];
    foreach ($raw_access as $a) {
        if ($a->can_change_past == 1) {
            $access_map[$a->user_id][$a->section_id]['change_past'] = true;
        }

        if ($a->line_id === null) {
            $access_map[$a->user_id][$a->section_id]['_section'] = true;
        } else {
            $access_map[$a->user_id][$a->section_id]['lines'][] = $a->line_id;
        }
    }
    ?>

    <div class="wrap">
        <h1>Access Management</h1>

        <form method="post">
            <table class="widefat fixed striped npm-access-table">
                <thead>
                    <tr>
                        <th width="20%">User</th>
                        <th width="25%">Role / In-Charge</th>
                        <th>Access</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($users as $u):
                    $user_access = $access_map[$u->ID] ?? [];

                    $wp_roles  = wp_roles();
                    $role_key  = $u->roles[0] ?? '';
                    $role_name = $wp_roles->roles[$role_key]['name'] ?? $role_key;

                    $user_key = strtolower(trim($u->display_name));
                ?>
                    <tr>
                        <td><strong><?= esc_html($u->display_name); ?></strong></td>

                        <td>
                            <strong><?= esc_html($role_name); ?></strong>

                            <?php if (!empty($section_incharge_map[$user_key])): ?>
                                <div style="font-size:12px;color:#2271b1;margin-top:4px;">
                                    <em>In-Charge:</em>
                                    <?= esc_html(implode(', ', $section_incharge_map[$user_key])); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- TOGGLE + HIDDEN CONTENT -->
                        <td>
                            <div class="npm-access-toggle" style="cursor:pointer;color:#2271b1;font-weight:600; height: 42px; display: flex;align-items: center;">
                                ▶ View Access
                            </div>

                            <div class="npm-access-content" style="display:none;margin-top:10px;">

                                <?php foreach ($sections as $s):
                                    $section_access  = $user_access[$s->id] ?? [];
                                    $section_checked = !empty($section_access['_section']);
                                    $past_checked    = !empty($section_access['change_past']);
                                    $line_access     = $section_access['lines'] ?? [];
                                ?>
                                    <div style="margin-bottom:15px;border:1px solid #ddd;padding:10px;">
                                        <div style="display:flex;gap:20px;align-items:center;border-bottom:1px solid #eee;padding-bottom:5px;">
                                            <label>
                                                <input type="checkbox"
                                                    name="access[<?= $u->ID ?>][<?= $s->id ?>][_section]"
                                                    <?= $section_checked ? 'checked' : '' ?>>
                                                <strong><?= esc_html($s->section_name); ?></strong>
                                            </label>

                                            <label style="color:#d63638;">
                                                <input type="checkbox"
                                                    name="access[<?= $u->ID ?>][<?= $s->id ?>][change_past]"
                                                    <?= $past_checked ? 'checked' : '' ?>>
                                                Change Past
                                            </label>
                                        </div>

                                        <div style="padding-top:8px;padding-left:20px;">
                                            <?php if (!empty($lines_by_section[$s->id])): ?>
                                                <?php foreach ($lines_by_section[$s->id] as $l): ?>
                                                    <label style="margin-right:15px;">
                                                        <input type="checkbox"
                                                            name="access[<?= $u->ID ?>][<?= $s->id ?>][lines][]"
                                                            value="<?= $l->id ?>"
                                                            <?= in_array($l->id, $line_access) ? 'checked' : '' ?>>
                                                        <?= esc_html($l->line_name); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <em>No lines</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

            <p>
                <button type="submit" name="save_access" class="button button-primary">
                    Save Access
                </button>
            </p>
        </form>
    </div>

    <!-- TOGGLE SCRIPT -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.npm-access-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const content = this.nextElementSibling;
                const open = content.style.display === 'block';

                content.style.display = open ? 'none' : 'block';
                this.innerHTML = open ? '▶ View Access' : '▼ Hide Access';
            });
        });
    });
    </script>

<?php
}
