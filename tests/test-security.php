<?php
/**
 * Stage 2 smoke tests for APD_Security.
 *
 * Loads the sibling sandbox WordPress install and asserts reject/accept
 * behaviour. Run from the plugin root:
 *
 *     php tests/test-security.php
 *
 * @package Auto_Plate_Designer
 */

$candidates = array(
	dirname( __DIR__, 3 ) . '/wp-load.php',
	dirname( __DIR__, 2 ) . '/plate-designer/wp-load.php',
);

$wp_load = '';

foreach ( $candidates as $candidate ) {
	if ( is_readable( $candidate ) ) {
		$wp_load = $candidate;
		break;
	}
}

if ( '' === $wp_load ) {
	fwrite( STDERR, "WordPress sandbox not found\n" );
	exit( 1 );
}

if ( empty( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST']   = 'localhost';
	$_SERVER['REQUEST_URI'] = '/plate-designer/';
}

require_once $wp_load;

if ( ! class_exists( 'APD_Security' ) ) {
	fwrite( STDERR, "APD_Security was not loaded. Is the plugin active?\n" );
	exit( 1 );
}

$passed = 0;
$failed = 0;

/**
 * Assert a condition.
 *
 * @param bool   $ok      Condition.
 * @param string $message Label.
 */
function apd_assert( $ok, $message ) {
	global $passed, $failed;

	if ( $ok ) {
		++$passed;
		echo "PASS  {$message}\n";
		return;
	}

	++$failed;
	echo "FAIL  {$message}\n";
}

/**
 * Whether a validator returned WP_Error.
 *
 * @param mixed $result Validator result.
 * @return bool
 */
function apd_is_error( $result ) {
	return is_wp_error( $result );
}

apd_assert( true === APD_Security::validate_plate_text( 'PB1234CD' ), 'Accepts plain Latin plate text' );
apd_assert( true === APD_Security::validate_plate_text( 'CA-12.34' ), 'Accepts hyphen and period' );
apd_assert( true === APD_Security::validate_plate_text( "MUN\u{00DC}CHEN" ), 'Accepts Latin Extended letters' );
apd_assert( true === APD_Security::validate_plate_text( "\u{0411}\u{0413}1234" ), 'Accepts Cyrillic letters via code points' );

apd_assert( apd_is_error( APD_Security::validate_plate_text( '<script>alert(1)</script>' ) ), 'Rejects script HTML' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( 'PB1234<img src=x onerror=alert(1)>' ) ), 'Rejects inline HTML tags' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( "'; DROP TABLE wp_posts;--" ) ), 'Rejects SQL-like punctuation' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( '[gallery]' ) ), 'Rejects shortcode brackets' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( '' ) ), 'Rejects empty text by default' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( str_repeat( 'A', 501 ) ) ), 'Rejects text over the absolute max length' );
apd_assert( apd_is_error( APD_Security::validate_plate_text( "LINE1\nLINE2" ) ), 'Rejects newlines in single-line mode' );
apd_assert( true === APD_Security::validate_plate_text( "LINE1\nLINE2", array( 'multiline' => true ) ), 'Accepts newlines in multiline mode' );

$too_permissive = APD_Security::validate_admin_char_class( 'A-Za-z0-9<>' );
apd_assert( apd_is_error( $too_permissive ), 'Admin whitelist cannot add angle brackets' );

$empty_class = APD_Security::validate_admin_char_class( '' );
apd_assert( apd_is_error( $empty_class ), 'Admin whitelist cannot be empty' );

$ok_class = APD_Security::validate_admin_char_class( APD_Security::DEFAULT_ADMIN_CHAR_CLASS );
apd_assert( true === $ok_class, 'Default admin whitelist compiles and is not overly permissive' );

$ascii_only = APD_Security::validate_admin_char_class( 'A-Za-z0-9 \.\-' );
apd_assert( true === $ascii_only, 'Admin may tighten the whitelist to ASCII' );

apd_assert( true === APD_Security::validate_hex_color( '#1A2B3C' ), 'Accepts hex color' );
apd_assert( apd_is_error( APD_Security::validate_hex_color( 'red' ) ), 'Rejects named colors' );
apd_assert( apd_is_error( APD_Security::validate_hex_color( '#GGHHII' ) ), 'Rejects invalid hex' );

apd_assert( true === APD_Security::validate_layout( 'multiline_text' ), 'Accepts known layout' );
apd_assert( apd_is_error( APD_Security::validate_layout( 'custom_hack' ) ), 'Rejects unknown layout' );
apd_assert( true === APD_Security::validate_format_type( 'holder' ), 'Accepts holder format type' );
apd_assert( true === APD_Security::validate_format_type( 'eu' ), 'Accepts EU format type' );
apd_assert( true === APD_Security::validate_format_type( 'eu_plain' ), 'Accepts car plate without preset' );
apd_assert( true === APD_Security::validate_format_type( 'color' ), 'Accepts color format type' );
apd_assert( true === APD_Security::validate_format_type( 'moto' ), 'Accepts motorcycle format type' );
apd_assert( true === APD_Security::validate_format_type( 'moto_plain' ), 'Accepts motorcycle without preset' );
apd_assert( true === APD_Security::validate_format_type( 'suv' ), 'Accepts SUV format type' );
apd_assert( true === APD_Security::validate_format_type( 'suv_eu' ), 'Accepts EU SUV format type' );
apd_assert( apd_is_error( APD_Security::validate_format_type( 'pwn' ) ), 'Rejects unknown format type' );

