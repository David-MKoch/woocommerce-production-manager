<?php
namespace WPM\API;

use Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class Webhook {
    public static function init() {
        // Trigger webhook on status change
        add_action('wpm_order_item_status_changed', [__CLASS__, 'send_webhook'], 10, 2);
        // Add webhook settings
        add_action('wpm_settings_tabs', [__CLASS__, 'add_settings_tab']);
        add_action('wpm_settings_tab_webhook', [__CLASS__, 'render_settings_tab']);
        add_action('wpm_save_settings', [__CLASS__, 'save_settings']);
    }

    public static function send_webhook($order_item_id/*, $old_status*/, $new_status) {
        $webhook_url = get_option('wpm_webhook_url', '');
        $webhook_enabled = get_option('wpm_webhook_enabled', 'no') === 'yes';

        if (!$webhook_enabled || empty($webhook_url)) {
            return;
        }

        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare("
            SELECT s.*, oi.order_item_name, o.order_date
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            WHERE s.order_item_id = %d
        ", $order_item_id));

        if (!$item) {
            return;
        }

        $payload = [
            'event' => 'order_item_status_changed',
            'order_item_id' => $order_item_id,
            'order_id' => $item->order_id,
            'item_name' => $item->order_item_name,
            //'old_status' => $old_status,
            'new_status' => $new_status,
            'delivery_date' => Jalalian::fromDateTime($item->delivery_date)->format('Y/m/d'),
            'order_date' => Jalalian::fromDateTime($item->order_date)->format('Y/m/d'),
            'timestamp' => current_time('mysql')
        ];

        $response = wp_remote_post($webhook_url, [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => get_option('wpm_api_key', '')
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            error_log('WPM Webhook Error: ' . $response->get_error_message());
        }
    }

    public static function add_settings_tab($tabs) {
        $tabs['webhook'] = __('Webhook & API', WPM_TEXT_DOMAIN);
        return $tabs;
    }

    public static function render_settings_tab() {
        ?>
        <h2><?php esc_html_e('Webhook & API Settings', WPM_TEXT_DOMAIN); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpm_api_key"><?php esc_html_e('API Key', WPM_TEXT_DOMAIN); ?></label></th>
                <td>
                    <input type="text" name="wpm_api_key" id="wpm_api_key" value="<?php echo esc_attr(get_option('wpm_api_key')); ?>" class="regular-text">
                    <p><?php esc_html_e('Generate a secure key for API authentication.', WPM_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpm_webhook_url"><?php esc_html_e('Webhook URL', WPM_TEXT_DOMAIN); ?></label></th>
                <td>
                    <input type="url" name="wpm_webhook_url" id="wpm_webhook_url" value="<?php echo esc_attr(get_option('wpm_webhook_url')); ?>" class="regular-text">
                    <p><?php esc_html_e('URL to receive webhook notifications for status changes.', WPM_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpm_webhook_enabled"><?php esc_html_e('Enable Webhook', WPM_TEXT_DOMAIN); ?></label></th>
                <td>
                    <input type="checkbox" name="wpm_webhook_enabled" id="wpm_webhook_enabled" value="yes" <?php checked(get_option('wpm_webhook_enabled'), 'yes'); ?>>
                    <p><?php esc_html_e('Enable sending webhook notifications.', WPM_TEXT_DOMAIN); ?></p>
                </td>
            </tr>
        </table>
        <h3><?php esc_html_e('API Documentation', WPM_TEXT_DOMAIN); ?></h3>
        <p><?php esc_html_e('Use the following endpoints with the API key in the X-API-Key header:', WPM_TEXT_DOMAIN); ?></p>
        <ul>
            <li><strong>GET /wp-json/wpm/v1/capacity</strong>: <?php esc_html_e('Get capacity information. Parameters: date, entity_type, entity_id.', WPM_TEXT_DOMAIN); ?></li>
            <li><strong>GET /wp-json/wpm/v1/order-items</strong>: <?php esc_html_e('Get order items. Parameters: status, category, date_from, date_to, persian_date.', WPM_TEXT_DOMAIN); ?></li>
            <li><strong>GET /wp-json/wpm/v1/reports/{type}</strong>: <?php esc_html_e('Get reports. Types: category-orders, full-capacity. Parameters: date_from, date_to, persian_date.', WPM_TEXT_DOMAIN); ?></li>
        </ul>
        <?php
    }

    public static function save_settings($settings) {
        if (isset($_POST['wpm_api_key'])) {
            update_option('wpm_api_key', sanitize_text_field($_POST['wpm_api_key']));
        }
        if (isset($_POST['wpm_webhook_url'])) {
            update_option('wpm_webhook_url', esc_url_raw($_POST['wpm_webhook_url']));
        }
        update_option('wpm_webhook_enabled', isset($_POST['wpm_webhook_enabled']) ? 'yes' : 'no');
    }
}
?>