<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Resumable preorder snapshots. No cart, payment, tax, stock or checkout calls. */
class B2B_Preorder_Submission {
    const PROCESSING_EMAIL_ACTION = 'woocommerce_order_status_b2b-draft_to_processing';

    public static function email_actions( $actions ) {
        $actions[] = self::PROCESSING_EMAIL_ACTION;
        return array_values( array_unique( $actions ) );
    }
    // Keep WooCommerce's templates, settings, recipients and deferred email queue.
    public static function processing_emails( $emails ) {
        foreach ( [ 'WC_Email_New_Order', 'WC_Email_Customer_Processing_Order' ] as $class ) {
            if ( isset( $emails[ $class ] ) && is_callable( [ $emails[ $class ], 'trigger' ] ) ) {
                add_action( self::PROCESSING_EMAIL_ACTION . '_notification', [ $emails[ $class ], 'trigger' ], 10, 2 );
            }
        }
        return $emails;
    }
    private static function is_preorder( $order ) {
        return $order instanceof WC_Order && $order->get_created_via() === 'b2b-preorder' && $order->get_meta( '_b2b_batch_token' );
    }
    public static function reduce_order_stock( $reduce, $order_id ) {
        return self::is_preorder( wc_get_order( $order_id ) ) ? false : $reduce;
    }
    public static function can_reduce_order_stock( $reduce, $order ) {
        return self::is_preorder( $order ) ? false : $reduce;
    }
    public static function quote_version( $items ) {
        $rows = array_map( static fn( $item ) => [ (int) $item['product_id'], (int) $item['variation_id'], (int) $item['qty'], (string) $item['price'] ], $items );
        usort( $rows, static fn( $a, $b ) => $a[1] <=> $b[1] );
        return hash( 'sha256', wp_json_encode( $rows ) );
    }
    /** Independent parent quotes let a matrix save replace only its own confirmation. */
    public static function parent_quotes( $items ) {
        $parents = [];
        foreach ( $items as $item ) { $parents[ (int) $item['product_id'] ][] = $item; }
        foreach ( $parents as &$rows ) { $rows = self::quote_version( $rows ); }
        unset( $rows );
        return $parents;
    }
    public static function matches_quote( $items, $expected ) {
        // AJAX sends the manifest as JSON; legacy callers may still send one digest.
        if ( is_string( $expected ) && substr( $expected, 0, 1 ) === '{' ) { $expected = json_decode( $expected, true ); }
        if ( is_string( $expected ) ) { return preg_match( '/^[a-f0-9]{64}$/D', $expected ) && hash_equals( self::quote_version( $items ), $expected ); }
        if ( ! is_array( $expected ) ) { return false; }
        $actual = self::parent_quotes( $items );
        if ( count( $expected ) !== count( $actual ) ) { return false; }
        foreach ( $actual as $pid => $digest ) {
            if ( ! is_string( $expected[ $pid ] ?? null ) || ! hash_equals( $digest, $expected[ $pid ] ) ) { return false; }
        }
        return true;
    }
    public static function register_statuses() {
        foreach ( self::order_statuses( [] ) as $status => $label ) {
            register_post_status( $status, [ 'label' => $label, 'public' => true, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( $label . ' (%s)', $label . ' (%s)' ) ] );
        }
    }
    public static function order_statuses( $statuses ) {
        $statuses['wc-b2b-draft'] = 'Προπαραγγελία σε καταχώριση';
        $statuses['wc-b2b-submitted'] = 'Υποβληθείσα προπαραγγελία';
        $statuses['wc-b2b-void'] = 'Ακυρωμένη καταχώριση';
        return $statuses;
    }
    private static function error( $message, $code = 'submission_error', $status = 409 ) {
        return new WP_Error( $code, $message, [ 'status' => $status ] );
    }
    private static function authorized( $uid ) {
        if ( ! is_user_logged_in() || (int) $uid !== get_current_user_id() ) { return self::error( 'Συνδεθείτε στον λογαριασμό σας.', 'forbidden', 403 ); }
        return B2B_Matrix_API::check_read_permissions();
    }
    public static function start( $uid, $note = '', $expected_quote = null, $live = null ) {
        $allowed = self::authorized( $uid );
        if ( is_wp_error( $allowed ) ) { return $allowed; }
        return B2B_Matrix_DB::with_user_lock( $uid, function() use ( $uid, $note, $expected_quote, $live ) {
            $batch = B2B_Matrix_DB::get_active_batch( $uid );
            if ( $batch && ! empty( $batch['wc_order_id'] ) ) { return self::progress( $batch ); }
            if ( ! $batch ) {
                $items = B2B_Matrix_DB::get_user_all_items( $uid );
                if ( ! $items ) { return self::error( 'Η λίστα σας είναι άδεια.', 'empty_list', 400 ); }
                $cancelled = function_exists( 'jpd_get_canceled_items_bulk' ) ? (array) jpd_get_canceled_items_bulk( array_column( $items, 'variation_id' ) ) : [];
                $parents = [];
                foreach ( $items as &$item ) {
                    $pid = (int) $item['product_id'];
                    if ( ! isset( $parents[ $pid ] ) ) { $parents[ $pid ] = B2B_Matrix_API::get_product( $pid ); }
                    $product = wc_get_product( (int) $item['variation_id'] );
                    if ( is_wp_error( $parents[ $pid ] ) || ! $product || ! $product->is_type( 'variation' ) || $product->get_status( 'edit' ) !== 'publish' || (int) $product->get_parent_id() !== $pid || in_array( (int) $item['variation_id'], array_map( 'intval', $cancelled ), true ) ) {
                        return self::error( 'Η λίστα περιέχει μη διαθέσιμα προϊόντα. Ελέγξτε και αφαιρέστε τα πριν την υποβολή.', 'unavailable_items' );
                    }
                    $price = $product->get_price();
                    if ( ! is_numeric( $price ) || (float) $price < 0 || (int) $item['qty'] < 1 || (int) $item['qty'] > B2B_Matrix_API::MAX_QTY ) { return self::error( 'Ελέγξτε τις ποσότητες και τις τιμές της λίστας.', 'invalid_items' ); }
                    $item['price'] = (string) $price;
                }
                unset( $item );
                if ( $expected_quote !== null && ! self::matches_quote( $items, $expected_quote ) ) {
                    return self::error( 'Η λίστα ή οι τιμές άλλαξαν. Ελέγξτε το νέο γενικό σύνολο και υποβάλετε ξανά.', 'list_changed' );
                }
                $token = B2B_Matrix_DB::create_batch( $uid, count( $items ), [ 'items' => $items, 'note' => sanitize_textarea_field( $note ), 'origin' => B2B_Order_Channel::capture( $live ) ] );
                if ( is_wp_error( $token ) ) { return $token; }
                $batch = B2B_Matrix_DB::get_batch( $token );
            }
            $snapshot = json_decode( $batch['snapshot'] ?? '', true );
            if ( empty( $snapshot['items'] ) ) { return self::error( 'Η παλιά καταχώριση δεν υποστηρίζει ασφαλή συνέχιση. Ακυρώστε την και υποβάλετε ξανά τη λίστα.', 'legacy_batch' ); }
            // Recover a shell saved before a process interruption, without creating duplicates.
            $existing = wc_get_orders( [ 'limit' => 1, 'customer_id' => $uid, 'meta_key' => '_b2b_batch_token', 'meta_value' => $batch['batch_token'] ] );
            $order = $existing ? $existing[0] : new WC_Order();
            if ( ! $existing ) {
                $order->set_customer_id( $uid );
                $order->set_created_via( 'b2b-preorder' );
                $order->set_status( 'b2b-draft' );
                $order->set_currency( get_woocommerce_currency() );
                $order->set_prices_include_tax( false );
                $order->set_customer_note( $snapshot['note'] ?? '' );
                $address = [];
                foreach ( [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' ] as $field ) { $address[ $field ] = get_user_meta( $uid, 'billing_' . $field, true ); }
                $order->set_address( $address, 'billing' );
                $order->update_meta_data( '_b2b_batch_token', $batch['batch_token'] );
                $order->update_meta_data( '_b2b_indicative_totals', 'yes' );
                B2B_Order_Channel::apply_snapshot( $order, $snapshot );
                if ( function_exists( 'jpd_get_active_season' ) ) { $order->update_meta_data( '_jpd_season', jpd_get_active_season() ); }
                if ( ! $order->save() ) { return B2B_Matrix_DB::db_error(); }
            }
            if ( ! B2B_Matrix_DB::update_batch( $batch['batch_token'], [ 'wc_order_id' => $order->get_id(), 'status' => 'building' ] ) ) { return B2B_Matrix_DB::db_error(); }
            return self::progress( B2B_Matrix_DB::get_batch( $batch['batch_token'] ) );
        } );
    }
    private static function progress( $batch ) {
        $complete = $batch['status'] === 'complete';
        $snapshot = json_decode( $batch['snapshot'] ?? '', true );
        $order = ! empty( $batch['wc_order_id'] ) ? wc_get_order( $batch['wc_order_id'] ) : null;
        return [
            'order_id' => (int) ( $batch['wc_order_id'] ?? 0 ), 'token' => $batch['batch_token'],
            'total' => (int) $batch['total_items'], 'processed' => (int) $batch['processed_items'],
            'status' => $batch['status'], 'complete' => $complete,
            'order_channel' => $snapshot['origin']['channel'] ?? 'unknown',
            'percent' => $batch['total_items'] ? (int) floor( 100 * $batch['processed_items'] / $batch['total_items'] ) : 0,
            'redirect' => $complete && $order ? wc_get_account_endpoint_url( 'orders' ) : '',
        ];
    }
    /** Read only the variations in the current chunk for safe retries. WooCommerce still writes every item. */
    private static function existing_batch_variations( $order_id, $rows ) {
        global $wpdb;
        if ( ! $rows ) { return []; }
        $ids = array_values( array_unique( array_map( static fn( $row ) => (int) $row['variation_id'], $rows ) ) );
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%s' ) );
        $sql = $wpdb->prepare(
            "SELECT DISTINCT oi.order_item_id, vim.meta_value AS variation_id
             FROM {$wpdb->prefix}woocommerce_order_items oi
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta vim ON vim.order_item_id = oi.order_item_id
             WHERE oi.order_id = %d AND oi.order_item_type = 'line_item'
               AND vim.meta_key = '_variation_id' AND vim.meta_value IN ({$placeholders})",
            array_merge( [ (int) $order_id ], array_map( 'strval', $ids ) )
        );
        $found = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $found ) || $wpdb->last_error ) { return B2B_Matrix_DB::db_error(); }
        $existing = [];
        foreach ( $found as $row ) {
            $vid = (int) $row['variation_id'];
            if ( isset( $existing[ $vid ] ) ) { return self::error( 'Βρέθηκε διπλή γραμμή παραγγελίας. Ακυρώστε την καταχώριση.' ); }
            $existing[ $vid ] = true;
        }
        return $existing;
    }
    public static function run( $action, $uid, $oid, $token ) {
        $allowed = self::authorized( $uid );
        if ( is_wp_error( $allowed ) ) { return $allowed; }
        return B2B_Matrix_DB::with_user_lock( $uid, function() use ( $action, $uid, $oid, $token ) {
            $batch = B2B_Matrix_DB::get_batch( $token );
            if ( ! $batch || (int) $batch['user_id'] !== (int) $uid || (int) ( $batch['wc_order_id'] ?? 0 ) !== (int) $oid ) { return self::error( 'Μη έγκυρη καταχώριση.', 'invalid_batch', 403 ); }
            // A replay must never touch a subsequently created draft list.
            if ( $batch['status'] === 'complete' || $action === 'order-status' ) { return self::progress( $batch ); }
            if ( ! in_array( $batch['status'], [ 'pending', 'building', 'ready_to_finalize' ], true ) ) { return self::error( 'Αυτή η καταχώριση έχει κλείσει.' ); }
            $order = $oid ? wc_get_order( $oid ) : null;
            if ( $oid && ( ! $order || (int) $order->get_customer_id() !== (int) $uid || $order->get_meta( '_b2b_batch_token' ) !== $token ) ) { return self::error( 'Η παραγγελία δεν αντιστοιχεί στην καταχώριση.', 'invalid_order', 403 ); }
            if ( $action === 'cancel-order' ) {
                if ( $order && ! in_array( $order->get_status(), [ 'b2b-draft', 'pending' ], true ) ) { return self::error( 'Η υποβολή ολοκληρώθηκε. Πατήστε Συνέχιση για ενημέρωση της λίστας.' ); }
                if ( $order ) { $order->set_status( 'b2b-void' ); $order->save(); }
                if ( ! B2B_Matrix_DB::update_batch( $token, [ 'status' => 'cancelled' ] ) ) { return B2B_Matrix_DB::db_error(); }
                return self::progress( B2B_Matrix_DB::get_batch( $token ) );
            }
            if ( ! $order ) { return self::error( 'Η καταχώριση δεν έχει ξεκινήσει. Δοκιμάστε ξανά.' ); }
            if ( ! in_array( $order->get_status(), [ 'b2b-draft', 'processing', 'b2b-submitted' ], true ) ) { return self::error( 'Η κατάσταση της παραγγελίας άλλαξε. Επικοινωνήστε με το κατάστημα.' ); }
            $snapshot = json_decode( $batch['snapshot'] ?? '', true );
            $items = $snapshot['items'] ?? [];
            if ( ! $items || count( $items ) !== (int) $batch['total_items'] ) { return self::error( 'Ακυρώστε την παλιά καταχώριση και ξεκινήστε νέα υποβολή.', 'legacy_batch' ); }
            if ( $action === 'add-batch' ) {
                if ( $order->get_status() !== 'b2b-draft' ) { return self::error( 'Η καταχώριση έχει ήδη υποβληθεί.' ); }
                $offset = (int) $batch['processed_items'];
                $chunk = array_slice( $items, $offset, B2B_Preorder_List::BATCH_SIZE );
                $existing = self::existing_batch_variations( $oid, $chunk );
                if ( is_wp_error( $existing ) ) { return $existing; }
                foreach ( $chunk as $row ) {
                    $vid = (int) $row['variation_id'];
                    if ( isset( $existing[ $vid ] ) ) { continue; }
                    $product = wc_get_product( $vid );
                    $parent = B2B_Matrix_API::get_product( (int) $row['product_id'] );
                    if ( is_wp_error( $parent ) || ! $product || ! $product->is_type( 'variation' ) || $product->get_status( 'edit' ) !== 'publish' || (int) $product->get_parent_id() !== (int) $row['product_id'] ) { return self::error( 'Ένα προϊόν άλλαξε. Ακυρώστε την καταχώριση και ελέγξτε τη λίστα.' ); }
                    $item = new WC_Order_Item_Product();
                    $item->set_product( $product );
                    $item->set_quantity( (int) $row['qty'] );
                    $line_total = wc_format_decimal( (float) $row['price'] * (int) $row['qty'], wc_get_price_decimals() );
                    $item->set_subtotal( $line_total ); $item->set_total( $line_total );
                    $item->set_taxes( [ 'total' => [], 'subtotal' => [] ] );
                    $item->set_order_id( $oid );
                    if ( ! $item->save() ) { return B2B_Matrix_DB::db_error(); }
                }
                $processed = min( count( $items ), $offset + B2B_Preorder_List::BATCH_SIZE );
                if ( ! B2B_Matrix_DB::update_batch( $token, [ 'processed_items' => $processed, 'status' => $processed === count( $items ) ? 'ready_to_finalize' : 'building' ] ) ) { return B2B_Matrix_DB::db_error(); }
                return self::progress( B2B_Matrix_DB::get_batch( $token ) );
            }
            // Finalization still validates every WooCommerce line against the frozen snapshot.
            $existing = [];
            foreach ( $order->get_items() as $item ) {
                $vid = (int) $item->get_variation_id();
                if ( isset( $existing[ $vid ] ) ) { return self::error( 'Βρέθηκε διπλή γραμμή παραγγελίας. Ακυρώστε την καταχώριση.' ); }
                $existing[ $vid ] = $item;
            }
            if ( $action !== 'finalize-order' || $batch['status'] !== 'ready_to_finalize' || (int) $batch['processed_items'] !== count( $items ) || count( $existing ) !== count( $items ) ) { return self::error( 'Δεν έχουν καταχωριστεί όλες οι γραμμές.', 'incomplete_batch' ); }
            $total = 0;
            foreach ( $items as $row ) {
                $item = $existing[ (int) $row['variation_id'] ] ?? null;
                if ( ! $item || (int) $item->get_quantity() !== (int) $row['qty'] ) { return self::error( 'Η παραγγελία δεν αντιστοιχεί στη λίστα.', 'snapshot_mismatch' ); }
                $line = (float) wc_format_decimal( (float) $row['price'] * (int) $row['qty'], wc_get_price_decimals() );
                if ( abs( (float) $item->get_total() - $line ) > 0.00001 ) { return self::error( 'Η αξία μιας γραμμής άλλαξε.', 'snapshot_mismatch' ); }
                $total += $line;
            }
            // A retry after saving the order must not repeat the status/email transition.
            // Old already-submitted batches keep their existing order status on recovery.
            if ( ! in_array( $order->get_status(), [ 'processing', 'b2b-submitted' ], true ) ) {
                $order->set_total( $total );
                $order->set_status( 'processing' );
                if ( ! $order->save() ) { return B2B_Matrix_DB::db_error(); }
            }
            $done = B2B_Matrix_DB::complete_batch( $uid, $token );
            if ( is_wp_error( $done ) ) { return $done; }
            return self::progress( B2B_Matrix_DB::get_batch( $token ) );
        } );
    }
}
