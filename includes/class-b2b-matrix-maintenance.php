<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit, site-scoped administration. Never schedules or automatically deletes data. */
class B2B_Matrix_Maintenance {
    const ACTIVE = "'pending', 'building', 'ready_to_finalize'";

    public static function init() {
        add_action( 'admin_post_b2b_matrix_reset_season', [ __CLASS__, 'handle_reset' ] );
    }

    public static function schema() {
        global $wpdb;
        $definitions = [
            B2B_Matrix_DB::table_items() => [
                'label' => 'My List',
                'columns' => [ 'id', 'user_id', 'product_id', 'variation_id', 'qty', 'price', 'updated_at' ],
                'indexes' => [ 'PRIMARY' => [ 'id' ], 'user_variation' => [ 'user_id', 'variation_id' ], 'user_product' => [ 'user_id', 'product_id' ], 'user_id' => [ 'user_id' ] ],
            ],
            B2B_Matrix_DB::table_batches() => [
                'label' => 'Υποβολές / batches',
                'columns' => [ 'id', 'batch_token', 'user_id', 'wc_order_id', 'status', 'total_items', 'processed_items', 'error_message', 'snapshot', 'created_at', 'updated_at' ],
                'indexes' => [ 'PRIMARY' => [ 'id' ], 'batch_token' => [ 'batch_token' ], 'user_status' => [ 'user_id', 'status' ] ],
            ],
        ];
        $result = [];
        foreach ( $definitions as $table => $definition ) {
            $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
            $issues = [];
            if ( ! $status || $wpdb->last_error ) { $issues[] = 'Ο πίνακας λείπει ή δεν μπορεί να διαβαστεί.'; }
            else {
                if ( strtolower( $status['Engine'] ?? '' ) !== 'innodb' ) { $issues[] = 'Απαιτείται InnoDB για ασφαλείς συναλλαγές.'; }
                $columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
                if ( $wpdb->last_error || array_diff( $definition['columns'], array_column( (array) $columns, 'Field' ) ) ) { $issues[] = 'Λείπουν απαραίτητα πεδία.'; }
                $indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
                if ( $wpdb->last_error ) { $issues[] = 'Δεν μπορεί να ελεγχθεί η δομή των indexes.'; }
                $actual = []; $unique = [];
                foreach ( (array) $indexes as $index ) {
                    $actual[ $index['Key_name'] ][ (int) $index['Seq_in_index'] ] = $index['Column_name'];
                    $unique[ $index['Key_name'] ] = (int) $index['Non_unique'] === 0;
                }
                foreach ( $definition['indexes'] as $name => $fields ) {
                    $found = $actual[ $name ] ?? []; ksort( $found );
                    if ( array_values( $found ) !== $fields || ( in_array( $name, [ 'PRIMARY', 'user_variation', 'batch_token' ], true ) && empty( $unique[ $name ] ) ) ) {
                        $issues[] = 'Λείπει ή διαφέρει το index: ' . $name;
                    }
                }
            }
            $result[ $table ] = [ 'label' => $definition['label'], 'status' => $status ?: [], 'issues' => $issues ];
        }
        return $result;
    }

    /** Read-only diagnostics, only on the administrator's explicit request. */
    public static function health() {
        global $wpdb;
        $tables = self::schema();
        foreach ( $tables as $name => &$table ) {
            if ( $table['issues'] ) { continue; }
            $table['rows'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}`" );
            if ( $wpdb->last_error ) { $table['issues'][] = 'Αποτυχία μέτρησης εγγραφών.'; continue; }
            if ( $name === B2B_Matrix_DB::table_items() ) {
                $table['invalid'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}` WHERE user_id <= 0 OR product_id <= 0 OR variation_id <= 0 OR qty <= 0" );
            } else {
                $table['invalid'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}` WHERE user_id <= 0 OR total_items < 0 OR processed_items < 0 OR processed_items > total_items OR status NOT IN ('pending', 'building', 'ready_to_finalize', 'complete', 'failed', 'cancelled')" );
                $table['active'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$name}` WHERE status IN (" . self::ACTIVE . ')' );
                if ( $wpdb->last_error ) { $table['issues'][] = 'Αποτυχία ανάγνωσης ενεργών υποβολών.'; }
                $table['history'] = $wpdb->get_row( "SELECT MIN(created_at) AS oldest, MAX(updated_at) AS latest, COALESCE(SUM(status = 'complete'), 0) AS complete, COALESCE(SUM(status = 'failed'), 0) AS failed, COALESCE(SUM(status = 'cancelled'), 0) AS cancelled FROM `{$name}`", ARRAY_A );
                if ( $wpdb->last_error || ! $table['history'] ) { $table['issues'][] = 'Αποτυχία ανάγνωσης ιστορικού υποβολών.'; }
            }
            if ( $wpdb->last_error || $table['invalid'] === null ) { $table['issues'][] = 'Ο έλεγχος εγγραφών δεν ολοκληρώθηκε.'; }
            elseif ( (int) $table['invalid'] > 0 ) { $table['issues'][] = 'Εγγραφές που χρειάζονται διερεύνηση: ' . (int) $table['invalid']; }
        }
        unset( $table );
        return $tables;
    }

