<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class CategoryOrdersTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Category Reservation', WPM_TEXT_DOMAIN),
            'plural'   => __('Category Reservations', WPM_TEXT_DOMAIN),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'category_name' => __('Category', WPM_TEXT_DOMAIN),
            'max_capacity' => __('Production Capacity', WPM_TEXT_DOMAIN),
            'reserved_count' => __('Reserved Count', WPM_TEXT_DOMAIN),
			'capacity_usage' => __('Capacity Usage', WPM_TEXT_DOMAIN),
            'reservation_date' => __('Reservation Date', WPM_TEXT_DOMAIN)
        ];
    }

    public function get_hidden_columns() {
        return get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reports_columnshidden', true) ?: [];
    }

    public function get_sortable_columns() {
        return [
            'max_capacity' => ['max_capacity', false],
            'reserved_count' => ['reserved_count', false],
            'reservation_date' => ['reservation_date', false]
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'category_name':
                if ($item->term_id == 0) {
                    return esc_html__('Other Products', WPM_TEXT_DOMAIN);
                }
                $term = get_term($item->term_id, 'product_cat');
                $category_url = get_term_link($item->term_id, 'product_cat');
                $edit_url = admin_url('term.php?taxonomy=product_cat&tag_ID=' . $item->term_id);
                $category_name = sprintf('<a href="%s">%s</a>', esc_url($category_url), esc_html($item->category_name));
                $actions = [
                    'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit Category', WPM_TEXT_DOMAIN))
                ];
                return $category_name . $this->row_actions($actions);
            case 'max_capacity':
                return $item->term_id == 0 ? esc_html__('-', WPM_TEXT_DOMAIN) : esc_html($item->max_capacity ?: __('No Limit', WPM_TEXT_DOMAIN));
            case 'reserved_count':
                return esc_html($item->reserved_count);
			case 'capacity_usage':
                if ($item->max_capacity) {
                    $percentage = round(($item->reserved_count / $item->max_capacity) * 100, 2);
                    return '<div class="wpm-capacity-bar"><div class="wpm-capacity-progress" style="width:' . esc_attr($percentage) . '%;"></div></div> ' . esc_html($percentage . '%');
                }
                return '-';
            case 'reservation_date':
                $report_url = admin_url('admin.php?page=wpm-reserved-products&date=' . urlencode($item->reservation_date));
                return sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($report_url),
                    esc_html(\WPM\Utils\PersianDate::to_persian($item->reservation_date))
                );
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

	public function extra_tablenav($which) {
        if ($which === 'top') {
            $selected_category = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
            ?>
            <div class="alignleft actions">
                <select name="category_id">
                    <option value="0"><?php esc_html_e('All Categories', WPM_TEXT_DOMAIN); ?></option>
                    <?php
                    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                    foreach ($categories as $category) {
                        printf(
                            '<option value="%d" %s>%s</option>',
                            esc_attr($category->term_id),
                            selected($selected_category, $category->term_id, false),
                            esc_html($category->name)
                        );
                    }
                    ?>
                </select>
                <?php submit_button(__('Filter', WPM_TEXT_DOMAIN), '', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
	
    public function prepare_items() {
        global $wpdb;

        $per_page = get_user_option('wpm_category_orders_per_page') ?: 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $where = [];
        $params = [];

        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'cc.date >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'cc.date <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }
		if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $where[] = 'pc.entity_id = %d';
            $params[] = absint($_GET['category_id']);
        }

        // Prepare WHERE clause
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $union_params = array_merge($params, $params, [$per_page, $offset]); // Duplicate params for UNION

        // Main query with UNION
        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
            $where_sql
            AND pc.entity_type = 'category'
            GROUP BY pc.entity_id, cc.date
            UNION
            SELECT 0 as term_id, 'Other Products' as category_name, NULL as max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
            LEFT JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            LEFT JOIN {$wpdb->prefix}wpm_production_capacity pc ON tt.term_id = pc.entity_id AND pc.entity_type = 'category'
            $where_sql
            AND pc.entity_id IS NULL
            GROUP BY cc.date
            ORDER BY reservation_date DESC
            LIMIT %d OFFSET %d
        ", $union_params);

        $this->items = $wpdb->get_results($query);

        // Total items query
        $total_items_query = $wpdb->prepare("
            SELECT COUNT(*) FROM (
                SELECT pc.entity_id, cc.date
                FROM {$wpdb->prefix}wpm_production_capacity pc
                JOIN {$wpdb->prefix}term_taxonomy tt ON pc.entity_id = tt.term_id AND tt.taxonomy = 'product_cat'
                JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
                JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
                $where_sql
                AND pc.entity_type = 'category'
                GROUP BY pc.entity_id, cc.date
                UNION
                SELECT 0 as term_id, cc.date
                FROM {$wpdb->prefix}wpm_capacity_count cc
                JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
                LEFT JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
                LEFT JOIN {$wpdb->prefix}wpm_production_capacity pc ON tt.term_id = pc.entity_id AND pc.entity_type = 'category'
                $where_sql
                AND pc.entity_id IS NULL
                GROUP BY cc.date
            ) as combined
        ", $params);

        $total_items = $wpdb->get_var($total_items_query);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}