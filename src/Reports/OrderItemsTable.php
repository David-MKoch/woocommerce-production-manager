<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class OrderItemsTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Order Item', WPM_TEXT_DOMAIN),
            'plural'   => __('Order Items', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'order_customer' => __('Order / Customer', WPM_TEXT_DOMAIN),
            'order_status' => __('Order Status', WPM_TEXT_DOMAIN),
            'order_date' => __('Order Date', WPM_TEXT_DOMAIN),
            'image' => __('Image', WPM_TEXT_DOMAIN),
            'item_id' => __('Item ID', WPM_TEXT_DOMAIN),
            'item_name' => __('Item Name', WPM_TEXT_DOMAIN),
            'item_quantity' => __('Item Quantity', WPM_TEXT_DOMAIN),
            'status' => __('Status', WPM_TEXT_DOMAIN),
            'delivery_date' => __('Delivery Date', WPM_TEXT_DOMAIN)
        ];
        return apply_filters('wpm_order_items_columns', $columns);
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-order-items_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'order_customer' => ['order_id', false],
            'item_id' => ['order_item_id', false],
            'order_date' => ['order_date', false],
            'order_status' => ['order_status', false]
        ];
    }

    public function get_bulk_actions() {
        $statuses = get_option('wpm_statuses', []);
        $actions = [
            'bulk_change_delivery_date' => __('Change Delivery Date', WPM_TEXT_DOMAIN)
        ];
        foreach ($statuses as $index => $status) {
            $actions['bulk_set_status_' . $index] = sprintf(__('Set Status to %s', WPM_TEXT_DOMAIN), $status['name']);
        }
        return $actions;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'image':
                $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($item->product_id), 'thumbnail');
                $large_image_url = wp_get_attachment_image_url(get_post_thumbnail_id($item->product_id), 'medium');
                return $image_url ? '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->order_item_name) . '" class="wpm-item-image" data-large="' . esc_url($large_image_url) . '" style="max-width: 50px; height: auto;" />' : '-';
            case 'order_customer':
                $order_edit_url = admin_url('post.php?post=' . $item->order_id . '&action=edit');
                return sprintf('<a href="%s">#%d</a> %s', $order_edit_url, $item->order_id, esc_html($item->customer_name ?: __('Guest', WPM_TEXT_DOMAIN)));
            case 'order_status':
                $status_label = wc_get_order_status_name($item->order_status);
                return sprintf('<mark class="order-status status-%s"><span>%s</span></mark>', esc_attr($item->order_status), esc_html($status_label));
            case 'item_id':
                return esc_attr($item->order_item_id);
            case 'item_quantity':
                return esc_attr($item->order_item_quantity);
            case 'item_name':
                $product_edit_url = admin_url('post.php?post=' . $item->product_id . '&action=edit');
                $product_view_url = get_permalink($item->product_id);
                $actions = [];
                $item_name = sprintf(
                    '<a href="%s">%s</a>', 
                    $product_view_url, 
                    esc_html($item->order_item_name)
                );
                $actions['edit'] = sprintf('<a href="%s">%s</a>', esc_url($product_edit_url), __('Edit Product', WPM_TEXT_DOMAIN));
                return $item_name . $this->row_actions($actions);
            case 'order_date':
                return esc_html(\WPM\Utils\PersianDate::to_persian($item->order_date));
            case 'status':
                $statuses = get_option('wpm_statuses', []);
                $current_status = $item->status;
                $status_color = '';
                foreach ($statuses as $status) {
                    if ($status['name'] === $current_status) {
                        $status_color = $status['color'];
                        break;
                    }
                }
                return sprintf(
                    '<span class="wpm-status-display" style="background-color: %s; color: #fff;" data-item-id="%d" data-order-id="%d">%s</span>',
                    esc_attr($status_color ?: '#ccc'),
                    esc_attr($item->order_item_id),
                    esc_attr($item->order_id),
                    esc_html($current_status ?: __('Select Status', WPM_TEXT_DOMAIN))
                );
            case 'delivery_date':
                return sprintf(
                    '<span class="wpm-delivery-date-display" style="background-color: #ccc; color: #fff;" data-item-id="%d" data-order-id="%d">%s</span>',
                    esc_attr($item->order_item_id),
                    esc_attr($item->order_id),
                    esc_html(\WPM\Utils\PersianDate::to_persian($item->delivery_date))
                );
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="order_item_ids[]" value="%s" />',
            $item->order_item_id
        );
    }

    public function prepare_items() {
        global $wpdb;

        // استفاده از screen option برای تعداد آیتم‌ها در هر صفحه
		$per_page = $this->get_items_per_page('wpm_order_items_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        if (isset($_GET['status']) && $_GET['status']) {
            $where[] = 's.status = %s';
            $params[] = sanitize_text_field($_GET['status']);
        }
        if (isset($_GET['order_id']) && $_GET['order_id']) {
            $where[] = 'o.id = %d';
            $params[] = absint($_GET['order_id']);
        }
        if (isset($_GET['customer_name']) && $_GET['customer_name']) {
            $where[] = '(u.display_name LIKE %s OR u.user_login LIKE %s)';
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_GET['customer_name'])) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'o.date_created_gmt >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'o.date_created_gmt <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }

        $allowed_statuses = get_option('wpm_allowed_order_statuses', array_keys(wc_get_order_statuses()));
        if (!empty($allowed_statuses)) {
            $placeholders = implode(',', array_fill(0, count($allowed_statuses), '%s'));
            $where[] = "o.status IN ($placeholders)";
            $params = array_merge($params, $allowed_statuses);
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'o.date_created_gmt';
        $order = !empty($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC';
        
        switch ($orderby) {
            case 'order_id':
                $orderby_sql = 'o.id';
                break;
            case 'order_item_id':
                $orderby_sql = 'oi.order_item_id';
                break;
            case 'order_date':
                $orderby_sql = 'o.date_created_gmt';
                break;
            case 'order_status':
                $orderby_sql = 'o.status';
                break;
            default:
                $orderby_sql = 'o.date_created_gmt';
        }

        $query = $wpdb->prepare("
            SELECT s.*, o.date_created_gmt as order_date, oi.order_item_name, oim.meta_value as product_id, oim2.meta_value as order_item_quantity,
                   u.display_name as customer_name, oi.order_item_id, o.id as order_id, o.status as order_status
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_qty'
            LEFT JOIN {$wpdb->prefix}users u ON o.customer_id = u.ID
            LEFT JOIN {$wpdb->prefix}wpm_order_items_status s ON s.order_item_id = oi.order_item_id
            $where_sql
            ORDER BY $orderby_sql $order
            LIMIT %d OFFSET %d
        ", array_merge($params, [$per_page, $offset]));

        $this->items = $wpdb->get_results($query);

        $total_items = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT oi.order_item_id)
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}users u ON o.customer_id = u.ID
            LEFT JOIN {$wpdb->prefix}wpm_order_items_status s ON s.order_item_id = oi.order_item_id
            $where_sql
        ", $params));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        if ($this->current_action()) {
            $this->process_bulk_action();
        }
    }

    public function process_bulk_action() {
        global $wpdb;

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', WPM_TEXT_DOMAIN));
        }

        $action = $this->current_action();
        $order_item_ids = isset($_REQUEST['order_item_ids']) ? array_map('absint', $_REQUEST['order_item_ids']) : [];

        if (empty($order_item_ids)) {
            return;
        }

        if (strpos($action, 'bulk_set_status_') === 0) {
            $statuses = get_option('wpm_statuses', []);
            $status_index = str_replace('bulk_set_status_', '', $action);
            if(isset($statuses[$status_index])){
                foreach ($order_item_ids as $item_id) {
                    $wpdb->update(
                        "{$wpdb->prefix}wpm_order_items_status",
                        [
                            'status' => $statuses[$status_index],
                            'updated_by' => get_current_user_id(),
                            'updated_at' => current_time('mysql')
                        ],
                        ['order_item_id' => $item_id],
                        ['%s', '%d', '%s'],
                        ['%d']
                    );
                }
				add_settings_error('wpm_order_items', 'bulk_action', __('Statuses updated.', WPM_TEXT_DOMAIN), 'updated');
            }
        } elseif ($action === 'bulk_change_delivery_date') {
            $delivery_date = isset($_REQUEST['bulk_delivery_date']) ? \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_REQUEST['bulk_delivery_date'])) : '';
            if ($delivery_date && \WPM\Utils\PersianDate::is_valid_date($delivery_date)) {
                foreach ($order_item_ids as $item_id) {
                    $wpdb->update(
                        "{$wpdb->prefix}wpm_order_items_status",
                        [
                            'delivery_date' => $delivery_date,
                            'updated_by' => get_current_user_id(),
                            'updated_at' => current_time('mysql')
                        ],
                        ['order_item_id' => $item_id],
                        ['%s', '%d', '%s'],
                        ['%d']
                    );
                }
				add_settings_error('wpm_order_items', 'bulk_action', __('Delivery dates updated.', WPM_TEXT_DOMAIN), 'updated');
            }
        }

        wp_redirect(add_query_arg('page', 'wpm-order-items', admin_url('admin.php')));
        exit;
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <label for="bulk_delivery_date" class="screen-reader-text"><?php esc_html_e('Bulk Delivery Date', WPM_TEXT_DOMAIN); ?></label>
                <input type="text" id="bulk_delivery_date" name="bulk_delivery_date" class="persian-datepicker" placeholder="<?php esc_attr_e('Select Date', WPM_TEXT_DOMAIN); ?>">
                <input type="submit" name="doaction" class="button action" value="<?php esc_attr_e('Apply', WPM_TEXT_DOMAIN); ?>">
            </div>
            <?php
        }
    }
}
?>