<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class B2B_Matrix_Settings {
    const OPTION = 'b2b_matrix_appearance';
    const GROUP = 'b2b_matrix_settings';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function defaults() {
        return [
            'palette' => 'indigo',
            'custom_color' => '#4f46e5',
            'inherit_font' => true,
            'radius' => 'rounded',
            'lightbox' => 'site',
        ];
    }

    public static function palettes() {
        return [
            'indigo' => [ 'Indigo', '#4f46e5' ],
            'blue' => [ 'Blue', '#2563eb' ],
            'violet' => [ 'Violet', '#7c3aed' ],
            'teal' => [ 'Teal', '#0f766e' ],
            'slate' => [ 'Slate', '#334155' ],
        ];
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $settings = self::defaults();
        if ( isset( $input['palette'] ) && is_string( $input['palette'] ) ) {
            if ( isset( self::palettes()[ $input['palette'] ] ) || $input['palette'] === 'custom' ) {
                $settings['palette'] = $input['palette'];
            }
        }
        if ( isset( $input['custom_color'] ) && is_string( $input['custom_color'] ) && preg_match( '/^#([A-Fa-f0-9]{6})$/', $input['custom_color'] ) ) {
            $settings['custom_color'] = strtolower( $input['custom_color'] );
        }
        if ( isset( $input['radius'] ) && in_array( $input['radius'], [ 'rounded', 'square' ], true ) ) {
            $settings['radius'] = $input['radius'];
        }
        if ( array_key_exists( 'inherit_font', $input ) ) {
            $settings['inherit_font'] = ! in_array( $input['inherit_font'], [ false, 0, '0' ], true );
        }
        if ( isset( $input['lightbox'] ) && in_array( $input['lightbox'], [ 'site', 'plugin' ], true ) ) {
            $settings['lightbox'] = $input['lightbox'];
        }
        return $settings;
    }

    public static function get() {
        return self::sanitize( get_option( self::OPTION, self::defaults() ) );
    }

    /** Small optional integration surface; no frontend framework dependency. */
    public static function design_tokens() {
        $settings = self::get();
        $color = $settings['palette'] === 'custom' ? $settings['custom_color'] : self::palettes()[ $settings['palette'] ][1];
        $rgb = array_map( function( $channel ) {
            $value = hexdec( $channel ) / 255;
            return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
        }, str_split( substr( $color, 1 ), 2 ) );
        $luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
        return [
            'accent' => $color,
            'on_accent' => 1.05 / ( $luminance + 0.05 ) >= 4.5 ? '#ffffff' : '#000000',
            'focus' => $luminance > 0.3 ? '#334155' : $color,
            'card_radius' => $settings['radius'] === 'square' ? '0px' : '14px',
            'control_radius' => $settings['radius'] === 'square' ? '0px' : '8px',
        ];
    }

    public static function register() {
        register_setting( self::GROUP, self::OPTION, [
            'type' => 'array', 'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default' => self::defaults(), 'show_in_rest' => false,
        ] );
    }

    public static function add_menu() {
        add_menu_page( 'B2B Matrix', 'B2B Matrix', 'manage_options', 'b2b-matrix', [ __CLASS__, 'render' ], 'dashicons-screenoptions', 56 );
    }

    public static function enqueue( $hook ) {
        if ( $hook !== 'toplevel_page_b2b-matrix' ) { return; }
        $file = dirname( __DIR__ ) . '/assets/admin-settings.css';
        wp_enqueue_style( 'b2b-matrix-admin', plugins_url( 'assets/admin-settings.css', dirname( __DIR__ ) . '/b2b-matrix-api.php' ), [], filemtime( $file ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Δεν έχετε δικαίωμα διαχείρισης αυτών των ρυθμίσεων.' ); }
        $settings = self::get();
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) && in_array( $_GET['tab'], [ 'overview', 'appearance', 'data' ], true ) ? $_GET['tab'] : 'overview';
        ?>
        <div class="wrap b2b-settings">
            <h1>B2B Matrix</h1>
            <p class="b2b-settings-intro">Λίστες προπαραγγελίας · εμφάνιση και διαχείριση σεζόν</p>
            <nav class="b2b-admin-tabs" aria-label="Διαχείριση B2B Matrix">
                <?php foreach ( [ 'overview' => 'Επισκόπηση', 'appearance' => 'Εμφάνιση', 'data' => 'Πίνακες & σεζόν' ] as $key => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=b2b-matrix&tab=' . $key ) ); ?>" <?php if ( $tab === $key ) { echo 'aria-current="page"'; } ?>><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php settings_errors(); ?>
            <?php if ( $tab === 'overview' ) : ?>
                <section class="b2b-settings-card"><h2>Λίστες πελατών</h2><p>Κάθε συνδεδεμένος πελάτης αποθηκεύει τις επιλογές του από τον πίνακα προϊόντος ή το My List. Οι υποβολές δημιουργούνται σε διαδοχικά βήματα με δυνατότητα συνέχισης.</p></section>
                <div class="b2b-admin-grid">
                    <section class="b2b-settings-card"><h2>Εμφάνιση</h2><p>Χρώμα, γραμματοσειρά, γωνίες και άνοιγμα εικόνων.</p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=b2b-matrix&tab=appearance' ) ); ?>">Ρύθμιση εμφάνισης</a></section>
                    <section class="b2b-settings-card"><h2>Πίνακες & σεζόν</h2><p>Έλεγχος δομής και εγγραφών, καθώς και χειροκίνητος καθαρισμός στο τέλος της σεζόν.</p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=b2b-matrix&tab=data' ) ); ?>">Διαχείριση πινάκων</a></section>
                </div>
                <p class="description">Οι κατάλογοι είναι ορατοί σε επισκέπτες. Η αποθήκευση απαιτεί σύνδεση. Η ιδιωτική πρόσβαση σε ολόκληρο το site ρυθμίζεται από το αντίστοιχο plugin του καταστήματος.</p>
            <?php elseif ( $tab === 'data' ) : self::render_data(); else : ?>
            <form action="options.php" method="post">
                <?php settings_fields( self::GROUP ); ?>

                <fieldset class="b2b-settings-card">
                    <legend>Άνοιγμα εικόνων</legend>
                    <label><input type="radio" name="b2b_matrix_appearance[lightbox]" value="site" <?php checked( $settings['lightbox'], 'site' ); ?>> Lightbox του site / WooCommerce (προεπιλογή)</label>
                    <p class="description">Χρησιμοποιεί το διαθέσιμο WooCommerce PhotoSwipe. Αν δεν υπάρχει συμβατό lightbox στη σελίδα, ανοίγει αυτόματα το lightbox του plugin.</p>
                    <label><input type="radio" name="b2b_matrix_appearance[lightbox]" value="plugin" <?php checked( $settings['lightbox'], 'plugin' ); ?>> Lightbox του plugin</label>
                </fieldset>

                <fieldset class="b2b-settings-card">
                    <legend>Βασικό χρώμα</legend>
                    <p>Χρώμα για αποθήκευση, επιλογές και εστίαση. Η επιτυχία παραμένει πράσινη και τα σφάλματα κόκκινα.</p>
                    <div class="b2b-palette-options">
                        <?php foreach ( self::palettes() as $key => $palette ) : ?>
                            <label class="b2b-palette-choice">
                                <input type="radio" name="b2b_matrix_appearance[palette]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['palette'], $key ); ?>>
                                <span class="b2b-swatch" style="background-color:<?php echo esc_attr( $palette[1] ); ?>" aria-hidden="true"></span>
                                <span><?php echo esc_html( $palette[0] ); ?><?php if ( $key === 'indigo' ) : ?><small>Προεπιλογή</small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                        <label class="b2b-palette-choice">
                            <input type="radio" name="b2b_matrix_appearance[palette]" value="custom" <?php checked( $settings['palette'], 'custom' ); ?>>
                            <span class="b2b-swatch" id="b2b-custom-swatch" style="background-color:<?php echo esc_attr( $settings['custom_color'] ?: '#4f46e5' ); ?>" aria-hidden="true"></span>
                            <span>Προσαρμοσμένο HEX</span>
                        </label>
                    </div>
                    <div class="b2b-custom-color-wrap">
                        <label class="screen-reader-text" for="b2b_custom_color_picker">Επιλογή προσαρμοσμένου χρώματος</label>
                        <input type="color" id="b2b_custom_color_picker" value="<?php echo esc_attr( $settings['custom_color'] ?: '#4f46e5' ); ?>" onchange="document.getElementById('b2b_custom_color_hex').value = this.value; document.getElementById('b2b-custom-swatch').style.backgroundColor = this.value; document.querySelector('input[value=custom]').checked = true;">
                        <label class="screen-reader-text" for="b2b_custom_color_hex">Κωδικός χρώματος HEX</label>
                        <input type="text" id="b2b_custom_color_hex" name="b2b_matrix_appearance[custom_color]" value="<?php echo esc_attr( $settings['custom_color'] ?: '#4f46e5' ); ?>" pattern="^#([A-Fa-f0-9]{6})$" placeholder="#4f46e5" oninput="if(/^#([A-Fa-f0-9]{6})$/.test(this.value)){ document.getElementById('b2b_custom_color_picker').value = this.value; document.getElementById('b2b-custom-swatch').style.backgroundColor = this.value; document.querySelector('input[value=custom]').checked = true; }">
                        <small class="description">Επιλέξτε με το εργαλείο χρώματος ή πληκτρολογήστε κωδικό HEX (π.χ. #2563eb)</small>
                    </div>
                </fieldset>

                <fieldset class="b2b-settings-card">
                    <legend>Γραμματοσειρά</legend>
                    <label class="b2b-font-choice">
                        <input type="hidden" name="b2b_matrix_appearance[inherit_font]" value="0">
                        <input type="checkbox" role="switch" name="b2b_matrix_appearance[inherit_font]" value="1" aria-describedby="b2b-font-help" <?php checked( $settings['inherit_font'] ); ?>>
                        <span>Χρήση γραμματοσειράς του θέματος</span>
                    </label>
                    <p id="b2b-font-help">Ενεργό από προεπιλογή. Αν το απενεργοποιήσετε, χρησιμοποιείται η ενσωματωμένη system sans-serif γραμματοσειρά του matrix (Segoe UI / system font), χωρίς λήψη εξωτερικών fonts.</p>
                </fieldset>

                <fieldset class="b2b-settings-card">
                    <legend>Γωνίες</legend>
                    <div class="b2b-radius-options">
                        <label><input type="radio" name="b2b_matrix_appearance[radius]" value="rounded" <?php checked( $settings['radius'], 'rounded' ); ?>><span class="b2b-radius-sample" aria-hidden="true"></span><span>Στρογγυλεμένες<small>Τρέχον design · προεπιλογή</small></span></label>
                        <label><input type="radio" name="b2b_matrix_appearance[radius]" value="square" <?php checked( $settings['radius'], 'square' ); ?>><span class="b2b-radius-sample b2b-square" aria-hidden="true"></span><span>Τετράγωνες<small>Radius 0 · πίνακας, πεδία, κουμπιά και διάλογοι</small></span></label>
                    </div>
                </fieldset>

                <?php submit_button( 'Αποθήκευση εμφάνισης' ); ?>
                <p class="description">Οι ρυθμίσεις εφαρμόζονται σε όλους τους πίνακες του καταστήματος κατά την επόμενη φόρτωσή τους.</p>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_data() {
        $checked = isset( $_GET['health'] ) && $_GET['health'] === '1';
        $tables = $checked ? B2B_Matrix_Maintenance::health() : B2B_Matrix_Maintenance::schema();
        $last_reset = get_option( 'b2b_matrix_last_season_reset', '' );
        ?>
        <section class="b2b-settings-card">
            <h2>Κατάσταση πινάκων</h2>
            <p>Ο έλεγχος εξετάζει InnoDB, απαραίτητα πεδία και indexes. Με τον αναλυτικό έλεγχο μετριούνται οι εγγραφές και εντοπίζονται μη έγκυρες ποσότητες ή καταστάσεις υποβολής.</p>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=b2b-matrix&tab=data&health=1' ) ); ?>">Εκτέλεση ελέγχου υγείας</a>
            <p class="description">Ο έλεγχος είναι μόνο ανάγνωση. Εκτελείται όταν τον ζητάτε· δεν φορτώνει snapshots ούτε τρέχει στο frontend.</p>
        </section>
        <div class="b2b-admin-grid">
        <?php foreach ( $tables as $name => $table ) : $status = $table['status']; ?>
            <section class="b2b-settings-card"><h2><?php echo esc_html( $table['label'] ); ?></h2><code class="b2b-table-name"><?php echo esc_html( $name ); ?></code>
                <dl class="b2b-data-stats">
                    <dt>Engine</dt><dd><?php echo esc_html( $status['Engine'] ?? '—' ); ?></dd>
                    <dt>Εγγραφές<?php echo $checked ? '' : ' (εκτίμηση)'; ?></dt><dd><?php echo esc_html( $checked ? ( $table['rows'] ?? '—' ) : ( $status['Rows'] ?? '—' ) ); ?></dd>
                    <dt>Δεδομένα & indexes</dt><dd><?php echo esc_html( size_format( (int) ( $status['Data_length'] ?? 0 ) + (int) ( $status['Index_length'] ?? 0 ) ) ); ?></dd>
                    <?php if ( isset( $table['active'] ) ) : ?><dt>Ενεργές υποβολές</dt><dd><?php echo (int) $table['active']; ?></dd><?php endif; ?>
                    <?php if ( ! empty( $table['history'] ) ) : $history = $table['history']; ?>
                        <dt>Ολοκληρωμένες</dt><dd><?php echo (int) $history['complete']; ?></dd>
                        <dt>Αποτυχημένες</dt><dd><?php echo (int) $history['failed']; ?></dd>
                        <dt>Ακυρωμένες</dt><dd><?php echo (int) $history['cancelled']; ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ( ! empty( $table['history']['oldest'] ) ) : ?><p class="description">Πρώτη υποβολή: <?php echo esc_html( $table['history']['oldest'] ); ?><br>Τελευταία ενημέρωση: <?php echo esc_html( $table['history']['latest'] ); ?></p><?php endif; ?>
                <?php if ( $table['issues'] ) : ?><ul class="b2b-data-issues"><?php foreach ( $table['issues'] as $issue ) : ?><li><?php echo esc_html( $issue ); ?></li><?php endforeach; ?></ul>
                <?php else : ?><p class="b2b-data-ok"><?php echo $checked ? 'Ο έλεγχος ολοκληρώθηκε χωρίς ευρήματα.' : 'Η δομή είναι συμβατή.'; ?></p><?php endif; ?>
            </section>
        <?php endforeach; ?>
        </div>
        <section class="b2b-settings-card b2b-danger-zone">
            <h2>Τέλος σεζόν · καθαρισμός και των τριών πινάκων</h2>
            <p>Διαγράφει <strong>όλες τις λίστες όλων των πελατών</strong> , <strong>τα αποθηκευμένα σχόλια</strong> και <strong>όλο το ιστορικό batches</strong>, μαζί με snapshots, αποτυχίες και ακυρώσεις, μόνο για αυτό το site. Οι πίνακες και οι ρυθμίσεις τους διατηρούνται. Οι παραγγελίες WooCommerce παραμένουν.</p>
            <p>Μπορείτε να κρατήσετε δεδομένα για περισσότερες σεζόν και να καθαρίσετε όποτε επιλέξετε. Για σύγκριση μετά τη διαγραφή, κρατήστε πρώτα αντίγραφο βάσης. Δεν υπάρχει αυτόματος καθαρισμός ανά εξάμηνο ούτε αναίρεση.</p>
            <p>Οι ενεργές υποβολές πρέπει πρώτα να ολοκληρωθούν ή να ακυρωθούν από το My List. Ανοιχτές καρτέλες πελατών χρειάζονται ανανέωση μετά τον καθαρισμό. Παλαιά δεδομένα migration δεν θα εισαχθούν ξανά σε αυτό το site.</p>
            <?php if ( $last_reset ) : ?><p>Τελευταίος επιτυχής καθαρισμός: <strong><?php echo esc_html( $last_reset ); ?></strong></p><?php endif; ?>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <input type="hidden" name="action" value="b2b_matrix_reset_season">
                <?php wp_nonce_field( 'b2b_matrix_reset_season' ); ?>
                <label for="b2b-reset-confirmation">Πληκτρολογήστε <strong>CLEAR</strong> για να επιβεβαιώσετε τη διαγραφή</label>
                <div class="b2b-reset-controls"><input id="b2b-reset-confirmation" name="confirmation" type="text" pattern="CLEAR" required autocomplete="off" spellcheck="false"><button type="submit" class="button b2b-danger-button">Καθαρισμός σεζόν</button></div>
            </form>
        </section>
        <?php
    }
}
