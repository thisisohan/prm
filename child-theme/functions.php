<?php

add_action( 'wp_enqueue_scripts', 'tie_theme_child_styles_scripts', 80 );
function tie_theme_child_styles_scripts() {

    if ( is_rtl() ) {
        wp_enqueue_style( 'tie-theme-rtl-css', get_template_directory_uri().'/rtl.css', '' );
    }

    wp_enqueue_style( 'tie-theme-child-css', get_stylesheet_directory_uri().'/style.css', '' );

}





/**
 * Conditionally load CSS based on device type
 */
function enqueue_mobile_styles_footer() {
    if (wp_is_mobile()) {
        // Output the link tag directly in the footer
        echo '<link rel="stylesheet" href="' . get_stylesheet_directory_uri() . '/dashboard-mobile.css" type="text/css" />';
    }
}
add_action('wp_footer', 'enqueue_mobile_styles_footer');




/**
 * Trigger Login Popup when clicking a menu item with URL #@#
 */
function npm_trigger_login_popup_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Listen for clicks on the entire document
            document.addEventListener('click', function(e) {
                // Find the closest anchor tag to the click
                const menuLink = e.target.closest('a[href*="#@#"]');
                
                if (menuLink) {
                    e.preventDefault(); // Stop the page from jumping/reloading
                    
                    // Find the login button in the header
                    const loginBtn = document.querySelector('.lgoin-btn.tie-popup-trigger');
                    
                    if (loginBtn) {
                        loginBtn.click(); // Programmatically click the login trigger
                    } else {
                        console.warn('Login trigger button not found in the DOM.');
                    }
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'npm_trigger_login_popup_script');




add_action( 'template_redirect', 'restrict_content_to_logged_in_users' );

function restrict_content_to_logged_in_users() {
    // 1. Check if the user is NOT logged in
    // 2. Ensure we aren't blocking the login page itself (to avoid a redirect loop)
    if ( ! is_user_logged_in() && ! is_login() ) {
        
        // Output the header so the site branding remains visible
        get_header();

        ?>
        <div style="
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        ">
            <h2 style="margin-bottom:10px;">🔒 Login Required</h2>
            <p style="color:#555; margin-bottom:20px;">
                You must be logged in to view or use this application.
            </p>
        </div>
        <?php
        echo '  <a href="' . wp_login_url() . '" style="color: #0073aa; text-decoration: underline;">Click here to Login</a>';
        // Output the footer to close the HTML tags properly
        get_footer();
        exit;
    }
}




/**
 * Restrict wp-admin access by role
 */
add_action('admin_init', function () {

    // Allow AJAX & REST requests
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;

    // Must be logged in
    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();

    // Allowed roles
    $allowed_roles = ['administrator', 'editor', 'controller', 'author'];

    // Check role intersection
    if (!array_intersect($allowed_roles, (array) $user->roles)) {
        wp_redirect(home_url());
        exit;
    }
});
