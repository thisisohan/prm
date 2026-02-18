<?php
/**
 * Template Name: Production Report Entry
 * Description: Production report data entry page by Sohan
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Table Names
$table_name = "{$wpdb->prefix}npm_production_entry";
$log_table  = "{$wpdb->prefix}npm_production_log";

// Get current user 
$current_user = wp_get_current_user(); 
$user_id = get_current_user_id();
$today_str = date('Y-m-d');

$message = '';
if (isset($_GET['success'])) {
    $count = intval($_GET['success']);
    $message = '<div class="success-msg-container" style="color:green; padding:10px; background:#e7f7ed; border:1px solid #7ad03a; margin-bottom:15px;">
                    Successfully Updated ' . $count . ' record(s)!
                </div>';
}

$ajax_nonce = wp_create_nonce('npm_production_ajax_nonce');

/* -------------------------------------------------------------------------
   FETCH PERMISSIONS & SECTIONS
------------------------------------------------------------------------- */
$can_change_past = $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(can_change_past) FROM {$wpdb->prefix}npm_access WHERE user_id = %d",
    $user_id
));

$assigned_sections = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT s.id, s.section_name 
     FROM {$wpdb->prefix}npm_sections s
     JOIN {$wpdb->prefix}npm_access a ON s.id = a.section_id
     WHERE a.user_id = %d
     ORDER BY s.section_name ASC",
    $user_id
));

// Header Info Query
$access_query = $wpdb->get_results($wpdb->prepare(
    "SELECT s.section_name, l.line_name 
     FROM {$wpdb->prefix}npm_access a
     INNER JOIN {$wpdb->prefix}npm_sections s ON a.section_id = s.id
     LEFT JOIN {$wpdb->prefix}npm_lines l ON a.line_id = l.id
     WHERE a.user_id = %d
     ORDER BY s.section_name ASC, l.line_name ASC",
    $user_id
));

$user_permissions = [];
if (!empty($access_query)) {
    foreach ($access_query as $row) {
        $section = $row->section_name;
        $line = $row->line_name ?: 'All Lines';
        $user_permissions[$section][] = $line;
    }
}

/* -------------------------------------------------------------------------
   1. HANDLE FORM SUBMISSION
------------------------------------------------------------------------- */
if (isset($_POST['prm_submit'])) {
    $production_date = sanitize_text_field($_POST['production_date'] ?? '');

    if ($production_date < $today_str && !$can_change_past) {
        wp_die('You do not have permission to edit past records.');
    } else if (!isset($_POST['nemrac_prm_nonce']) || !wp_verify_nonce($_POST['nemrac_prm_nonce'], 'nemrac_prm_entry')) {
        $message = '<p style="color:red;">Security check failed.</p>';
    } else {
        $section_id = intval($_POST['section_id'] ?? 0);
        $line_ids = $_POST['line_id'] ?? [];

        if (!$production_date || !$section_id || empty($line_ids)) {
            $message = '<p style="color:red;">Please select a date, section, and fill at least one line.</p>';
        } else {
            // Get Section Name for Logging
            $section_name = $wpdb->get_var($wpdb->prepare(
                "SELECT section_name FROM {$wpdb->prefix}npm_sections WHERE id = %d", 
                $section_id
            ));

            $success_count = 0;
            foreach ($line_ids as $i => $line_id) {
                $line_id = intval($line_id);
                if ($section_id === 0) continue; 

                $has_permission = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}npm_access 
                     WHERE user_id = %d AND section_id = %d AND (line_id = %d OR line_id IS NULL)",
                    $user_id, $section_id, $line_id
                ));

                if (!$has_permission) continue;

                $data = [
                    'section_id'            => $section_id,
                    'line_id'               => $line_id,
                    'production_title'     => sanitize_text_field($_POST['production_title'][$i] ?? ''),
                    'target_quantity'       => intval($_POST['target_quantity'][$i] ?? 0),
                    'produced_quantity'     => intval($_POST['produced_quantity'][$i] ?? 0),
                    'reject_quantity'       => intval($_POST['reject_quantity'][$i] ?? 0),
                    'other_reason_name'     => sanitize_text_field($_POST['other_reason_name'][$i] ?? ''),
                    'other_reason_quantity' => intval($_POST['other_reason_quantity'][$i] ?? 0),
                    'production_date'       => $production_date,
                    'last_update_date'      => current_time('mysql'),
                    'updated_by'            => $user_id,
                    'remarks'               => sanitize_text_field($_POST['remarks'][$i] ?? ''),
                ];

                $formats = [ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s' ];

                $existing_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE section_id = %d AND line_id = %d AND production_date = %s",
                    $section_id, $line_id, $production_date
                ), ARRAY_A);

                if ($existing_row) {
                    $wpdb->update($table_name, $data, ['id' => $existing_row['id']], $formats, ['%d']);
                    
                    // Log Update
                    $wpdb->insert($log_table, [
                        'record_id'       => $existing_row['id'],
                        'user_id'         => $user_id,
                        'section_name'    => $section_name,
                        'production_date' => $production_date,
                        'action'          => 'UPDATE',
                        'old_data'        => json_encode($existing_row),
                        'new_data'        => json_encode($data),
                        'change_date'     => current_time('mysql')
                    ]);
                    $success_count++;
                } else {
                    $wpdb->insert($table_name, $data, $formats);
                    $new_id = $wpdb->insert_id;

                    // Log Insert
                    $wpdb->insert($log_table, [
                        'record_id'       => $new_id,
                        'user_id'         => $user_id,
                        'section_name'    => $section_name,
                        'production_date' => $production_date,
                        'action'          => 'INSERT',
                        'old_data'        => null,
                        'new_data'        => json_encode($data),
                        'change_date'     => current_time('mysql')
                    ]);
                    $success_count++;
                }
            }

            if ($success_count > 0) {
                wp_redirect(add_query_arg('success', $success_count, get_permalink()));
                exit;
            }
        }
    }
}

