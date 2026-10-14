<?php
/** Remove only data owned by B2B Pre-Order Matrix on plugin deletion. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

function b2b_matrix_uninstall_site() {
    global $wpdb;

    foreach ( [ 'b2b_preorder_items', 'b2b_preorder_batches', 'b2b_preorder_drafts' ] as $suffix ) {
        $table = $wpdb->prefix . $suffix;
        if ( preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }
    }

    delete_option( 'b2b_matrix_db_version' );
    delete_option( 'b2b_matrix_appearance' );
    delete_option( 'b2b_matrix_catalog_generation' );
    delete_option( 'b2b_matrix_legacy_retired' );
    delete_option( 'b2b_matrix_last_season_reset' );
    delete_option( 'b2b_matrix_draft_epoch' );
    delete_metadata( 'user', 0, '_b2b_matrix_removed_items_' . md5( $wpdb->prefix ), '', true );
}

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
        switch_to_blog( $site_id );
        b2b_matrix_uninstall_site();
        restore_current_blog();
    }
} else {
    b2b_matrix_uninstall_site();
}
