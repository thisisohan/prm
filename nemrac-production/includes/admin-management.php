<?php
if (!defined('ABSPATH')) exit;


function npm_admin_management_page() {
    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $roles = [
        'controller' => 'Controller',
        'line_chief' => 'Line Chief',
        'pm' => 'PM',
        'section_incharge' => 'Section Incharge',
        'reporter' => 'Reporter',
        'administrator' => 'Administrator'
    ];

    if (isset($_POST['assign_role'])) {
        $user = get_user_by('id', $_POST['user_id']);
        if ($user) {
            $user->set_role($_POST['role']);
        }
    }

    $users = get_users([
        'role__not_in' => ['administrator']
    ]);

    ?>
    <div class="wrap">
        <h1>Admin Management</h1>

        <table class="widefat">
            <tr>
                <th>User</th>
                <th>Current Role</th>
                <th>Assign Role</th>
            </tr>

            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= esc_html($u->display_name) ?></td>
                <td><?= implode(', ', $u->roles) ?></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="user_id" value="<?= $u->ID ?>">
                        <select name="role">
                            <?php foreach ($roles as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" name="assign_role">Assign</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}
