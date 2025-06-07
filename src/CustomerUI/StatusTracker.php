<?php
namespace WPM\CustomerUI;

use WPM\Utils\PersianDate;

defined('ABSPATH') || exit;

class StatusTracker {
    public static function init() {
        add_action('woocommerce_order_item_meta_end', [__CLASS__, 'add_delivery_date_to_item_data'], 10, 3);
        // Add endpoint for status tracking
        //add_action('init', [__CLASS__, 'register_endpoint']);
        //add_filter('woocommerce_account_menu_items', [__CLASS__, 'add_menu_item']);
        //add_action('woocommerce_account_wpm-order-status_endpoint', [__CLASS__, 'render_status_page']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /*public static function register_endpoint() {
        add_rewrite_endpoint('wpm-order-status', EP_PAGES);
    }*/

    /*public static function add_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'orders') {
                $new_items['wpm-order-status'] = __('Order Status', 'woocommerce-production-manager');
            }
        }
        return $new_items;
    }*/

    /*public static function render_status_page() {
        global $wpdb;
        if (!is_user_logged_in()) {
            wc_add_notice(__('Please log in to view order statuses.', 'woocommerce-production-manager'), 'error');
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        $where = [];
        $params = [];

        $where[] = 'o.customer_id = %d';
        $params[] = get_current_user_id();

        $allowed_statuses = get_option('wpm_allowed_order_statuses', array_keys(wc_get_order_statuses()));
        if (!empty($allowed_statuses)) {
            $placeholders = implode(',', array_fill(0, count($allowed_statuses), '%s'));
            $where[] = "o.status IN ($placeholders)";
            $params = array_merge($params, $allowed_statuses);
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = "
            SELECT o.id as order_id, oi.order_item_id, oi.order_item_name, o.date_created_gmt as order_date, o.status as order_status, s.status item_status, s.delivery_date
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}wpm_order_items_status s ON s.order_item_id = oi.order_item_id
            $where_sql 
            ORDER BY o.date_created_gmt DESC
        ";

        $items = $wpdb->get_results($wpdb->prepare($query, $params));

        ?>
        <div class="wpm-status-tracker">
            <h2><?php esc_html_e('Order Status', 'woocommerce-production-manager'); ?></h2>

            <!-- Order Items Table -->
            <table class="shop_table wpm-order-status-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order ID', 'woocommerce-production-manager'); ?></th>
                        <th><?php esc_html_e('Item Name', 'woocommerce-production-manager'); ?></th>
                        <th><?php esc_html_e('Order Date', 'woocommerce-production-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'woocommerce-production-manager'); ?></th>
                        <th><?php esc_html_e('Delivery Date', 'woocommerce-production-manager'); ?></th>
                        <th><?php esc_html_e('Progress', 'woocommerce-production-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->order_id); ?></td>
                            <td><?php echo esc_html($item->order_item_name); ?></td>
                            <td><?php echo esc_html(PersianDate::to_persian($item->order_date)); ?></td>
                            <td><?php echo esc_html($item->item_status); ?></td>
                            <td><?php echo esc_html(PersianDate::to_persian($item->delivery_date)); ?></td>
                            <td>
                                <div class="wpm-progress-bar">
                                    <div class="wpm-progress" style="width: <?php echo esc_attr(self::get_progress_percentage($item->item_status)); ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpm-status-log">
                            <td colspan="6">
                                <strong><?php esc_html_e('Status History', 'woocommerce-production-manager'); ?>:</strong>
                                <ul>
                                    <?php
                                    $logs = $wpdb->get_results($wpdb->prepare(
                                        "SELECT status, changed_at, note FROM {$wpdb->prefix}wpm_status_logs WHERE order_item_id = %d ORDER BY changed_at DESC",
                                        $item->order_item_id
                                    ));
                                    foreach ($logs as $log) {
                                        $date = PersianDate::to_persian($log->changed_at, 'Y/m/d H:i');
                                        $note = $log->note ? esc_html($log->note) : esc_html($log->status);
                                        echo '<li>' . sprintf(esc_html__('%s: %s', 'woocommerce-production-manager'), esc_html($date), $note) . '</li>';
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
    }*/

    public static function add_delivery_date_to_item_data($item_id, $item, $order) {
        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT `status`, `delivery_date` FROM {$wpdb->prefix}wpm_order_items_status WHERE order_item_id = %d",
            $item_id
        ));
        $product_id = $item->get_variation_id() ?: $item->get_product_id();
        $production_days = \WPM\Delivery\DeliveryCalculator::get_delivery_days_for_products([$product_id])[$product_id];
        $max_available_date = null;
        if ($result->delivery_date) {
            $max_offset = max(0, $production_days['max'] - $production_days['min']);
            $max_delivery_date = \WPM\Delivery\DeliveryCalculator::add_business_days($result->delivery_date, $max_offset);
        }

        $min_delivery_date = PersianDate::to_persian($result->delivery_date, 'j F');
        $max_delivery_date = PersianDate::to_persian($max_delivery_date, 'j F');
        $delivery_date = sprintf(__('between %s and %s', 'woocommerce-production-manager'), $min_delivery_date, $max_delivery_date);

        ?>
        <div class="wpm-production-status">
            <div><? echo esc_html__('Status: ', 'woocommerce-production-manager'); ?> <span><?php echo esc_html($result->status); ?></span></div>
            <div><? echo esc_html__('Delivery Date: ', 'woocommerce-production-manager'); ?> <span><?php echo esc_html($delivery_date); ?></span></div>
        </div>
        <div class="wpm-progress-bar">
            <div class="wpm-progress" style="width: <?php echo esc_attr(self::get_progress_percentage($result->status)); ?>%;"></div>
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

    public static function enqueue_scripts() {
        if (is_account_page()) {
            //wp_enqueue_script('wpm-frontend-js', WPM_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], '1.0.0', true);
            wp_enqueue_style('wpm-frontend-css', WPM_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0');
            /*wp_localize_script('wpm-frontend-js', 'wpmFrontend', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wpm_customer_ui'),
                'i18n'    => [
                    'loading' => __('Loading...', 'woocommerce-production-manager'),
                    'error'   => __('Unable to calculate delivery date.', 'woocommerce-production-manager'),
                    'smsUpdated' => __('SMS notification settings updated', 'woocommerce-production-manager')
                ]
            ]);*/
        }
    }
}
?>