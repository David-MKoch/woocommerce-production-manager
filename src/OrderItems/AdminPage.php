<?php
namespace WPM\OrderItems;

defined('ABSPATH') || exit;

class AdminPage {
    private $table;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function add_menu_page() {
        $hook = add_submenu_page(
            'wpm-dashboard',
            __('Order Items Status', WPM_TEXT_DOMAIN),
            __('Order Items Status', WPM_TEXT_DOMAIN),
            'manage_woocommerce',
            'wpm-order-items',
            [__CLASS__, 'render_page']
        );
        add_action("load-$hook", [__CLASS__, 'add_options']);
    }

    public static function add_options() {
        $option = 'per_page';
        $args = [
            'label' => __('Items per page', WPM_TEXT_DOMAIN),
            'default' => 20,
            'option' => 'wpm_order_items_per_page'
        ];
        add_screen_option($option, $args);

        add_filter('screen_settings', [__CLASS__, 'add_column_settings'], 10, 2);

        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $instance = new self();
        $instance->table = new OrderItemsTable();
    }

    public static function add_column_settings($settings, $screen) {
        if ($screen->id !== 'wpm-dashboard_page_wpm-order-items') {
            return $settings;
        }

        $columns = (new OrderItemsTable())->get_columns();
        $hidden = get_user_meta(get_current_user_id(), 'managewoocommerce_page_wpm-order-items_columnshidden', true) ?: [];

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
        \WPM\Utils\AssetsManager::enqueue_admin_assets($hook, 'production-manager_page_wpm-order-items');
    }

    public static function render_page() {
        $instance = new self();
        if (!$instance->table) {
            $instance->table = new OrderItemsTable();
        }
        $instance->table->prepare_items();

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
                </div>
            </form>
            <form method="post">
                <?php
                $instance->table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
?>