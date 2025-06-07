<?php
namespace WPM\Reports;

use WP_List_Table;

defined('ABSPATH') || exit;

class CategoryOrdersTable extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => __('Category Reservation', 'woocommerce-production-manager'),
            'plural'   => __('Category Reservations', 'woocommerce-production-manager'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'category_name' => __('Category', 'woocommerce-production-manager'),
            'max_capacity' => __('Production Capacity', 'woocommerce-production-manager'),
            'reserved_count' => __('Reserved Count', 'woocommerce-production-manager'),
            'capacity_usage' => __('Capacity Usage', 'woocommerce-production-manager'),
            'reservation_date' => __('Reservation Date', 'woocommerce-production-manager')
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
                    return esc_html__('Total Reservations', 'woocommerce-production-manager');
                }
                $term = get_term($item->term_id, 'product_cat');
                if (is_wp_error($term) || !$term) {
                    return esc_html($item->category_name);
                }
                $category_url = get_term_link($item->term_id, 'product_cat');
                if (is_wp_error($category_url)) {
                    return esc_html($item->category_name);
                }
                $edit_url = admin_url('term.php?taxonomy=product_cat&tag_ID=' . $item->term_id);
                $category_name = sprintf('<a href="%s">%s</a>', esc_url($category_url), esc_html($item->category_name));
                $actions = [
                    'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit Category', 'woocommerce-production-manager'))
                ];
                return $category_name . $this->row_actions($actions);
            case 'max_capacity':
                return $item->term_id == 0 ? esc_html__('-', 'woocommerce-production-manager') : esc_html($item->max_capacity ?: __('No Limit', 'woocommerce-production-manager'));
            case 'reserved_count':
                return esc_html($item->reserved_count);
            case 'capacity_usage':
                if ($item->max_capacity) {
                    $percentage = round(($item->reserved_count / $item->max_capacity) * 100, 2);
                    return '<div class="wpm-capacity-bar"><div class="wpm-capacity-progress" style="width:' . esc_attr($percentage) . '%;"></div></div> ' . esc_html($percentage . '%');
                }
                return '-';
            case 'reservation_date':
                $report_url = add_query_arg([
                    'page' => 'wpm-reports',
                    'section' => 'reserved-products',
                    'date' => urlencode($item->reservation_date),
                    'category_id' => absint($item->term_id)
                ], admin_url('admin.php'));
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
            '<input type="checkbox" name="category_ids[]" value="%d" />',
            $item->term_id
        );
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            $selected_category = isset($_GET['category_id']) ? absint($_GET['category_id']) : 0;
            ?>
            <div class="alignleft actions">
                <select name="category_id">
                    <option value="0"><?php esc_html_e('All Categories', 'woocommerce-production-manager'); ?></option>
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
                <?php submit_button(__('Filter', 'woocommerce-production-manager'), '', 'filter_action', false); ?>
            </div>
            <?php
        }
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

        $category_filter = '';
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $category_ids = $this->get_child_term_ids(absint($_GET['category_id']));
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $category_filter = "AND tt.term_id IN ($placeholders)";
            $params = array_merge($params, $category_ids);
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $union_params = array_merge($params, $params, [$per_page, $offset]);

        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
            $where_clause
            $category_filter
            AND pc.entity_type = 'category'
            GROUP BY pc.entity_id, cc.date
            UNION
            SELECT 0 as term_id, 'Total Reservations' as category_name, NULL as max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
            $where_clause
            AND cc.entity_type IN ('product', 'variation')
            GROUP BY cc.date
            ORDER BY reservation_date DESC
            LIMIT %d OFFSET %d
        ", $union_params);

        $this->items = $wpdb->get_results($query);

        $total_items_query = $wpdb->prepare("
            SELECT COUNT(*) FROM (
                SELECT pc.entity_id, cc.date
                FROM {$wpdb->prefix}wpm_production_capacity pc
                JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
                JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'product_cat'
                JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
                JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
                $where_clause
                $category_filter
                AND pc.entity_type = 'category'
                GROUP BY pc.entity_id, cc.date
                UNION
                SELECT 0 as term_id, cc.date
                FROM {$wpdb->prefix}wpm_capacity_count cc
                JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
                $where_clause
                AND cc.entity_type IN ('product', 'variation')
                GROUP BY cc.date
            ) as combined
        ", $params);

        $total_items = $wpdb->get_var($total_items_query);
        $total_items = absint($total_items ?: 0);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
}