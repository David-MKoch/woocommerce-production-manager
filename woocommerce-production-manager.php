<?php
/*
Plugin Name: WooCommerce Production Manager
Description: A WooCommerce plugin to manage production capacity, delivery dates, and order item statuses.
Version: 1.0.0
Author: neodesign
Text Domain: woocommerce-production-manager
Domain Path: /languages
Requires PHP: 7.4
Requires at least: 5.8
Tested up to: 6.4
WC requires at least: 7.0
WC tested up to: 8.0
License: GPL-2.0+
*/

defined('ABSPATH') || exit;

// Define constants
define('WPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . esc_html__('WooCommerce Production Manager requires WooCommerce to be installed and active.', 'woocommerce-production-manager') . '</p></div>';
    });
    return;
}

// Include Composer autoloader
if (file_exists(WPM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WPM_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include bootstrap
require_once WPM_PLUGIN_DIR . 'includes/bootstrap.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    \WPM\Includes\Bootstrap::init();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    \WPM\Includes\Bootstrap::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    \WPM\Includes\Bootstrap::deactivate();
});
?>