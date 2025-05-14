<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class FullCapacityTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Full Capacity Day', WPM_TEXT_DOMAIN),
            'plural'   => __('Full Capacity Days', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'image' => __('Image', WPM_TEXT_DOMAIN),
            'date' => __('Date', WPM_TEXT_DOMAIN),
            'entity_type' => __('Entity Type', WPM_TEXT_DOMAIN),
            'entity_name' => __('Entity Name', WPM_TEXT_DOMAIN),
            'capacity' => __('Capacity', WPM_TEXT_DOMAIN)
        ];
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reports_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'date' => ['date', false]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'image':
                if ($item->entity_type === 'category') {
                    $products = get_posts([
                        'post_type' => 'product',
                        'posts_per_page' => 1,
                        'tax_query' => [
                            [
                                'taxonomy' => 'product_cat',
                                'field' => 'term_id',
                                'terms' => $item->entity_id
                            ]
                        ]
                    ]);
                    if ($products) {
                        $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($products[0]->ID), 'thumbnail');
                        return $image_url ? '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->entity_name) . '" style="max-width: 50px; height: auto;" />' : '-';
                    }
                } elseif ($item->entity_type === 'product' || $item->entity_type === 'variation') {
                    $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($item->entity_id), 'thumbnail');
                    return $image_url ? '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->entity_name) . '" style="max-width: 50px; height: auto;" />' : '-';
                }
                return '-';
            case 'date':
                return esc_html(\WPM\Utils\PersianDate::to_persian($item->date));
            case 'entity_type':
                return esc_html(ucfirst($item->entity_type));
            case 'entity_name':
                return esc_html($item->entity_name);
            case 'capacity':
                return esc_html($item->reserved_count . '/' . $item->max_capacity);
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="capacity_ids[]" value="%s" />',
            $item->id
        );
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = get_user_option('wpm_full_capacity_per_page') ?: 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'c.date >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'c.date <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }
        $where[] = 'c.reserved_count >= pc.max_capacity';

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT c.id, c.date, c.entity_type, c.entity_id, c.reserved_count, pc.max_capacity
            FROM {$wpdb->prefix}wpm_capacity_count c
            JOIN {$wpdb->prefix}wpm_production_capacity pc ON c.entity_type = pc.entity_type AND c.entity_id = pc.entity_id
            $where_sql
            ORDER BY c.date DESC
            LIMIT %d OFFSET %d
        ", array_merge($params, [$per_page, $offset]));

        $results = $wpdb->get_results($query);

        $this->items = array_map(function($row) {
            $row->entity_name = '';
            if ($row->entity_type === 'category') {
                $term = get_term($row->entity_id, 'product_cat');
                $row->entity_name = $term->name;
            } elseif ($row->entity_type === 'product' || $row->entity_type === 'variation') {
                $row->entity_name = get_the_title($row->entity_id);
            }
            return $row;
        }, $results);

        $total_items = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(c.id)
            FROM {$wpdb->prefix}wpm_capacity_count c
            JOIN {$wpdb->prefix}wpm_production_capacity pc ON c.entity_type = pc.entity_type AND c.entity_id = pc.entity_id
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