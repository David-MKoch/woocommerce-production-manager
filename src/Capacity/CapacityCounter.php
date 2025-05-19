<?php
namespace WPM\Capacity;

defined('ABSPATH') || exit;

class CapacityCounter {
    public static function init() {
        // Update capacity count when order is placed
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'update_capacity_on_order'], 10, 3);
        // Free capacity when order status changes to completed
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 10, 4);
    }

    public static function get_reserved_count($entity_type, $entity_id, $date) {
        global $wpdb;

        $cache_key = "capacity_count_{$entity_type}_{$entity_id}_" . $date;
        $count = \WPM\Utils\Cache::get($cache_key);

        if ($count === false) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(reserved_count) FROM {$wpdb->prefix}wpm_capacity_count WHERE entity_type = %s AND entity_id = %d AND date = %s",
                $entity_type,
                $entity_id,
                $date
            ));
            \WPM\Utils\Cache::set($cache_key, $count ?: 0, 300);
        }

        return absint($count);
    }

    public static function update_capacity_count($entity_type, $entity_id, $date, $quantity) {
        global $wpdb;

		if (!in_array($entity_type, ['product', 'variation']) || !is_numeric($entity_id) || !is_numeric($quantity) || !$date) {
            error_log("Invalid input for update_capacity_count: entity_type=$entity_type, entity_id=$entity_id, quantity=$quantity, date=$date");
            return false;
        }

        $table = "{$wpdb->prefix}wpm_capacity_count";
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, reserved_count FROM $table WHERE date = %s AND entity_type = %s AND entity_id = %d",
            $date, $entity_type, $entity_id
        ));

        if ($existing) {
            $new_count = max(0, $existing->reserved_count + $quantity);
            $wpdb->update(
                $table,
                ['reserved_count' => $new_count],
                ['id' => $existing->id],
                ['%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'date' => $date,
                    'entity_type' => $entity_type,
                    'entity_id' => $entity_id,
                    'reserved_count' => max(0, abs($quantity))
                ],
                ['%s', '%s', '%d', '%d']
            );
        }

        // Clear cache
        \WPM\Utils\Cache::clear("capacity_count_{$entity_type}_{$entity_id}_" . $date);
        \WPM\Utils\Cache::clear("capacity_data_"); // Clear cached capacity data
		\WPM\Utils\Cache::clear("reserved_counts_");
    }

    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if ($new_status !== 'completed') {
            return;
        }

        global $wpdb;

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            // Get delivery date
            $delivery_date = $wpdb->get_var($wpdb->prepare(
                "SELECT delivery_date FROM {$wpdb->prefix}wpm_order_items_status WHERE order_item_id = %d",
                $item_id
            ));

            if (!$delivery_date) {
                continue;
            }

			// If completed early, reset capacity
			$today = current_time('Y-m-d');
			if ($delivery_date > $today) {
				// Free capacity for product/variation
				$entity_type = $variation_id ? 'variation' : 'product';
				$entity_id = $variation_id ?: $product_id;
				self::update_capacity_count($entity_type, $entity_id, $delivery_date, -$quantity);
			}
        }
    }

    public static function update_capacity_on_order($order_id, $posted_data, $order) {
        global $wpdb;

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();

            // Calculate delivery date
            $delivery_date = \WPM\Delivery\DeliveryCalculator::calculate_delivery_date($product_id, $variation_id, $quantity);

            if ($delivery_date) {
                $entity_type = $variation_id ? 'variation' : 'product';
                $entity_id = $variation_id ?: $product_id;
                self::update_capacity_count($entity_type, $entity_id, $delivery_date, $quantity);

                // Store delivery date
                $default_status = get_option('wpm_statuses', [['name' => __('Received', WPM_TEXT_DOMAIN), 'color' => '#0073aa']])[0]['name'];
				$user_id = get_current_user_id();
                $wpdb->insert(
                    "{$wpdb->prefix}wpm_order_items_status",
                    [
                        'order_id' => $order_id,
                        'order_item_id' => $item_id,
                        'status' => $default_status,
                        'delivery_date' => $delivery_date,
                        'updated_by' => $user_id,
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s', '%d', '%s']
                );
				
				\WPM\Settings\StatusManager::log_status_change($item_id, $default_status, $user_id, __('Initial status set', WPM_TEXT_DOMAIN));
            }
        }
    }
}
?>