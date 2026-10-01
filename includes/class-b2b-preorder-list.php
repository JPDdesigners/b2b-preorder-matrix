<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-b2b-preorder-submission.php';

class B2B_Preorder_List {
    const BATCH_SIZE = 150;
    const ACTIONS = [
        'b2b_get_preorder_list_data' => 'list-data', 'b2b_search_products' => 'search-products',
        'b2b_empty_custom_list' => 'empty', 'b2b_empty_preorder_list' => 'empty',
        'b2b_remove_parent_custom' => 'remove-parent', 'b2b_remove_parent_preorder' => 'remove-parent',
        'b2b_get_matrix_for_edit' => 'matrix-for-edit', 'b2b_update_custom_list' => 'update-matrix',
        'b2b_start_order' => 'start-order', 'b2b_add_order_batch' => 'add-batch',
        'b2b_finalize_order' => 'finalize-order', 'b2b_order_process_status' => 'order-status',
        'b2b_cancel_order' => 'cancel-order',
    ];
    public static function init() {
        add_shortcode( 'b2b_preorder_list', [ __CLASS__, 'render_shortcode' ] );
        add_shortcode( 'b2b_custom_order_list', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
        add_action( 'init', [ 'B2B_Preorder_Submission', 'register_statuses' ] );
        add_filter( 'wc_order_statuses', [ 'B2B_Preorder_Submission', 'order_statuses' ] );
        foreach ( self::ACTIONS as $action => $endpoint ) {
            add_action( 'wp_ajax_' . $action, function() use ( $endpoint ) { self::ajax_dispatch( $endpoint ); } );
            add_action( 'wp_ajax_nopriv_' . $action, [ 'B2B_Matrix_API', 'ajax_unauthorized' ] );
        }
    }
    public static function check_permission( $request = null ) {
        if ( ! is_user_logged_in() ) { return new WP_Error( 'login_required', 'Συνδεθείτε για να δείτε τη λίστα σας.', [ 'status' => 401 ] ); }
        return $request ? B2B_Matrix_API::check_write_permissions( $request ) : B2B_Matrix_API::check_read_permissions();
    }
    public static function register_rest_routes() {
        foreach ( array_unique( self::ACTIONS ) as $endpoint ) {
            register_rest_route( 'b2b/v1', '/preorder/' . $endpoint, [
                'methods' => in_array( $endpoint, [ 'list-data', 'search-products', 'matrix-for-edit' ], true ) ? 'GET' : 'POST',
                'permission_callback' => [ __CLASS__, 'check_permission' ],
                'callback' => function( $request ) use ( $endpoint ) { return rest_ensure_response( self::dispatch( $endpoint, $request->get_params() ) ); },
            ] );
        }
    }
    public static function ajax_dispatch( $endpoint ) {
        B2B_Matrix_API::send_nocache_headers();
        $permission = self::check_permission();
        if ( is_wp_error( $permission ) ) { self::send_error( $permission ); }
        $read = in_array( $endpoint, [ 'list-data', 'search-products', 'matrix-for-edit' ], true );
        if ( ! $read && ! check_ajax_referer( 'wp_rest', 'security', false ) ) {
            self::send_error( new WP_Error( 'invalid_nonce', 'Η σύνδεση έληξε. Ανανεώστε τη λίστα.', [ 'status' => 403 ] ) );
        }
        $params = wp_unslash( $read ? $_GET : $_POST );
        $result = self::dispatch( $endpoint, $params );
        if ( is_wp_error( $result ) ) { self::send_error( $result ); }
        wp_send_json_success( $result['data'] );
    }
    private static function send_error( $error ) {
        wp_send_json_error( [ 'code' => $error->get_error_code(), 'message' => $error->get_error_message() ], $error->get_error_data()['status'] ?? 500 );
    }
    public static function dispatch( $endpoint, $params ) {
        $permission = self::check_permission();
        if ( is_wp_error( $permission ) ) { return $permission; }
        $uid = get_current_user_id();
        $read = in_array( $endpoint, [ 'list-data', 'search-products', 'matrix-for-edit' ], true );
        if ( ! $read && (int) ( $params['expected_user_id'] ?? 0 ) !== $uid ) {
            return new WP_Error( 'account_changed', 'Ο λογαριασμός άλλαξε. Ανανεώστε τη λίστα πριν συνεχίσετε.', [ 'status' => 409 ] );
        }
        try {
            switch ( $endpoint ) {
                case 'list-data': $data = self::get_list_payload( $uid ); break;
                case 'search-products':
                    $q = is_string( $params['q'] ?? null ) ? sanitize_text_field( substr( $params['q'], 0, 120 ) ) : '';
                    $data = strlen( $q ) >= 2 ? self::search_products_catalog( $q, ! empty( $params['sku_only'] ), $uid ) : []; break;
                case 'empty': $data = B2B_Matrix_DB::clear_user_items( $uid ); break;
                case 'remove-parent':
                    $id = $params['parent_id'] ?? '';
                    if ( ! is_scalar( $id ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) { return new WP_Error( 'invalid_product', 'Μη έγκυρο προϊόν.', [ 'status' => 400 ] ); }
                    $data = B2B_Matrix_DB::delete_user_product( $uid, (int) $id ); break;
                case 'matrix-for-edit': $data = B2B_Matrix_API::build_matrix_payload( $params['parent_id'] ?? null, $uid ); break;
                case 'update-matrix':
                    $items = $params['items'] ?? null;
                    if ( is_string( $items ) ) { $items = json_decode( $items, true ); }
                    $data = B2B_Matrix_API::save_user_product_quantities( $uid, $params['parent_id'] ?? null, $items ); break;
                case 'start-order': $data = self::execute_start_order( $uid, is_string( $params['customer_note'] ?? null ) ? substr( $params['customer_note'], 0, 4000 ) : '', $params['quote_version'] ?? null ); break;
                default:
                    if ( ! in_array( $endpoint, [ 'add-batch', 'finalize-order', 'order-status', 'cancel-order' ], true ) ) { return new WP_Error( 'invalid_action', 'Μη έγκυρη ενέργεια.', [ 'status' => 400 ] ); }
                    $token = $params['token'] ?? $params['process_token'] ?? '';
                    $oid = $params['order_id'] ?? 0;
                    if ( ! is_string( $token ) || ! is_scalar( $oid ) ) { return new WP_Error( 'invalid_request', 'Μη έγκυρο αίτημα.', [ 'status' => 400 ] ); }
                    $data = B2B_Preorder_Submission::run( $endpoint, $uid, (int) $oid, $token );
            }
            return is_wp_error( $data ) ? $data : [ 'success' => true, 'data' => $data ];
        } catch ( Throwable $e ) { return B2B_Matrix_DB::db_error(); }
    }
    public static function execute_start_order( $uid, $note = '', $expected_quote = null ) { return B2B_Preorder_Submission::start( $uid, $note, $expected_quote ); }
    public static function execute_add_batch( $uid, $oid, $token ) { return B2B_Preorder_Submission::run( 'add-batch', $uid, $oid, $token ); }
    public static function execute_finalize_order( $uid, $oid, $token ) { return B2B_Preorder_Submission::run( 'finalize-order', $uid, $oid, $token ); }
    public static function rest_remove_parent( $request ) { return self::dispatch( 'remove-parent', [ 'parent_id' => $request->get_param( 'parent_id' ), 'expected_user_id' => get_current_user_id() ] ); }
    public static function render_shortcode( $atts ) {
        B2B_Matrix_API::enqueue_assets( true );
        $config = [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'restBase' => rest_url( 'b2b/v1' ), 'loginUrl' => wc_get_page_permalink( 'myaccount' ) ];
        return '<div class="b2b-order-list-container" data-b2b-config="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
    }
    public static function normalize_brand_key( $value ) {
        $slug = function_exists( 'sanitize_title' ) ? sanitize_title( (string) $value ) : strtolower( trim( (string) $value ) );
        $aliases = [
            'joyce'       => 'joyce',
            'joyce-kids'  => 'joyce',
            'paco'        => 'paco',
            'paco-co'     => 'paco',
            'paco-and-co' => 'paco',
            'dreams'      => 'dreams',
        ];

        if ( isset( $aliases[ $slug ] ) ) {
            return $aliases[ $slug ];
        }

        foreach ( [ 'joyce', 'paco', 'dreams' ] as $known_brand ) {
            if ( strpos( $slug, $known_brand ) === 0 ) {
                return $known_brand;
            }
        }

        return $slug;
    }

    /**
     * Builds full payload for the list page: products grouped by parent,
     * brand summaries, category facets, canceled items alert, and customer info.
     *
     * @param int $user_id
     * @param int $saved_parent_id Parent for an acknowledgement patch; zero for a full load.
     * @param array|null $saved_quantities Canonical map returned by Save; null for a full load.
     * @return array
     */
    public static function get_list_payload( $user_id, $saved_parent_id = 0, $saved_quantities = null ) {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return [
                'items'        => [],
                'brandSummary' => [],
                'removedItems' => [],
                'customerInfo' => [ 'afm' => '', 'company' => '', 'name' => '', 'email' => '' ],
                'season'       => '',
                'totalQty'     => 0,
                'totalVal'     => 0.0,
                'quoteVersion' => '',
                'activeBatch'  => null,
            ];
        }

        // Save already returned the canonical quantities. Never reread the full list for a patch.
        $raw_items = $saved_quantities === null ? B2B_Matrix_DB::get_user_all_items( $user_id ) : [];
        if ( $saved_quantities !== null ) {
            foreach ( $saved_quantities as $vid => $qty ) {
                if ( $qty > 0 ) { $raw_items[] = [ 'product_id' => $saved_parent_id, 'variation_id' => (int) $vid, 'qty' => (int) $qty ]; }
            }
        }

        $removed_items_alert = [];
        $valid_items = $raw_items;
        $cancelled = function_exists( 'jpd_get_canceled_items_bulk' ) ? (array) jpd_get_canceled_items_bulk( array_column( $raw_items, 'variation_id' ) ) : [];
        $cancelled = array_fill_keys( array_map( 'intval', $cancelled ), true );
        // 2. Aggregate valid items by parent product
        $parents_data = [];
        $quote_items = [];
        $quote_valid = true;
        $brand_taxonomies = [ 'product_brand', '_product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand', 'brand' ];

        $parent_cache = [];
        foreach ( $valid_items as $item ) {
            $parent_id = (int) $item['product_id'];
            $vid       = (int) $item['variation_id'];
            $qty       = (int) $item['qty'];

            if ( ! isset( $parent_cache[ $parent_id ] ) ) { $parent_cache[ $parent_id ] = B2B_Matrix_API::get_product( $parent_id ); }
            $parent = $parent_cache[ $parent_id ];
            $var_obj = wc_get_product( $vid );
            $available = ! is_wp_error( $parent ) && $var_obj && $var_obj->is_type( 'variation' ) && $var_obj->get_status( 'edit' ) === 'publish' && (int) $var_obj->get_parent_id() === $parent_id && ! isset( $cancelled[ $vid ] );
            $price = $available ? $var_obj->get_price() : '';
            $variation_price = is_numeric( $price ) && (float) $price >= 0 ? (float) $price : null;
            if ( $available && $variation_price !== null ) { $quote_items[] = [ 'product_id' => $parent_id, 'variation_id' => $vid, 'qty' => $qty, 'price' => (string) $price ]; }
            else { $quote_valid = false; }
            if ( ! isset( $parents_data[ $parent_id ] ) ) {
                $parent_obj = is_wp_error( $parent ) ? null : $parent;

                $brand_slug  = '';
                $brand_name  = '';
                $brand_image = '';

                foreach ( $parent_obj ? $brand_taxonomies : [] as $tax ) {
                    if ( ! taxonomy_exists( $tax ) ) { continue; }
                    $terms = wc_get_product_terms( $parent_id, $tax, [ 'fields' => 'all' ] );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        $brand_name   = $terms[0]->name;
                        $brand_slug   = $terms[0]->slug;
                        $thumbnail_id = get_term_meta( $terms[0]->term_id, 'thumbnail_id', true )
                                        ?: get_term_meta( $terms[0]->term_id, 'pwb_brand_image', true );
                        if ( $thumbnail_id ) {
                            $brand_image = wp_get_attachment_image_url( $thumbnail_id, 'full' ) ?: '';
                        }
                        break;
                    }
                }

                $parents_data[ $parent_id ] = [
                    'id'                => $parent_id,
                    'name'              => $parent_obj ? $parent_obj->get_name() : ( 'Προϊόν #' . $parent_id ),
                    'sku'               => $parent_obj ? $parent_obj->get_sku() : '',
                    'image'             => ( $parent_obj && $parent_obj->get_image_id() )
                                           ? ( wp_get_attachment_image_url( $parent_obj->get_image_id(), 'thumbnail' ) ?: '' )
                                           : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' ),
                    'price'             => $parent_obj ? (float) $parent_obj->get_price() : $variation_price,
                    'brand_slug'        => $brand_slug,
                    'brand_filter_slug' => self::normalize_brand_key( $brand_slug ?: $brand_name ),
                    'brand_name'        => $brand_name,
                    'brand_image'       => $brand_image,
                    'root_filters'      => [],
                    'category_filters'  => [],
                    'unavailable'       => false,
                    'unpriced'          => false,
                    'total_qty'         => 0,
                    'total_val'         => 0.0,
                ];
            }

            $parents_data[ $parent_id ]['unavailable'] = $parents_data[ $parent_id ]['unavailable'] || ! $available;
            $parents_data[ $parent_id ]['unpriced'] = $parents_data[ $parent_id ]['unpriced'] || $variation_price === null;
            $parents_data[ $parent_id ]['total_qty'] += $qty;
            if ( $variation_price !== null ) { $parents_data[ $parent_id ]['total_val'] += (float) wc_format_decimal( $qty * $variation_price, wc_get_price_decimals() ); }
        }

        // 3. Category Facets per Parent Product
        if ( ! empty( $parents_data ) ) {
            $terms_by_parent = [];
            $category_terms  = wp_get_object_terms(
                array_keys( $parents_data ),
                'product_cat',
                [ 'fields' => 'all_with_object_id' ]
            );

            if ( ! is_wp_error( $category_terms ) ) {
                foreach ( $category_terms as $term ) {
                    $object_id = (int) ( $term->object_id ?? 0 );
                    if ( $object_id ) {
                        $terms_by_parent[ $object_id ][] = $term;
                    }
                }
            }

            $category_path_cache = [];

            foreach ( $parents_data as $parent_id => &$pdata ) {
                if ( is_wp_error( $parent_cache[ $parent_id ] ) ) { continue; }
                $facets = [];
                $roots = [];

                foreach ( $terms_by_parent[ $parent_id ] ?? [] as $assigned_term ) {
                    $assigned_term_id = (int) $assigned_term->term_id;

                    if ( ! array_key_exists( $assigned_term_id, $category_path_cache ) ) {
                        $path_ids = array_reverse( get_ancestors( $assigned_term_id, 'product_cat', 'taxonomy' ) );
                        $path_ids[] = $assigned_term_id;

                        $root_term = get_term( $path_ids[0], 'product_cat' );
                        $level_one_term = count( $path_ids ) > 1 ? get_term( $path_ids[1], 'product_cat' ) : null;
                        $category_path_cache[ $assigned_term_id ] = is_wp_error( $root_term ) || ! $root_term || is_wp_error( $level_one_term ) ? false : [
                            'root' => $root_term,
                            'term' => $level_one_term,
                        ];
                    }

                    $category_path = $category_path_cache[ $assigned_term_id ];
                    if ( ! $category_path ) { continue; }

                    $root_term = $category_path['root'];
                    $root_brand_key = self::normalize_brand_key( $root_term->slug ?: $root_term->name );
                    $level_one_term = $category_path['term'];
                    $roots[ (int) $root_term->term_id ] = [
                        'id' => (int) $root_term->term_id,
                        'name' => $root_term->name,
                        'slug' => $root_term->slug,
                    ];
                    if ( ! $level_one_term ) { continue; }

                    $facets[ (int) $level_one_term->term_id ] = [
                        'id'         => (int) $level_one_term->term_id,
                        'slug'       => $level_one_term->slug,
                        'name'       => $level_one_term->name,
                        'root_id'    => (int) $root_term->term_id,
                        'root_name'  => $root_term->name,
                        'brand_slug' => $root_brand_key,
                    ];
                }

                uasort( $facets, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
                uasort( $roots, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
                $pdata['root_filters'] = array_values( $roots );
                $pdata['category_filters'] = array_values( $facets );

                if ( ! $pdata['brand_filter_slug'] && ! empty( $pdata['category_filters'] ) ) {
                    $pdata['brand_filter_slug'] = $pdata['category_filters'][0]['brand_slug'];
                }
            }
            unset( $pdata );
        }

        // Sort items naturally by SKU
        uasort( $parents_data, static fn( $a, $b ) => strnatcasecmp( $a['sku'], $b['sku'] ) );
        $items_list = array_values( $parents_data );

        if ( $saved_quantities !== null ) {
            return [ 'parentId' => (int) $saved_parent_id, 'item' => $items_list[0] ?? null, 'quoteVersion' => $quote_valid ? B2B_Preorder_Submission::quote_version( $quote_items ) : '' ];
        }

        // 4. Brand Summary
        $brand_map = [];

        $grand_qty = 0;
        $grand_val = 0.0;

        foreach ( $items_list as $item ) {
            $grand_qty += $item['total_qty'];
            $grand_val += $item['total_val'];

            $slug = self::normalize_brand_key( $item['brand_filter_slug'] ?: $item['brand_slug'] );
            if ( ! $slug ) { continue; }

            if ( ! isset( $brand_map[ $slug ] ) ) {
                $brand_map[ $slug ] = [
                    'slug'    => $slug,
                    'name'    => $item['brand_name'] ?: ucfirst( $slug ),
                    'image'   => $item['brand_image'] ?: '',
                    'qty'     => 0,
                    'val'     => 0.0,
                    'parents' => 0,
                ];
            } elseif ( empty( $brand_map[ $slug ]['image'] ) && ! empty( $item['brand_image'] ) ) {
                $brand_map[ $slug ]['image'] = $item['brand_image'];
            }

            $brand_map[ $slug ]['qty']     += $item['total_qty'];
            $brand_map[ $slug ]['val']     += $item['total_val'];
            $brand_map[ $slug ]['parents'] += 1;
        }

        $brand_summary = array_values( $brand_map );

        // 5. Customer info
        $afm = get_user_meta( $user_id, 'billing_first_name', true ) ?: ( get_user_meta( $user_id, 'billing_vat', true ) ?: get_user_meta( $user_id, 'billing_afm', true ) );
        $company = get_user_meta( $user_id, 'billing_last_name', true ) ?: get_user_meta( $user_id, 'billing_company', true );
        $user_obj   = get_userdata( $user_id );

        $customer_info = [
            'afm'     => (string) $afm,
            'company' => (string) $company,
            'name'    => (string) ( $company ?: ( $user_obj ? $user_obj->display_name : '' ) ),
            'email'   => (string) get_user_meta( $user_id, 'billing_email', true ),
        ];

        // 6. Season
        $season = function_exists( 'jpd_get_active_season' ) ? jpd_get_active_season() : '';

        // 7. Active batch
        $active_batch = B2B_Matrix_DB::get_active_batch( $user_id );

        if ( $active_batch ) { unset( $active_batch['snapshot'], $active_batch['error_message'] ); }
        return [
            'user_id' => $user_id, 'nonce' => wp_create_nonce( 'wp_rest' ),
            'appearance' => B2B_Matrix_Settings::get(),
            'currency' => get_woocommerce_currency(), 'price_decimals' => wc_get_price_decimals(),
            'items'        => $items_list,
            'brandSummary' => $brand_summary,
            'removedItems' => array_values( $removed_items_alert ),
            'customerInfo' => $customer_info,
            'season'       => $season,
            'totalQty'     => $grand_qty,
            'totalVal'     => round( $grand_val, wc_get_price_decimals() ),
            'quoteVersion' => $quote_valid ? B2B_Preorder_Submission::quote_version( $quote_items ) : '',
            'quoteParents' => (object) B2B_Preorder_Submission::parent_quotes( $quote_items ),
            'activeBatch'  => $active_batch,
        ];
    }

    public static function search_products_catalog( $query, $sku_only = false, $user_id = 0 ) {
        global $wpdb;
        $clean_q = trim( $query );
        if ( empty( $clean_q ) ) {
            return [];
        }

        $like = '%' . $wpdb->esc_like( $clean_q ) . '%';
        $matched_parent_ids = [];

        // 1. Direct SKU match on products or variations
        $sku_query = $wpdb->prepare(
            "SELECT DISTINCT p.ID, p.post_parent, p.post_type FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sku')
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('product', 'product_variation')
               AND pm.meta_value LIKE %s
             LIMIT 30",
            $like
        );
        $sku_rows = $wpdb->get_results( $sku_query, ARRAY_A );

        if ( ! empty( $sku_rows ) ) {
            foreach ( $sku_rows as $row ) {
                if ( $row['post_type'] === 'product_variation' && (int) $row['post_parent'] > 0 ) {
                    $matched_parent_ids[] = (int) $row['post_parent'];
                } else {
                    $matched_parent_ids[] = (int) $row['ID'];
                }
            }
        }

        // 2. Title search if not sku_only
        if ( ! $sku_only && count( $matched_parent_ids ) < 20 ) {
            $title_query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type = 'product'
                   AND post_title LIKE %s
                 LIMIT 20",
                $like
            );
            $title_ids = $wpdb->get_col( $title_query );
            if ( ! empty( $title_ids ) ) {
                $matched_parent_ids = array_merge( $matched_parent_ids, array_map( 'intval', $title_ids ) );
            }
        }

        $matched_parent_ids = array_values( array_unique( array_filter( $matched_parent_ids ) ) );
        if ( empty( $matched_parent_ids ) ) {
            return [];
        }

        // 3. User existing items lookup
        $user_existing = [];
        if ( $user_id > 0 ) {
            $table = B2B_Matrix_DB::table_items();
            $pids_in = implode( ',', array_map( 'intval', $matched_parent_ids ) );
            $exist_rows = $wpdb->get_results(
                "SELECT product_id, SUM(qty) as total_qty FROM {$table} WHERE user_id = {$user_id} AND product_id IN ({$pids_in}) AND qty > 0 GROUP BY product_id",
                ARRAY_A
            );
            if ( ! empty( $exist_rows ) ) {
                foreach ( $exist_rows as $er ) {
                    $user_existing[ (int) $er['product_id'] ] = (int) $er['total_qty'];
                }
            }
        }

        // 4. Build return list
        $results = [];
        $brand_taxonomies = [ 'product_brand', '_product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand', 'brand' ];

        foreach ( array_slice( $matched_parent_ids, 0, 15 ) as $pid ) {
            $product = B2B_Matrix_API::get_product( $pid );
            if ( is_wp_error( $product ) ) {
                continue;
            }

            $brand_name = '';
            $brand_slug = '';
            foreach ( $brand_taxonomies as $tax ) {
                if ( ! taxonomy_exists( $tax ) ) { continue; }
                $terms = wc_get_product_terms( $pid, $tax, [ 'fields' => 'all' ] );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $brand_name = $terms[0]->name;
                    $brand_slug = $terms[0]->slug;
                    break;
                }
            }

            $img_id = $product->get_image_id();
            $img_url = $img_id ? ( wp_get_attachment_image_url( $img_id, 'thumbnail' ) ?: '' ) : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' );

            $results[] = [
                'id'          => $pid,
                'name'        => $product->get_name(),
                'sku'         => $product->get_sku(),
                'price'       => (float) $product->get_price(),
                'image'       => $img_url,
                'brand_name'  => $brand_name,
                'brand_slug'  => $brand_slug,
                'in_list_qty' => $user_existing[ $pid ] ?? 0,
            ];
        }

        return $results;
    }

}
