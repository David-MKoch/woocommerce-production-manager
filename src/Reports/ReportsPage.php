<?php
namespace WPM\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('ABSPATH') || exit;

class ReportsPage {
    public static function render_page() {
        $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'status_logs';

        if ($section === 'reserved-products') {
            // Render Reserved Products page
            $table = \WPM\Reports\AdminPage::$table;
            if (!$table) {
                $table = new ReservedProductsTable(); // fallback if not set
            }
            
            $table->prepare_items();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Reserved Products', 'woocommerce-production-manager'); ?></h1>
                <?php if ($table->items) : ?>
                    <form method="get">
                        <input type="hidden" name="page" value="wpm-reports">
                        <input type="hidden" name="section" value="reserved-products">
                        <input type="hidden" name="date" value="<?php echo esc_attr(isset($_GET['date']) ? sanitize_text_field($_GET['date']) : ''); ?>">
                        <input type="hidden" name="category_id" value="<?php echo esc_attr(isset($_GET['category_id']) ? absint($_GET['category_id']) : 0); ?>">
                        <div class="wpm-filters">
                            <?php $table->search_box(__('Search Products', 'woocommerce-production-manager'), 'product-search'); ?>
                            <button type="submit" class="button"><?php esc_html_e('Filter', 'woocommerce-production-manager'); ?></button>
                            <button type="button" class="button wpm-export-reserved-products-csv"><?php esc_html_e('Export to CSV', 'woocommerce-production-manager'); ?></button>
                            <button type="button" class="button wpm-export-reserved-products-excel"><?php esc_html_e('Export to Excel', 'woocommerce-production-manager'); ?></button>
                        </div>
                    </form>
                    <form method="post">
                        <?php $table->display(); ?>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e('No reserved products found for the specified date or category.', 'woocommerce-production-manager'); ?></p>
                <?php endif; ?>
            </div>
            <?php
        } else {
            // Render Category Reservations or Status Logs
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Reports', 'woocommerce-production-manager'); ?></h1>
                <h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url(add_query_arg('tab', 'status_logs')); ?>" class="nav-tab <?php echo $tab === 'status_logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Status Logs', 'woocommerce-production-manager'); ?></a>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'category')); ?>" class="nav-tab <?php echo $tab === 'category' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Category Orders', 'woocommerce-production-manager'); ?></a>
                </h2>
                <?php if ($tab === 'category') : ?>
                    <?php
                    $table = \WPM\Reports\AdminPage::$table;
                    if (!$table) {
                        $table = new CategoryOrdersTable(); // fallback if not set
                    }
                    
                    $table->prepare_items();
                    ?>
                    <?php if ($table->items) : ?>
                        <form method="get">
                            <input type="hidden" name="page" value="wpm-reports">
                            <input type="hidden" name="tab" value="category">
                            <div class="wpm-filters">
                                <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                                <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                                <button type="submit" class="button"><?php esc_html_e('Filter', 'woocommerce-production-manager'); ?></button>
                                <button type="button" class="button wpm-export-category-orders-csv"><?php esc_html_e('Export to CSV', 'woocommerce-production-manager'); ?></button>
                                <button type="button" class="button wpm-export-category-orders-excel"><?php esc_html_e('Export to Excel', 'woocommerce-production-manager'); ?></button>
                            </div>
                        </form>
                        <form method="post">
                            <?php $table->display(); ?>
                        </form>
                    <?php else : ?>
                        <p><?php esc_html_e('No category reservations found for the specified filters.', 'woocommerce-production-manager'); ?></p>
                    <?php endif; ?>
                <?php elseif ($tab === 'status_logs') : ?>
                    <?php
                    $table = \WPM\Reports\AdminPage::$table;
                    if (!$table) {
                        $table = new StatusLogsTable(); // fallback if not set
                    }
                    
                    $table->prepare_items();
                    ?>
                    <?php if ($table->items) : ?>
                        <form method="get">
                            <input type="hidden" name="page" value="wpm-reports">
                            <input type="hidden" name="tab" value="status_logs">
                            <div class="wpm-filters">
                                <input type="text" name="order_item_id" placeholder="<?php esc_attr_e('Order Item ID', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['order_item_id'] ?? ''); ?>">
                                <input type="text" name="changed_by" placeholder="<?php esc_attr_e('Changed By (User ID)', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['changed_by'] ?? ''); ?>">
                                <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                                <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', 'woocommerce-production-manager'); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                                <button type="submit" class="button"><?php esc_html_e('Filter', 'woocommerce-production-manager'); ?></button>
                                <button type="button" class="button wpm-export-status-logs-csv"><?php esc_html_e('Export to CSV', 'woocommerce-production-manager'); ?></button>
                                <button type="button" class="button wpm-export-status-logs-excel"><?php esc_html_e('Export to Excel', 'woocommerce-production-manager'); ?></button>
                            </div>
                        </form>
                        <form method="post">
                            <?php $table->display(); ?>
                        </form>
                    <?php else : ?>
                        <p><?php esc_html_e('No status logs found for the specified filters.', 'woocommerce-production-manager'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    public static function export_category_orders_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        global $wpdb;

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
            $category_ids = (new CategoryOrdersTable())->get_child_term_ids(absint($_GET['category_id']));
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $category_filter = "AND tt.term_id IN ($placeholders)";
            $params = array_merge($params, $category_ids);
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
            $where_sql
            $category_filter
            AND pc.entity_type = 'category'
            GROUP BY pc.entity_id, cc.date
            UNION
            SELECT 0 as term_id, 'Total Reservations' as category_name, NULL as max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
            $where_sql
            AND cc.entity_type IN ('product', 'variation')
            GROUP BY cc.date
            ORDER BY reservation_date DESC
        ", $params);

        $items = $wpdb->get_results($query);

        $filename = 'category-orders-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        fputcsv($file, [
            __('Category', 'woocommerce-production-manager'),
            __('Production Capacity', 'woocommerce-production-manager'),
            __('Reserved Count', 'woocommerce-production-manager'),
            __('Reservation Date', 'woocommerce-production-manager')
        ]);

        foreach ($items as $item) {
            fputcsv($file, [
                $item->category_name,
                $item->max_capacity ?: __('No Limit', 'woocommerce-production-manager'),
                $item->reserved_count,
                \WPM\Utils\PersianDate::to_persian($item->reservation_date)
            ]);
        }

        fclose($file);
        wp_send_json_success(['url' => $upload_dir['baseurl'] . '/' . $filename]);
    }

    public static function export_category_orders_excel() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'woocommerce-production-manager'));
        }

        global $wpdb;

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
            $category_ids = (new CategoryOrdersTable())->get_child_term_ids(absint($_GET['category_id']));
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $category_filter = "AND tt.term_id IN ($placeholders)";
            $params = array_merge($params, $category_ids);
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id AND tt.taxonomy = 'product_cat'
            JOIN {$wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            JOIN {$wpdb->prefix}posts p ON tr.object_id = p.ID AND p.post_type IN ('product', 'product_variation')
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON p.ID = cc.entity_id AND cc.entity_type IN ('product', 'variation')
            $where_sql
            $category_filter
            AND pc.entity_type = 'category'
            GROUP BY pc.entity_id, cc.date
            UNION
            SELECT 0 as term_id, 'Total Reservations' as category_name, NULL as max_capacity, cc.date as reservation_date, SUM(cc.reserved_count) as reserved_count
            FROM {$wpdb->prefix}wpm_capacity_count cc
            JOIN {$wpdb->prefix}posts p ON cc.entity_id = p.ID AND p.post_type IN ('product', 'product_variation')
            $where_sql
            AND cc.entity_type IN ('product', 'variation')
            GROUP BY cc.date
            ORDER BY reservation_date DESC
        ", $params);

        $items = $wpdb->get_results($query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Category', 'woocommerce-production-manager'),
            __('Production Capacity', 'woocommerce-production-manager'),
            __('Reserved Count', 'woocommerce-production-manager'),
            __('Reservation Date', 'woocommerce-production-manager')
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue("A$row", $item->category_name);
            $sheet->setCellValue("B$row", $item->max_capacity ?: __('No Limit', 'woocommerce-production-manager'));
            $sheet->setCellValue("C$row", $item->reserved_count);
            $sheet->setCellValue("D$row", \WPM\Utils\PersianDate::to_persian($item->reservation_date));
            $row++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'category-orders-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
	
	public static function export_reserved_products_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        global $wpdb;

        $reservation_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $where = [];
        $params = [];

        if ($reservation_date) {
            $where[] = 'cc.date = %s';
            $params[] = $reservation_date;
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_query = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(p.post_title LIKE %s OR cc.entity_id = %s)';
            $params[] = $search_query;
            $params[] = absint($_GET['s']);
        }
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $category_ids = (new ReservedProductsTable())->get_child_term_ids(absint($_GET['category_id']));
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
        ", $params);

        $items = $wpdb->get_results($query);

        $filename = 'reserved-products-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        fputcsv($file, [
            __('Product Name', 'woocommerce-production-manager'),
            __('Product ID', 'woocommerce-production-manager'),
            __('Reserved Count', 'woocommerce-production-manager'),
            __('Reservation Date', 'woocommerce-production-manager')
        ]);

        foreach ($items as $item) {
            fputcsv($file, [
                $item->product_name,
                $item->entity_id,
                $item->reserved_count,
                \WPM\Utils\PersianDate::to_persian($item->reservation_date)
            ]);
        }

        fclose($file);
        wp_send_json_success(['url' => $upload_dir['baseurl'] . '/' . $filename]);
    }

    public static function export_reserved_products_excel() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'woocommerce-production-manager'));
        }

        global $wpdb;

        $reservation_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $where = [];
        $params = [];

        if ($reservation_date) {
            $where[] = 'cc.date = %s';
            $params[] = $reservation_date;
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_query = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(p.post_title LIKE %s OR cc.entity_id = %s)';
            $params[] = $search_query;
            $params[] = absint($_GET['s']);
        }
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $category_ids = (new ReservedProductsTable())->get_child_term_ids(absint($_GET['category_id']));
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
        ", $params);

        $items = $wpdb->get_results($query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Product Name', 'woocommerce-production-manager'),
            __('Product ID', 'woocommerce-production-manager'),
            __('Reserved Count', 'woocommerce-production-manager'),
            __('Reservation Date', 'woocommerce-production-manager')
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue("A$row", $item->product_name);
            $sheet->setCellValue("B$row", $item->entity_id);
            $sheet->setCellValue("C$row", $item->reserved_count);
            $sheet->setCellValue("D$row", \WPM\Utils\PersianDate::to_persian($item->reservation_date));
            $row++;
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'reserved-products-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public static function export_status_logs_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        global $wpdb;

        $where = [];
        $params = [];

        if (isset($_GET['order_item_id']) && $_GET['order_item_id']) {
            $where[] = 'order_item_id = %d';
            $params[] = absint($_GET['order_item_id']);
        }
        if (isset($_GET['changed_by']) && $_GET['changed_by']) {
            $where[] = 'changed_by = %d';
            $params[] = absint($_GET['changed_by']);
        }
        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'changed_at >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'changed_at <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT l.*, u.display_name as changed_by_name
            FROM {$wpdb->prefix}wpm_status_logs l
            LEFT JOIN {$wpdb->prefix}users u ON l.changed_by = u.ID
            $where_sql
            ORDER BY l.changed_at DESC
        ", $params);

        $logs = $wpdb->get_results($query);

        $filename = 'status-logs-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        fputcsv($file, [
            __('Order Item ID', 'woocommerce-production-manager'),
            __('Status', 'woocommerce-production-manager'),
            __('Changed By', 'woocommerce-production-manager'),
            __('Changed At', 'woocommerce-production-manager'),
            __('Note', 'woocommerce-production-manager')
        ]);

        foreach ($logs as $log) {
            fputcsv($file, [
                $log->order_item_id,
                $log->status,
                $log->changed_by_name ?: __('Unknown', 'woocommerce-production-manager'),
                \WPM\Utils\PersianDate::to_persian($log->changed_at, 'Y/m/d H:i'),
                $log->note
            ]);
        }

        fclose($file);
        wp_send_json_success(['url' => $upload_dir['baseurl'] . '/' . $filename]);
    }

    public static function export_status_logs_excel() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', 'woocommerce-production-manager'));
        }

        global $wpdb;

        $where = [];
        $params = [];

        if (isset($_GET['order_item_id']) && $_GET['order_item_id']) {
            $where[] = 'order_item_id = %d';
            $params[] = absint($_GET['order_item_id']);
        }
        if (isset($_GET['changed_by']) && $_GET['changed_by']) {
            $where[] = 'changed_by = %d';
            $params[] = absint($_GET['changed_by']);
        }
        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'changed_at >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_from']));
        }
        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'changed_at <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian(sanitize_text_field($_GET['date_to']));
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT l.*, u.display_name as changed_by_name
            FROM {$wpdb->prefix}wpm_status_logs l
            LEFT JOIN {$wpdb->prefix}users u ON l.changed_by = u.ID
            $where_sql
            ORDER BY l.changed_at DESC
        ", $params);

        $logs = $wpdb->get_results($query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Order Item ID', 'woocommerce-production-manager'),
            __('Status', 'woocommerce-production-manager'),
            __('Changed By', 'woocommerce-production-manager'),
            __('Changed At', 'woocommerce-production-manager'),
            __('Note', 'woocommerce-production-manager')
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue("A$row", $log->order_item_id);
            $sheet->setCellValue("B$row", $log->status);
            $sheet->setCellValue("C$row", $log->changed_by_name ?: __('Unknown', 'woocommerce-production-manager'));
            $sheet->setCellValue("D$row", \WPM\Utils\PersianDate::to_persian($log->changed_at, 'Y/m/d H:i'));
            $sheet->setCellValue("E$row", $log->note);
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'status-logs-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}