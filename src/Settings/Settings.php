<?php
namespace WPM\Settings;

use WPM\Settings\Calendar;
use WPM\Settings\SMS;
use WPM\Settings\StatusManager;
use WPM\Capacity\CapacityCounter;

defined('ABSPATH') || exit;

class Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
		add_action('wp_ajax_wpm_reset_production_capacity', [__CLASS__, 'reset_production_capacity']);
		add_action('wp_ajax_wpm_clear_cache', [__CLASS__, 'clear_cache']);
    }

    public static function add_menu_page() {
        add_submenu_page(
            'wpm-dashboard',
            __('Settings', WPM_TEXT_DOMAIN),
            __('Settings', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_scripts($hook) {
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook);
    }

    public static function render_page() {
        $tabs = [
            'general' => __('General', WPM_TEXT_DOMAIN),
            'holidays' => __('Holidays', WPM_TEXT_DOMAIN),
            'statuses' => __('Order Statuses', WPM_TEXT_DOMAIN),
            'sms' => __('SMS', WPM_TEXT_DOMAIN),
			'advanced' => __('Advanced', WPM_TEXT_DOMAIN),
        ];
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can('manage_woocommerce')) {
            self::save_settings($_POST);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Production Manager Settings', WPM_TEXT_DOMAIN); ?></h1>
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab => $name) : ?>
                    <a href="?page=wpm-settings&tab=<?php echo esc_attr($tab); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($name); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            switch ($current_tab) {
                case 'general':
                    self::render_general_tab();
                    break;
                case 'holidays':
                    Calendar::render_holidays_tab();
                    break;
                case 'sms':
                    SMS::render_sms_tab();
                    break;
                case 'statuses':
                    StatusManager::render_statuses_tab();
                    break;
				case 'advanced':
                    self::render_advanced_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }

    public static function render_general_tab() {
        $wc_statuses = wc_get_order_statuses();
        $selected_statuses = get_option('wpm_allowed_order_statuses', array_keys($wc_statuses));
        ?>
        <form method="post" id="wpm-settings-form">
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('General Settings', WPM_TEXT_DOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpm_default_capacity"><?php esc_html_e('Default Daily Production Capacity', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="number" name="wpm_default_capacity" id="wpm_default_capacity" value="<?php echo esc_attr(get_option('wpm_default_capacity', 1)); ?>" min="1">
                            <p><?php esc_html_e('Default maximum number of products that can be produced daily.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_default_delivery_days"><?php esc_html_e('Default Delivery Days', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="number" name="wpm_default_delivery_days" id="wpm_default_delivery_days" value="<?php echo esc_attr(get_option('wpm_default_delivery_days', 3)); ?>" min="1">
                            <p><?php esc_html_e('Number of days to add to order date for default delivery.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
					<tr>
                        <th><label for="wpm_default_max_delivery_days"><?php esc_html_e('Default Max Delivery Days', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="number" name="wpm_default_max_delivery_days" id="wpm_default_max_delivery_days" value="<?php echo esc_attr(get_option('wpm_default_max_delivery_days', 3)); ?>" min="1">
                            <p><?php esc_html_e('Max Number of days to add to order date for default delivery.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_daily_cutoff_time"><?php esc_html_e('Daily Cutoff Time', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="time" name="wpm_daily_cutoff_time" id="wpm_daily_cutoff_time" value="<?php echo esc_attr(get_option('wpm_daily_cutoff_time', '14:00')); ?>">
                            <p><?php esc_html_e('Daily cutoff time for processing orders.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Allowed Order Statuses', WPM_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php foreach ($wc_statuses as $status_key => $status_label) : ?>
                                <label>
                                    <input type="checkbox" name="wpm_allowed_order_statuses[]" value="<?php echo esc_attr($status_key); ?>" <?php checked(in_array($status_key, $selected_statuses)); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p><?php esc_html_e('Select WooCommerce order statuses to display in the order items table.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>
        </form>
        <?php
    }
	
	public static function render_advanced_tab() {
        $wc_statuses = wc_get_order_statuses();
        $selected_statuses = get_option('wpm_open_order_statuses', array_keys($wc_statuses));
        ?>
        <div class="wpm-tab-content">
            <h2><?php esc_html_e('Advanced Settings', WPM_TEXT_DOMAIN); ?></h2>
            <form method="post" id="wpm-advanced-settings-form">
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Open Order Statuses', WPM_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php foreach ($wc_statuses as $status_key => $status_label) : ?>
                                <label>
                                    <input type="checkbox" name="wpm_open_order_statuses[]" value="<?php echo esc_attr($status_key); ?>" <?php checked(in_array($status_key, $selected_statuses)); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p><?php esc_html_e('Select WooCommerce order statuses to consider as open for production capacity reset.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Reset Production Capacity', WPM_TEXT_DOMAIN); ?></th>
                        <td>
                            <button type="button" class="button wpm-reset-capacity"><?php esc_html_e('Reset Capacity', WPM_TEXT_DOMAIN); ?></button>
                            <p><?php esc_html_e('Recalculate delivery dates for open orders and reserve capacity based on order date and production time.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Cache Management', WPM_TEXT_DOMAIN); ?></th>
                        <td>
                            <button type="button" class="button button-primary wpm-clear-cache"><?php esc_html_e('Clear Cache', WPM_TEXT_DOMAIN); ?></button>
                            <p><?php esc_html_e('Clear cached data to refresh calculations and settings.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings($data) {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        switch($current_tab){
            case 'general':
                update_option('wpm_default_capacity', absint($data['wpm_default_capacity'] ?? 1));
                update_option('wpm_default_delivery_days', absint($data['wpm_default_delivery_days'] ?? 3));
				update_option('wpm_default_max_delivery_days', absint($data['wpm_default_max_delivery_days'] ?? 3));
                update_option('wpm_daily_cutoff_time', self::sanitize_time($data['wpm_daily_cutoff_time'] ?? '14:00'));
                $allowed_statuses = isset($data['wpm_allowed_order_statuses']) ? array_map('sanitize_text_field', $data['wpm_allowed_order_statuses']) : [];
                update_option('wpm_allowed_order_statuses', self::sanitize_order_statuses($allowed_statuses));
                break;
            case 'holidays':
                $weekly_holidays = isset($data['wpm_weekly_holidays']) ? array_map('sanitize_text_field', $data['wpm_weekly_holidays']) : [];
                update_option('wpm_weekly_holidays', Calendar::sanitize_weekly_holidays($weekly_holidays));
                break;
            case 'sms':
                update_option('wpm_enable_sms_customers', isset($data['wpm_enable_sms_customers']) ? 1 : 0);
                update_option('wpm_enable_sms_manager', isset($data['wpm_enable_sms_manager']) ? 1 : 0);
                update_option('wpm_admin_phone_number', sanitize_text_field($data['wpm_admin_phone_number'] ?? ''));
                update_option('wpm_sms_api_key', sanitize_text_field($data['wpm_sms_api_key'] ?? ''));
                update_option('wpm_sms_sender', sanitize_text_field($data['wpm_sms_sender'] ?? ''));
                update_option('wpm_delay_sms_template', sanitize_textarea_field($data['wpm_delay_sms_template'] ?? __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN)));
                update_option('wpm_sms_template', sanitize_textarea_field($data['wpm_sms_template'] ?? __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN)));
                break;
            case 'advanced':
                $wc_statuses = array_keys(wc_get_order_statuses());
                $open_statuses = isset($data['wpm_open_order_statuses']) ? array_map('sanitize_text_field', $data['wpm_open_order_statuses']) : [];
                $open_statuses = array_intersect($open_statuses, $wc_statuses); // Ensure only valid statuses are saved
                update_option('wpm_open_order_statuses', $open_statuses);
                break;
        }
    }

    public static function sanitize_time($value) {
        if (preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            return $value;
        }
        return '14:00';
    }

    public static function sanitize_order_statuses($value) {
        if (!is_array($value)) {
            return [];
        }
        $valid_statuses = array_keys(wc_get_order_statuses());
        return array_intersect($value, $valid_statuses);
    }
	
	public static function reset_production_capacity() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        global $wpdb;

        $open_statuses = get_option('wpm_open_order_statuses', array_keys(wc_get_order_statuses()));
        if (empty($open_statuses)) {
            wp_send_json_error(['message' => __('No open order statuses selected.', WPM_TEXT_DOMAIN)]);
        }

        // Convert statuses to format for SQL (e.g., 'wc-pending', 'wc-processing')
        $open_statuses = array_map(function($status) {
            return 'wc-' . ltrim($status, 'wc-');
        }, $open_statuses);
        $status_placeholders = implode(',', array_fill(0, count($open_statuses), '%s'));

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT o.id as order_id, oi.order_item_id, o.date_created_gmt as order_date,
                   oim.meta_value as product_id, oim2.meta_value as variation_id, oim3.meta_value as quantity
            FROM {$wpdb->prefix}wc_orders o
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_variation_id'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 ON oi.order_item_id = oim3.order_item_id AND oim3.meta_key = '_qty'
            WHERE o.status IN ($status_placeholders)
        ", $open_statuses));

        if (!$items) {
            wp_send_json_success(['message' => __('No open orders to reset.', WPM_TEXT_DOMAIN)]);
        }

        // Clear existing capacity reservations
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wpm_capacity_count");
        \WPM\Utils\Cache::clear();

        // Prepare bulk input for delivery date calculation
        $bulk_items = [];
        foreach ($items as $item) {
            $product_id = absint($item->product_id);
            $variation_id = absint($item->variation_id) ?: 0;
            $quantity = absint($item->quantity) ?: 1;
            $order_date = date('Y-m-d', strtotime($item->order_date));

            if ($product_id) {
                $bulk_items[] = [
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->order_item_id,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'quantity' => $quantity,
                    'order_date' => $order_date
                ];
            }
        }

        // Calculate delivery dates in bulk
        $results = \WPM\Delivery\DeliveryCalculator::calculate_delivery_dates_bulk($bulk_items);

        foreach ($results as $item_data) {
            \WPM\Settings\StatusManager::replace_order_items_status($item_data['order_id'], $item_data['order_item_id'] , null, $item_data['delivery_date']);
			
			// Trigger delay SMS if enabled
            //do_action('wpm_order_item_delivery_date_changed', $item_data['order_item_id'], $item_data['delivery_date']);
        }
        
        wp_send_json_success(['message' => __('Production capacity reset successfully.', WPM_TEXT_DOMAIN)]);
    }
	
	public static function clear_cache() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }
        \WPM\Utils\Cache::clear();
        wp_send_json_success(['message' => __('Cache cleared successfully', WPM_TEXT_DOMAIN)]);
    }
}
?>