<?php
namespace WPM\Settings;

defined('ABSPATH') || exit;

class SMS {
    public static function init() {
        add_action('wpm_order_item_status_changed', [__CLASS__, 'send_status_change_sms'], 10, 2);
        add_action('wpm_order_item_delivery_date_changed', [__CLASS__, 'send_delivery_delay_sms'], 10, 2);
    }

    public static function render_sms_tab() {
        ?>
        <form method="post" id="wpm-settings-form">
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('SMS Settings', WPM_TEXT_DOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpm_enable_sms_customers"><?php esc_html_e('Enable SMS for Customers', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_sms_customers" id="wpm_enable_sms_customers" value="1" <?php checked(get_option('wpm_enable_sms_customers', 0), 1); ?>>
                            <p><?php esc_html_e('Enable SMS notifications for customers.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_enable_sms_manager"><?php esc_html_e('Enable SMS Manager', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_sms_manager" id="wpm_enable_sms_manager" value="1" <?php checked(get_option('wpm_enable_sms_manager', 0), 1); ?>>
                            <p><?php esc_html_e('Enable SMS notifications for managers.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_admin_phone_number"><?php esc_html_e('Admin Phone Number', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_admin_phone_number" id="wpm_admin_phone_number" value="<?php echo esc_attr(get_option('wpm_admin_phone_number', '')); ?>">
                            <p><?php esc_html_e('Phone number for admin SMS notifications.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_api_key"><?php esc_html_e('SMS API Key', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_api_key" id="wpm_sms_api_key" value="<?php echo esc_attr(get_option('wpm_sms_api_key', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Kavenegar API key.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_sender"><?php esc_html_e('SMS Sender Number', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_sender" id="wpm_sms_sender" value="<?php echo esc_attr(get_option('wpm_sms_sender', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Kavenegar sender number.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_template"><?php esc_html_e('Status Change SMS Template', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="wpm_sms_template" id="wpm_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {order_id}, {status}, {item_name}.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_delay_sms_template"><?php esc_html_e('Delay SMS Template', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="wpm_delay_sms_template" id="wpm_delay_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {customer_name}, {order_id}, {delivery_date}.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    public static function send_status_change_sms($order_item_id, $new_status) {
        global $wpdb;

        $sms_enabled = get_option('wpm_enable_sms_customers', 0);
        $sms_api_key = get_option('wpm_sms_api_key', '');
        $sms_sender = get_option('wpm_sms_sender', '');
        $sms_template = get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN));

        if (!$sms_enabled || empty($sms_api_key) || empty($sms_sender)) {
			self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete', WPM_TEXT_DOMAIN));
            return;
        }

        $item = $wpdb->get_row($wpdb->prepare("
            SELECT s.order_id, s.status, o.customer_id, oi.order_item_name
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            WHERE s.order_item_id = %d
        ", $order_item_id));

        if (!$item) {
			self::log_sms($order_item_id, '', 'failed', __('Order item not found', WPM_TEXT_DOMAIN));
            return;
        }

        $user = get_user_by('id', $item->customer_id);
        $phone = get_user_meta($item->customer_id, 'billing_phone', true);
        $sms_notification_enabled = get_user_meta($item->customer_id, 'wpm_sms_notification', true) !== 'no';

        if (!$phone || !$sms_notification_enabled) {
			self::log_sms($order_item_id, $phone, 'failed', __('Phone or notification disabled', WPM_TEXT_DOMAIN));
            return;
        }

        $message = str_replace(
            ['{order_id}', '{status}', '{item_name}'],
            [$item->order_id, $new_status, $item->order_item_name],
            $sms_template
        );

        $response = self::send_sms($phone, $message, $sms_api_key, $sms_sender);
        self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
    }

    public static function send_delivery_delay_sms($order_item_id, $new_delivery_date) {
        global $wpdb;

        $sms_enabled = get_option('wpm_enable_sms_customers', 0);
        $sms_api_key = get_option('wpm_sms_api_key', '');
        $sms_sender = get_option('wpm_sms_sender', '');
        $sms_template = get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN));

        if (!$sms_enabled || empty($sms_api_key) || empty($sms_sender)) {
            self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete', WPM_TEXT_DOMAIN));
            return;
        }

        $item = $wpdb->get_row($wpdb->prepare("
            SELECT s.order_id, o.customer_id, oi.order_item_name
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            WHERE s.order_item_id = %d
        ", $order_item_id));

        if (!$item) {
			self::log_sms($order_item_id, '', 'failed', __('Order item not found', WPM_TEXT_DOMAIN));
            return;
        }

        $user = get_user_by('id', $item->customer_id);
        $phone = get_user_meta($item->customer_id, 'billing_phone', true);
        $sms_notification_enabled = get_user_meta($item->customer_id, 'wpm_sms_notification', true) !== 'no';

        if (!$phone || !$sms_notification_enabled) {
			self::log_sms($order_item_id, $phone, 'failed', __('Phone or notification disabled', WPM_TEXT_DOMAIN));
            return;
        }

        $message = str_replace(
            ['{customer_name}', '{order_id}', '{delivery_date}'],
            [$user->display_name, $item->order_id, \WPM\Utils\PersianDate::to_persian($new_delivery_date)],
            $sms_template
        );

        $response = self::send_sms($phone, $message, $sms_api_key, $sms_sender);
        self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
    }

    private static function send_sms($phone, $message, $api_key, $sender) {
        $response = wp_remote_post('https://api.kavenegar.com/v1/' . $api_key . '/sms/send.json', [
            'body' => [
                'receptor' => $phone,
                'message' => $message,
                'sender' => $sender
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['return']['status']) && $body['return']['status'] == 200) {
            return [
                'success' => true,
                'message' => __('SMS sent successfully', WPM_TEXT_DOMAIN)
            ];
        }

        return [
            'success' => false,
            'message' => $body['return']['message'] ?? __('Unknown error', WPM_TEXT_DOMAIN)
        ];
    }

    private static function log_sms($order_item_id, $phone, $status, $response) {
        global $wpdb;
        $wpdb->insert(
            "{$wpdb->prefix}wpm_sms_logs",
            [
                'order_item_id' => $order_item_id,
                'phone' => $phone,
                'message' => $response,
                'status' => $status,
                'response' => $response,
                'sent_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
    }
}
?>