<?php
if (!defined('ABSPATH')) exit;


function npm_dashboard_page() {
    if (!current_user_can('npm_manage')) {
        wp_die(__('You do not have permission to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1>Nemrac Production Manager</h1>

        <p>Welcome to <strong>Nemrac Production Manager</strong> — your central hub for managing production operations efficiently and securely. This plugin is developed to help Nemrac Design Ltd streamline daily production reporting, section and line management, and role-based access control.</p>

        <h2>What You Can Do:</h2>
        <ul style="font-size:16px; line-height:1.6;">
            <li>📌 <strong>Manage Sections:</strong> Add, edit, and organize all production sections in your facility.</li>
            <li>📌 <strong>Manage Lines:</strong> Define production lines under each section to streamline reporting and tracking.</li>
            <li>📌 <strong>Admin & Role Management:</strong> Assign roles, manage permissions, and control who can access which sections and lines.</li>
            <li>📌 <strong>Access Management:</strong> Fine-tune access for each user to ensure data security and accountability.</li>
            <li>📌 <strong>Production Log:</strong> View, track, and update daily production entries for every line and section.</li>
        </ul>

        <p>Use the menu on the left to navigate between modules. This plugin is designed to help your team:</p>
        <ul style="font-size:16px; line-height:1.6;">
            <li>✅ Improve production tracking accuracy</li>
            <li>✅ Simplify reporting and record keeping</li>
            <li>✅ Maintain secure role-based access</li>
            <li>✅ Streamline daily operational management</li>
        </ul>

        <h2>About the Company & Creator</h2>
        <p>This plugin is developed by <strong>Md. Sohanur Rahaman Khan</strong> for <strong>Nemrac Design Ltd.</strong>, based in Narayanganj, Bangladesh. It is designed with the goal of helping production teams work smarter, safer, and more efficiently. Your feedback and suggestions are always welcome!</p>

        <p>Thank you for using <strong>Nemrac Production Manager</strong> — making your production operations organized, efficient, and transparent.</p>
    </div>


    <?php
}
