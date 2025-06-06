<?php
namespace WPM\Delivery;

use WPM\Utils\PersianDate;

defined('ABSPATH') || exit;

class DeliveryDisplay {
    public static function init() {
        // Display delivery date in cart and checkout
        add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_delivery_date'], 10, 2);
        // Display delivery date in product page
        add_action('woocommerce_single_product_summary', [__CLASS__, 'display_product_delivery_date'], 25);
        add_shortcode('show_delivery_date', [__CLASS__, 'display_product_delivery_date']);
        // Enqueue scripts
        //add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        // Add delivery days fields
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_product_delivery_days_field']);
        add_action('product_cat_add_form_fields', [__CLASS__, 'add_category_delivery_days_field']);
        add_action('product_cat_edit_form_fields', [__CLASS__, 'edit_category_delivery_days_field'], 10, 1);
        // Save delivery days
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_delivery_days']);
        add_action('created_product_cat', [__CLASS__, 'save_category_delivery_days']);
        add_action('edited_product_cat', [__CLASS__, 'save_category_delivery_days']);
    
        add_action('wp_ajax_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
        add_action('wp_ajax_nopriv_wpm_get_delivery_date', [__CLASS__, 'ajax_get_delivery_date']);
    }

    public static function display_product_delivery_date() {
        global $product;
        $product_id = $product->get_id();
        $is_variable = $product->is_type('variable');

        if (!$product->is_on_backorder()) {
            return;
        }

        $delivery_days = DeliveryCalculator::get_delivery_days_for_products([$product_id]);
        $min_delivery_days = $delivery_days[$product_id]['min'];
        $max_delivery_days = $delivery_days[$product_id]['max'];
        $msg = sprintf(__('between %s and %s business days', 'woocommerce-production-manager'), $min_delivery_days, $max_delivery_days);
        
        ?>
        <div class="wpm-delivery-date" data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php if (!$is_variable) : ?>
                <p><?php esc_html_e('Estimated Delivery:', 'woocommerce-production-manager'); ?> <span class="wpm-delivery-date-text"><?php echo $msg; ?></span></p>
            <?php else : ?>
                <p><?php esc_html_e('Estimated Delivery (select variation):', 'woocommerce-production-manager'); ?><span class="wpm-delivery-date-text"><?php echo $msg; ?></span></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function display_cart_delivery_date($item_data, $cart_item) {
        if (isset($cart_item['product_id'], $cart_item['variation_id'], $cart_item['quantity'])) {
            $delivery_result = DeliveryCalculator::calculate_delivery_date($cart_item['product_id'], $cart_item['variation_id'], $cart_item['quantity']);
            if($delivery_result && $delivery_result['delivery_date']){
                $min_delivery_date = PersianDate::to_persian($delivery_result['delivery_date'], 'j F');
                $max_delivery_date = PersianDate::to_persian($delivery_result['max_delivery_date'], 'j F');

                $item_data[] = array(
                    'key' => __('Production completion time', 'woocommerce-production-manager'), 
                    'value' => sprintf(__('between %s and %s', 'woocommerce-production-manager'), $min_delivery_date, $max_delivery_date)
                );
            }
        }
        return $item_data;
    }

    public static function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script('wpm-frontend-js', WPM_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], '1.0.0', true);
            wp_enqueue_style('wpm-frontend-css', WPM_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0');
            wp_localize_script('wpm-frontend-js', 'wpmFrontend', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('wpm_delivery'),
                'i18n'    => [
                    'loading' => __('Loading...', 'woocommerce-production-manager'),
                    'error'   => __('Unable to calculate delivery date.', 'woocommerce-production-manager')
                ]
            ]);
        }
    }

    public static function add_product_delivery_days_field() {
        woocommerce_wp_text_input([
            'id'          => 'wpm_delivery_days',
            'label'       => __('Production Days', 'woocommerce-production-manager'),
            'desc_tip'    => true,
            'description' => __('Number of days required to produce this product. Overrides category and default settings.', 'woocommerce-production-manager'),
            'type'        => 'number',
            'value'       => get_post_meta(get_the_ID(), 'wpm_delivery_days', true)
        ]);
		woocommerce_wp_text_input([
            'id'          => 'wpm_max_delivery_days',
            'label'       => __('Max Production Days', 'woocommerce-production-manager'),
            'desc_tip'    => true,
            'description' => __('Maximum days required to produce this product. Overrides category and default settings.', 'woocommerce-production-manager'),
            'type'        => 'number',
            'value'       => get_post_meta(get_the_ID(), 'wpm_max_delivery_days', true)
        ]);
    }

    public static function add_category_delivery_days_field() {
        ?>
        <div class="form-field">
            <label for="wpm_delivery_days"><?php esc_html_e('Production Days', 'woocommerce-production-manager'); ?></label>
            <input type="number" name="wpm_delivery_days" id="wpm_delivery_days" min="0">
            <p class="description"><?php esc_html_e('Minimum days required to produce products in this category.', 'woocommerce-production-manager'); ?></p>
        </div>
		<div class="form-field">
            <label for="wpm_max_delivery_days"><?php esc_html_e('Max Delivery Days', 'woocommerce-production-manager'); ?></label>
            <input type="number" name="wpm_max_delivery_days" id="wpm_max_delivery_days" min="0">
            <p class="description"><?php esc_html_e('Maximum delivery days for products in this category.', 'woocommerce-production-manager'); ?></p>
        </div>
        <?php
    }

    public static function edit_category_delivery_days_field($term) {
        $delivery_days = get_term_meta($term->term_id, 'wpm_delivery_days', true);
		$max_delivery_days = get_term_meta($term->term_id, 'wpm_max_delivery_days', true);
        ?>
        <tr class="form-field">
            <th><label for="wpm_delivery_days"><?php esc_html_e('Production Days', 'woocommerce-production-manager'); ?></label></th>
            <td>
                <input type="number" name="wpm_delivery_days" id="wpm_delivery_days" value="<?php echo esc_attr($delivery_days); ?>" min="0">
                <p class="description"><?php esc_html_e('Minimum  days required to produce products in this category.', 'woocommerce-production-manager'); ?></p>
            </td>
        </tr>
		<tr class="form-field">
            <th><label for="wpm_max_delivery_days"><?php esc_html_e('Max Delivery Days', 'woocommerce-production-manager'); ?></label></th>
            <td>
                <input type="number" name="wpm_max_delivery_days" id="wpm_max_delivery_days" value="<?php echo esc_attr($max_delivery_days); ?>" min="0">
                <p class="description"><?php esc_html_e('Maximum delivery days for products in this category.', 'woocommerce-production-manager'); ?></p>
            </td>
        </tr>
        <?php
    }

    public static function save_product_delivery_days($post_id) {
        $delivery_days = isset($_POST['wpm_delivery_days']) ? absint($_POST['wpm_delivery_days']) : 0;
        update_post_meta($post_id, 'wpm_delivery_days', $delivery_days);
		
		$max_delivery_days = isset($_POST['wpm_max_delivery_days']) ? absint($_POST['wpm_max_delivery_days']) : 0;
        update_post_meta($post_id, 'wpm_max_delivery_days', $max_delivery_days);
    }

    public static function save_category_delivery_days($term_id) {
        $delivery_days = isset($_POST['wpm_delivery_days']) ? absint($_POST['wpm_delivery_days']) : 0;
        update_term_meta($term_id, 'wpm_delivery_days', $delivery_days);
		
		$max_delivery_days = isset($_POST['wpm_max_delivery_days']) ? absint($_POST['wpm_max_delivery_days']) : 0;
		update_term_meta($term_id, 'wpm_max_delivery_days', $max_delivery_days);
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
            wp_send_json_error(['message' => __('Invalid product', 'woocommerce-production-manager')]);
        }

        $result = self::calculate_delivery_date($product_id, $variation_id, $quantity);

        if ($result && $result['delivery_date']) {
            $min_delivery_date = PersianDate::to_persian($result['delivery_date'], 'j F');
            $max_delivery_date = PersianDate::to_persian($result['max_delivery_date'], 'j F');

            wp_send_json_success([
                'delivery_date' => sprintf(__('between %s and %s', 'woocommerce-production-manager'), $min_delivery_date, $max_delivery_date)
            ]);
        }

        wp_send_json_error(['message' => __('No available delivery date', 'woocommerce-production-manager')]);
    }
}
?>