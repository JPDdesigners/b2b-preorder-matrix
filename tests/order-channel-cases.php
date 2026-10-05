<?php
// Included by backend.php, sharing its isolated WP/WC and database fixtures.
return [
    'ordinary customer and guest cannot choose a channel or forge live' => function() {
        check( B2B_Order_Channel::context() === [ 'canChoose' => false, 'defaultLive' => false ] );
        foreach ( [ true, 1, '1', 'true', 'live', [], null ] as $value ) { check( B2B_Order_Channel::capture( $value ) === [ 'channel' => 'online', 'actor_id' => 0 ] ); }
        $GLOBALS['logged_in'] = false; $GLOBALS['manager'] = true;
        check( B2B_Order_Channel::actor_id() === 0 );
    },
    'direct manager defaults online and boolean input is parsed strictly' => function() {
        $GLOBALS['manager'] = true;
        check( B2B_Order_Channel::context() === [ 'canChoose' => true, 'defaultLive' => false ] );
        foreach ( [ null, false, 'false', '0', [], 'yes' ] as $value ) { check( B2B_Order_Channel::capture( $value ) === [ 'channel' => 'online', 'actor_id' => 7 ] ); }
        foreach ( [ true, 1, '1', 'true' ] as $value ) { check( B2B_Order_Channel::capture( $value ) === [ 'channel' => 'live', 'actor_id' => 7 ] ); }
    },
    'switched seller must have authenticated original cookie and current capability' => function() {
        $GLOBALS['old_user'] = new WP_User( 19 ); $GLOBALS['manager_ids'] = [ 19 ];
        check( B2B_Order_Channel::actor_id() === 0 );
        $GLOBALS['old_authenticated'] = true;
        check( B2B_Order_Channel::context() === [ 'canChoose' => true, 'defaultLive' => true ] );
        check( B2B_Order_Channel::capture() === [ 'channel' => 'live', 'actor_id' => 19 ] );
        check( B2B_Order_Channel::capture( '0' ) === [ 'channel' => 'online', 'actor_id' => 19 ] );
        $GLOBALS['manager_ids'] = []; check( B2B_Order_Channel::actor_id() === 0 );
        $GLOBALS['old_user'] = (object) [ 'ID' => 19 ]; check( B2B_Order_Channel::actor_id() === 0 );
    },
    'switched privileged target still records original seller' => function() {
        $GLOBALS['manager'] = $GLOBALS['old_authenticated'] = true; $GLOBALS['old_user'] = new WP_User( 19 ); $GLOBALS['manager_ids'] = [ 19 ];
        check( B2B_Order_Channel::actor_id() === 19 );
    },
    'customer forged seller id ignored in start-order AJAX payload' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] );
        $reply = B2B_Preorder_List::dispatch( 'start-order', [ 'expected_user_id' => 7, 'live_showroom' => '1', 'actor_id' => 19 ] );
        check( ! is_wp_error( $reply ) ); $order = wc_get_order( $reply['data']['order_id'] );
        check( $order->get_meta( '_jpd_order_channel' ) === 'online' );
        check( $order->get_meta( '_jpd_order_actor_id' ) === '' );
    },
    'live origin and seller are persisted before batch lines and native email' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] ); $GLOBALS['old_user'] = new WP_User( 19 ); $GLOBALS['old_authenticated'] = true; $GLOBALS['manager_ids'] = [ 19 ];
        $reply = B2B_Preorder_List::execute_start_order( 7 ); $order = wc_get_order( $reply['order_id'] );
        check( $order->get_meta( '_jpd_order_channel' ) === 'live' && $order->get_meta( '_jpd_order_actor_id' ) === 19 );
        check( $order->get_status() === 'b2b-draft' && ! $order->items );
        $snapshot = json_decode( $GLOBALS['wpdb']->batches[$reply['token']]['snapshot'], true );
        check( $snapshot['origin'] === [ 'channel' => 'live', 'actor_id' => 19 ] );
        $GLOBALS['old_authenticated'] = false;
        $again = B2B_Preorder_List::execute_start_order( 7, '', null, '0' );
        check( $again['order_channel'] === 'live' && $again['order_id'] === $reply['order_id'] );
        B2B_Preorder_List::execute_add_batch( 7, $reply['order_id'], $reply['token'] );
        B2B_Preorder_List::execute_finalize_order( 7, $reply['order_id'], $reply['token'] );
        check( $order->get_status() === 'processing' && $order->get_meta( '_jpd_order_channel' ) === 'live' );
    },
    'snapshot retains source when interruption happens before shell association' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] ); $GLOBALS['manager'] = true;
        $reply = B2B_Preorder_List::execute_start_order( 7, '', null, '1' );
        $GLOBALS['wpdb']->batches[$reply['token']]['wc_order_id'] = 0;
        $GLOBALS['orders'] = []; $GLOBALS['manager'] = false;
        $again = B2B_Preorder_List::execute_start_order( 7, '', null, '0' );
        check( $again['token'] === $reply['token'] && wc_get_order( $again['order_id'] )->get_meta( '_jpd_order_channel' ) === 'live' );
        check( wc_get_order( $again['order_id'] )->get_meta( '_jpd_order_actor_id' ) === 7 );
    },
    'order recovery preserves admin correction despite frozen snapshot' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] ); $GLOBALS['manager'] = true;
        $reply = B2B_Preorder_List::execute_start_order( 7, '', null, '1' );
        $order = wc_get_order( $reply['order_id'] ); $order->update_meta_data( '_jpd_order_channel', 'online' );
        $GLOBALS['wpdb']->batches[$reply['token']]['wc_order_id'] = 0;
        B2B_Preorder_List::execute_start_order( 7, '', null, '1' );
        check( count( $GLOBALS['orders'] ) === 1 && $order->get_meta( '_jpd_order_channel' ) === 'online' );
    },
    'private list payload exposes staff context and frozen channel without seller id or snapshot' => function() {
        unset( $GLOBALS['wpdb']->items[7][55] ); $GLOBALS['manager'] = true;
        B2B_Preorder_List::execute_start_order( 7, '', null, '1' );
        $payload = B2B_Preorder_List::get_list_payload( 7 );
        check( $payload['orderChannelContext']['canChoose'] && $payload['activeBatch']['order_channel'] === 'live' );
        check( ! isset( $payload['activeBatch']['snapshot'] ) && ! isset( $payload['orderChannelContext']['actor_id'] ) );
    },
    'legacy snapshot remains unknown instead of guessing online' => function() {
        $order = new WC_Order(); B2B_Order_Channel::apply_snapshot( $order, [ 'items' => [] ] );
        check( B2B_Order_Channel::channel( $order ) === 'unknown' && $order->get_meta( '_jpd_order_channel' ) === '' );
        $order->update_meta_data( '_jpd_order_channel', 'bogus' ); check( B2B_Order_Channel::channel( $order ) === 'unknown' );
    },
    'HPOS filter preserves nested OR constraints and combines with AND' => function() {
        $GLOBALS['manager'] = true; $_GET['jpd_order_channel'] = 'live';
        $existing = [ 'relation' => 'OR', [ 'key' => 'season', 'value' => 'A' ], [ 'key' => 'season', 'value' => 'B' ] ];
        $args = B2B_Order_Channel::hpos_query( [ 'status' => 'processing', 'meta_query' => $existing ] );
        check( $args['status'] === 'processing' && $args['meta_query']['relation'] === 'AND' && $args['meta_query'][0] === $existing );
        check( $args['meta_query'][1] === [ 'key' => '_jpd_order_channel', 'value' => 'live', 'compare' => '=' ] );
        $_GET['jpd_order_channel'] = 'unknown'; $clause = B2B_Order_Channel::hpos_query( [] )['meta_query'][0];
        check( $clause['relation'] === 'OR' && $clause[0]['compare'] === 'NOT EXISTS' && $clause[1]['value'] === [ 'live', 'online' ] );
    },
    'invalid filter and unprivileged filter leave query unchanged' => function() {
        foreach ( [ 'invalid', [], 'live' ] as $value ) { $_GET['jpd_order_channel'] = $value; check( B2B_Order_Channel::hpos_query( [ 'limit' => 20 ] ) === [ 'limit' => 20 ] ); }
        $GLOBALS['manager'] = true; $_GET['jpd_order_channel'] = [];
        check( B2B_Order_Channel::hpos_query( [] ) === [] );
    },
    'legacy filter affects only admin main shop_order query' => function() {
        $GLOBALS['manager'] = $GLOBALS['is_admin'] = true; $GLOBALS['screen_id'] = 'edit-shop_order'; $_GET['jpd_order_channel'] = 'online';
        $query = new class { public $main = true, $vars = [ 'post_type' => 'shop_order' ]; function is_main_query() { return $this->main; } function get( $key ) { return $this->vars[$key] ?? []; } function set( $key, $value ) { $this->vars[$key] = $value; } };
        B2B_Order_Channel::legacy_query( $query ); check( $query->vars['meta_query'][0]['value'] === 'online' );
        unset( $query->vars['meta_query'] ); $query->main = false; B2B_Order_Channel::legacy_query( $query ); check( ! isset( $query->vars['meta_query'] ) );
        $query->main = true; $GLOBALS['screen_id'] = 'edit-product'; B2B_Order_Channel::legacy_query( $query ); check( ! isset( $query->vars['meta_query'] ) );
        $GLOBALS['screen_id'] = 'edit-shop_order'; $GLOBALS['is_admin'] = false; B2B_Order_Channel::legacy_query( $query ); check( ! isset( $query->vars['meta_query'] ) );
    },
    'admin correction creates internal audit note and preserves seller' => function() {
        $GLOBALS['manager'] = true; $order = new WC_Order(); $order->update_meta_data( '_jpd_order_channel', 'live' ); $order->update_meta_data( '_jpd_order_actor_id', 19 ); $order->save();
        $_POST = [ 'b2b_order_channel_nonce' => 'b2b_order_channel_' . $order->id, 'b2b_order_channel' => 'online' ];
        B2B_Order_Channel::save_edit( $order->id );
        check( $order->get_meta( '_jpd_order_channel' ) === 'online' && $order->get_meta( '_jpd_order_actor_id' ) === 19 );
        check( count( $order->notes ) === 1 && strpos( $order->notes[0][0], 'Live δειγματισμός → Online' ) !== false && strpos( $order->notes[0][0], '#7' ) !== false && $order->notes[0][1] === false );
        B2B_Order_Channel::save_edit( $order->id ); check( count( $order->notes ) === 1 );
        $_POST['b2b_order_channel'] = 'unknown'; B2B_Order_Channel::save_edit( $order->id ); check( $order->get_meta( '_jpd_order_channel' ) === '' && $order->get_meta( '_jpd_order_actor_id' ) === 19 );
    },
    'correction rejects wrong nonce capabilities malformed fields and invalid values' => function() {
        $GLOBALS['manager'] = true; $order = new WC_Order(); $order->update_meta_data( '_jpd_order_channel', 'live' ); $order->save();
        foreach ( [ 'wrong', 'b2b_order_channel_999', [] ] as $nonce ) { $_POST = [ 'b2b_order_channel_nonce' => $nonce, 'b2b_order_channel' => 'online' ]; B2B_Order_Channel::save_edit( $order->id ); check( B2B_Order_Channel::channel( $order ) === 'live' ); }
        $_POST = [ 'b2b_order_channel_nonce' => 'b2b_order_channel_' . $order->id, 'b2b_order_channel' => 'online' ];
        $GLOBALS['denied_order_id'] = $order->id; B2B_Order_Channel::save_edit( $order->id ); check( B2B_Order_Channel::channel( $order ) === 'live' );
        $GLOBALS['denied_order_id'] = null; $GLOBALS['manager'] = false; B2B_Order_Channel::save_edit( $order->id ); check( B2B_Order_Channel::channel( $order ) === 'live' );
        $GLOBALS['manager'] = true;
        foreach ( [ 'bogus', [] ] as $value ) { $_POST['b2b_order_channel'] = $value; B2B_Order_Channel::save_edit( $order->id ); check( B2B_Order_Channel::channel( $order ) === 'live' ); }
    },
    'CSV includes source seller saved total and formula-safe company once per selected order' => function() {
        $GLOBALS['manager'] = true; $order = new WC_Order(); $order->set_customer_id( 7 ); $order->set_total( '321.45' ); $order->set_address( [ 'company' => " \t=HYPERLINK(\"https://example.test\")" ], 'billing' );
        $order->update_meta_data( '_jpd_order_channel', 'live' ); $order->update_meta_data( '_jpd_order_actor_id', 19 ); $order->save();
        $stream = fopen( 'php://temp', 'w+' ); B2B_Order_Channel::write_csv( $stream, [ $order->id, $order->id, 999, 0 ] ); rewind( $stream ); $csv = stream_get_contents( $stream );
        check( substr( $csv, 0, 3 ) === "\xEF\xBB\xBF" ); rewind( $stream ); fgetcsv( $stream ); $row = fgetcsv( $stream );
        check( $row[5][0] === "'" && $row[6] === '321.45' && $row[8] === 'live' && $row[10] === '19' && $row[11] === 'Seller' );
        check( fgetcsv( $stream ) === false && $GLOBALS['full_order_item_reads'] === 0 ); fclose( $stream );
    },
    'CSV unknown origins are explicit and inaccessible orders are excluded' => function() {
        $GLOBALS['manager'] = true; $order = new WC_Order(); $order->save();
        $stream = fopen( 'php://temp', 'w+' ); B2B_Order_Channel::write_csv( $stream, [ $order->id ] ); rewind( $stream ); fgetcsv( $stream ); check( fgetcsv( $stream )[8] === 'unknown' ); fclose( $stream );
        $GLOBALS['denied_order_id'] = $order->id; $stream = fopen( 'php://temp', 'w+' ); B2B_Order_Channel::write_csv( $stream, [ $order->id ] ); rewind( $stream ); fgetcsv( $stream ); check( fgetcsv( $stream ) === false ); fclose( $stream );
    },
    'admin display filter edit and bulk action are hidden for customers' => function() {
        $order = new WC_Order(); $order->save(); ob_start(); B2B_Order_Channel::column( 'jpd_order_channel', $order ); B2B_Order_Channel::dropdown( 'shop_order' ); B2B_Order_Channel::edit_field( $order ); check( ob_get_clean() === '' );
        check( B2B_Order_Channel::columns( [] ) === [] && B2B_Order_Channel::bulk_actions( [] ) === [] );
        $GLOBALS['manager'] = true; check( isset( B2B_Order_Channel::columns( [] )['jpd_order_channel'] ) && isset( B2B_Order_Channel::bulk_actions( [] )[ B2B_Order_Channel::EXPORT_ACTION ] ) );
        ob_start(); B2B_Order_Channel::dropdown( 'product' ); B2B_Order_Channel::dropdown( 'shop_order', 'bottom' ); check( ob_get_clean() === '' );
        ob_start(); B2B_Order_Channel::edit_field( $order ); check( strpos( ob_get_clean(), 'Μη καταγεγραμμένη προέλευση' ) !== false );
    },
    'CSV bulk download checks native nonce permissions and bounded selection before output' => function() {
        check( B2B_Order_Channel::bulk_export( '/orders', 'unrelated', [] ) === '/orders' );
        $must_reject = function( $ids, $message ) {
            try { B2B_Order_Channel::bulk_export( '/orders', B2B_Order_Channel::EXPORT_ACTION, $ids ); throw new Exception( 'Expected rejection' ); }
            catch ( RuntimeException $e ) { check( strpos( $e->getMessage(), $message ) !== false ); }
        };
        $must_reject( [], 'Δεν έχετε πρόσβαση' ); check( $GLOBALS['checked_admin_action'] === '' );
        $GLOBALS['manager'] = true; $GLOBALS['screen_id'] = 'woocommerce_page_wc-orders';
        $must_reject( [], 'Invalid admin nonce' ); check( $GLOBALS['checked_admin_action'] === 'bulk-orders' );
        $GLOBALS['screen_id'] = 'edit-shop_order'; $must_reject( [], 'Invalid admin nonce' ); check( $GLOBALS['checked_admin_action'] === 'bulk-posts' );
        $GLOBALS['admin_nonce_action'] = 'bulk-posts'; $must_reject( range( 1, 1001 ), '1.000' ); $must_reject( '1001', '1.000' );
    },
];
