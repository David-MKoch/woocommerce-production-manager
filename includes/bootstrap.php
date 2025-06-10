<?php
namespace WPM\Includes;

defined('ABSPATH') || exit;

class Bootstrap {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
		// Delay initialization until all plugins are fully loaded
        add_action('plugins_loaded', [$this, 'init'], 9999);
    }
	
	public function init() {
		add_action('init', [$this, 'load_textdomain']);

        if ($this->check_requirements()) {
            $this->load_classes();
            // Check for updates on plugin load
            add_action('init', [$this, 'check_for_updates']);
			// Schedule cron job for cleanup
			if (!wp_next_scheduled('wpm_cleanup_old_logs')) {
				wp_schedule_event(time(), 'daily', 'wpm_cleanup_old_logs');
			}
			add_action('wpm_cleanup_old_logs', [__CLASS__, 'cleanup_old_logs']);
        } else {
            add_action('admin_notices', [$this, 'show_requirements_notice']);
        }
	}

    public function load_textdomain() {
		$language_path = plugin_basename(dirname(plugin_dir_path(__FILE__))) . '/languages';
        load_plugin_textdomain('woocommerce-production-manager', false, $language_path);
	}

    private function check_requirements() {
        // Check if WooCommerce plugin is active
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', []));
        if (!in_array('woocommerce/woocommerce.php', $active_plugins)) {
            //error_log('WooCommerce Production Manager: WooCommerce plugin is not active in active_plugins list.');
            return false;
        }

        // Check if WooCommerce class exists
        if (!class_exists('WooCommerce')) {
            //error_log('WooCommerce Production Manager: WooCommerce class is not loaded. Possible loading order issue. Active plugins: ' . implode(', ', $active_plugins));
            return false;
        }

        // Get WooCommerce version
        $wc_version = defined('WC_VERSION') ? WC_VERSION : 'unknown';
        $required_version = '7.0';

        // Log the detected version for debugging
        //error_log('WooCommerce Production Manager: Detected WooCommerce version: ' . $wc_version);

        if ($wc_version === 'unknown' || version_compare($wc_version, $required_version, '<')) {
            //error_log('WooCommerce Production Manager: WooCommerce version ' . $wc_version . ' is less than required version ' . $required_version);
            return false;
        }

        return true;
    }

    public function show_requirements_notice() {
		$wc_version = defined('WC_VERSION') ? WC_VERSION : 'unknown';
        ?>
        <div class="notice notice-error">
            <p>
                <?php
				printf(
					esc_html__('WooCommerce Production Manager requires WooCommerce version %s or higher. Detected version: %s.', 'woocommerce-production-manager'),
					'7.0',
					esc_html($wc_version)
				);
                ?>
            </p>
        </div>
        <?php
    }

    private function load_classes() {
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
        //\WPM\API\Webhook::init();
    }

    public function create_db_tables() {
        global $wpdb;
		
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE {$wpdb->prefix}wpm_order_items_status (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(100) NOT NULL,
                delivery_date DATE NOT NULL,
                updated_by BIGINT UNSIGNED NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY order_item_id (order_item_id),
                INDEX status_idx (status, delivery_date)
            ) $charset_collate;",

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

            "CREATE TABLE {$wpdb->prefix}wpm_capacity_count (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                entity_type ENUM('product', 'variation') NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                reserved_count INT NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY date_entity_idx (date, entity_type, entity_id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}wpm_holidays (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                date DATE NOT NULL,
                description VARCHAR(255),
                PRIMARY KEY (id),
                UNIQUE KEY date_idx (date)
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

        // Check if tables already exist
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$table_prefix}order_items_status'") !== null;

        // Create or update tables
        foreach ($tables as $table) {
            dbDelta($table);
        }

        // Initialize default settings only if tables were just created
        if (!$tables_exist) {
            $this->initialize_default_settings();
        }
    }

    private function initialize_default_settings() {
		// Check if the plugin is already initialized
        if (get_option('wpm_initialized', false)) {
            return; // Skip initialization if already done
        }
        // Set default settings only for first-time installation
        $default_settings = [
            'wpm_default_delivery_days' => 3,
            'wpm_daily_cutoff_time' => '14:00',
            'wpm_allowed_order_statuses' => array_keys(wc_get_order_statuses()),
            'wpm_open_order_statuses' => array_keys(wc_get_order_statuses()),
            'wpm_weekly_holidays' => ['friday'],
            'wpm_statuses' => [
                ['name' => __('Received', 'woocommerce-production-manager'), 'color' => '#0073aa'],
                ['name' => __('In Production', 'woocommerce-production-manager'), 'color' => '#ff9900'],
                ['name' => __('Ready', 'woocommerce-production-manager'), 'color' => '#00cc00']
            ],
            'wpm_enable_sms_customers' => 0,
            'wpm_enable_sms_manager' => 0,
            'wpm_admin_phone_number' => '',
            'wpm_sms_api_key' => '',
            'wpm_sms_sender' => '',
            'wpm_sms_template' => __('Order #{order_id} status changed to {status}.', 'woocommerce-production-manager'),
            'wpm_delay_sms_template' => __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', 'woocommerce-production-manager'),
            'wpm_default_capacity' => 0
        ];

        foreach ($default_settings as $option_name => $value) {
            // Only set if the option doesn't exist
            if (get_option($option_name) === false) {
                update_option($option_name, $value);
            }
        }

        // Mark the plugin as initialized
        update_option('wpm_initialized', true);
    }

	public function check_for_updates() {
        $current_version = get_option('wpm_version', '0.0.0');
        if (version_compare($current_version, WPM_VERSION, '<')) {
            // Run table creation to apply any schema changes
            $this->create_db_tables();
            // Update version
            update_option('wpm_version', WPM_VERSION);
        }
    }
	
    public function activate() {
        if (!$this->check_requirements()) {
            wp_die(
                esc_html__('WooCommerce Production Manager requires WooCommerce 7.0.0 or higher.', 'woocommerce-production-manager'),
                esc_html__('Plugin Activation Error', 'woocommerce-production-manager'),
                ['response' => 200, 'back_link' => true]
            );
        }

        $this->create_db_tables();
    }

    public function deactivate() {
        // Clear cache
        \WPM\Utils\Cache::clear();
        // Flush rewrite rules
        //flush_rewrite_rules();
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

Bootstrap::get_instance();
?>