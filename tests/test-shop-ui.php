<?php
/**
 * Shop configurator markup: no storefront frame checkbox, border palettes, sample text.
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

if ( ! class_exists( 'APD_Formats' ) ) {
	fwrite( STDERR, "APD_Formats missing\n" );
	exit( 1 );
}

$format = APD_Formats::sanitize(
	array(
		'id'           => 'ui-eu',
		'name'         => 'EU UI',
		'type'         => 'eu',
		'width'        => 520,
		'height'       => 110,
		'border_width' => 8,
		'border_color' => '#000000',
		'band_box'     => array(
			'x'      => 0,
			'y'      => 0,
			'width'  => 7.7,
			'height' => 100,
		),
	)
);

if ( is_wp_error( $format ) ) {
	fwrite( STDERR, 'FORMAT_ERR ' . $format->get_error_message() . PHP_EOL );
	exit( 1 );
}

$i18n = array(
	'textLabel'     => 'Text',
	'fontLabel'     => 'Font',
	'countryLabel'  => 'Country band',
	'changeCountry' => 'Change country',
	'chooseCountry' => 'Choose country',
	'closeDialog'   => 'Close',
	'bandHint'      => 'Click the country band to change country',
	'designLabel'   => 'Plate design',
	'textColor'     => 'Text color',
	'borderColor'   => 'Border color',
	'plateColor'    => 'Plate color',
	'stripColor'    => 'White strip color',
	'frameLabel'    => 'Frame',
	'chars'         => '%1$s / %2$s characters',
	'invalid'       => 'Please enter valid plate text before adding to cart.',
);

$palettes = array(
	'text'       => array(
		array(
			'id'    => 'ink',
			'hex'   => '#000000',
			'label' => 'Black',
		),
	),
	'border'     => array(
		array(
			'id'    => 'frame',
			'hex'   => '#000000',
			'label' => 'Black',
		),
	),
	'background' => array(),
);

$payload = APD_Formats::frontend_payload( $format );
$payload['fonts']   = array();
$payload['presets'] = array();
$payload['designs'] = array();

$apd_payload = array(
	'format'       => $payload,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);

ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$html = ob_get_clean();

if ( false === strpos( $html, 'name="apd_frame"' ) || false === strpos( $html, 'checked' ) ) {
	fwrite( STDERR, "SHOP_FRAME_CHOICE_MISSING\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'CA 0909 BX' ) ) {
	fwrite( STDERR, "SHOP_DEFAULT_TEXT_FAIL\n" );
	exit( 1 );
}

$apd_payload['default_text'] = 'BG 1234 XX';
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$custom_html = ob_get_clean();

if ( false === strpos( $custom_html, 'BG 1234 XX' ) || false !== strpos( $custom_html, 'value="CA 0909 BX"' ) ) {
	fwrite( STDERR, "SHOP_PRODUCT_INITIAL_TEXT_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::sample_plate_text( 'eu' ) !== 'CA 0909 BX' || APD_Formats::sample_plate_text( 'us' ) !== 'TEXT' ) {
	fwrite( STDERR, "SAMPLE_PLATE_TEXT_FAIL\n" );
	exit( 1 );
}

echo 'SHOP_PRODUCT_INITIAL_TEXT_OK' . PHP_EOL;

if ( false === strpos( $html, 'name="apd_border_color"' ) ) {
	fwrite( STDERR, "SHOP_BORDER_SWATCH_MISSING\n" );
	exit( 1 );
}

if ( false === strpos( $html, '--apd-canvas-max: 500px' ) ) {
	fwrite( STDERR, "SHOP_CANVAS_MAX_MISSING\n" );
	exit( 1 );
}

echo 'SHOP_FRAMED_UI_OK' . PHP_EOL;

$bare = $format;
$bare['no_frame'] = true;
$bare_payload     = APD_Formats::frontend_payload( $bare );
$bare_payload['fonts']   = array();
$bare_payload['presets'] = array();
$bare_payload['designs'] = array();

$apd_payload = array(
	'format'       => $bare_payload,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);

ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$bare_html = ob_get_clean();

if ( ! preg_match( '/data-apd-frame-colors[^>]*hidden/', $bare_html ) ) {
	fwrite( STDERR, "SHOP_NO_FRAME_STILL_SHOWS_BORDER\n" );
	exit( 1 );
}

if ( false === strpos( $bare_html, 'name="apd_text"' ) ) {
	fwrite( STDERR, "SHOP_TEXT_FIELD_MISSING\n" );
	exit( 1 );
}

echo 'SHOP_NO_FRAME_HIDES_BORDER_OK' . PHP_EOL;

$us_format = APD_Formats::sanitize(
	array(
		'id'     => 'ui-us',
		'name'   => 'US UI',
		'type'   => 'us',
		'width'  => 305,
		'height' => 152,
	)
);
$us_ui = APD_Formats::frontend_payload( $us_format );
$us_ui['fonts']   = array();
$us_ui['presets'] = array();
$us_ui['designs'] = array();
$apd_payload = array(
	'format'       => $us_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$us_html = ob_get_clean();

if ( false !== strpos( $us_html, 'name="apd_frame"' ) || false !== strpos( $us_html, 'data-apd-frame-colors' ) ) {
	fwrite( STDERR, "SHOP_US_STILL_HAS_FRAME\n" );
	exit( 1 );
}

if ( false === strpos( $us_html, '--apd-frame-w: 500px' ) || false === strpos( $html, '--apd-frame-w: 500px' ) ) {
	fwrite( STDERR, "SHOP_FRAME_WIDTH_FAIL\n" );
	exit( 1 );
}

$apd_payload['default_text'] = 'CA 0909 BX';
$apd_payload['format']       = $payload;
$apd_payload['cart_restore'] = array(
	'text'       => 'XX 9999 YY',
	'text_color' => '#ff0000',
	'no_frame'   => true,
);
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$restore_html = ob_get_clean();

if ( false === strpos( $restore_html, 'XX 9999 YY' ) || false !== strpos( $restore_html, 'value="CA 0909 BX"' ) ) {
	fwrite( STDERR, "SHOP_CART_RESTORE_TEXT_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $restore_html, 'value="#ff0000"' ) || ! preg_match( '/data-apd-frame-colors[^>]*hidden/', $restore_html ) ) {
	fwrite( STDERR, "SHOP_CART_RESTORE_STYLE_FAIL\n" );
	exit( 1 );
}

$legacy_rows = APD_Formats::suv_plate_rows( 'CA 0909 BX' );
$typed_rows  = APD_Formats::suv_plate_rows( "AA 00\n1234" );
if ( array( 'CA BX', '0909' ) !== $legacy_rows || array( 'AA 00', '1234' ) !== $typed_rows || "AA 00\n1234" !== APD_Formats::join_suv_rows( 'AA 00', '1234' ) || "CA AA\n4935" !== APD_Formats::sample_plate_text( 'suv' ) || 'CA 0909 BX' !== APD_Formats::sample_plate_text( 'eu' ) ) {
	fwrite( STDERR, "SUV_ROW_SPLIT_FAIL\n" );
	exit( 1 );
}

$suv_format = APD_Formats::sanitize(
	array(
		'id'     => 'ui-suv',
		'name'   => 'SUV UI',
		'type'   => 'suv_eu',
		'width'  => 280,
		'height' => 200,
	)
);
if ( is_wp_error( $suv_format ) ) {
	fwrite( STDERR, 'SUV_FORMAT_ERR ' . $suv_format->get_error_message() . PHP_EOL );
	exit( 1 );
}
$suv_ui = APD_Formats::frontend_payload( $suv_format );
$suv_ui['fonts']   = array();
$suv_ui['presets'] = array();
$suv_ui['designs'] = array();
$apd_payload = array(
	'format'       => $suv_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border', 'background' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
	'default_text' => "CA AA\n4935",
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$suv_html = ob_get_clean();

if ( false === strpos( $suv_html, 'name="apd_text_row_1"' ) || false === strpos( $suv_html, 'name="apd_text_row_2"' ) || false === strpos( $suv_html, 'value="CA AA"' ) || false === strpos( $suv_html, 'value="4935"' ) || false === strpos( $suv_html, 'maxlength="5"' ) || false === strpos( $suv_html, 'maxlength="4"' ) || substr_count( $suv_html, 'data-apd-count' ) < 2 || false !== strpos( $html, 'name="apd_text_row_1"' ) ) {
	fwrite( STDERR, "SHOP_SUV_ROWS_FAIL\n" );
	exit( 1 );
}

$shop_js = file_get_contents( APD_PLUGIN_DIR . 'assets/js/configurator.js' );
if ( false === strpos( $shop_js, 'groups.join' ) || false === strpos( $shop_js, 'bindStayOnProduct' ) || false === strpos( $shop_js, "format.type === 'us'" ) || false === strpos( $shop_js, "new CustomEvent('wc-blocks_added_to_cart', { bubbles: true })" ) || false === strpos( $shop_js, 'placeBandInsideFrame' ) || false === strpos( $shop_js, 'apd-band-layer' ) || false === strpos( $shop_js, 'plateSnapshot' ) || false === strpos( $shop_js, 'drawImageCover' ) || false === strpos( $shop_js, 'rowsForPlate' ) || false === strpos( $shop_js, 'syncSuvRows' ) || false === strpos( $shop_js, 'rowLimit' ) || false === strpos( $shop_js, 'plateGlyphs' ) || false === strpos( $shop_js, 'clampBoxInsideFrame' ) ) {
	fwrite( STDERR, "SHOP_JS_RULES_FAIL\n" );
	exit( 1 );
}

echo 'SHOP_US_AND_RESTORE_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
