<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {

    $capability = 'npm_manage';
    $menu_slug  = 'npm_dashboard';

    add_menu_page(
        'Nemrac Production',
        'Nemrac Production',
        $capability,
        $menu_slug,
        'npm_dashboard_page',
        'dashicons-clipboard',
        25
    );

    add_submenu_page(
        $menu_slug,
        'Dashboard',
        'Dashboard',
        $capability,
        $menu_slug,
        'npm_dashboard_page'
    );

    add_submenu_page(
        $menu_slug,
        'Section',
        'Section',
        $capability,
        'npm_section',
        'npm_section_page'
    );

    add_submenu_page(
        $menu_slug,
        'Line',
        'Line',
        $capability,
        'npm_line',
        'npm_line_page'
    );

    // add_submenu_page(
    //     $menu_slug,
    //     'Admin Management',
    //     'Admin Management',
    //     $capability,
    //     'npm_admin_management',
    //     'npm_admin_management_page'
    // );

    add_submenu_page(
        $menu_slug,
        'Access Management',
        'Access Management',
        $capability,
        'npm_access_management',
        'npm_access_management_page'
    );

    add_submenu_page(
        $menu_slug,
        'Production Log',
        'Production Log',
        $capability,
        'npm_production_log',
        'production_log_page'
    );

});