$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );
apd_assert( true === APD_Security::validate_png_data_url( 'data:image/png;base64,' . base64_encode( $png ) ), 'Accepts PNG data URL' );
apd_assert( apd_is_error( APD_Security::validate_png_data_url( 'data:image/svg+xml;base64,PHN2Zz4=' ) ), 'Rejects non-PNG data URL' );

$tmp_png = tempnam( sys_get_temp_dir(), 'apd' );
$tmp_png_named = $tmp_png . '.png';
rename( $tmp_png, $tmp_png_named );
$tmp_png = $tmp_png_named;
file_put_contents( $tmp_png, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$png_file = array(
	'name'     => 'tiny.png',
	'type'     => 'image/gif',
	'tmp_name' => $tmp_png,
	'error'    => UPLOAD_ERR_OK,
	'size'     => strlen( $png ),
);
$png_ok = APD_Security::validate_upload(
	$png_file,
	array(
		'require_uploaded' => false,
		'max_width'        => 10,
		'max_height'       => 10,
	)
);
apd_assert( true === $png_ok, 'Accepts PNG upload when finfo MIME is image/png even if client type lies' );

$tmp_php = tempnam( sys_get_temp_dir(), 'apd' );
$tmp_php_named = $tmp_php . '.png';
rename( $tmp_php, $tmp_php_named );
$tmp_php = $tmp_php_named;
file_put_contents( $tmp_php, "<?php echo 'x';" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$php_file = array(
	'name'     => 'shell.png',
	'type'     => 'image/png',
	'tmp_name' => $tmp_php,
	'error'    => UPLOAD_ERR_OK,
	'size'     => filesize( $tmp_php ),
);
$php_result = APD_Security::validate_upload(
	$php_file,
	array(
		'require_uploaded' => false,
	)
);
apd_assert( apd_is_error( $php_result ), 'Rejects PHP payload with a PNG extension' );

$dirty_svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><foreignObject></foreignObject><rect width="10" height="10"/></svg>';
$clean_svg = APD_Security::sanitize_svg( $dirty_svg );
apd_assert( is_string( $clean_svg ), 'SVG sanitizer returns markup' );
apd_assert( is_string( $clean_svg ) && false === stripos( $clean_svg, '<script' ), 'SVG sanitizer strips script' );
apd_assert( is_string( $clean_svg ) && false === stripos( $clean_svg, 'onload' ), 'SVG sanitizer strips on* attributes' );
apd_assert( is_string( $clean_svg ) && false === stripos( $clean_svg, 'foreignObject' ), 'SVG sanitizer strips foreignObject' );

apd_assert( true === APD_Security::validate_country_code( 'A' ), 'Allows one-letter oval codes such as Austria A' );
apd_assert( true === APD_Security::validate_country_code( 'BG' ), 'Allows two-letter country codes' );
apd_assert( true === APD_Security::validate_country_code( 'SLO' ), 'Allows three-letter country codes' );
apd_assert( apd_is_error( APD_Security::validate_country_code( '' ) ), 'Rejects an empty country code' );
apd_assert( apd_is_error( APD_Security::validate_country_code( 'AUST' ) ), 'Rejects country codes longer than 3 letters' );
apd_assert( apd_is_error( APD_Security::validate_country_code( 'A', 2 ) ), 'US design codes still require at least 2 letters' );

$tmp_svg = tempnam( sys_get_temp_dir(), 'apd' );
$tmp_svg_named = $tmp_svg . '.svg';
rename( $tmp_svg, $tmp_svg_named );
$tmp_svg = $tmp_svg_named;
file_put_contents( $tmp_svg, $dirty_svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$svg_file = array(
	'name'     => 'flag.svg',
	'type'     => 'image/svg+xml',
	'tmp_name' => $tmp_svg,
	'error'    => UPLOAD_ERR_OK,
	'size'     => strlen( $dirty_svg ),
);
$svg_disallowed = APD_Security::validate_upload(
	$svg_file,
	array(
		'require_uploaded' => false,
		'allow_svg'        => false,
	)
);
apd_assert( apd_is_error( $svg_disallowed ), 'Rejects SVG when the caller did not opt in' );

$svg_allowed = APD_Security::validate_upload(
	$svg_file,
	array(
		'require_uploaded' => false,
		'allow_svg'        => true,
		'max_bytes'        => APD_Security::MAX_SVG_BYTES,
	)
);
apd_assert( true === $svg_allowed, 'Allows SVG when opted in after sanitization checks' );

@unlink( $tmp_png ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@unlink( $tmp_php ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@unlink( $tmp_svg ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo "\n{$passed} passed, {$failed} failed\n";

exit( $failed > 0 ? 1 : 0 );
