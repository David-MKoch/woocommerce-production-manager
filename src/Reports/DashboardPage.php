<?php
namespace WPM\Reports;

defined('ABSPATH') || exit;

class DashboardPage {

    public static function render_page() {
        global $wpdb;
        $today = current_time('Y-m-d');
        $total_capacity = $wpdb->get_var("SELECT SUM(max_capacity) FROM {$wpdb->prefix}wpm_production_capacity");
        $used_capacity = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(reserved_count) FROM {$wpdb->prefix}wpm_capacity_count WHERE date = %s",
            $today
        ));
        $delayed_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpm_order_items_status WHERE delivery_date < %s",
            $today
        ));
        $sms_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpm_sms_logs WHERE status = 'success' AND DATE(sent_at) = %s",
            $today
        ));

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Production Dashboard', WPM_TEXT_DOMAIN); ?></h1>
            <div class="wpm-dashboard">
                <div class="wpm-stats">
                    <div class="wpm-stat-box">
                        <h3><?php esc_html_e('Total Capacity', WPM_TEXT_DOMAIN); ?></h3>
                        <p><?php echo esc_html($total_capacity ?: 0); ?></p>
                    </div>
                    <div class="wpm-stat-box">
                        <h3><?php esc_html_e('Used Capacity Today', WPM_TEXT_DOMAIN); ?></h3>
                        <p><?php echo esc_html($used_capacity ?: 0); ?></p>
                    </div>
                    <div class="wpm-stat-box">
                        <h3><?php esc_html_e('Delayed Orders', WPM_TEXT_DOMAIN); ?></h3>
                        <p><?php echo esc_html($delayed_orders ?: 0); ?></p>
                    </div>
                    <div class="wpm-stat-box">
                        <h3><?php esc_html_e('SMS Sent Today', WPM_TEXT_DOMAIN); ?></h3>
                        <p><?php echo esc_html($sms_sent ?: 0); ?></p>
                    </div>
                </div>
                <div class="wpm-charts">
                    <div class="wpm-chart-section">
                        <h2><?php esc_html_e('Capacity Usage (Last 30 Days)', WPM_TEXT_DOMAIN); ?></h2>
                        <canvas id="capacityChart"></canvas>
                    </div>
                    <div class="wpm-chart-section">
                        <h2><?php esc_html_e('Delayed Orders (Last 30 Days)', WPM_TEXT_DOMAIN); ?></h2>
                        <canvas id="delayedOrdersChart"></canvas>
                    </div>
                    <div class="wpm-chart-section">
                        <h2><?php esc_html_e('SMS Statistics', WPM_TEXT_DOMAIN); ?></h2>
                        <canvas id="smsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function get_dashboard_data() {
        check_ajax_referer('wpm_dashboard', 'nonce');
        global $wpdb;

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = current_time('Y-m-d');

        switch ($type) {
            case 'capacity':
                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT date, SUM(reserved_count) as value
                     FROM {$wpdb->prefix}wpm_capacity_count
                     WHERE date BETWEEN %s AND %s
                     GROUP BY date
                     ORDER BY date",
                    $start_date,
                    $end_date
                ));
                $dates = [];
                $values = [];
                $current_date = new \DateTime($start_date);
                while ($current_date <= new \DateTime($end_date)) {
                    $date_str = $current_date->format('Y-m-d');
                    $dates[] = $date_str;
                    $value = 0;
                    foreach ($data as $row) {
                        if ($row->date === $date_str) {
                            $value = (int)$row->value;
                            break;
                        }
                    }
                    $values[] = $value;
                    $current_date->modify('+1 day');
                }
                wp_send_json_success(['dates' => $dates, 'values' => $values]);
                break;

            case 'delayed_orders':
                $data = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(updated_at) as date, COUNT(*) as value
                     FROM {$wpdb->prefix}wpm_order_items_status
                     WHERE delivery_date < %s
                     AND updated_at BETWEEN %s AND %s
                     GROUP BY DATE(updated_at)
                     ORDER BY DATE(updated_at)",
                    $end_date,
                    $start_date,
                    $end_date
                ));
                $dates = [];
                $values = [];
                $current_date = new \DateTime($start_date);
                while ($current_date <= new \DateTime($end_date)) {
                    $date_str = $current_date->format('Y-m-d');
                    $dates[] = $date_str;
                    $value = 0;
                    foreach ($data as $row) {
                        if ($row->date === $date_str) {
                            $value = (int)$row->value;
                            break;
                        }
                    }
                    $values[] = $value;
                    $current_date->modify('+1 day');
                }
                wp_send_json_success(['dates' => $dates, 'values' => $values]);
                break;

            case 'sms':
                $success = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wpm_sms_logs
                     WHERE status = 'success' AND sent_at BETWEEN %s AND %s",
                    $start_date,
                    $end_date
                ));
                $failed = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wpm_sms_logs
                     WHERE status = 'failed' AND sent_at BETWEEN %s AND %s",
                    $start_date,
                    $end_date
                ));
                wp_send_json_success(['success' => (int)$success, 'failed' => (int)$failed]);
                break;
        }

        wp_send_json_error(['message' => __('Invalid request', WPM_TEXT_DOMAIN)]);
    }
}
?>