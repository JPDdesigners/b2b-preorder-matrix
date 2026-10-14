<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

/**
 * High-performance database operations for B2B Pre-Order items and batch order creation.
 */
class B2B_Matrix_DB {
    const REMOVAL_NOTICE_META = '_b2b_matrix_removed_items';
    const DB_VERSION = '3.5.0';
    const DB_VERSION_OPTION = 'b2b_matrix_db_version';

    public static function init() {
        if ( function_exists( 'register_activation_hook' ) && defined( 'B2B_MATRIX_PLUGIN_FILE' ) ) {
            register_activation_hook( B2B_MATRIX_PLUGIN_FILE, [ __CLASS__, 'install' ] );
        }
        add_action( 'plugins_loaded', [ __CLASS__, 'check_schema' ] );
    }

    private static $locks = [];

    public static function maintenance_lock_name() {
        global $wpdb;
        return 'b2b_reset_' . md5( $wpdb->prefix );
    }

    public static function db_error() {
        return new WP_Error( 'database_error', 'Η αλλαγή δεν αποθηκεύτηκε. Δοκιμάστε ξανά.', [ 'status' => 500 ] );
    }

    public static function with_user_lock( $user_id, $callback ) {
        global $wpdb;
        $key = 'b2b_' . md5( $wpdb->prefix . ':' . (int) $user_id );
        if ( isset( self::$locks[ $key ] ) ) { return $callback(); }
        if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) !== '1' ) {
            return new WP_Error( 'list_busy', 'Η λίστα ενημερώνεται ήδη. Δοκιμάστε ξανά.', [ 'status' => 409 ] );
        }
        self::$locks[ $key ] = true;
        try {
            // Check after acquiring the account lock. Reset holds the maintenance
            // lock and checks existing account locks before touching either table.
            $owner = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', self::maintenance_lock_name() ) );
            if ( $wpdb->last_error ) { return self::db_error(); }
            if ( $owner !== null && (int) $owner > 0 ) {
                return new WP_Error( 'season_reset', 'Γίνεται καθαρισμός σεζόν. Περιμένετε λίγο και ανανεώστε τη σελίδα.', [ 'status' => 409 ] );
            }
            return $callback();
        }
        catch ( Throwable $e ) { return self::db_error(); }
        finally { unset( self::$locks[ $key ] ); $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
    }

    public static function ensure_editable( $user_id ) {
        if ( self::get_active_batch( $user_id ) ) {
            return new WP_Error( 'submission_active', 'Η λίστα βρίσκεται σε υποβολή. Συνεχίστε ή ακυρώστε την υποβολή από τη σελίδα λίστας.', [ 'status' => 409 ] );
        }
        return true;
    }

    public static function removal_notice( $user_id ) {
        $notice = get_user_meta( $user_id, self::removal_notice_key(), true );
        return is_array( $notice ) && isset( $notice['token'], $notice['items'] ) ? $notice : [ 'token' => '', 'items' => [] ];
    }
    public static function removal_notice_key() {
        global $wpdb;
        return self::REMOVAL_NOTICE_META . '_' . md5( $wpdb->prefix );
    }

    /** Caller holds the account lock. Journal first so a lost response cannot lose the notice. */
    public static function prune_cancelled_items( $user_id, $raw_items, $removed ) {
        global $wpdb;
        $notice = self::removal_notice( $user_id );
        $entries = $notice['items'];
        $current_ids = array_fill_keys( array_column( $raw_items, 'variation_id' ), true );
        // Discard staged notices for items since republished or re-added.
        foreach ( $entries as $vid => $entry ) {
            if ( isset( $current_ids[ $vid ] ) && ! isset( $removed[ $vid ] ) ) { unset( $entries[ $vid ] ); }
        }
        foreach ( $removed as $vid => $entry ) { $entries[ $vid ] = $entry; }
        if ( $entries !== $notice['items'] ) {
            $notice = [ 'token' => wp_generate_uuid4(), 'items' => $entries ];
            if ( ! update_user_meta( $user_id, self::removal_notice_key(), wp_slash( $notice ) ) ) { return self::db_error(); }
        }
        if ( $removed ) {
            $placeholders = implode( ',', array_fill( 0, count( $removed ), '%d' ) );
            $sql = $wpdb->prepare( 'DELETE FROM ' . self::table_items() . " WHERE user_id = %d AND variation_id IN ({$placeholders})", array_merge( [ $user_id ], array_keys( $removed ) ) );
            if ( false === $wpdb->query( $sql ) ) { return self::db_error(); }
        }
        return $notice;
    }

    public static function acknowledge_removals( $user_id, $token ) {
        return self::with_user_lock( $user_id, function() use ( $user_id, $token ) {
            $notice = self::removal_notice( $user_id );
            // A stale tab must never acknowledge a newer cancellation notice.
            if ( ! is_string( $token ) || ! $token || $notice['token'] !== $token ) { return true; }
            return delete_user_meta( $user_id, self::removal_notice_key() ) ? true : self::db_error();
        } );
    }

    // Caller holds the account lock. Clear and completion are committed together.
    public static function complete_batch( $user_id, $token ) {
        global $wpdb;
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::db_error(); }
        $deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_items() . ' WHERE user_id = %d', $user_id ) );
        if ( false === $deleted || is_wp_error( self::clear_draft_locked( $user_id ) ) || ! self::update_batch( $token, [ 'status' => 'complete' ] ) || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' ); return self::db_error();
        }
        delete_user_meta( $user_id, '_b2b_custom_order_list' );
        return true;
    }

    public static function table_items() {
        global $wpdb;
        return $wpdb->prefix . 'b2b_preorder_items';
    }

    public static function table_batches() {
        global $wpdb;
        return $wpdb->prefix . 'b2b_preorder_batches';
    }

    public static function table_drafts() {
        global $wpdb;
        return $wpdb->prefix . 'b2b_preorder_drafts';
    }

    /** Small, indexed account draft. Callers hold the account lock. */
    public static function get_draft( $user_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT customer_note, revision FROM ' . self::table_drafts() . ' WHERE user_id = %d', $user_id ), ARRAY_A );
        if ( $wpdb->last_error ) { return self::db_error(); }
        return [
            'note' => self::legacy_note_text( $row['customer_note'] ?? '' ),
            // The epoch invalidates even empty drafts held open across seasonal reset.
            'revision' => hash( 'sha256', $wpdb->prefix . ':' . (int) $user_id . ':' . ( $row['revision'] ?? 0 ) . ':' . get_option( 'b2b_matrix_draft_epoch', 'initial' ) ),
        ];
    }

    public static function sanitize_note( $note ) {
        if ( ! is_string( $note ) || strlen( $note ) > 16000 || preg_match( '/\A.{0,4000}\z/us', $note ) !== 1 ) {
            return new WP_Error( 'invalid_note', 'Το σχόλιο πρέπει να περιέχει έως 4.000 χαρακτήρες.', [ 'status' => 400 ] );
        }
        // Plain text, stored exactly as typed: "< 40 τεμ." or "10%20" must not change. Every output
        // escapes it (Vue text binding, WooCommerce wp_kses, PDF esc_html), so no HTML encoding here.
        $note = str_replace( [ "\r\n", "\r" ], "\n", $note );
        return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $note );
    }

    /** Drafts saved before 3.6.1 went through sanitize_textarea_field, which stored "<" as "&lt;". */
    private static function legacy_note_text( $note ) {
        return str_replace( '&lt;', '<', (string) $note );
    }

    public static function save_draft( $user_id, $note, $revision ) {
        $note = self::sanitize_note( $note );
        if ( is_wp_error( $note ) ) { return $note; }
        return self::with_user_lock( $user_id, function() use ( $user_id, $note, $revision ) {
            $editable = self::ensure_editable( $user_id );
            if ( is_wp_error( $editable ) ) { return $editable; }
            $draft = self::get_draft( $user_id );
            if ( is_wp_error( $draft ) ) { return $draft; }
            if ( ! is_string( $revision ) || ! hash_equals( $draft['revision'], $revision ) ) {
                return new WP_Error( 'note_conflict', 'Το σχόλιο άλλαξε από άλλη καρτέλα ή συσκευή. Ελέγξτε το αποθηκευμένο σχόλιο πριν συνεχίσετε.', [ 'status' => 409 ] );
            }
            if ( $draft['note'] !== $note ) {
                $saved = self::write_draft_locked( $user_id, $note );
                if ( is_wp_error( $saved ) ) { return $saved; }
            }
            return self::get_draft( $user_id );
        } );
    }

    private static function write_draft_locked( $user_id, $note ) {
        global $wpdb;
        $sql = $wpdb->prepare( 'INSERT INTO ' . self::table_drafts() . ' (user_id, customer_note, revision, updated_at) VALUES (%d, %s, 1, %s) ON DUPLICATE KEY UPDATE customer_note = VALUES(customer_note), revision = revision + 1, updated_at = VALUES(updated_at)', $user_id, $note, current_time( 'mysql' ) );
        return false === $wpdb->query( $sql ) ? self::db_error() : true;
    }

    /** Retain a revision tombstone, so a stale tab cannot resurrect an old note. */
    public static function clear_draft_locked( $user_id ) {
        return self::write_draft_locked( $user_id, '' );
    }

    public static function check_schema() {
        if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        if ( empty( $charset_collate ) ) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        $table_items = self::table_items();
        $table_batches = self::table_batches();
        $table_drafts = self::table_drafts();

        $sql = "CREATE TABLE {$table_items} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            variation_id BIGINT(20) UNSIGNED NOT NULL,
            qty INT(11) NOT NULL DEFAULT 0,
            price DECIMAL(10,2) NULL DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_variation (user_id, variation_id),
            KEY user_product (user_id, product_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB {$charset_collate};
        CREATE TABLE {$table_batches} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_token VARCHAR(64) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            wc_order_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            total_items INT(11) NOT NULL DEFAULT 0,
            processed_items INT(11) NOT NULL DEFAULT 0,
            error_message TEXT NULL DEFAULT NULL,
            snapshot LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY batch_token (batch_token),
            KEY user_status (user_id, status)
        ) ENGINE=InnoDB {$charset_collate};
        CREATE TABLE {$table_drafts} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            customer_note TEXT NOT NULL,
            revision BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        foreach ( [ $table_items, $table_batches, $table_drafts ] as $table ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return; }
            // Transactions are required for mixed deletes/upserts and finalization.
            $engine = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
            if ( strtolower( $engine['Engine'] ?? '' ) !== 'innodb' && false === $wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" ) ) { return; }
        }
        if ( ! $wpdb->get_var( "SHOW COLUMNS FROM `{$table_batches}` LIKE 'snapshot'" ) ) { return; }
        foreach ( [ 'user_id', 'customer_note', 'revision', 'updated_at' ] as $field ) {
            if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_drafts}` LIKE %s", $field ) ) ) { return; }
        }
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Retrieve quantities for a user and product.
     * Includes automatic transparent migration from legacy usermeta if found.
     *
     * @param int $user_id
     * @param int $product_id
     * @return array [ variation_id => qty, ... ]
     */
    public static function get_user_product_quantities( $user_id, $product_id ) {
        if ( (int) $user_id <= 0 ) { return []; }
        $result = self::with_user_lock( $user_id, function() use ( $user_id, $product_id ) {
            self::migrate_all_legacy( $user_id );
            return self::get_user_product_quantities_locked( $user_id, $product_id );
        } );
        if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
        return $result;
    }
    private static function get_user_product_quantities_locked( $user_id, $product_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $product_id = (int) $product_id;
        if ( $user_id <= 0 || $product_id <= 0 ) {
            return [];
        }

        $table = self::table_items();
        $query = $wpdb->prepare(
            "SELECT variation_id, qty FROM {$table} WHERE user_id = %d AND product_id = %d AND qty > 0",
            $user_id,
            $product_id
        );
        $rows = $wpdb->get_results( $query, ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'Αποτυχία ανάγνωσης λίστας.' ); }

        if ( ! empty( $rows ) ) {
            $result = [];
            foreach ( $rows as $row ) {
                $vid = (int) $row['variation_id'];
                $qty = (int) $row['qty'];
                if ( $vid > 0 && $qty > 0 ) {
                    $result[ $vid ] = $qty;
                }
            }
            return $result;
        }


        return [];
    }

    /**
     * Bulk save user product quantities via direct MySQL queries.
     * Deletes 0-quantity rows and upserts >0 rows in bulk.
     *
     * @param int $user_id
     * @param int $product_id
     * @param array $changed [ variation_id => new_qty, ... ]
     * @param array $all_updated [ variation_id => qty, ... ] Full state for reference
     * @return true|WP_Error
     */
    public static function save_user_product_quantities( $user_id, $product_id, $changed, $all_updated = [] ) {
        return self::with_user_lock( $user_id, function() use ( $user_id, $product_id, $changed, $all_updated ) {
            $editable = self::ensure_editable( $user_id );
            if ( is_wp_error( $editable ) ) { return $editable; }
            return self::save_user_product_quantities_locked( $user_id, $product_id, $changed, $all_updated );
        } );
    }

    private static function save_user_product_quantities_locked( $user_id, $product_id, $changed, $all_updated = [] ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $product_id = (int) $product_id;
        if ( $user_id <= 0 || $product_id <= 0 || empty( $changed ) ) {
            return true;
        }

        $table = self::table_items();
        $to_delete = [];
        $to_upsert = [];

        foreach ( $changed as $var_id => $qty ) {
            $var_id = (int) $var_id;
            $qty = (int) $qty;
            if ( $var_id <= 0 ) {
                continue;
            }
            if ( $qty <= 0 ) {
                $to_delete[] = $var_id;
            } else {
                $to_upsert[] = [ 'id' => $var_id, 'qty' => $qty ];
            }
        }

        $now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::db_error(); }
        // 1. Bulk Delete zeroed variations
        if ( ! empty( $to_delete ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%d' ) );
            $delete_sql = $wpdb->prepare(
                "DELETE FROM {$table} WHERE user_id = %d AND variation_id IN ({$placeholders})",
                array_merge( [ $user_id ], $to_delete )
            );
            $res = $wpdb->query( $delete_sql );
            if ( $res === false || ! empty( $wpdb->last_error ) ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'save_failed', 'Η διαγραφή ποσοτήτων απέτυχε.', [ 'status' => 500 ] );
            }
        }

        // 2. Bulk Upsert active variations
        if ( ! empty( $to_upsert ) ) {
            $row_placeholders = [];
            $values = [];
            foreach ( $to_upsert as $item ) {
                $row_placeholders[] = '(%d, %d, %d, %d, %s)';
                $values[] = $user_id;
                $values[] = $product_id;
                $values[] = $item['id'];
                $values[] = $item['qty'];
                $values[] = $now;
            }

            $upsert_sql = "INSERT INTO {$table} (user_id, product_id, variation_id, qty, updated_at) VALUES "
                . implode( ', ', $row_placeholders )
                . " ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = VALUES(updated_at)";

            $prepared = $wpdb->prepare( $upsert_sql, $values );
            $res = $wpdb->query( $prepared );
            if ( $res === false || ! empty( $wpdb->last_error ) ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'save_failed', 'Η αποθήκευση ποσοτήτων απέτυχε.', [ 'status' => 500 ] );
            }
        }

        if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return self::db_error(); }
        // Clean up legacy usermeta if exists
        if ( function_exists( 'delete_user_meta' ) ) {
            delete_user_meta( $user_id, '_b2b_preorder_items_' . $product_id );
        }

        return true;
    }

    /**
     * Retrieve all active pre-order lines for a user (for the My List / Custom Cart page).
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_all_items( $user_id ) {
        if ( (int) $user_id <= 0 ) { return []; }
        $result = self::with_user_lock( $user_id, function() use ( $user_id ) {
            self::migrate_all_legacy( $user_id );
            return self::get_user_all_items_locked( $user_id );
        } );
        if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
        return $result;
    }
    private static function get_user_all_items_locked( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return [];
        }
        $table = self::table_items();
        $query = $wpdb->prepare(
            "SELECT id, product_id, variation_id, qty, price, updated_at FROM {$table} WHERE user_id = %d AND qty > 0 ORDER BY product_id ASC, variation_id ASC",
            $user_id
        );
        $results = $wpdb->get_results( $query, ARRAY_A ) ?: [];
        if ( $wpdb->last_error ) { throw new RuntimeException( 'Αποτυχία ανάγνωσης λίστας.' ); }

        return $results;
    }

    /**
     * Delete all items of a parent product for a user.
     *
     * @param int $user_id
     * @param int $product_id
     * @return bool
     */
    public static function delete_user_product( $user_id, $product_id ) {
        return self::with_user_lock( $user_id, function() use ( $user_id, $product_id ) {
            $editable = self::ensure_editable( $user_id );
            if ( is_wp_error( $editable ) ) { return $editable; }
            self::migrate_all_legacy( $user_id );
            return self::delete_user_product_locked( $user_id, $product_id );
        } );
    }

    private static function delete_user_product_locked( $user_id, $product_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $product_id = (int) $product_id;
        if ( $user_id <= 0 || $product_id <= 0 ) {
            return false;
        }
        $table = self::table_items();
        $query = $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d AND product_id = %d", $user_id, $product_id );
        if ( false === $wpdb->query( $query ) ) { return self::db_error(); }
        delete_user_meta( $user_id, '_b2b_custom_order_list' );
        return true;
    }

    /**
     * Migrate items from legacy usermeta _b2b_custom_order_list into the custom table.
     *
     * @param int $user_id
     * @param array $legacy_cart [ variation_id => qty ]
     */
    public static function migrate_legacy_cart_list( $user_id, $legacy_cart ) {
        return self::with_user_lock( $user_id, function() use ( $user_id, $legacy_cart ) {
            self::import_legacy( $user_id, '_b2b_custom_order_list', $legacy_cart );
            return true;
        } );
    }
    private static function migrate_all_legacy( $user_id ) {
        if ( get_option( 'b2b_matrix_legacy_retired', false ) ) { return; }
        // Account lock is held. Never add data into a frozen submission snapshot.
        if ( self::get_active_batch( $user_id ) ) { return; }
        $metadata = get_user_meta( $user_id );
        foreach ( (array) $metadata as $key => $values ) {
            if ( $key !== '_b2b_custom_order_list' && ! preg_match( '/^_b2b_preorder_items_[0-9]+$/D', $key ) ) { continue; }
            $legacy = get_user_meta( $user_id, $key, true );
            if ( is_array( $legacy ) ) { self::import_legacy( $user_id, $key, $legacy ); }
        }
    }
    private static function import_legacy( $user_id, $key, $legacy ) {
        if ( get_option( 'b2b_matrix_legacy_retired', false ) ) { return; }
        global $wpdb;
        $rows = []; $values = [];
        foreach ( $legacy as $vid => $qty ) {
            if ( ! ctype_digit( (string) $vid ) || ! ctype_digit( (string) $qty ) || (int) $qty <= 0 ) { continue; }
            $product = wc_get_product( (int) $vid );
            $parent_id = $product && $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : 0;
            if ( ! $parent_id && preg_match( '/_items_([0-9]+)$/', $key, $match ) ) { $parent_id = (int) $match[1]; }
            // Preserve an orphan as an unavailable removable row instead of dropping it.
            if ( ! $parent_id ) { $parent_id = (int) $vid; }
            $rows[] = '(%d, %d, %d, %d, %s)';
            array_push( $values, (int) $user_id, $parent_id, (int) $vid, (int) $qty, gmdate( 'Y-m-d H:i:s' ) );
        }
        if ( $rows ) {
            $sql = 'INSERT INTO ' . self::table_items() . ' (user_id, product_id, variation_id, qty, updated_at) VALUES ' . implode( ', ', $rows ) . ' ON DUPLICATE KEY UPDATE variation_id = VALUES(variation_id)';
            if ( false === $wpdb->query( $wpdb->prepare( $sql, $values ) ) ) { throw new RuntimeException( 'Αποτυχία μεταφοράς παλιάς λίστας.' ); }
        }
        // Newer table values take precedence; remove metadata only after successful import.
        delete_user_meta( $user_id, $key );
    }

    /**
     * Total quantity and count of active items for a user.
     *
     * @param int $user_id
     * @return array [ 'total_items' => int, 'total_qty' => int ]
     */
    public static function get_user_summary( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return [ 'total_items' => 0, 'total_qty' => 0 ];
        }
        $table = self::table_items();
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as total_items, COALESCE(SUM(qty), 0) as total_qty FROM {$table} WHERE user_id = %d AND qty > 0",
            $user_id
        );
        $row = $wpdb->get_row( $query, ARRAY_A );
        return [
            'total_items' => (int) ( $row['total_items'] ?? 0 ),
            'total_qty'   => (int) ( $row['total_qty'] ?? 0 ),
        ];
    }

    /**
     * Clear all items for a user (e.g. after successful order submission).
     *
     * @param int $user_id
     * @return bool
     */
    public static function clear_user_items( $user_id ) {
        return self::with_user_lock( $user_id, function() use ( $user_id ) {
            $editable = self::ensure_editable( $user_id );
            if ( is_wp_error( $editable ) ) { return $editable; }
            self::migrate_all_legacy( $user_id );
            return self::clear_user_items_locked( $user_id );
        } );
    }

    private static function clear_user_items_locked( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return false;
        }
        $table = self::table_items();
        $query = $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id );
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::db_error(); }
        if ( false === $wpdb->query( $query ) || is_wp_error( self::clear_draft_locked( $user_id ) ) || false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' ); return self::db_error();
        }
        delete_user_meta( $user_id, '_b2b_custom_order_list' );
        return true;
    }

    /**
     * Create a new batch submission record.
     *
     * @param int $user_id
     * @param int $total_items
     * @return string|WP_Error batch_token
     */
    public static function create_batch( $user_id, $total_items, $snapshot = [] ) {
        global $wpdb;
        $user_id = (int) $user_id;
        $total_items = (int) $total_items;
        $token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
        $table = self::table_batches();
        $now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

        $inserted = $wpdb->insert(
            $table,
            [
                'batch_token'     => $token,
                'user_id'         => $user_id,
                'status'          => 'pending',
                'total_items'     => $total_items,
                'processed_items' => 0,
                'snapshot'        => wp_json_encode( $snapshot ),
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [ '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' ]
        );

        if ( ! $inserted || ! empty( $wpdb->last_error ) ) {
            return new WP_Error( 'batch_create_failed', 'Αποτυχία έναρξης μαζικής αποστολής.', [ 'status' => 500 ] );
        }
        return $token;
    }

    /**
     * Update an existing batch status / progress.
     *
     * @param string $batch_token
     * @param array $data
     * @return bool
     */
    public static function update_batch( $batch_token, $data ) {
        global $wpdb;
        $table = self::table_batches();
        $now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
        $data['updated_at'] = $now;

        $format = [];
        foreach ( $data as $key => $val ) {
            if ( in_array( $key, [ 'wc_order_id', 'total_items', 'processed_items' ], true ) ) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }

        $res = $wpdb->update( $table, $data, [ 'batch_token' => $batch_token ], $format, [ '%s' ] );
        return $res !== false && empty( $wpdb->last_error );
    }

    /**
     * Get batch record by token.
     *
     * @param string $batch_token
     * @return array|null
     */
    public static function get_batch( $batch_token ) {
        global $wpdb;
        $table = self::table_batches();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_token = %s LIMIT 1", $batch_token );
        return $wpdb->get_row( $query, ARRAY_A );
    }

    /**
     * Get active batch for a user (status not complete/failed within 1 hour).
     *
     * @param int $user_id
     * @return array|null
     */
    public static function get_active_batch( $user_id ) {
        global $wpdb;
        $user_id = (int) $user_id;
        if ( $user_id <= 0 ) {
            return null;
        }
        $table = self::table_batches();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND status IN ('pending', 'building', 'ready_to_finalize') ORDER BY id DESC LIMIT 1",
            $user_id
        );
        $batch = $wpdb->get_row( $query, ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'Αποτυχία ανάγνωσης υποβολής.' ); }
        if ( ! $batch ) {
            return null;
        }

        return $batch;
    }

    /**
     * Get batch record by WooCommerce order ID.
     *
     * @param int $order_id
     * @return array|null
     */
    public static function get_batch_by_order_id( $order_id ) {
        global $wpdb;
        $order_id = (int) $order_id;
        if ( $order_id <= 0 ) {
            return null;
        }
        $table = self::table_batches();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE wc_order_id = %d ORDER BY id DESC LIMIT 1", $order_id );
        return $wpdb->get_row( $query, ARRAY_A );
    }
}
