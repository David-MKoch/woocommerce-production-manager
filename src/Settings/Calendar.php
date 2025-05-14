<?php
namespace WPM\Settings;

defined('ABSPATH') || exit;

class Calendar {
    public static function init() {
        add_action('wp_ajax_wpm_save_holidays', [__CLASS__, 'save_holidays']);
        add_action('wp_ajax_wpm_delete_holiday', [__CLASS__, 'delete_holiday']);
        add_action('wp_ajax_wpm_update_holiday', [__CLASS__, 'update_holiday']);
        add_action('wp_ajax_wpm_reorder_holidays', [__CLASS__, 'reorder_holidays']);
    }

    public static function render_holidays_tab() {
        ?>
        <form method="post" id="wpm-settings-form">
            <div class="wpm-tab-content">
                <h2><?php esc_html_e('Holidays Settings', WPM_TEXT_DOMAIN); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Weekly Holidays', WPM_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php
                            $days = [
                                'saturday' => __('Saturday', WPM_TEXT_DOMAIN),
                                'sunday' => __('Sunday', WPM_TEXT_DOMAIN),
                                'monday' => __('Monday', WPM_TEXT_DOMAIN),
                                'tuesday' => __('Tuesday', WPM_TEXT_DOMAIN),
                                'wednesday' => __('Wednesday', WPM_TEXT_DOMAIN),
                                'thursday' => __('Thursday', WPM_TEXT_DOMAIN),
                                'friday' => __('Friday', WPM_TEXT_DOMAIN)
                            ];
                            $weekly_holidays = get_option('wpm_weekly_holidays', []);
                            foreach ($days as $key => $label) :
                            ?>
                                <label>
                                    <input type="checkbox" name="wpm_weekly_holidays[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $weekly_holidays)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p><?php esc_html_e('Select days to mark as holidays each week.', WPM_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>
        </form>
        <h3><?php esc_html_e('Custom Holidays', WPM_TEXT_DOMAIN); ?></h3>
        <div id="wpm-holidays-form">
            <input type="text" id="wpm-holiday-date" class="persian-datepicker" placeholder="<?php esc_attr_e('Select date', WPM_TEXT_DOMAIN); ?>">
            <input type="text" id="wpm-holiday-description" placeholder="<?php esc_attr_e('Description (optional)', WPM_TEXT_DOMAIN); ?>">
            <button type="button" class="button button-primary wpm-add-holiday"><?php esc_html_e('Add Holiday', WPM_TEXT_DOMAIN); ?></button>
        </div>
        <table class="wp-list-table widefat fixed striped wpm-holidays-list" id="wpm-holidays-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', WPM_TEXT_DOMAIN); ?></th>
                    <th><?php esc_html_e('Description', WPM_TEXT_DOMAIN); ?></th>
                    <th><?php esc_html_e('Actions', WPM_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody id="wpm-holidays-sortable">
                <?php
                $holidays = get_option('wpm_holidays', []);
                foreach ($holidays as $index => $holiday) :
                ?>
                    <tr data-index="<?php echo esc_attr($index); ?>">
                        <td class="wpm-holiday-date"><?php echo esc_html($holiday['date']); ?></td>
                        <td class="wpm-holiday-description"><?php echo esc_html($holiday['description']); ?></td>
                        <td>
                            <button class="button wpm-edit-holiday"><?php esc_html_e('Edit', WPM_TEXT_DOMAIN); ?></button>
                            <button class="button wpm-delete-holiday"><?php esc_html_e('Delete', WPM_TEXT_DOMAIN); ?></button>
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
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        $date = sanitize_text_field($_POST['date']);
        $description = sanitize_text_field($_POST['description']);

        if (empty($date) || !\WPM\Utils\PersianDate::is_valid_persian_date($date)) {
            wp_send_json_error(['message' => __('Invalid date', WPM_TEXT_DOMAIN)]);
        }

        $holidays = get_option('wpm_holidays', []);
        $index = count($holidays);
        $holidays[] = [
            'date' => $date,
            'description' => $description
        ];
        update_option('wpm_holidays', $holidays);

        wp_send_json_success([
            'message' => __('Holidays saved', WPM_TEXT_DOMAIN),
            'index' => $index,
            'holiday' => ['date' => $date, 'description' => $description]
        ]);
    }

    public static function update_holiday() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        $index = absint($_POST['index']);
        $date = sanitize_text_field($_POST['date']);
        $description = sanitize_text_field($_POST['description']);

        if (empty($date) || !\WPM\Utils\PersianDate::is_valid_persian_date($date)) {
            wp_send_json_error(['message' => __('Invalid date', WPM_TEXT_DOMAIN)]);
        }

        $holidays = get_option('wpm_holidays', []);
        if (isset($holidays[$index])) {
            $holidays[$index] = ['date' => $date, 'description' => $description];
            update_option('wpm_holidays', $holidays);
            wp_send_json_success([
                'message' => __('Holiday updated', WPM_TEXT_DOMAIN),
                'holiday' => [
                    'date' => $date,
                    'description' => $description
                ]
            ]);
        }

        wp_send_json_error(['message' => __('Invalid holiday index', WPM_TEXT_DOMAIN)]);
    }
	
	public static function delete_holiday() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        $index = absint($_POST['index']);
        $holidays = get_option('wpm_holidays', []);

        if (isset($holidays[$index])) {
            unset($holidays[$index]);
            $holidays = array_values($holidays);
            update_option('wpm_holidays', $holidays);
            wp_send_json_success(['message' => __('Holiday deleted', WPM_TEXT_DOMAIN)]);
        }

        wp_send_json_error(['message' => __('Invalid holiday index', WPM_TEXT_DOMAIN)]);
    }

    public static function reorder_holidays() {
        check_ajax_referer('wpm_Admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized', WPM_TEXT_DOMAIN)]);
        }

        $order = array_map('absint', $_POST['order']);
        $holidays = get_option('wpm_holidays', []);
        $reordered = [];

        foreach ($order as $index) {
            if (isset($holidays[$index])) {
                $reordered[] = $holidays[$index];
            }
        }

        update_option('wpm_holidays', $reordered);
        wp_send_json_success(['message' => __('Holidays reordered', WPM_TEXT_DOMAIN)]);
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