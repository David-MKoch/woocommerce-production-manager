<?php
namespace WPM\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('ABSPATH') || exit;

class OrderItemsPage {
    public static function render_page() {
        $table = \WPM\Reports\AdminPage::$table;
        if (!$table) {
            $table = new OrderItemsTable(); // fallback if not set
        }
        $table->prepare_items();
        $statuses = \WPM\Settings\StatusManager::get_statuses();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Order Items Status', WPM_TEXT_DOMAIN); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="wpm-order-items">
                <div class="wpm-filters">
                    <input type="text" name="order_id" placeholder="<?php esc_attr_e('Order ID', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['order_id'] ?? ''); ?>">
                    <input type="text" name="customer_name" placeholder="<?php esc_attr_e('Customer Name', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['customer_name'] ?? ''); ?>">
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', WPM_TEXT_DOMAIN); ?></option>
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($_GET['status'] ?? '', $status); ?>><?php echo esc_html($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button wpm-export-order-items-csv"><?php esc_html_e('Export to CSV', WPM_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button wpm-export-order-items-excel"><?php esc_html_e('Export to Excel', WPM_TEXT_DOMAIN); ?></button>
                </div>
            </form>
            <form method="post">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function export_order_items_csv() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        global $wpdb;

        $query = "
            SELECT s.*, o.date_created_gmt as order_date, oi.order_item_name, p.meta_value as product_id,
                   u.display_name as customer_name
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta p ON oi.order_item_id = p.order_item_id AND p.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}users u ON o.customer_id = u.ID
            ORDER BY o.date_created_gmt DESC
        ";

        $items = $wpdb->get_results($query);

        $filename = 'order-items-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        fputcsv($file, [
            __('Order ID', WPM_TEXT_DOMAIN),
            __('Item Name', WPM_TEXT_DOMAIN),
            __('Customer', WPM_TEXT_DOMAIN),
            __('Order Date', WPM_TEXT_DOMAIN),
            __('Status', WPM_TEXT_DOMAIN),
            __('Delivery Date', WPM_TEXT_DOMAIN)
        ]);

        foreach ($items as $item) {
            fputcsv($file, [
                $item->order_id,
                $item->order_item_name,
                $item->customer_name ?: __('Guest', WPM_TEXT_DOMAIN),
                \WPM\Utils\PersianDate::to_persian($item->order_date),
                $item->status,
                \WPM\Utils\PersianDate::to_persian($item->delivery_date)
            ]);
        }

        fclose($file);
        wp_send_json_success(['url' => $upload_dir['baseurl'] . '/' . $filename]);
    }

    public static function export_order_items_excel() {
        check_ajax_referer('wpm_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized', WPM_TEXT_DOMAIN));
        }

        global $wpdb;

        $query = "
            SELECT s.*, o.date_created_gmt as order_date, oi.order_item_name, p.meta_value as product_id,
                   u.display_name as customer_name
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta p ON oi.order_item_id = p.order_item_id AND p.meta_key = '_product_id'
            LEFT JOIN {$wpdb->prefix}users u ON o.customer_id = u.ID
            ORDER BY o.date_created_gmt DESC
        ";

        $items = $wpdb->get_results($query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Order ID', WPM_TEXT_DOMAIN),
            __('Item Name', WPM_TEXT_DOMAIN),
            __('Customer', WPM_TEXT_DOMAIN),
            __('Order Date', WPM_TEXT_DOMAIN),
            __('Status', WPM_TEXT_DOMAIN),
            __('Delivery Date', WPM_TEXT_DOMAIN)
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue("A$row", $item->order_id);
            $sheet->setCellValue("B$row", $item->order_item_name);
            $sheet->setCellValue("C$row", $item->customer_name ?: __('Guest', WPM_TEXT_DOMAIN));
            $sheet->setCellValue("D$row", \WPM\Utils\PersianDate::to_persian($item->order_date));
            $sheet->setCellValue("E$row", $item->status);
            $sheet->setCellValue("F$row", \WPM\Utils\PersianDate::to_persian($item->delivery_date));
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'order-items-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}