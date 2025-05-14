<?php
namespace WPM\Capacity;

use WPM\Capacity\CapacityManager;

defined('ABSPATH') || exit;

class CapacityCounter {
    public static function init() {
        // Update capacity count when order item status changes
        add_action('wpm_order_item_status_changed', [__CLASS__, 'handle_status_change'], 10, 2);
        // Update capacity count when order is placed
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'update_capacity_on_order'], 10, 3);
    }

    public static function get_reserved_count($entity_type, $entity_id, $date) {
        global $wpdb;

        $cache_key = "capacity_count_{$entity_type}_{$entity_id}_" . $date;
        $count = \WPM\Utils\Cache::get($cache_key);

        if ($count === false) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT reserved_count FROM {$wpdb->prefix}wpm_capacity_count WHERE entity_type = %s AND entity_id = %d AND date = %s",
                $entity_type,
                $entity_id,
                $date
            ));
            \WPM\Utils\Cache::set($cache_key, $count ?: 0);
        }

        return absint($count);
    }

    public static function update_capacity_count($entity_type, $entity_id, $date, $quantity) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, reserved_count FROM {$wpdb->prefix}wpm_capacity_count WHERE entity_type = %s AND entity_id = %d AND date = %s",
            $entity_type,
            $entity_id,
            $date
        ));

        if ($existing) {
            $new_count = max(0, $existing->reserved_count + $quantity);
            $wpdb->update(
                "{$wpdb->prefix}wpm_capacity_count",
                ['reserved_count' => $new_count],
                ['id' => $existing->id],
                ['%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                "{$wpdb->prefix}wpm_capacity_count",
                [
                    'date' => $date,
                    'entity_type' => $entity_type,
                    'entity_id' => $entity_id,
                    'reserved_count' => max(0, $quantity)
                ],
                ['%s', '%s', '%d', '%d']
            );
        }

        // Clear cache
        \WPM\Utils\Cache::clear("capacity_count_{$entity_type}_{$entity_id}_" . $date);
    }

    public static function handle_status_change($order_item_id, $new_status) {
        if ($new_status !== 'completed') {
            return;
        }

        global $wpdb;

        // Get order item details
        $order_item = $wpdb->get_row($wpdb->prepare(
            "SELECT order_id, product_id, variation_id, quantity FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
            $order_item_id
        ));

        if (!$order_item) {
            return;
        }

        $product_id = $order_item->variation_id ?: $order_item->product_id;
        $entity_type = $order_item->variation_id ? 'variation' : 'product';
        $entity_id = $product_id;

        // Get delivery date
        $delivery_date = $wpdb->get_var($wpdb->prepare(
            "SELECT delivery_date FROM {$wpdb->prefix}wpm_order_items_status WHERE order_item_id = %d",
            $order_item_id
        ));

        if (!$delivery_date) {
            return;
        }

        // If completed early, reset capacity
        $today = current_time('Y-m-d');
        if ($delivery_date > $today) {
            self::update_capacity_count($entity_type, $entity_id, $delivery_date, -$order_item->quantity);
        }

        // Update category capacity
        $categories = wp_get_post_terms($order_item->product_id, 'product_cat', ['fields' => 'ids']);
        foreach ($categories as $category_id) {
            if ($delivery_date > $today) {
                self::update_capacity_count('category', $category_id, $delivery_date, -$order_item->quantity);
            }
        }
    }

    public static function update_capacity_on_order($order_id, $posted_data, $order) {
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $entity_type = $item->get_variation_id() ? 'variation' : 'product';
            $entity_id = $product_id;
            $quantity = $item->get_quantity();

            // Calculate delivery date (will be implemented in Delivery module)
            $delivery_date = \WPM\Delivery\DeliveryCalculator::calculate_delivery_date($product_id, $quantity);

            if ($delivery_date) {
                self::update_capacity_count($entity_type, $entity_id, $delivery_date, $quantity);

                // Update category capacity
                $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
                foreach ($categories as $category_id) {
                    self::update_capacity_count('category', $category_id, $delivery_date, $quantity);
                }
            }
        }
    }

    public static function has_capacity($entity_type, $entity_id, $date, $quantity) {
        $max_capacity = CapacityManager::get_capacity($entity_type, $entity_id);
        if (!$max_capacity) {
            return true; // No limit set
        }

        $reserved = self::get_reserved_count($entity_type, $entity_id, $date);
        return ($reserved + $quantity) <= $max_capacity;
    }
}
?>