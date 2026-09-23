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

if ( empty( $payload_bare['no_frame'] ) || 12 !== (int) $payload_bare['border_width'] || empty( $payload_bare['frame_choice'] ) ) {
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

$eu_plain = APD_Formats::sanitize(
	array(
		'name' => 'Car without preset',
		'type' => 'eu_plain',
	)
);
$color_box = APD_Formats::default_text_box( 'color' );
$plain_box = APD_Formats::default_text_box( 'eu_plain' );

if ( is_wp_error( $eu_plain ) || 520 !== (int) $eu_plain['width'] || 110 !== (int) $eu_plain['height'] || ! empty( $eu_plain['band_box'] ) ) {
	fwrite( STDERR, "EU_PLAIN_SAVE_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'eu_plain' ) || ! APD_Formats::uses_painted_plate( 'eu_plain' ) || APD_Formats::uses_two_rows( 'eu_plain' ) || 'eu' !== APD_Formats::catalog_kind( 'eu_plain' ) ) {
	fwrite( STDERR, "EU_PLAIN_FLAGS_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::default_color_fields( 'eu_plain' ) !== APD_Formats::default_color_fields( 'color' ) || $plain_box !== $color_box ) {
	fwrite( STDERR, "EU_PLAIN_STUDIO_FAIL\n" );
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

$us_payload = APD_Formats::frontend_payload( $us );
if ( ! empty( $us_payload['frame_choice'] ) || APD_Formats::offers_frame_choice( 'us' ) || APD_Formats::offers_frame_choice( 'holder' ) || ! APD_Formats::offers_frame_choice( 'eu' ) ) {
	fwrite( STDERR, "US_FRAME_CHOICE_FAIL\n" );
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
		'name' => 'Moto 199x154',
		'type' => 'moto',
	)
);

if ( is_wp_error( $moto ) || 199 !== (int) $moto['width'] || 154 !== (int) $moto['height'] ) {
	fwrite( STDERR, 'MOTO_DEFAULT_SIZE_FAIL ' . wp_json_encode( $moto ) . PHP_EOL );
	exit( 1 );
}

if ( ! APD_Formats::uses_country_band( 'moto' ) || ! APD_Formats::uses_painted_plate( 'moto' ) || APD_Formats::uses_base_image( 'moto' ) ) {
	fwrite( STDERR, "MOTO_FLAGS_FAIL\n" );
	exit( 1 );
}

echo 'MOTO_TYPE_OK' . PHP_EOL;

$two_row_types = array( 'moto', 'moto_plain', 'suv', 'suv_eu' );
$single_types  = array( 'eu', 'eu_plain', 'us', 'color', 'custom', 'holder' );

foreach ( $two_row_types as $two_type ) {
	if ( ! APD_Formats::uses_two_rows( $two_type ) ) {
		fwrite( STDERR, "TWO_ROW_FLAG_FAIL {$two_type}\n" );
		exit( 1 );
	}
	$two_box = APD_Formats::sanitize_text_box( array(), $two_type );
	if ( ! isset( $two_box['letter_align'], $two_box['number_align'] ) || 'justify' !== $two_box['letter_align'] || 'justify' !== $two_box['number_align'] ) {
		fwrite( STDERR, 'TWO_ROW_ALIGN_FAIL ' . wp_json_encode( $two_box ) . PHP_EOL );
		exit( 1 );
	}
}

foreach ( $single_types as $single_type ) {
	if ( APD_Formats::uses_two_rows( $single_type ) ) {
		fwrite( STDERR, "SINGLE_ROW_FLAG_FAIL {$single_type}\n" );
		exit( 1 );
	}
}

$us_keeps_align = APD_Formats::sanitize_text_box(
	array(
		'align'        => 'justify',
		'letter_align' => 'justify',
	),
	'us'
);

if ( isset( $us_keeps_align['letter_align'] ) || 'center' !== $us_keeps_align['align'] ) {
	fwrite( STDERR, 'US_ROW_ALIGN_LEAK ' . wp_json_encode( $us_keeps_align ) . PHP_EOL );
	exit( 1 );
}

echo 'TWO_ROW_LAYOUT_OK' . PHP_EOL;

$moto_plain = APD_Formats::sanitize(
	array(
		'name' => 'Moto no preset',
		'type' => 'moto_plain',
	)
);

if ( is_wp_error( $moto_plain ) || 199 !== (int) $moto_plain['width'] || 154 !== (int) $moto_plain['height'] ) {
	fwrite( STDERR, 'MOTO_PLAIN_DEFAULT_SIZE_FAIL ' . wp_json_encode( $moto_plain ) . PHP_EOL );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'moto_plain' ) || ! APD_Formats::uses_painted_plate( 'moto_plain' ) || APD_Formats::uses_base_image( 'moto_plain' ) ) {
	fwrite( STDERR, "MOTO_PLAIN_FLAGS_FAIL\n" );
	exit( 1 );
}

if ( 'moto' !== APD_Formats::catalog_kind( 'moto_plain' ) ) {
	fwrite( STDERR, "MOTO_PLAIN_CATALOG_KIND_FAIL\n" );
	exit( 1 );
}

echo 'MOTO_PLAIN_TYPE_OK' . PHP_EOL;

$suv_eu = APD_Formats::sanitize(
	array(
		'name' => 'SUV EU',
		'type' => 'suv_eu',
	)
);

if ( is_wp_error( $suv_eu ) || 280 !== (int) $suv_eu['width'] || 200 !== (int) $suv_eu['height'] ) {
	fwrite( STDERR, 'SUV_EU_DEFAULT_SIZE_FAIL ' . wp_json_encode( $suv_eu ) . PHP_EOL );
	exit( 1 );
}

if ( abs( (float) $suv_eu['band_box']['height'] - 50 ) > 0.2 || abs( (float) $suv_eu['band_box']['y'] ) > 0.1 || abs( (float) $suv_eu['band_box']['width'] - 13 ) > 0.4 ) {
	fwrite( STDERR, 'SUV_EU_BAND_ROW_FAIL ' . wp_json_encode( $suv_eu['band_box'] ) . PHP_EOL );
	exit( 1 );
}

if ( (float) $suv_eu['text_box']['x'] > 8 || (float) $suv_eu['text_box']['width'] < 85 ) {
	fwrite( STDERR, 'SUV_EU_TEXT_SPAN_FAIL ' . wp_json_encode( $suv_eu['text_box'] ) . PHP_EOL );
	exit( 1 );
}

if ( abs( (float) $moto['band_box']['height'] - 100 ) > 0.2 ) {
	fwrite( STDERR, 'MOTO_BAND_STILL_FULL_FAIL ' . wp_json_encode( $moto['band_box'] ) . PHP_EOL );
	exit( 1 );
}

if ( ! APD_Formats::uses_country_band( 'suv_eu' ) || ! APD_Formats::uses_painted_plate( 'suv_eu' ) || APD_Formats::uses_base_image( 'suv_eu' ) ) {
	fwrite( STDERR, "SUV_EU_FLAGS_FAIL\n" );
	exit( 1 );
}

if ( 'suv' !== APD_Formats::catalog_kind( 'suv_eu' ) ) {
	fwrite( STDERR, "SUV_EU_CATALOG_KIND_FAIL\n" );
	exit( 1 );
}

$suv_eu_payload = APD_Formats::frontend_payload( $suv_eu );
if ( empty( $suv_eu_payload['capabilities']['country_band'] ) || empty( $suv_eu_payload['capabilities']['painted'] ) ) {
	fwrite( STDERR, "SUV_EU_PAYLOAD_CAPS_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::admin_sample_plate_text( 'eu' ) !== 'CA 1234' || APD_Formats::admin_sample_plate_text( 'moto' ) !== 'CA 1234' || APD_Formats::admin_sample_plate_text( 'suv' ) !== 'CAAA 1234' || APD_Formats::admin_sample_plate_text( 'suv_eu' ) !== 'CAAA 1234' ) {
	fwrite( STDERR, "ADMIN_SAMPLE_TEXT_FAIL\n" );
	exit( 1 );
}

if ( abs( APD_Formats::SAMPLE_FONT_FILL - 0.9 ) > 0.001 ) {
	fwrite( STDERR, "SAMPLE_FONT_FILL_FAIL\n" );
	exit( 1 );
}

echo 'ADMIN_STUDIO_SAMPLE_OK' . PHP_EOL;

$suv_missing = APD_Formats::sanitize(
	array(
		'name' => 'SUV type C',
		'type' => 'suv',
	)
);

if ( is_wp_error( $suv_missing ) || 0 !== (int) $suv_missing['base_image_id'] || array( 5, 4 ) !== APD_Formats::suv_row_limits( $suv_missing ) || 9 !== (int) $suv_missing['max_chars'] ) {
	fwrite( STDERR, "SUV_PHOTO_NOT_REQUIRED\n" );
	exit( 1 );
}

$suv_rows = APD_Formats::sanitize(
	array(
		'name'            => 'SUV row caps',
		'type'            => 'suv_eu',
		'max_chars_row_1' => 8,
		'max_chars_row_2' => 3,
	)
);
$suv_rows_payload = is_wp_error( $suv_rows ) ? array() : APD_Formats::frontend_payload( $suv_rows );
if ( is_wp_error( $suv_rows ) || array( 8, 3 ) !== $suv_rows_payload['row_max_chars'] || 11 !== (int) $suv_rows_payload['max_chars'] || "CA AA\n123" !== APD_Formats::limit_suv_rows( 'CA AAA', '12345', 5, 3 ) ) {
	fwrite( STDERR, "SUV_ROW_LIMITS_FAIL\n" );
	exit( 1 );
}

$suv_size = APD_Formats::default_size( 'suv' );
if ( 280 !== $suv_size['width'] || 200 !== $suv_size['height'] ) {
	fwrite( STDERR, "SUV_DEFAULT_SIZE_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::uses_base_image( 'suv' ) || APD_Formats::uses_country_band( 'suv' ) || ! APD_Formats::uses_painted_plate( 'suv' ) || APD_Formats::uses_plate_designs( 'suv' ) ) {
	fwrite( STDERR, "SUV_FLAGS_FAIL\n" );
	exit( 1 );
}

if ( array( 'text', 'border', 'background' ) !== APD_Formats::color_field_capabilities( 'suv' ) ) {
	fwrite( STDERR, "SUV_COLOR_CAPS_FAIL\n" );
	exit( 1 );
}

$preset_backup = APD_Plugin::get_settings();
$preset_probe  = $preset_backup;
$preset_probe['presets'][] = array(
	'id'                   => 'probe-eu-only',
	'name'                 => 'Probe',
	'country_code'         => 'DE',
	'active'               => 1,
	'allowed_format_types' => array( 'eu' ),
	'image_id'             => 0,
	'side'                 => 'left',
	'band_ratio'           => 0.08,
);
APD_Plugin::save_settings( $preset_probe );
$suv_eu_ids = wp_list_pluck( APD_Presets::active_for_format( 'suv_eu' ), 'id' );
$moto_ids   = wp_list_pluck( APD_Presets::active_for_format( 'moto' ), 'id' );
$plain_ids  = APD_Presets::active_for_format( 'suv' );
APD_Plugin::save_settings( $preset_backup );
if ( ! in_array( 'probe-eu-only', $suv_eu_ids, true ) || ! in_array( 'probe-eu-only', $moto_ids, true ) || ! empty( $plain_ids ) ) {
	fwrite( STDERR, "PRESET_ON_EU_BAND_FAIL\n" );
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
$holder_strip  = APD_Formats::holder_strip_box();
$holder_size   = APD_Formats::default_size( 'holder' );
if ( abs( $holder_region['y'] - $holder_strip['y'] ) > 0.1 || abs( $holder_region['height'] - $holder_strip['height'] ) > 0.1 ) {
	fwrite( STDERR, "HOLDER_REGION_FAIL\n" );
	exit( 1 );
}
if ( 520 !== (int) $holder_size['width'] || 260 !== (int) $holder_size['height'] || '' === APD_Formats::bundled_holder_image_url() ) {
	fwrite( STDERR, "HOLDER_STANDARD_FAIL\n" );
	exit( 1 );
}

echo 'CENTER_TEXT_BOX_OK' . PHP_EOL;

if ( 500 !== APD_Formats::CANVAS_DISPLAY_MAX_PX || 106 !== APD_Formats::CANVAS_DISPLAY_MAX_H_PX ) {
	fwrite( STDERR, "CANVAS_MAX_FAIL\n" );
	exit( 1 );
}

$eu_display    = APD_Formats::canvas_display_width( 520, 110 );
$moto_display  = APD_Formats::canvas_display_width( 199, 154 );
$us_display    = APD_Formats::canvas_display_width( 305, 152 );
$suv_display   = APD_Formats::canvas_display_width( 280, 200 );
$holder_display = APD_Formats::canvas_display_width( 520, 260 );
if ( 500 !== $eu_display || $eu_display !== $moto_display || $eu_display !== $us_display || $eu_display !== $suv_display || $eu_display !== $holder_display ) {
	fwrite( STDERR, "CANVAS_DISPLAY_FAIL eu={$eu_display} moto={$moto_display} us={$us_display}\n" );
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
