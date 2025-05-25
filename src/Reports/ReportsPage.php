<?php
namespace WPM\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('ABSPATH') || exit;

class ReportsPage {
    public static function render() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Category Reservations', WPM_TEXT_DOMAIN); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'category')); ?>" class="nav-tab <?php echo $tab === 'category' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Category Orders', WPM_TEXT_DOMAIN); ?></a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'status_logs')); ?>" class="nav-tab <?php echo $tab === 'status_logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Status Logs', WPM_TEXT_DOMAIN); ?></a>
            </h2>
            <?php if ($tab === 'category') : ?>
                <?php
                $table = new CategoryOrdersTable();
                $table->prepare_items();
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="wpm-reports">
                    <input type="hidden" name="tab" value="category">
                    <div class="wpm-filters">
                        <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                        <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button wpm-export-category-orders-csv"><?php esc_html_e('Export to CSV', WPM_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button wpm-export-category-orders-excel"><?php esc_html_e('Export to Excel', WPM_TEXT_DOMAIN); ?></button>
                    </div>
                </form>
                <form method="post">
                    <?php $table->display(); ?>
                </form>
            <?php elseif ($tab === 'status_logs') : ?>
                <?php
                $table = new StatusLogsTable();
                $table->prepare_items();
                ?>
                <form method="get">
                    <input type="hidden" name="page" value="wpm-reports">
                    <input type="hidden" name="tab" value="status_logs">
                    <div class="wpm-filters">
                        <input type="text" name="order_item_id" placeholder="<?php esc_attr_e('Order Item ID', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['order_item_id'] ?? ''); ?>">
                        <input type="text" name="changed_by" placeholder="<?php esc_attr_e('Changed By (User ID)', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['changed_by'] ?? ''); ?>">
                        <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                        <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button wpm-export-status-logs-csv"><?php esc_html_e('Export to CSV', WPM_TEXT_DOMAIN); ?></button>
                        <button type="button" class="button wpm-export-status-logs-excel"><?php esc_html_e('Export to Excel', WPM_TEXT_DOMAIN); ?></button>
                    </div>
                </form>
                <form method="post">
                    <?php $table->display(); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function export_category_orders_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
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
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, cc.reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON pc.entity_id = cc.entity_id AND pc.entity_type = cc.entity_type
            $where_sql
            AND pc.entity_type = 'category'
            ORDER BY cc.date DESC
        ", $params);

        $items = $wpdb->get_results($query);

        $filename = 'category-orders-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        fputcsv($file, [
            __('Category', WPM_TEXT_DOMAIN),
            __('Production Capacity', WPM_TEXT_DOMAIN),
            __('Reserved Count', WPM_TEXT_DOMAIN),
            __('Reservation Date', WPM_TEXT_DOMAIN)
        ]);

        foreach ($items as $item) {
            fputcsv($file, [
                $item->category_name,
                $item->max_capacity ?: __('No Limit', WPM_TEXT_DOMAIN),
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
            wp_die(__('Unauthorized', WPM_TEXT_DOMAIN));
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
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT pc.entity_id as term_id, t.name as category_name, pc.max_capacity, cc.date as reservation_date, cc.reserved_count
            FROM {$wpdb->prefix}wpm_production_capacity pc
            JOIN {$wpdb->prefix}terms t ON pc.entity_id = t.term_id
            JOIN {$wpdb->prefix}wpm_capacity_count cc ON pc.entity_id = cc.entity_id AND pc.entity_type = cc.entity_type
            $where_sql
            AND pc.entity_type = 'category'
            ORDER BY cc.date DESC
        ", $params);

        $items = $wpdb->get_results($query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Category', WPM_TEXT_DOMAIN),
            __('Production Capacity', WPM_TEXT_DOMAIN),
            __('Reserved Count', WPM_TEXT_DOMAIN),
            __('Reservation Date', WPM_TEXT_DOMAIN)
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue("A$row", $item->category_name);
            $sheet->setCellValue("B$row", $item->max_capacity ?: __('No Limit', WPM_TEXT_DOMAIN));
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

    public static function export_status_logs_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
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
            __('Order Item ID', WPM_TEXT_DOMAIN),
            __('Status', WPM_TEXT_DOMAIN),
            __('Changed By', WPM_TEXT_DOMAIN),
            __('Changed At', WPM_TEXT_DOMAIN),
            __('Note', WPM_TEXT_DOMAIN)
        ]);

        foreach ($logs as $log) {
            fputcsv($file, [
                $log->order_item_id,
                $log->status,
                $log->changed_by_name ?: __('Unknown', WPM_TEXT_DOMAIN),
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
            wp_die(__('Unauthorized', WPM_TEXT_DOMAIN));
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
            __('Order Item ID', WPM_TEXT_DOMAIN),
            __('Status', WPM_TEXT_DOMAIN),
            __('Changed By', WPM_TEXT_DOMAIN),
            __('Changed At', WPM_TEXT_DOMAIN),
            __('Note', WPM_TEXT_DOMAIN)
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue("A$row", $log->order_item_id);
            $sheet->setCellValue("B$row", $log->status);
            $sheet->setCellValue("C$row", $log->changed_by_name ?: __('Unknown', WPM_TEXT_DOMAIN));
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