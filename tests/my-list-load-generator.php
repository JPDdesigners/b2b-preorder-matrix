<?php
// Standalone behavioral checks for the NEW generator; no old seeding snippet is loaded.
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Error {
    private $code, $message;
    public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$actor = 1;
$hooks = [];
function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function get_current_user_id() { return $GLOBALS['actor']; }
function wp_set_current_user( $id ) { $GLOBALS['actor'] = $id; }
function current_user_can( $cap ) { return get_current_user_id() === 1; }
function get_user_by( $field, $id ) { return in_array( $id, [ 1, 2 ], true ) ? (object) [ 'ID' => $id ] : false; }
function current_time( $format ) { return '2026-09-30 12:00:00'; }
function wp_rand( $min, $max ) { return mt_rand( $min, $max ); }
function jpd_get_canceled_items_bulk( $ids ) { return array_values( array_intersect( $ids, $GLOBALS['cancelled'] ) ); }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( 'wp_die: ' . $message ); }
function check_admin_referer( $action ) { throw new RuntimeException( 'Invalid nonce' ); }

class FixtureVariation {
    public $id, $parent, $sku, $price = '12.50', $status = 'publish';
    public function __construct( $id ) { $this->id = $id; $this->parent = 100000 + intdiv( $id - 1, 10 ); $this->sku = 'SKU-' . $id; }
    public function is_type( $type ) { return $type === 'variation'; }
    public function get_status( $context ) { return $this->status; }
    public function get_sku() { return $this->sku; }
    public function get_parent_id() { return $this->parent; }
    public function get_price() { $GLOBALS['price_users'][ get_current_user_id() ] = true; return $this->price; }
}
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
class B2B_Matrix_API {
    const MAX_QTY = 99999;
    public static $denied = [], $deny_user = false;
    public static function check_read_permissions() { return self::$deny_user ? new WP_Error( 'denied', 'Denied account' ) : true; }
    public static function get_product( $id ) { return isset( self::$denied[ $id ] ) ? new WP_Error( 'denied', 'Denied parent' ) : (object) [ 'id' => $id ]; }
}
class B2B_Matrix_Catalog {
    public static $unsupported = [];
    public static function structure( $product ) {
        if ( isset( self::$unsupported[ $product->id ] ) ) { return new WP_Error( 'unsupported', 'Unsupported matrix' ); }
        $cells = [];
        foreach ( $GLOBALS['products'] as $id => $variation ) {
            if ( $variation->parent === $product->id ) { $cells[] = [ 'id' => $id ]; }
        }
        return [ 'matrix' => [ 'color' => $cells ] ];
    }
}
class B2B_Matrix_DB {
    public static $active = false, $locked = false;
    public static function table_items() { return 'wp_b2b_preorder_items'; }
    public static function ensure_editable( $uid ) { return self::$active ? new WP_Error( 'submission_active', 'Active submission' ) : true; }
    public static function with_user_lock( $uid, $callback ) {
        self::$locked = true;
        try { return $callback(); } finally { self::$locked = false; }
    }
    public static function get_user_all_items( $uid ) {
        return array_values( array_filter( $GLOBALS['wpdb']->items, static function( $row ) use ( $uid ) { return $row['user_id'] === $uid && $row['qty'] > 0; } ) );
    }
}
class GeneratorWPDB {
    public $posts = 'wp_posts', $last_error = '', $items = [], $queries = [], $insert_calls = 0, $fail_insert = 0, $engine = 'InnoDB';
    private $snapshot;
    public function esc_like( $value ) { return $value; }
    public function prepare( $sql, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
        return preg_replace_callback( '/%[ds]/', static function( $match ) use ( &$args ) {
            $arg = array_shift( $args );
            return $match[0] === '%d' ? (string) (int) $arg : "'" . str_replace( "'", "''", $arg ) . "'";
        }, $sql );
    }
    public function get_col( $sql ) {
        $ids = array_reverse( array_keys( $GLOBALS['products'] ) );
        if ( strpos( $sql, 'AND NOT EXISTS' ) !== false ) {
            $existing = array_fill_keys( array_column( B2B_Matrix_DB::get_user_all_items( get_current_user_id() ), 'variation_id' ), true );
            $ids = array_values( array_filter( $ids, static function( $id ) use ( $existing ) { return ! isset( $existing[ $id ] ); } ) );
        }
        return $ids;
    }
    public function get_row( $sql, $format ) {
        if ( strpos( $sql, 'SHOW TABLE STATUS' ) === 0 ) { return [ 'Engine' => $this->engine ]; }
        $rows = B2B_Matrix_DB::get_user_all_items( get_current_user_id() );
        return [ 'total_rows' => count( $rows ), 'parents' => count( array_unique( array_column( $rows, 'product_id' ) ) ) ];
    }
    public function query( $sql ) {
        if ( ! B2B_Matrix_DB::$locked ) { throw new RuntimeException( 'Write outside account lock' ); }
        $this->queries[] = $sql;
        $this->last_error = '';
        if ( $sql === 'START TRANSACTION' ) { $this->snapshot = $this->items; return 0; }
        if ( $sql === 'ROLLBACK' ) { $this->items = $this->snapshot; return 0; }
        if ( $sql === 'COMMIT' ) { $this->snapshot = null; return 0; }
        if ( preg_match( '/^DELETE .* WHERE user_id = (\d+)$/', $sql, $match ) ) {
            $uid = (int) $match[1];
            $this->items = array_values( array_filter( $this->items, static function( $row ) use ( $uid ) { return $row['user_id'] !== $uid; } ) );
            return 1;
        }
        if ( strpos( $sql, 'INSERT INTO' ) === 0 ) {
            $this->insert_calls++;
            if ( $this->fail_insert === $this->insert_calls ) { $this->last_error = 'Simulated insert failure'; return false; }
            preg_match_all( "/\((\d+), (\d+), (\d+), (\d+), NULL, '[^']+'\)/", $sql, $matches, PREG_SET_ORDER );
            foreach ( $matches as $m ) {
                foreach ( $this->items as $row ) {
                    if ( $row['user_id'] === (int) $m[1] && $row['variation_id'] === (int) $m[3] ) { throw new RuntimeException( 'Duplicate user/variation' ); }
                }
                $this->items[] = [ 'user_id' => (int) $m[1], 'product_id' => (int) $m[2], 'variation_id' => (int) $m[3], 'qty' => (int) $m[4], 'price' => null ];
            }
            return count( $matches );
        }
        throw new RuntimeException( 'Unexpected query: ' . $sql );
    }
}

