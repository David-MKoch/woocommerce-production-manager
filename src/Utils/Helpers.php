<?php
namespace WPM\Utils;

defined('ABSPATH') || exit;

class Helpers {
    /**
     * بررسی می‌کند که آیا محصول یا واریانت در حالت Backorder است
     *
     * @param int $product_id
     * @param int $variation_id
     * @return bool
     */
    public static function is_backorder_product($product_id, $variation_id = 0) {
        $product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        return $product->is_on_backorder();
    }
}