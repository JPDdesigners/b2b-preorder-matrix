<?php
// Isolated contract tests. No WordPress install, network, or real database is touched.
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
$hooks = $meta = $products = [];
$logged_in = true;
$access = true;
$product_access = true;
$write_failure = false;
$write_count = 0;
$password_required = false;
class WP_Error {
    private $code, $message, $data;
    function __construct( $code, $message, $data = [] ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    function get_error_code() { return $this->code; }
    function get_error_message() { return $this->message; }
    function get_error_data() { return $this->data; }
}
class JsonExit extends Exception { public $body, $status; function __construct( $body, $status ) { $this->body = $body; $this->status = $status; } }
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['hooks'][ $name ] = $callback; $GLOBALS['hook_priorities'][ $name ] = $priority;
    $GLOBALS['action_callbacks'][ $name ] = $GLOBALS['action_callbacks'][ $name ] ?? [];
    foreach ( $GLOBALS['action_callbacks'][ $name ] as $registered ) { if ( $registered[0] === $callback ) { return; } }
    $GLOBALS['action_callbacks'][ $name ][] = [ $callback, $accepted_args ];
}
function add_filter( $name, $callback, ...$rest ) { add_action( $name, $callback ); }
function add_shortcode( $name, $callback ) { add_action( $name, $callback ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_current_user_id() { return $GLOBALS['logged_in'] ? 7 : 0; }
class WP_User { public $ID, $display_name; function __construct( $id ) { $this->ID = $id; $this->display_name = $id === 19 ? 'Seller' : 'Admin'; } }
class user_switching {
    static function get_old_user() { return $GLOBALS['old_user'] ?? false; }
    static function authenticate_old_user( $user ) { return ! empty( $GLOBALS['old_authenticated'] ); }
}
function current_user_can( $cap, $id = null ) { return ! empty( $GLOBALS['manager'] ) && ( $cap === 'manage_woocommerce' || ( $cap === 'edit_shop_order' && $id !== ( $GLOBALS['denied_order_id'] ?? null ) ) ); }
function user_can( $user, $cap ) { return $cap === 'manage_woocommerce' && in_array( $user->ID, $GLOBALS['manager_ids'] ?? [], true ); }
function get_current_screen() { return (object) [ 'id' => $GLOBALS['screen_id'] ?? '' ]; }
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function selected( $a, $b, $echo = true ) { $value = $a === $b ? ' selected="selected"' : ''; if ( $echo ) echo $value; return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_nonce_field( $action, $field ) { echo '<input name="' . $field . '" value="' . $action . '" />'; }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( 'wp_die: ' . $message ); }
function check_admin_referer( $action ) { $GLOBALS['checked_admin_action'] = $action; if ( $action !== ( $GLOBALS['admin_nonce_action'] ?? '' ) ) { wp_die( 'Invalid admin nonce' ); } }
function apply_filters( $hook, $value, ...$rest ) { return $hook === 'b2b_matrix_user_can_access' ? $GLOBALS['access'] : ( $hook === 'b2b_matrix_product_can_access' ? $GLOBALS['product_access'] : $value ); }
function wp_verify_nonce( $nonce, $action ) { return ( $nonce === 'valid' && $action === 'wp_rest' ) || ( strpos( $action, 'b2b_order_channel_' ) === 0 && $nonce === $action ); }
function wp_create_nonce( $action ) { return 'valid'; }
function check_ajax_referer( $action, $field, $die ) { return wp_verify_nonce( $_POST[ $field ] ?? null, $action ); }
function nocache_headers() {}
function do_action( $name, ...$args ) { foreach ( $GLOBALS['action_callbacks'][ $name ] ?? [] as $registered ) { call_user_func_array( $registered[0], array_slice( $args, 0, $registered[1] ) ); } }
function rest_ensure_response( $data ) { return $data; }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function jpd_get_canceled_items_bulk( $ids ) {
    $GLOBALS['jpd_requested_ids'][] = $ids;
    return array_values(array_intersect($ids, $GLOBALS['jpd_cancelled_ids'] ?? []));
}
function post_password_required( $id ) { return $GLOBALS['password_required']; }
function taxonomy_exists( $tax ) { return true; }
function get_term_by( $field, $value, $tax ) { return (object) [ 'name' => strtoupper( $value ) ]; }
function get_woocommerce_currency() { return 'EUR'; }
function wc_get_price_decimals() { return 2; }
function wc_tax_enabled() { throw new Exception('Tax calculation must not be used'); }
function get_option( $name, $default = false ) { return $GLOBALS['options'][$name] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][$name] = $value; return true; }
function get_locale() { return 'el'; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; }
function wp_generate_uuid4() { return 'version-' . ++$GLOBALS['generation']; }
function wp_json_encode( $value ) { return json_encode($value); }
function absint( $v ) { return abs( (int) $v ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url_raw( $url ) { return $url; }
function wp_login_url() { return 'https://shop.test/wp-login.php'; }
function rest_url( $path = '' ) { return 'https://shop.test/wp-json/' . ltrim( $path, '/' ); }
function admin_url( $path = '' ) { return 'https://shop.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return '/thumb.jpg'; }
function wc_get_page_permalink( $page ) { return 'https://shop.test/my-account/'; }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://shop.test/my-account/' . $endpoint . '/'; }
function wp_get_attachment_image_src( $id, $size ) { return [ $size === 'full' ? '/full.jpg' : '/thumb.jpg', 1200, 1600 ]; }
function plugin_dir_path( $file ) { return __DIR__ . '/../'; }
function plugin_dir_url( $file ) { return 'https://shop.test/wp-content/plugins/b2b/'; }
function wc_placeholder_img_src() { return '/placeholder.jpg'; }
function wp_enqueue_style( ...$args ) {}
function wp_enqueue_script_module( ...$args ) {}
function remove_action( $hook, ...$args ) { $GLOBALS['removed'][] = $hook; }

function get_user_meta( $user, $key = '', $single = false ) { return $key === '' ? ($GLOBALS['meta'][$user] ?? []) : ($GLOBALS['meta'][ $user ][ $key ] ?? ''); }
function update_user_meta( $user, $key, $value ) {
    $GLOBALS['write_count']++;
    if ( $GLOBALS['write_failure'] ) { return false; }
    $GLOBALS['meta'][ $user ][ $key ] = wp_unslash( $value );
    return true;
}
function delete_user_meta( $user, $key ) {
    unset( $GLOBALS['meta'][ $user ][ $key ] );
    return true;
}
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function wp_send_json_success( $data, $status = 200 ) { throw new JsonExit( [ 'success' => true, 'data' => $data ], $status ); }
function wp_send_json_error( $data, $status = 400 ) { throw new JsonExit( [ 'success' => false, 'data' => $data ], $status ); }
function wc_get_product_terms( $id, $tax, $args = [] ) { return [ (object) [ 'term_id' => 1, 'name' => 'Joyce', 'slug' => 'joyce' ] ]; }
function wp_get_object_terms( $ids, $tax, $args = [] ) { return [ (object) [ 'object_id' => is_array($ids) ? $ids[0] : $ids, 'term_id' => 20, 'name' => 'Baby Boy', 'slug' => 'baby-boy' ] ]; }
function get_ancestors( $term_id, $tax, $type = 'taxonomy' ) { return [ 1 ]; }
function get_term( $term_id, $tax ) { return (object) [ 'term_id' => $term_id, 'name' => $term_id === 1 ? 'Joyce' : 'Baby Boy', 'slug' => $term_id === 1 ? 'joyce' : 'baby-boy' ]; }
function get_term_meta( $id, $key, $single = true ) { return ''; }
function get_userdata( $user_id ) { return (object) [ 'ID' => $user_id, 'user_email' => 'test@b2b.test', 'display_name' => $user_id === 19 ? 'Seller' : 'B2B Client' ]; }
function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( (string) $t ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) { return array_intersect_key( (array) $atts, (array) $pairs ) + (array) $pairs; }
function get_the_ID() { global $post; return $post->ID ?? 0; }
function has_shortcode( $content = '', $tag = '' ) { return false; }

class WC_Order_Item_Product {
    public $product, $qty, $total, $subtotal, $taxes, $order_id;
    function set_order_id( $id ) { $this->order_id = $id; }
    function save() { $GLOBALS['orders'][$this->order_id]->items[] = $this; return count($GLOBALS['orders'][$this->order_id]->items); }
    function set_product( $product ) { $this->product = $product; }
    function set_quantity( $qty ) { $this->qty = $qty; }
    function set_total( $v ) { $this->total = $v; }
    function set_subtotal( $v ) { $this->subtotal = $v; }
    function set_taxes( $v ) { $this->taxes = $v; }
    function get_quantity() { return $this->qty; }
    function get_total() { return $this->total; }
    function get_variation_id() { return $this->product->get_id(); }
}
class WC_Order {
    public $id = 0, $customer_id, $status = 'pending', $meta = [], $items = [], $addresses = [], $total, $created_via = '', $transitions = [];
    private $saved_status = 'pending';
    function get_id() { return $this->id; }
    function get_customer_id() { return $this->customer_id; }
    function set_customer_id( $uid ) { $this->customer_id = $uid; }
    function set_created_via( $v ) { $this->created_via = $v; }
    function get_created_via() { return $this->created_via; }
    function set_currency( $v ) {}
    function set_prices_include_tax( $v ) {}
    function get_status() { return $this->status; }
    function set_status( $status ) { $this->status = $status; }
    function set_address( $addr, $type ) { $this->addresses[$type] = $addr; }
    function set_customer_note( $note ) {}
    function update_meta_data( $key, $val ) { $this->meta[$key] = $val; }
    function get_meta( $key ) { return $this->meta[$key] ?? ''; }
    function delete_meta_data( $key ) { unset( $this->meta[$key] ); }
    function get_type() { return 'shop_order'; }
    function get_order_number() { return $this->id; }
    function get_date_created() { return null; }
    function get_billing_company() { return $this->addresses['billing']['company'] ?? ''; }
    function get_total() { return $this->total; }
    function get_currency() { return 'EUR'; }
    function add_order_note( $text, $customer = false, $user = false ) { $this->notes[] = [ $text, $customer, $user ]; }
    function add_item( $item ) { $this->items[] = $item; }
    function get_items() { $GLOBALS['full_order_item_reads']++; return $this->items; }
    function calculate_totals() { throw new Exception( 'Tax/checkout calculation is forbidden' ); }
    function set_total( $v ) { $this->total = $v; }
    function save() {
        if ( ! $this->id ) { $this->id = 1001 + count( $GLOBALS['orders'] ); }
        $GLOBALS['orders'][$this->id] = $this;
        if ( $this->saved_status !== $this->status ) {
            $from = $this->saved_status; $this->saved_status = $this->status;
            $this->transitions[] = [ $from, $this->status ];
            do_action( 'woocommerce_order_status_' . $from . '_to_' . $this->status, $this->id, $this );
        }
        return $this->id;
    }
    function get_view_order_url() { return 'https://shop.test/view-order/' . $this->id; }
}
function wc_get_orders( $args ) { return array_values( array_filter( $GLOBALS['orders'], function( $o ) use ( $args ) { return $o->customer_id === $args['customer_id'] && $o->get_meta( $args['meta_key'] ) === $args['meta_value']; } ) ); }
function wc_get_order( $id ) { return $GLOBALS['orders'][$id] ?? null; }
function wc_format_decimal( $v, $dp ) { return number_format( $v, $dp, '.', '' ); }

class MockWPDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $items = [];
    public $batches = [];
    public $order_item_queries = 0;
    public $last_order_item_sql = '';
    public $fail_order_item_read = false;

    private $transaction;
    function get_var( $query ) { if ( stripos( $query, 'GET_LOCK' ) !== false ) { return empty( $GLOBALS['lock_failure'] ) ? 1 : 0; } return 1; }
    function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }

    function prepare( $query, ...$args ) {
        if ( isset( $args[0] ) && is_array( $args[0] ) && count( $args ) === 1 ) {
            $args = $args[0];
        }
        foreach ( $args as $arg ) {
            $val = is_numeric( $arg ) ? $arg : "'" . addslashes( (string) $arg ) . "'";
            $query = preg_replace( '/%[dfs]/', $val, $query, 1 );
        }
        return $query;
    }

    function get_results( $query, $output = ARRAY_A ) {
        if ( strpos( $query, 'woocommerce_order_items oi' ) !== false ) {
            $this->order_item_queries++;
            $this->last_order_item_sql = $query;
            if ( $this->fail_order_item_read ) { $this->last_error = 'Order item read failed'; return null; }
            preg_match( '/oi\.order_id = (\d+)/', $query, $order_match );
            preg_match( '/vim\.meta_value IN \(([^)]+)\)/', $query, $ids_match );
            $order_id = (int) ( $order_match[1] ?? 0 );
            $ids = array_map( 'intval', explode( ',', $ids_match[1] ?? '' ) );
            $results = [];
            foreach ( $GLOBALS['orders'][ $order_id ]->items ?? [] as $index => $item ) {
                $vid = $item->get_variation_id();
                if ( in_array( $vid, $ids, true ) ) { $results[] = [ 'order_item_id' => $index + 1, 'variation_id' => (string) $vid ]; }
            }
            return $results;
        }
        if ( preg_match( '/WHERE user_id = (\d+) AND product_id = (\d+)/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            $product_id = (int) $m[2];
            $results = [];
            foreach ( $this->items[ $user_id ] ?? [] as $vid => $item ) {
                if ( (int) $item['product_id'] === $product_id && (int) $item['qty'] > 0 ) {
                    $results[] = [ 'variation_id' => $vid, 'qty' => $item['qty'] ];
                }
            }
            return $results;
        }
        if ( preg_match( '/WHERE user_id = (\d+) AND qty > 0/i', $query, $m ) ) {
            $GLOBALS['full_list_reads'] = ( $GLOBALS['full_list_reads'] ?? 0 ) + 1;
            $user_id = (int) $m[1];
            $results = [];
            foreach ( $this->items[ $user_id ] ?? [] as $vid => $item ) {
                if ( (int) $item['qty'] > 0 ) {
                    $results[] = [
                        'id'           => $vid,
                        'product_id'   => $item['product_id'],
                        'variation_id' => $vid,
                        'qty'          => $item['qty'],
                        'price'        => null,
                        'updated_at'   => '2026-09-24 12:00:00',
                    ];
                }
            }
            return $results;
        }
        return [];
    }

    function get_row( $query, $output = ARRAY_A ) {
        if ( preg_match( '/COUNT\(\*\) as total_items.*WHERE user_id = (\d+)/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            $total_items = 0;
            $total_qty = 0;
            foreach ( $this->items[ $user_id ] ?? [] as $vid => $item ) {
                if ( (int) $item['qty'] > 0 ) {
                    $total_items++;
                    $total_qty += (int) $item['qty'];
                }
            }
            return [ 'total_items' => $total_items, 'total_qty' => $total_qty ];
        }
        if ( preg_match( "/WHERE batch_token = '([^']+)'/i", $query, $m ) ) {
            return $this->batches[ $m[1] ] ?? null;
        }
        if ( preg_match( '/WHERE user_id = (\d+) AND status IN/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            foreach ( array_reverse( $this->batches ) as $b ) {
                if ( (int) $b['user_id'] === $user_id && in_array( $b['status'], [ 'pending', 'building', 'ready_to_finalize' ], true ) ) {
                    return $b;
                }
            }
            return null;
        }
        if ( preg_match( '/WHERE wc_order_id = (\d+)/i', $query, $m ) ) {
            $oid = (int) $m[1];
            foreach ( array_reverse( $this->batches ) as $b ) {
                if ( (int) ( $b['wc_order_id'] ?? 0 ) === $oid ) {
                    return $b;
                }
            }
            return null;
        }
        return null;
    }

    function delete( $table, $where, $where_format = null ) {
        $GLOBALS['write_count']++;
        if ( $GLOBALS['write_failure'] ) {
            $this->last_error = 'Database write failed';
            return false;
        }
        $uid = $where['user_id'] ?? null;
        $vid = $where['variation_id'] ?? null;
        if ( $uid && $vid ) {
            unset( $this->items[ $uid ][ $vid ] );
            return 1;
        }
        return 0;
    }

    function query( $query ) {
        if ( $query === 'START TRANSACTION' ) { $this->transaction = [ $this->items, $this->batches ]; return true; }
        if ( $query === 'ROLLBACK' ) { if ( $this->transaction ) { [ $this->items, $this->batches ] = $this->transaction; } $this->last_error = ''; return true; }
        if ( $query === 'COMMIT' ) { $this->transaction = null; return true; }
        if ( stripos( $query, 'CREATE TABLE' ) !== false ) {
            return true;
        }
        $GLOBALS['write_count']++;
        if ( strpos( $query, 'DELETE FROM' ) === 0 && strpos( $query, 'variation_id IN' ) !== false ) { $GLOBALS['prune_delete_queries'][] = $query; }
        if ( ! empty( $GLOBALS['fail_prune_delete'] ) && strpos( $query, 'DELETE FROM' ) === 0 && strpos( $query, 'variation_id IN' ) !== false ) { $this->last_error = 'Delete failed'; return false; }
        if ( $GLOBALS['write_failure'] || ( !empty($GLOBALS['fail_insert']) && strpos($query, 'INSERT INTO') === 0 ) ) {
            $this->last_error = 'Database write failed';
            return false;
        }
        $this->last_error = '';

        if ( preg_match( '/DELETE FROM .* WHERE user_id = (\d+) AND variation_id IN \(([^)]+)\)/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            $ids = array_map( 'intval', explode( ',', $m[2] ) );
            foreach ( $ids as $id ) {
                unset( $this->items[ $user_id ][ $id ] );
            }
            return count( $ids );
        }

        if ( preg_match( '/DELETE FROM .* WHERE user_id = (\d+) AND product_id = (\d+)/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            $pid = (int) $m[2];
            foreach ( $this->items[ $user_id ] ?? [] as $vid => $it ) {
                if ( (int) $it['product_id'] === $pid ) {
                    unset( $this->items[ $user_id ][ $vid ] );
                }
            }
            return true;
        }

        if ( preg_match( '/DELETE FROM .* WHERE user_id = (\d+)/i', $query, $m ) ) {
            $user_id = (int) $m[1];
            unset( $this->items[ $user_id ] );
            return true;
        }

        if ( preg_match( '/INSERT INTO .* VALUES (.*) ON DUPLICATE/is', $query, $m ) ) {
            preg_match_all( '/\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*[^)]+\)/', $m[1], $matches, PREG_SET_ORDER );
            foreach ( $matches as $row ) {
                $user_id = (int) $row[1];
                $product_id = (int) $row[2];
                $vid = (int) $row[3];
                $qty = (int) $row[4];
                if ( strpos( $query, 'UPDATE variation_id' ) !== false && isset( $this->items[$user_id][$vid] ) ) { continue; }
                $this->items[ $user_id ][ $vid ] = [
                    'product_id'   => $product_id,
                    'variation_id' => $vid,
                    'qty'          => $qty,
                ];
            }
            return count( $matches );
        }

        return true;
    }

    function insert( $table, $data, $format = null ) {
        $GLOBALS['write_count']++;
        if ( $GLOBALS['write_failure'] ) {
            $this->last_error = 'Database write failed';
            return false;
        }
        $this->last_error = '';
        $this->batches[ $data['batch_token'] ] = $data;
        return 1;
    }

    function update( $table, $data, $where, $format = null, $where_format = null ) {
        $GLOBALS['write_count']++;
        if ( $GLOBALS['write_failure'] ) {
            $this->last_error = 'Database write failed';
            return false;
        }
        $this->last_error = '';
        $token = $where['batch_token'] ?? null;
        if ( $token && isset( $this->batches[ $token ] ) ) {
            $this->batches[ $token ] = array_merge( $this->batches[ $token ], $data );
            return 1;
        }
        return 0;
    }
}
$GLOBALS['wpdb'] = new MockWPDB();

class Product {
    public $id, $type, $parent, $status = 'publish', $variations, $attributes, $sku = '';
    function __construct( $type = 'variable', $parent = 0 ) {
        $this->type = $type; $this->parent = $parent;
        $this->attributes = [ 'pa_color' => [ 'black' ], 'pa_size' => [ 's', 'm' ] ];
        $this->variations = [ self::variation( 10, 's', 15.5 ), self::variation( 11, 'm', 17.5 ) ];
    }
    static function variation( $id, $size, $price ) { return [ 'variation_id' => $id, 'attributes' => [ 'attribute_pa_color' => 'black', 'attribute_pa_size' => $size ], 'display_price' => $price, 'image' => [ 'src' => '/image.jpg' ], 'is_in_stock' => true ]; }
    function is_type( $type ) { return $type === $this->type; }
    function get_status() { return $this->status; }
    function get_parent_id() { return $this->parent; }
    function get_name() { return 'Test shirt'; }
    function get_sku() { return $this->sku; }
    function get_id() { return $this->id; }
    function exists() { return true; }
    function get_attribute( $attr = '' ) { return $attr === 'Color' ? 'Black' : ''; }
    function get_image_id( $context = 'view' ) { return 1; }
    function get_children() { $GLOBALS['catalog_reads']++; return array_column( $this->variations, 'variation_id' ); }
    function get_variation_attributes() {
        if ($this->type === 'variable') return $this->attributes;
        foreach ($GLOBALS['products'][$this->parent]->variations ?? [] as $v) { if ($v['variation_id'] === $this->id) return $v['attributes']; }
        return [];
    }
    function get_price() {
        $GLOBALS['price_reads']++;
        foreach ($GLOBALS['products'][$this->parent]->variations ?? [] as $v) { if ($v['variation_id'] === $this->id) return $v['display_price']; }
        return '';
    }
    function get_available_variations() { throw new Exception('Heavy cart/stock/tax payload must not be used'); }

}
class Request {
    private $params, $nonce;
    function __construct( $params = [], $nonce = 'valid' ) { $this->params = $params; $this->nonce = $nonce; }
    function get_param( $name ) { return $this->params[ $name ] ?? null; }
    function get_json_params() { return $this->params; }
    function get_params() { return $this->params; }
    function get_header( $name ) { return $this->nonce; }
    function get_route() { return '/b2b/v1/matrix'; }
}
require __DIR__ . '/../b2b-matrix-api.php';
function fixture() {
    global $wpdb;
    $GLOBALS['logged_in'] = $GLOBALS['access'] = $GLOBALS['product_access'] = true;
    $GLOBALS['manager'] = $GLOBALS['old_authenticated'] = $GLOBALS['is_admin'] = false;
    $GLOBALS['old_user'] = false; $GLOBALS['manager_ids'] = []; $GLOBALS['screen_id'] = ''; $GLOBALS['denied_order_id'] = null;
    $GLOBALS['checked_admin_action'] = $GLOBALS['admin_nonce_action'] = '';
    $GLOBALS['write_failure'] = $GLOBALS['password_required'] = false;
    $GLOBALS['write_count'] = $GLOBALS['catalog_reads'] = $GLOBALS['price_reads'] = $GLOBALS['generation'] = 0;
    $GLOBALS['options'] = $GLOBALS['transients'] = $GLOBALS['removed'] = [];
    $GLOBALS['products'] = [ 100 => new Product(), 10 => new Product( 'variation', 100 ), 11 => new Product( 'variation', 100 ), 99 => new Product( 'variation', 200 ) ];
    foreach ($GLOBALS['products'] as $id => $product) { $product->id = $id; }
    $GLOBALS['product'] = $GLOBALS['products'][100];
    $GLOBALS['meta'] = [];
    $GLOBALS['orders'] = [];
    $GLOBALS['lock_failure'] = $GLOBALS['fail_insert'] = false;
    $wpdb->last_error = '';
    $wpdb->order_item_queries = 0;
    $wpdb->last_order_item_sql = '';
    $wpdb->fail_order_item_read = false;
    $GLOBALS['full_order_item_reads'] = 0;
    $GLOBALS['full_list_reads'] = 0;
    $GLOBALS['jpd_cancelled_ids'] = $GLOBALS['jpd_requested_ids'] = [];
    $GLOBALS['fail_prune_delete'] = false;
    $GLOBALS['prune_delete_queries'] = [];
    unset( $GLOBALS['action_callbacks'][B2B_Preorder_Submission::PROCESSING_EMAIL_ACTION], $GLOBALS['action_callbacks'][B2B_Preorder_Submission::PROCESSING_EMAIL_ACTION . '_notification'] );
    $wpdb->items = [
        7 => [
            10 => [ 'product_id' => 100, 'variation_id' => 10, 'qty' => 2 ],
            11 => [ 'product_id' => 100, 'variation_id' => 11, 'qty' => 3 ],
            55 => [ 'product_id' => 100, 'variation_id' => 55, 'qty' => 4 ],
        ],
    ];
    $wpdb->batches = [];
    $_GET = $_POST = $_REQUEST = [];
}
function check( $condition, $message = 'Assertion failed' ) { if ( ! $condition ) { throw new Exception( $message ); } }
function error_is( $value, $code ) { check( is_wp_error( $value ) && $value->get_error_code() === $code, 'Expected ' . $code ); }
function save( $items, $id = 100 ) { return B2B_Matrix_API::save_user_product_quantities( 7, $id, $items ); }
function ajax( $method ) { try { B2B_Matrix_API::$method(); } catch ( JsonExit $e ) { return $e; } throw new Exception( 'No JSON response' ); }
$tests = [
    'product identity returns parent SKU rather than a variation SKU and allows an empty parent SKU' => function() {
        $GLOBALS['products'][100]->sku = 'PARENT-100';
        $GLOBALS['products'][10]->sku = 'CHILD-10';
        $r = B2B_Matrix_API::rest_get_matrix_data( new Request( [ 'product_id' => 100 ] ) );
        check( $r['product_name'] === 'Test shirt' && $r['parent_sku'] === 'PARENT-100' );
        $GLOBALS['products'][100]->sku = '';
        B2B_Matrix_Catalog::invalidate();
        $_GET = [ 'product_id' => 100 ];
        $r = ajax( 'ajax_get_matrix_data' );
        check( $r->body['data']['parent_sku'] === '' );
    },
    'guest reads are registered but guest writes are rejected' => function() { check( $GLOBALS['hooks']['wp_ajax_nopriv_b2b_matrix_session'][1] === 'ajax_session' && $GLOBALS['hooks']['wp_ajax_nopriv_b2b_save_matrix'][1] === 'ajax_unauthorized' ); check( isset( $GLOBALS['hooks']['rest_post_dispatch'] ) ); },
    'guests can read but cannot save and denied signed-in users remain blocked' => function() { $GLOBALS['logged_in'] = false; check( B2B_Matrix_API::check_read_permissions() === true ); error_is( save( [ [ 'id' => 10, 'qty' => 9 ] ] ), 'rest_forbidden' ); $GLOBALS['logged_in'] = true; $GLOBALS['access'] = false; error_is( B2B_Matrix_API::check_read_permissions(), 'b2b_forbidden' ); check( $GLOBALS['write_count'] === 0 ); },
    'writes require a valid REST nonce' => function() { foreach ( [ '', 'expired' ] as $nonce ) { error_is( B2B_Matrix_API::check_write_permissions( new Request( [], $nonce ) ), 'rest_invalid_nonce' ); } check( B2B_Matrix_API::check_write_permissions( new Request() ) === true ); },
    'session returns current identity and nonce' => function() { $r = ajax( 'ajax_session' ); check( $r->status === 200 && $r->body['data']['user_id'] === 7 && $r->body['data']['nonce'] === 'valid' ); },
    'GET returns quantities and catalog prices without tax or stock data' => function() { $r = B2B_Matrix_API::rest_get_matrix_data( new Request( [ 'product_id' => 100 ] ) ); check( $r['matrix']['black']['m']['price'] === 17.5 ); check( $r['color_labels']['black'] === 'BLACK' ); check( !isset($r['tax_label']) && !isset($r['matrix']['black']['m']['stock']) ); check( ( (array) $r['existing_quantities'] )[10] === 2 ); },
    'invalid product IDs are rejected without coercion' => function() { foreach ( [ null, 0, -100, '1e2', '100.5', [], true ] as $id ) { error_is( save( [ [ 'id' => 10, 'qty' => 3 ] ], $id ), 'invalid_product' ); } check( $GLOBALS['write_count'] === 0 ); },
    'draft, password-protected, nonexistent and restricted products cannot be read' => function() { $GLOBALS['products'][100]->status = 'draft'; error_is( B2B_Matrix_API::rest_get_matrix_data( new Request( [ 'product_id' => 100 ] ) ), 'product_unavailable' ); $GLOBALS['products'][100]->status = 'publish'; $GLOBALS['password_required'] = true; error_is( save( [ [ 'id' => 10, 'qty' => 1 ] ] ), 'product_unavailable' ); $GLOBALS['password_required'] = false; $GLOBALS['product_access'] = false; error_is( save( [ [ 'id' => 10, 'qty' => 1 ] ] ), 'product_forbidden' ); },
    'zero removes only the chosen variation and preserves unseen entries' => function() { $r = save( [ [ 'id' => 10, 'qty' => 0 ] ] ); check( $r === [ 11 => 3, 55 => 4 ] ); check( $GLOBALS['write_count'] === 1 ); },
    'foreign, non-variation and duplicate IDs reject the entire batch' => function() { foreach ( [ 99, 100, 123456 ] as $id ) { error_is( save( [ [ 'id' => 10, 'qty' => 9 ], [ 'id' => $id, 'qty' => 1 ] ] ), 'invalid_variation' ); } error_is( save( [ [ 'id' => 10, 'qty' => 1 ], [ 'id' => 10, 'qty' => 2 ] ] ), 'invalid_variation' ); check( $GLOBALS['write_count'] === 0 ); },
    'negative, decimal, boolean, excessive and missing quantities reject atomically' => function() { foreach ( [ -1, 1.2, true, '1e2', '2.5', 100000, null, [] ] as $qty ) { error_is( save( [ [ 'id' => 10, 'qty' => 9 ], [ 'id' => 11, 'qty' => $qty ] ] ), 'invalid_quantity' ); } check( $GLOBALS['write_count'] === 0 ); },
    'malformed and oversized lists are rejected' => function() { foreach ( [ null, [], 'bad', [ 'id' => 10, 'qty' => 2 ], array_fill( 0, 2001, [] ) ] as $items ) { error_is( save( $items ), 'invalid_items' ); } error_is( save( [ 'bad' ] ), 'invalid_quantity' ); },
    'unavailable variation cannot be submitted' => function() { array_pop( $GLOBALS['products'][100]->variations ); error_is( save( [ [ 'id' => 11, 'qty' => 2 ] ] ), 'invalid_variation' ); },
    'wildcard and ambiguous combinations fail visibly' => function() { $GLOBALS['products'][100]->variations[0]['attributes']['attribute_pa_size'] = ''; error_is( B2B_Matrix_API::rest_get_matrix_data( new Request( [ 'product_id' => 100 ] ) ), 'ambiguous_variations' ); fixture(); $GLOBALS['products'][12] = new Product('variation', 100); $GLOBALS['products'][12]->id = 12; $GLOBALS['products'][100]->variations[] = Product::variation( 12, 's', 12 ); error_is( save( [ [ 'id' => 10, 'qty' => 1 ] ] ), 'ambiguous_variations' ); },
    'database failure is reported as 500, never as success' => function() { $GLOBALS['write_failure'] = true; $r = B2B_Matrix_API::rest_save_matrix( new Request( [ 'product_id' => 100, 'items' => [ [ 'id' => 10, 'qty' => 9 ] ] ] ) ); error_is( $r, 'save_failed' ); check( $r->get_error_data()['status'] === 500 ); check( B2B_Matrix_API::get_user_product_quantities( 7, 100 )[10] === 2 ); },
    'no-op is successful without unnecessary DB writes' => function() { check( ! is_wp_error( save( [ [ 'id' => 10, 'qty' => 2 ] ] ) ) ); check( $GLOBALS['write_count'] === 0 ); },
    'one user cannot write another user quantities' => function() { error_is( B2B_Matrix_API::save_user_product_quantities( 8, 100, [ [ 'id' => 10, 'qty' => 9 ] ] ), 'invalid_user' ); },
    'AJAX shares validation and nonce requirements' => function() { $_POST = [ 'product_id' => 100, 'items' => '[{"id":10,"qty":-1}]', 'nonce' => 'valid' ]; $r = ajax( 'ajax_save_matrix' ); check( $r->status === 400 && $r->body['success'] === false ); $_POST['nonce'] = 'expired'; $r = ajax( 'ajax_save_matrix' ); check( $r->status === 403 ); },
    'shared catalog cache contains no identity, nonce, price or quantities' => function() {
        $r = B2B_Matrix_API::rest_get_matrix_data(new Request(['product_id'=>100]));
        $cache = array_values($GLOBALS['transients'])[0];
        foreach (['nonce','user_id','prices','existing_quantities','appearance','currency'] as $key) check(!array_key_exists($key,$cache));
        check(!array_key_exists('price',$cache['matrix']['black']['s']));
        check($cache['matrix']['black']['s']['img'] === '/thumb.jpg' && $cache['matrix']['black']['s']['full_img'] === '/full.jpg');
        $GLOBALS['wpdb']->items[7][10] = [ 'product_id' => 100, 'variation_id' => 10, 'qty' => 18 ];
        $GLOBALS['products'][100]->variations[0]['display_price']=25;
        $r2 = B2B_Matrix_API::rest_get_matrix_data(new Request(['product_id'=>100,'structure_version'=>$r['structure_version']]));
        check(!isset($r2['matrix']) && ((array)$r2['existing_quantities'])[10]===18 && ((array)$r2['prices'])[10]===25.0);
        check($GLOBALS['catalog_reads']===1);
    },
    'catalog mutations invalidate structure and stale versions receive a full replacement' => function() {
        $r = B2B_Matrix_API::rest_get_matrix_data(new Request(['product_id'=>100]));
        $GLOBALS['products'][100]->sku='NEW'; B2B_Matrix_Catalog::invalidate();
        $next=B2B_Matrix_API::rest_get_matrix_data(new Request(['product_id'=>100,'structure_version'=>$r['structure_version']]));
        check($next['parent_sku']==='NEW' && $next['structure_version']!==$r['structure_version']);
        check(isset($GLOBALS['hooks']['woocommerce_update_product_variation'], $GLOBALS['hooks']['edited_term'], $GLOBALS['hooks']['transition_post_status']));
    },
    'guests see the same catalog and no customer quantities but every save route rejects them' => function() {
        $GLOBALS['logged_in']=false; $_GET=['product_id'=>100];
        $r=ajax('ajax_get_matrix_data');
        check($r->body['data']['user_id']===0 && (array)$r->body['data']['existing_quantities']===[]);
        check($r->body['data']['matrix']['black']['m']['price']===17.5);
        error_is(B2B_Matrix_API::check_write_permissions(new Request()),'rest_forbidden');
        error_is(B2B_Matrix_API::save_user_product_quantities(0,100,[['id'=>10,'qty'=>4]]),'rest_forbidden');
        $_POST=['product_id'=>100,'items'=>'[{"id":10,"qty":4}]','nonce'=>'valid'];
        check(ajax('ajax_save_matrix')->status===401 && $GLOBALS['write_count']===0);
    },
    'out of stock variations remain editable and save never reads prices or checkout data' => function() {
        $GLOBALS['products'][100]->variations[0]['is_in_stock']=false;
        check(!is_wp_error(save([['id'=>10,'qty'=>8]])));
        check($GLOBALS['price_reads']===0 && $GLOBALS['wpdb']->items[7][10]['qty']===8);
    },
    'disabled variations are rejected on save even with a warm structural cache' => function() {
        B2B_Matrix_API::rest_get_matrix_data(new Request(['product_id'=>100]));
        $GLOBALS['products'][10]->status='private';
        error_is(save([['id'=>10,'qty'=>8]]),'invalid_variation');
        check($GLOBALS['write_count']===0);
    },
    'unsupported or forbidden matrices do not remove the standard purchase form' => function() {
        $GLOBALS['products'][100]->attributes=[]; B2B_Matrix_API::setup_single_product_display(); check($GLOBALS['removed']===[]);
        fixture(); $GLOBALS['access']=false; B2B_Matrix_API::setup_single_product_display(); check($GLOBALS['removed']===[]);
        fixture(); $GLOBALS['product_access']=false; B2B_Matrix_API::setup_single_product_display(); check($GLOBALS['removed']===[]);
        fixture(); $GLOBALS['logged_in']=false; ob_start(); B2B_Matrix_API::setup_single_product_display(); ob_end_clean(); check($GLOBALS['removed']===[]);
    },
    'supported product matrix renders after summary content' => function() {
        ob_start();
        B2B_Matrix_API::setup_single_product_display();
        $early_css = ob_get_clean();
        check( strpos( $early_css, '#product-100 form.cart { display: none !important; }' ) !== false );
        check( $GLOBALS['hooks']['woocommerce_after_single_product_summary'] === [ 'B2B_Matrix_API', 'render_matrix_after_single_product' ] );
        check( $GLOBALS['hook_priorities']['woocommerce_after_single_product_summary'] === 11 );
        check( ! isset( $GLOBALS['hooks']['woocommerce_single_product_summary'] ) );
        check( $GLOBALS['removed'] === [] );
    },
    'canonical product IDs use integer casting in DB' => function() {
        check(!is_wp_error(save([['id'=>10,'qty'=>8]],'00100')));
        check(B2B_Matrix_API::get_user_product_quantities(7, 100)[10]===8);
    },
    'REST acknowledgement includes server canonical quantities' => function() { $r = B2B_Matrix_API::rest_save_matrix( new Request( [ 'product_id' => 100, 'items' => [ [ 'id' => 10, 'qty' => 4 ] ] ] ) ); check( $r['success'] === true && ( (array) $r['data']['items'] ) === [ 10 => 4, 11 => 3, 55 => 4 ] ); },
    'legacy usermeta automatically migrates to custom DB table' => function() {
        global $wpdb;
        $wpdb->items = [];
        $GLOBALS['meta'][7]['_b2b_preorder_items_100'] = [ 10 => 5, 11 => 7 ];
        $quantities = B2B_Matrix_API::get_user_product_quantities( 7, 100 );
        check( $quantities === [ 10 => 5, 11 => 7 ] );
        check( $wpdb->items[7][10]['qty'] === 5 && $wpdb->items[7][11]['qty'] === 7 );
        check( ! isset( $GLOBALS['meta'][7]['_b2b_preorder_items_100'] ) );
    },
    'preorder items and summary endpoints return user quantities and counts' => function() {
        $r = B2B_Matrix_API::rest_get_preorder_summary( new Request() );
        check( $r['success'] === true && $r['data']['summary']['total_items'] === 3 && $r['data']['summary']['total_qty'] === 9 );
        $r_items = B2B_Matrix_API::rest_get_all_preorder_items( new Request() );
        check( $r_items['success'] === true && count( $r_items['data']['items'] ) === 3 );
        B2B_Matrix_API::rest_clear_preorder_items( new Request() );
        $r_empty = B2B_Matrix_API::rest_get_preorder_summary( new Request() );
        check( $r_empty['data']['summary']['total_items'] === 0 );
    },
    'preorder list shortcode renders container with valid JSON config and handles guests' => function() {
        $GLOBALS['logged_in'] = false;
        $guest_html = B2B_Preorder_List::render_shortcode( [] );
        check( strpos( $guest_html, 'b2b-order-list-container' ) !== false );

        $GLOBALS['logged_in'] = true;
        $html = B2B_Preorder_List::render_shortcode( [] );
        check( strpos( $html, 'b2b-order-list-container' ) !== false && strpos( $html, 'data-b2b-config' ) !== false );
    },
    'preorder list payload aggregates products by parent, calculates brand summaries and customer info' => function() {
        $GLOBALS['meta'][7]['billing_first_name'] = '123456789';
        $GLOBALS['meta'][7]['billing_last_name'] = 'B2B Company SA';
        $GLOBALS['meta'][7]['billing_vat'] = '987654321';
        $GLOBALS['meta'][7]['billing_company'] = 'Old company field';
        $GLOBALS['meta'][7]['billing_email'] = 'billing@b2b.test';
        $payload = B2B_Preorder_List::get_list_payload( 7 );
        check( count( $payload['items'] ) >= 1 );
        check( $payload['totalQty'] === 5 && count( $payload['removedItems'] ) === 1 );
        check( $payload['customerInfo']['afm'] === '123456789' );
        check( $payload['customerInfo']['company'] === 'B2B Company SA' );
        check( $payload['customerInfo']['name'] === 'B2B Company SA' );
        check( $payload['customerInfo']['email'] === 'billing@b2b.test' );
        check( $payload['items'][0]['root_filters'][0]['name'] === 'Joyce' );
        check( $payload['items'][0]['category_filters'][0]['root_name'] === 'Joyce' );
        check( $payload['items'][0]['category_filters'][0]['root_id'] === 1 );
        check( count( $payload['brandSummary'] ) >= 1 );
    },
    'matrix save returns only its own parent summary without reading the full list' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] );
        $GLOBALS['wpdb']->items[7][99] = [ 'product_id' => 200, 'variation_id' => 99, 'qty' => 8 ];
        $r = B2B_Matrix_API::rest_save_matrix( new Request( [ 'product_id' => 100, 'items' => [ [ 'id' => 10, 'qty' => 4 ] ], 'include_list_patch' => 1 ] ) );
        check( $r['success'] && $r['data']['items']->{10} === 4 );
        $patch = $r['data']['listPatch'];
        check( $patch['parentId'] === 100 && $patch['item']['id'] === 100 );
        check( $patch['item']['total_qty'] === 7 && $patch['item']['total_val'] === 114.5 );
        check( count( $patch['item']['category_filters'] ) > 0 && strlen( $patch['quoteVersion'] ) === 64 );
        check( $GLOBALS['full_list_reads'] === 0 );
        check( $GLOBALS['wpdb']->items[7][99]['qty'] === 8 );
    },
    'AJAX matrix acknowledgement includes the parent patch when requested by My List' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] );
        $_POST = [ 'nonce' => 'valid', 'product_id' => 100, 'include_list_patch' => 1, 'items' => '[{"id":10,"qty":4}]' ];
        $r = ajax( 'ajax_save_matrix' );
        check( $r->body['success'] && $r->body['data']['listPatch']['item']['total_qty'] === 7 );
        check( $GLOBALS['full_list_reads'] === 0 );
    },
    'independent parent quotes preserve confirmation for other products while rejecting their stale prices' => function() {
        $rows = [ [ 'product_id' => 100, 'variation_id' => 10, 'qty' => 2, 'price' => '15.5' ], [ 'product_id' => 200, 'variation_id' => 99, 'qty' => 8, 'price' => '17.5' ] ];
        $quote = B2B_Preorder_Submission::parent_quotes( $rows );
        $rows[0]['qty'] = 9;
        $quote[100] = B2B_Preorder_Submission::quote_version( [ $rows[0] ] );
        check( B2B_Preorder_Submission::matches_quote( $rows, $quote ) );
        $rows[1]['price'] = '18';
        check( ! B2B_Preorder_Submission::matches_quote( $rows, $quote ) );
        $rows[1]['price'] = '17.5'; $rows[1]['qty'] = 9;
        check( ! B2B_Preorder_Submission::matches_quote( $rows, $quote ) );
    },
    'zeroing a parent returns a removal patch and hidden unavailable quantities remain visible' => function() {
        $r = B2B_Matrix_API::save_user_product_quantities( 7, 100, [ [ 'id' => 10, 'qty' => 0 ], [ 'id' => 11, 'qty' => 0 ] ], true );
        check( $r['listPatch']['item']['total_qty'] === 4 && $r['listPatch']['item']['unavailable'] );
        check( $r['listPatch']['quoteVersion'] === '' && $r['items']->{55} === 4 );
        unset( $GLOBALS['wpdb']->items[7][55] );
        $r = B2B_Matrix_API::save_user_product_quantities( 7, 100, [ [ 'id' => 10, 'qty' => 0 ] ], true );
        check( $r['listPatch']['item'] === null && count( (array) $r['items'] ) === 0 );
        check( $GLOBALS['full_list_reads'] === 0 );
    },
    'parent confirmation rejects missing, extra, stale and malformed quotes before order creation' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] );
        $quote = (array) B2B_Preorder_List::get_list_payload( 7 )['quoteParents'];
        check( count( $quote ) === 1 && strlen( $quote[100] ) === 64 );
        foreach ( [ [], [ 100 => 'bad' ], [ 100 => $quote[100], 200 => $quote[100] ], '{bad' ] as $bad ) {
            error_is( B2B_Preorder_List::execute_start_order( 7, '', $bad ), 'list_changed' );
        }
        $GLOBALS['products'][100]->variations[0]['display_price'] = 20;
        error_is( B2B_Preorder_List::execute_start_order( 7, '', wp_json_encode( (object) $quote ) ), 'list_changed' );
        $GLOBALS['products'][100]->variations[0]['display_price'] = 15.5;
        check( empty( $GLOBALS['orders'] ) );
        $patch = B2B_Matrix_API::save_user_product_quantities( 7, 100, [ [ 'id' => 10, 'qty' => 9 ] ], true );
        error_is( B2B_Preorder_List::execute_start_order( 7, '', $quote ), 'list_changed' );
        $quote[100] = $patch['listPatch']['quoteVersion'];
        check( ! is_wp_error( B2B_Preorder_List::execute_start_order( 7, '', wp_json_encode( (object) $quote ) ) ) );
    },
    'legacy _b2b_custom_order_list transparently migrates to custom DB table on list access' => function() {
        global $wpdb;
        $wpdb->items = [];
        $GLOBALS['meta'][7]['_b2b_custom_order_list'] = [ 10 => 12, 11 => 15 ];
        $items = B2B_Matrix_DB::get_user_all_items( 7 );
        check( count( $items ) === 2 );
        check( ! isset( $GLOBALS['meta'][7]['_b2b_custom_order_list'] ) );
        check( $wpdb->items[7][10]['qty'] === 12 && $wpdb->items[7][11]['qty'] === 15 );
    },
    'remove parent deletes only the variations belonging to that parent product' => function() {
        global $wpdb;
        $wpdb->items[7][99] = [ 'product_id' => 200, 'variation_id' => 99, 'qty' => 5 ];
        $r = B2B_Preorder_List::rest_remove_parent( new Request( [ 'parent_id' => 100 ] ) );
        check( $r['success'] === true );
        check( ! isset( $wpdb->items[7][10] ) && ! isset( $wpdb->items[7][11] ) );
        check( isset( $wpdb->items[7][99] ) && $wpdb->items[7][99]['qty'] === 5 );
    },
    'batched order process creates order shell, increments progress, and finalizes cleanly' => function() {
        global $wpdb;
        unset( $wpdb->items[7][55] );
        // 1. Start order
        $start = B2B_Preorder_List::execute_start_order( 7, 'Special shipping instructions' );
        check( ! is_wp_error( $start ) && ! empty( $start['token'] ) && $start['order_id'] === 1001 );
        check( $start['total'] === 2 && $start['processed'] === 0 && $start['status'] === 'building' );

        // 2. Add batch
        $batch = B2B_Preorder_List::execute_add_batch( 7, $start['order_id'], $start['token'] );
        check( ! is_wp_error( $batch ) && $batch['processed'] === 2 && $batch['status'] === 'ready_to_finalize' );

        // 3. Finalize order
        $fin = B2B_Preorder_List::execute_finalize_order( 7, $start['order_id'], $start['token'] );
        check( ! is_wp_error( $fin ) && $fin['complete'] === true && $fin['status'] === 'complete' );
        check( $fin['redirect'] === 'https://shop.test/my-account/orders/' );
        check( $GLOBALS['orders'][1001]->status === 'processing' );
        // Pre-order table should be cleared for user after finalization
        check( empty( $wpdb->items[7] ) );
    },

    'all list AJAX mutations reject invalid nonces without any write' => function() {
        foreach ( [ 'empty', 'remove-parent', 'update-matrix', 'start-order', 'add-batch', 'finalize-order', 'cancel-order' ] as $action ) {
            $_POST = [ 'security' => 'invalid', 'expected_user_id' => 7 ];
            try { B2B_Preorder_List::ajax_dispatch( $action ); throw new Exception('Did not reject'); }
            catch ( JsonExit $e ) { check( $e->status === 403 && !$e->body['success'] ); }
        }
        check( $GLOBALS['write_count'] === 0 );
    },
    'list permissions enforce B2B access and account identity' => function() {
        $GLOBALS['access'] = false; error_is( B2B_Preorder_List::check_permission(), 'b2b_forbidden' );
        $GLOBALS['access'] = true; error_is( B2B_Preorder_List::dispatch('empty', ['expected_user_id'=>8]), 'account_changed' );
        error_is( B2B_Preorder_List::check_permission(new Request([], 'bad')), 'rest_invalid_nonce' );
    },
    'shortcode HTML is identical across sessions and contains no personal payload' => function() {
        $a = B2B_Preorder_List::render_shortcode([]); $GLOBALS['logged_in']=false;
        check($a === B2B_Preorder_List::render_shortcode([]));
        check(strpos($a, 'nonce') === false && strpos($a, 'listPayload') === false);
    },
    'mixed delete and upsert rolls back when insert fails' => function() {
        $GLOBALS['fail_insert']=true;
        error_is(save([['id'=>10,'qty'=>0],['id'=>11,'qty'=>8]]), 'save_failed');
        check($GLOBALS['wpdb']->items[7][10]['qty']===2 && $GLOBALS['wpdb']->items[7][11]['qty']===3);
    },
    'failed legacy import keeps original metadata and current table values' => function() {
        $GLOBALS['meta'][7]['_b2b_custom_order_list']=[10=>40,11=>50]; $GLOBALS['fail_insert']=true;
        try { B2B_Matrix_DB::get_user_all_items(7); throw new Exception('Expected read failure'); } catch(RuntimeException $e) {}
        check(isset($GLOBALS['meta'][7]['_b2b_custom_order_list']));
        $GLOBALS['fail_insert']=false; $GLOBALS['wpdb']->last_error=''; B2B_Matrix_DB::get_user_all_items(7);
        check($GLOBALS['wpdb']->items[7][10]['qty']===2);
    },
    'submission rejects unavailable lines instead of silently dropping them' => function() {
        error_is(B2B_Preorder_List::execute_start_order(7), 'unavailable_items'); check(empty($GLOBALS['orders']));
    },
    'processing emails use the native WooCommerce notification pipeline only after all lines exist' => function() {
        $action = B2B_Preorder_Submission::PROCESSING_EMAIL_ACTION;
        $actions = B2B_Preorder_Submission::email_actions( ['woocommerce_order_status_pending_to_processing', $action] );
        check( count($actions) === 2 && in_array($action, $actions, true) );
        $admin = new class { public $calls = []; function trigger($id, $order) { $this->calls[] = [$id, $order->get_status(), count($order->items), $order->total]; } };
        $customer = clone $admin;
        $emails = ['WC_Email_New_Order'=>$admin, 'WC_Email_Customer_Processing_Order'=>$customer];
        check( B2B_Preorder_Submission::processing_emails($emails) === $emails );
        B2B_Preorder_Submission::processing_emails($emails);
        // Simulate WooCommerce's transactional dispatcher, including its notification suffix.
        add_action($action, function($id, $order) use ($action) { do_action($action . '_notification', $id, $order); }, 10, 2);
        unset($GLOBALS['wpdb']->items[7][55]);
        $start = B2B_Preorder_List::execute_start_order(7);
        check($GLOBALS['orders'][1001]->get_status() === 'b2b-draft' && !$admin->calls && !$customer->calls);
        B2B_Preorder_List::execute_add_batch(7, $start['order_id'], $start['token']);
        check(!$admin->calls && !$customer->calls);
        B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']);
        check($admin->calls === [[1001, 'processing', 2, 83.5]] && $customer->calls === $admin->calls);
        B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']);
        check(count($admin->calls) === 1 && count($customer->calls) === 1);
    },
    'My List removes site cancellations once in bulk and keeps valid selections with accurate totals' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $GLOBALS['jpd_cancelled_ids'] = [10];
        $payload = B2B_Preorder_List::get_list_payload(7);
        check($GLOBALS['jpd_requested_ids'] === [[10, 11]]);
        check(count($payload['items']) === 1 && !$payload['items'][0]['unavailable'] && $payload['items'][0]['total_qty'] === 3);
        check(count($GLOBALS['wpdb']->items[7]) === 1 && !isset($GLOBALS['wpdb']->items[7][10]));
        check($payload['removedItems'][0]['partial'] && $payload['removedItems'][0]['variations'][0]['id'] === 10);
        check($payload['totalQty'] === 3 && $payload['totalVal'] === 52.5 && strlen($payload['quoteVersion']) === 64);
        check(!is_wp_error(B2B_Preorder_List::execute_start_order(7, '', $payload['quoteVersion'])));
    },
    'unpublished or missing parent and variation products are pruned independently of the site helper' => function() {
        foreach (['draft', 'private', 'trash', 'deleted'] as $status) {
            fixture(); unset($GLOBALS['wpdb']->items[7][55]);
            if ($status === 'deleted') { unset($GLOBALS['products'][10]); } else { $GLOBALS['products'][10]->status = $status; }
            error_is(B2B_Preorder_List::execute_start_order(7), 'unavailable_items');
            $payload = B2B_Preorder_List::get_list_payload(7);
            check(!$payload['items'][0]['unavailable'] && $payload['items'][0]['total_qty'] === 3 && $payload['removedItems'][0]['partial']);
            fixture(); unset($GLOBALS['wpdb']->items[7][55]);
            if ($status === 'deleted') { unset($GLOBALS['products'][100]); } else { $GLOBALS['products'][100]->status = $status; }
            error_is(B2B_Preorder_List::execute_start_order(7), 'unavailable_items');
            $payload = B2B_Preorder_List::get_list_payload(7);
            check(!$payload['items'] && !$GLOBALS['wpdb']->items[7] && !$payload['removedItems'][0]['partial'] && count($payload['removedItems'][0]['variations']) === 2);
            check(!$GLOBALS['orders']);
        }
    },
    'cancellation notices survive lost responses and stale acknowledgements cannot clear newer cancellations' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['products'][10]->status = 'private';
        $first = B2B_Preorder_List::get_list_payload(7);
        $again = B2B_Preorder_List::get_list_payload(7);
        check($first['removedItems'] === $again['removedItems'] && $first['removedNoticeToken'] === $again['removedNoticeToken']);
        $GLOBALS['products'][11]->status = 'trash';
        $newer = B2B_Preorder_List::get_list_payload(7);
        check($newer['removedNoticeToken'] !== $first['removedNoticeToken'] && count($newer['removedItems'][0]['variations']) === 2);
        B2B_Matrix_DB::acknowledge_removals(7, $first['removedNoticeToken']);
        check(B2B_Preorder_List::get_list_payload(7)['removedNoticeToken'] === $newer['removedNoticeToken']);
        $GLOBALS['wpdb']->items[8][10] = ['product_id'=>100,'variation_id'=>10,'qty'=>9];
        B2B_Matrix_DB::acknowledge_removals(7, $newer['removedNoticeToken']);
        check(!B2B_Preorder_List::get_list_payload(7)['removedItems'] && isset($GLOBALS['wpdb']->items[8][10]));
    },
    'failed notice persistence or deletion never returns a falsely cleaned list and safely retries' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['products'][10]->status = 'draft';
        $GLOBALS['write_failure'] = true;
        error_is(B2B_Preorder_List::get_list_payload(7), 'database_error');
        check(isset($GLOBALS['wpdb']->items[7][10]) && !B2B_Matrix_DB::removal_notice(7)['items']);
        $GLOBALS['write_failure'] = false; $GLOBALS['wpdb']->last_error = ''; $GLOBALS['fail_prune_delete'] = true;
        error_is(B2B_Preorder_List::get_list_payload(7), 'database_error');
        check(isset($GLOBALS['wpdb']->items[7][10]) && B2B_Matrix_DB::removal_notice(7)['items']);
        $GLOBALS['fail_prune_delete'] = false; $GLOBALS['wpdb']->last_error = '';
        check(B2B_Preorder_List::get_list_payload(7)['removedItems'] && !isset($GLOBALS['wpdb']->items[7][10]));
    },
    'a staged removal notice is discarded if the product is republished after a failed delete' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['products'][10]->status = 'draft'; $GLOBALS['fail_prune_delete'] = true;
        error_is(B2B_Preorder_List::get_list_payload(7), 'database_error');
        $GLOBALS['fail_prune_delete'] = false; $GLOBALS['wpdb']->last_error = ''; $GLOBALS['products'][10]->status = 'publish';
        $payload = B2B_Preorder_List::get_list_payload(7);
        check(!$payload['removedItems'] && $payload['totalQty'] === 5 && isset($GLOBALS['wpdb']->items[7][10]));
    },
    'removal notice preserves catalog backslashes through WordPress metadata unslashing' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['products'][100]->sku = 'SKU\\STYLE'; $GLOBALS['products'][10]->status = 'draft';
        $payload = B2B_Preorder_List::get_list_payload(7);
        check($payload['removedItems'][0]['sku'] === 'SKU\\STYLE');
        check(B2B_Preorder_List::get_list_payload(7)['removedItems'][0]['sku'] === 'SKU\\STYLE');
    },
    'many canceled styles are removed with one bulk delete scoped to the current account' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        for($i=0;$i<15;$i++) {
            $pid=200+$i; $vid=2000+$i;
            $GLOBALS['products'][$pid] = new Product(); $GLOBALS['products'][$pid]->id=$pid; $GLOBALS['products'][$pid]->status='private';
            $GLOBALS['products'][$vid] = new Product('variation', $pid); $GLOBALS['products'][$vid]->id=$vid;
            $row=['product_id'=>$pid,'variation_id'=>$vid,'qty'=>6];
            $GLOBALS['wpdb']->items[7][$vid]=$GLOBALS['wpdb']->items[8][$vid]=$row;
        }
        $payload=B2B_Preorder_List::get_list_payload(7);
        check(count($payload['removedItems'])===15 && $payload['totalQty']===5 && count($GLOBALS['wpdb']->items[7])===2);
        check(count($GLOBALS['prune_delete_queries'])===1 && count($GLOBALS['wpdb']->items[8])===15);
        check(count($GLOBALS['jpd_requested_ids'])===1 && count($GLOBALS['jpd_requested_ids'][0])===17);
        B2B_Preorder_List::get_list_payload(7);
        check(count($GLOBALS['prune_delete_queries'])===1);
    },
    'automatic pruning preserves frozen submissions, missing prices and access-restricted published products' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $start = B2B_Preorder_List::execute_start_order(7);
        $GLOBALS['products'][10]->status = 'draft';
        $payload = B2B_Preorder_List::get_list_payload(7);
        check($payload['activeBatch'] && !$payload['removedItems'] && $payload['items'][0]['unavailable'] && count($GLOBALS['wpdb']->items[7]) === 2);
        fixture(); unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['product_access'] = false;
        $payload = B2B_Preorder_List::get_list_payload(7);
        check(!$payload['removedItems'] && $payload['items'][0]['unavailable'] && count($GLOBALS['wpdb']->items[7]) === 2);
        fixture(); unset($GLOBALS['wpdb']->items[7][55]); $GLOBALS['products'][100]->variations[0]['display_price'] = '';
        $payload = B2B_Preorder_List::get_list_payload(7);
        check(!$payload['removedItems'] && $payload['items'][0]['unpriced'] && count($GLOBALS['wpdb']->items[7]) === 2);
    },
    'removal acknowledgements require the same authenticated account and notices are isolated per site' => function() {
        $payload = B2B_Preorder_List::get_list_payload(7);
        error_is(B2B_Preorder_List::dispatch('acknowledge-removals', ['expected_user_id'=>8,'notice_token'=>$payload['removedNoticeToken']]), 'account_changed');
        $GLOBALS['logged_in'] = false;
        error_is(B2B_Preorder_List::dispatch('acknowledge-removals', ['expected_user_id'=>7,'notice_token'=>$payload['removedNoticeToken']]), 'login_required');
        $GLOBALS['logged_in'] = true; $GLOBALS['wpdb']->prefix = 'wp_2_';
        check(!B2B_Matrix_DB::removal_notice(7)['items']);
        $GLOBALS['wpdb']->prefix = 'wp_';
        check(B2B_Preorder_List::dispatch('acknowledge-removals', ['expected_user_id'=>7,'notice_token'=>$payload['removedNoticeToken']])['success']);
        check(!B2B_Matrix_DB::removal_notice(7)['items']);
    },
    'a failure after saving processing resumes without another status transition or clearing a new list' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $start = B2B_Preorder_List::execute_start_order(7);
        B2B_Preorder_List::execute_add_batch(7, $start['order_id'], $start['token']);
        $GLOBALS['write_failure'] = true;
        error_is(B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']), 'database_error');
        $order = $GLOBALS['orders'][1001];
        check($order->get_status() === 'processing' && count($GLOBALS['wpdb']->items[7]) === 2);
        $transitions = $order->transitions;
        $GLOBALS['write_failure'] = false; $GLOBALS['wpdb']->last_error = '';
        $done = B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']);
        check(!is_wp_error($done) && $done['complete'] && $order->transitions === $transitions);
        save([['id'=>10, 'qty'=>19]]);
        B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']);
        check($GLOBALS['wpdb']->items[7][10]['qty'] === 19 && $order->transitions === $transitions);
    },
    'legacy submitted batches finish without changing or resending their historical order status' => function() {
        unset($GLOBALS['wpdb']->items[7][55]);
        $start = B2B_Preorder_List::execute_start_order(7);
        B2B_Preorder_List::execute_add_batch(7, $start['order_id'], $start['token']);
        $order = $GLOBALS['orders'][1001]; $order->set_status('b2b-submitted'); $order->save();
        $transitions = $order->transitions;
        $done = B2B_Preorder_List::execute_finalize_order(7, $start['order_id'], $start['token']);
        check(!is_wp_error($done) && $done['complete'] && $order->get_status() === 'b2b-submitted' && $order->transitions === $transitions);
    },
    'processing preserves preorder stock policy without changing ordinary WooCommerce orders' => function() {
        $normal = new WC_Order(); $normal->save();
        check(B2B_Preorder_Submission::can_reduce_order_stock(true, $normal) === true);
        check(B2B_Preorder_Submission::reduce_order_stock(false, $normal->get_id()) === false);
        $normal->update_meta_data('_b2b_batch_token', 'foreign-token');
        check(B2B_Preorder_Submission::can_reduce_order_stock(true, $normal) === true);
        $preorder = new WC_Order(); $preorder->set_created_via('b2b-preorder');
        check(B2B_Preorder_Submission::can_reduce_order_stock(true, $preorder) === true);
        $preorder->update_meta_data('_b2b_batch_token', 'test-token'); $preorder->save();
        check(B2B_Preorder_Submission::can_reduce_order_stock(true, $preorder) === false);
        check(B2B_Preorder_Submission::reduce_order_stock(true, $preorder->get_id()) === false);
        check(B2B_Preorder_Submission::reduce_order_stock(true, 99999) === true);
    },
    'submission requires the same quantities and prices the customer confirmed' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] );
        $payload = B2B_Preorder_List::get_list_payload( 7 );
        $quote = $payload['quoteVersion'];
        check( strlen( $quote ) === 64 && $payload['totalVal'] === 83.5 );
        $GLOBALS['products'][100]->variations[0]['display_price'] = 16.5;
        error_is( B2B_Preorder_List::execute_start_order( 7, '', $quote ), 'list_changed' );
        check( empty( $GLOBALS['orders'] ) );
        $GLOBALS['products'][100]->variations[0]['display_price'] = 15.5;
        $GLOBALS['wpdb']->items[7][10]['qty'] = 4;
        error_is( B2B_Preorder_List::execute_start_order( 7, '', $quote ), 'list_changed' );
        check( empty( $GLOBALS['orders'] ) );
        $GLOBALS['wpdb']->items[7][10]['qty'] = 2;
        $start = B2B_Preorder_List::execute_start_order( 7, '', $quote );
        check( ! is_wp_error( $start ) && $start['order_id'] === 1001 );
    },
    'early finalization is rejected and active submission blocks all list edits' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $start=B2B_Preorder_List::execute_start_order(7);
        error_is(B2B_Preorder_List::execute_finalize_order(7,$start['order_id'],$start['token']), 'incomplete_batch');
        error_is(save([['id'=>10,'qty'=>8]]), 'submission_active');
        error_is(B2B_Matrix_DB::clear_user_items(7), 'submission_active');
        error_is(B2B_Matrix_DB::delete_user_product(7,100), 'submission_active');
        check(count($GLOBALS['wpdb']->items[7])===2);
    },
    'retry uses one order and snapshot prices; completed replay preserves new list' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $s=B2B_Preorder_List::execute_start_order(7);
        $again=B2B_Preorder_List::execute_start_order(7); check($again['token']===$s['token'] && count($GLOBALS['orders'])===1);
        B2B_Preorder_List::execute_add_batch(7,$s['order_id'],$s['token']);
        B2B_Preorder_List::execute_add_batch(7,$s['order_id'],$s['token']); check(count($GLOBALS['orders'][1001]->items)===2);
        B2B_Preorder_List::execute_finalize_order(7,$s['order_id'],$s['token']);
        save([['id'=>10,'qty'=>19]]);
        B2B_Preorder_List::execute_finalize_order(7,$s['order_id'],$s['token']);
        check($GLOBALS['wpdb']->items[7][10]['qty']===19);
    },
    'batch retry reads only current variations and finalization still reads the whole order' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $s=B2B_Preorder_List::execute_start_order(7);
        $partial = new WC_Order_Item_Product();
        $partial->set_order_id($s['order_id']); $partial->set_product($GLOBALS['products'][10]);
        $partial->set_quantity(2); $partial->set_total('31.00'); $partial->set_subtotal('31.00'); $partial->save();
        $r = B2B_Preorder_List::execute_add_batch(7,$s['order_id'],$s['token']);
        check(!is_wp_error($r) && $r['processed']===2 && count($GLOBALS['orders'][1001]->items)===2);
        check($GLOBALS['wpdb']->order_item_queries===1 && $GLOBALS['full_order_item_reads']===0);
        check(strpos($GLOBALS['wpdb']->last_order_item_sql, 'vim.meta_value IN')!==false);
        $done = B2B_Preorder_List::execute_finalize_order(7,$s['order_id'],$s['token']);
        check(!is_wp_error($done) && $done['complete'] && $GLOBALS['full_order_item_reads']===1);
    },
    'batch retry rejects duplicate saved variations and database read failures' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $s=B2B_Preorder_List::execute_start_order(7);
        $GLOBALS['wpdb']->fail_order_item_read = true;
        error_is(B2B_Preorder_List::execute_add_batch(7,$s['order_id'],$s['token']), 'database_error');
        check(count($GLOBALS['orders'][1001]->items)===0);
        $GLOBALS['wpdb']->fail_order_item_read = false; $GLOBALS['wpdb']->last_error = '';
        for ($i=0; $i<2; $i++) { $item=new WC_Order_Item_Product(); $item->set_order_id($s['order_id']); $item->set_product($GLOBALS['products'][10]); $item->save(); }
        error_is(B2B_Preorder_List::execute_add_batch(7,$s['order_id'],$s['token']), 'submission_error');
        check(count($GLOBALS['orders'][1001]->items)===2 && $GLOBALS['full_order_item_reads']===0);
    },
    'cancel retains draft and ownership is enforced' => function() {
        unset($GLOBALS['wpdb']->items[7][55]); $s=B2B_Preorder_List::execute_start_order(7);
        error_is(B2B_Preorder_Submission::run('cancel-order',8,$s['order_id'],$s['token']), 'forbidden');
        error_is(B2B_Preorder_Submission::run('cancel-order',7,999,$s['token']), 'invalid_batch');
        $r=B2B_Preorder_Submission::run('cancel-order',7,$s['order_id'],$s['token']); check($r['status']==='cancelled');
        check(count($GLOBALS['wpdb']->items[7])===2 && !is_wp_error(save([['id'=>10,'qty'=>8]])));
    },
    'lock contention rejects changes rather than overwriting data' => function() {
        $GLOBALS['lock_failure']=true; error_is(save([['id'=>10,'qty'=>20]]), 'list_busy'); check($GLOBALS['wpdb']->items[7][10]['qty']===2);
    },
    'greek and uppercase variation attributes resolve correctly' => function() {
        list($c, $s) = B2B_Matrix_Catalog::resolve_color_size_keys(['pa_xroma' => ['red'], 'pa_megethos' => ['l']]);
        check($c === 'pa_xroma' && $s === 'pa_megethos');

        list($c2, $s2) = B2B_Matrix_Catalog::resolve_color_size_keys(['Color' => ['black'], 'Size' => ['xl']]);
        check($c2 === 'Color' && $s2 === 'Size');

        list($c3, $s3) = B2B_Matrix_Catalog::resolve_color_size_keys(['Χρώμα' => ['άσπρο'], 'Μέγεθος' => ['m']]);
        check($c3 === 'Χρώμα' && $s3 === 'Μέγεθος');

        list($c4, $s4) = B2B_Matrix_Catalog::resolve_color_size_keys(['custom_shade' => ['a'], 'custom_fit' => ['b']]);
        check($c4 === 'custom_shade' && $s4 === 'custom_fit');
    },
    'render_matrix_container preserves canonical product ID despite altered post ID' => function() {
        global $product, $post;
        $product = $GLOBALS['products'][100];
        $post = (object) [ 'ID' => 9999 ]; // Simulate an altered global post from related products/UX block
        $html = B2B_Matrix_API::render_matrix_container([]);
        check(strpos($html, 'data-product-id="100"') !== false);
    },
    'single-attribute product with only sizes generates valid matrix structure' => function() {
        $p = new Product('variable', 0);
        $p->id = 500;
        $p->attributes = ['pa_size' => ['s', 'm']];
        $p->variations = [
            ['variation_id' => 501, 'attributes' => ['attribute_pa_size' => 's'], 'display_price' => 12.0, 'image' => ['src' => '/s.jpg'], 'is_in_stock' => true],
            ['variation_id' => 502, 'attributes' => ['attribute_pa_size' => 'm'], 'display_price' => 12.0, 'image' => ['src' => '/m.jpg'], 'is_in_stock' => true],
        ];
        $v1 = new Product('variation', 500); $v1->id = 501;
        $v2 = new Product('variation', 500); $v2->id = 502;
        $GLOBALS['products'][500] = $p;
        $GLOBALS['products'][501] = $v1;
        $GLOBALS['products'][502] = $v2;
        $struct = B2B_Matrix_Catalog::structure($p);
        check(!is_wp_error($struct));
        check($struct['colors'] === ['Standard']);
        check($struct['sizes'] === ['s', 'm']);
        check(isset($struct['matrix']['Standard']['s']));
        check($struct['matrix']['Standard']['s']['id'] === 501);
    },
];
// Buffer test output so PHP header calls can still run in the AJAX fixtures.
$tests = array_merge( $tests, require __DIR__ . '/order-channel-cases.php' );
ob_start();
$failed = 0;
foreach ( $tests as $name => $test ) { fixture(); try { $test(); echo "PASS $name\n"; } catch ( Throwable $e ) { $failed++; echo "FAIL $name: " . $e->getMessage() . "\n"; } }
echo count( $tests ) . " tests; $failed failures\n";
ob_end_flush();
exit( $failed ? 1 : 0 );
