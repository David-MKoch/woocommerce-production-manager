<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class ReservedProductsTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Reserved Product', WPM_TEXT_DOMAIN),
            'plural'   => __('Reserved Products', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'image' => __('Image', WPM_TEXT_DOMAIN),
            'product_name' => __('Product Name', WPM_TEXT_DOMAIN),
            'product_id' => __('Product ID', WPM_TEXT_DOMAIN),
            'reserved_count' => __('Reserved Count', WPM_TEXT_DOMAIN),
            'reservation_date' => __('Reservation Date', WPM_TEXT_DOMAIN)
        ];
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reserved-products_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'product_name' => ['product_name', false],
            'reserved_count' => ['reserved_count', false],
            'reservation_date' => ['reservation_date', false]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'image':
                $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($item->entity_id), 'thumbnail');
                return $image_url ? '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($item->product_name) . '" style="max-width: 50px; height: auto;" />' : '-';
            case 'product_name':
                $edit_url = admin_url('post.php?post=' . $item->entity_id . '&action=edit');
                $product_name = sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html($item->product_name));
                return $product_name;
            case 'product_id':
                return esc_html($item->entity_id);
            case 'reserved_count':
                return esc_html($item->reserved_count);
            case 'reservation_date':
                return esc_html(\WPM\Utils\PersianDate::to_persian($item->reservation_date));
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="product_ids[]" value="%s" />',
            $item->entity_id
        );
    }
	
	private function get_child_term_ids($term_id) {
        $term_ids = [$term_id];
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $term_id,
            'hide_empty' => false,
            'fields' => 'ids'
        ]);
        if (!is_wp_error($children)) {
            foreach ($children as $child_id) {
                $term_ids = array_merge($term_ids, $this->get_child_term_ids($child_id));
            }
        }
        return array_unique($term_ids);
    }

    public function prepare_items() {
        global $wpdb;

        $per_page = get_user_option('wpm_reserved_products_per_page') ?: 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        $reservation_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        if ($reservation_date) {
            $where[] = 'cc.date = %s';
            $params[] = $reservation_date;
        } else {
            wp_die(__('Invalid reservation date.', WPM_TEXT_DOMAIN));
        }
		if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_query = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(p.post_title LIKE %s OR cc.entity_id = %s)';
            $params[] = $search_query;
            $params[] = absint($_GET['s']);
        }
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $category_ids = $this->get_child_term_ids(absint($_GET['category_id']));
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $where[] = "tr.term_id IN ($placeholders)";
            $params = array_merge($params, $category_ids);
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$join_category = isset($_GET['category_id']) && absint($_GET['category_id']) > 0 ?
            "JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id" : '';

        $query = $wpdb->prepare("
            SELECT cc.entity_id, p.post_title as product_name, cc.reserved_count, cc.date as reservation_date
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID
			$join_category
            $where_sql
            AND cc.entity_type IN ('product', 'variation')
            ORDER BY cc.reserved_count DESC
            LIMIT %d OFFSET %d
        ", array_merge($params, [$per_page, $offset]));

        $this->items = $wpdb->get_results($query);

        $total_items_query = $wpdb->prepare("
            SELECT COUNT(DISTINCT cc.entity_id)
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID
            $join_category
            $where_sql
            AND cc.entity_type IN ('product', 'variation')
        ", $params);

        $total_items = $wpdb->get_var($total_items_query);
        $total_items = absint($total_items ?: 0); // Ensure total_items is an integer

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}