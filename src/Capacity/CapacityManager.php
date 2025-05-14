<?php
namespace WPM\Capacity;

//use WPM\Utils\PersianDate;

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

        // Register capacity in database
        add_action('init', [__CLASS__, 'register_capacity']);
    }

    public static function add_product_capacity_field() {
        woocommerce_wp_text_input([
            'id'          => 'wpm_product_capacity',
            'label'       => __('Daily Production Capacity', WPM_TEXT_DOMAIN),
            'desc_tip'    => true,
            'description' => __('Maximum number of this product that can be produced daily. Overrides category setting.', WPM_TEXT_DOMAIN),
            'type'        => 'number',
            'value'       => get_post_meta(get_the_ID(), 'wpm_product_capacity', true)
        ]);
    }

    public static function add_variation_capacity_field($loop, $variation_data, $variation) {
        woocommerce_wp_text_input([
            'id'          => 'wpm_variation_capacity_' . $variation->ID,
            'label'       => __('Daily Production Capacity', WPM_TEXT_DOMAIN),
            'desc_tip'    => true,
            'description' => __('Maximum number of this variation that can be produced daily. Overrides product setting.', WPM_TEXT_DOMAIN),
            'type'        => 'number',
            'value'       => get_post_meta($variation->ID, 'wpm_variation_capacity', true)
        ]);
    }

    public static function add_category_capacity_field() {
        ?>
        <div class="form-field">
            <label for="wpm_category_capacity"><?php esc_html_e('Daily Production Capacity', WPM_TEXT_DOMAIN); ?></label>
            <input type="number" name="wpm_category_capacity" id="wpm_category_capacity" min="0">
            <p><?php esc_html_e('Maximum number of products in this category that can be produced daily.', WPM_TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }

    public static function edit_category_capacity_field($term) {
        $capacity = get_term_meta($term->term_id, 'wpm_category_capacity', true);
        ?>
        <tr class="form-field">
            <th><label for="wpm_category_capacity"><?php esc_html_e('Daily Production Capacity', WPM_TEXT_DOMAIN); ?></label></th>
            <td>
                <input type="number" name="wpm_category_capacity" id="wpm_category_capacity" value="<?php echo esc_attr($capacity); ?>" min="0">
                <p><?php esc_html_e('Maximum number of products in this category that can be produced daily.', WPM_TEXT_DOMAIN); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_product_capacity($post_id) {
        $capacity = isset($_POST['wpm_product_capacity']) ? absint($_POST['wpm_product_capacity']) : 0;
        update_post_meta($post_id, 'wpm_product_capacity', $capacity);
        self::update_capacity_record('product', $post_id, $capacity);
    }

    public static function save_variation_capacity($variation_id, $i) {
        $capacity = isset($_POST['wpm_variation_capacity_' . $variation_id]) ? absint($_POST['wpm_variation_capacity_' . $variation_id]) : 0;
        update_post_meta($variation_id, 'wpm_variation_capacity', $capacity);
        self::update_capacity_record('variation', $variation_id, $capacity);
    }

    public static function save_category_capacity($term_id) {
        $capacity = isset($_POST['wpm_category_capacity']) ? absint($_POST['wpm_category_capacity']) : 0;
        update_term_meta($term_id, 'wpm_category_capacity', $capacity);
        self::update_capacity_record('category', $term_id, $capacity);
    }

    public static function update_capacity_record($entity_type, $entity_id, $capacity) {
        global $wpdb;

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wpm_production_capacity WHERE entity_type = %s AND entity_id = %d",
            $entity_type,
            $entity_id
        ));

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
                    'entity_id'   => $entity_id,
                    'max_capacity' => $capacity,
                    'created_at'  => current_time('mysql'),
                    'updated_at'  => current_time('mysql')
                ],
                ['%s', '%d', '%d', '%s', '%s']
            );
        }

        // Clear cache
        \WPM\Utils\Cache::clear("capacity_{$entity_type}_{$entity_id}");
    }

    public static function get_capacity($entity_type, $entity_id) {
        $cache_key = "capacity_{$entity_type}_{$entity_id}";
        $capacity = \WPM\Utils\Cache::get($cache_key);

        if ($capacity === false) {
            global $wpdb;
            $capacity = $wpdb->get_var($wpdb->prepare(
                "SELECT max_capacity FROM {$wpdb->prefix}wpm_production_capacity WHERE entity_type = %s AND entity_id = %d",
                $entity_type,
                $entity_id
            ));
            \WPM\Utils\Cache::set($cache_key, $capacity ?: 0);
        }

        return absint($capacity);
    }

    public static function register_capacity() {
        // Sync existing meta with capacity table
        global $wpdb;

        // Products
        $products = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'wpm_product_capacity'");
        foreach ($products as $product) {
            self::update_capacity_record('product', $product->post_id, absint($product->meta_value));
        }

        // Variations
        $variations = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'wpm_variation_capacity'");
        foreach ($variations as $variation) {
            self::update_capacity_record('variation', $variation->post_id, absint($variation->meta_value));
        }

        // Categories
        $categories = $wpdb->get_results("SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'wpm_category_capacity'");
        foreach ($categories as $category) {
            self::update_capacity_record('category', $category->term_id, absint($category->meta_value));
        }
    }
}
?>