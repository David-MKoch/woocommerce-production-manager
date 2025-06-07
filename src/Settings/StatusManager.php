<?php
namespace WPM\Settings;

defined('ABSPATH') || exit;

class StatusManager {
    public static function init() {
        add_action('wp_ajax_wpm_update_order_item_status', [__CLASS__, 'update_order_item_status']);
        add_action('wp_ajax_wpm_update_order_item_delivery_date', [__CLASS__, 'update_order_item_delivery_date']);
        
        add_action('wp_ajax_wpm_save_status', [__CLASS__, 'save_status']);
        add_action('wp_ajax_wpm_delete_status', [__CLASS__, 'delete_status']);
        add_action('wp_ajax_wpm_update_status', [__CLASS__, 'update_status']);
        add_action('wp_ajax_wpm_reorder_statuses', [__CLASS__, 'reorder_statuses']);
    }

    public static function render_statuses_tab() {
        ?>
        
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('Order Statuses', 'woocommerce-production-manager'); ?></h2>
                <div class="wpm-statuses-form">
                    <input type="text" id="wpm-status-name" placeholder="<?php esc_attr_e('Status Name', 'woocommerce-production-manager'); ?>">
                    <input type="text" id="wpm-status-color" class="wp-color-picker" value="#0073aa">
                    <button type="button" class="button wpm-add-status"><?php esc_html_e('Add Status', 'woocommerce-production-manager'); ?></button>
                </div>
                <table class="wp-list-table widefat fixed striped wpm-statuses-list">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Status Name', 'woocommerce-production-manager'); ?></th>
                            <th><?php esc_html_e('Color', 'woocommerce-production-manager'); ?></th>
                            <th><?php esc_html_e('Actions', 'woocommerce-production-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpm-statuses-sortable">
                        <?php
                        $statuses = get_option('wpm_statuses', [['name' => __('Received', 'woocommerce-production-manager'), 'color' => '#0073aa']]);
                        foreach ($statuses as $index => $status) :
                        ?>
                            <tr data-index="<?php echo esc_attr($index); ?>">
                                <td class="wpm-status-name"><?php echo esc_html($status['name']); ?></td>
                                <td class="wpm-status-color"><span style="background-color: <?php echo esc_attr($status['color']); ?>; padding: 5px; color: #fff;"><?php echo esc_html($status['color']); ?></span></td>
                                <td>
                                    <button class="button wpm-edit-status"><?php esc_html_e('Edit', 'woocommerce-production-manager'); ?></button>
                                    <button class="button wpm-delete-status"><?php esc_html_e('Delete', 'woocommerce-production-manager'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php
    }

    public static function save_status() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $name = sanitize_text_field($_POST['name']);
        $color = sanitize_hex_color($_POST['color']);

        if (empty($name) || empty($color)) {
            wp_send_json_error(['message' => __('Name and color are required', 'woocommerce-production-manager')]);
        }

        $statuses = get_option('wpm_statuses', []);
        if (in_array($name, array_column($statuses, 'name'))) {
            wp_send_json_error(['message' => __('Status name already exists', 'woocommerce-production-manager')]);
        }

        $index = count($statuses);
        $statuses[] = ['name' => $name, 'color' => $color];
        update_option('wpm_statuses', $statuses);

        wp_send_json_success([
            'message' => __('Status added', 'woocommerce-production-manager'),
            'status' => ['name' => $name, 'color' => $color],
            'index' => $index
        ]);
    }

    public static function update_status() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $index = absint($_POST['index']);
        $name = sanitize_text_field($_POST['name']);
        $color = sanitize_hex_color($_POST['color']);

        if (empty($name) || empty($color)) {
            wp_send_json_error(['message' => __('Name and color are required', 'woocommerce-production-manager')]);
        }

        $statuses = get_option('wpm_statuses', []);
        if (!isset($statuses[$index])) {
            wp_send_json_error(['message' => __('Invalid status index', 'woocommerce-production-manager')]);
        }

        foreach ($statuses as $i => $status) {
            if ($i !== $index && $status['name'] === $name) {
                wp_send_json_error(['message' => __('Status name already exists', 'woocommerce-production-manager')]);
            }
        }

        $statuses[$index] = ['name' => $name, 'color' => $color];
        update_option('wpm_statuses', $statuses);

        wp_send_json_success([
            'message' => __('Status updated', 'woocommerce-production-manager'),
            'status' => ['name' => $name, 'color' => $color]
        ]);
    }

    public static function delete_status() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $index = absint($_POST['index']);
        $statuses = get_option('wpm_statuses', []);

        if (isset($statuses[$index])) {
            unset($statuses[$index]);
            $statuses = array_values($statuses);
            update_option('wpm_statuses', $statuses);
            wp_send_json_success(['message' => __('Status deleted', 'woocommerce-production-manager')]);
        }

