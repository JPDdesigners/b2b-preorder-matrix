<?php
/**
 * Plugin Name: B2B Pre-Order Matrix API
 * Description: Private pre-order quantities and a cache-safe Vue matrix for WooCommerce.
 * Version: 3.6.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'B2B_MATRIX_VERSION' ) ) { define( 'B2B_MATRIX_VERSION', '3.6.1' ); }
if ( ! defined( 'B2B_MATRIX_PLUGIN_FILE' ) ) { define( 'B2B_MATRIX_PLUGIN_FILE', __FILE__ ); }
add_action( 'before_woocommerce_init', static function() {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', B2B_MATRIX_PLUGIN_FILE, true );
    }
} );

require_once __DIR__ . '/includes/class-b2b-matrix-db.php';
B2B_Matrix_DB::init();
require_once __DIR__ . '/includes/class-b2b-matrix-maintenance.php';
B2B_Matrix_Maintenance::init();
require_once __DIR__ . '/includes/class-b2b-matrix-settings.php';
B2B_Matrix_Settings::init();
require_once __DIR__ . '/includes/class-b2b-matrix-catalog.php';
B2B_Matrix_Catalog::init();
require_once __DIR__ . '/includes/class-b2b-order-channel.php';
B2B_Order_Channel::init();
require_once __DIR__ . '/includes/class-b2b-preorder-list.php';
B2B_Preorder_List::init();

class B2B_Matrix_API {
    const MAX_QTY = 99999;
    const MAX_ITEMS = 2000;

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
        add_filter( 'rest_post_dispatch', [ __CLASS__, 'rest_nocache' ], 10, 3 );
        foreach ( [ 'b2b_matrix_session' => 'ajax_session', 'b2b_get_matrix_data' => 'ajax_get_matrix_data', 'b2b_save_matrix' => 'ajax_save_matrix' ] as $action => $callback ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, $callback ] );
            add_action( 'wp_ajax_nopriv_' . $action, [ __CLASS__, $action === 'b2b_save_matrix' ? 'ajax_unauthorized' : $callback ] );
        }
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_shortcode( 'b2b_product_matrix', [ __CLASS__, 'render_matrix_container' ] );
        add_action( 'woocommerce_before_single_product', [ __CLASS__, 'setup_single_product_display' ] );
    }

    public static function register_rest_routes() {
        register_rest_route( 'b2b/v1', '/matrix', [
            [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'rest_get_matrix_data' ], 'permission_callback' => [ __CLASS__, 'check_read_permissions' ] ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'rest_save_matrix' ], 'permission_callback' => [ __CLASS__, 'check_write_permissions' ] ],
        ] );
        register_rest_route( 'b2b/v1', '/preorder/items', [
            [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'rest_get_all_preorder_items' ], 'permission_callback' => [ __CLASS__, 'check_write_permissions' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'rest_clear_preorder_items' ], 'permission_callback' => [ __CLASS__, 'check_write_permissions' ] ],
        ] );
        register_rest_route( 'b2b/v1', '/preorder/summary', [
            [ 'methods' => 'GET', 'callback' => [ __CLASS__, 'rest_get_preorder_summary' ], 'permission_callback' => [ __CLASS__, 'check_write_permissions' ] ],
        ] );
    }

    public static function send_nocache_headers() {
        nocache_headers();
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Vary: Cookie', false );
        do_action( 'litespeed_control_set_nocache', 'B2B Matrix private response' );
    }

    // Applies to validation/authentication failures too, before callbacks are reached.
    public static function rest_nocache( $response, $server, $request ) {
        if ( strpos( $request->get_route(), '/b2b/v1/' ) === 0 ) {
            self::send_nocache_headers();
            $response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
            $response->header( 'Vary', 'Cookie', false );
        }
        return $response;
    }

    private static function error( $code, $message, $status = 400 ) {
        return new WP_Error( $code, $message, [ 'status' => $status ] );
    }

    public static function check_read_permissions( $request = null ) {
        if ( ! is_user_logged_in() ) { return true; }
        // Preserve the existing logged-in policy; shops can restrict to their B2B role here.
        if ( ! apply_filters( 'b2b_matrix_user_can_access', true, get_current_user_id() ) ) {
            return self::error( 'b2b_forbidden', 'Δεν έχετε πρόσβαση στις λίστες παραγγελίας.', 403 );
        }
        return true;
    }

    public static function check_write_permissions( $request ) {
        if ( ! is_user_logged_in() ) {
            return self::error( 'rest_forbidden', 'Συνδεθείτε στον λογαριασμό σας για να αποθηκεύσετε τη λίστα παραγγελίας.', 401 );
        }
        $permission = self::check_read_permissions( $request );
        if ( is_wp_error( $permission ) ) { return $permission; }
        if ( ! wp_verify_nonce( $request->get_header( 'x_wp_nonce' ), 'wp_rest' ) ) {
            return self::error( 'rest_invalid_nonce', 'Η σύνδεση έληξε. Δοκιμάστε ξανά την αποθήκευση.', 403 );
        }
        return true;
    }

    // Read-only bootstrap: WP authenticates admin-ajax cookies without a REST nonce.
    // The nonce is never embedded in cacheable HTML or exposed through CORS.
    public static function ajax_session() {
        self::send_nocache_headers();
        $permission = self::check_read_permissions();
        if ( is_wp_error( $permission ) ) { self::ajax_error( $permission ); }
        wp_send_json_success( [ 'nonce' => wp_create_nonce( 'wp_rest' ), 'user_id' => get_current_user_id() ] );
    }

    public static function ajax_unauthorized() {
        self::send_nocache_headers();
        self::ajax_error( self::error( 'rest_forbidden', 'Συνδεθείτε στον λογαριασμό σας.', 401 ) );
    }

    private static function ajax_error( $error ) {
        wp_send_json_error( [ 'code' => $error->get_error_code(), 'message' => $error->get_error_message() ], $error->get_error_data()['status'] ?? 400 );
    }

    private static function valid_integer( $value, $min, $max = PHP_INT_MAX ) {
        return ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^\d+$/D', $value ) ) )
            && $value >= $min && $value <= $max;
    }

    public static function get_product( $id ) {
        if ( ! self::valid_integer( $id, 1 ) ) { return self::error( 'invalid_product', 'Μη έγκυρο προϊόν.' ); }
        if ( ! function_exists( 'wc_get_product' ) ) { return self::error( 'woocommerce_missing', 'Το WooCommerce δεν είναι διαθέσιμο.', 503 ); }
        $product = wc_get_product( (int) $id );
        if ( ! $product || ! $product->is_type( 'variable' ) || $product->get_status() !== 'publish' || post_password_required( (int) $id ) ) {
            return self::error( 'product_unavailable', 'Το προϊόν δεν είναι διαθέσιμο για λίστα παραγγελίας.', 404 );
        }
        if ( ! apply_filters( 'b2b_matrix_product_can_access', true, $product, get_current_user_id() ) ) {
            return self::error( 'product_forbidden', 'Δεν έχετε πρόσβαση σε αυτό το προϊόν.', 403 );
        }
        return $product;
    }

    public static function build_matrix_payload( $product_id, $user_id, $known_version = null ) {
        $product = self::get_product( $product_id );
        if ( is_wp_error( $product ) ) { return $product; }
        $structure = B2B_Matrix_Catalog::structure( $product );
        if ( is_wp_error( $structure ) ) { return $structure; }
        $prices = [];
        foreach ( $structure['matrix'] as $row ) {
            foreach ( $row as $cell ) {
                $variation = wc_get_product( $cell['id'] );
                // Fresh per-request catalog price, without tax/cart calculations.
                $price = $variation ? $variation->get_price() : '';
                $prices[ $cell['id'] ] = is_numeric( $price ) && (float) $price >= 0 ? (float) $price : null;
            }
        }
        try {
            $existing = $user_id ? self::get_user_product_quantities( $user_id, (int) $product_id ) : [];
        } catch ( Throwable $e ) {
            // Lock timeout during a running submission/season reset or a DB read error: never a PHP fatal.
            return new WP_Error( 'list_busy', 'Η λίστα σας ενημερώνεται αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγα δευτερόλεπτα.', [ 'status' => 503 ] );
        }
        $payload = [
            'nonce' => wp_create_nonce( 'wp_rest' ), 'user_id' => (int) $user_id,
            'appearance' => B2B_Matrix_Settings::get(), 'login_url' => wc_get_page_permalink( 'myaccount' ),
            'structure_version' => $structure['structure_version'], 'prices' => (object) $prices,
            'currency' => get_woocommerce_currency(), 'price_decimals' => wc_get_price_decimals(),
            'max_qty' => self::MAX_QTY,
            'existing_quantities' => (object) $existing,
        ];
        if ( $known_version !== $structure['structure_version'] ) {
            // Full response also supports old cached frontend assets during an upgrade.
            foreach ( $structure['matrix'] as &$row ) { foreach ( $row as &$cell ) { $cell['price'] = $prices[ $cell['id'] ]; } unset( $cell ); } unset( $row );
            $payload = array_merge( $structure, $payload );
        }
        return $payload;
    }

    public static function rest_get_matrix_data( $request ) {
        return rest_ensure_response( self::build_matrix_payload( $request->get_param( 'product_id' ), get_current_user_id(), $request->get_param( 'structure_version' ) ) );
    }

    public static function get_user_product_quantities( $user_id, $product_id ) {
        if ( ! self::valid_integer( $user_id, 1 ) || ! self::valid_integer( $product_id, 1 ) ) {
            return [];
        }
        return B2B_Matrix_DB::get_user_product_quantities( (int) $user_id, (int) $product_id );
    }

    public static function save_user_product_quantities( $user_id, $product_id, $items, $include_list_patch = false ) {
        return B2B_Matrix_DB::with_user_lock( $user_id, function() use ( $user_id, $product_id, $items, $include_list_patch ) {
            $saved = self::save_quantities_locked( $user_id, $product_id, $items );
            if ( is_wp_error( $saved ) || ! $include_list_patch ) { return $saved; }
            return [ 'items' => (object) $saved, 'listPatch' => B2B_Preorder_List::get_list_payload( $user_id, (int) $product_id, $saved ) ];
        } );
    }

    private static function save_quantities_locked( $user_id, $product_id, $items ) {
        if ( ! is_user_logged_in() ) { return self::error( 'rest_forbidden', 'Η αποθήκευση λίστας απαιτεί σύνδεση.', 401 ); }
        $permission = self::check_read_permissions();
        if ( is_wp_error( $permission ) ) { return $permission; }
        if ( $user_id !== get_current_user_id() ) { return self::error( 'invalid_user', 'Μη έγκυρος λογαριασμός.', 403 ); }
        $product = self::get_product( $product_id );
        if ( is_wp_error( $product ) ) { return $product; }
        $product_id = (int) $product_id;
        $payload = B2B_Matrix_Catalog::structure( $product );
        if ( is_wp_error( $payload ) ) { return $payload; }
        if ( ! is_array( $items ) || ! $items || count( $items ) > self::MAX_ITEMS || array_keys( $items ) !== range( 0, count( $items ) - 1 ) ) {
            return self::error( 'invalid_items', 'Μη έγκυρη λίστα αλλαγών.' );
        }
        $allowed = [];
        foreach ( $payload['matrix'] as $row ) { foreach ( $row as $cell ) { $allowed[ $cell['id'] ] = true; } }
        $editable = B2B_Matrix_DB::ensure_editable( $user_id );
        if ( is_wp_error( $editable ) ) { return $editable; }
        try { $current = self::get_user_product_quantities( $user_id, $product_id ); }
        catch ( Throwable $e ) { return self::error( 'list_busy', 'Η λίστα σας ενημερώνεται αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγα δευτερόλεπτα.', 503 ); }
        $updated = $current;
        $seen = [];
        $changed = [];
        // Validate the entire request before writing anything. Prices always come from WooCommerce.
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || ! self::valid_integer( $item['id'] ?? null, 1 ) || ! self::valid_integer( $item['qty'] ?? null, 0, self::MAX_QTY ) ) {
                return self::error( 'invalid_quantity', 'Οι ποσότητες πρέπει να είναι ακέραιες από 0 έως ' . self::MAX_QTY . '.' );
            }
            $id = (int) $item['id'];
            $qty = (int) $item['qty'];
            $variation = wc_get_product( $id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_status( 'edit' ) !== 'publish' || (int) $variation->get_parent_id() !== (int) $product_id || ! isset( $allowed[ $id ] ) || isset( $seen[ $id ] ) ) {
                return self::error( 'invalid_variation', 'Μη διαθέσιμη, ξένη ή διπλή παραλλαγή. Ανανεώστε τον πίνακα.', 422 );
            }
            $seen[ $id ] = true;
            $old_qty = $current[ $id ] ?? 0;
            if ( $qty !== $old_qty ) {
                $changed[ $id ] = $qty;
            }
            if ( $qty > 0 ) { $updated[ $id ] = $qty; } else { unset( $updated[ $id ] ); }
        }

        // If no quantities changed, avoid unnecessary database writes
        if ( empty( $changed ) ) {
            return $updated;
        }

        $res = B2B_Matrix_DB::save_user_product_quantities( $user_id, $product_id, $changed, $updated );
        if ( is_wp_error( $res ) ) {
            return $res;
        }
        return $updated;
    }

    public static function rest_save_matrix( $request ) {
        $params = $request->get_json_params();
        $include_patch = ! empty( $params['include_list_patch'] );
        $result = self::save_user_product_quantities( get_current_user_id(), $params['product_id'] ?? null, $params['items'] ?? null, $include_patch );
        if ( is_wp_error( $result ) ) { return $result; }
        $data = $include_patch ? $result : [ 'items' => (object) $result ];
        $data['nonce'] = wp_create_nonce( 'wp_rest' );
        return rest_ensure_response( [ 'success' => true, 'data' => $data ] );
    }

    public static function rest_get_all_preorder_items( $request ) {
        $user_id = get_current_user_id();
        try { $items = B2B_Matrix_DB::get_user_all_items( $user_id ); }
        catch ( Throwable $e ) { return new WP_Error( 'list_busy', 'Η λίστα σας ενημερώνεται αυτή τη στιγμή. Δοκιμάστε ξανά σε λίγα δευτερόλεπτα.', [ 'status' => 503 ] ); }
        return rest_ensure_response( [
            'success' => true,
            'data'    => [
                'items' => $items,
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ],
        ] );
    }

    public static function rest_get_preorder_summary( $request ) {
        $user_id = get_current_user_id();
        $summary = B2B_Matrix_DB::get_user_summary( $user_id );
        return rest_ensure_response( [
            'success' => true,
            'data'    => [
                'summary' => $summary,
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ],
        ] );
    }

    public static function rest_clear_preorder_items( $request ) {
        $user_id = get_current_user_id();
        $cleared = B2B_Matrix_DB::clear_user_items( $user_id );
        if ( is_wp_error( $cleared ) ) { return $cleared; }
        return rest_ensure_response( [
            'success' => $cleared,
            'data'    => [
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ],
        ] );
    }

    public static function ajax_get_matrix_data() {
        self::send_nocache_headers();
        $permission = self::check_read_permissions();
        if ( is_wp_error( $permission ) ) { self::ajax_error( $permission ); }
        $payload = self::build_matrix_payload( $_GET['product_id'] ?? null, get_current_user_id(), $_GET['structure_version'] ?? null );
        if ( is_wp_error( $payload ) ) { self::ajax_error( $payload ); }
        wp_send_json_success( $payload );
    }

    public static function ajax_save_matrix() {
        self::send_nocache_headers();
        if ( ! is_user_logged_in() ) { self::ajax_unauthorized(); }
        $permission = self::check_read_permissions();
        if ( is_wp_error( $permission ) ) { self::ajax_error( $permission ); }
        if ( ! check_ajax_referer( 'wp_rest', 'nonce', false ) ) { self::ajax_error( self::error( 'rest_invalid_nonce', 'Η σύνδεση έληξε. Δοκιμάστε ξανά.', 403 ) ); }
        $raw = $_POST['items'] ?? null;
        $items = is_string( $raw ) ? json_decode( wp_unslash( $raw ), true ) : null;
        $include_patch = ! empty( $_POST['include_list_patch'] );
        $result = self::save_user_product_quantities( get_current_user_id(), $_POST['product_id'] ?? null, $items, $include_patch );
        if ( is_wp_error( $result ) ) { self::ajax_error( $result ); }
        $data = $include_patch ? $result : [ 'items' => (object) $result ];
        $data['nonce'] = wp_create_nonce( 'wp_rest' );
        wp_send_json_success( $data );
    }

    public static function enqueue_assets( $force = false ) {
        global $post;
        $content = ! empty( $post->post_content ) ? $post->post_content : '';
        $has_list_shortcode = $content && ( has_shortcode( $content, 'b2b_product_matrix' ) || has_shortcode( $content, 'b2b_preorder_list' ) || has_shortcode( $content, 'b2b_custom_order_list' ) );
        if ( ! $force && ! ( function_exists( 'is_product' ) && is_product() ) && ! $has_list_shortcode ) { return; }
        $path = plugin_dir_path( __FILE__ ) . 'b2b-matrix/dist/';
        $url = plugin_dir_url( __FILE__ ) . 'b2b-matrix/dist/';
        if ( ! file_exists( $path . 'matrix-app.js' ) || ! file_exists( $path . 'matrix-style.css' ) ) { return; }
        wp_enqueue_style( 'b2b-matrix-style', $url . 'matrix-style.css', [], B2B_MATRIX_VERSION . '-' . filemtime( $path . 'matrix-style.css' ) );
        wp_enqueue_script_module( 'b2b-matrix-app', $url . 'matrix-app.js', [], B2B_MATRIX_VERSION . '-' . filemtime( $path . 'matrix-app.js' ) );
    }

    private static $current_single_product_id = null;

    public static function render_matrix_container( $atts ) {
        $default_id = 0;
        if ( ! empty( self::$current_single_product_id ) ) {
            $default_id = self::$current_single_product_id;
        } elseif ( ! empty( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) && method_exists( $GLOBALS['product'], 'get_id' ) ) {
            $default_id = $GLOBALS['product']->get_id();
        } elseif ( function_exists( 'is_product' ) && is_product() && function_exists( 'get_queried_object_id' ) && get_queried_object_id() ) {
            $default_id = get_queried_object_id();
        } else {
            $default_id = get_the_ID();
        }

        $atts = shortcode_atts( [ 'product_id' => $default_id ], $atts, 'b2b_product_matrix' );
        self::enqueue_assets( true );
        $structure = null;
        $product = self::get_product( absint( $atts['product_id'] ) );
        if ( ! is_wp_error( $product ) && ! is_wp_error( self::check_read_permissions() ) ) {
            $catalog = B2B_Matrix_Catalog::structure( $product );
            if ( ! is_wp_error( $catalog ) ) { $structure = $catalog; }
        }
        return sprintf( '<div class="b2b-vue-matrix-container" data-product-id="%d" data-api-endpoint="%s" data-session-endpoint="%s" data-appearance="%s" data-structure="%s"></div>',
            absint( $atts['product_id'] ), esc_url( rest_url( 'b2b/v1/matrix' ) ), esc_url( admin_url( 'admin-ajax.php' ) ), esc_attr( wp_json_encode( B2B_Matrix_Settings::get() ) ), esc_attr( wp_json_encode( $structure ) ) );
    }

    public static function setup_single_product_display() {
        global $product;
        if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) || ! $product->is_type( 'variable' ) ) {
            return;
        }
        if ( ! apply_filters( 'b2b_matrix_auto_inject_single_product', true, $product ) ) {
            return;
        }
        if ( is_wp_error( self::check_read_permissions() ) ) { return; }
        $allowed_product = self::get_product( $product->get_id() );
        if ( is_wp_error( $allowed_product ) ) { return; }
        $structure = B2B_Matrix_Catalog::structure( $allowed_product );
        if ( is_wp_error( $structure ) || ! $structure['matrix'] ) { return; }

        self::$current_single_product_id = (int) $product->get_id();

        // This runs before the product summary is rendered, so the original cart
        // form is hidden on the first paint while the Vue matrix loads its data.
        $product_id = self::$current_single_product_id;
        echo '<style id="b2b-matrix-hide-cart">#product-' . esc_attr( $product_id ) . ' form.cart { display: none !important; }</style>';

        // Prevent duplicate rendering if legacy snippet exists in child theme functions.php
        if ( function_exists( 'has_action' ) && has_action( 'woocommerce_after_single_product_summary', 'b2b_render_vue_matrix_single' ) ) {
            remove_action( 'woocommerce_after_single_product_summary', 'b2b_render_vue_matrix_single', 5 );
        }

        // Woo tabs are priority 10; upsells and related products follow at 15/20.
        add_action( 'woocommerce_after_single_product_summary', [ __CLASS__, 'render_matrix_after_single_product' ], 11 );
    }

    public static function render_matrix_after_single_product() {
        static $rendered = false;
        if ( $rendered ) { return; }
        $rendered = true;
        $product_id = self::$current_single_product_id;
        if ( ! $product_id ) {
            if ( ! empty( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) && method_exists( $GLOBALS['product'], 'get_id' ) ) {
                $product_id = $GLOBALS['product']->get_id();
            } elseif ( function_exists( 'is_product' ) && is_product() && function_exists( 'get_queried_object_id' ) && get_queried_object_id() ) {
                $product_id = get_queried_object_id();
            } else {
                $product_id = get_the_ID();
            }
        }
        echo self::render_matrix_container( [ 'product_id' => $product_id ] );
    }
}
B2B_Matrix_API::init();
