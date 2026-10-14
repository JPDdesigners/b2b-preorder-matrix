<?php
/** Integration checks against a disposable local MariaDB, never a WordPress database.
 * Run: php tests/maintenance.php 3317
 * The caller must start its own isolated server; database and prefix are fixed here.
 */
define( 'ABSPATH', __DIR__ . '/fixtures/maintenance-wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );
$port = filter_var( $argv[1] ?? '', FILTER_VALIDATE_INT );
if ( ! $port || $port === 3306 ) { throw new RuntimeException( 'Pass the port of a disposable local MariaDB (not 3306).' ); }
mysqli_report( MYSQLI_REPORT_OFF );
class WP_Error {
    private $code, $message;
    function __construct( $code, $message, $data = [] ) { $this->code = $code; $this->message = $message; }
    function get_error_code() { return $this->code; }
    function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $name, $default = false ) { $db=$GLOBALS['wpdb']; return $db->get_var($db->prepare('SELECT option_value FROM audit_options WHERE option_name = %s', $name)) ?? $default; }
function update_option( $name, $value, $autoload = null ) { $db=$GLOBALS['wpdb']; return false !== $db->query($db->prepare("INSERT INTO audit_options (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)",$name,(string)$value)); }
function wp_cache_delete( $key, $group ) { $GLOBALS['cache_deletions'][]=[$key,$group]; }
function delete_metadata( ...$args ) { $GLOBALS['metadata_deleted'][] = $args; return true; }
function current_time( $format ) { return '2026-10-05 12:00:00'; }
function sanitize_textarea_field( $value ) { return trim(strip_tags($value)); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['admin'] ) && $cap === 'manage_options'; }
function check_admin_referer( $action ) { if ( ! $GLOBALS['nonce'] ) wp_die( 'invalid nonce' ); }
function wp_die( $message, ...$args ) { throw new RuntimeException( $message ); }
function wp_unslash( $v ) { return $v; }
function get_user_meta( ...$args ) { $GLOBALS['legacy_reads']++; return [ '_b2b_custom_order_list' => [ 101 => 8 ] ]; }
class LocalWPDB {
    public $prefix = 'audit_', $users = 'audit_users', $options = 'audit_options', $last_error = '', $connection, $queries = [], $fail_sql = '';
    function __construct( $port ) {
        $this->connection = new mysqli( '127.0.0.1', 'root', '', '', $port );
        if ( $this->connection->connect_errno ) throw new RuntimeException( 'Isolated MariaDB unavailable' );
        $this->connection->query( 'CREATE DATABASE IF NOT EXISTS codex_b2b_maintenance_test' );
        $this->connection->select_db( 'codex_b2b_maintenance_test' );
        $this->connection->set_charset( 'utf8mb4' );
    }
    function prepare( $sql, ...$values ) {
        if ( isset( $values[0] ) && is_array( $values[0] ) ) $values = $values[0];
        return preg_replace_callback( '/%[sd]/', function( $match ) use ( &$values ) {
            $value = array_shift( $values );
            return $match[0] === '%d' ? (string) (int) $value : "'" . $this->connection->real_escape_string( $value ) . "'";
        }, $sql );
    }
    function esc_like( $v ) { return addcslashes( $v, '_%\\' ); }
    function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    function execute( $sql ) {
        $this->queries[] = $sql; $this->last_error = '';
        if ( $this->fail_sql && $sql === $this->fail_sql ) { $this->fail_sql = ''; $this->last_error = 'Injected failure'; return false; }
        $result = $this->connection->query( $sql );
        if ( $result === false ) $this->last_error = $this->connection->error;
        return $result;
    }
    function query( $sql ) { $r = $this->execute( $sql ); return $r === false ? false : $this->connection->affected_rows; }
    function get_var( $sql ) { $r = $this->execute( $sql ); $row = $r instanceof mysqli_result ? $r->fetch_row() : null; return $row[0] ?? null; }
    function get_row( $sql, $mode = null ) { $r = $this->execute( $sql ); return $r instanceof mysqli_result ? $r->fetch_assoc() : null; }
    function get_results( $sql, $mode = null ) { $r = $this->execute( $sql ); return $r instanceof mysqli_result ? $r->fetch_all( MYSQLI_ASSOC ) : null; }
}
$wpdb = new LocalWPDB( $port ); $other = new LocalWPDB( $port );
$GLOBALS['wpdb_primary'] = $wpdb;
require __DIR__ . '/../includes/class-b2b-matrix-db.php';
require __DIR__ . '/../includes/class-b2b-matrix-maintenance.php';
$checks = 0;
function check( $value, $label ) { if ( ! $value ) throw new RuntimeException( $label ); echo "PASS $label\n"; $GLOBALS['checks']++; }
function count_rows( $table ) { return (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM `{$table}`" ); }
function error_code( $result, $code ) { return is_wp_error( $result ) && $result->get_error_code() === $code; }
foreach ( [ 'audit_b2b_preorder_items', 'audit_b2b_preorder_batches', 'audit_b2b_preorder_drafts', 'audit_users', 'audit_wc_orders', 'audit_options' ] as $table ) $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
$wpdb->query( "CREATE TABLE audit_options (option_name VARCHAR(191) PRIMARY KEY, option_value LONGTEXT, autoload VARCHAR(20)) ENGINE=InnoDB" );
$wpdb->query( 'CREATE TABLE audit_users (ID BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB' );
$wpdb->query( 'INSERT INTO audit_users VALUES (7), (12)' );
$wpdb->query( 'CREATE TABLE audit_wc_orders (id INT PRIMARY KEY) ENGINE=InnoDB' );
$wpdb->query( 'INSERT INTO audit_wc_orders VALUES (300)' );
B2B_Matrix_DB::install();
$items = B2B_Matrix_DB::table_items(); $batches = B2B_Matrix_DB::table_batches();
$drafts = B2B_Matrix_DB::table_drafts();
$wpdb->query( "INSERT INTO `{$items}` (user_id,product_id,variation_id,qty) VALUES (12,100,101,3)" );
$wpdb->query( "INSERT INTO `{$batches}` (batch_token,user_id,status,total_items,processed_items,snapshot) VALUES ('old',12,'complete',1,1,'{\"items\":[101]}')" );
$wpdb->query("DROP TABLE `{$drafts}`");
update_option(B2B_Matrix_DB::DB_VERSION_OPTION, '3.2.0');
B2B_Matrix_DB::check_schema();
check(get_option(B2B_Matrix_DB::DB_VERSION_OPTION) === B2B_Matrix_DB::DB_VERSION && count_rows($items) === 1 && count_rows($batches) === 1 && count_rows($drafts) === 0, 'upgrade creates draft table while preserving existing lists and batch history');
$initialDraft = B2B_Matrix_DB::get_draft(7);
$savedDraft = B2B_Matrix_DB::save_draft(7, "Σχόλιο 'πελάτη' \\ αποθήκη\n" . str_repeat('Ω',3900), $initialDraft['revision']);
check(!is_wp_error($savedDraft) && $savedDraft['note'] === "Σχόλιο 'πελάτη' \\ αποθήκη\n" . str_repeat('Ω',3900), 'real UTF8 draft persistence preserves Greek, quotes, slashes and newlines');
$wpdb = $other;
check(B2B_Matrix_DB::get_draft(7) === $savedDraft && B2B_Matrix_DB::get_draft(12)['note'] === '', 'second database connection sees persisted draft and isolated accounts');
check(error_code(B2B_Matrix_DB::save_draft(7,'Stale device',$initialDraft['revision']),'note_conflict'), 'stale device revision cannot overwrite persisted note');
$wpdb = $GLOBALS['wpdb_primary'];
$health = B2B_Matrix_Maintenance::health();
check( ! $health[$items]['issues'] && ! $health[$batches]['issues'] && ! $health[$drafts]['issues'] && (int) $health[$drafts]['rows'] === 1 && (int) $health[$items]['rows'] === 1 && (int) $health[$batches]['history']['complete'] === 1, 'all three installed tables, exact counts and batch history pass health check' );
$wpdb->query( "INSERT INTO `{$batches}` (batch_token,user_id,status) VALUES ('active',12,'pending')" );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'active_batches' ) && count_rows($items) === 1 && count_rows($batches) === 2, 'active submission blocks deletion of both tables' );
$wpdb->query( "DELETE FROM `{$batches}` WHERE batch_token='active'" );
$user_lock = 'b2b_' . md5( $wpdb->prefix . ':7' );
$other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $user_lock ) );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'accounts_busy' ) && count_rows($items) === 1, 'in-flight first save without table rows blocks reset' );
$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $user_lock ) );
$other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', B2B_Matrix_DB::maintenance_lock_name() ) );
$called = false;
$result = B2B_Matrix_DB::with_user_lock(7, function() use (&$called) { $called=true; return true; });
check( error_code($result, 'season_reset') && ! $called, 'maintenance rejects new account callbacks before any writes' );
check( (string) $other->get_var( $other->prepare( 'SELECT IS_FREE_LOCK(%s)', $user_lock ) ) === '1', 'rejected callback releases its account lock' );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'maintenance_busy' ), 'parallel reset is rejected' );
$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', B2B_Matrix_DB::maintenance_lock_name() ) );
$wpdb->query( "ALTER TABLE `{$items}` DROP INDEX user_variation" );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'schema_error' ), 'missing unique index prevents unsafe reset' );
$wpdb->query( "ALTER TABLE `{$items}` ADD UNIQUE KEY user_variation (user_id,variation_id)" );
$wpdb->query( "ALTER TABLE `{$items}` ENGINE=MyISAM" );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'schema_error' ), 'nontransactional table prevents reset' );
$wpdb->query( "ALTER TABLE `{$items}` ENGINE=InnoDB" );
$wpdb->query( 'ALTER TABLE audit_options ENGINE=MyISAM' );
check( error_code( B2B_Matrix_Maintenance::reset_season(), 'schema_error' ), 'nontransactional WordPress options table prevents reset' );
$wpdb->query( 'ALTER TABLE audit_options ENGINE=InnoDB' );
foreach ( [ "DELETE FROM `{$batches}`", "DELETE FROM `{$drafts}`", 'COMMIT' ] as $failure ) {
    $wpdb->fail_sql = $failure;
    check( error_code( B2B_Matrix_Maintenance::reset_season(), 'database_error' ) && count_rows($items) === 1 && count_rows($batches) === 1 && B2B_Matrix_DB::get_draft(7) === $savedDraft && !get_option('b2b_matrix_legacy_retired') && !get_option('b2b_matrix_draft_epoch'), 'failed ' . $failure . ' rolls back all three tables and both policies' );
}
foreach ( [ ['admin'=>false,'nonce'=>true,'method'=>'POST','confirmation'=>'CLEAR'], ['admin'=>true,'nonce'=>false,'method'=>'POST','confirmation'=>'CLEAR'], ['admin'=>true,'nonce'=>true,'method'=>'GET','confirmation'=>'CLEAR'], ['admin'=>true,'nonce'=>true,'method'=>'POST','confirmation'=>['CLEAR']], ['admin'=>true,'nonce'=>true,'method'=>'POST','confirmation'=>'clear'] ] as $case ) {
    $GLOBALS['admin']=$case['admin']; $GLOBALS['nonce']=$case['nonce']; $_SERVER['REQUEST_METHOD']=$case['method']; $_POST['confirmation']=$case['confirmation']; $denied=false;
    try { B2B_Matrix_Maintenance::handle_reset(); } catch (RuntimeException $e) { $denied=true; }
    check($denied && count_rows($items)===1, 'handler rejects invalid capability / nonce / method / confirmation');
}
check( B2B_Matrix_Maintenance::reset_season() === true && count_rows($items) === 0 && count_rows($batches) === 0 && count_rows($drafts) === 0, 'successful reset deletes all three tables atomically' );
check(error_code(B2B_Matrix_DB::save_draft(7,'Old draft',$savedDraft['revision']),'note_conflict') && error_code(B2B_Matrix_DB::save_draft(12,'Previously empty draft',B2B_Matrix_DB::get_draft(12)['revision'].'stale'),'note_conflict'), 'season reset rejects stale drafts');
$emptyAfterReset = B2B_Matrix_DB::get_draft(12);
check(B2B_Matrix_Maintenance::reset_season() === true && error_code(B2B_Matrix_DB::save_draft(12,'Stale empty draft',$emptyAfterReset['revision']),'note_conflict'), 'repeated reset in the same second invalidates empty draft revisions');
check( count_rows('audit_wc_orders') === 1, 'WooCommerce order data is preserved' );
check( get_option('b2b_matrix_last_season_reset') && get_option('b2b_matrix_legacy_retired') && $GLOBALS['metadata_deleted'][0][2] === B2B_Matrix_DB::removal_notice_key(), 'successful reset records date and retires site legacy import and removal notices' );
$GLOBALS['legacy_reads']=0;
check( B2B_Matrix_DB::get_user_all_items(12) === [] && $GLOBALS['legacy_reads']===0, 'old metadata cannot repopulate a cleared seasonal list' );
check( B2B_Matrix_DB::with_user_lock(7, function() { return true; }) === true, 'normal account work resumes after maintenance' );
echo "$checks integration checks passed\n";
