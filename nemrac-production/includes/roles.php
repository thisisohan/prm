<?php
if (!defined('ABSPATH')) exit;



function npm_register_roles() {

    add_role('line_chief', 'Line Chief', [
        'read' => true,
    ]);

    add_role('pm', 'PM', [
        'read' => true,
    ]);

    add_role('section_incharge', 'Section Incharge', [
        'read' => true,
    ]);

    add_role('reporter', 'Reporter', [
        'read' => true,
    ]);

    add_role('controller', 'Controller', [
        'read' => true,
    ]);
}
add_action('init', 'npm_register_roles');
