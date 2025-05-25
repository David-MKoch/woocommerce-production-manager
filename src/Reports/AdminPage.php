<?php
namespace WPM\Reports;

use WPM\Reports\DashboardPage;
use WPM\Reports\OrderItemsPage;
use WPM\Reports\ReportsPage;

defined('ABSPATH') || exit;

class AdminPage {
    public static $table;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
		add_action('wp_ajax_wpm_get_dashboard_data', [DashboardPage::class, 'get_dashboard_data']);
        add_action('wp_ajax_wpm_export_order_items_csv', [OrderItemsPage::class, 'export_order_items_csv']);
        add_action('wp_ajax_wpm_export_order_items_excel', [OrderItemsPage::class, 'export_order_items_excel']);
        add_action('wp_ajax_wpm_export_category_orders_csv', [ReportsPage::class, 'export_category_orders_csv']);
        add_action('wp_ajax_wpm_export_category_orders_excel', [ReportsPage::class, 'export_category_orders_excel']);
        add_action('wp_ajax_wpm_export_reserved_products_csv', [ReportsPage::class, 'export_reserved_products_csv']);
        add_action('wp_ajax_wpm_export_reserved_products_excel', [ReportsPage::class, 'export_reserved_products_excel']);
        add_action('wp_ajax_wpm_export_status_logs_csv', [ReportsPage::class, 'export_status_logs_csv']);
        add_action('wp_ajax_wpm_export_status_logs_excel', [ReportsPage::class, 'export_status_logs_excel']);
        add_action('wp_ajax_wpm_update_order_item_status', [\WPM\Settings\StatusManager::class, 'update_order_item_status']);
        add_action('wp_ajax_wpm_update_order_item_delivery_date', [\WPM\Settings\StatusManager::class, 'update_order_item_delivery_date']);
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);
    }

    public static function add_menu_pages() {
        $dashboard_hook = add_menu_page(
            __('Production Manager', WPM_TEXT_DOMAIN),
            __('Production Manager', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-dashboard',
            [DashboardPage::class, 'render_page'],
            'dashicons-products',
            56
        );

        $order_items_hook = add_submenu_page(
            'wpm-dashboard',
            __('Order Items', WPM_TEXT_DOMAIN),
            __('Order Items', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-order-items',
            [OrderItemsPage::class, 'render_page']
        );

        $reports_hook = add_submenu_page(
            'wpm-dashboard',
            __('Category Reservations', WPM_TEXT_DOMAIN),
            __('Category Reservations', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-reports',
            [ReportsPage::class, 'render_page']
        );

        add_action("load-$dashboard_hook", [__CLASS__, 'add_screen_options']);
        add_action("load-$order_items_hook", [__CLASS__, 'add_screen_options']);
        add_action("load-$reports_hook", [__CLASS__, 'add_screen_options']);
    }

    public static function add_screen_options() {
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $screen = get_current_screen();

        if ($screen->id === 'toplevel_page_wpm-dashboard') {
            // No screen options for dashboard yet
        } elseif ($screen->id === 'production-manager_page_wpm-order-items') {
            add_screen_option('per_page', [
                'label' => __('Items per page', WPM_TEXT_DOMAIN),
                'default' => 20,
                'option' => 'wpm_order_items_per_page'
            ]);

            self::$table = new \WPM\Reports\OrderItemsTable();

        } elseif ($screen->id === 'production-manager_page_wpm-reports') {
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
            if (isset($_GET['section']) && $_GET['section'] === 'reserved-products') {
                add_screen_option('per_page', [
                    'label' => __('Products per page', WPM_TEXT_DOMAIN),
                    'default' => 20,
                    'option' => 'wpm_reserved_products_per_page'
                ]);
                self::$table = new \WPM\Reports\ReservedProductsTable();

            } elseif ($tab === 'category') {
                add_screen_option('per_page', [
                    'label' => __('Categories per page', WPM_TEXT_DOMAIN),
                    'default' => 20,
                    'option' => 'wpm_category_orders_per_page'
                ]);
                self::$table = new \WPM\Reports\CategoryOrdersTable();

            } elseif ($tab === 'status_logs') {
                add_screen_option('per_page', [
                    'label' => __('Logs per page', WPM_TEXT_DOMAIN),
                    'default' => 20,
                    'option' => 'wpm_status_logs_per_page'
                ]);
                self::$table = new \WPM\Reports\StatusLogsTable();

            }
        }

        //add_filter('screen_settings', [__CLASS__, 'add_column_settings'], 10, 2);
    }

    /*public static function add_column_settings($settings, $screen) {
        $hidden = [];
        $columns = [];

        if ($screen->id === 'production-manager_page_wpm-order-items') {
            $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-order-items_columnshidden', true) ?: [];
            //$table = new \WPM\Reports\OrderItemsTable();
            $columns = self::$table->get_columns();
        } elseif ($screen->id === 'production-manager_page_wpm-reports') {
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'category';
            if ($tab === 'category') {
                $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reports_columnshidden', true) ?: [];
                //$table = new \WPM\Reports\CategoryOrdersTable();
                $columns = self::$table->get_columns();
            } elseif ($tab === 'status_logs') {
                $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-status-logs_columnshidden', true) ?: [];
                //$table = new \WPM\Reports\StatusLogsTable();
                $columns = self::$table->get_columns();
            } elseif (isset($_GET['section']) && $_GET['section'] === 'reserved-products') {
                $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-reserved-products_columnshidden', true) ?: [];
                //$table = new \WPM\Reports\ReservedProductsTable();
                $columns = self::$table->get_columns();
            }
        }

        if (empty($columns)) {
            return $settings;
        }

        ob_start();
        ?>
        <fieldset class="metabox-prefs">
            <legend><?php esc_html_e('Columns', WPM_TEXT_DOMAIN); ?></legend>
            <?php
            foreach ($columns as $column_key => $column_name) {
                if ($column_key === 'cb') {
                    continue;
                }
                $checked = !in_array($column_key, $hidden) ? 'checked="checked"' : '';
                ?>
                <label>
                    <input class="hide-column-tog" type="checkbox" name="wpm_columns[<?php echo esc_attr($column_key); ?>]" value="1" <?php echo $checked; ?>>
                    <?php echo esc_html($column_name); ?>
                </label>
                <?php
            }
            ?>
        </fieldset>
        <?php
        return $settings . ob_get_clean();
    }*/

    public static function set_screen_option($status, $option, $value) {
        if (in_array($option, [
            'wpm_order_items_per_page', 
            'wpm_category_orders_per_page', 
            'wpm_status_logs_per_page', 
            'wpm_reserved_products_per_page'
        ])) {
            return absint($value);
        }
        return $status;
    }

    public static function enqueue_scripts($hook) {
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook);
    }
}