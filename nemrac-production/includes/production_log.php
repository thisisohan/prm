<?php
if (!defined('ABSPATH')) exit;

/**
 * Render the Production Log Page in Admin Dashboard
 */
function production_log_page() {

    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    global $wpdb;

    $log_table   = "{$wpdb->prefix}npm_production_log";
    $lines_table = "{$wpdb->prefix}npm_lines";
    $entry_table = "{$wpdb->prefix}npm_production_entry";

    /* ===============================
     * Pagination setup
     * =============================== */
    $per_page = 20; // logs per page
    $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset   = ($paged - 1) * $per_page;

    // Total rows
    $total_logs = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
    $total_pages = ceil($total_logs / $per_page);

    /* ===============================
     * Fetch logs with LIMIT & OFFSET
     * =============================== */
    $logs = $wpdb->get_results($wpdb->prepare("
        SELECT l.*, ln.line_name
        FROM $log_table l
        LEFT JOIN $entry_table e ON l.record_id = e.id
        LEFT JOIN $lines_table ln ON e.line_id = ln.id
        ORDER BY l.change_date DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));

    echo '<div class="wrap">';
    echo '<h1><span class="dashicons dashicons-media-text" style="margin-top:5px;"></span> Production Change History</h1>';
    echo '<p>Monitoring all additions and modifications to production records.</p>';

    echo '<table class="wp-list-table widefat fixed striped" style="margin-top:20px;">';
    echo '<thead>
        <tr>
            <th style="width:15%;">Log Timestamp</th>
            <th style="width:12%;">User</th>
            <th style="width:12%;">Section</th>
            <th style="width:12%;">Report Date</th>
            <th style="width:10%;">Line</th>
            <th style="width:8%;">Action</th>
            <th>Changes (Old → New)</th>
        </tr>
    </thead>';
    echo '<tbody>';

    if ($logs) {
        foreach ($logs as $log) {

            $user = get_userdata($log->user_id);
            $old_arr = json_decode($log->old_data, true);
            $new_arr = json_decode($log->new_data, true);

            $action_style = ($log->action === 'INSERT')
                ? 'background:#d1e7dd;color:#0f5132;'
                : 'background:#fff3cd;color:#856404;';

            echo '<tr>';
            echo '<td>' . esc_html(date('d M Y, h:i A', strtotime($log->change_date))) . '</td>';
            echo '<td><strong>' . esc_html($user ? $user->display_name : 'Unknown') . '</strong></td>';
            echo '<td>' . esc_html($log->section_name ?: 'N/A') . '</td>';
            echo '<td><code style="font-weight:bold;color:#2271b1;">' . esc_html(date('d M Y', strtotime($log->production_date))) . '</code></td>';
            echo '<td>' . esc_html($log->line_name ?: 'Collective') . '</td>';
            echo '<td><span style="' . $action_style . ' padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold;">' . esc_html($log->action) . '</span></td>';

            echo '<td>';
            if ($log->action === 'INSERT') {
                echo '<span style="color:green;">New entry created for this line.</span>';
            } else {

                $fields = [
                    'target_quantity'   => 'Target',
                    'produced_quantity' => 'Produced',
                    'reject_quantity'   => 'Reject',
                    'other_reason_name' => 'Reason',
                    'other_reason_quantity' => 'Reason Qty',
                    'remarks'           => 'Remarks'
                ];

                $diff_found = false;

                foreach ($fields as $key => $label) {
                    $old = $old_arr[$key] ?? '';
                    $new = $new_arr[$key] ?? '';

                    if ($old != $new) {
                        $diff_found = true;
                        echo '<div style="font-size:12px;margin-bottom:4px;">';
                        echo '<strong>' . esc_html($label) . ':</strong> ';
                        echo '<span style="color:#b32d2e;text-decoration:line-through;">' . esc_html($old) . '</span>';
                        echo ' ➔ ';
                        echo '<span style="color:#2c5e2e;font-weight:bold;">' . esc_html($new) . '</span>';
                        echo '</div>';
                    }
                }

                if (!$diff_found) {
                    echo '<span class="description">Metadata update (No quantity changes).</span>';
                }
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center;padding:20px;">No history logs found.</td></tr>';
    }

    echo '</tbody></table>';

    /* ===============================
     * Pagination links
     * =============================== */
    if ($total_pages > 1) {
        echo '<div class="tablenav log-page-pagination"><div class="tablenav-pages" style="margin-top:15px;">';
        echo paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => '« Previous',
            'next_text' => 'Next »',
        ]);
        echo '</div></div>';
    }
    echo '
    <style>
        .log-page-pagination a {
            padding: 4px 9px;
            border: 1px solid #ccd0d4;
            border-radius: 7px;
            text-decoration: none;
            margin: 0 2px;
        }

        .log-page-pagination .current {
            padding: 4px 9px;
            border: 1px solid #2271b1;
            background: #2271b1;
            color: #fff;
            border-radius: 7px;
            font-weight: bold;
        }
    </style>
    ';
    echo '</div>';
}