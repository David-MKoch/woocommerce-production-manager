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

    public static function calculate_delivery_date($product_id, $variation_id, $quantity) {
        global $wpdb;

        // Get production days
        $production_days = self::get_delivery_days($product_id);

        $entity_type = $variation_id ? 'variation' : 'product';
        $entity_id = $variation_id ?: $product_id;

        // Get cutoff time
        $cutoff_time = get_option('wpm_cutoff_time', '14:00');
        $current_time = current_time('H:i');
        $today = current_time('Y-m-d');
        $start_date = ($current_time > $cutoff_time) ? date('Y-m-d', strtotime('+1 day')) : $today;

        // Calculate minimum date
        $min_date = self::add_business_days($start_date, $production_days);

        // Get capacity data
        $capacity_data = self::get_capacity_data($min_date);

        // Get all categories (including parents)
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $all_categories = [];
        foreach ($categories as $cat_id) {
            $all_categories[] = $cat_id;
            $ancestors = get_ancestors($cat_id, 'product_cat', 'taxonomy');
            $all_categories = array_merge($all_categories, $ancestors);
        }
        $all_categories = array_unique($all_categories);

        // Find available date
        $available_date = null;
        foreach ($capacity_data as $row) {
            $date = $row->date;

            // Check product/variation capacity
            $product_ok = false;
            foreach ($capacity_data as $p_row) {
                if ($p_row->date === $date && $p_row->entity_type === $entity_type && $p_row->entity_id == $entity_id) {
                    if ($p_row->max_capacity === 0 || ($p_row->reserved_count + $quantity) <= $p_row->max_capacity) {
                        $product_ok = true;
                    }
                    break;
                }
            }

            if (!$product_ok) {
                continue;
            }

            // Check all categories (including parents)
            $categories_ok = true;
            foreach ($all_categories as $cat_id) {
                $cat_found = false;
                foreach ($capacity_data as $c_row) {
                    if ($c_row->date === $date && $c_row->entity_type === 'category' && $c_row->entity_id == $cat_id) {
                        $cat_found = true;
                        if ($c_row->max_capacity !== 0 && ($c_row->reserved_count + $quantity) > $c_row->max_capacity) {
                            $categories_ok = false;
                            break;
                        }
                    }
                }
                // If category not found in capacity data, check its max_capacity
                if (!$cat_found) {
                    $cat_max_capacity = CapacityManager::get_capacity('category', $cat_id);
                    if ($cat_max_capacity !== 0) {
                        $cat_reserved = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(cc.reserved_count)
                            FROM {$wpdb->prefix}wc_capacity_count cc
                            INNER JOIN {$wpdb->prefix}term_relationships tr ON cc.entity_id = tr.object_id
                            WHERE cc.date = %s
                            AND cc.entity_type IN ('product', 'variation')
                            AND tr.term_taxonomy_id = %d",
                            $date,
                            $cat_id
                        ));
                        $cat_reserved = absint($cat_reserved);
                        if (($cat_reserved + $quantity) > $cat_max_capacity) {
                            $categories_ok = false;
                            break;
                        }
                    }
                }
                if (!$categories_ok) {
                    break;
                }
            }

            if ($categories_ok) {
                $available_date = $date;
                break;
            }
        }

        if (!$available_date) {
            // Find first non-holiday date after min_date
            $current_date = new \DateTime($min_date);
            $weekly_holidays = Calendar::get_weekly_holidays();

            while (true) {
                $date_str = $current_date->format('Y-m-d');

                $is_holiday = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}wc_holidays WHERE date = %s",
                    $date_str
                ));

                $day_of_week = strtolower($current_date->format('l'));
                $is_weekly_holiday = in_array($day_of_week, $weekly_holidays);

                if (!$is_holiday && !$is_weekly_holiday) {
                    // Check category capacity
                    $category_has_capacity = true;
                    foreach ($all_categories as $cat_id) {
                        $cat_max_capacity = CapacityManager::get_capacity('category', $cat_id);
                        if ($cat_max_capacity === 0) {
                            continue;
                        }
                        $cat_reserved = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(cc.reserved_count)
                            FROM {$wpdb->prefix}wc_capacity_count cc
                            INNER JOIN {$wpdb->prefix}term_relationships tr ON cc.entity_id = tr.object_id
                            WHERE cc.date = %s
                            AND cc.entity_type IN ('product', 'variation')
                            AND tr.term_taxonomy_id = %d",
                            $date_str,
                            $cat_id
                        ));

                        $cat_reserved = absint($cat_reserved);
                        if (($cat_reserved + $quantity) > $cat_max_capacity) {
                            $category_has_capacity = false;
                            break;
                        }
                    }

                    if ($category_has_capacity) {
                        $available_date = $date_str;
                        break;
                    }
                }

                $current_date->modify('+1 day');
            }
        }

        return $available_date;
    }

    public static function get_capacity_data($min_date) {
        global $wpdb;

        // Cache key
        $cache_key = 'capacity_data_' . md5($min_date);
        $capacity_data = \WPM\Utils\Cache::get($cache_key);

        if ($capacity_data === false) {
            // Get weekly holidays
            $weekly_holidays = Calendar::get_weekly_holidays();
            $weekly_holidays_sql = [];
            foreach ($weekly_holidays as $day) {
                $weekly_holidays_sql[] = "DAYNAME(date) != '$day'";
            }
            $weekly_holidays_condition = !empty($weekly_holidays_sql) ? 'AND (' . implode(' AND ', $weekly_holidays_sql) . ')' : '';

            // Query for products/variations and categories
            $query = $wpdb->prepare(
                "SELECT cc.date, cc.entity_type, cc.entity_id, cc.reserved_count, pc.max_capacity, NULL AS parent_id
                FROM {$wpdb->prefix}wc_capacity_count cc
                INNER JOIN {$wpdb->prefix}wc_production_capacity pc ON cc.entity_type = pc.entity_type AND cc.entity_id = pc.entity_id
                LEFT JOIN {$wpdb->prefix}wc_holidays h ON cc.date = h.date
                WHERE cc.date >= %s
                AND h.id IS NULL
                $weekly_holidays_condition
                UNION
                SELECT cc.date, 'category' AS entity_type, tr.term_taxonomy_id AS entity_id, 
                       SUM(cc.reserved_count) AS reserved_count, pc.max_capacity, tt.parent AS parent_id
                FROM {$wpdb->prefix}wc_capacity_count cc
                INNER JOIN {$wpdb->prefix}term_relationships tr ON cc.entity_id = tr.object_id
                INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_id
                LEFT JOIN {$wpdb->prefix}wc_production_capacity pc ON pc.entity_type = 'category' AND pc.entity_id = tr.term_taxonomy_id
                LEFT JOIN {$wpdb->prefix}wc_holidays h ON cc.date = h.date
                WHERE cc.date >= %s
                AND h.id IS NULL
                $weekly_holidays_condition
                AND cc.entity_type IN ('product', 'variation')
                GROUP BY cc.date, tr.term_taxonomy_id
                ORDER BY date ASC",
                $min_date,
                $min_date
            );

            $capacity_data = $wpdb->get_results($query);

            // Cache the result
            \WPM\Utils\Cache::set($cache_key, $capacity_data, 60); // Cache for 60 seconds
        }

        return $capacity_data;
    }

    public static function get_delivery_days($product_id) {
        // Get delivery days by product/category
        $product_delivery_days = get_post_meta($product_id, 'wpm_delivery_days', true);
        if ($product_delivery_days !== '') {
            return absint($product_delivery_days);
        }
        // Check category meta (return max value)
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        $max_delivery_days = 0;
        foreach ($categories as $category_id) {
            $category_delivery_days = get_term_meta($category_id, 'wpm_delivery_days', true);
            if ($category_delivery_days && absint($category_delivery_days) > $max_delivery_days) {
                $max_delivery_days = absint($category_delivery_days);
            }
        }
        if ($max_delivery_days > 0) {
            return $max_delivery_days;
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
                "SELECT id FROM {$wpdb->prefix}wpm_holidays WHERE date = %s",
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