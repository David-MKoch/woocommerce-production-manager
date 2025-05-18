<?php
namespace WPM\Delivery;

use WPM\Capacity\CapacityCounter;
use WPM\Settings\Calendar;
use WPM\Utils\PersianDate;
use Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class DeliveryCalculator {
    public static function init() {
        // Register AJAX handler for product page
        add_action('wp_ajax_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
        add_action('wp_ajax_nopriv_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
    }
	
	public static function is_holiday($date) {
        global $wpdb;

        $cache_key = 'holiday_' . $date;
        $is_holiday = \WPM\Utils\Cache::get($cache_key);

        if ($is_holiday === false) {
            // Check specific holiday
            $is_holiday = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wpm_holidays WHERE date = %s",
                $date
            ));

            if (!$is_holiday) {
                // Check weekly holidays
                $weekly_holidays = Calendar::get_weekly_holidays();
                $day_of_week = strtolower(date('l', strtotime($date)));
                $is_holiday = in_array($day_of_week, $weekly_holidays);
            }

            \WPM\Utils\Cache::set($cache_key, $is_holiday ? 1 : 0, 48 * HOUR_IN_SECONDS);
        }

        return $is_holiday ? true : false;
    }
	
	public static function get_business_days($start_date, $max_days = 90) {
        global $wpdb;

        $cache_key = 'business_days_' . md5($start_date . '_' . $max_days);
        $business_days = \WPM\Utils\Cache::get($cache_key);

        if ($business_days === false) {
            $max_date = (new \DateTime($start_date))->modify("+$max_days days")->format('Y-m-d');
            $holidays = $wpdb->get_col($wpdb->prepare(
                "SELECT date FROM {$wpdb->prefix}wpm_holidays WHERE date BETWEEN %s AND %s",
                $start_date,
                $max_date
            ));

            $weekly_holidays = Calendar::get_weekly_holidays();
            $business_days = [];
            $current_date = new \DateTime($start_date);

            while ($current_date->format('Y-m-d') <= $max_date) {
                $date_str = $current_date->format('Y-m-d');
                $day_of_week = strtolower($current_date->format('l'));

                if (!in_array($date_str, $holidays) && !in_array($day_of_week, $weekly_holidays)) {
                    $business_days[] = $date_str;
                }
                $current_date->modify('+1 day');
            }

            \WPM\Utils\Cache::set($cache_key, $business_days, 48 * HOUR_IN_SECONDS);
        }

        return $business_days;
    }

    public static function add_business_days($start_date, $days) {
        $business_days = self::get_business_days($start_date, $days + 100);
        $index = array_search($start_date, $business_days);

        if ($index === false) {
            $index = 0;
        }

        if (isset($business_days[$index + $days])) {
            return $business_days[$index + $days];
        }

        return end($business_days);
    }

    public static function get_delivery_days($product_id) {
        $product_delivery_days = get_post_meta($product_id, 'wpm_delivery_days', true);
        if ($product_delivery_days !== '') {
            return absint($product_delivery_days);
        }

        $all_categories = self::get_categories($product_id);
        $max_delivery_days = 0;
        foreach ($all_categories as $category_id) {
            $category_delivery_days = get_term_meta($category_id, 'wpm_delivery_days', true);
            if ($category_delivery_days && absint($category_delivery_days) > $max_delivery_days) {
                $max_delivery_days = absint($category_delivery_days);
            }
        }
        if ($max_delivery_days > 0) {
            return $max_delivery_days;
        }

        return absint(get_option('wpm_default_delivery_days', 3));
    }

    public static function get_all_max_capacities() {
        global $wpdb;

        $cache_key = 'all_max_capacities';
        $capacities = \WPM\Utils\Cache::get($cache_key);

        if ($capacities === false) {
            $query = "
                SELECT pc.entity_type, pc.entity_id, pc.max_capacity, 
                       CASE WHEN pc.entity_type = 'category' THEN tt.parent ELSE NULL END AS parent_id
                FROM {$wpdb->prefix}wpm_production_capacity pc
                LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON pc.entity_type = 'category' AND pc.entity_id = tt.term_id
                WHERE pc.max_capacity IS NOT NULL
            ";

            $capacities = $wpdb->get_results($query);
            \WPM\Utils\Cache::set($cache_key, $capacities, 48 * HOUR_IN_SECONDS);
        }

        return $capacities;
    }

    public static function get_reserved_counts($min_date) {
        global $wpdb;

        $cache_key = 'reserved_counts_' . md5($min_date);
        $reserved_counts = \WPM\Utils\Cache::get($cache_key);

        if ($reserved_counts === false) {
            $business_days = self::get_business_days($min_date);
            $business_days_sql = "'" . implode("','", array_map('esc_sql', $business_days)) . "'";

            $query = $wpdb->prepare(
                "SELECT cc.date, cc.entity_type, cc.entity_id, SUM(cc.reserved_count) AS reserved_count
                 FROM {$wpdb->prefix}wpm_capacity_count cc
                 WHERE cc.date >= %s AND cc.date IN ($business_days_sql)
                 GROUP BY cc.date, cc.entity_type, cc.entity_id",
                $min_date
            );

            $reserved_counts = $wpdb->get_results($query);
            $reserved_counts = self::aggregate_category_reservations($reserved_counts, $business_days);
            \WPM\Utils\Cache::set($cache_key, $reserved_counts, 300);
        }

        return $reserved_counts;
    }
	
	public static function aggregate_category_reservations($reserved_counts, $business_days) {
        global $wpdb;

        $product_reservations = [];
        foreach ($reserved_counts as $res) {
            if ($res->entity_type === 'product' || $res->entity_type === 'variation') {
                $product_reservations[$res->date][$res->entity_id] = [
                    'entity_type' => $res->entity_type,
                    'reserved_count' => $res->reserved_count
                ];
            }
        }

        $category_reservations = [];
        foreach ($product_reservations as $date => $products) {
            foreach ($products as $entity_id => $data) {
                $product_id = $data['entity_type'] === 'product' ? $entity_id : $wpdb->get_var($wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = '_product_id'",
                    $entity_id
                ));

                if (!$product_id) {
                    continue;
                }

                $categories = self::get_categories($product_id);
                foreach ($categories as $cat_id) {
                    if (!isset($category_reservations[$date]['category'][$cat_id])) {
                        $category_reservations[$date]['category'][$cat_id] = 0;
                    }
                    $category_reservations[$date]['category'][$cat_id] += $data['reserved_count'];
                }
            }
        }

        $result = $reserved_counts;
        foreach ($category_reservations as $date => $cats) {
            foreach ($cats['category'] as $cat_id => $reserved_count) {
                $result[] = (object) [
                    'date' => $date,
                    'entity_type' => 'category',
                    'entity_id' => $cat_id,
                    'reserved_count' => $reserved_count
                ];
            }
        }

        return $result;
    }

    public static function get_categories($product_id){
        $cache_key = 'categories_' . $product_id;
        $all_categories = \WPM\Utils\Cache::get($cache_key);

        if ($all_categories === false) {
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            $all_categories = [];
            foreach ($categories as $cat_id) {
                $all_categories[] = $cat_id;
                $ancestors = get_ancestors($cat_id, 'product_cat', 'taxonomy');
                $all_categories = array_merge($all_categories, $ancestors);
            }
            $all_categories = array_unique($all_categories);
            \WPM\Utils\Cache::set($cache_key, $all_categories, 48 * HOUR_IN_SECONDS);
        }
        return $all_categories;
    }

    public static function calculate_delivery_date($product_id, $variation_id, $quantity) {
        global $wpdb;

        if (!is_numeric($product_id) || !is_numeric($quantity) || $quantity <= 0) {
            //error_log("Invalid input for calculate_delivery_date: product_id=$product_id, quantity=$quantity");
            return null;
        }

        $production_days = self::get_delivery_days($product_id);

        $entity_type = $variation_id ? 'variation' : 'product';
        $entity_id = $variation_id ?: $product_id;

        $cutoff_time = get_option('wpm_daily_cutoff_time', '14:00');
        $current_time = current_time('H:i');
        $today = current_time('Y-m-d');
        $start_date = ($current_time > $cutoff_time) ? date('Y-m-d', strtotime('+1 day')) : $today;

        $cache_key = 'min_date_' . $product_id . '_' . $production_days . '_' . $today;
        $min_date = \WPM\Utils\Cache::get($cache_key);

        if ($min_date === false) {
            $min_date = self::add_business_days($start_date, $production_days);
            \WPM\Utils\Cache::set($cache_key, $min_date, HOUR_IN_SECONDS);
        }

        $business_days = self::get_business_days($min_date);
        $max_capacities = self::get_all_max_capacities();
        $reserved_counts = self::get_reserved_counts($min_date);

        $all_categories = self::get_categories($product_id);

        $default_capacity = absint(get_option('wpm_default_capacity', 0));

        $max_capacity_map = [];
        foreach ($max_capacities as $cap) {
            $max_capacity_map[$cap->entity_type][$cap->entity_id] = [
                'max_capacity' => $cap->max_capacity,
                'parent_id' => $cap->parent_id
            ];
        }

        $reserved_count_map = [];
        foreach ($reserved_counts as $res) {
            $reserved_count_map[$res->date][$res->entity_type][$res->entity_id] = $res->reserved_count;
        }

        $available_date = null;

        foreach ($business_days as $date) {
            if ($date < $min_date) {
                continue;
            }

            $product_max_capacity = isset($max_capacity_map[$entity_type][$entity_id]['max_capacity']) 
                ? $max_capacity_map[$entity_type][$entity_id]['max_capacity'] 
                : null;
            $product_reserved = isset($reserved_count_map[$date][$entity_type][$entity_id]) 
                ? $reserved_count_map[$date][$entity_type][$entity_id] 
                : 0;

            if ($product_max_capacity !== null && $product_max_capacity !== 0) {
                if (($product_reserved + $quantity) <= $product_max_capacity) {
                    $available_date = $date;
                    break;
                }
                continue;
            }

            $min_cat_capacity = null;
            $cat_reserved = 0;

            foreach ($all_categories as $cat_id) {
                $current_cat_id = $cat_id;
                $cat_max_capacity = null;

                while ($current_cat_id !== null && $cat_max_capacity === null) {
                    if (isset($max_capacity_map['category'][$current_cat_id]['max_capacity'])) {
                        $cat_max_capacity = $max_capacity_map['category'][$current_cat_id]['max_capacity'];
                    } else {
                        $current_cat_id = isset($max_capacity_map['category'][$current_cat_id]['parent_id']) 
                            ? $max_capacity_map['category'][$current_cat_id]['parent_id'] 
                            : null;
                    }
                }

                if ($cat_max_capacity === null || $cat_max_capacity === 0) {
                    continue;
                }

                $current_reserved = isset($reserved_count_map[$date]['category'][$cat_id]) 
                    ? $reserved_count_map[$date]['category'][$cat_id] 
                    : 0;
                $cat_reserved = max($cat_reserved, $current_reserved);

                $min_cat_capacity = is_null($min_cat_capacity) ? $cat_max_capacity : min($min_cat_capacity, $cat_max_capacity);
            }

            $effective_capacity = is_null($min_cat_capacity) ? $default_capacity : $min_cat_capacity;

            if ($effective_capacity === 0 || ($cat_reserved + $quantity) <= $effective_capacity) {
                $available_date = $date;
                break;
            }
        }

        if (!$available_date) {
            //error_log("No available date found for product_id: $product_id, variation_id: $variation_id");
            $available_date = end($business_days);
        }

        return $available_date;
    }

    public static function ajax_get_delivery_date() {
        check_ajax_referer('wpm_delivery', 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product', WPM_TEXT_DOMAIN)]);
        }

        $delivery_date = self::calculate_delivery_date($product_id, $variation_id, $quantity);

        if ($delivery_date) {
            $jalali_date = Jalalian::fromDateTime($delivery_date)->format('Y/m/d');
            wp_send_json_success(['delivery_date' => $jalali_date]);
        } else {
            wp_send_json_error(['message' => __('No available delivery date', WPM_TEXT_DOMAIN)]);
        }
    }
}
?>