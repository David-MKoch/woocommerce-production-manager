<?php
namespace WPM\CustomerUI;

use WPM\Utils\PersianDate;

defined('ABSPATH') || exit;

class StatusTracker {
    public static function init() {
        // Add endpoint for status tracking
        add_action('init', [__CLASS__, 'register_endpoint']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'add_menu_item']);
        add_action('woocommerce_account_wpm-order-status_endpoint', [__CLASS__, 'render_status_page']);
        // Handle SMS notification settings
        add_action('wp_ajax_wpm_update_sms_notification', [__CLASS__, 'update_sms_notification']);
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function register_endpoint() {
        add_rewrite_endpoint('wpm-order-status', EP_PAGES);
    }

    public static function add_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'orders') {
                $new_items['wpm-order-status'] = __('Order Status', WPM_TEXT_DOMAIN);
            }
        }
        return $new_items;
    }

    public static function render_status_page() {
        if (!is_user_logged_in()) {
            wc_add_notice(__('Please log in to view order statuses.', WPM_TEXT_DOMAIN), 'error');
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $user_id = get_current_user_id();
        $sms_enabled = get_user_meta($user_id, 'wpm_sms_notification', true) !== 'no';

        global $wpdb;

        $query = "
            SELECT s.*, o.order_date, oi.order_item_name, p.post_title as product_name, p.ID as product_id
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            JOIN {$wpdb->posts} p ON oi.order_item_meta_product_id = p.ID
            WHERE o.customer_id = %d
            ORDER BY o.order_date DESC
        ";

        $items = $wpdb->get_results($wpdb->prepare($query, $user_id));

        ?>
        <div class="wpm-status-tracker">
            <h2><?php esc_html_e('Order Status', WPM_TEXT_DOMAIN); ?></h2>

            <!-- SMS Notification Setting -->
            <form id="wpm-sms-notification-form">
                <label>
                    <input type="checkbox" id="wpm-sms-notification" <?php checked($sms_enabled); ?>>
                    <?php esc_html_e('Receive SMS notifications for order status changes', WPM_TEXT_DOMAIN); ?>
                </label>
            </form>

            <!-- Order Items Table -->
            <table class="shop_table wpm-order-status-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order ID', WPM_TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Item Name', WPM_TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Order Date', WPM_TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Status', WPM_TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Delivery Date', WPM_TEXT_DOMAIN); ?></th>
                        <th><?php esc_html_e('Progress', WPM_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->order_id); ?></td>
                            <td><?php echo esc_html($item->order_item_name); ?></td>
                            <td><?php echo esc_html(PersianDate::to_persian($item->order_date)); ?></td>
                            <td><?php echo esc_html($item->status); ?></td>
                            <td><?php echo esc_html(PersianDate::to_persian($item->delivery_date)); ?></td>
                            <td>
                                <div class="wpm-progress-bar">
                                    <div class="wpm-progress" style="width: <?php echo esc_attr(self::get_progress_percentage($item->status)); ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpm-status-log">
                            <td colspan="6">
                                <strong><?php esc_html_e('Status History', WPM_TEXT_DOMAIN); ?>:</strong>
                                <ul>
                                    <?php
                                    $logs = $wpdb->get_results($wpdb->prepare(
                                        "SELECT status, changed_at, note FROM {$wpdb->prefix}wpm_status_logs WHERE order_item_id = %d ORDER BY changed_at DESC",
                                        $item->order_item_id
                                    ));
                                    foreach ($logs as $log) {
                                        $date = PersianDate::to_persian($log->changed_at, 'Y/m/d H:i');
                                        $note = $log->note ? esc_html($log->note) : esc_html($log->status);
                                        echo '<li>' . sprintf(esc_html__('%s: %s', WPM_TEXT_DOMAIN), esc_html($date), $note) . '</li>';
                                    }
                                    ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function get_progress_percentage($status) {
        $statuses = \WPM\Settings\StatusManager::get_statuses();
        $index = array_search($status, $statuses);
        if ($index === false) {
            return 0;
        }
        return (($index + 1) / count($statuses)) * 100;
    }

    public static function update_sms_notification() {
        check_ajax_referer('wpm_customer_ui', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        $user_id = get_current_user_id();
        $sms_enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true' ? 'yes' : 'no';

        update_user_meta($user_id, 'wpm_sms_notification', $sms_enabled);

        wp_send_json_success(['message' => __('SMS notification settings updated', WPM_TEXT_DOMAIN)]);
    }

    public static function enqueue_scripts() {
        if (is_account_page()) {
            wp_enqueue_script('wpm-frontend-js', WPM_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], '1.0.0', true);
            wp_enqueue_style('wpm-frontend-css', WPM_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0');
            wp_localize_script('wpm-frontend-js', 'wpmFrontend', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wpm_customer_ui'),
                'i18n'    => [
                    'loading' => __('Loading...', WPM_TEXT_DOMAIN),
                    'error'   => __('Unable to calculate delivery date.', WPM_TEXT_DOMAIN),
                    'smsUpdated' => __('SMS notification settings updated', WPM_TEXT_DOMAIN)
                ]
            ]);
        }
    }
}
?>