    public static function reset_season() {
        global $wpdb;
        $lock = B2B_Matrix_DB::maintenance_lock_name();
        if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) !== '1' ) {
            return new WP_Error( 'maintenance_busy', 'Εκτελείται ήδη καθαρισμός. Δοκιμάστε αργότερα.' );
        }
        $transaction = false;
        try {
            foreach ( self::schema() as $table ) {
                if ( $table['issues'] ) { return new WP_Error( 'schema_error', 'Ο καθαρισμός σταμάτησε: ' . implode( ' ', $table['issues'] ) ); }
            }
            $items = B2B_Matrix_DB::table_items(); $batches = B2B_Matrix_DB::table_batches();
            // Include all accounts, even a first save without any table rows yet,
            // plus deleted accounts whose requests might still be finishing.
            // No new callback can enter while the maintenance lock is held.
            $busy = $wpdb->get_var( $wpdb->prepare(
                "SELECT account_id FROM (SELECT ID AS account_id FROM `{$wpdb->users}` UNION SELECT user_id FROM `{$items}` UNION SELECT user_id FROM `{$batches}`) accounts WHERE COALESCE(IS_FREE_LOCK(CONCAT('b2b_', MD5(CONCAT(%s, ':', account_id)))), 0) <> 1 LIMIT 1", $wpdb->prefix
            ) );
            if ( $wpdb->last_error ) { return B2B_Matrix_DB::db_error(); }
            if ( $busy !== null ) { return new WP_Error( 'accounts_busy', 'Υπάρχει αίτημα που ενημερώνει λίστα ή υποβολή. Δοκιμάστε ξανά όταν ολοκληρωθεί.' ); }
            $active = $wpdb->get_var( "SELECT COUNT(*) FROM `{$batches}` WHERE status IN (" . self::ACTIVE . ')' );
            if ( $wpdb->last_error || $active === null ) { return B2B_Matrix_DB::db_error(); }
            if ( (int) $active > 0 ) { return new WP_Error( 'active_batches', 'Υπάρχουν ενεργές υποβολές. Ολοκληρώστε ή ακυρώστε τις από το My List πριν από τον καθαρισμό.' ); }
            $options_status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->options ) ), ARRAY_A );
            if ( $wpdb->last_error || strtolower( $options_status['Engine'] ?? '' ) !== 'innodb' ) {
                return new WP_Error( 'schema_error', 'Ο πίνακας επιλογών WordPress πρέπει επίσης να είναι InnoDB, ώστε ο καθαρισμός και η απενεργοποίηση παλιών εισαγωγών να ολοκληρώνονται μαζί.' );
            }
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return B2B_Matrix_DB::db_error(); }
            $transaction = true;
            // Legacy metadata is not site-scoped. Retire its import for this site
            // instead of deleting another site's legacy customer lists. Include
            // the marker in the transaction, even if the PHP worker is interrupted.
            if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO `{$wpdb->options}` (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = '1', autoload = 'no'", 'b2b_matrix_legacy_retired' ) ) ) { return B2B_Matrix_DB::db_error(); }
            foreach ( [ $items, $batches ] as $table ) {
                if ( false === $wpdb->query( "DELETE FROM `{$table}`" ) ) { return B2B_Matrix_DB::db_error(); }
            }
            // No account callback can read/cache the old marker during maintenance.
            // Invalidate before commit too, covering worker loss immediately after it.
            self::clear_retirement_cache();
            if ( false === $wpdb->query( 'COMMIT' ) ) { return B2B_Matrix_DB::db_error(); }
            $transaction = false;
            delete_metadata( 'user', 0, B2B_Matrix_DB::removal_notice_key(), '', true );
            update_option( 'b2b_matrix_last_season_reset', current_time( 'mysql' ), false );
            return true;
        } catch ( Throwable $e ) { return B2B_Matrix_DB::db_error(); }
        finally {
            if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
            self::clear_retirement_cache();
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    private static function clear_retirement_cache() {
        wp_cache_delete( 'b2b_matrix_legacy_retired', 'options' );
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }

    public static function handle_reset() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Δεν έχετε δικαίωμα διαχείρισης πινάκων.', '', [ 'response' => 403 ] ); }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'Ο καθαρισμός απαιτεί POST.', '', [ 'response' => 405 ] ); }
        check_admin_referer( 'b2b_matrix_reset_season' );
        if ( ! isset( $_POST['confirmation'] ) || ! is_string( $_POST['confirmation'] ) || trim( wp_unslash( $_POST['confirmation'] ) ) !== 'CLEAR' ) {
            wp_die( 'Πληκτρολογήστε CLEAR για να επιβεβαιώσετε τη διαγραφή.' );
        }
        $result = self::reset_season();
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'Ο καθαρισμός δεν ολοκληρώθηκε', [ 'back_link' => true ] ); }
        wp_safe_redirect( admin_url( 'admin.php?page=b2b-matrix&tab=data&reset=done' ) ); exit;
    }
}
