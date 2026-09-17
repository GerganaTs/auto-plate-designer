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
	'stripColor'    => 'Text background',
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

if ( false !== strpos( $html, 'name="apd_no_frame"' ) || false !== strpos( $html, 'data-apd-no-frame' ) ) {
	fwrite( STDERR, "SHOP_HAS_FRAME_CHECKBOX\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'CA 0909 BX' ) ) {
	fwrite( STDERR, "SHOP_DEFAULT_TEXT_FAIL\n" );
	exit( 1 );
}

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

if ( false !== strpos( $bare_html, 'name="apd_border_color"' ) ) {
	fwrite( STDERR, "SHOP_NO_FRAME_STILL_SHOWS_BORDER\n" );
	exit( 1 );
}

if ( false === strpos( $bare_html, 'name="apd_text"' ) ) {
	fwrite( STDERR, "SHOP_TEXT_FIELD_MISSING\n" );
	exit( 1 );
}

echo 'SHOP_NO_FRAME_HIDES_BORDER_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
