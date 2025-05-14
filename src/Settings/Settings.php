<?php
namespace WPM\Settings;

use WPM\Settings\Calendar;
use WPM\Settings\SMS;
use WPM\Settings\StatusManager;

defined('ABSPATH') || exit;

class Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function add_menu_page() {
        add_menu_page(
            __('Production Manager', WPM_TEXT_DOMAIN),
            __('Production Manager', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-settings',
            [__CLASS__, 'render_page'],
            'dashicons-products',
            56
        );
    }

    public static function enqueue_scripts($hook) {
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook, 'toplevel_page_wpm-settings');
    }

    public static function render_page() {
        $tabs = [
            'general' => __('General', WPM_TEXT_DOMAIN),
            'holidays' => __('Holidays', WPM_TEXT_DOMAIN),
            'statuses' => __('Order Statuses', WPM_TEXT_DOMAIN),
            'sms' => __('SMS', WPM_TEXT_DOMAIN),
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
                        <th><label for="wpm_default_delivery_days"><?php esc_html_e('Default Delivery Days', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="number" name="wpm_default_delivery_days" id="wpm_default_delivery_days" value="<?php echo esc_attr(get_option('wpm_default_delivery_days', 3)); ?>" min="1">
                            <p><?php esc_html_e('Number of days to add to order date for default delivery.', WPM_TEXT_DOMAIN); ?></p>
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

    public static function save_settings($data) {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        switch($current_tab){
            case 'general':
                update_option('wpm_default_delivery_days', absint($data['wpm_default_delivery_days'] ?? 3));
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
                update_option('wpm_api_key', sanitize_text_field($data['wpm_api_key'] ?? ''));
                update_option('wpm_delay_sms_template', sanitize_textarea_field($data['wpm_delay_sms_template'] ?? __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN)));
                update_option('wpm_sms_template', sanitize_textarea_field($data['wpm_sms_template'] ?? __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN)));
                break;
            case 'statuses':
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
}
?>