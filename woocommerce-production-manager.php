<?php
/*
Plugin Name: WooCommerce Production Manager
Description: A WooCommerce plugin to manage production capacity, delivery dates, and order item statuses.
Version: 1.1.0
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
define('WPM_VERSION', '1.1.0');


// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . sprintf(esc_html__('WooCommerce Production Manager requires WooCommerce to be installed and activated. Please <a href="%s">install or activate WooCommerce</a>.', 'woocommerce-production-manager'), admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '</p></div>';
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
    \WPM\Includes\Bootstrap::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    \WPM\Includes\Bootstrap::get_instance()->activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    \WPM\Includes\Bootstrap::get_instance()->deactivate();
});
?>