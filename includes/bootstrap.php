<?php
namespace WPM\Includes;

defined('ABSPATH') || exit;

class Bootstrap {
    public static function init() {
        // Load translations
        add_action('init', [__CLASS__, 'load_textdomain']);

        // Initialize modules
        \WPM\Reports\AdminPage::init();
        \WPM\CustomerUI\StatusTracker::init();
		
        \WPM\Capacity\CapacityManager::init();
        \WPM\Capacity\CapacityCounter::init();
        \WPM\Delivery\DeliveryDisplay::init();

        \WPM\Settings\Settings::init();
        \WPM\Settings\Calendar::init();
		\WPM\Settings\StatusManager::init();
        \WPM\Settings\SMS::init();

        //\WPM\API\RESTController::init();
        \WPM\API\Webhook::init();
		
		// Schedule cron job for cleanup
        if (!wp_next_scheduled('wpm_cleanup_old_logs')) {
            wp_schedule_event(time(), 'daily', 'wpm_cleanup_old_logs');
        }
        add_action('wpm_cleanup_old_logs', [__CLASS__, 'cleanup_old_logs']);
    }

    public static function load_textdomain() {
        $language_path = plugin_basename(dirname(plugin_dir_path(__FILE__))) . '/languages';
        load_plugin_textdomain('woocommerce-production-manager', false, $language_path);
    }

    public static function activate() {
        global $wpdb;

        // Create database tables
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [
            "CREATE TABLE {$wpdb->prefix}wpm_production_capacity (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type ENUM('category', 'product', 'variation') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                max_capacity INT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX entity_idx (entity_type, entity_id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_order_items_status (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(100) NOT NULL,
                delivery_date DATE NOT NULL,
                updated_by BIGINT UNSIGNED NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX order_idx (order_id, order_item_id),
                INDEX status_idx (status, delivery_date)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_capacity_count (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                entity_type ENUM('product', 'variation') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                reserved_count INT NOT NULL,
                PRIMARY KEY (id),
                INDEX date_entity_idx (date, entity_type, entity_id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_holidays (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                description VARCHAR(255),
                PRIMARY KEY (id),
                INDEX date_idx (date)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_status_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_item_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(100) NOT NULL,
                changed_by BIGINT UNSIGNED NOT NULL,
                changed_at DATETIME NOT NULL,
                note TEXT,
                PRIMARY KEY (id),
                INDEX item_idx (order_item_id, changed_at)
            ) $charset_collate;",
			
			"CREATE TABLE {$wpdb->prefix}wpm_sms_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_item_id BIGINT UNSIGNED NOT NULL,
                phone VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('success', 'failed') NOT NULL,
                response TEXT,
                sent_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX sms_idx (order_item_id, sent_at)
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Set default settings
        update_option('wpm_default_delivery_days', 3);
        update_option('wpm_statuses', [
            ['name' => __('Received', 'woocommerce-production-manager'), 'color' => '#0073aa']
        ]);

        // Generate default API key if not exists
        /*if (!get_option('wpm_api_key')) {
            update_option('wpm_api_key', wp_generate_password(32, false));
        }*/

        // Flush rewrite rules for endpoint
        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Clear cache
        \WPM\Utils\Cache::clear();
        // Flush rewrite rules
        flush_rewrite_rules();
		// Clear scheduled cron
        wp_clear_scheduled_hook('wpm_cleanup_old_logs');
    }

    public static function cleanup_old_logs() {
        global $wpdb;
        $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wpm_sms_logs WHERE sent_at < %s",
            $six_months_ago
        ));
        /*$wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wpm_status_logs WHERE changed_at < %s",
            $six_months_ago
        ));*/
    }
}
?>