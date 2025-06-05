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
                        <th><label for="wpm_enable_status_sms"><?php esc_html_e('Enable Status Change SMS', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_status_sms" id="wpm_enable_status_sms" value="1" <?php checked(get_option('wpm_enable_status_sms', 1), 1); ?>>
                            <p><?php esc_html_e('Send SMS when order item status changes.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_enable_delay_sms"><?php esc_html_e('Enable Delivery Delay SMS', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_delay_sms" id="wpm_enable_delay_sms" value="1" <?php checked(get_option('wpm_enable_delay_sms', 1), 1); ?>>
                            <p><?php esc_html_e('Send SMS when delivery date is delayed.', WPM_TEXT_DOMAIN); ?></p>
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
                        <th><label for="wpm_sms_username"><?php esc_html_e('SMS Username', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_username" id="wpm_sms_username" value="<?php echo esc_attr(get_option('wpm_sms_username', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak username.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_password"><?php esc_html_e('SMS Password', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="password" name="wpm_sms_password" id="wpm_sms_password" value="<?php echo esc_attr(get_option('wpm_sms_password', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak password.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_sender_number"><?php esc_html_e('SMS Sender Number', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_sender_number" id="wpm_sms_sender_number" value="<?php echo esc_attr(get_option('wpm_sms_sender_number', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak sender number (used for simple SMS).', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_use_pattern"><?php esc_html_e('Use SMS Pattern', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_sms_use_pattern" id="wpm_sms_use_pattern" value="1" <?php checked(get_option('wpm_sms_use_pattern', 0), 1); ?>>
                            <p><?php esc_html_e('Enable to send SMS using predefined patterns.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_status_pattern_id"><?php esc_html_e('Status Change Pattern ID', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_status_pattern_id" id="wpm_sms_status_pattern_id" value="<?php echo esc_attr(get_option('wpm_sms_status_pattern_id', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter Melipayamak pattern ID for status change SMS.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_delay_pattern_id"><?php esc_html_e('Delivery Delay Pattern ID', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_delay_pattern_id" id="wpm_sms_delay_pattern_id" value="<?php echo esc_attr(get_option('wpm_sms_delay_pattern_id', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter Melipayamak pattern ID for delivery delay SMS.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_template"><?php esc_html_e('Status Change SMS Template', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="wpm_sms_template" id="wpm_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {order_id}, {status}, {item_name}. For pattern, use pattern variables.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_delay_sms_template"><?php esc_html_e('Delivery Delay SMS Template', WPM_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <textarea name="wpm_delay_sms_template" id="wpm_delay_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {customer_name}, {order_id}, {delivery_date}. For pattern, use pattern variables.', WPM_TEXT_DOMAIN); ?></p>
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
        $status_sms_enabled = get_option('wpm_enable_status_sms', 1);
        $sms_username = get_option('wpm_sms_username', '');
        $sms_password = get_option('wpm_sms_password', '');
        $sms_sender = get_option('wpm_sms_sender_number', '');
        $sms_template = get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', WPM_TEXT_DOMAIN));
        $use_pattern = get_option('wpm_sms_use_pattern', 0);
        $pattern_id = get_option('wpm_sms_status_pattern_id', '');

        if (!$sms_enabled || !$status_sms_enabled || empty($sms_username) || empty($sms_password) || (empty($sms_sender) && !$use_pattern) || ($use_pattern && empty($pattern_id))) {
            self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete or disabled', WPM_TEXT_DOMAIN));
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

        if ($use_pattern) {
            // Pattern-based SMS
            $pattern_values = [
                'order_id' => $item->order_id,
                'status' => $new_status,
                'item_name' => $item->order_item_name
            ];
            $response = self::send_sms($phone, $pattern_values, $sms_username, $sms_password, $sms_sender, $pattern_id, true);
        } else {
            // Simple SMS
            $message = str_replace(
                ['{order_id}', '{status}', '{item_name}'],
                [$item->order_id, $new_status, $item->order_item_name],
                $sms_template
            );
            $response = self::send_sms($phone, $message, $sms_username, $sms_password, $sms_sender);
        }

        self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
    }

    public static function send_delivery_delay_sms($order_item_id, $new_delivery_date) {
        global $wpdb;

        $sms_enabled = get_option('wpm_enable_sms_customers', 0);
        $delay_sms_enabled = get_option('wpm_enable_delay_sms', 1);
        $sms_username = get_option('wpm_sms_username', '');
        $sms_password = get_option('wpm_sms_password', '');
        $sms_sender = get_option('wpm_sms_sender_number', '');
        $sms_template = get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', WPM_TEXT_DOMAIN));
        $use_pattern = get_option('wpm_sms_use_pattern', 0);
        $pattern_id = get_option('wpm_sms_delay_pattern_id', '');

        if (!$sms_enabled || !$delay_sms_enabled || empty($sms_username) || empty($sms_password) || (empty($sms_sender) && !$use_pattern) || ($use_pattern && empty($pattern_id))) {
            self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete or disabled', WPM_TEXT_DOMAIN));
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

        if ($use_pattern) {
            // Pattern-based SMS
            $pattern_values = [
                'customer_name' => $user->display_name,
                'order_id' => $item->order_id,
                'delivery_date' => \WPM\Utils\PersianDate::to_persian($new_delivery_date)
            ];
            $response = self::send_sms($phone, $pattern_values, $sms_username, $sms_password, $sms_sender, $pattern_id, true);
        } else {
            // Simple SMS
            $message = str_replace(
                ['{customer_name}', '{order_id}', '{delivery_date}'],
                [$user->display_name, $item->order_id, \WPM\Utils\PersianDate::to_persian($new_delivery_date)],
                $sms_template
            );
            $response = self::send_sms($phone, $message, $sms_username, $sms_password, $sms_sender);
        }

        self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
    }

    private static function send_sms($phone, $message, $username, $password, $sender, $pattern_id = '', $use_pattern = false) {
        try {
            ini_set("soap.wsdl_cache_enabled", "0");
            $sms_client = new \SoapClient("http://api.payamak-panel.com/post/Send.asmx?wsdl", ["encoding" => "UTF-8"]);

            if ($use_pattern) {
                // Pattern-based SMS
                $data = [
                    "username" => $username,
                    "password" => $password,
                    "text" => json_encode($message), // Pattern values as JSON
                    "to" => strval($phone),
                    "bodyId" => $pattern_id
                ];
                $result = $sms_client->SendByBaseNumber($data)->SendByBaseNumberResult;

                if ($result && is_numeric($result)) {
                    return [
                        'success' => true,
                        'message' => __('SMS sent successfully', WPM_TEXT_DOMAIN)
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $result ? $result : __('Pattern SMS failed', WPM_TEXT_DOMAIN)
                    ];
                }
            } else {
                // Simple SMS
                $data = [
                    "username" => $username,
                    "password" => $password,
                    "to" => [$phone],
                    "from" => $sender,
                    "text" => $message,
                    "isflash" => false
                ];
                $result = $sms_client->SendSimpleSMS($data)->SendSimpleSMSResult;

                if ($result && is_numeric($result)) {
                    return [
                        'success' => true,
                        'message' => __('SMS sent successfully', WPM_TEXT_DOMAIN)
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $result ? $result : __('Simple SMS failed', WPM_TEXT_DOMAIN)
                    ];
                }
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
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