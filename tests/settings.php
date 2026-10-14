<?php
// Settings contract/security tests; uses no live WordPress installation or options table.
define( 'ABSPATH', __DIR__ );
$options = [];
$can_manage = true;
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function register_setting( $group, $name, $args ) { $GLOBALS['registration'] = compact( 'group', 'name', 'args' ); }
function add_menu_page( ...$args ) { $GLOBALS['menu'] = $args; }
function current_user_can( $cap ) { return $cap === 'manage_options' && $GLOBALS['can_manage']; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function settings_fields( $group ) { echo '<input type="hidden" name="_wpnonce" value="test-nonce"><input type="hidden" name="option_page" value="' . $group . '">'; }
function settings_errors() {}
function submit_button( $label ) { echo '<p><button class="button-primary" type="submit">' . $label . '</button></p>'; }
function checked( $a, $b = true ) { if ( (string) $a === (string) $b ) { echo ' checked="checked"'; } }
function esc_attr( $v ) { return htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return esc_attr( $v ); }
function esc_url( $v ) { return esc_attr( $v ); }
function admin_url( $path ) { if (!empty($GLOBALS['preview']) && preg_match('/tab=([a-z]+)/', $path, $match)) return 'settings-'.$match[1].'.html'; return '/wp-admin/' . $path; }
function size_format( $value ) { return round($value / 1024 / 1024, 2) . ' MB'; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="fixture">'; }
$_GET['tab'] = 'appearance';
require __DIR__ . '/../includes/class-b2b-matrix-settings.php';
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
// Render the real PHP form for visual inspection without a WordPress database.
if ( in_array( '--preview', $argv ?? [], true ) ) {
    $GLOBALS['preview']=true;
    foreach ($argv as $arg) { if (preg_match('/^--tab=(overview|appearance|data)$/', $arg, $match)) $_GET['tab']=$match[1]; }
    class B2B_Matrix_Maintenance {
        static function schema() { return [
            'wp_b2b_preorder_items' => ['label'=>'My List','status'=>['Engine'=>'InnoDB','Rows'=>8157,'Data_length'=>1048576,'Index_length'=>524288],'issues'=>[]],
            'wp_b2b_preorder_batches' => ['label'=>'Υποβολές / batches','status'=>['Engine'=>'InnoDB','Rows'=>120,'Data_length'=>3145728,'Index_length'=>65536],'issues'=>[]],
            'wp_b2b_preorder_drafts' => ['label'=>'Σχόλια προσχεδίου','status'=>['Engine'=>'InnoDB','Rows'=>530,'Data_length'=>65536,'Index_length'=>0],'issues'=>[]],
        ]; }
        static function health() { return self::schema(); }
    }
    ob_start(); B2B_Matrix_Settings::render(); $form = ob_get_clean();
    $form = preg_replace( '/<form action="[^"]*" method="post">/', '<form onsubmit="event.preventDefault()">', $form );
    $form = str_replace( 'type="submit"', 'type="submit" disabled title="Η αποθήκευση λειτουργεί μόνο μέσα στο WordPress"', $form );
    $css = file_get_contents( __DIR__ . '/../assets/admin-settings.css' );
    $js = file_get_contents( __DIR__ . '/../assets/admin-settings.js' );
    $css .= '.screen-reader-text{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}';
    echo '<!doctype html><html lang="el"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>B2B Matrix · Settings preview</title><style>body{background:#f0f0f1;font:13px system-ui;margin:24px}button,input{font:inherit}button{border:1px solid;border-radius:4px}.b2b-settings{margin:auto}h1{font-size:26px}.button{display:inline-block;padding:10px 14px;line-height:20px;text-decoration:none;border:1px solid #b4b4b8;border-radius:4px;color:#334155;background:#f6f7f7}button:disabled{opacity:.6}input[type=text]{border:1px solid #b4b4b8;border-radius:4px}@media(max-width:600px){body{margin:12px}}</style><style>' . $css . '</style><p style="text-align:center">Τοπική προεπισκόπηση · εικονικά δεδομένα · οι ενέργειες είναι απενεργοποιημένες.</p>' . $form . '<script>' . $js . '</script></html>';
    exit;
}
$tests = [
    'shared tokens keep a readable foreground for pale custom colors' => function() {
        $GLOBALS['options'][B2B_Matrix_Settings::OPTION] = [ 'palette' => 'custom', 'custom_color' => '#ffffff', 'radius' => 'square' ];
        $tokens = B2B_Matrix_Settings::design_tokens();
        check($tokens['on_accent'] === '#000000' && $tokens['focus'] === '#334155' && $tokens['card_radius'] === '0px', 'Unreadable shared appearance');
    },
    'overview has separate native navigation and no settings form' => function() {
        unset($_GET['tab']);
        ob_start(); B2B_Matrix_Settings::render(); $html = ob_get_clean();
        $_GET['tab'] = 'appearance';
        check(strpos($html, 'aria-current="page"') !== false && strpos($html, 'Πίνακες &amp; σεζόν') !== false && strpos($html, '<form') === false, 'Dashboard navigation failed');
    },
    'first install defaults to indigo, inherited font, rounded corners' => function() {
        check( B2B_Matrix_Settings::get() === [ 'palette' => 'indigo', 'custom_color' => '#4f46e5', 'inherit_font' => true, 'radius' => 'rounded', 'lightbox' => 'site' ], 'Incorrect defaults' );
    },
    'settings form off value survives sanitization and a simulated options round trip' => function() {
        $GLOBALS['options'][B2B_Matrix_Settings::OPTION] = B2B_Matrix_Settings::sanitize( [ 'palette' => 'violet', 'custom_color' => '#7c3aed', 'inherit_font' => '0', 'radius' => 'square', 'lightbox' => 'plugin' ] );
        check( B2B_Matrix_Settings::get() === [ 'palette' => 'violet', 'custom_color' => '#7c3aed', 'inherit_font' => false, 'radius' => 'square', 'lightbox' => 'plugin' ], 'Lost saved choices' );
    },
    'malicious nested and arbitrary CSS values never enter output' => function() {
        foreach ( [ false, 'bad', [ 'palette' => ['indigo'], 'radius' => ['square'] ], [ 'palette' => 'red;}</style><script>alert(1)</script>', 'radius' => '100px', 'other' => 'injected' ] ] as $input ) {
            check( B2B_Matrix_Settings::sanitize( $input ) === B2B_Matrix_Settings::defaults(), 'Unsafe value escaped allowlist' );
        }
    },
    'lightbox is allowlisted and legacy login setting no longer hides guest catalog' => function() {
        check(B2B_Matrix_Settings::sanitize(['lightbox'=>'javascript:bad','require_login'=>true])['lightbox']==='site','Invalid lightbox');
        check(!array_key_exists('require_login',B2B_Matrix_Settings::sanitize(['require_login'=>true])),'Legacy restriction survived');
    },
    'every advertised palette can be saved' => function() {
        foreach ( B2B_Matrix_Settings::palettes() as $key => $value ) {
            check( B2B_Matrix_Settings::sanitize( [ 'palette' => $key ] )['palette'] === $key, 'Unsaveable palette' );
        }
        check( B2B_Matrix_Settings::sanitize( [ 'palette' => 'custom', 'custom_color' => '#10B981' ] )['custom_color'] === '#10b981', 'Unsaveable custom hex' );
    },
    'registration uses a sanitizer and no public REST write surface' => function() {
        B2B_Matrix_Settings::register();
        $r = $GLOBALS['registration'];
        check( $r['group'] === 'b2b_matrix_settings' && $r['args']['show_in_rest'] === false && is_callable( $r['args']['sanitize_callback'] ), 'Unsafe registration' );
        B2B_Matrix_Settings::add_menu();
        check( $GLOBALS['menu'][2] === 'manage_options', 'Incorrect capability' );
    },
    'unauthorized direct page rendering stops before any form output' => function() {
        $GLOBALS['can_manage'] = false;
        ob_start();
        $denied = false;
        try { B2B_Matrix_Settings::render(); } catch ( RuntimeException $e ) { $denied = true; }
        $html = ob_get_clean();
        $GLOBALS['can_manage'] = true;
        check( $denied && $html === '', 'Unauthorized page content' );
    },
    'rendered form uses native options handler, nonce fields, hidden off value, saved choices and external JS' => function() {
        $GLOBALS['options'][B2B_Matrix_Settings::OPTION] = [ 'palette' => 'blue', 'inherit_font' => false, 'radius' => 'square' ];
        ob_start(); B2B_Matrix_Settings::render(); $html = ob_get_clean();
        check( strpos( $html, 'action="options.php" method="post"' ) !== false && strpos( $html, '_wpnonce' ) !== false, 'No native save protection' );
        check( strpos( $html, 'type="hidden" name="b2b_matrix_appearance[inherit_font]" value="0"' ) !== false, 'Missing unchecked value' );
        check( preg_match( '/value="square"\s+checked/', $html ) === 1 && preg_match( '/value="blue"\s+checked/', $html ) === 1, 'Saved selection lost' );
        check( strpos( $html, 'onchange=' ) === false && strpos( $html, 'oninput=' ) === false, 'Inline JS attributes still present' );
    },
    'season reset success and failure query flags render accessible admin notices' => function() {
        $_GET['reset'] = 'done';
        ob_start(); B2B_Matrix_Settings::render(); $doneHtml = ob_get_clean();
        check( strpos( $doneHtml, 'notice-success' ) !== false && strpos( $doneHtml, 'ολοκληρώθηκε επιτυχώς' ) !== false, 'Missing reset success notice' );
        $_GET['reset'] = 'failed';
        $_GET['reason'] = 'active_batches';
        ob_start(); B2B_Matrix_Settings::render(); $failHtml = ob_get_clean();
        unset( $_GET['reset'], $_GET['reason'] );
        check( strpos( $failHtml, 'notice-error' ) !== false && strpos( $failHtml, 'ενεργές υποβολές' ) !== false, 'Missing reset error notice' );
    },
];
$failed = 0;
foreach ( $tests as $name => $test ) {
    $GLOBALS['options'] = [];
    try {
        $test();
        echo "PASS $name\n";
    } catch ( Throwable $e ) {
        $failed++;
        echo "FAIL $name: " . $e->getMessage() . "\n";
    }
}
echo count( $tests ) . " tests; $failed failures\n";
exit( $failed ? 1 : 0 );
