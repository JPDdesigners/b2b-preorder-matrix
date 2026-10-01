<?php
define( 'WP_UNINSTALL_PLUGIN', 'b2b-matrix-api.php' );

class Fake_WPDB_Uninstall {
    public $prefix = 'wp_';
    public $queries = [];
    public function query( $sql ) { $this->queries[] = $sql; return 1; }
}

$wpdb = new Fake_WPDB_Uninstall();
$deleted_options = [];
function is_multisite() { return false; }
function delete_option( $name ) { $GLOBALS['deleted_options'][] = $name; }

require __DIR__ . '/../uninstall.php';

if ( $wpdb->queries !== [
    'DROP TABLE IF EXISTS `wp_b2b_preorder_items`',
    'DROP TABLE IF EXISTS `wp_b2b_preorder_batches`',
] ) { throw new Exception( 'Uninstall targeted unexpected tables.' ); }

if ( $deleted_options !== [
    'b2b_matrix_db_version',
    'b2b_matrix_appearance',
    'b2b_matrix_catalog_generation',
] ) { throw new Exception( 'Uninstall did not remove only plugin settings.' ); }

echo "PASS uninstall drops only plugin tables and options\n";
