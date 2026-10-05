<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Order origin belongs to the order, independently of its status or storage engine. */
class B2B_Order_Channel {
    const META_CHANNEL = '_jpd_order_channel';
    const META_ACTOR = '_jpd_order_actor_id';
    const FILTER = 'jpd_order_channel';
    const EXPORT_ACTION = 'b2b_export_order_channels';

    public static function init() {
        add_filter( 'manage_edit-shop_order_columns', [ __CLASS__, 'columns' ] );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ __CLASS__, 'columns' ] );
        add_action( 'manage_shop_order_posts_custom_column', [ __CLASS__, 'column' ], 10, 2 );
        add_action( 'woocommerce_shop_order_list_table_custom_column', [ __CLASS__, 'column' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ __CLASS__, 'dropdown' ], 10, 2 );
        add_action( 'woocommerce_order_list_table_restrict_manage_orders', [ __CLASS__, 'dropdown' ], 10, 2 );
        add_action( 'pre_get_posts', [ __CLASS__, 'legacy_query' ] );
        add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', [ __CLASS__, 'hpos_query' ] );
        add_action( 'woocommerce_admin_order_data_after_order_details', [ __CLASS__, 'edit_field' ] );
        // Woo's order data has already been saved at priority 40, on both order screens.
        add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'save_edit' ], 60 );
        foreach ( [ 'edit-shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
            add_filter( 'bulk_actions-' . $screen, [ __CLASS__, 'bulk_actions' ] );
            add_filter( 'handle_bulk_actions-' . $screen, [ __CLASS__, 'bulk_export' ], 10, 3 );
        }
    }

    /** Never trust a role, original user ID or seller ID supplied by the browser. */
    public static function actor_id() {
        if ( ! get_current_user_id() ) { return 0; }
        if ( is_callable( [ 'user_switching', 'get_old_user' ] ) && is_callable( [ 'user_switching', 'authenticate_old_user' ] ) ) {
            $old = user_switching::get_old_user();
            if ( $old instanceof WP_User && user_switching::authenticate_old_user( $old ) && user_can( $old, 'manage_woocommerce' ) ) {
                return (int) $old->ID;
            }
        }
        return current_user_can( 'manage_woocommerce' ) ? get_current_user_id() : 0;
    }

    public static function context() {
        $actor = self::actor_id();
        return [ 'canChoose' => $actor > 0, 'defaultLive' => $actor > 0 && $actor !== get_current_user_id() ];
    }

    /** Captured once in the private, server-side batch snapshot. */
    public static function capture( $live = null ) {
        $actor = self::actor_id();
        $requested = $live === null ? $actor > 0 && $actor !== get_current_user_id() : in_array( $live, [ true, 1, '1', 'true' ], true );
        return [ 'channel' => $actor && $requested ? 'live' : 'online', 'actor_id' => $actor ];
    }

    public static function channel( $order ) {
        $value = $order->get_meta( self::META_CHANNEL );
        return in_array( $value, [ 'live', 'online' ], true ) ? $value : 'unknown';
    }

    public static function labels() {
        return [ 'live' => 'Live δειγματισμός', 'online' => 'Online', 'unknown' => 'Μη καταγεγραμμένη προέλευση' ];
    }

    /** Only apply a new snapshot to a new shell; retries never overwrite corrections. */
    public static function apply_snapshot( $order, $snapshot ) {
        $origin = $snapshot['origin'] ?? null;
        if ( ! is_array( $origin ) || ! in_array( $origin['channel'] ?? null, [ 'live', 'online' ], true ) ) { return; }
        $order->update_meta_data( self::META_CHANNEL, $origin['channel'] );
        if ( ! empty( $origin['actor_id'] ) ) { $order->update_meta_data( self::META_ACTOR, (int) $origin['actor_id'] ); }
    }

    public static function columns( $columns ) {
        if ( current_user_can( 'manage_woocommerce' ) ) { $columns['jpd_order_channel'] = 'Προέλευση'; }
        return $columns;
    }

    public static function actor_name( $order ) {
        $id = (int) $order->get_meta( self::META_ACTOR );
        if ( ! $id ) { return ''; }
        $user = get_userdata( $id );
        return $user ? $user->display_name : 'Χρήστης #' . $id;
    }

    public static function column( $column, $order ) {
        if ( $column !== 'jpd_order_channel' || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        if ( ! $order instanceof WC_Order ) { $order = wc_get_order( $order ); }
        if ( ! $order ) { return; }
        echo esc_html( self::labels()[ self::channel( $order ) ] );
        $actor = self::actor_name( $order );
        if ( $actor ) { echo '<br><small>' . esc_html( $actor ) . '</small>'; }
    }

    private static function requested_filter() {
        $value = $_GET[ self::FILTER ] ?? '';
        return is_string( $value ) && isset( self::labels()[ $value ] ) ? $value : '';
    }

    public static function dropdown( $type, $which = 'top' ) {
        if ( $type !== 'shop_order' || $which !== 'top' || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        echo '<label class="screen-reader-text" for="jpd-order-channel-filter">Προέλευση παραγγελίας</label><select id="jpd-order-channel-filter" name="' . self::FILTER . '"><option value="">Όλες οι προελεύσεις</option>';
        foreach ( self::labels() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( self::requested_filter(), $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select>';
    }

    public static function filter_clause( $channel ) {
        if ( $channel === 'unknown' ) {
            return [ 'relation' => 'OR', [ 'key' => self::META_CHANNEL, 'compare' => 'NOT EXISTS' ], [ 'key' => self::META_CHANNEL, 'value' => [ 'live', 'online' ], 'compare' => 'NOT IN' ] ];
        }
        return [ 'key' => self::META_CHANNEL, 'value' => $channel, 'compare' => '=' ];
    }

    private static function with_filter( $existing, $channel ) {
        // Nest the existing group to preserve other plugins' OR relationships.
        return $existing ? [ 'relation' => 'AND', $existing, self::filter_clause( $channel ) ] : [ self::filter_clause( $channel ) ];
    }

    public static function hpos_query( $args ) {
        $channel = self::requested_filter();
        if ( $channel && current_user_can( 'manage_woocommerce' ) ) { $args['meta_query'] = self::with_filter( $args['meta_query'] ?? [], $channel ); }
        return $args;
    }

    public static function legacy_query( $query ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $channel = self::requested_filter();
        if ( ! is_admin() || ! $screen || $screen->id !== 'edit-shop_order' || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'shop_order' || ! $channel || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $query->set( 'meta_query', self::with_filter( $query->get( 'meta_query' ), $channel ) );
    }

    public static function edit_field( $order ) {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { return; }
        wp_nonce_field( 'b2b_order_channel_' . $order->get_id(), 'b2b_order_channel_nonce' );
        echo '<p class="form-field form-field-wide"><label for="b2b-order-channel">Προέλευση:</label><select id="b2b-order-channel" name="b2b_order_channel">';
        foreach ( self::labels() as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( self::channel( $order ), $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></p>';
        $actor = self::actor_name( $order );
        if ( $actor ) { echo '<p class="form-field form-field-wide"><label>Καταχώριση από:</label>' . esc_html( $actor ) . ' (#' . (int) $order->get_meta( self::META_ACTOR ) . ')</p>'; }
    }

    public static function save_edit( $id ) {
        $nonce = $_POST['b2b_order_channel_nonce'] ?? null;
        $value = $_POST['b2b_order_channel'] ?? null;
        if ( ! is_string( $nonce ) || ! is_string( $value ) || ! isset( self::labels()[ $value ] ) || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $id ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'b2b_order_channel_' . $id ) ) { return; }
        $order = wc_get_order( $id );
        if ( ! $order || $order->get_type() !== 'shop_order' ) { return; }
        $before = self::channel( $order );
        if ( $before === $value ) { return; }
        if ( $value === 'unknown' ) { $order->delete_meta_data( self::META_CHANNEL ); }
        else { $order->update_meta_data( self::META_CHANNEL, $value ); }
        if ( ! $order->save() ) { return; }
        // Keep the original seller; record the correcting administrator separately in the note.
        $editor = get_userdata( get_current_user_id() );
        $order->add_order_note( sprintf( 'Διόρθωση προέλευσης: %s → %s. Από %s (#%d).', self::labels()[ $before ], self::labels()[ $value ], $editor ? $editor->display_name : 'Χρήστη', get_current_user_id() ), false, true );
    }

    public static function bulk_actions( $actions ) {
        if ( current_user_can( 'manage_woocommerce' ) ) { $actions[ self::EXPORT_ACTION ] = 'Εξαγωγή προέλευσης (CSV)'; }
        return $actions;
    }

    /** CSV has order totals saved in WooCommerce, never recalculated catalog prices. */
    public static function write_csv( $stream, $ids ) {
        fwrite( $stream, "\xEF\xBB\xBF" );
        fputcsv( $stream, [ 'order_id', 'order_number', 'date', 'status', 'customer_id', 'company', 'total', 'currency', 'order_channel', 'order_channel_label', 'actor_id', 'actor_name' ], ',', '"', '' );
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            if ( ! $id || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $id ) ) { continue; }
            $order = wc_get_order( $id );
            if ( ! $order || $order->get_type() !== 'shop_order' ) { continue; }
            $date = $order->get_date_created();
            $row = [ $id, $order->get_order_number(), $date ? $date->date( 'Y-m-d H:i:s' ) : '', $order->get_status(), $order->get_customer_id(), $order->get_billing_company(), $order->get_total(), $order->get_currency(), self::channel( $order ), self::labels()[ self::channel( $order ) ], $order->get_meta( self::META_ACTOR ), self::actor_name( $order ) ];
            // Treat user-controlled cells as text in spreadsheet applications.
            $row = array_map( static function( $cell ) { $cell = (string) $cell; return preg_match( '/^[\s\x00-\x1f]*[=+@-]/', $cell ) ? "'" . $cell : $cell; }, $row );
            fputcsv( $stream, $row, ',', '"', '' );
        }
    }

    public static function bulk_export( $redirect, $action, $ids ) {
        if ( $action !== self::EXPORT_ACTION ) { return $redirect; }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Δεν έχετε πρόσβαση.', '', [ 'response' => 403 ] ); }
        $screen = get_current_screen();
        check_admin_referer( $screen && $screen->id === 'woocommerce_page_wc-orders' ? 'bulk-orders' : 'bulk-posts' );
        if ( ! is_array( $ids ) || count( $ids ) > 1000 ) { wp_die( 'Επιλέξτε έως 1.000 παραγγελίες ανά εξαγωγή.', '', [ 'response' => 400 ] ); }
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="order-channels-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
        $stream = fopen( 'php://output', 'w' );
        self::write_csv( $stream, $ids );
        fclose( $stream );
        exit;
    }
}