$products = [];
for ( $i = 1; $i <= 5000; $i++ ) { $products[ $i ] = new FixtureVariation( $i ); }
$cancelled = [];
$price_users = [];
$wpdb = new GeneratorWPDB();
$wpdb->items[] = [ 'user_id' => 1, 'product_id' => 100000, 'variation_id' => 1, 'qty' => 9, 'price' => null ];
require __DIR__ . '/../snippets/my-list-load-generator.php';
$checks = 0;
function expect( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
    $GLOBALS['checks']++;
}
function fill( $rows, $mode = 'replace', $extra = [] ) {
    return B2B_My_List_Load_Generator::fill( array_merge( [ 'user_id' => 2, 'rows' => $rows, 'mode' => $mode, 'qty_min' => 2, 'qty_max' => 4 ], $extra ) );
}

$result = fill( 1000 );
expect( ! is_wp_error( $result ) && $result['total_rows'] === 1000, 'exact 1000 rows' );
expect( $wpdb->insert_calls === 4 && $result['parents'] === 100, '250-row bulk INSERT chunks and parent counts' );
expect( count( B2B_Matrix_DB::get_user_all_items( 1 ) ) === 1, 'other account stays untouched' );
expect( get_current_user_id() === 1 && array_keys( $price_users ) === [ 2 ], 'price getter sees recipient and actor is restored' );
expect( count( array_filter( B2B_Matrix_DB::get_user_all_items( 2 ), static function( $row ) {
    return $row['qty'] < 2 || $row['qty'] > 4 || $row['price'] !== null;
} ) ) === 0, 'random quantity bounds and live price storage' );
$result = fill( 2000 );
expect( ! is_wp_error( $result ) && $result['total_rows'] === 2000, 'replace 1000 with exactly 2000' );
$before = B2B_Matrix_DB::get_user_all_items( 2 );
$result = fill( 1000, 'append' );
expect( ! is_wp_error( $result ) && $result['total_rows'] === 3000, 'append 1000 distinct rows' );
$after = B2B_Matrix_DB::get_user_all_items( 2 );
expect( array_slice( $after, 0, 2000 ) === $before, 'append preserves existing quantities' );
expect( count( array_unique( array_column( $after, 'variation_id' ) ) ) === 3000, 'unique variations' );
$saved = $wpdb->items;
$query_count = count( $wpdb->queries );
$result = fill( 6000 );
expect( is_wp_error( $result ) && $result->get_error_code() === 'not_enough_products' && $wpdb->items === $saved && count( $wpdb->queries ) === $query_count, 'shortage leaves original list without writes' );
$result = fill( 3000, 'append' );
expect( is_wp_error( $result ) && $result->get_error_code() === 'not_enough_products' && $wpdb->items === $saved, 'append shortage excludes already selected variations' );
$wpdb->fail_insert = $wpdb->insert_calls + 2;
$result = fill( 500 );
expect( is_wp_error( $result ) && $wpdb->items === $saved && end( $wpdb->queries ) === 'ROLLBACK', 'failed second insert rolls back DELETE and first insert' );
$wpdb->fail_insert = 0;
B2B_Matrix_DB::$active = true;
$result = fill( 100 );
expect( is_wp_error( $result ) && $result->get_error_code() === 'submission_active' && $wpdb->items === $saved, 'active submission blocks seeding' );
B2B_Matrix_DB::$active = false;
$wpdb->engine = 'MyISAM';
expect( is_wp_error( fill( 100 ) ) && $wpdb->items === $saved, 'reject nontransactional table' );
$wpdb->engine = 'InnoDB';
foreach ( [ [ 'rows' => '2.5' ], [ 'rows' => 0 ], [ 'rows' => [] ], [ 'qty_max' => 100000 ], [ 'qty_min' => 8 ], [ 'user_id' => 3 ], [ 'mode' => 'bad' ] ] as $input ) {
    expect( is_wp_error( fill( 100, 'replace', $input ) ) && $wpdb->items === $saved, 'invalid input never mutates list' );
}
wp_set_current_user( 2 );
expect( is_wp_error( fill( 100 ) ) && $wpdb->items === $saved, 'nonadministrator cannot seed' );
wp_set_current_user( 1 );
B2B_Matrix_API::$deny_user = true;
expect( is_wp_error( fill( 100 ) ) && get_current_user_id() === 1, 'denied recipient restores administrator' );
B2B_Matrix_API::$deny_user = false;
$cancelled = [ 5000 ];
$products[4999]->sku = '';
$products[4998]->price = '';
$products[4997]->price = '-5';
$products[4996]->status = 'private';
B2B_Matrix_API::$denied[100498] = true;
B2B_Matrix_Catalog::$unsupported[100497] = true;
$result = fill( 20 );
$selected = array_column( B2B_Matrix_DB::get_user_all_items( 2 ), 'variation_id' );
expect( ! is_wp_error( $result ) && ! array_intersect( [ 5000, 4999, 4998, 4997, 4996 ], $selected ), 'skip cancelled, missing SKU, missing/negative price and unpublished variation' );
expect( ! array_intersect( range( 4971, 4990 ), $selected ), 'skip forbidden or unsupported matrix parents' );
expect( get_current_user_id() === 1 && ! B2B_Matrix_DB::$locked, 'release lock and restore actor at end' );
expect( isset( $hooks['admin_post_' . B2B_My_List_Load_Generator::PAGE] ) && ! isset( $hooks['admin_post_nopriv_' . B2B_My_List_Load_Generator::PAGE] ), 'POST handler registered only for authenticated users' );
$saved = $wpdb->items;
$_SERVER['REQUEST_METHOD'] = 'GET';
try { B2B_My_List_Load_Generator::handle(); expect( false, 'GET must stop' ); }
catch ( RuntimeException $e ) { expect( strpos( $e->getMessage(), 'wp_die:' ) === 0 && $wpdb->items === $saved, 'GET cannot trigger writes' ); }
$_SERVER['REQUEST_METHOD'] = 'POST';
try { B2B_My_List_Load_Generator::handle(); expect( false, 'invalid nonce must stop' ); }
catch ( RuntimeException $e ) { expect( $e->getMessage() === 'Invalid nonce' && $wpdb->items === $saved, 'invalid nonce cannot trigger writes' ); }
echo "PASS: generator behavior checks ({$checks} assertions). Mock WP/WC/SQL; live database not exercised.\n";
