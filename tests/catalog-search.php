<?php
// Execute the catalog SQL against an isolated in-memory database; no store is touched.
// SQLite LIKE needs an explicit escape character to reproduce MySQL's backslash escaping.
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
require_once __DIR__ . '/../includes/class-b2b-matrix-db.php';
require_once __DIR__ . '/../includes/class-b2b-preorder-list.php';

class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function taxonomy_exists( $name ) { return false; }
function wc_placeholder_img_src() { return '/placeholder.jpg'; }
class SearchProduct {
    public $sku, $name, $allowed;
    function __construct( $sku, $name, $allowed = true ) { $this->sku = $sku; $this->name = $name; $this->allowed = $allowed; }
    function get_sku() { return $this->sku; }
    function get_name() { return $this->name; }
    function get_price() { return 10; }
    function get_image_id() { return 0; }
}
class B2B_Matrix_API {
    static function get_product( $id ) {
        $product = $GLOBALS['catalog_products'][ $id ] ?? null;
        return $product && $product->allowed ? $product : new WP_Error();
    }
}
class SearchDB {
    public $prefix = 'test_', $posts = 'test_posts', $pdo, $prepared = [], $queries = [];
    function __construct() {
        $this->pdo = new PDO( 'sqlite::memory:' );
        $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $this->pdo->exec( 'CREATE TABLE test_posts (ID INTEGER PRIMARY KEY, post_parent INTEGER, post_type TEXT, post_status TEXT, post_title TEXT); CREATE TABLE test_wc_product_meta_lookup (product_id INTEGER PRIMARY KEY, sku TEXT COLLATE NOCASE); CREATE INDEX sku ON test_wc_product_meta_lookup (sku); CREATE TABLE test_b2b_preorder_items (user_id INTEGER, product_id INTEGER, qty INTEGER)' );
    }
    function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
    function prepare( $query, ...$args ) {
        $this->prepared[] = [ $query, $args ];
        $query = str_replace( 'LIKE %s', "LIKE %s ESCAPE '\\'", $query );
        $index = 0;
        return preg_replace_callback( '/%[ds]/', function( $match ) use ( &$index, $args ) {
            $value = $args[ $index++ ];
            return $match[0] === '%d' ? (string) (int) $value : $this->pdo->quote( $value );
        }, $query );
    }
    function get_results( $query, $output = ARRAY_A ) { $this->queries[] = $query; return $this->pdo->query( $query )->fetchAll( PDO::FETCH_ASSOC ); }
    function get_col( $query ) { $this->queries[] = $query; return $this->pdo->query( $query )->fetchAll( PDO::FETCH_COLUMN ); }
    function add( $id, $sku, $name = '', $parent = 0, $status = 'publish', $allowed = true ) {
        $this->pdo->prepare( 'INSERT INTO test_posts VALUES (?, ?, ?, ?, ?)' )->execute( [ $id, $parent, $parent ? 'product_variation' : 'product', $status, $name ] );
        $this->pdo->prepare( 'INSERT INTO test_wc_product_meta_lookup VALUES (?, ?)' )->execute( [ $id, $sku ] );
        if ( ! $parent ) { $GLOBALS['catalog_products'][ $id ] = new SearchProduct( $sku, $name, $allowed ); }
    }
}
function check( $value, $message = 'Assertion failed' ) { if ( ! $value ) { throw new Exception( $message ); } }
function search( $query, $sku_only = false, $user = 7 ) { return B2B_Preorder_List::search_products_catalog( $query, $sku_only, $user ); }
$tests = [
    'exact, prefix, contains and title matches retain their priority' => function( $db ) {
        $db->add( 1, '82413851', 'Contains' ); $db->add( 2, '24138510', 'Prefix' );
        $db->add( 3, '2413851', 'Exact' ); $db->add( 4, 'OTHER', 'Name with 2413851' );
        $result = search( ' 2413851 ' );
        check( array_column( $result, 'id' ) === [ 3, 2, 1, 4 ] );
        check( array_column( $result, 'sku_match' ) === [ 'exact', 'prefix', 'contains', 'title' ] );
        check( $db->prepared[0][1] === [ '2413851' ] && $db->prepared[1][1] === [ '2413851%' ] );
        check( strpos( $db->prepared[0][0], 'lookup.sku = %s' ) !== false );
        check( strpos( implode( ' ', $db->queries ), 'postmeta' ) === false );
    },
    'variation SKUs open a published parent once and retain the matching code' => function( $db ) {
        $db->add( 1, 'PARENT', 'Parent' );
        for ( $i = 100; $i < 160; $i++ ) { $db->add( $i, '2413851-' . $i, '', 1 ); }
        $db->add( 2, '2413851999', 'Second parent' );
        check( array_column( search( '2413851' ), 'id' ) === [ 1, 2 ] );
        $result = search( '2413851-120' );
        check( count( $result ) === 1 && $result[0]['id'] === 1 && $result[0]['sku'] === 'PARENT' );
        check( $result[0]['matched_sku'] === '2413851-120' && $result[0]['sku_match'] === 'exact' );
    },
    'draft variations and children of draft parents never enter results' => function( $db ) {
        $db->add( 1, 'PARENT', 'Parent', 0, 'draft' ); $db->add( 10, '2413851', '', 1 );
        $db->add( 2, 'PUBLIC', 'Public' ); $db->add( 20, '2413851', '', 2, 'draft' );
        $db->add( 3, '24138510', 'Published prefix' );
        check( array_column( search( '2413851' ), 'id' ) === [ 3 ] );
    },
    'access-denied or unsupported candidates do not consume the result limit' => function( $db ) {
        for ( $i = 1; $i <= 15; $i++ ) { $db->add( $i, '2410-' . sprintf( '%02d', $i ), 'Denied', 0, 'publish', false ); }
        $db->add( 16, '2410-16', 'Available' ); $db->add( 17, 'OTHER', 'Name 2410' );
        check( array_column( search( '2410' ), 'id' ) === [ 16, 17 ] );
    },
    'a full exact/prefix page skips the wider SKU scan and title search' => function( $db ) {
        $db->add( 1, '2410', 'Exact' );
        for ( $i = 2; $i <= 20; $i++ ) { $db->add( $i, '2410-' . sprintf( '%02d', $i ), 'Prefix' ); }
        check( count( search( '2410', false, 0 ) ) === 8 );
        check( count( $db->queries ) === 2 && count( $db->prepared ) === 2 );
    },
    'SKU-only excludes names while normal search still finds product names' => function( $db ) {
        $db->add( 1, 'OTHER', 'Φούτερ με κουκούλα' );
        check( search( 'Φούτερ', true ) === [] );
        check( array_column( search( 'Φούτερ' ), 'id' ) === [ 1 ] );
    },
    'catalog search never reads list quantities from the custom table' => function( $db ) {
        $db->add( 1, '2413851', 'Exact' );
        $db->pdo->exec( 'INSERT INTO test_b2b_preorder_items VALUES (7, 1, 3), (7, 1, 4), (7, 1, 0), (8, 1, 99)' );
        foreach ( [7, 8, 0] as $user ) {
            $result = search( '2413851', false, $user );
            check( count( $result ) === 1 && ! array_key_exists( 'in_list_qty', $result[0] ) );
        }
        check( strpos( implode( ' ', $db->queries ), 'b2b_preorder_items' ) === false );
    },
    'SQL wildcard characters in a SKU are literal and quotes remain data' => function( $db ) {
        $db->add( 1, "A_%'", 'Exact' ); $db->add( 2, "A_%'1", 'Prefix' );
        $db->add( 3, "XA_%'", 'Contains' ); $db->add( 4, "ABC'", 'Noise' );
        check( array_column( search( "A_%'" ), 'id' ) === [ 1, 2, 3 ] );
        $db->add( 5, 'BACK\\SLASH', 'Backslash' );
        check( array_column( search( 'BACK\\SLASH' ), 'id' ) === [ 5 ] );
    },
    'empty search never queries the catalog' => function( $db ) { check( search( '   ' ) === [] && $db->queries === [] ); },
    'short numeric queries never query while four digits and two-letter names do' => function( $db ) {
        $db->add( 1, '2410', 'AB coat' );
        foreach ( ['2', '24', '241', ' A '] as $query ) { check( search( $query ) === [] && $db->queries === [] ); }
        check( array_column( search( '2410' ), 'id' ) === [1] );
        check( array_column( search( 'AB' ), 'id' ) === [1] );
    },
];
$failed = 0;
foreach ( $tests as $name => $test ) {
    $GLOBALS['catalog_products'] = []; $GLOBALS['wpdb'] = new SearchDB();
    try { $test( $GLOBALS['wpdb'] ); echo "PASS $name\n"; }
    catch ( Throwable $e ) { $failed++; echo "FAIL $name: {$e->getMessage()}\n"; }
}
echo count( $tests ) . " tests; $failed failures\n";
exit( $failed ? 1 : 0 );
