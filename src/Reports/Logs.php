<?php
namespace WPM\Reports;

defined('ABSPATH') || exit;

class Logs {
    private $table;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wpm_export_logs', [__CLASS__, 'export_logs']);
    }

    public static function add_menu_page() {
        $hook = add_submenu_page(
            'wpm-dashboard',
            __('Status Logs', WPM_TEXT_DOMAIN),
            __('Status Logs', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-status-logs',
            [__CLASS__, 'render_page']
        );
        add_action("load-$hook", [__CLASS__, 'add_options']);
    }

    public static function add_options() {
        $option = 'per_page';
        $args = [
            'label' => __('Items per page', WPM_TEXT_DOMAIN),
            'default' => 20,
            'option' => 'wpm_status_logs_per_page'
        ];
        add_screen_option($option, $args);

        add_filter('screen_settings', [__CLASS__, 'add_column_settings'], 10, 2);

        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $instance = new self();
        $instance->table = new StatusLogsTable();
    }

    public static function add_column_settings($settings, $screen) {
        if ($screen->id !== 'wpm-settings_page_wpm-status-logs') {
            return $settings;
        }

        $table = new StatusLogsTable();
        $columns = $table->get_columns();
        $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-status-logs_columnshidden', true) ?: [];

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
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook, 'production-manager_page_wpm-status-logs');
    }

    public static function render_page() {
        $instance = new self();
        if (!$instance->table) {
            $instance->table = new StatusLogsTable();
        }
        $instance->table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Status Logs', WPM_TEXT_DOMAIN); ?></h1>
            <form method="get">
                <input type="hidden" name="page" value="wpm-status-logs">
                <div class="wpm-filters">
                    <input type="text" name="order_item_id" placeholder="<?php esc_attr_e('Order Item ID', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['order_item_id'] ?? ''); ?>">
                    <input type="text" name="changed_by" placeholder="<?php esc_attr_e('Changed By (User ID)', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['changed_by'] ?? ''); ?>">
                    <input type="text" name="date_from" class="persian-datepicker" placeholder="<?php esc_attr_e('From Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                    <input type="text" name="date_to" class="persian-datepicker" placeholder="<?php esc_attr_e('To Date', WPM_TEXT_DOMAIN); ?>" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Filter', WPM_TEXT_DOMAIN); ?></button>
                    <button type="button" class="button wpm-export-logs"><?php esc_html_e('Export Logs', WPM_TEXT_DOMAIN); ?></button>
                </div>
            </form>
            <form method="post">
                <?php $instance->table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function export_logs() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        global $wpdb;

        $where = [];
        $params = [];

        if (isset($_POST['order_item_id']) && $_POST['order_item_id']) {
            $where[] = 'order_item_id = %d';
            $params[] = absint($_POST['order_item_id']);
        }
        if (isset($_POST['changed_by']) && $_POST['changed_by']) {
            $where[] = 'changed_by = %d';
            $params[] = absint($_POST['changed_by']);
        }
        if (isset($_POST['date_from']) && $_POST['date_from']) {
            $where[] = 'changed_at >= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian($_POST['date_from']);
        }
        if (isset($_POST['date_to']) && $_POST['date_to']) {
            $where[] = 'changed_at <= %s';
            $params[] = \WPM\Utils\PersianDate::to_gregorian($_POST['date_to']);
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

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
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

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'status-logs-' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
?>