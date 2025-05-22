<?php
namespace WPM\Delivery;

defined('ABSPATH') || exit;

class CalculationData {
    public $business_days;
    public $max_capacity_map;
    public $reserved_count_map;
    public $default_capacity;

    public function __construct(array $business_days, array $max_capacity_map, array $reserved_count_map, int $default_capacity) {
        $this->business_days = $business_days;
        $this->max_capacity_map = $max_capacity_map;
        $this->reserved_count_map = $reserved_count_map;
        $this->default_capacity = $default_capacity;
    }
}
?>