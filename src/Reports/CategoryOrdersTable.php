<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class CategoryOrdersTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Category Order', WPM_TEXT_DOMAIN),
            'plural'   => __('Category Orders', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'image' => __('Image', WPM_TEXT_DOMAIN),
            'category_name' => __('Category', WPM_TEXT_DOMAIN),
            'order_count' => __('Order Count', WPM_TEXT_DOMAIN),
            'item_count' => __('Item Count', WPM_TEXT_DOMAIN)
        ];
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reports_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'order_count' => ['order_count', false],
            'item_count' => ['item_count', false]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'image':
                // Get the first product in the category for thumbnail
                $products = get_posts([
                    'post_type' => 'product',
                    'posts_per_page' => 1,
                    'tax_query' => [
                        [
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => $item->term_id
                        ]
                    ]
                ]);
                if ($products) {
                    $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($products[0]->ID), 'thumbnail');
                    return $image_url ? '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->category_name) . '" style="max-width: 50px; height: auto;" />' : '-';
                }
                return '-';
            case 'category_name':
                return esc_html($item->category_name);
            case 'order_count':
                return esc_html($item->order_count);
            case 'item_count':
                return esc_html($item->item_count);
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="category_ids[]" value="%s" />',
            $item->term_id
        );
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = get_user_option('wpm_category_orders_per_page') ?: 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'o.date_created_gmt >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'o.date_created_gmt <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT t.term_id, t.name as category_name, COUNT(DISTINCT oi.order_id) as order_count, SUM(CAST(oim1.meta_value AS UNSIGNED)) as item_count
            FROM {$wpdb->prefix}woocommerce_order_items oi 
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim1 ON oi.order_item_id = oim1.order_item_id AND oim1.meta_key = '_qty'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_product_id'
            JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            JOIN {$wpdb->prefix}term_relationships tr ON oim2.meta_value = tr.object_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}terms t ON tr.term_taxonomy_id = t.term_id
            $where_sql
            GROUP BY t.term_id
            ORDER BY order_count DESC
            LIMIT %d OFFSET %d
        ", array_merge($params, [$per_page, $offset]));

        $this->items = $wpdb->get_results($query);

        $total_items = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT t.term_id)
            FROM {$wpdb->prefix}woocommerce_order_items oi 
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim1 ON oi.order_item_id = oim1.order_item_id AND oim1.meta_key = '_qty'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_product_id'
            JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            JOIN {$wpdb->prefix}term_relationships tr ON oim2.meta_value = tr.object_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}terms t ON tr.term_taxonomy_id = t.term_id
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