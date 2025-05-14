<?php
namespace WPM\Delivery;

use WPM\Capacity\CapacityCounter;
use WPM\Settings\Calendar;
//use WPM\Utils\PersianDate;
use Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class DeliveryCalculator {
    public static function init() {
        // Register AJAX handler for product page
        add_action('wp_ajax_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
        add_action('wp_ajax_nopriv_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
    }

    public static function calculate_delivery_date($product_id, $quantity, $variation_id = 0) {
        $entity_type = $variation_id ? 'variation' : 'product';
        $entity_id = $variation_id ?: $product_id;

        // Get delivery days
        $delivery_days = self::get_delivery_days($product_id);

        // Get cutoff time
        $cutoff_time = get_option('wpm_cutoff_time', '14:00');
        $current_time = current_time('H:i');
        $today = current_time('Y-m-d');
        $start_date = ($current_time > $cutoff_time) ? date('Y-m-d', strtotime('+1 day')) : $today;

        // Find first available date with capacity
        $max_attempts = 30; // Prevent infinite loop
        $attempt = 0;
        $current_date = $start_date;

        while ($attempt < $max_attempts) {
            if (!self::is_holiday($current_date) && CapacityCounter::has_capacity($entity_type, $entity_id, $current_date, $quantity)) {
                // Found a valid date, add delivery days
                $delivery_date = self::add_business_days($current_date, $delivery_days);
                return $delivery_date;
            }
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            $attempt++;
        }

        return false; // No available date found
    }

    public static function get_delivery_days($product_id) {
        // Check product meta
        $product_delivery_days = get_post_meta($product_id, 'wpm_delivery_days', true);
        if ($product_delivery_days) {
            return absint($product_delivery_days);
        }

        // Check category meta
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        foreach ($categories as $category_id) {
            $category_delivery_days = get_term_meta($category_id, 'wpm_delivery_days', true);
            if ($category_delivery_days) {
                return absint($category_delivery_days);
            }
        }

        // Fallback to default
        return absint(get_option('wpm_default_delivery_days', 3));
    }

    public static function is_holiday($date) {
        global $wpdb;

        $cache_key = 'holiday_' . $date;
        $is_holiday = \WPM\Utils\Cache::get($cache_key);

        if ($is_holiday === false) {
            // Check specific holiday
            $is_holiday = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wpm_holidays WHERE date = %s AND type = 'custom'",
                $date
            ));

            if (!$is_holiday) {
                // Check weekly holidays
                $weekly_holidays = Calendar::get_weekly_holidays();
                $day_of_week = strtolower(date('l', strtotime($date)));
                $is_holiday = in_array($day_of_week, $weekly_holidays);
            }

            \WPM\Utils\Cache::set($cache_key, $is_holiday ? 1 : 0);
        }

        return $is_holiday ? true : false;
    }

    public static function add_business_days($start_date, $days) {
        $current_date = $start_date;
        $added_days = 0;

        while ($added_days < $days) {
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            if (!self::is_holiday($current_date)) {
                $added_days++;
            }
        }

        return $current_date;
    }

    public static function ajax_get_delivery_date() {
        check_ajax_referer('wpm_delivery', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product', WPM_TEXT_DOMAIN)]);
        }

        $delivery_date = self::calculate_delivery_date($product_id, $quantity, $variation_id);

        if ($delivery_date) {
            $jalali_date = Jalalian::fromDateTime($delivery_date)->format('Y/m/d');
            wp_send_json_success(['delivery_date' => $jalali_date]);
        } else {
            wp_send_json_error(['message' => __('No available delivery date', WPM_TEXT_DOMAIN)]);
        }
    }
}
?>