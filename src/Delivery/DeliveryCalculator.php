<?php
namespace WPM\Delivery;

use WPM\Capacity\CapacityCounter;
use WPM\Settings\Calendar;
use WPM\Utils\PersianDate;
use Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class DeliveryCalculator {
    // تعریف ثابت‌ها
    const CACHE_LONG_TTL = 48 * HOUR_IN_SECONDS;
    const CACHE_SHORT_TTL = 300;
    const DEFAULT_MAX_DAYS = 90;

    public static function init() {
        add_action('wp_ajax_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
        add_action('wp_ajax_nopriv_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
    }

    /**
     * بررسی تعطیلی یک تاریخ
     */
    public static function is_holiday($date) {
        global $wpdb;

        $cache_key = 'holiday_' . $date;
        $is_holiday = \WPM\Utils\Cache::get($cache_key);

        if ($is_holiday === false) {
            $is_holiday = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wpm_holidays WHERE date = %s",
                $date
            ));

            if (!$is_holiday) {
                $weekly_holidays = Calendar::get_weekly_holidays();
                $day_of_week = strtolower(date('l', strtotime($date)));
                $is_holiday = in_array($day_of_week, $weekly_holidays);
            }

            \WPM\Utils\Cache::set($cache_key, $is_holiday ? 1 : 0, self::CACHE_LONG_TTL);
        }

        return $is_holiday ? true : false;
    }

    /**
     * گرفتن روزهای کاری
     */
    public static function get_business_days($start_date, $max_days = self::DEFAULT_MAX_DAYS) {
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
            $date_str = $current_date->format('Y-m-d');
            while ($date_str <= $max_date) {
                $day_of_week = strtolower($current_date->format('l'));

                if (!in_array($date_str, $holidays) && !in_array($day_of_week, $weekly_holidays)) {
                    $business_days[] = $date_str;
                }

                $current_date->modify('+1 day');
                $date_str = $current_date->format('Y-m-d');
            }

            \WPM\Utils\Cache::set($cache_key, $business_days, self::CACHE_LONG_TTL);
        }

        return $business_days;
    }

    /**
     * اضافه کردن روزهای کاری به تاریخ شروع
     */
    public static function add_business_days($start_date, $days) {
        $business_days = self::get_business_days($start_date);
        
        if (empty($business_days)) {
            return $start_date;
        }

        $index = null;
        foreach ($business_days as $i => $business_day) {
            if ($business_day >= $start_date) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return end($business_days);
        }

        return $business_days[$index + $days] ?? end($business_days);
    }

    /**
     * گرفتن سلسله‌مراتب دسته‌بندی‌ها
     */
    public static function get_all_category_hierarchy() {
        $cache_key = 'all_category_hierarchy';
        $hierarchy = \WPM\Utils\Cache::get($cache_key);

        if ($hierarchy === false) {
            $hierarchy = [];
            $all_terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

            foreach ($all_terms as $term) {
                $ancestors = get_ancestors($term->term_id, 'product_cat');
                $hierarchy[$term->term_id] = $ancestors;
            }

            \WPM\Utils\Cache::set($cache_key, $hierarchy, self::CACHE_LONG_TTL);
        }

        return $hierarchy;
    }

    /**
     * گرفتن دسته‌بندی‌های محصول با والدین
     */
    public static function get_product_categories_with_ancestors($product_id) {
        $cache_key = 'product_full_categories_' . $product_id;
        $result = \WPM\Utils\Cache::get($cache_key);

        if ($result === false) {
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            $hierarchy = self::get_all_category_hierarchy();
            $all_categories = array_merge($categories, ...array_map(fn($cat_id) => $hierarchy[$cat_id] ?? [], $categories));
            $result = array_unique($all_categories);

            \WPM\Utils\Cache::set($cache_key, $result, self::CACHE_LONG_TTL);
        }

        return $result;
    }

    /**
     * گرفتن روزهای تولید برای محصولات
     */
    public static function get_delivery_days_for_products($product_ids) {
        global $wpdb;

        if (empty($product_ids)) {
            return [];
        }
        
        $cache_key = 'delivery_days_' . md5(implode(',', $product_ids));
        $results = \WPM\Utils\Cache::get($cache_key);

        if ($results !== false) {
            return $results;
        }

        $ids_placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $product_meta_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM $wpdb->postmeta 
                 WHERE meta_key = 'wpm_delivery_days' 
                 AND post_id IN ($ids_placeholders)",
                $product_ids
            ),
            OBJECT_K
        );

        $product_categories_map = [];
        $all_category_ids = [];

        foreach ($product_ids as $product_id) {
            $full_categories = self::get_product_categories_with_ancestors($product_id);
            $product_categories_map[$product_id] = $full_categories;
            $all_category_ids = array_merge($all_category_ids, $full_categories);
        }

        $all_category_ids = array_unique($all_category_ids);
        $category_meta = [];

        if (!empty($all_category_ids)) {
            $cat_placeholders = implode(',', array_fill(0, count($all_category_ids), '%d'));
            $category_meta = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT term_id, meta_value FROM $wpdb->termmeta 
                     WHERE meta_key = 'wpm_delivery_days' 
                     AND term_id IN ($cat_placeholders)",
                    $all_category_ids
                ),
                OBJECT_K
            );
        }

        $results = [];
        $default_days = absint(get_option('wpm_default_delivery_days', 3));

        foreach ($product_ids as $product_id) {
            if (isset($product_meta_raw[$product_id]) && $product_meta_raw[$product_id]->meta_value !== '') {
                $results[$product_id] = absint($product_meta_raw[$product_id]->meta_value);
                continue;
            }

            $max_delivery_days = 0;
            foreach ($product_categories_map[$product_id] as $category_id) {
                if (isset($category_meta[$category_id])) {
                    $max_delivery_days = max($max_delivery_days, absint($category_meta[$category_id]->meta_value));
                }
            }

            $results[$product_id] = $max_delivery_days > 0 ? $max_delivery_days : $default_days;
        }
        
        \WPM\Utils\Cache::set($cache_key, $results, self::CACHE_SHORT_TTL);

        return $results;
    }

    /**
     * گرفتن تمام ظرفیت‌های حداکثر
     */
    public static function get_all_max_capacities() {
        global $wpdb;

        $cache_key = 'all_max_capacities';
        $max_capacity_map = \WPM\Utils\Cache::get($cache_key);

        if ($max_capacity_map === false) {
            $query = "
                SELECT pc.entity_type, pc.entity_id, pc.max_capacity, 
                       CASE WHEN pc.entity_type = 'category' THEN tt.parent ELSE NULL END AS parent_id
                FROM {$wpdb->prefix}wpm_production_capacity pc
                LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON pc.entity_type = 'category' AND pc.entity_id = tt.term_id
                WHERE pc.max_capacity IS NOT NULL
            ";

            $capacities = $wpdb->get_results($query);
            $max_capacity_map = [];
            foreach ($capacities as $cap) {
                $max_capacity_map[$cap->entity_type][$cap->entity_id] = [
                    'max_capacity' => $cap->max_capacity,
                    'parent_id' => $cap->parent_id
                ];
            }
            \WPM\Utils\Cache::set($cache_key, $max_capacity_map, self::CACHE_LONG_TTL);
        }

        return $max_capacity_map;
    }

    /**
     * گرفتن تعداد رزروها
     */
    public static function get_reserved_counts($min_date, $business_days) {
        global $wpdb;

        $business_days_sql = "'" . implode("','", array_map('esc_sql', $business_days)) . "'";

        // کوئری اصلی برای گرفتن رزروها
        $query = $wpdb->prepare(
            "SELECT cc.date, cc.entity_type, cc.entity_id, SUM(cc.reserved_count) AS reserved_count
            FROM {$wpdb->prefix}wpm_capacity_count cc
            WHERE cc.date >= %s AND cc.date IN ($business_days_sql)
            GROUP BY cc.date, cc.entity_type, cc.entity_id",
            $min_date
        );

        $reserved_counts = $wpdb->get_results($query);
        $reserved_count_map = [];
        $variation_ids = [];

        // مرحله اول: پردازش رکوردها و جمع‌آوری واریانت‌ها
        foreach ($reserved_counts as $res) {
            if (!isset($reserved_count_map[$res->date][$res->entity_type])) {
                $reserved_count_map[$res->date][$res->entity_type] = [];
            }
            $reserved_count_map[$res->date][$res->entity_type][$res->entity_id] = $res->reserved_count;
            
            if ($res->entity_type === 'variation') {
                $variation_ids[] = $res->entity_id;
            }
        }

        // مرحله دوم: دریافت محصولات والد برای واریانت‌ها
        $variation_to_product = [];
        if (!empty($variation_ids)) {
            $variation_ids = array_unique($variation_ids);
            $placeholders = implode(',', array_fill(0, count($variation_ids), '%d'));
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT ID as variation_id, post_parent as product_id 
                FROM {$wpdb->posts} 
                WHERE ID IN ($placeholders) 
                AND post_type = 'product_variation'",
                $variation_ids
            ));
            
            foreach ($results as $row) {
                $variation_to_product[$row->variation_id] = $row->product_id;
            }
        }

        // مرحله سوم: محاسبه رزروهای دسته‌بندی‌ها
        foreach ($reserved_counts as $res) {
            $product_id = ($res->entity_type === 'product') 
                ? $res->entity_id 
                : ($variation_to_product[$res->entity_id] ?? null);

            if (!$product_id) {
                continue;
            }

            $categories = self::get_product_categories_with_ancestors($product_id);
            foreach ($categories as $cat_id) {
                if (!isset($reserved_count_map[$res->date]['category'][$cat_id])) {
                    $reserved_count_map[$res->date]['category'][$cat_id] = 0;
                }
                $reserved_count_map[$res->date]['category'][$cat_id] += $res->reserved_count;
            }
        }

        return $reserved_count_map;
    }

    /**
     * محاسبه تاریخ شروع با در نظر گرفتن زمان قطع روزانه
     */
    private static function get_adjusted_start_date($order_date = null) {
        $cutoff_time = get_option('wpm_daily_cutoff_time', '14:00');
        $start_date = $order_date ?: current_time('Y-m-d');
        $order_time = $order_date ? date('H:i', strtotime($order_date)) : current_time('H:i');

        if ($order_time > $cutoff_time) {
            $start_date = date('Y-m-d', strtotime($start_date . ' +1 day'));
        }

        return $start_date;
    }

    /**
     * بارگذاری داده‌های مشترک برای محاسبات
     */
    private static function load_calculation_data($min_date, $max_days = self::DEFAULT_MAX_DAYS) {
        $business_days = self::get_business_days($min_date, $max_days);
        $max_capacity_map = self::get_all_max_capacities();
        $reserved_count_map = self::get_reserved_counts($min_date, $business_days);
        $default_capacity = absint(get_option('wpm_default_capacity', 0));

        return new CalculationData(
			$business_days, 
			$max_capacity_map, 
			$reserved_count_map, 
			$default_capacity
		);
    }

    /**
     * محاسبه تاریخ تحویل برای یک آیتم
     */
    private static function calculate_single_delivery_date($product_id, $variation_id, $quantity, $order_date, $data, $production_days) {
        if (!is_numeric($product_id) || !is_numeric($quantity) || $quantity <= 0) {
            error_log("Invalid input for calculate_single_delivery_date: product_id=$product_id, quantity=$quantity");
            return null;
        }

        $entity_type = $variation_id ? 'variation' : 'product';
        $entity_id = $variation_id ?: $product_id;
        $categories = self::get_product_categories_with_ancestors($product_id);
        $start_date = self::get_adjusted_start_date($order_date);
        $min_date = self::add_business_days($start_date, $production_days);

        $remaining_quantity = $quantity; // تعداد آیتم‌های باقی‌مانده برای تخصیص
		$allocations = []; // لیست تخصیص‌ها: [['date' => $date, 'quantity' => $qty], ...]
        $available_date = null;

        foreach ($data->business_days as $date) {
            if ($date < $min_date) {
                continue;
            }

            // بررسی ظرفیت محصول
            $product_max_capacity = $data->max_capacity_map[$entity_type][$entity_id]['max_capacity'] ?? null;
            $product_reserved = $data->reserved_count_map[$date][$entity_type][$entity_id] ?? 0;

            if ($product_max_capacity !== null && $product_max_capacity !== 0) {
                // ظرفیت محصول وجود دارد، فقط آن را بررسی می‌کنیم
                $product_remaining_capacity = $product_max_capacity - $product_reserved;

                if ($product_remaining_capacity <= 0) {
                    // هیچ ظرفیتی برای محصول در این روز وجود ندارد
                    continue;
                }

                // تخصیص آیتم‌ها بر اساس ظرفیت محصول
                $allocatable_quantity = min($remaining_quantity, $product_remaining_capacity);
                if ($allocatable_quantity > 0) {
                    // به‌روزرسانی رزروهای محصول در حافظه
                    $data->reserved_count_map[$date][$entity_type][$entity_id] = ($data->reserved_count_map[$date][$entity_type][$entity_id] ?? 0) + $allocatable_quantity;
					
					$allocations[] = [
                        'date' => $date,
                        'quantity' => $allocatable_quantity
                    ];

                    $remaining_quantity -= $allocatable_quantity;
                    $available_date = $date;

                    if ($remaining_quantity <= 0) {
                        // تمام آیتم‌ها تخصیص داده شدند
                        break;
                    }
                }
            } else {
                // ظرفیت محصول وجود ندارد، بررسی ظرفیت دسته‌بندی‌ها
                $min_cat_capacity = null;
                $cat_reserved = 0;

                foreach ($categories as $cat_id) {
                    $current_cat_id = $cat_id;
                    $cat_max_capacity = null;

                    while ($current_cat_id !== null && $cat_max_capacity === null) {
                        if (isset($data->max_capacity_map['category'][$current_cat_id]['max_capacity'])) {
                            $cat_max_capacity = $data->max_capacity_map['category'][$current_cat_id]['max_capacity'];
                        } else {
                            $current_cat_id = $data->max_capacity_map['category'][$current_cat_id]['parent_id'] ?? null;
                        }
                    }

                    if ($cat_max_capacity === null || $cat_max_capacity === 0) {
                        continue;
                    }

                    $current_reserved = $data->reserved_count_map[$date]['category'][$cat_id] ?? 0;
                    $cat_reserved = max($cat_reserved, $current_reserved);
                    $min_cat_capacity = $min_cat_capacity === null ? $cat_max_capacity : min($min_cat_capacity, $cat_max_capacity);
                }

                $effective_capacity = $min_cat_capacity ?? $data->default_capacity;
                $category_remaining_capacity = ($effective_capacity !== 0) ? ($effective_capacity - $cat_reserved) : PHP_INT_MAX;

                if ($category_remaining_capacity <= 0) {
                    // هیچ ظرفیتی برای دسته‌بندی در این روز وجود ندارد
                    continue;
                }

                // تخصیص آیتم‌ها بر اساس ظرفیت دسته‌بندی
                $allocatable_quantity = min($remaining_quantity, $category_remaining_capacity);
                if ($allocatable_quantity > 0) {
                    // به‌روزرسانی رزروهای دسته‌بندی و محصول در حافظه
                    foreach ($categories as $cat_id) {
                        $data->reserved_count_map[$date]['category'][$cat_id] = ($data->reserved_count_map[$date]['category'][$cat_id] ?? 0) + $allocatable_quantity;
                    }
                    // به‌روزرسانی رزروهای محصول در حافظه
                    $data->reserved_count_map[$date][$entity_type][$entity_id] = ($data->reserved_count_map[$date][$entity_type][$entity_id] ?? 0) + $allocatable_quantity;
					
					$allocations[] = [
                        'date' => $date,
                        'quantity' => $allocatable_quantity
                    ];

                    $remaining_quantity -= $allocatable_quantity;
                    $available_date = $date;

                    if ($remaining_quantity <= 0) {
                        // تمام آیتم‌ها تخصیص داده شدند
                        break;
                    }
                }
            }
        }

        if ($remaining_quantity > 0) {
            // اگر همچنان آیتم‌هایی باقی مانده‌اند، به آخرین روز کاری تخصیص می‌دهیم
            $available_date = end($data->business_days);
			$allocations[] = [
                'date' => $available_date,
                'quantity' => $remaining_quantity
            ];
			
			$data->reserved_count_map[$available_date][$entity_type][$entity_id] = ($data->reserved_count_map[$available_date][$entity_type][$entity_id] ?? 0) + $remaining_quantity;
			foreach ($categories as $cat_id) {
				$data->reserved_count_map[$available_date]['category'][$cat_id] = ($data->reserved_count_map[$available_date]['category'][$cat_id] ?? 0) + $remaining_quantity;
			}
			
            error_log("Insufficient capacity for product_id=$product_id, variation_id=$variation_id, remaining_quantity=$remaining_quantity, assigned to last business day: $available_date");
        }

        return [
            'delivery_date' => $available_date,
            'allocations' => $allocations
        ];
    }

    /**
     * محاسبه تاریخ تحویل برای یک محصول
     */
    public static function calculate_delivery_date($product_id, $variation_id, $quantity, $order_date = null) {
        $min_date = $order_date ?: current_time('Y-m-d');
        $data = self::load_calculation_data($min_date);
        $production_days = self::get_delivery_days_for_products([$product_id])[$product_id];

        return self::calculate_single_delivery_date(
            $product_id,
            $variation_id,
            $quantity,
            $order_date,
            $data,
            $production_days
        );
    }

    /**
     * محاسبه گروهی تاریخ‌های تحویل
     */
    public static function calculate_delivery_dates_bulk($items) {
        if (empty($items)) {
            return [];
        }

        $min_order_date = min(array_column($items, 'order_date'));
        $max_order_date = max(array_column($items, 'order_date'));
        $date_diff = (strtotime($max_order_date) - strtotime($min_order_date)) / (60 * 60 * 24);
        $max_days = ceil($date_diff) + self::DEFAULT_MAX_DAYS;

        $data = self::load_calculation_data($min_order_date, $max_days);
        $product_ids = array_unique(array_column($items, 'product_id'));
        $delivery_days_cache = self::get_delivery_days_for_products($product_ids);

        $results = [];
        foreach ($items as $item) {
            $entity_type = $item['variation_id'] ? 'variation' : 'product';
            $entity_id = $item['variation_id'] ?: $item['product_id'];
            $result = self::calculate_single_delivery_date(
                $item['product_id'],
                $item['variation_id'],
                $item['quantity'],
                $item['order_date'],
                $data,
                $delivery_days_cache[$item['product_id']]
            );

            if ($result && $result['delivery_date']) {
                $results[] = [
                    'order_item_id' => $item['order_item_id'],
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'quantity' => $item['quantity'],
                    'delivery_date' => $result['delivery_date'],
                    'allocations' => $result['allocations']
                ];

                // ثبت رزروها برای تمام تخصیص‌ها
                foreach ($result['allocations'] as $allocation) {
                    \WPM\Capacity\CapacityCounter::update_capacity_count(
                        $entity_type,
                        $entity_id,
                        $allocation['date'],
                        $allocation['quantity']
                    );
                }
            }
        }

        return $results;
    }

    /**
     * هندلر AJAX برای گرفتن تاریخ تحویل
     */
    public static function ajax_get_delivery_date() {
        check_ajax_referer('wpm_delivery', 'nonce');

        $product_id = absint($_POST['product_id'] ?? 0);
        $variation_id = absint($_POST['variation_id'] ?? 0);
        $quantity = absint($_POST['quantity'] ?? 1);

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product', WPM_TEXT_DOMAIN)]);
        }

        $result = self::calculate_delivery_date($product_id, $variation_id, $quantity);

        if ($result && $result['delivery_date']) {
            $jalali_date = Jalalian::fromDateTime($result['delivery_date'])->format('Y/m/d');
            wp_send_json_success(['delivery_date' => $jalali_date]);
        }

        wp_send_json_error(['message' => __('No available delivery date', WPM_TEXT_DOMAIN)]);
    }
}
?>