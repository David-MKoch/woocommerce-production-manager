<?php
namespace WPM\Utils;

defined('ABSPATH') || exit;

class AssetsManager {
    public static function enqueue_admin_assets($hook, $page_identifier) {
        // Check if the current page matches the identifier
        if ($hook !== $page_identifier) {
            return;
        }

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
                'requiredFields' => __('Name and color are required', WPM_TEXT_DOMAIN),
                'delete' => __('Delete', WPM_TEXT_DOMAIN),
                'error' => __('Error occurred', WPM_TEXT_DOMAIN),
                'edit' => __('Edit', WPM_TEXT_DOMAIN),
                'save' => __('Save', WPM_TEXT_DOMAIN),
                'selectStatus' => __('Select Status', WPM_TEXT_DOMAIN),
                'statusUpdated' => __('Status updated', WPM_TEXT_DOMAIN),
                'statusesReordered' => __('Statuses reordered', WPM_TEXT_DOMAIN),
                'deliveryDateUpdated' => __('Delivery date updated', WPM_TEXT_DOMAIN),
                'confirmDelete' => __('Are you sure you want to delete this?', WPM_TEXT_DOMAIN),
                'invalidDate' => __('Invalid date', WPM_TEXT_DOMAIN),
                'holidayUpdated' => __('Holiday updated', WPM_TEXT_DOMAIN),
                'holidaysReordered' => __('Holidays reordered', WPM_TEXT_DOMAIN),
                'exporting' => __('Exporting...', WPM_TEXT_DOMAIN),
                'exportOrderItems' => __('Export Order Items', WPM_TEXT_DOMAIN),
                'exportLogs' => __('Export Logs', WPM_TEXT_DOMAIN)
            ]
        ]);
    }

}
?>