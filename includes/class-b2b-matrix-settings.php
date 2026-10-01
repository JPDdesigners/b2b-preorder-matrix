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

    public static function register() {
        register_setting( self::GROUP, self::OPTION, [
            'type' => 'array', 'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default' => self::defaults(), 'show_in_rest' => false,
        ] );
    }

    public static function add_menu() {
        add_menu_page( 'B2B Matrix · Εμφάνιση', 'B2B Matrix', 'manage_options', 'b2b-matrix', [ __CLASS__, 'render' ], 'dashicons-screenoptions', 56 );
    }

    public static function enqueue( $hook ) {
        if ( $hook !== 'toplevel_page_b2b-matrix' ) { return; }
        $file = dirname( __DIR__ ) . '/assets/admin-settings.css';
        wp_enqueue_style( 'b2b-matrix-admin', plugins_url( 'assets/admin-settings.css', dirname( __DIR__ ) . '/b2b-matrix-api.php' ), [], filemtime( $file ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Δεν έχετε δικαίωμα διαχείρισης αυτών των ρυθμίσεων.' ); }
        $settings = self::get();
        ?>
        <div class="wrap b2b-settings">
            <h1>B2B Matrix</h1>
            <p class="b2b-settings-intro">Ρυθμίσεις εμφάνισης & λειτουργίας πίνακα λίστας παραγγελίας</p>
            <?php settings_errors(); ?>
            <form action="options.php" method="post">
                <?php settings_fields( self::GROUP ); ?>

                <fieldset class="b2b-settings-card">
                    <legend>Λίστα παραγγελίας</legend>
                    <p>Ο πίνακας είναι ορατός και σε επισκέπτες. Η αποθήκευση επιτρέπεται μόνο μετά τη σύνδεση, στην προσωπική λίστα του χρήστη. Η ιδιωτική πρόσβαση σε ολόκληρο το site ρυθμίζεται από το αντίστοιχο plugin του καταστήματος.</p>
                </fieldset>
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
                        <input type="color" id="b2b_custom_color_picker" value="<?php echo esc_attr( $settings['custom_color'] ?: '#4f46e5' ); ?>" onchange="document.getElementById('b2b_custom_color_hex').value = this.value; document.getElementById('b2b-custom-swatch').style.backgroundColor = this.value; document.querySelector('input[value=custom]').checked = true;">
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
        </div>
        <?php
    }
}
