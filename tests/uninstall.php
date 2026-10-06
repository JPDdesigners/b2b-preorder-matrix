<?php
define( 'WP_UNINSTALL_PLUGIN', 'b2b-matrix-api.php' );

class Fake_WPDB_Uninstall {
    public $prefix = 'wp_';
    public $queries = [];
    public function query( $sql ) { $this->queries[] = $sql; return 1; }
}

$wpdb = new Fake_WPDB_Uninstall();
$deleted_options = [];
$deleted_metadata = [];
function is_multisite() { return false; }
function delete_option( $name ) { $GLOBALS['deleted_options'][] = $name; }
function delete_metadata( ...$args ) { $GLOBALS['deleted_metadata'][] = $args; }

require __DIR__ . '/../uninstall.php';

if ( $wpdb->queries !== [
    'DROP TABLE IF EXISTS `wp_b2b_preorder_items`',
    'DROP TABLE IF EXISTS `wp_b2b_preorder_batches`',
] ) { throw new Exception( 'Uninstall targeted unexpected tables.' ); }

if ( $deleted_options !== [
    'b2b_matrix_db_version',
    'b2b_matrix_appearance',
    'b2b_matrix_catalog_generation',
    'b2b_matrix_legacy_retired',
    'b2b_matrix_last_season_reset',
] ) { throw new Exception( 'Uninstall did not remove only plugin settings.' ); }

if ( $deleted_metadata !== [ [ 'user', 0, '_b2b_matrix_removed_items_' . md5( 'wp_' ), '', true ] ] ) { throw new Exception( 'Uninstall must remove only its cancellation notice user metadata, preserving order history.' ); }
echo "PASS uninstall drops only plugin tables, options and cancellation notices; preserves order metadata\n";
