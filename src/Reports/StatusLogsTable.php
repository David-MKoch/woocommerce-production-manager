<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class StatusLogsTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Status Log', WPM_TEXT_DOMAIN),
            'plural'   => __('Status Logs', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'order_item_id' => __('Order Item ID', WPM_TEXT_DOMAIN),
            'status' => __('Status', WPM_TEXT_DOMAIN),
            'changed_by' => __('Changed By', WPM_TEXT_DOMAIN),
            'changed_at' => __('Changed At', WPM_TEXT_DOMAIN),
            'note' => __('Note', WPM_TEXT_DOMAIN)
        ];
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-status-logs_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'order_item_id' => ['order_item_id', false],
            'changed_at' => ['changed_at', false]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'order_item_id':
                return esc_html($item->order_item_id);
            case 'status':
                return esc_html($item->status);
            case 'changed_by':
                return esc_html($item->changed_by_name ?: __('Unknown', WPM_TEXT_DOMAIN));
            case 'changed_at':
                return esc_html(\WPM\Utils\PersianDate::to_persian($item->changed_at, 'Y/m/d H:i'));
            case 'note':
                return esc_html($item->note);
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%s" />',
            $item->id
        );
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = get_user_option('wpm_status_logs_per_page') ?: 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        if (isset($_GET['order_item_id']) && $_GET['order_item_id']) {
            $where[] = 'l.order_item_id = %d';
            $params[] = absint($_GET['order_item_id']);
        }
        if (isset($_GET['changed_by']) && $_GET['changed_by']) {
            $where[] = 'l.changed_by = %d';
            $params[] = absint($_GET['changed_by']);
        }
        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'l.changed_at >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'l.changed_at <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT l.*, u.display_name as changed_by_name
            FROM {$wpdb->prefix}wpm_status_logs l
            LEFT JOIN {$wpdb->prefix}users u ON l.changed_by = u.ID
            $where_sql
            ORDER BY l.changed_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($params, [$per_page, $offset]));

        $this->items = $wpdb->get_results($query);

        $total_items = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(l.id)
            FROM {$wpdb->prefix}wpm_status_logs l
            LEFT JOIN {$wpdb->prefix}users u ON l.changed_by = u.ID
            $where_sql
        ", $params));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}
?>