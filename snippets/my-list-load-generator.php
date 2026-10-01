<?php
/**
 * NEW standalone snippet, derived from the plugin's current schema and readers.
 * Code Snippets: paste without the opening <?php and run in the administration area.
 * Alternatively load this file as a small plugin (add a Plugin Name header).
 * WordPress Admin > Tools > B2B My List Load Test.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class B2B_My_List_Load_Generator {
    const PAGE = 'b2b-my-list-load-generator';

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Δεν έχετε πρόσβαση.', '', [ 'response' => 403 ] ); }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { wp_die( 'Χρειάζεται POST.', '', [ 'response' => 405 ] ); }
        check_admin_referer( self::PAGE );
        $result = self::fill( wp_unslash( $_POST ) );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), 'B2B My List Load Test', [ 'response' => 400, 'back_link' => true ] );
        }
        // Redirect before admin-page output; browser refresh cannot repeat the INSERT.
        wp_safe_redirect( add_query_arg( $result, admin_url( 'tools.php?page=' . self::PAGE ) ) );
        exit;
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Δεν έχετε πρόσβαση.' ); }
        $values = [ 'user_id' => get_current_user_id(), 'rows' => 1000, 'qty_min' => 1, 'qty_max' => 6, 'mode' => 'replace' ];
        ?>
        <div class="wrap">
            <h1>B2B My List — Load Test</h1>
            <p>Τυχαίες υπαρκτές παραλλαγές με SKU, τιμή καταλόγου και τυχαία ποσότητα. Ένα row = μία παραλλαγή στη βάση.</p>
            <p>Το My List ομαδοποιεί τις παραλλαγές ανά προϊόν, άρα οι ορατές κάρτες μπορεί να είναι λιγότερες από τα rows.</p>
            <?php if ( isset( $_GET['filled'], $_GET['total_rows'], $_GET['parents'], $_GET['target_user'] ) ) : ?>
                <div class="notice notice-success"><p><?php
                    printf( 'Προστέθηκαν %d rows. Η λίστα του user #%d έχει %d rows / %d προϊόντα. Άνοιξε το My List με αυτόν τον λογαριασμό.',
                        absint( $_GET['filled'] ), absint( $_GET['target_user'] ), absint( $_GET['total_rows'] ), absint( $_GET['parents'] ) );
                ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::PAGE ); ?>">
                <?php wp_nonce_field( self::PAGE ); ?>
                <table class="form-table"><tbody>
                    <?php foreach ( [ 'user_id' => 'User ID', 'rows' => 'Πόσα rows', 'qty_min' => 'Ελάχιστη ποσότητα / row', 'qty_max' => 'Μέγιστη ποσότητα / row' ] as $key => $label ) : ?>
                        <tr><th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                            <td><input required type="number" min="1" step="1" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $values[ $key ] ); ?>"></td></tr>
                    <?php endforeach; ?>
                    <tr><th><label for="mode">Τρόπος γεμίσματος</label></th><td>
                        <select id="mode" name="mode">
                            <option value="replace" <?php selected( $values['mode'], 'replace' ); ?>>Αντικατάσταση — ακριβώς τόσα rows συνολικά</option>
                            <option value="append" <?php selected( $values['mode'], 'append' ); ?>>Προσθήκη — τόσα επιπλέον νέα rows</option>
                        </select>
                        <p class="description">Η αντικατάσταση διαγράφει την προηγούμενη draft λίστα μόνο του επιλεγμένου χρήστη. Χρησιμοποίησε λογαριασμό δοκιμών.</p>
                    </td></tr>
                </tbody></table>
                <?php submit_button( 'Γέμισε το My List' ); ?>
            </form>
        </div>
        <?php
    }

    public static function fill( $input ) {
        if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'forbidden', 'Δεν έχετε πρόσβαση.' ); }
        foreach ( [ 'B2B_Matrix_DB', 'B2B_Matrix_API', 'B2B_Matrix_Catalog' ] as $class ) {
            if ( ! class_exists( $class ) ) { return new WP_Error( 'missing_plugin', 'Ενεργοποίησε το B2B Matrix και το WooCommerce.' ); }
        }
        if ( ! function_exists( 'wc_get_product' ) ) { return new WP_Error( 'missing_woocommerce', 'Το WooCommerce δεν είναι ενεργό.' ); }
        if ( ! method_exists( 'B2B_Matrix_DB', 'with_user_lock' ) || ! method_exists( 'B2B_Matrix_DB', 'ensure_editable' ) ) {
            return new WP_Error( 'old_plugin', 'Χρειάζεται η τρέχουσα έκδοση του B2B Matrix με κλείδωμα λίστας.' );
        }
        $numbers = [];
        foreach ( [ 'user_id', 'rows', 'qty_min', 'qty_max' ] as $key ) {
            $value = $input[ $key ] ?? null;
            if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $value ) || false === filter_var( $value, FILTER_VALIDATE_INT ) ) {
                return new WP_Error( 'invalid_input', 'Συμπλήρωσε θετικούς ακέραιους σε όλα τα αριθμητικά πεδία.' );
            }
            $numbers[ $key ] = (int) $value;
        }
        $mode = $input['mode'] ?? '';
        if ( ! in_array( $mode, [ 'replace', 'append' ], true ) || $numbers['qty_min'] > $numbers['qty_max'] || $numbers['qty_max'] > B2B_Matrix_API::MAX_QTY ) {
            return new WP_Error( 'invalid_input', 'Έλεγξε τον τρόπο γεμίσματος και το εύρος ποσοτήτων (έως ' . B2B_Matrix_API::MAX_QTY . ').' );
        }
        if ( ! get_user_by( 'id', $numbers['user_id'] ) ) { return new WP_Error( 'invalid_user', 'Το User ID δεν υπάρχει.' ); }

        // Product access and price filters must see the recipient, not the administrator.
        $actor = get_current_user_id();
        try {
            wp_set_current_user( $numbers['user_id'] );
            $permission = B2B_Matrix_API::check_read_permissions();
            if ( is_wp_error( $permission ) ) { return $permission; }
            return B2B_Matrix_DB::with_user_lock( $numbers['user_id'], function() use ( $numbers, $mode ) {
                return self::fill_locked( $numbers, $mode );
            } );
        } catch ( Throwable $e ) {
            return new WP_Error( 'fill_failed', 'Το γέμισμα απέτυχε. ' . $e->getMessage() );
        } finally {
            wp_set_current_user( $actor );
        }
    }

    private static function fill_locked( $numbers, $mode ) {
        global $wpdb;
        $uid = $numbers['user_id'];
        $count = $numbers['rows'];
        $table = B2B_Matrix_DB::table_items();
        $editable = B2B_Matrix_DB::ensure_editable( $uid );
        if ( is_wp_error( $editable ) ) { return $editable; }
        if ( $wpdb->last_error ) { throw new RuntimeException( 'Αποτυχία ελέγχου ενεργής υποβολής.' ); }
        $status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
        if ( ! $status || strtolower( $status['Engine'] ?? '' ) !== 'innodb' ) {
            return new WP_Error( 'invalid_table', 'Ο πίνακας της λίστας πρέπει να υπάρχει και να είναι InnoDB.' );
        }

        // Finish the plugin's legacy migration first, so old rows cannot reappear later.
        $existing = B2B_Matrix_DB::get_user_all_items( $uid );
        $before = count( $existing );
        unset( $existing );
        $exclude = $mode === 'append' ? $wpdb->prepare(
            "AND NOT EXISTS (SELECT 1 FROM {$table} li WHERE li.user_id = %d AND li.variation_id = v.ID)", $uid
        ) : '';
        // Shuffle IDs only; all inserts below are direct bulk SQL, not WC cart operations.
        $ids = $wpdb->get_col( "SELECT v.ID FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->posts} p ON p.ID = v.post_parent
            WHERE v.post_type = 'product_variation' AND v.post_status = 'publish'
              AND p.post_type = 'product' AND p.post_status = 'publish'
              {$exclude} ORDER BY RAND()" );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'Αποτυχία ανάγνωσης καταλόγου.' ); }
        $rows = [];
        $allowed = [];
        foreach ( array_chunk( $ids, 250 ) as $chunk ) {
            $cancelled = function_exists( 'jpd_get_canceled_items_bulk' ) ? (array) jpd_get_canceled_items_bulk( $chunk ) : [];
            $cancelled = array_fill_keys( array_map( 'intval', $cancelled ), true );
            foreach ( $chunk as $vid ) {
                $vid = (int) $vid;
                if ( isset( $cancelled[ $vid ] ) ) { continue; }
                $variation = wc_get_product( $vid );
                if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_status( 'edit' ) !== 'publish' || trim( (string) $variation->get_sku() ) === '' ) { continue; }
                $pid = (int) $variation->get_parent_id();
                if ( ! array_key_exists( $pid, $allowed ) ) {
                    $parent = B2B_Matrix_API::get_product( $pid );
                    $structure = is_wp_error( $parent ) ? $parent : B2B_Matrix_Catalog::structure( $parent );
                    $allowed[ $pid ] = [];
                    if ( ! is_wp_error( $structure ) ) {
                        foreach ( $structure['matrix'] as $color ) {
                            foreach ( $color as $cell ) { $allowed[ $pid ][ (int) $cell['id'] ] = true; }
                        }
                    }
                }
                $price = $variation->get_price();
                if ( ! isset( $allowed[ $pid ][ $vid ] ) || ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 ) { continue; }
                $rows[] = [ $pid, $vid, wp_rand( $numbers['qty_min'], $numbers['qty_max'] ) ];
                if ( count( $rows ) === $count ) { break 2; }
            }
        }
        if ( count( $rows ) < $count ) {
            return new WP_Error( 'not_enough_products', sprintf(
                'Ζήτησες %d rows, αλλά βρέθηκαν μόνο %d κατάλληλες %sπαραλλαγές με SKU και τιμή. Δεν έγινε γέμισμα ή διαγραφή. Μείωσε τον αριθμό.',
                $count, count( $rows ), $mode === 'append' ? 'νέες ' : ''
            ) );
        }

        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Αποτυχία έναρξης συναλλαγής.' ); }
        try {
            if ( $mode === 'replace' && false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $uid ) ) ) {
                throw new RuntimeException( 'Αποτυχία αντικατάστασης λίστας.' );
            }
            $now = current_time( 'mysql' );
            foreach ( array_chunk( $rows, 250 ) as $chunk ) {
                $tuples = $params = [];
                foreach ( $chunk as $row ) {
                    $tuples[] = '(%d, %d, %d, %d, NULL, %s)';
                    array_push( $params, $uid, $row[0], $row[1], $row[2], $now );
                }
                // The draft price column is intentionally NULL: My List reads live WC prices.
                $sql = "INSERT INTO {$table} (user_id, product_id, variation_id, qty, price, updated_at) VALUES " . implode( ', ', $tuples );
                if ( $wpdb->query( $wpdb->prepare( $sql, $params ) ) !== count( $chunk ) ) { throw new RuntimeException( 'Αποτυχία μαζικού INSERT.' ); }
            }
            $stats = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(*) AS total_rows, COUNT(DISTINCT product_id) AS parents FROM {$table} WHERE user_id = %d AND qty > 0", $uid
            ), ARRAY_A );
            $expected = $mode === 'replace' ? $count : $before + $count;
            if ( $wpdb->last_error || ! $stats || (int) $stats['total_rows'] !== $expected ) { throw new RuntimeException( 'Ο τελικός αριθμός rows δεν συμφωνεί. Έγινε rollback.' ); }
            if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Αποτυχία ολοκλήρωσης συναλλαγής.' ); }
            return [ 'filled' => $count, 'total_rows' => (int) $stats['total_rows'], 'parents' => (int) $stats['parents'], 'target_user' => $uid ];
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'fill_failed', 'Το γέμισμα ακυρώθηκε και η προηγούμενη λίστα διατηρήθηκε. ' . $e->getMessage() );
        }
    }
}

add_action( 'admin_menu', static function() {
    add_management_page( 'B2B My List Load Test', 'B2B My List Load Test', 'manage_options', B2B_My_List_Load_Generator::PAGE, [ 'B2B_My_List_Load_Generator', 'page' ] );
} );
add_action( 'admin_post_' . B2B_My_List_Load_Generator::PAGE, [ 'B2B_My_List_Load_Generator', 'handle' ] );
