<?php
namespace WPM\Settings;

defined('ABSPATH') || exit;

class Calendar {
    public static function init() {
        add_action('wp_ajax_wpm_save_holidays', [__CLASS__, 'save_holidays']);
        add_action('wp_ajax_wpm_delete_holiday', [__CLASS__, 'delete_holiday']);
        add_action('wp_ajax_wpm_update_holiday', [__CLASS__, 'update_holiday']);
    }

    public static function render_holidays_tab() {
        global $wpdb;
        ?>
        <form method="post" id="wpm-settings-form">
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('Holidays Settings', 'woocommerce-production-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Weekly Holidays', 'woocommerce-production-manager'); ?></th>
                        <td>
                            <?php
                            $days = [
                                'saturday' => __('Saturday', 'woocommerce-production-manager'),
                                'sunday' => __('Sunday', 'woocommerce-production-manager'),
                                'monday' => __('Monday', 'woocommerce-production-manager'),
                                'tuesday' => __('Tuesday', 'woocommerce-production-manager'),
                                'wednesday' => __('Wednesday', 'woocommerce-production-manager'),
                                'thursday' => __('Thursday', 'woocommerce-production-manager'),
                                'friday' => __('Friday', 'woocommerce-production-manager')
                            ];
                            $weekly_holidays = get_option('wpm_weekly_holidays', []);
                            foreach ($days as $key => $label) :
                            ?>
                                <label>
                                    <input type="checkbox" name="wpm_weekly_holidays[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $weekly_holidays)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p><?php esc_html_e('Select days to mark as holidays each week.', 'woocommerce-production-manager'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>
        </form>
        <h3><?php esc_html_e('Custom Holidays', 'woocommerce-production-manager'); ?></h3>
        <div id="wpm-holidays-form">
            <input type="text" id="wpm-holiday-date" class="persian-datepicker" placeholder="<?php esc_attr_e('Select date', 'woocommerce-production-manager'); ?>">
            <input type="text" id="wpm-holiday-description" placeholder="<?php esc_attr_e('Description (optional)', 'woocommerce-production-manager'); ?>">
            <button type="button" class="button button-primary wpm-add-holiday"><?php esc_html_e('Add Holiday', 'woocommerce-production-manager'); ?></button>
        </div>
        <table class="wp-list-table widefat fixed striped wpm-holidays-list" id="wpm-holidays-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'woocommerce-production-manager'); ?></th>
                    <th><?php esc_html_e('Description', 'woocommerce-production-manager'); ?></th>
                    <th><?php esc_html_e('Actions', 'woocommerce-production-manager'); ?></th>
                </tr>
            </thead>
            <tbody id="wpm-holidays-sortable">
                <?php
                $holidays = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpm_holidays ORDER BY date");
                foreach ($holidays as $index => $holiday) :
                ?>
                    <tr data-index="<?php echo esc_attr($holiday->id); ?>">
                        <td class="wpm-holiday-date"><?php echo esc_html(\WPM\Utils\PersianDate::to_persian($holiday->date)); ?></td>
                        <td class="wpm-holiday-description"><?php echo esc_html($holiday->description); ?></td>
                        <td>
                            <button class="button wpm-edit-holiday"><?php esc_html_e('Edit', 'woocommerce-production-manager'); ?></button>
                            <button class="button wpm-delete-holiday"><?php esc_html_e('Delete', 'woocommerce-production-manager'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public static function save_holidays() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $date = sanitize_text_field($_POST['date']);
        $description = sanitize_text_field($_POST['description']);

        if (empty($date) || !\WPM\Utils\PersianDate::is_valid_persian_date($date)) {
            wp_send_json_error(['message' => __('Invalid date', 'woocommerce-production-manager')]);
        }

        global $wpdb;
        $date_gregorian = \WPM\Utils\PersianDate::to_gregorian($date);
        $inserted = $wpdb->insert(
            "{$wpdb->prefix}wpm_holidays",
            [
                'date' => $date_gregorian,
                'description' => $description,
            ],
            ['%s', '%s']
        );

        if ($inserted === false) {
            wp_send_json_error(['message' => __('Failed to save holiday', 'woocommerce-production-manager')]);
        }

        $holiday_id = $wpdb->insert_id;
        wp_send_json_success([
            'message' => __('Holiday saved', 'woocommerce-production-manager'),
            'index' => $holiday_id,
            'holiday' => ['date' => $date, 'description' => $description]
        ]);
    }

    public static function update_holiday() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $holiday_id = absint($_POST['index']);
        $date = sanitize_text_field($_POST['date']);
        $description = sanitize_text_field($_POST['description']);

        if (empty($date) || !\WPM\Utils\PersianDate::is_valid_persian_date($date)) {
            wp_send_json_error(['message' => __('Invalid date', 'woocommerce-production-manager')]);
        }

        global $wpdb;
        $date_gregorian = \WPM\Utils\PersianDate::to_gregorian($date);
        $updated = $wpdb->update(
            "{$wpdb->prefix}wpm_holidays",
            [
                'date' => $date_gregorian,
                'description' => $description
            ],
            ['id' => $holiday_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => __('Failed to update holiday', 'woocommerce-production-manager')]);
        }

        wp_send_json_success([
            'message' => __('Holiday updated', 'woocommerce-production-manager'),
            'holiday' => [
                'date' => $date,
                'description' => $description
            ]
        ]);
    }

    public static function delete_holiday() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', 'woocommerce-production-manager')]);
        }

        $holiday_id = absint($_POST['index']);
        global $wpdb;

        $deleted = $wpdb->delete(
            "{$wpdb->prefix}wpm_holidays",
            ['id' => $holiday_id],
            ['%d']
        );

        if ($deleted === false) {
            wp_send_json_error(['message' => __('Failed to delete holiday', 'woocommerce-production-manager')]);
        }

        wp_send_json_success(['message' => __('Holiday deleted', 'woocommerce-production-manager')]);
    }

    public static function sanitize_weekly_holidays($value) {
        if (!is_array($value)) {
            return [];
        }
        $valid_days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        return array_intersect($value, $valid_days);
    }

    public static function get_weekly_holidays() {
        return get_option('wpm_weekly_holidays', ['friday']);
    }
}
?>