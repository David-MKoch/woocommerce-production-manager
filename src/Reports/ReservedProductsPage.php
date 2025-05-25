<?php
namespace WPM\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('ABSPATH') || exit;

class ReservedProductsPage {
    public static function render() {
        $table = new ReservedProductsTable();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reserved Products', WPM_TEXT_DOMAIN); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="wpm-reserved-products">
                <input type="hidden" name="date" value="<?php echo esc_attr(isset($_GET['date']) ? sanitize_text_field($_GET['date']) : ''); ?>">
                <div class="wpm-filters">
                    <?php $table->search_box(__('Search Products', WPM_TEXT_DOMAIN), 'product-search'); ?>
                    <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button wpm-export-reserved-products-csv"><?php esc_html_e('Export to CSV', WPM_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button wpm-export-reserved-products-excel"><?php esc_html_e('Export to Excel', WPM_TEXT_DOMAIN); ?></button>
                </div>
            </form>
            <form method="post">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function export_reserved_products_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        global $wpdb;

        $reservation_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $where = [];
        $params = [];

        if ($reservation_date) {
            $where[] = 'cc.date = %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian($reservation_date);
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_query = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(p.post_title LIKE %s OR cc.entity_id = %s)';
            $params[] = $search_query;
            $params[] = absint($_GET['s']);
        }
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $where[] = 'tr.term_id = %d';
            $params[] = absint($_GET['category_id']);
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
            __('Product Name', WPM_TEXT_DOMAIN),
            __('Product ID', WPM_TEXT_DOMAIN),
            __('Reserved Count', WPM_TEXT_DOMAIN),
            __('Reservation Date', WPM_TEXT_DOMAIN)
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
            wp_die(__('Unauthorized', WPM_TEXT_DOMAIN));
        }

        global $wpdb;

        $reservation_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $where = [];
        $params = [];

        if ($reservation_date) {
            $where[] = 'cc.date = %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian($reservation_date);
        }
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search_query = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(p.post_title LIKE %s OR cc.entity_id = %s)';
            $params[] = $search_query;
            $params[] = absint($_GET['s']);
        }
        if (isset($_GET['category_id']) && absint($_GET['category_id']) > 0) {
            $where[] = 'tr.term_id = %d';
            $params[] = absint($_GET['category_id']);
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
            __('Product Name', WPM_TEXT_DOMAIN),
            __('Product ID', WPM_TEXT_DOMAIN),
            __('Reserved Count', WPM_TEXT_DOMAIN),
            __('Reservation Date', WPM_TEXT_DOMAIN)
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
}