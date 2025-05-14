<?php
namespace WPM\Utils;

defined('ABSPATH') || exit;

class SettingsManager {
    private static $instance = null;
    private $options = [];

    private function __construct() {
        // Preload frequently used options
        $this->options = [
            'wpm_default_delivery_days' => get_option('wpm_default_delivery_days', 3),
            'wpm_daily_cutoff_time' => get_option('wpm_daily_cutoff_time', '14:00'),
            'wpm_enable_sms_manager' => get_option('wpm_enable_sms_manager', 0),
            'wpm_enable_sms_customers' => get_option('wpm_enable_sms_customers', 0),
            'wpm_admin_phone_number' => get_option('wpm_admin_phone_number', ''),
            'wpm_api_key' => get_option('wpm_api_key', ''),
            'wpm_delay_sms_template' => get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN)),
            'wpm_statuses' => get_option('wpm_statuses', [['name' => __('Received', WPM_TEXT_DOMAIN), 'color' => '#0073aa']]),
            'wpm_holidays' => get_option('wpm_holidays', []),
            'wpm_weekly_holidays' => get_option('wpm_weekly_holidays', []),
        ];
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    public function set($key, $value) {
        $this->options[$key] = $value;
        update_option($key, $value);
    }
}
?>