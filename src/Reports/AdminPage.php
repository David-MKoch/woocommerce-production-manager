<?php
namespace WPM\Reports;

use WPM\Reports\DashboardPage;
use WPM\Reports\OrderItemsPage;
use WPM\Reports\ReportsPage;
use WPM\Settings\StatusManager;

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
        add_action('wp_ajax_wpm_update_order_item_status', [StatusManager::class, 'update_order_item_status']);
        add_action('wp_ajax_wpm_update_order_item_delivery_date', [StatusManager::class, 'update_order_item_delivery_date']);
        add_filter('set-screen-option', [__CLASS__, 'set_screen_option'], 10, 3);
    }

    public static function add_menu_pages() {
        $dashboard_hook = add_menu_page(
            __('Production Manager', 'woocommerce-production-manager'),
            __('Production Manager', 'woocommerce-production-manager'),
            'manage_woocommerce',
            'wpm-dashboard',
            [DashboardPage::class, 'render_page'],
            'dashicons-products',
            56
        );

        $order_items_hook = add_submenu_page(
            'wpm-dashboard',
            __('Order Items', 'woocommerce-production-manager'),
            __('Order Items', 'woocommerce-production-manager'),
            'manage_woocommerce',
            'wpm-order-items',
            [OrderItemsPage::class, 'render_page']
        );

        $reports_hook = add_submenu_page(
            'wpm-dashboard',
            __('Reports', 'woocommerce-production-manager'),
            __('Reports', 'woocommerce-production-manager'),
            'manage_woocommerce',
            'wpm-reports',
            [ReportsPage::class, 'render_page']
        );

        //add_action("load-$dashboard_hook", [__CLASS__, 'dashboard_screen_options']);
        add_action("load-$order_items_hook", [__CLASS__, 'order_items_screen_options']);
        add_action("load-$reports_hook", [__CLASS__, 'reports_screen_options']);
    }

    public static function order_items_screen_options() {
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        add_screen_option('per_page', [
            'label' => __('Items per page', 'woocommerce-production-manager'),
            'default' => 20,
            'option' => 'wpm_order_items_per_page'
        ]);
        self::$table = new \WPM\Reports\OrderItemsTable();
    }

    public static function reports_screen_options() {
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'status_logs';
        
        if (isset($_GET['section']) && $_GET['section'] === 'reserved-products') {
            add_screen_option('per_page', [
                'label' => __('Products per page', 'woocommerce-production-manager'),
                'default' => 20,
                'option' => 'wpm_reserved_products_per_page'
            ]);
            self::$table = new \WPM\Reports\ReservedProductsTable();
        } elseif ($tab === 'category') {
            add_screen_option('per_page', [
                'label' => __('Categories per page', 'woocommerce-production-manager'),
                'default' => 20,
                'option' => 'wpm_category_orders_per_page'
            ]);
            self::$table = new \WPM\Reports\CategoryOrdersTable();

        } elseif ($tab === 'status_logs') {
            add_screen_option('per_page', [
                'label' => __('Logs per page', 'woocommerce-production-manager'),
                'default' => 20,
                'option' => 'wpm_status_logs_per_page'
            ]);
            self::$table = new \WPM\Reports\StatusLogsTable();

        }
    }

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

    public static function enqueue_scripts() {
        if (!isset($_GET['page'])) {
            return;
        }

        $page = sanitize_text_field($_GET['page']);

        if (strpos($page, 'wpm-') === 0) {
            \WPM\Utils\AssetsManager::enqueue_admin_assets($page);
        }
    }
}