get_header();
?>

<div class="nemrac-prm-entry">
    <div class="prm-meta"> 
        <?php echo $message; ?>
        <div class="prm-card">
            <div class="prm-header">
                <div class="prm-title">
                    <h2>Production Report Entry</h2>
                </div>
            </div>
            <div class="user-description-area" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="user-description">
                    
                    <table>
                        <tbody>
                            <th  colspan="3"><span>User Details</span></th>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-user"></i>
                                </td>
                                <td>
                                    User Name:
                                </td>
                                <td>
                                    <?php echo esc_html($current_user->display_name); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-list-check"></i>
                                </td>
                                <td>
                                    Section:
                                </td>
                                <td>
                                    <?php foreach ($user_permissions as $section => $lines): ?>
                                        <?php echo esc_html($section); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-bars fa-rotate-90"></i>
                                </td>
                                <td>
                                    Line(s):
                                </td>
                                <td>
                                    <?php echo esc_html(implode(', ', $lines)); ?>
                                    <?php break; endforeach; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="today-dt">
                    <span>Today:</span>
                    <div id="today-date"><?php echo esc_html(wp_date('d F Y')); ?></div>
                    <div id="digital-clock"></div>
                </div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field('nemrac_prm_entry', 'nemrac_prm_nonce'); ?>
            <div class="prm-card">
                <div class="prm-filter" style="display: flex; gap: 20px;">
                    <div class="prm-filter-date">
                        <span>📅 Production Date</span>
                        <input type="date" id="production_date" name="production_date" value="<?php echo $today_str; ?>" required>
                    </div>
                    <div class="prm-filter-section-dropdown">
                        <span>🏭 Section</span>
                        <select id="section_id" name="section_id" required>
                            <option value="">Select Section</option>
                            <?php foreach ($assigned_sections as $sec): ?>
                                <option value="<?php echo esc_attr($sec->id); ?>"><?php echo esc_html($sec->section_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div id="lines-container"></div>
            <div class="submit-area">
                <button type="submit" name="prm_submit" id="prm-submit-btn">Select Options First</button>
            </div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function ($) {
    const todayStr = '<?php echo $today_str; ?>';
    const canChangePast = <?php echo $can_change_past ? 'true' : 'false'; ?>;

    function renderLineBlock(entry) {
        const selectedDate = $('#production_date').val();
        const shouldDisable = (selectedDate < todayStr && !canChangePast);

        const lineIdValue = entry ? (entry.line_id || entry.id) : '0';
        const lineTitle = entry && entry.line_name ? `${entry.line_name}` : 'Manual Entry';
        
        const productionTitle = (entry && entry.production_title) || '';
        const targetQty = (entry && entry.target_quantity) || '';
        const producedQty = (entry && entry.produced_quantity) || '';
        const rejectQty = (entry && entry.reject_quantity) || '';
        const otherReasonName = (entry && entry.other_reason_name) || '';
        const otherReasonQty = (entry && entry.other_reason_quantity) || '';
        const remarks = (entry && entry.remarks) || '';

        const block = `
        <div class="line-block" style="${shouldDisable ? 'opacity: 0.7; background: #f9f9f9;' : ''}">
            <h4>⛓ ${lineTitle} ${shouldDisable ? '<span style="color:red; font-size:12px;">(Read Only)</span>' : ''}</h4>
            <input type="hidden" name="line_id[]" value="${lineIdValue}">
            
            <div style="margin-bottom: 15px;">
                <label>🖋 Production Title</label>
                <input type="text" 
                       name="production_title[]" 
                       value="${productionTitle}" 
                       ${shouldDisable ? 'readonly' : ''} 
                       style="width: 100%; background: #fff9de; padding: 8px 11px;">
            </div>

            <div class="line-grid">
                <div><label>🎯 Target</label><input type="number" name="target_quantity[]" value="${targetQty}" ${shouldDisable ? 'readonly' : ''}></div>
                <div><label>✅ Produced</label><input type="number" name="produced_quantity[]" value="${producedQty}" ${shouldDisable ? 'readonly' : ''}></div>
                <div><label>❌ Reject</label><input type="number" name="reject_quantity[]" value="${rejectQty}" ${shouldDisable ? 'readonly' : ''}></div>
                <div><label>📌 Other Reason</label><input type="text" name="other_reason_name[]" value="${otherReasonName}" ${shouldDisable ? 'readonly' : ''}></div>
                <div><label>🔢 Qty</label><input type="number" name="other_reason_quantity[]" value="${otherReasonQty}" ${shouldDisable ? 'readonly' : ''}></div>
                <div><label>📝 Remarks</label><input type="text" name="remarks[]" value="${remarks}" ${shouldDisable ? 'readonly' : ''}></div>
            </div>
        </div>`;
        
        $('#lines-container').append(block);
        if (shouldDisable) { $('#prm-submit-btn').hide(); } else { $('#prm-submit-btn').show(); }
    }

    function loadEntries() {
        const sID = $('#section_id').val();
        const pDate = $('#production_date').val();
        if (!sID || !pDate) { $('#lines-container').html('<p>Please select options.</p>'); return; }

        $('#lines-container').html('<p>Loading...</p>');
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'npm_get_production_entry',
            section_id: sID,
            production_date: pDate,
            security: '<?php echo $ajax_nonce; ?>'
        }, function(res) {
            $('#lines-container').empty();
            if (!res || res.length === 0) {
                renderLineBlock(null);
                $('#prm-submit-btn').text('Submit Report');
            } else {
                res.forEach(e => renderLineBlock(e));
                const exists = res.some(e => e.entry_id);
                $('#prm-submit-btn').text(exists ? '♻ Update Production Report' : '✔ Submit Production Report');
            }
        }, 'json');
    }

    $('#section_id, #production_date').on('change', loadEntries);

    if (window.history.replaceState && window.location.href.indexOf('success=') > -1) {
        window.history.replaceState({}, '', window.location.pathname);
    }
    setTimeout(() => { $('.success-msg-container').fadeOut(); }, 5000);
});
</script>


<!-- Digital Clock -->
<script>
  function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    let minutes = now.getMinutes();
    let seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12 || 12;
    hours = hours.toString().padStart(2, '0');
    minutes = minutes.toString().padStart(2, '0');
    seconds = seconds.toString().padStart(2, '0');

    document.getElementById('digital-clock').textContent =
      hours + ':' + minutes + ':' + seconds + ' ' + ampm;
  }

  setInterval(updateClock, 1000);
  updateClock();
</script>




<?php get_footer(); ?>