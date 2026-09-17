<?php
/**
 * Format layout: no_frame, band_box, shop payload, and frame helpers.
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

$eu_ratio = APD_Formats::eu_band_ratio( 520, 110 );
$default  = APD_Formats::default_band_box( 520, 110, 'left' );

if ( abs( $eu_ratio - ( 40 / 520 ) ) > 0.0002 ) {
	fwrite( STDERR, "EU_MM_RATIO_FAIL\n" );
	exit( 1 );
}

if ( abs( $default['width'] - ( $eu_ratio * 100 ) ) > 0.2 || 0.0 !== $default['x'] || 100.0 !== $default['height'] ) {
	fwrite( STDERR, 'DEFAULT_BAND_BOX_FAIL ' . wp_json_encode( $default ) . PHP_EOL );
	exit( 1 );
}

$right = APD_Formats::band_box_from_ratio( $eu_ratio, 'right' );
if ( abs( ( $right['x'] + $right['width'] ) - 100 ) > 0.2 ) {
	fwrite( STDERR, "RIGHT_BAND_BOX_FAIL\n" );
	exit( 1 );
}

$clamped = APD_Formats::sanitize_band_box(
	array(
		'x'      => -10,
		'y'      => 90,
		'width'  => 80,
		'height' => 5,
	),
	520,
	110,
	'left'
);

if ( $clamped['x'] < 0 || $clamped['width'] > 25 || $clamped['height'] < 40 || ( $clamped['y'] + $clamped['height'] ) > 100 ) {
	fwrite( STDERR, 'BAND_BOX_CLAMP_FAIL ' . wp_json_encode( $clamped ) . PHP_EOL );
	exit( 1 );
}

echo 'BAND_BOX_SANITIZE_OK' . PHP_EOL;

$framed = APD_Formats::sanitize(
	array(
		'name'         => 'EU framed',
		'type'         => 'eu',
		'width'        => 520,
		'height'       => 110,
		'border_width' => 8,
		'border_color' => '#111111',
		'band_box'     => array(
			'x'      => 0,
			'y'      => 0,
			'width'  => 7.7,
			'height' => 100,
		),
	)
);

if ( is_wp_error( $framed ) || ! empty( $framed['no_frame'] ) || ! APD_Formats::uses_frame( $framed ) ) {
	fwrite( STDERR, "FRAMED_SANITIZE_FAIL\n" );
	exit( 1 );
}

if ( abs( (float) $framed['band_ratio'] - 0.077 ) > 0.002 || 'left' !== $framed['band_side'] ) {
	fwrite( STDERR, 'BAND_RATIO_FROM_BOX_FAIL ' . wp_json_encode( $framed ) . PHP_EOL );
	exit( 1 );
}

$bare = APD_Formats::sanitize(
	array(
		'name'         => 'EU bare',
		'type'         => 'eu',
		'no_frame'     => '1',
		'border_width' => 12,
		'border_color' => '#ff0000',
		'band_ratio'   => 0.15,
		'band_side'    => 'right',
	)
);

if ( is_wp_error( $bare ) || true !== $bare['no_frame'] || APD_Formats::uses_frame( $bare ) ) {
	fwrite( STDERR, "NO_FRAME_SANITIZE_FAIL\n" );
	exit( 1 );
}

if ( 12 !== (int) $bare['border_width'] ) {
	fwrite( STDERR, "NO_FRAME_KEEPS_WIDTH_FAIL\n" );
	exit( 1 );
}

if ( abs( (float) $bare['band_box']['width'] - 15 ) > 0.2 || 'right' !== $bare['band_side'] ) {
	fwrite( STDERR, 'LEGACY_RATIO_TO_BOX_FAIL ' . wp_json_encode( $bare['band_box'] ) . PHP_EOL );
	exit( 1 );
}

$payload_bare = APD_Formats::frontend_payload( $bare );
$payload_on   = APD_Formats::frontend_payload( $framed );

if ( empty( $payload_bare['no_frame'] ) || 0 !== (int) $payload_bare['border_width'] ) {
	fwrite( STDERR, "PAYLOAD_NO_FRAME_FAIL\n" );
	exit( 1 );
}

if ( ! empty( $payload_on['no_frame'] ) || 8 !== (int) $payload_on['border_width'] ) {
	fwrite( STDERR, "PAYLOAD_FRAME_FAIL\n" );
	exit( 1 );
}

if ( empty( $payload_on['band_box']['width'] ) || abs( (float) $payload_on['band_box']['width'] - 7.7 ) > 0.2 ) {
	fwrite( STDERR, 'PAYLOAD_BAND_BOX_FAIL ' . wp_json_encode( $payload_on['band_box'] ) . PHP_EOL );
	exit( 1 );
}

$color = APD_Formats::sanitize(
	array(
		'name'     => 'Color no band',
		'type'     => 'color',
		'no_frame' => 1,
	)
);

if ( is_wp_error( $color ) || ! empty( $color['band_box'] ) || true !== $color['no_frame'] ) {
	fwrite( STDERR, "COLOR_NO_BAND_FAIL\n" );
	exit( 1 );
}

$us = APD_Formats::sanitize(
	array(
		'name'     => 'US no frame ignored',
		'type'     => 'us',
		'no_frame' => 1,
	)
);

if ( is_wp_error( $us ) || ! empty( $us['no_frame'] ) || APD_Formats::uses_frame( $us ) ) {
	fwrite( STDERR, "US_NO_FRAME_IGNORED_FAIL\n" );
	exit( 1 );
}

$color_payload = APD_Formats::frontend_payload( $color );

if ( ! empty( $color_payload['presets'] ) || ! empty( $color_payload['band_box'] ) ) {
	fwrite( STDERR, "COLOR_PAYLOAD_BAND_FAIL\n" );
	exit( 1 );
}

$legacy_shop = APD_Formats::frontend_payload(
	array(
		'id'               => 'legacy-eu',
		'name'             => 'Legacy EU',
		'type'             => 'eu',
		'width'            => 520,
		'height'           => 110,
		'border_width'     => 8,
		'border_color'     => '#000000',
		'band_ratio'       => 0.15,
		'band_side'        => 'left',
		'base_image_id'    => 0,
		'font_ids'         => array(),
		'max_chars'        => 12,
		'price_adjustment' => 0,
	)
);

if ( empty( $legacy_shop['band_box']['width'] ) || abs( (float) $legacy_shop['band_box']['width'] - 7.7 ) > 0.3 ) {
	fwrite( STDERR, 'LEGACY_SHOP_BAND_FAIL ' . wp_json_encode( $legacy_shop['band_box'] ) . PHP_EOL );
	exit( 1 );
}

echo 'NO_FRAME_PAYLOAD_OK' . PHP_EOL;
echo 'LEGACY_EU_BAND_OK' . PHP_EOL;

$us_no_band = APD_Formats::sanitize(
	array(
		'name'   => 'USA passenger',
		'type'   => 'us',
		'width'  => 600,
		'height' => 300,
	)
);

if ( is_wp_error( $us_no_band ) ) {
	fwrite( STDERR, 'US_SAVE_WITHOUT_BAND_FAIL ' . $us_no_band->get_error_message() . PHP_EOL );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'us' ) || ! empty( $us_no_band['band_box'] ) ) {
	fwrite( STDERR, "US_STILL_HAS_BAND_BOX\n" );
	exit( 1 );
}

echo 'US_SAVE_WITHOUT_BAND_OK' . PHP_EOL;

$moto = APD_Formats::sanitize(
	array(
		'name' => 'Moto 24x13',
		'type' => 'moto',
	)
);

if ( is_wp_error( $moto ) || 240 !== (int) $moto['width'] || 130 !== (int) $moto['height'] ) {
	fwrite( STDERR, 'MOTO_DEFAULT_SIZE_FAIL ' . wp_json_encode( $moto ) . PHP_EOL );
	exit( 1 );
}

if ( ! APD_Formats::uses_country_band( 'moto' ) || ! APD_Formats::uses_painted_plate( 'moto' ) || APD_Formats::uses_base_image( 'moto' ) ) {
	fwrite( STDERR, "MOTO_FLAGS_FAIL\n" );
	exit( 1 );
}

echo 'MOTO_TYPE_OK' . PHP_EOL;

$suv_missing = APD_Formats::sanitize(
	array(
		'name' => 'SUV type C',
		'type' => 'suv',
	)
);

if ( ! is_wp_error( $suv_missing ) ) {
	fwrite( STDERR, "SUV_SHOULD_REQUIRE_IMAGE\n" );
	exit( 1 );
}

$suv_size = APD_Formats::default_size( 'suv' );
if ( 340 !== $suv_size['width'] || 200 !== $suv_size['height'] ) {
	fwrite( STDERR, "SUV_DEFAULT_SIZE_FAIL\n" );
	exit( 1 );
}

if ( ! APD_Formats::uses_base_image( 'suv' ) || APD_Formats::uses_country_band( 'suv' ) || APD_Formats::uses_painted_plate( 'suv' ) || APD_Formats::uses_plate_designs( 'suv' ) ) {
	fwrite( STDERR, "SUV_FLAGS_FAIL\n" );
	exit( 1 );
}

if ( array( 'text' ) !== APD_Formats::color_field_capabilities( 'suv' ) ) {
	fwrite( STDERR, "SUV_COLOR_CAPS_FAIL\n" );
	exit( 1 );
}

echo 'SUV_TYPE_OK' . PHP_EOL;

$eu_region = APD_Formats::text_area_region(
	array(
		'type'         => 'eu',
		'width'        => 520,
		'height'       => 110,
		'border_width' => 8,
		'no_frame'     => false,
		'band_box'     => APD_Formats::default_band_box( 520, 110, 'left' ),
	)
);

if ( $eu_region['x'] < 7 || $eu_region['width'] > 93 ) {
	fwrite( STDERR, 'EU_REGION_FAIL ' . wp_json_encode( $eu_region ) . PHP_EOL );
	exit( 1 );
}

$box = array(
	'x'      => 20,
	'y'      => 5,
	'width'  => 40,
	'height' => 30,
	'align'  => 'center',
	'valign' => 'middle',
);
$centered = APD_Formats::center_text_box( $box, $eu_region, 'eu' );
$mid_x    = $eu_region['x'] + ( $eu_region['width'] / 2 );
$mid_y    = $eu_region['y'] + ( $eu_region['height'] / 2 );

if ( abs( ( $centered['x'] + ( $centered['width'] / 2 ) ) - $mid_x ) > 0.6 || abs( ( $centered['y'] + ( $centered['height'] / 2 ) ) - $mid_y ) > 0.6 ) {
	fwrite( STDERR, 'CENTER_TEXT_BOX_FAIL ' . wp_json_encode( $centered ) . ' region=' . wp_json_encode( $eu_region ) . PHP_EOL );
	exit( 1 );
}

$holder_region = APD_Formats::text_area_region( array( 'type' => 'holder' ) );
if ( abs( $holder_region['y'] - 82 ) > 0.1 || abs( $holder_region['height'] - 18 ) > 0.1 ) {
	fwrite( STDERR, "HOLDER_REGION_FAIL\n" );
	exit( 1 );
}

echo 'CENTER_TEXT_BOX_OK' . PHP_EOL;

if ( 500 !== APD_Formats::CANVAS_DISPLAY_MAX_PX ) {
	fwrite( STDERR, "CANVAS_MAX_FAIL\n" );
	exit( 1 );
}

$admin_css = file_get_contents( APD_PLUGIN_DIR . 'assets/css/admin-settings.css' );
$shop_css  = file_get_contents( APD_PLUGIN_DIR . 'assets/css/configurator.css' );
$admin_js  = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );

if ( false === strpos( $admin_css, '--apd-canvas-max, 500px' ) || false === strpos( $shop_css, '--apd-canvas-max, 500px' ) ) {
	fwrite( STDERR, "CANVAS_CSS_CAP_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $admin_css, '.apd-metric-grid' ) || false === strpos( $admin_css, 'minmax(11.5rem, 1fr)' ) ) {
	fwrite( STDERR, "ADMIN_METRIC_GRID_CSS_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $admin_js, 'input.disabled = !enabled' ) || false === strpos( $admin_js, 'centerBoxInRegion' ) ) {
	fwrite( STDERR, "ADMIN_JS_DISABLE_OR_CENTER_FAIL\n" );
	exit( 1 );
}

$formats_tab = file_get_contents( APD_PLUGIN_DIR . 'templates/admin/formats-tab.php' );
if ( preg_match( '/name="apd_format\[band_ratio\]"[^>]*type="number"/', $formats_tab ) || preg_match( '/type="number"[^>]*name="apd_format\[band_ratio\]"/', $formats_tab ) ) {
	fwrite( STDERR, "BAND_RATIO_STILL_NUMBER_INPUT\n" );
	exit( 1 );
}

echo 'CANVAS_AND_VALIDATION_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
