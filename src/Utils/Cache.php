<?php
namespace WPM\Utils;

defined('ABSPATH') || exit;

class Cache {
    public static function get($key) {
        $transient = get_transient('wpm_' . $key);
        return $transient !== false ? $transient : false;
    }

    public static function set($key, $value, $expiration = HOUR_IN_SECONDS) {
        set_transient('wpm_' . $key, $value, $expiration);
    }

    public static function clear($key = '') {
        if ($key) {
            delete_transient('wpm_' . $key);
        } else {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpm_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpm_%'");
        }
    }
}
?>