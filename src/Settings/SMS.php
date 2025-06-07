<?php
namespace WPM\Settings;

defined('ABSPATH') || exit;

class SMS {
    public static function init() {
        add_action('wpm_order_item_status_changed', [__CLASS__, 'send_status_change_sms'], 10, 2);
        add_action('wpm_order_item_delivery_date_changed', [__CLASS__, 'send_delivery_delay_sms'], 10, 2);

        // Schedule cron job for checking delayed items
        add_action('wpm_check_delayed_items', [__CLASS__, 'check_delayed_items']);
        add_action('init', [__CLASS__, 'schedule_cron']);
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled('wpm_check_delayed_items')) {
            wp_schedule_event(time(), 'daily', 'wpm_check_delayed_items');
        }
    }

    public static function render_sms_tab() {
        ?>
        <form method="post" id="wpm-settings-form">
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('SMS Settings', 'woocommerce-production-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="wpm_enable_sms_manager"><?php esc_html_e('Enable SMS for Manager', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_sms_manager" id="wpm_enable_sms_manager" value="1" <?php checked(get_option('wpm_enable_sms_manager', 0), 1); ?>>
                            <p><?php esc_html_e('Enable SMS notifications for managers.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_enable_sms_customers"><?php esc_html_e('Enable SMS for Customers', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_sms_customers" id="wpm_enable_sms_customers" value="1" <?php checked(get_option('wpm_enable_sms_customers', 0), 1); ?>>
                            <p><?php esc_html_e('Enable SMS notifications for customers.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <!-- <tr>
                        <th><label for="wpm_sms_use_pattern"><?php esc_html_e('Use SMS Pattern', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_sms_use_pattern" id="wpm_sms_use_pattern" value="1" <?php checked(get_option('wpm_sms_use_pattern', 0), 1); ?>>
                            <p><?php esc_html_e('Enable to send SMS using predefined patterns.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr> -->
                    <tr>
                        <th><label for="wpm_admin_phone_number"><?php esc_html_e('Admin Phone Number', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="text" name="wpm_admin_phone_number" id="wpm_admin_phone_number" value="<?php echo esc_attr(get_option('wpm_admin_phone_number', '')); ?>">
                            <p><?php esc_html_e('Phone number for admin SMS notifications.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_username"><?php esc_html_e('SMS Username', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_username" id="wpm_sms_username" value="<?php echo esc_attr(get_option('wpm_sms_username', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak username.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_password"><?php esc_html_e('SMS Password', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="password" name="wpm_sms_password" id="wpm_sms_password" value="<?php echo esc_attr(get_option('wpm_sms_password', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak password.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_sms_sender_number"><?php esc_html_e('SMS Sender Number', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_sender_number" id="wpm_sms_sender_number" value="<?php echo esc_attr(get_option('wpm_sms_sender_number', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter your Melipayamak sender number (used for simple SMS).', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wpm_enable_status_sms"><?php esc_html_e('Enable Status Change SMS', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_status_sms" id="wpm_enable_status_sms" value="1" <?php checked(get_option('wpm_enable_status_sms', 1), 1); ?>>
                            <p><?php esc_html_e('Send SMS when order item status changes.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <!-- <tr>
                        <th><label for="wpm_sms_status_pattern_id"><?php esc_html_e('Status Change Pattern ID', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_status_pattern_id" id="wpm_sms_status_pattern_id" value="<?php echo esc_attr(get_option('wpm_sms_status_pattern_id', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter Melipayamak pattern ID for status change SMS.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr> -->
                    <tr>
                        <th><label for="wpm_sms_template"><?php esc_html_e('Status Change SMS Template', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <textarea name="wpm_sms_template" id="wpm_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', 'woocommerce-production-manager'))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {customer_name}, {order_id}, {status}, {item_name}. For pattern, use pattern variables.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="wpm_enable_delay_sms"><?php esc_html_e('Enable Delivery Delay SMS', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="checkbox" name="wpm_enable_delay_sms" id="wpm_enable_delay_sms" value="1" <?php checked(get_option('wpm_enable_delay_sms', 1), 1); ?>>
                            <p><?php esc_html_e('Send SMS when delivery date is delayed.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                    <!-- <tr>
                        <th><label for="wpm_sms_delay_pattern_id"><?php esc_html_e('Delivery Delay Pattern ID', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <input type="text" name="wpm_sms_delay_pattern_id" id="wpm_sms_delay_pattern_id" value="<?php echo esc_attr(get_option('wpm_sms_delay_pattern_id', '')); ?>" class="regular-text">
                            <p><?php esc_html_e('Enter Melipayamak pattern ID for delivery delay SMS.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr> -->
                    <tr>
                        <th><label for="wpm_delay_sms_template"><?php esc_html_e('Delivery Delay SMS Template', 'woocommerce-production-manager'); ?></label></th>
                        <td>
                            <textarea name="wpm_delay_sms_template" id="wpm_delay_sms_template" class="large-text" rows="4"><?php echo esc_textarea(get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', 'woocommerce-production-manager'))); ?></textarea>
                            <p><?php esc_html_e('Available placeholders: {customer_name}, {order_id}, {delivery_date}, {item_name}. For pattern, use pattern variables.', 'woocommerce-production-manager'); ?></p>
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

        $status_sms_enabled = get_option('wpm_enable_status_sms', 1);
        $manager_enabled = get_option('wpm_enable_sms_manager', 0);
        $customers_enabled = get_option('wpm_enable_sms_customers', 0);

        $sms_username = get_option('wpm_sms_username', '');
        $sms_password = get_option('wpm_sms_password', '');
        $sms_sender = get_option('wpm_sms_sender_number', '');
        $sms_template = get_option('wpm_sms_template', __('Order #{order_id} status changed to {status}.', 'woocommerce-production-manager'));
        $use_pattern = get_option('wpm_sms_use_pattern', 0);
        $pattern_id = get_option('wpm_sms_delay_pattern_id', '');

        if (!$status_sms_enabled || empty($sms_username) || empty($sms_password) || (empty($sms_sender) && !$use_pattern) || ($use_pattern && empty($pattern_id))) {
            self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete or disabled', 'woocommerce-production-manager'));
            return;
        }

        $item = $wpdb->get_row($wpdb->prepare("
            SELECT oi.order_id, oi.order_item_name, o.customer_id
            FROM {$wpdb->prefix}wc_orders o 
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            WHERE oi.order_item_id = %d
        ", $order_item_id));

        if (!$item) {
            self::log_sms($order_item_id, '', 'failed', __('Order item not found', 'woocommerce-production-manager'));
            return;
        }

        $user = get_user_by('id', $item->customer_id);
        $sms_vars = [
            'customer_name' => $user->display_name,
            'order_id' => $item->order_id,
            'status' => $new_status,
            'item_name' => $item->order_item_name
        ];
        $message = str_replace(
            ['{customer_name}', '{order_id}', '{status}', '{item_name}'],
            $sms_vars,
            $sms_template
        );

        if($manager_enabled){
            $phone = get_option('wpm_admin_phone_number', '');
            $response = self::send_sms([
                'phone' => $phone,
                'use_pattern' => $use_pattern,
                'pattern_id' => $pattern_id,
                'sms_vars' => $sms_vars,
                'message' => $message,
                'sms_username' => $sms_username,
                'sms_password' => $sms_password,
                'sms_sender' => $sms_sender
            ]);
            self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
        }
        if($customers_enabled){
            $phone = get_user_meta($item->customer_id, 'billing_phone', true);
            $response = self::send_sms([
                'phone' => $phone,
                'use_pattern' => $use_pattern,
                'pattern_id' => $pattern_id,
                'sms_vars' => $sms_vars,
                'message' => $message,
                'sms_username' => $sms_username,
                'sms_password' => $sms_password,
                'sms_sender' => $sms_sender
            ]);
            self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
        }
    }

    public static function send_delivery_delay_sms($order_item_id, $new_delivery_date) {
        global $wpdb;

        $delay_sms_enabled = get_option('wpm_enable_delay_sms', 1);
        $manager_enabled = get_option('wpm_enable_sms_manager', 0);
        $customers_enabled = get_option('wpm_enable_sms_customers', 0);

        $sms_username = get_option('wpm_sms_username', '');
        $sms_password = get_option('wpm_sms_password', '');
        $sms_sender = get_option('wpm_sms_sender_number', '');
        $sms_template = get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', 'woocommerce-production-manager'));
        $use_pattern = get_option('wpm_sms_use_pattern', 0);
        $pattern_id = get_option('wpm_sms_delay_pattern_id', '');

        if (!$delay_sms_enabled || empty($sms_username) || empty($sms_password) || (empty($sms_sender) && !$use_pattern) || ($use_pattern && empty($pattern_id))) {
            self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete or disabled', 'woocommerce-production-manager'));
            return;
        }

        $item = $wpdb->get_row($wpdb->prepare("
            SELECT oi.order_id, oi.order_item_name, o.customer_id
            FROM {$wpdb->prefix}wc_orders o 
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            WHERE oi.order_item_id = %d
        ", $order_item_id));

        if (!$item) {
            self::log_sms($order_item_id, '', 'failed', __('Order item not found', 'woocommerce-production-manager'));
            return;
        }

        $user = get_user_by('id', $item->customer_id);
        $sms_vars = [
            'customer_name' => $user->display_name,
            'order_id' => $item->order_id,
            'delivery_date' => \WPM\Utils\PersianDate::to_persian($new_delivery_date),
            'item_name' => $item->order_item_name
        ];
        $message = str_replace(
            ['{customer_name}', '{order_id}', '{delivery_date}', '{item_name}'],
            $sms_vars,
            $sms_template
        );

        if($manager_enabled){
            $phone = get_option('wpm_admin_phone_number', '');
            $response = self::send_sms([
                'phone' => get_option('wpm_admin_phone_number', ''),
                'use_pattern' => $use_pattern,
                'pattern_id' => $pattern_id,
                'sms_vars' => $sms_vars,
                'message' => $message,
                'sms_username' => $sms_username,
                'sms_password' => $sms_password,
                'sms_sender' => $sms_sender
            ]);
            self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
        }
        if($customers_enabled){
            $phone = get_user_meta($item->customer_id, 'billing_phone', true);
            $response = self::send_sms([
                'phone' => get_user_meta($item->customer_id, 'billing_phone', true),
                'use_pattern' => $use_pattern,
                'pattern_id' => $pattern_id,
                'sms_vars' => $sms_vars,
                'message' => $message,
                'sms_username' => $sms_username,
                'sms_password' => $sms_password,
                'sms_sender' => $sms_sender
            ]);
            self::log_sms($order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
        }
    }

    private static function send_sms($sms_settings) {
        if (!$sms_settings['phone']) {
            return [
                'success' => false,
                'message' => __('Phone disabled', 'woocommerce-production-manager')
            ];
        }
        try {
            ini_set("soap.wsdl_cache_enabled", "0");
            $sms_client = new \SoapClient("http://api.payamak-panel.com/post/Send.asmx?wsdl", ["encoding" => "UTF-8"]);

            if ($sms_settings['use_pattern']) {
                // Pattern-based SMS
                $data = [
                    "username" => $sms_settings['sms_username'],
                    "password" => $sms_settings['sms_password'],
                    "text" => $sms_settings['sms_vars'], //json_encode() Pattern values as JSON
                    "to" => strval($sms_settings['phone']),
                    "bodyId" => $sms_settings['pattern_id']
                ];
                $result = $sms_client->SendByBaseNumber($data)->SendByBaseNumberResult->string;

                return [
                    'success' => true,
                    'message' => print_r($result, true)
                ];
            } else {
                // Simple SMS
                $data = [
                    "username" => $sms_settings['sms_username'],
                    "password" => $sms_settings['sms_password'],
                    "to" => [$sms_settings['phone']],
                    "from" => $sms_settings['sms_sender'],
                    "text" => $sms_settings['message'],
                    "isflash" => false
                ];
                $result = $sms_client->SendSimpleSMS($data)->SendSimpleSMSResult;

                return [
                    'success' => true,
                    'message' => print_r($result, true)
                ];
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

    public static function check_delayed_items() {
        global $wpdb;

        $delay_sms_enabled = get_option('wpm_enable_delay_sms', 1);
        $manager_enabled = get_option('wpm_enable_sms_manager', 0);
        $customers_enabled = get_option('wpm_enable_sms_customers', 0);

        $sms_username = get_option('wpm_sms_username', '');
        $sms_password = get_option('wpm_sms_password', '');
        $sms_sender = get_option('wpm_sms_sender_number', '');
        $sms_template = get_option('wpm_delay_sms_template', __('Dear {customer_name}, your order #{order_id} is delayed. New delivery date: {delivery_date}.', 'woocommerce-production-manager'));
        $use_pattern = get_option('wpm_sms_use_pattern', 0);
        $pattern_id = get_option('wpm_sms_delay_pattern_id', '');

        if (!$delay_sms_enabled || empty($sms_username) || empty($sms_password) || (empty($sms_sender) && !$use_pattern) || ($use_pattern && empty($pattern_id))) {
            //self::log_sms($order_item_id, '', 'failed', __('SMS configuration incomplete or disabled', 'woocommerce-production-manager'));
            return;
        }

        // Get open order statuses from settings
        $open_statuses = get_option('wpm_open_order_statuses', array_keys(wc_get_order_statuses()));
        if (empty($open_statuses)) {
            return; // No open statuses defined, skip processing
        }

        // Convert statuses to SQL format (e.g., 'wc-pending', 'wc-processing')
        $open_statuses = array_map(function($status) {
            return 'wc-' . ltrim($status, 'wc-');
        }, $open_statuses);
        $status_placeholders = implode(',', array_fill(0, count($open_statuses), '%s'));

        $query = $wpdb->prepare("
            SELECT s.order_id, oi.order_item_id, oi.order_item_name, o.customer_id, s.delivery_date
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = s.order_item_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON s.order_item_id = om.order_item_id AND om.meta_key = 'wpm_delay_sms_sent'
            WHERE s.delivery_date < CURRENT_DATE
            AND o.status IN ($status_placeholders) AND o.status != 'wc-completed'
            AND (om.meta_value IS NULL OR om.meta_value != '1')
        ", $open_statuses);

        $items = $wpdb->get_results($query);

        foreach ($items as $item) {
            $user = get_user_by('id', $item->customer_id);
            $sms_vars = [
                'customer_name' => $user->display_name,
                'order_id' => $item->order_id,
                'delivery_date' => \WPM\Utils\PersianDate::to_persian($item->delivery_date),
                'item_name' => $item->order_item_name
            ];
            $message = str_replace(
                ['{customer_name}', '{order_id}', '{delivery_date}', '{item_name}'],
                $sms_vars,
                $sms_template
            );

            $sent = false;
            if($manager_enabled){
                $phone = get_option('wpm_admin_phone_number', '');
                $response = self::send_sms([
                    'phone' => get_option('wpm_admin_phone_number', ''),
                    'use_pattern' => $use_pattern,
                    'pattern_id' => $pattern_id,
                    'sms_vars' => $sms_vars,
                    'message' => $message,
                    'sms_username' => $sms_username,
                    'sms_password' => $sms_password,
                    'sms_sender' => $sms_sender
                ]);
                $sent = $response['success'];
                self::log_sms($item->order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
            }
            if($customers_enabled){
                $phone = get_user_meta($item->customer_id, 'billing_phone', true);
                $response = self::send_sms([
                    'phone' => get_user_meta($item->customer_id, 'billing_phone', true),
                    'use_pattern' => $use_pattern,
                    'pattern_id' => $pattern_id,
                    'sms_vars' => $sms_vars,
                    'message' => $message,
                    'sms_username' => $sms_username,
                    'sms_password' => $sms_password,
                    'sms_sender' => $sms_sender
                ]);
                $sent = $response['success'];
                self::log_sms($item->order_item_id, $phone, $response['success'] ? 'success' : 'failed', $response['message']);
            }

            if ($sent) {
                // Store meta in woocommerce_order_itemmeta to prevent duplicate SMS
                wc_update_order_item_meta($item->order_item_id, 'wpm_delay_sms_sent', '1');
            }
        }
    }
}
?>