        wp_send_json_error(['message' => __('Invalid status index', 'woocommerce-production-manager')]);
    }

    public static function reorder_statuses() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $order = array_map('absint', $_POST['order']);
        $statuses = get_option('wpm_statuses', []);
        $reordered = [];

        foreach ($order as $index) {
            if (isset($statuses[$index])) {
                $reordered[] = $statuses[$index];
            }
        }

        update_option('wpm_statuses', $reordered);
        wp_send_json_success(['message' => __('Statuses reordered', 'woocommerce-production-manager')]);
    }

    public static function update_order_item_status() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $order_id = absint($_POST['order_id']);
        $order_item_id = absint($_POST['order_item_id']);
        $status = sanitize_text_field($_POST['status']);

        if (empty($order_id) || empty($order_item_id) || empty($status)) {
            wp_send_json_error(['message' => __('Invalid input data', 'woocommerce-production-manager')]);
        }

        global $wpdb;
        $user_id = get_current_user_id();

        self::replace_order_items_status($order_id, $order_item_id , $status);

        self::log_status_change($order_item_id, $status, $user_id, __('changed from admin panel', 'woocommerce-production-manager'));
        do_action('wpm_order_item_status_changed', $order_item_id, $status);
        wp_send_json_success(['message' => __('Status updated successfully', 'woocommerce-production-manager')]);
    }

    public static function update_order_item_delivery_date() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        global $wpdb;
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_item_id = absint($_POST['order_item_id']);
        $delivery_date = sanitize_text_field($_POST['delivery_date']);

        if (empty($order_id) || empty($order_item_id) || empty($delivery_date)) {
            wp_send_json_error(['message' => __('Invalid data', 'woocommerce-production-manager')]);
        }

        $delivery_date = \WPM\Utils\PersianDate::to_gregorian($delivery_date);

        if (!\WPM\Utils\PersianDate::is_valid_date($delivery_date)) {
            wp_send_json_error(['message' => __('Invalid date format', 'woocommerce-production-manager')]);
        }

        self::replace_order_items_status($order_id, $order_item_id , null, $delivery_date);

        do_action('wpm_order_item_delivery_date_changed', $order_item_id, $delivery_date);
        wp_send_json_success(['message' => __('Delivery date updated', 'woocommerce-production-manager')]);
    }

    public static function replace_order_items_status($order_id, $order_item_id , $status = null, $delivery_date = null){
        global $wpdb;

        $table = $wpdb->prefix. 'wpm_order_items_status';

        $user_id = get_current_user_id();

        if($status){
            $updated = $wpdb->update(
                $table,
                [
                    'status' => $status,
                    'updated_by' => $user_id,
                    'updated_at' => current_time('mysql')
                ],
                ['order_item_id' => $order_item_id],
                ['%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
                wp_send_json_error(['message' => __('Failed to update status', 'woocommerce-production-manager')]);
            }

            if ($updated === 0) {
                $inserted = $wpdb->insert(
                    $table,
                    [
                        'order_item_id' => $order_item_id,
                        'order_id' => $order_id,
                        'status' => $status,
                        'updated_by' => $user_id,
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s']
                );
                if (!$inserted) {
                    wp_send_json_error(['message' => __('Failed to insert status', 'woocommerce-production-manager')]);
                }
            }
        }
        if($delivery_date){
            $updated = $wpdb->update(
                $table,
                [
                    'delivery_date' => $delivery_date,
                    'updated_by' => $user_id,
                    'updated_at' => current_time('mysql')
                ],
                ['order_item_id' => $order_item_id],
                ['%s', '%d', '%s'],
                ['%d']
            );

            if ($updated === false) {
                wp_send_json_error(['message' => __('Failed to update delivery date', 'woocommerce-production-manager')]);
            }

            if ($updated === 0) {
                $inserted = $wpdb->insert(
                    $table,
                    [
                        'order_item_id' => $order_item_id,
                        'order_id' => $order_id,
                        'delivery_date' => $delivery_date,
                        'updated_by' => $user_id,
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s']
                );
                if (!$inserted) {
                    wp_send_json_error(['message' => __('Failed to insert delivery date', 'woocommerce-production-manager')]);
                }
            }
        }
    }

    public static function log_status_change($order_item_id, $status, $user_id, $note = '') {
        global $wpdb;

        $wpdb->insert(
            "{$wpdb->prefix}wpm_status_logs",
            [
                'order_item_id' => $order_item_id,
                'status' => $status,
                'changed_by' => $user_id,
                'changed_at' => current_time('mysql'),
                'note' => $note
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );
    }

    public static function get_statuses() {
        $statuses = get_option('wpm_statuses', [['name' => __('Received', 'woocommerce-production-manager'), 'color' => '#0073aa']]);
        return array_column($statuses, 'name');
    }
}
?>