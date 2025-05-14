<?php
namespace WPM\API;

use Morilog\Jalali\Jalalian;
use WP_REST_Controller;

defined('ABSPATH') || exit;

class RESTController extends WP_REST_Controller {
    protected $namespace = 'wpm/v1';

    public function register_routes() {
        // Capacity endpoint
        register_rest_route($this->namespace, '/capacity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_capacity'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'date' => [
                    'validate_callback' => function($param) {
                        return \WPM\Utils\AssetsManager::is_valid_date($param);
                    }
                ],
                'entity_type' => [
                    'enum' => ['category', 'product', 'variation']
                ],
                'entity_id' => [
                    'type' => 'integer'
                ]
            ]
        ]);

        // Order items endpoint
        register_rest_route($this->namespace, '/order-items', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order_items'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'status' => [
                    'type' => 'string'
                ],
                'category' => [
                    'type' => 'integer'
                ],
                'date_from' => [
                    'validate_callback' => function($param) {
                        return \WPM\Utils\AssetsManager::is_valid_date($param);
                    }
                ],
                'date_to' => [
                    'validate_callback' => function($param) {
                        return \WPM\Utils\AssetsManager::is_valid_date($param);
                    }
                ],
                'persian_date' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ]
        ]);

        // Reports endpoint
        register_rest_route($this->namespace, '/reports/(?P<type>[a-z-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reports'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'type' => [
                    'enum' => ['category-orders', 'full-capacity']
                ],
                'date_from' => [
                    'validate_callback' => function($param) {
                        return \WPM\Utils\AssetsManager::is_valid_date($param);
                    }
                ],
                'date_to' => [
                    'validate_callback' => function($param) {
                        return \WPM\Utils\AssetsManager::is_valid_date($param);
                    }
                ],
                'persian_date' => [
                    'type' => 'boolean',
                    'default' => true
                ]
            ]
        ]);
    }

    public function check_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        $stored_key = get_option('wpm_api_key', '');

        if (empty($stored_key) || $api_key !== $stored_key) {
            return new \WP_Error('invalid_api_key', __('Invalid API key', WPM_TEXT_DOMAIN), ['status' => 401]);
        }

        // Rate limiting (max 100 requests per hour per IP)
        $ip = $_SERVER['REMOTE_ADDR'];
        $cache_key = 'wpm_api_rate_' . md5($ip);
        $requests = \WPM\Utils\Cache::get($cache_key, 0);
        if ($requests >= 100) {
            return new \WP_Error('rate_limit_exceeded', __('Rate limit exceeded', WPM_TEXT_DOMAIN), ['status' => 429]);
        }
        \WPM\Utils\Cache::set($cache_key, $requests + 1, 3600);

        return true;
    }

    public function get_capacity($request) {
        global $wpdb;

        $date = $request->get_param('date') ?: current_time('Y-m-d');
        $entity_type = $request->get_param('entity_type');
        $entity_id = $request->get_param('entity_id');

        $where = ['c.date = %s'];
        $params = [$date];

        if ($entity_type) {
            $where[] = 'c.entity_type = %s';
            $params[] = $entity_type;
        }
        if ($entity_id) {
            $where[] = 'c.entity_id = %d';
            $params[] = $entity_id;
        }

        $where_sql = implode(' AND ', $where);

        $query = $wpdb->prepare("
            SELECT c.date, c.entity_type, c.entity_id, c.reserved_count, pc.max_capacity
            FROM {$wpdb->prefix}wpm_capacity_count c
            JOIN {$wpdb->prefix}wpm_production_capacity pc ON c.entity_type = pc.entity_type AND c.entity_id = pc.entity_id
            WHERE $where_sql
        ", $params);

        $results = $wpdb->get_results($query);

        $data = array_map(function($row) use ($request) {
            $entity_name = '';
            if ($row->entity_type === 'category') {
                $term = get_term($row->entity_id, 'product_cat');
                $entity_name = $term->name;
            } elseif ($row->entity_type === 'product' || $row->entity_type === 'variation') {
                $entity_name = get_the_title($row->entity_id);
            }

            return [
                'date' => $request->get_param('persian_date') ? Jalalian::fromDateTime($row->date)->format('Y/m/d') : $row->date,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'entity_name' => $entity_name,
                'reserved_count' => $row->reserved_count,
                'max_capacity' => $row->max_capacity
            ];
        }, $results);

        return rest_ensure_response($data);
    }

    public function get_order_items($request) {
        global $wpdb;

        $where = [];
        $params = [];

        if ($status = $request->get_param('status')) {
            $where[] = 's.status = %s';
            $params[] = $status;
        }
        if ($category = $request->get_param('category')) {
            $where[] = 't.term_id = %d';
            $params[] = $category;
        }
        if ($date_from = $request->get_param('date_from')) {
            $where[] = 'o.order_date >= %s';
            $params[] = $date_from;
        }
        if ($date_to = $request->get_param('date_to')) {
            $where[] = 'o.order_date <= %s';
            $params[] = $date_to;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = $wpdb->prepare("
            SELECT s.*, o.order_date, oi.order_item_name, p.post_title as product_name, p.ID as product_id,
                   u.display_name as customer_name
            FROM {$wpdb->prefix}wpm_order_items_status s
            JOIN {$wpdb->prefix}woocommerce_order_items oi ON s.order_item_id = oi.order_item_id
            JOIN {$wpdb->prefix}wc_orders o ON s.order_id = o.id
            JOIN {$wpdb->posts} p ON oi.order_item_meta_product_id = p.ID
            LEFT JOIN {$wpdb->users} u ON o.customer_id = u.ID
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->terms} t ON tr.term_taxonomy_id = t.term_id
            $where_sql
            ORDER BY o.order_date DESC
            LIMIT 100
        ", $params);

        $items = $wpdb->get_results($query);

        $data = array_map(function($item) use ($request) {
            return [
                'order_id' => $item->order_id,
                'order_item_id' => $item->order_item_id,
                'item_name' => $item->order_item_name,
                'customer_name' => $item->customer_name ?: __('Guest', WPM_TEXT_DOMAIN),
                'order_date' => $request->get_param('persian_date') ? Jalalian::fromDateTime($item->order_date)->format('Y/m/d') : $item->order_date,
                'status' => $item->status,
                'delivery_date' => $request->get_param('persian_date') ? Jalalian::fromDateTime($item->delivery_date)->format('Y/m/d') : $item->delivery_date
            ];
        }, $items);

        return rest_ensure_response($data);
    }

    public function get_reports($request) {
        global $wpdb;

        $type = $request->get_param('type');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $persian_date = $request->get_param('persian_date');

        if ($type === 'category-orders') {
            $where = [];
            $params = [];
            if ($date_from) {
                $where[] = 'o.order_date >= %s';
                $params[] = $date_from;
            }
            if ($date_to) {
                $where[] = 'o.order_date <= %s';
                $params[] = $date_to;
            }
            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $query = $wpdb->prepare("
                SELECT t.name as category_name, COUNT(DISTINCT o.id) as order_count, SUM(oi.quantity) as item_count
                FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                JOIN {$wpdb->posts} p ON oi.order_item_meta_product_id = p.ID
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->terms} t ON tr.term_taxonomy_id = t.term_id
                $where_sql
                GROUP BY t.term_id
                ORDER BY order_count DESC
            ", $params);

            $results = $wpdb->get_results($query);

            $data = array_map(function($row) {
                return [
                    'category_name' => $row->category_name,
                    'order_count' => $row->order_count,
                    'item_count' => $row->item_count
                ];
            }, $results);

            return rest_ensure_response($data);
        }

        if ($type === 'full-capacity') {
            $where = [];
            $params = [];
            if ($date_from) {
                $where[] = 'c.date >= %s';
                $params[] = $date_from;
            }
            if ($date_to) {
                $where[] = 'c.date <= %s';
                $params[] = $date_to;
            }
            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $query = $wpdb->prepare("
                SELECT c.date, c.entity_type, c.entity_id, c.reserved_count, pc.max_capacity
                FROM {$wpdb->prefix}wpm_capacity_count c
                JOIN {$wpdb->prefix}wpm_production_capacity pc ON c.entity_type = pc.entity_type AND c.entity_id = pc.entity_id
                $where_sql
                HAVING reserved_count >= max_capacity
                ORDER BY c.date DESC
            ", $params);

            $results = $wpdb->get_results($query);

            $data = array_map(function($row) use ($persian_date) {
                $entity_name = '';
                if ($row->entity_type === 'category') {
                    $term = get_term($row->entity_id, 'product_cat');
                    $entity_name = $term->name;
                } elseif ($row->entity_type === 'product' || $row->entity_type === 'variation') {
                    $entity_name = get_the_title($row->entity_id);
                }

                return [
                    'date' => $persian_date ? Jalalian::fromDateTime($row->date)->format('Y/m/d') : $row->date,
                    'entity_type' => $row->entity_type,
                    'entity_name' => $entity_name,
                    'capacity' => $row->reserved_count . '/' . $row->max_capacity
                ];
            }, $results);

            return rest_ensure_response($data);
        }

        return new \WP_Error('invalid_type', __('Invalid report type', WPM_TEXT_DOMAIN), ['status' => 400]);
    }
}
?>