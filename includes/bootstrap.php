<?php
namespace WPM\Includes;

defined('ABSPATH') || exit;

class Bootstrap {
    public static function init() {
        // Load translations
        add_action('init', [__CLASS__, 'load_textdomain']);

        // Initialize modules
        \WPM\Settings\Settings::init();
		\WPM\Settings\SMS::init();
		\WPM\Settings\StatusManager::init();
		\WPM\Settings\Calendar::init();
		
        \WPM\Capacity\CapacityManager::init();
        \WPM\Capacity\CapacityCounter::init();
        \WPM\Delivery\DeliveryCalculator::init();
        \WPM\Delivery\DeliveryDisplay::init();
        
        \WPM\OrderItems\AdminPage::init();
        \WPM\CustomerUI\StatusTracker::init();
        
        \WPM\Reports\Logs::init();
		\WPM\Reports\Reports::init();
        //\WPM\API\RESTController::init();
        \WPM\API\Webhook::init();
    }

    public static function load_textdomain() {
        load_plugin_textdomain(WPM_TEXT_DOMAIN, false, dirname(plugin_basename(WPM_PLUGIN_DIR)) . '/languages');
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
                entity_type ENUM('category', 'product', 'variation') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                reserved_count INT NOT NULL,
                PRIMARY KEY (id),
                INDEX date_entity_idx (date, entity_type, entity_id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_holidays (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                type ENUM('weekly', 'custom') NOT NULL,
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
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Set default settings
        update_option('wpm_default_delivery_days', 3);
        update_option('wpm_statuses', [
            ['name' => __('Received', WPM_TEXT_DOMAIN), 'color' => '#0073aa']
        ]);

        // Generate default API key if not exists
        if (!get_option('wpm_api_key')) {
            update_option('wpm_api_key', wp_generate_password(32, false));
        }

        // Flush rewrite rules for endpoint
        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Clear cache
        \WPM\Utils\Cache::clear();
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
?>