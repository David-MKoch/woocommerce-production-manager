<?php
namespace WPM\Capacity;

defined('ABSPATH') || exit;

class CapacityManager {
    public static function init() {
        // Add capacity fields to product and category edit pages
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_product_capacity_field']);
        add_action('woocommerce_variation_options_pricing', [__CLASS__, 'add_variation_capacity_field'], 10, 3);
        add_action('product_cat_add_form_fields', [__CLASS__, 'add_category_capacity_field']);
        add_action('product_cat_edit_form_fields', [__CLASS__, 'edit_category_capacity_field'], 10, 1);

        // Save capacity fields
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_capacity']);
        add_action('woocommerce_save_product_variation', [__CLASS__, 'save_variation_capacity'], 10, 2);
        add_action('created_product_cat', [__CLASS__, 'save_category_capacity']);
        add_action('edited_product_cat', [__CLASS__, 'save_category_capacity']);
    }

    public static function add_product_capacity_field() {
        woocommerce_wp_text_input([
            'id'          => 'wpm_product_capacity',
            'label'       => __('Daily Production Capacity', 'woocommerce-production-manager'),
            'desc_tip'    => true,
            'description' => __('Maximum number of this product that can be produced daily. Leave empty to inherit from category.', 'woocommerce-production-manager'),
            'type'        => 'number',
            'value'       => self::get_capacity('product', get_the_ID())
        ]);
    }

    public static function add_variation_capacity_field($loop, $variation_data, $variation) {
        woocommerce_wp_text_input([
            'id'          => 'wpm_variation_capacity_' . $variation->ID,
            'label'       => __('Daily Production Capacity', 'woocommerce-production-manager'),
            'desc_tip'    => true,
            'description' => __('Maximum number of this variation that can be produced daily. Leave empty to inherit from product.', 'woocommerce-production-manager'),
            'type'        => 'number',
            'value'       => self::get_capacity('variation', $variation->ID)
        ]);
    }

    public static function add_category_capacity_field() {
        ?>
        <div class="form-field">
            <label for="wpm_category_capacity"><?php esc_html_e('Daily Production Capacity', 'woocommerce-production-manager'); ?></label>
            <input type="number" name="wpm_category_capacity" id="wpm_category_capacity" min="0">
            <p><?php esc_html_e('Maximum number of products in this category that can be produced daily. Leave empty for no limit.', 'woocommerce-production-manager'); ?></p>
        </div>
        <?php
    }

    public static function edit_category_capacity_field($term) {
        $capacity = self::get_capacity('category', $term->term_id);
        ?>
        <tr class="form-field">
            <th><label for="wpm_category_capacity"><?php esc_html_e('Daily Production Capacity', 'woocommerce-production-manager'); ?></label></th>
            <td>
                <input type="number" name="wpm_category_capacity" id="wpm_category_capacity" value="<?php echo esc_attr($capacity); ?>" min="0">
                <p><?php esc_html_e('Maximum number of products in this category that can be produced daily. Leave empty for no limit.', 'woocommerce-production-manager'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_product_capacity($post_id) {
        $capacity = isset($_POST['wpm_product_capacity']) && $_POST['wpm_product_capacity'] !== '' ? absint($_POST['wpm_product_capacity']) : null;
        self::update_capacity_record('product', $post_id, $capacity);
    }

    public static function save_variation_capacity($variation_id, $i) {
        $capacity = isset($_POST['wpm_variation_capacity_' . $variation_id]) && $_POST['wpm_variation_capacity_' . $variation_id] !== '' ? absint($_POST['wpm_variation_capacity_' . $variation_id]) : null;
        self::update_capacity_record('variation', $variation_id, $capacity);
    }

    public static function save_category_capacity($term_id) {
        $capacity = isset($_POST['wpm_category_capacity']) && $_POST['wpm_category_capacity'] !== '' ? absint($_POST['wpm_category_capacity']) : null;

        // Check parent capacity
        if ($capacity !== null) {
            $ancestors = get_ancestors($term_id, 'product_cat', 'taxonomy');
            foreach ($ancestors as $parent_id) {
                $parent_capacity = self::get_capacity('category', $parent_id);
                if ($parent_capacity !== 0 && $capacity > $parent_capacity) {
                    wp_die(sprintf(
                        __('Category capacity (%d) cannot exceed parent category capacity (%d).', 'woocommerce-production-manager'),
                        $capacity,
                        $parent_capacity
                    ));
                }
            }
        }

        self::update_capacity_record('category', $term_id, $capacity);
    }

    public static function update_capacity_record($entity_type, $entity_id, $capacity) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wpm_production_capacity WHERE entity_type = %s AND entity_id = %d",
            $entity_type,
            $entity_id
        ));

        if (is_null($capacity)) {
            if ($existing) {
                $wpdb->delete(
                    "{$wpdb->prefix}wpm_production_capacity",
                    ['entity_type' => $entity_type, 'entity_id' => $entity_id],
                    ['%s', '%d']
                );
            }
        } else {
            if ($existing) {
                $wpdb->update(
                    "{$wpdb->prefix}wpm_production_capacity",
                    [
                        'max_capacity' => $capacity,
                        'updated_at'   => current_time('mysql')
                    ],
                    [
                        'entity_type' => $entity_type,
                        'entity_id'   => $entity_id
                    ],
                    ['%d', '%s'],
                    ['%s', '%d']
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}wpm_production_capacity",
                    [
                        'entity_type' => $entity_type,
                        'entity_id' => $entity_id,
                        'max_capacity' => $capacity,
                        'created_at'  => current_time('mysql'),
                        'updated_at'  => current_time('mysql')
                    ],
                    ['%s', '%d', '%d', '%s', '%s']
                );
            }
        }

        // Clear cache
        \WPM\Utils\Cache::clear("capacity_{$entity_type}_{$entity_id}");
    }

    public static function get_capacity($entity_type, $entity_id, $product_id = null) {
        // Use product_id in cache key to ensure unique cache for each product context
        $cache_key = "capacity_{$entity_type}_{$entity_id}" . ($product_id ? "_{$product_id}" : "");
        $capacity = \WPM\Utils\Cache::get($cache_key);

        if ($capacity === false) {
            global $wpdb;

			$capacity = $wpdb->get_var($wpdb->prepare(
				"SELECT max_capacity FROM {$wpdb->prefix}wpm_production_capacity WHERE entity_type = %s AND entity_id = %d",
				$entity_type,
				$entity_id
			));
			$capacity = $capacity !== null ? absint($capacity) : null;

            // If capacity is not set, check higher levels
            if ($capacity === null && $entity_type === 'variation' && $product_id) {
                $capacity = self::get_capacity('product', $product_id, $product_id);
            }

            if ($capacity === null && ($entity_type === 'product' || $entity_type === 'variation') && $product_id) {
                $all_categories = \WPM\Delivery\DeliveryCalculator::get_categories($product_id);
                if (!empty($all_categories)) {
                    $capacities = [];
                    foreach ($all_categories as $cat_id) {
                        $cat_capacity = self::get_capacity('category', $cat_id);
                        if ($cat_capacity !== null && $cat_capacity !== 0) {
                            $capacities[] = $cat_capacity;
                        }
                    }
                    $capacity = !empty($capacities) ? min($capacities) : null;
                }
            }

            if ($capacity === null) {
                $capacity = absint(get_option('wpm_default_capacity', 1));
            }

            // Cache the final result
            \WPM\Utils\Cache::set($cache_key, $capacity);
        }

        return $capacity;
    }
}
?>