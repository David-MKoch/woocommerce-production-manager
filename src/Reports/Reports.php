<?php
namespace WPM\Reports;

defined('ABSPATH') || exit;

class Reports {
    private $table;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wpm_export_order_items', [__CLASS__, 'export_order_items']);
    }

    public static function add_menu_page() {
        $hook = add_submenu_page(
            'wpm-dashboard',
            __('Production Reports', WPM_TEXT_DOMAIN),
            __('Production Reports', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-reports',
            [__CLASS__, 'render_page']
        );
        add_action("load-$hook", [__CLASS__, 'add_options']);
    }

    public static function add_options() {
        $option = 'per_page';
        $args = [
            'label' => __('Items per page', WPM_TEXT_DOMAIN),
            'default' => 20,
            'option' => 'wpm_category_orders_per_page'
        ];
        add_screen_option($option, $args);

        add_filter('screen_settings', [__CLASS__, 'add_column_settings'], 10, 2);

        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $instance = new self();
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
        $instance->table = $tab === 'category' ? new CategoryOrdersTable() : new FullCapacityTable();
    }

    public static function add_column_settings($settings, $screen) {
        if ($screen->id !== 'wpm-settings_page_wpm-reports') {
            return $settings;
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
        $table = $tab === 'category' ? new CategoryOrdersTable() : new FullCapacityTable();
        $columns = $table->get_columns();
        $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reports_columnshidden', true) ?: [];

        ob_start();
        ?>
        <fieldset class="metabox-prefs">
            <legend><?php esc_html_e('Columns', WPM_TEXT_DOMAIN); ?></legend>
            <?php
            foreach ($columns as $column_key => $column_label) {
                if ($column_key === 'cb') {
                    continue;
                }
                ?>
                <label>
                    <input class="hide-column-tog" name="<?php echo esc_attr($column_key . '-hide'); ?>" type="checkbox" id="<?php echo esc_attr($column_key . '-hide'); ?>" value="<?php echo esc_attr($column_key); ?>" <?php checked(!in_array($column_key, $hidden)); ?>>
                    <?php echo esc_html($column_label); ?>
                </label>
                <?php
            }
            ?>
        </fieldset>
        <?php
        return $settings . ob_get_clean();
    }

    public static function enqueue_scripts($hook) {
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook, 'production-manager_page_wpm-reports');
    }

    public static function render_page() {
        $instance = new self();
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
        if (!$instance->table) {
            $instance->table = $tab === 'category' ? new CategoryOrdersTable() : new FullCapacityTable();
        }
        $instance->table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Production Reports', WPM_TEXT_DOMAIN); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'category')); ?>" class="nav-tab <?php echo $tab === 'category' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Category Orders', WPM_TEXT_DOMAIN); ?></a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'full_capacity')); ?>" class="nav-tab <?php echo $tab === 'full_capacity' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Full Capacity Days', WPM_TEXT_DOMAIN); ?></a>
            </h2>
            <form method="get">
                <input type="hidden" name="page" value="wpm-reports">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                <div class="wpm-filters">
                    <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                    <?php if ($tab === 'category') : ?>
                        <button type="button" class="button wpm-export-order-items"><?php esc_html_e('Export Order Items', WPM_TEXT_DOMAIN); ?></button>
                    <?php endif; ?>
                </div>
            </form>
            <form method="post">
                <?php $instance->table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function export_order_items() {
        check_ajax_referer('wpm_Admin', 'nonce');

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

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            __('Image', WPM_TEXT_DOMAIN),
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
            $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($item->product_id), 'thumbnail') ?: '';
            $sheet->setCellValue("A$row", $image_url);
            $sheet->setCellValue("B$row", $item->order_id);
            $sheet->setCellValue("C$row", $item->order_item_name);
            $sheet->setCellValue("D$row", $item->customer_name ?: __('Guest', WPM_TEXT_DOMAIN));
            $sheet->setCellValue("E$row", \WPM\Utils\PersianDate::to_persian($item->order_date));
            $sheet->setCellValue("F$row", $item->status);
            $sheet->setCellValue("G$row", \WPM\Utils\PersianDate::to_persian($item->delivery_date));
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'order-items-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
?>