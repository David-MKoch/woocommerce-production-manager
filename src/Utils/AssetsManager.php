<?php
namespace WPM\Utils;

defined('ABSPATH') || exit;

class AssetsManager {
    public static function enqueue_admin_assets($hook) {
        // Check if the current page matches the identifier
        if (!in_array($hook, [
            'toplevel_page_wpm-dashboard',
            'production-manager_page_wpm-order-items',
            'production-manager_page_wpm-reports',
            'production-manager_page_wpm-reserved-products',
            'production-manager_page_wpm-settings'
        ])) {
            return;
        }

        if ($hook === 'toplevel_page_wpm-dashboard') {
            wp_enqueue_script('chart-js', WPM_PLUGIN_URL . 'assets/js/chart.js', [], '4.4.0', true);

            wp_enqueue_script('wpm-dashboard-js', WPM_PLUGIN_URL . 'assets/js/dashboard.js', ['chart-js', 'jquery'], '1.0.0', true);
            wp_enqueue_style('wpm-dashboard-css', WPM_PLUGIN_URL . 'assets/css/dashboard.css', [], '1.0.0');
            wp_localize_script('wpm-dashboard-js', 'wpmDashboard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpm_dashboard'),
                'i18n' => [
                    'capacityUsed' => __('Capacity Used', 'woocommerce-production-manager'),
                    'delayedOrders' => __('Delayed Orders', 'woocommerce-production-manager'),
                    'smsSent' => __('SMS Sent', 'woocommerce-production-manager')
                ]
            ]);
        }else{
            wp_enqueue_style('wpm-admin-css', WPM_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0');
            wp_enqueue_script('wpm-admin-js', WPM_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable'], '1.0.0', true);
            wp_enqueue_script('wpm-persian-datepicker', WPM_PLUGIN_URL . 'assets/js/persian-datepicker.min.js', ['jquery'], '1.0.0', true);
            wp_enqueue_style('wpm-persian-datepicker', WPM_PLUGIN_URL . 'assets/css/persian-datepicker-default.css', [], '1.0.0');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-color-picker');

            $statuses = get_option('wpm_statuses', []);
            $status_options = array_map(function($status) {
                return [
                    'name' => $status['name'],
                    'color' => $status['color']
                ];
            }, $statuses);

            wp_localize_script('wpm-admin-js', 'wpmAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wpm_Admin'),
                'statuses' => $status_options,
                'i18n'    => [
                    'requiredFields' => __('Name and color are required', 'woocommerce-production-manager'),
                    'delete' => __('Delete', 'woocommerce-production-manager'),
                    'error' => __('Error occurred', 'woocommerce-production-manager'),
                    'edit' => __('Edit', 'woocommerce-production-manager'),
                    'save' => __('Save', 'woocommerce-production-manager'),
                    'selectStatus' => __('Select Status', 'woocommerce-production-manager'),
                    'statusUpdated' => __('Status updated', 'woocommerce-production-manager'),
                    'statusesReordered' => __('Statuses reordered', 'woocommerce-production-manager'),
                    'deliveryDateUpdated' => __('Delivery date updated', 'woocommerce-production-manager'),
                    'confirmDelete' => __('Are you sure you want to delete this?', 'woocommerce-production-manager'),
                    'invalidDate' => __('Invalid date', 'woocommerce-production-manager'),
                    'holidayUpdated' => __('Holiday updated', 'woocommerce-production-manager'),
                    'holidaysReordered' => __('Holidays reordered', 'woocommerce-production-manager'),
                    'exporting' => __('Exporting...', 'woocommerce-production-manager'),
                    'exportOrderItems' => __('Export Order Items', 'woocommerce-production-manager'),
                    'exportLogs' => __('Export Logs', 'woocommerce-production-manager'),
                    'cacheCleared' => __('Cache cleared successfully.', 'woocommerce-production-manager'),
                    'loading' => __('Loading...', 'woocommerce-production-manager'),
                    'capacityReset' => __('Production capacity reset successfully.', 'woocommerce-production-manager'),
                ]
            ]);
        }

        
    }

}
?>