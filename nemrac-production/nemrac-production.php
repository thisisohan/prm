<?php
/**
 * Plugin Name: Nemrac Production Manager
 * Description: Section, Line & Admin Role Management for Nemrac.
 * Version: 1.0.0
 * Author: Md. Sohanur Rahaman Khan Nemrac IT and Payroll officer.
 */

if (!defined('ABSPATH')) exit;

define('NPM_PATH', plugin_dir_path(__FILE__));
define('NPM_URL', plugin_dir_url(__FILE__));

require_once NPM_PATH . 'includes/menu.php';
require_once NPM_PATH . 'includes/dashboard.php';
require_once NPM_PATH . 'includes/section.php';
require_once NPM_PATH . 'includes/line.php';
// require_once NPM_PATH . 'includes/admin-management.php';
require_once NPM_PATH . 'includes/roles.php';
require_once NPM_PATH . 'includes/access-management.php';
require_once NPM_PATH . 'includes/production_log.php';
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'npm_access_management') !== false) {
        wp_enqueue_style(
            'npm-admin-css',
            NPM_URL . 'assets/admin.css',
            [],
            '1.0'
        );
    }
});


register_activation_hook(__FILE__, function () {

    $roles = ['administrator', 'controller'];

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('npm_manage');
        }
    }
});

add_action('admin_menu', function () {

    // Only apply for Controller
    if (!current_user_can('controller') || current_user_can('administrator')) {
        return;
    }

    // Remove Dashboard
    remove_menu_page('index.php');

    // Remove Profile
    remove_menu_page('profile.php');

}, 999);










add_action('wp_ajax_get_lines_by_section', 'npm_get_lines_by_section');
add_action('wp_ajax_nopriv_get_lines_by_section', 'npm_get_lines_by_section');

function npm_get_lines_by_section() {
    global $wpdb;

    $section_id = intval($_POST['section_id']);

    $lines = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, line_name 
             FROM {$wpdb->prefix}npm_lines 
             WHERE section_id = %d 
             ORDER BY line_name ASC",
            $section_id
        )
    );

    wp_send_json($lines);
}




add_action('wp_ajax_npm_get_production_entry', 'npm_get_production_entry');
add_action('wp_ajax_nopriv_npm_get_production_entry', 'npm_get_production_entry');

function npm_get_production_entry() {
    check_ajax_referer('npm_production_ajax_nonce', 'security');
    global $wpdb;

    $current_user_id = get_current_user_id();
    $section_id = intval($_POST['section_id'] ?? 0);
    $production_date = sanitize_text_field($_POST['production_date'] ?? '');

    if (!$section_id || !$production_date) wp_send_json([]);

    // Verify Access
    $access = $wpdb->get_results($wpdb->prepare(
        "SELECT line_id FROM {$wpdb->prefix}npm_access 
         WHERE user_id = %d AND section_id = %d",
        $current_user_id, $section_id
    ));

    if (empty($access)) {
        wp_send_json(['error' => 'No access to this section']);
    }

    $allowed_lines = [];
    $full_section_access = false;
    foreach ($access as $row) {
        if (is_null($row->line_id)) { $full_section_access = true; break; }
        $allowed_lines[] = intval($row->line_id);
    }

    // Check if this section actually has lines defined
    $has_lines = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}npm_lines WHERE section_id = %d", $section_id));

    if ($has_lines > 0) {
        // Query based on Lines
        $query = "SELECT 
                    l.id as line_id, 
                    l.line_name, 
                    e.id as entry_id,
                    e.production_title,
                    COALESCE(e.target_quantity, 0) as target_quantity, 
                    COALESCE(e.produced_quantity, 0) as produced_quantity,
                    COALESCE(e.reject_quantity, 0) as reject_quantity,
                    e.other_reason_name,
                    COALESCE(e.other_reason_quantity, 0) as other_reason_quantity,
                    e.remarks
                  FROM {$wpdb->prefix}npm_lines l
                  LEFT JOIN {$wpdb->prefix}npm_production_entry e 
                    ON l.id = e.line_id AND e.production_date = %s
                  WHERE l.section_id = %d";

        if (!$full_section_access) {
            $query .= " AND l.id IN (" . implode(',', array_map('intval', $allowed_lines)) . ")";
        }
        $query .= " ORDER BY l.line_name ASC";
        $results = $wpdb->get_results($wpdb->prepare($query, $production_date, $section_id));
    } else {
        // 3. FALLBACK: Section has no lines. Look for a record with line_id = 0
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                0 as line_id, 
                'General Entry' as line_name, 
                id as entry_id,
                production_title, target_quantity, produced_quantity, reject_quantity,
                other_reason_name, other_reason_quantity, remarks
             FROM {$wpdb->prefix}npm_production_entry 
             WHERE section_id = %d AND (line_id = 0 OR line_id IS NULL) AND production_date = %s",
            $section_id, $production_date
        ));

        if (empty($results)) {
            $results = [(object)[
                'line_id' => 0,
                'line_name' => 'General Production',
                'entry_id' => null,
                'production_title' => '',
                'target_quantity' => 0,
                'produced_quantity' => 0,
                'reject_quantity' => 0,
                'other_reason_name' => '',
                'other_reason_quantity' => 0,
                'remarks' => ''
            ]];
        }
    }

    wp_send_json($results);
}