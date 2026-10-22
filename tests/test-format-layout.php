<?php
/**
 * Format layout: no_frame, band_box, shop payload, and frame helpers.
 *
 * @package Auto_Plate_Designer
 */

$candidates = array(
	dirname( __DIR__, 4 ) . '/wp-load.php',
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

if ( 5 !== (int) $moto['max_chars_row_1'] || 4 !== (int) $moto['max_chars_row_2'] || 9 !== (int) $moto['max_chars'] || '5 / 4' !== APD_Formats::max_chars_label( $moto ) || "CA\n1234" !== APD_Formats::sample_plate_text( 'moto' ) ) {
	fwrite( STDERR, 'MOTO_ROW_LIMITS_FAIL ' . wp_json_encode( $moto ) . PHP_EOL );
	exit( 1 );
}

echo 'MOTO_TYPE_OK' . PHP_EOL;

$two_row_types = array( 'moto', 'moto_240', 'moto_plain', 'moto_plain_240', 'suv', 'suv_340', 'suv_eu', 'suv_eu_340', 'eu_300' );
$single_types  = array( 'eu', 'eu_plain', 'us', 'color', 'custom', 'holder', 'holder_moto', 'holder_d', 'atyp_300', 'fr', 'fr_ww', 'fr_region', 'austria', 'uk', 'uk_plain', 'mini', 'mini_plain', 'super_mini', 'super_mini_plain' );

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

$eu_300 = APD_Formats::sanitize(
	array(
		'name'             => 'EU 300',
		'type'             => 'eu_300',
		'width'            => 520,
		'height'           => 110,
		'max_chars_row_1'  => 6,
		'max_chars_row_2'  => 5,
		'text_rows'        => '1',
	)
);
$eu_region = is_wp_error( $eu_300 ) ? array() : APD_Formats::text_area_region( $eu_300 );
$eu_box    = is_wp_error( $eu_300 ) ? array() : $eu_300['text_box'];
$eu_band   = is_wp_error( $eu_300 ) ? array() : $eu_300['band_box'];
$eu_payload = is_wp_error( $eu_300 ) ? array() : APD_Formats::frontend_payload( $eu_300 );
if (
	is_wp_error( $eu_300 )
	|| 300 !== (int) $eu_300['width']
	|| 150 !== (int) $eu_300['height']
	|| isset( $eu_300['text_rows'] )
	|| ! APD_Formats::uses_two_rows( 'eu_300' )
	|| ! APD_Formats::uses_country_band( 'eu_300' )
	|| APD_Formats::uses_palette_slots( 'eu_300' )
	|| APD_Formats::is_suv_euro( 'eu_300' )
	|| 6 !== (int) $eu_300['max_chars_row_1']
	|| 5 !== (int) $eu_300['max_chars_row_2']
	|| 11 !== (int) $eu_300['max_chars']
	|| '6 / 5' !== APD_Formats::max_chars_label( $eu_300 )
	|| empty( $eu_payload['capabilities']['two_row'] )
	|| empty( $eu_payload['capabilities']['country_band'] )
	|| 100.0 !== (float) $eu_band['height']
	|| (float) $eu_band['width'] < 17 || (float) $eu_band['width'] > 20
	|| ! isset( $eu_box['letter_align'], $eu_box['number_align'] )
	|| 'justify' !== $eu_box['letter_align']
	|| (float) $eu_box['x'] + 0.05 < (float) $eu_region['x']
	|| (float) $eu_box['x'] + (float) $eu_box['width'] > (float) $eu_region['x'] + (float) $eu_region['width'] + 0.05
	|| (float) $eu_box['y'] + 0.05 < (float) $eu_region['y']
	|| (float) $eu_box['y'] + (float) $eu_box['height'] > (float) $eu_region['y'] + (float) $eu_region['height'] + 0.05
	|| "CA\n1234" !== APD_Formats::sample_plate_text( 'eu_300' )
) {
	fwrite( STDERR, 'EU_300_FAIL ' . ( is_wp_error( $eu_300 ) ? $eu_300->get_error_message() : wp_json_encode( array( $eu_300, $eu_region ) ) ) . PHP_EOL );
	exit( 1 );
}

$eu_untouched = APD_Formats::sanitize(
	array(
		'name'      => 'EU still',
		'type'      => 'eu',
		'width'     => 300,
		'height'    => 150,
		'text_rows' => '2',
	)
);
if ( is_wp_error( $eu_untouched ) || 520 !== (int) $eu_untouched['width'] || 110 !== (int) $eu_untouched['height'] || isset( $eu_untouched['text_rows'] ) || APD_Formats::uses_two_rows( 'eu' ) ) {
	fwrite( STDERR, "EU_300_LEAK_FAIL\n" );
	exit( 1 );
}

$atyp_one = APD_Formats::sanitize(
	array(
		'name'            => 'Atypical one',
		'type'            => 'atyp_300',
		'width'           => 199,
		'height'          => 154,
		'text_rows'       => '1',
		'max_chars'       => 8,
		'max_chars_row_1' => 7,
		'max_chars_row_2' => 3,
	)
);
$atyp_two = APD_Formats::sanitize(
	array(
		'name'            => 'Atypical two',
		'type'            => 'atyp_300',
		'text_rows'       => '2',
		'max_chars'       => 8,
		'max_chars_row_1' => 7,
		'max_chars_row_2' => 3,
	)
);
$atyp_one_payload = is_wp_error( $atyp_one ) ? array() : APD_Formats::frontend_payload( $atyp_one );
$atyp_two_payload = is_wp_error( $atyp_two ) ? array() : APD_Formats::frontend_payload( $atyp_two );
$atyp_region      = is_wp_error( $atyp_two ) ? array() : APD_Formats::text_area_region( $atyp_two );
$atyp_box         = is_wp_error( $atyp_two ) ? array() : $atyp_two['text_box'];
if (
	is_wp_error( $atyp_one )
	|| is_wp_error( $atyp_two )
	|| APD_Formats::uses_two_rows( 'atyp_300' )
	|| APD_Formats::uses_country_band( 'atyp_300' )
	|| ! APD_Formats::uses_palette_slots( 'atyp_300' )
	|| ! APD_Formats::uses_painted_plate( 'atyp_300' )
	|| 300 !== (int) $atyp_one['width']
	|| 150 !== (int) $atyp_one['height']
	|| 300 !== (int) $atyp_two['width']
	|| 150 !== (int) $atyp_two['height']
	|| 1 !== (int) $atyp_one['text_rows']
	|| 2 !== (int) $atyp_two['text_rows']
	|| APD_Formats::format_uses_two_rows( $atyp_one )
	|| ! APD_Formats::format_uses_two_rows( $atyp_two )
	|| 8 !== (int) $atyp_one['max_chars']
	|| 8 !== (int) $atyp_two['max_chars']
	|| 7 !== (int) $atyp_one['max_chars_row_1']
	|| 3 !== (int) $atyp_one['max_chars_row_2']
	|| 7 !== (int) $atyp_two['max_chars_row_1']
	|| 3 !== (int) $atyp_two['max_chars_row_2']
	|| '8' !== APD_Formats::max_chars_label( $atyp_one )
	|| '7 / 3' !== APD_Formats::max_chars_label( $atyp_two )
	|| isset( $atyp_one['text_box']['letter_align'] )
	|| ! isset( $atyp_two['text_box']['letter_align'], $atyp_two['text_box']['number_align'] )
	|| 'justify' !== $atyp_two['text_box']['letter_align']
	|| ! empty( $atyp_one_payload['capabilities']['two_row'] )
	|| empty( $atyp_two_payload['capabilities']['two_row'] )
	|| ! empty( $atyp_one_payload['capabilities']['country_band'] )
	|| 8 !== (int) $atyp_one_payload['max_chars']
	|| 10 !== (int) $atyp_two_payload['max_chars']
	|| 7 !== (int) $atyp_two_payload['row_max_chars'][0]
	|| 3 !== (int) $atyp_two_payload['row_max_chars'][1]
	|| (float) $atyp_box['x'] + 0.05 < (float) $atyp_region['x']
	|| (float) $atyp_box['y'] + 0.05 < (float) $atyp_region['y']
	|| (float) $atyp_box['x'] + (float) $atyp_box['width'] > (float) $atyp_region['x'] + (float) $atyp_region['width'] + 0.05
	|| (float) $atyp_box['y'] + (float) $atyp_box['height'] > (float) $atyp_region['y'] + (float) $atyp_region['height'] + 0.05
) {
	fwrite( STDERR, 'ATYP_300_FAIL ' . wp_json_encode( array( $atyp_one, $atyp_two, $atyp_region ) ) . PHP_EOL );
	exit( 1 );
}

echo 'PLATE_300_OK' . PHP_EOL;

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

$moto_centered = APD_Formats::sanitize(
	array(
		'name'            => 'Moto centered',
		'type'            => 'moto_plain',
		'max_chars_row_1' => 3,
		'max_chars_row_2' => 6,
		'text_box'        => array(
			'letter_align' => 'center',
			'number_align' => 'center',
		),
	)
);

if (
	is_wp_error( $moto_centered )
	|| 3 !== (int) $moto_centered['max_chars_row_1']
	|| 6 !== (int) $moto_centered['max_chars_row_2']
	|| 9 !== (int) $moto_centered['max_chars']
	|| 'center' !== $moto_centered['text_box']['letter_align']
	|| 'center' !== $moto_centered['text_box']['number_align']
	|| array( 3, 6 ) !== APD_Formats::suv_row_limits( $moto_centered )
	|| "CA\n1234" !== APD_Formats::sample_plate_text( 'moto_plain' )
) {
	fwrite( STDERR, 'MOTO_PLAIN_ROWS_FAIL ' . wp_json_encode( $moto_centered ) . PHP_EOL );
	exit( 1 );
}

if ( 'moto' !== APD_Formats::catalog_kind( 'moto_plain' ) ) {
	fwrite( STDERR, "MOTO_PLAIN_CATALOG_KIND_FAIL\n" );
	exit( 1 );
}

echo 'MOTO_PLAIN_TYPE_OK' . PHP_EOL;

$moto_240 = APD_Formats::sanitize(
	array(
		'name'   => 'Moto 240',
		'type'   => 'moto_240',
		'width'  => 199,
		'height' => 154,
	)
);
$moto_plain_240 = APD_Formats::sanitize(
	array(
		'name'   => 'Moto plain 240',
		'type'   => 'moto_plain_240',
		'width'  => 520,
		'height' => 110,
	)
);

$band_width = ( ! is_wp_error( $moto_240 ) && isset( $moto_240['band_box']['width'] ) ) ? (float) $moto_240['band_box']['width'] : 0;
$band_height = ( ! is_wp_error( $moto_240 ) && isset( $moto_240['band_box']['height'] ) ) ? (float) $moto_240['band_box']['height'] : 0;
$text_x = ( ! is_wp_error( $moto_240 ) && isset( $moto_240['text_box']['x'] ) ) ? (float) $moto_240['text_box']['x'] : 0;
$plain_x = ( ! is_wp_error( $moto_plain_240 ) && isset( $moto_plain_240['text_box']['x'] ) ) ? (float) $moto_plain_240['text_box']['x'] : 0;

if (
	is_wp_error( $moto_240 )
	|| is_wp_error( $moto_plain_240 )
	|| 240 !== (int) $moto_240['width']
	|| 130 !== (int) $moto_240['height']
	|| 240 !== (int) $moto_plain_240['width']
	|| 130 !== (int) $moto_plain_240['height']
	|| ! APD_Formats::uses_country_band( 'moto_240' )
	|| APD_Formats::uses_country_band( 'moto_plain_240' )
	|| ! APD_Formats::uses_painted_plate( 'moto_240' )
	|| ! APD_Formats::uses_painted_plate( 'moto_plain_240' )
	|| APD_Formats::uses_palette_slots( 'moto_240' )
	|| ! APD_Formats::uses_palette_slots( 'moto_plain_240' )
	|| 'moto' !== APD_Formats::catalog_kind( 'moto_240' )
	|| 'moto' !== APD_Formats::catalog_kind( 'moto_plain_240' )
	|| abs( $band_width - 19.7 ) > 0.2
	|| 100.0 !== $band_height
	|| $text_x < 15
	|| (float) $moto_plain_240['text_box']['x'] + 0.05 < (float) APD_Formats::text_area_region( $moto_plain_240 )['x']
	|| 'justify' !== $moto_240['text_box']['letter_align']
	|| 'justify' !== $moto_plain_240['text_box']['number_align']
) {
	fwrite( STDERR, 'MOTO_240_FAIL ' . wp_json_encode( array( $moto_240, $moto_plain_240 ) ) . PHP_EOL );
	exit( 1 );
}

$holder_for_car  = APD_Formats::format_size_label( array( 'type' => 'holder', 'width' => 520, 'height' => 260 ) );
$holder_for_moto = APD_Formats::format_size_label( array( 'type' => 'holder_moto', 'width' => 199, 'height' => 199 ) );
$holder_for_d    = APD_Formats::format_size_label( array( 'type' => 'holder_d', 'width' => 280, 'height' => 159 ) );
$plate_size      = APD_Formats::format_size_label( array( 'type' => 'moto', 'width' => 199, 'height' => 154 ) );

if (
	false === strpos( $holder_for_car, '520' )
	|| false === strpos( $holder_for_car, '110' )
	|| false !== strpos( $holder_for_car, '260' )
	|| false === strpos( $holder_for_moto, '199' )
	|| false === strpos( $holder_for_moto, '154' )
	|| false === strpos( $holder_for_d, '280' )
	|| false === strpos( $holder_for_d, '200' )
	|| '199 x 154' !== $plate_size
	|| 500 !== APD_Formats::preview_frame_width( 'holder', 520 )
	|| 191 !== APD_Formats::preview_frame_width( 'holder_moto', 199 )
	|| 269 !== APD_Formats::preview_frame_width( 'holder_d', 280 )
) {
	fwrite( STDERR, 'HOLDER_PLATE_SIZE_FAIL ' . wp_json_encode( array( $holder_for_car, $holder_for_moto, $holder_for_d, $plate_size ) ) . PHP_EOL );
	exit( 1 );
}

$oversized = APD_Formats::sanitize(
	array(
		'name'     => 'Moto gap',
		'type'     => 'moto_240',
		'text_box' => array(
			'x'      => 0,
			'y'      => 0,
			'width'  => 100,
			'height' => 100,
		),
	)
);
$gap_region = is_wp_error( $oversized ) ? array() : APD_Formats::text_area_region( $oversized );
$gap_box    = is_wp_error( $oversized ) ? array() : $oversized['text_box'];
$gap_top_mm = ( ! is_wp_error( $oversized ) ) ? ( (float) $gap_box['y'] / 100 ) * 130 : 0;
$gap_side   = ( ! is_wp_error( $oversized ) ) ? ( (float) $gap_box['x'] / 100 ) * 240 : 0;
if (
	is_wp_error( $oversized )
	|| (float) $gap_box['x'] + 0.15 < (float) $gap_region['x']
	|| (float) $gap_box['y'] + 0.15 < (float) $gap_region['y']
	|| (float) $gap_box['x'] + (float) $gap_box['width'] > (float) $gap_region['x'] + (float) $gap_region['width'] + 0.15
	|| (float) $gap_box['y'] + (float) $gap_box['height'] > (float) $gap_region['y'] + (float) $gap_region['height'] + 0.15
	|| $gap_top_mm + 0.2 < 16
	|| $gap_side + 0.2 < 16
) {
	fwrite( STDERR, 'TEXT_FRAME_GAP_FAIL ' . wp_json_encode( array( $gap_box, $gap_region ) ) . PHP_EOL );
	exit( 1 );
}

$open_plate = APD_Formats::sanitize(
	array(
		'name'     => 'Open color',
		'type'     => 'color',
		'no_frame' => '1',
		'text_box' => array(
			'x'      => 1,
			'y'      => 1,
			'width'  => 90,
			'height' => 90,
		),
	)
);
if ( is_wp_error( $open_plate ) || 1.0 !== (float) $open_plate['text_box']['x'] || 1.0 !== (float) $open_plate['text_box']['y'] ) {
	fwrite( STDERR, 'NO_FRAME_GAP_FAIL ' . ( is_wp_error( $open_plate ) ? $open_plate->get_error_message() : wp_json_encode( $open_plate['text_box'] ) ) . PHP_EOL );
	exit( 1 );
}

$shop_draw = file_get_contents( APD_PLUGIN_DIR . 'assets/js/configurator.js' );
$admin_draw = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );
if (
	false === strpos( $shop_draw, 'TEXT_FRAME_GAP_MM = 8' )
	|| false === strpos( $shop_draw, 'function frameTextInset' )
	|| false === strpos( $shop_draw, 'frameTextInset()' )
	|| false === strpos( $admin_draw, 'TEXT_FRAME_GAP_MM = 8' )
	|| false === strpos( $admin_draw, 'bw + TEXT_FRAME_GAP_MM' )
) {
	fwrite( STDERR, "TEXT_FRAME_GAP_DRAW_FAIL\n" );
	exit( 1 );
}

echo 'MOTO_240_TYPE_OK' . PHP_EOL;

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

if ( APD_Formats::admin_sample_plate_text( 'eu' ) !== 'CA 1234' || APD_Formats::admin_sample_plate_text( 'moto' ) !== 'CA 1234' || APD_Formats::admin_sample_plate_text( 'suv' ) !== 'CAAA 1234' || APD_Formats::admin_sample_plate_text( 'suv_340' ) !== 'CAAA 1234' || APD_Formats::admin_sample_plate_text( 'suv_eu' ) !== 'CAAA 1234' || APD_Formats::admin_sample_plate_text( 'suv_eu_340' ) !== 'CAAA 1234' ) {
	fwrite( STDERR, "ADMIN_SAMPLE_TEXT_FAIL\n" );
	exit( 1 );
}

if ( abs( APD_Formats::SAMPLE_FONT_FILL - 0.9 ) > 0.001 ) {
	fwrite( STDERR, "SAMPLE_FONT_FILL_FAIL\n" );
	exit( 1 );
}

echo 'ADMIN_STUDIO_SAMPLE_OK' . PHP_EOL;

$suv_eu_wide = APD_Formats::sanitize(
	array(
		'name'   => 'SUV EU wide',
		'type'   => 'suv_eu_340',
		'width'  => 280,
		'height' => 200,
	)
);
$suv_plain_wide = APD_Formats::sanitize(
	array(
		'name'   => 'SUV plain wide',
		'type'   => 'suv_340',
		'width'  => 520,
		'height' => 110,
	)
);

if ( is_wp_error( $suv_eu_wide ) || 340 !== (int) $suv_eu_wide['width'] || 200 !== (int) $suv_eu_wide['height'] || ! APD_Formats::uses_country_band( 'suv_eu_340' ) || ! APD_Formats::uses_painted_plate( 'suv_eu_340' ) || APD_Formats::uses_base_image( 'suv_eu_340' ) || APD_Formats::uses_palette_slots( 'suv_eu_340' ) || 'suv' !== APD_Formats::catalog_kind( 'suv_eu_340' ) || "CA AA\n4935" !== APD_Formats::sample_plate_text( 'suv_eu_340' ) ) {
	fwrite( STDERR, 'SUV_EU_340_FAIL ' . wp_json_encode( $suv_eu_wide ) . PHP_EOL );
	exit( 1 );
}

if ( abs( (float) $suv_eu_wide['band_box']['height'] - 50 ) > 0.2 || abs( (float) $suv_eu_wide['band_box']['width'] - 10.7 ) > 0.2 ) {
	fwrite( STDERR, 'SUV_EU_340_BAND_FAIL ' . wp_json_encode( $suv_eu_wide['band_box'] ) . PHP_EOL );
	exit( 1 );
}

if ( is_wp_error( $suv_plain_wide ) || 340 !== (int) $suv_plain_wide['width'] || 200 !== (int) $suv_plain_wide['height'] || APD_Formats::uses_country_band( 'suv_340' ) || ! APD_Formats::uses_painted_plate( 'suv_340' ) || APD_Formats::uses_base_image( 'suv_340' ) || ! APD_Formats::uses_palette_slots( 'suv_340' ) || 'suv' !== APD_Formats::catalog_kind( 'suv_340' ) || array( 5, 4 ) !== APD_Formats::suv_row_limits( $suv_plain_wide ) ) {
	fwrite( STDERR, 'SUV_340_FAIL ' . wp_json_encode( $suv_plain_wide ) . PHP_EOL );
	exit( 1 );
}

echo 'SUV_340_TYPE_OK' . PHP_EOL;

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
$holder_limit_row = APD_Formats::sanitize(
	array(
		'name' => 'Holder limit',
		'type' => 'holder',
	)
);
if ( ! is_wp_error( $holder_limit_row ) ) {
	$holder_limit_row['max_chars'] = 24;
}
$holder_limit_payload = is_wp_error( $holder_limit_row ) ? array() : APD_Formats::frontend_payload( $holder_limit_row );

if ( APD_Formats::HOLDER_TEXT_MAX !== APD_Formats::holder_text_limit() || APD_Formats::HOLDER_TEXT_MAX !== (int) $holder_limit_payload['max_chars'] || '—' !== APD_Formats::max_chars_label( array( 'type' => 'holder', 'max_chars' => 24 ) ) || '40' !== APD_Formats::max_chars_label( array( 'type' => 'holder', 'max_chars' => 40, 'holder_max_set' => true ) ) ) {
	fwrite( STDERR, "HOLDER_TEXT_LIMIT_FAIL\n" );
	exit( 1 );
}

if ( 520 !== (int) $holder_size['width'] || 260 !== (int) $holder_size['height'] || false === strpos( APD_Formats::bundled_holder_image_url(), 'gray-plate-holder-hole.png' ) ) {
	fwrite( STDERR, "HOLDER_STANDARD_FAIL\n" );
	exit( 1 );
}

$photo_holder_expect = array(
	'holder_moto' => array( 199, 199, 84.18, 'holder-moto.png' ),
	'holder_d'    => array( 280, 159, 85.28, 'holder-type-d.png' ),
);

foreach ( $photo_holder_expect as $photo_type => $photo_expect ) {
	$photo_row = APD_Formats::sanitize(
		array(
			'name' => 'Photo ' . str_replace( '_', ' ', $photo_type ),
			'type' => $photo_type,
		)
	);
	$photo_payload = is_wp_error( $photo_row ) ? array() : APD_Formats::frontend_payload( $photo_row );
	$photo_caps    = APD_Formats::color_field_capabilities( $photo_type );
	$photo_box     = APD_Formats::holder_strip_box( $photo_type );

	if (
		is_wp_error( $photo_row )
		|| $photo_expect[0] !== (int) $photo_row['width']
		|| $photo_expect[1] !== (int) $photo_row['height']
		|| 0 !== (int) $photo_row['base_image_id']
		|| abs( (float) $photo_row['text_box']['y'] - $photo_expect[2] ) > 0.05
		|| abs( (float) $photo_box['y'] - $photo_expect[2] ) > 0.05
		|| 'holder' !== APD_Formats::catalog_kind( $photo_type )
		|| APD_Formats::offers_frame_choice( $photo_type )
		|| '' !== APD_Formats::sample_plate_text( $photo_type )
		|| array( 'holder_text', 'holder_strip' ) !== $photo_caps
		|| empty( $photo_payload['allow_empty'] )
		|| APD_Formats::HOLDER_TEXT_MAX !== (int) $photo_payload['max_chars']
		|| false === strpos( (string) $photo_payload['base_image_url'], $photo_expect[3] )
		|| abs( (float) $photo_payload['strip_box']['y'] - $photo_expect[2] ) > 0.05
		|| abs( (float) $holder_strip['y'] - 77.3 ) > 0.05
	) {
		fwrite( STDERR, "PHOTO_HOLDER_FAIL {$photo_type}\n" );
		exit( 1 );
	}
}

echo "PHOTO_HOLDER_OK\n";

$holder_saved = APD_Formats::sanitize(
	array(
		'name'             => 'Strip metrics',
		'type'             => 'holder',
		'text_box_metric'  => 'strip',
		'text_box'         => array(
			'x'      => 20,
			'y'      => 30,
			'width'  => 60,
			'height' => 40,
			'align'  => 'center',
			'valign' => 'middle',
		),
	)
);
$holder_mid_x = $holder_strip['x'] + ( $holder_strip['width'] / 2 );
$holder_mid_y = $holder_strip['y'] + ( $holder_strip['height'] / 2 );
if (
	is_wp_error( $holder_saved )
	|| abs( ( $holder_saved['text_box']['x'] + ( $holder_saved['text_box']['width'] / 2 ) ) - $holder_mid_x ) > 0.6
	|| abs( ( $holder_saved['text_box']['y'] + ( $holder_saved['text_box']['height'] / 2 ) ) - $holder_mid_y ) > 0.6
	|| $holder_saved['text_box']['height'] >= 5
	|| $holder_saved['text_box']['width'] >= $holder_strip['width']
) {
	fwrite( STDERR, 'HOLDER_STRIP_METRICS_FAIL ' . ( is_wp_error( $holder_saved ) ? $holder_saved->get_error_message() : wp_json_encode( $holder_saved['text_box'] ) ) . PHP_EOL );
	exit( 1 );
}

$moto_strip_saved = APD_Formats::sanitize(
	array(
		'name'            => 'Moto strip metrics',
		'type'            => 'holder_moto',
		'text_box_metric' => 'strip',
		'text_box'        => array(
			'x'      => 10,
			'y'      => 25,
			'width'  => 80,
			'height' => 50,
			'align'  => 'center',
			'valign' => 'middle',
		),
	)
);
$moto_strip = APD_Formats::holder_strip_box( 'holder_moto' );
if (
	is_wp_error( $moto_strip_saved )
	|| abs( $moto_strip_saved['text_box']['width'] - ( $moto_strip['width'] * 0.8 ) ) > 0.2
	|| abs( $moto_strip_saved['text_box']['height'] - ( $moto_strip['height'] * 0.5 ) ) > 0.2
) {
	fwrite( STDERR, 'MOTO_STRIP_METRICS_FAIL ' . ( is_wp_error( $moto_strip_saved ) ? $moto_strip_saved->get_error_message() : wp_json_encode( $moto_strip_saved['text_box'] ) ) . PHP_EOL );
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
$photo_holder_px = APD_Formats::preview_frame_width( 'holder_moto', 199 );
$type_d_px       = APD_Formats::preview_frame_width( 'holder_d', 280 );
if ( 191 !== $photo_holder_px || 269 !== $type_d_px || 500 !== APD_Formats::preview_frame_width( 'holder', 520 ) ) {
	fwrite( STDERR, "HOLDER_PREVIEW_WIDTH_FAIL moto={$photo_holder_px} d={$type_d_px}\n" );
	exit( 1 );
}

if ( 500 !== $eu_display || $eu_display !== $moto_display || $eu_display !== $us_display || $eu_display !== $suv_display || $eu_display !== $holder_display ) {
	fwrite( STDERR, "CANVAS_DISPLAY_FAIL eu={$eu_display} moto={$moto_display} us={$us_display}\n" );
	exit( 1 );
}

$wide_moto = APD_Formats::preview_frame_width( 'moto_240', 240 );
$wide_suv  = APD_Formats::preview_frame_width( 'suv_eu_340', 340 );
$plain_wide_moto = APD_Formats::preview_frame_width( 'moto_plain_240', 240 );
$plain_wide_suv  = APD_Formats::preview_frame_width( 'suv_340', 340 );

if (
	500 !== APD_Formats::preview_frame_width( 'moto', 199 )
	|| 500 !== APD_Formats::preview_frame_width( 'suv_eu', 280 )
	|| 500 !== APD_Formats::preview_frame_width( 'eu', 520 )
	|| 327 !== APD_Formats::preview_frame_width( 'mini', 340 )
	|| 327 !== APD_Formats::preview_frame_width( 'mini_plain', 340 )
	|| 192 !== APD_Formats::preview_frame_width( 'super_mini', 200 )
	|| 192 !== APD_Formats::preview_frame_width( 'super_mini_plain', 200 )
	|| 754 !== APD_Formats::preview_frame_width( 'eu_300', 300 )
	|| 754 !== APD_Formats::preview_frame_width( 'atyp_300', 300 )
	|| 500 !== APD_Formats::preview_frame_width( 'fr', 520 )
	|| 500 !== APD_Formats::preview_frame_width( 'fr_ww', 520 )
	|| 500 !== APD_Formats::preview_frame_width( 'austria', 520 )
	|| 500 !== APD_Formats::preview_frame_width( 'us', 305 )
	|| 984 !== APD_Formats::preview_frame_width( 'us', 600 )
	|| 603 !== $wide_moto
	|| 603 !== $plain_wide_moto
	|| 607 !== $wide_suv
	|| 607 !== $plain_wide_suv
	|| $wide_moto <= APD_Formats::preview_frame_width( 'moto', 199 )
	|| $wide_suv <= APD_Formats::preview_frame_width( 'suv', 280 )
) {
	fwrite( STDERR, "PREVIEW_SCALE_FAIL moto={$wide_moto} suv={$wide_suv}\n" );
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

if ( false === strpos( $admin_js, 'basis = 199' ) || false === strpos( $admin_js, 'basis = 280' ) || false === strpos( $admin_js, 'basis = 305' ) || false === strpos( $admin_js, "type === 'eu_300' || type === 'atyp_300'" ) || false === strpos( $admin_js, '500 * (mm.width / basis)' ) ) {
	fwrite( STDERR, "ADMIN_PREVIEW_SCALE_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $admin_css, '.apd-region-code-box[hidden]' ) || false === strpos( $admin_js, 'function englishFrameMm' ) || false === strpos( $admin_js, 'data-apd-extra-box="slogan"' ) ) {
	fwrite( STDERR, "ADMIN_ENGLISH_PREVIEW_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $admin_js, 'input.disabled = !enabled' ) || false === strpos( $admin_js, 'centerBoxInRegion' ) || false === strpos( $admin_js, 'canvasToStrip' ) || false === strpos( $admin_js, 'data-apd-strip-metrics' ) ) {
	fwrite( STDERR, "ADMIN_JS_DISABLE_OR_CENTER_FAIL\n" );
	exit( 1 );
}

$formats_tab = file_get_contents( APD_PLUGIN_DIR . 'templates/admin/formats-tab.php' );
if ( preg_match( '/name="apd_format\[band_ratio\]"[^>]*type="number"/', $formats_tab ) || preg_match( '/type="number"[^>]*name="apd_format\[band_ratio\]"/', $formats_tab ) ) {
	fwrite( STDERR, "BAND_RATIO_STILL_NUMBER_INPUT\n" );
	exit( 1 );
}

$wrapped = APD_Formats::sanitize(
	array(
		'name'      => 'Street wrap',
		'type'      => 'custom',
		'width'     => 340,
		'height'    => 200,
		'wrap_text' => '1',
	)
);
$plain = APD_Formats::sanitize(
	array(
		'name'      => 'EU no wrap',
		'type'      => 'eu',
		'wrap_text' => '1',
	)
);

if ( is_wp_error( $wrapped ) || empty( $wrapped['wrap_text'] ) || 340 !== (int) $wrapped['width'] || 200 !== (int) $wrapped['height'] ) {
	fwrite( STDERR, "WRAP_SANITIZE_FAIL\n" );
	exit( 1 );
}

$wrap_payload = APD_Formats::frontend_payload( $wrapped );

if ( empty( $wrap_payload['wrap_text'] ) || empty( $wrap_payload['multiline'] ) || (int) $wrap_payload['max_lines'] < 1 ) {
	fwrite( STDERR, "WRAP_PAYLOAD_FAIL\n" );
	exit( 1 );
}

if ( is_wp_error( $plain ) || ! empty( $plain['wrap_text'] ) || ! empty( APD_Formats::frontend_payload( $plain )['wrap_text'] ) || 520 !== (int) $plain['width'] || 110 !== (int) $plain['height'] ) {
	fwrite( STDERR, "WRAP_EU_FAIL\n" );
	exit( 1 );
}

$locked_sizes = array(
	'eu'         => array( 520, 110 ),
	'eu_plain'   => array( 520, 110 ),
	'color'      => array( 520, 110 ),
	'us'         => array( 305, 152 ),
	'moto'            => array( 199, 154 ),
	'moto_240'        => array( 240, 130 ),
	'moto_plain'      => array( 199, 154 ),
	'moto_plain_240'  => array( 240, 130 ),
	'suv'        => array( 280, 200 ),
	'suv_340'    => array( 340, 200 ),
	'suv_eu'     => array( 280, 200 ),
	'suv_eu_340' => array( 340, 200 ),
	'eu_300'     => array( 300, 150 ),
	'atyp_300'   => array( 300, 150 ),
	'fr'         => array( 520, 110 ),
	'fr_ww'      => array( 520, 110 ),
	'fr_region'  => array( 520, 110 ),
	'austria'    => array( 520, 110 ),
	'uk'         => array( 520, 110 ),
	'uk_plain'   => array( 520, 110 ),
	'mini'       => array( 340, 90 ),
	'mini_plain' => array( 340, 90 ),
	'super_mini' => array( 200, 90 ),
	'super_mini_plain' => array( 200, 90 ),
	'holder'      => array( 520, 260 ),
	'holder_moto' => array( 199, 199 ),
	'holder_d'    => array( 280, 159 ),
);

foreach ( $locked_sizes as $locked_type => $locked_pair ) {
	$locked = APD_Formats::sanitize(
		array(
			'name'   => 'Locked ' . str_replace( '_', ' ', $locked_type ),
			'type'   => $locked_type,
			'width'  => 340,
			'height' => 200,
		)
	);

	if ( is_wp_error( $locked ) || $locked_pair[0] !== (int) $locked['width'] || $locked_pair[1] !== (int) $locked['height'] ) {
		fwrite( STDERR, "SIZE_LOCK_FAIL {$locked_type}\n" );
		exit( 1 );
	}
}

$admin_js   = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );
$size_start = is_string( $admin_js ) ? strpos( $admin_js, 'function applyDefaultSize' ) : false;
$size_end   = is_string( $admin_js ) ? strpos( $admin_js, 'function resetLayoutForNewFormat' ) : false;
$size_body  = ( false !== $size_start && false !== $size_end && $size_end > $size_start ) ? substr( $admin_js, $size_start, $size_end - $size_start ) : '';

$custom_return = strpos( $size_body, 'return;' );
$preset_lock   = strpos( $size_body, 'readOnly = true' );

if ( false === strpos( $size_body, "type === 'custom'" ) || false === strpos( $size_body, 'readOnly = false' ) || false === $custom_return || false === $preset_lock || $custom_return > $preset_lock ) {
	fwrite( STDERR, "STREET_SIZE_JS_FAIL\n" );
	exit( 1 );
}

$street_sizes = array(
	array( 340, 200 ),
	array( 240, 130 ),
	array( 300, 150 ),
);

foreach ( $street_sizes as $street_pair ) {
	$street = APD_Formats::sanitize(
		array(
			'name'   => 'Street ' . $street_pair[0] . 'x' . $street_pair[1],
			'type'   => 'custom',
			'width'  => $street_pair[0],
			'height' => $street_pair[1],
		)
	);

	if ( is_wp_error( $street ) || $street_pair[0] !== (int) $street['width'] || $street_pair[1] !== (int) $street['height'] || ! empty( $street['wrap_text'] ) ) {
		fwrite( STDERR, "STREET_SIZE_FAIL {$street_pair[0]}\n" );
		exit( 1 );
	}
}

$clamped = APD_Formats::sanitize(
	array(
		'name'   => 'Street too small',
		'type'   => 'custom',
		'width'  => 34,
		'height' => 20,
	)
);

if ( is_wp_error( $clamped ) || 100 !== (int) $clamped['width'] || 40 !== (int) $clamped['height'] || 520 === (int) $clamped['width'] ) {
	fwrite( STDERR, "STREET_CLAMP_FAIL\n" );
	exit( 1 );
}

echo 'CANVAS_AND_VALIDATION_OK' . PHP_EOL;

$fr = APD_Formats::sanitize(
	array(
		'name'     => 'French',
		'type'     => 'fr',
		'width'    => 340,
		'height'   => 200,
		'fr_max_1' => 2,
		'fr_max_2' => 3,
		'fr_max_3' => 2,
	)
);
$fr_ww = APD_Formats::sanitize(
	array(
		'name'     => 'French temporary',
		'type'     => 'fr_ww',
		'width'    => 300,
		'height'   => 150,
		'fr_max_2' => 4,
		'fr_max_3' => 3,
	)
);
$fr_payload = is_wp_error( $fr ) ? array() : APD_Formats::frontend_payload( $fr );
$ww_payload = is_wp_error( $fr_ww ) ? array() : APD_Formats::frontend_payload( $fr_ww );
$eu_plain   = APD_Formats::sanitize(
	array(
		'name'     => 'EU stays plain',
		'type'     => 'eu',
		'fr_max_1' => 9,
		'fr_max_2' => 9,
		'fr_max_3' => 9,
		'text_rows' => '2',
	)
);
$mixed = APD_Formats::french_posted_text(
	is_array( $fr ) ? $fr : array( 'type' => 'fr' ),
	array( 'A1', '000', 'AA' )
);
$ww_text = APD_Formats::french_posted_text(
	is_array( $fr_ww ) ? $fr_ww : array( 'type' => 'fr_ww' ),
	array( '1234', 'AB' ),
	array(
		'month' => '05',
		'year'  => '26',
	)
);
$ww_bad_date = APD_Formats::french_posted_text(
	is_array( $fr_ww ) ? $fr_ww : array( 'type' => 'fr_ww' ),
	array( '000', 'AA' ),
	array(
		'month' => 'AB',
		'year'  => '26',
	)
);
$fr_font = APD_Formats::default_font_id(
	array(
		array(
			'id'     => 'oswald',
			'family' => 'Oswald',
		),
		array(
			'id'     => 'french',
			'family' => 'French font',
		),
	),
	'fr'
);
$eu_font = APD_Formats::default_font_id(
	array(
		array(
			'id'     => 'oswald',
			'family' => 'Oswald',
		),
		array(
			'id'     => 'french',
			'family' => 'French font',
		),
	),
	'eu'
);

$locked_preset_bad = static function ( $payload, $codes ) {
	if ( empty( $payload['presets'] ) || ! is_array( $payload['presets'] ) ) {
		return false;
	}
	foreach ( $payload['presets'] as $preset ) {
		$code = strtoupper( (string) ( isset( $preset['country_code'] ) ? $preset['country_code'] : '' ) );
		if ( ! in_array( $code, $codes, true ) ) {
			return true;
		}
	}
	return false;
};

if (
	is_wp_error( $fr )
	|| is_wp_error( $fr_ww )
	|| is_wp_error( $eu_plain )
	|| 520 !== (int) $fr['width']
	|| 110 !== (int) $fr['height']
	|| 520 !== (int) $fr_ww['width']
	|| 110 !== (int) $fr_ww['height']
	|| isset( $fr['text_rows'] )
	|| isset( $eu_plain['fr_max_1'] )
	|| isset( $eu_plain['text_rows'] )
	|| 2 !== (int) $fr['fr_max_1']
	|| 3 !== (int) $fr['fr_max_2']
	|| 2 !== (int) $fr['fr_max_3']
	|| 9 !== (int) $fr['max_chars']
	|| '2-3-2' !== APD_Formats::max_chars_label( $fr )
	|| 4 !== (int) $fr_ww['fr_max_2']
	|| 3 !== (int) $fr_ww['fr_max_3']
	|| 11 !== (int) $fr_ww['max_chars']
	|| '4 / 3' !== APD_Formats::max_chars_label( $fr_ww )
	|| empty( $fr['no_frame'] )
	|| ! empty( $fr['border_width'] )
	|| APD_Formats::uses_two_rows( 'fr' )
	|| APD_Formats::uses_two_rows( 'fr_ww' )
	|| APD_Formats::uses_palette_slots( 'fr' )
	|| APD_Formats::offers_frame_choice( 'fr' )
	|| APD_Formats::offers_frame_choice( 'fr_ww' )
	|| array() !== APD_Formats::color_field_capabilities( 'fr' )
	|| array() !== APD_Formats::color_field_capabilities( 'fr_ww' )
	|| array() !== APD_Formats::default_color_fields( 'fr' )
	|| empty( $fr_payload['capabilities']['country_band'] )
	|| empty( $fr_payload['capabilities']['country_locked'] )
	|| empty( $ww_payload['capabilities']['country_locked'] )
	|| $locked_preset_bad( $fr_payload, array( 'F', 'FR' ) )
	|| $locked_preset_bad( $ww_payload, array( 'F', 'FR' ) )
	|| isset( $eu_plain['at_max_1'] )
	|| empty( $fr_payload['band_box']['width'] )
	|| abs( (float) $fr_payload['band_box']['width'] - 7.7 ) > 0.4
	|| ! empty( $fr_payload['frame_choice'] )
	|| 'AA-000-AA' !== APD_Formats::sample_plate_text( 'fr' )
	|| 'WW-000-AA' !== APD_Formats::sample_plate_text( 'fr_ww' )
	|| '#FFFFFF' !== $fr_payload['fr_fill']
	|| '#F19DC4' !== $ww_payload['fr_fill']
	|| '#000000' !== $fr_payload['fr_ink']
	|| 'WW' !== $ww_payload['fr_mark']
	|| '' !== $fr_payload['fr_mark']
	|| 2 !== (int) $ww_payload['fr_date_max']
	|| 0 !== (int) $fr_payload['fr_date_max']
	|| array( 2, 3, 2 ) !== array_map( 'intval', $fr_payload['fr_groups'] )
	|| ! is_wp_error( $mixed )
	|| ! is_array( $ww_text )
	|| 'WW-1234-AB' !== $ww_text['text']
	|| '05' !== $ww_text['fr_month']
	|| '26' !== $ww_text['fr_year']
	|| ! is_wp_error( $ww_bad_date )
	|| 'french' !== $fr_font
	|| 'oswald' !== $eu_font
	|| 500 !== APD_Formats::preview_frame_width( 'fr', 520 )
	|| ! isset( $fr_payload['text_boxes'] ) || 3 !== count( $fr_payload['text_boxes'] )
	|| ! isset( $ww_payload['text_boxes'] ) || 3 !== count( $ww_payload['text_boxes'] )
	|| abs( (float) $fr_payload['text_boxes'][0]['y'] - ( 8 / 110 * 100 ) ) > 1.2
	|| abs( (float) $ww_payload['text_boxes'][0]['height'] - 72 ) > 1
	|| isset( $eu_plain['text_box_2'] )
	|| isset( $eu_plain['at_region'] )
	|| isset( $eu_plain['fr_month'] )
	|| isset( $fr['fr_month'] )
	|| isset( $fr['fr_region_box'] )
	|| empty( $fr_ww['fr_month']['height'] )
	|| ! empty( $fr_ww['fr_date_custom'] )
	|| abs( (float) $fr_ww['fr_month']['y'] - 14 ) > 0.6
	|| abs( ( (float) $fr_ww['fr_year']['y'] + (float) $fr_ww['fr_year']['height'] ) - 86 ) > 0.8
) {
	fwrite( STDERR, 'FR_FAIL ' . ( is_wp_error( $fr ) ? $fr->get_error_message() : wp_json_encode( array( $fr, $fr_ww, $fr_payload, $ww_text ) ) ) . PHP_EOL );
	exit( 1 );
}

$fr_region = APD_Formats::sanitize(
	array(
		'name' => 'French region',
		'type' => 'fr_region',
	)
);
$fr_region_box = is_array( $fr_region ) && isset( $fr_region['fr_region_box'] ) ? $fr_region['fr_region_box'] : array();
$fr_region_payload = is_array( $fr_region ) ? APD_Formats::frontend_payload( $fr_region ) : array();
$fr_region_groups = isset( $fr_region_payload['text_boxes'] ) ? $fr_region_payload['text_boxes'] : array();
$fr_region_last = isset( $fr_region_groups[2] ) ? $fr_region_groups[2] : array();
if (
	is_wp_error( $fr_region )
	|| isset( $fr_region['fr_month'] )
	|| empty( $fr_region_box['height'] )
	|| abs( (float) $fr_region_box['y'] - 60.4 ) > 0.2
	|| abs( ( (float) $fr_region_box['y'] + (float) $fr_region_box['height'] ) - 82.3 ) > 0.3
	|| APD_Formats::offers_country_choice( 'fr_region' )
	|| 'F' !== APD_Formats::locked_country_code( 'fr_region' )
	|| '95' !== APD_Formats::french_region_code( '95a' )
	|| '12' !== APD_Formats::french_region_code( '129' )
	|| empty( $fr_region_last )
	|| ( (float) $fr_region_last['x'] + (float) $fr_region_last['width'] ) > (float) $fr_region_box['x']
) {
	fwrite( STDERR, 'FR_REGION_FAIL ' . ( is_wp_error( $fr_region ) ? $fr_region->get_error_message() : wp_json_encode( $fr_region_box ) ) . PHP_EOL );
	exit( 1 );
}

$fr_rows = APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'fr_ww',
		'text'        => 'WW-000-AA',
		'fr_month'    => '05',
		'fr_year'     => '26',
	)
);
$fr_shown = '';
foreach ( $fr_rows as $fr_row ) {
	if ( isset( $fr_row['value'] ) && false !== strpos( (string) $fr_row['value'], 'WW-000-AA' ) ) {
		$fr_shown = $fr_row['value'];
	}
}
if ( 'WW-000-AA 05/26' !== $fr_shown ) {
	fwrite( STDERR, "FR_DATE_ROW_FAIL {$fr_shown}\n" );
	exit( 1 );
}

echo 'FR_OK' . PHP_EOL;

$uk_slots = APD_Color_Palettes::DEFAULT_PALETTE_IDS;
$uk = APD_Formats::sanitize(
	array(
		'name'          => 'English plate',
		'type'          => 'uk',
		'width'         => 340,
		'height'        => 200,
		'slogan_max'    => 18,
		'palette_slots' => array(
			'text'       => $uk_slots['text'],
			'background' => $uk_slots['background'],
			'border'     => $uk_slots['border'],
			'slogan'     => $uk_slots['slogan'],
		),
	)
);
$uk_plain = APD_Formats::sanitize(
	array(
		'name'          => 'English plain',
		'type'          => 'uk_plain',
		'slogan_max'    => 12,
		'palette_slots' => array(
			'text'       => $uk_slots['text'],
			'background' => $uk_slots['background'],
			'border'     => $uk_slots['border'],
			'slogan'     => $uk_slots['slogan'],
		),
	)
);
$mini = APD_Formats::sanitize(
	array(
		'name'   => 'Mini plate',
		'type'   => 'mini',
		'width'  => 520,
		'height' => 110,
	)
);
$mini_plain = APD_Formats::sanitize(
	array(
		'name' => 'Mini plain',
		'type' => 'mini_plain',
	)
);
$uk_payload = is_wp_error( $uk ) ? array() : APD_Formats::frontend_payload( $uk );
if (
	is_wp_error( $uk )
	|| is_wp_error( $uk_plain )
	|| is_wp_error( $mini )
	|| is_wp_error( $mini_plain )
	|| 520 !== (int) $uk['width']
	|| 110 !== (int) $uk['height']
	|| 18 !== (int) $uk['slogan_max']
	|| 18 !== (int) $uk_payload['slogan_max']
	|| isset( $mini['slogan_max'] )
	|| ! APD_Formats::offers_country_choice( 'uk' )
	|| ! APD_Formats::offers_country_choice( 'mini' )
	|| APD_Formats::offers_country_choice( 'uk_plain' )
	|| APD_Formats::offers_country_choice( 'mini_plain' )
	|| '' !== APD_Formats::locked_country_code( 'uk' )
	|| APD_Formats::offers_frame_choice( 'uk' )
	|| ! APD_Formats::offers_frame_choice( 'mini' )
	|| array( 'text', 'border', 'background', 'slogan' ) !== APD_Formats::color_field_capabilities( 'uk' )
	|| array( 'text', 'border', 'background' ) !== APD_Formats::color_field_capabilities( 'mini' )
	|| 'LKOO FDN' !== APD_Formats::sample_plate_text( 'uk' )
	|| 'GUNTHER' !== APD_Formats::sample_plate_text( 'mini' )
	|| 340 !== (int) $mini['width']
	|| 90 !== (int) $mini['height']
	|| ! empty( $uk['no_frame'] ) === false
	|| 0 !== (int) $uk['border_width']
	|| empty( $uk['slogan_box']['width'] )
	|| empty( $uk_payload['slogan_box']['width'] )
	|| isset( $mini['slogan_box'] )
	|| abs( (float) $uk['slogan_box']['y'] - 87.6 ) > 1.5
	|| abs( (float) $uk_plain['slogan_box']['x'] - 2.1 ) > 0.6
) {
	fwrite( STDERR, 'UK_MINI_FAIL ' . ( is_wp_error( $uk ) ? $uk->get_error_message() : wp_json_encode( $uk ) ) . PHP_EOL );
	exit( 1 );
}

echo 'UK_MINI_OK' . PHP_EOL;

$super = APD_Formats::sanitize(
	array(
		'name'   => 'Super mini plate',
		'type'   => 'super_mini',
		'width'  => 340,
		'height' => 90,
	)
);
$super_plain = APD_Formats::sanitize(
	array(
		'name' => 'Super mini plain',
		'type' => 'super_mini_plain',
	)
);
if (
	is_wp_error( $super )
	|| is_wp_error( $super_plain )
	|| 200 !== (int) $super['width']
	|| 90 !== (int) $super['height']
	|| 200 !== (int) $super_plain['width']
	|| 90 !== (int) $super_plain['height']
	|| ! APD_Formats::uses_country_band( 'super_mini' )
	|| APD_Formats::uses_country_band( 'super_mini_plain' )
	|| ! APD_Formats::offers_country_choice( 'super_mini' )
	|| APD_Formats::offers_country_choice( 'super_mini_plain' )
	|| ! empty( $super['no_frame'] )
	|| 8 !== (int) $super['border_width']
	|| ! empty( $mini['no_frame'] )
	|| 8 !== (int) $mini['border_width']
	|| isset( $super['slogan_box'] )
	|| 'FDN' !== APD_Formats::sample_plate_text( 'super_mini' )
	|| 'mini' !== APD_Formats::catalog_kind( 'mini_plain' )
	|| 'super_mini' !== APD_Formats::catalog_kind( 'super_mini_plain' )
	|| array( 'text', 'border', 'background' ) !== APD_Formats::color_field_capabilities( 'super_mini_plain' )
	|| ! APD_Formats::uses_palette_slots( 'mini_plain' )
	|| ! APD_Formats::uses_palette_slots( 'super_mini_plain' )
	|| APD_Formats::uses_palette_slots( 'mini' )
	|| APD_Formats::uses_palette_slots( 'super_mini' )
) {
	fwrite( STDERR, 'SUPER_MINI_FAIL ' . ( is_wp_error( $super ) ? $super->get_error_message() : wp_json_encode( $super ) ) . PHP_EOL );
	exit( 1 );
}

echo 'SUPER_MINI_OK' . PHP_EOL;

$at = APD_Formats::sanitize(
	array(
		'name'     => 'Austrian',
		'type'     => 'austria',
		'width'    => 340,
		'height'   => 200,
		'at_max_1' => 2,
		'at_max_2' => 6,
		'fr_max_1' => 9,
	)
);
$at_payload = is_wp_error( $at ) ? array() : APD_Formats::frontend_payload( $at );
$at_text    = is_wp_error( $at ) ? $at : APD_Formats::austria_posted_text( $at, 'WE', '000 CF' );
$at_bad     = is_wp_error( $at ) ? $at : APD_Formats::austria_posted_text( $at, 'W1', '000 CF' );
$at_rows    = is_array( $at_text ) ? APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'austria',
		'text'        => $at_text['text'],
	)
) : array();
$at_shown   = '';
foreach ( $at_rows as $at_row ) {
	if ( isset( $at_row['value'] ) && false !== strpos( (string) $at_row['value'], 'WE' ) ) {
		$at_shown = $at_row['value'];
	}
}
if (
	is_wp_error( $at )
	|| isset( $at['fr_max_1'] )
	|| 520 !== (int) $at['width']
	|| 110 !== (int) $at['height']
	|| 2 !== (int) $at['at_max_1']
	|| 6 !== (int) $at['at_max_2']
	|| 9 !== (int) $at['max_chars']
	|| '2 / 6' !== APD_Formats::max_chars_label( $at )
	|| empty( $at['no_frame'] )
	|| ! empty( $at['border_width'] )
	|| empty( $at['band_box']['width'] )
	|| APD_Formats::uses_two_rows( 'austria' )
	|| APD_Formats::offers_frame_choice( 'austria' )
	|| array() !== APD_Formats::color_field_capabilities( 'austria' )
	|| array() !== APD_Formats::shop_swatch_colors( 'austria', 'text', array( 'text' ), array() )
	|| empty( $at_payload['capabilities']['country_locked'] )
	|| $locked_preset_bad( $at_payload, array( 'A', 'AT' ) )
	|| "WE\n000 CF" !== APD_Formats::sample_plate_text( 'austria' )
	|| 'WE 000 CF' !== APD_Formats::admin_sample_plate_text( 'austria' )
	|| '#FFFFFF' !== $at_payload['at_fill']
	|| '#000000' !== $at_payload['at_ink']
	|| 'A' !== $at_payload['at_mark']
	|| array( 2, 6 ) !== array_map( 'intval', $at_payload['at_groups'] )
	|| ! is_array( $at_text )
	|| "WE\n000 CF" !== $at_text['text']
	|| ! is_wp_error( $at_bad )
	|| 'WE 000 CF' !== $at_shown
	|| 500 !== APD_Formats::preview_frame_width( 'austria', 520 )
	|| ! isset( $at['at_region']['width'] )
	|| abs( (float) $at['at_region']['x'] - 33.5 ) > 0.2
	|| abs( (float) $at['at_region']['width'] - 10.9 ) > 0.2
	|| ! isset( $at_payload['text_boxes'] ) || 2 !== count( $at_payload['text_boxes'] )
	|| abs( (float) $at_payload['text_boxes'][0]['y'] - ( 8 / 110 * 100 ) ) > 1.2
	|| isset( $eu_plain['at_region'] )
) {
	fwrite( STDERR, 'AT_FAIL ' . ( is_wp_error( $at ) ? $at->get_error_message() : wp_json_encode( array( $at, $at_payload, $at_text, $at_shown ) ) ) . PHP_EOL );
	exit( 1 );
}

echo 'AT_OK' . PHP_EOL;

$preset_plain = APD_Formats::sanitize(
	array(
		'name'        => 'Plain plate',
		'type'        => 'eu_plain',
		'preset_mode' => 'selected',
		'preset_ids'  => array( 'missing-preset' ),
	)
);
$preset_locked = APD_Formats::sanitize(
	array(
		'name'        => 'French lock',
		'type'        => 'fr',
		'preset_mode' => 'selected',
		'preset_ids'  => array( 'missing-preset' ),
	)
);
$preset_open = APD_Formats::sanitize(
	array(
		'name' => 'Open EU',
		'type' => 'eu',
	)
);
$active_ids = array();
foreach ( APD_Presets::all() as $preset_row ) {
	if ( ! empty( $preset_row['active'] ) && ! empty( $preset_row['id'] ) ) {
		$active_ids[] = (string) $preset_row['id'];
	}
}
$preset_selected = APD_Formats::sanitize(
	array(
		'name'        => 'Chosen EU',
		'type'        => 'eu',
		'preset_mode' => 'selected',
		'preset_ids'  => array( 'missing-preset' ),
	)
);
$preset_one = array();
if ( ! empty( $active_ids ) ) {
	$preset_one = APD_Formats::sanitize(
		array(
			'name'        => 'One EU',
			'type'        => 'eu',
			'preset_mode' => 'selected',
			'preset_ids'  => array( $active_ids[0] ),
		)
	);
}
$preset_one_payload = is_array( $preset_one ) ? APD_Formats::frontend_payload( $preset_one ) : array();
$preset_one_ids     = array();
if ( isset( $preset_one_payload['presets'] ) && is_array( $preset_one_payload['presets'] ) ) {
	foreach ( $preset_one_payload['presets'] as $preset_payload_row ) {
		$preset_one_ids[] = (string) $preset_payload_row['id'];
	}
}
if (
	is_wp_error( $preset_plain )
	|| is_wp_error( $preset_locked )
	|| is_wp_error( $preset_open )
	|| is_wp_error( $preset_selected )
	|| isset( $preset_plain['preset_mode'] )
	|| isset( $preset_locked['preset_mode'] )
	|| isset( $preset_open['preset_mode'] )
	|| isset( $preset_selected['preset_mode'] )
	|| ( ! empty( $active_ids ) && ( ! is_array( $preset_one ) || 'selected' !== $preset_one['preset_mode'] || array( $active_ids[0] ) !== $preset_one_ids ) )
	|| ! APD_Formats::offers_country_choice( 'eu' )
	|| APD_Formats::offers_country_choice( 'fr' )
	|| APD_Formats::offers_country_choice( 'austria' )
	|| APD_Formats::offers_country_choice( 'eu_plain' )
) {
	fwrite( STDERR, 'PRESET_CHOICE_FAIL ' . wp_json_encode( array( $preset_one, $preset_one_ids ) ) . PHP_EOL );
	exit( 1 );
}

echo 'PRESET_CHOICE_OK' . PHP_EOL;

if ( count( $active_ids ) > 1 ) {
	$preset_lead = APD_Formats::sanitize(
		array(
			'name'              => 'Lead EU',
			'type'              => 'eu',
			'preset_mode'       => 'selected',
			'preset_ids'        => array( $active_ids[0], $active_ids[1] ),
			'default_preset_id' => $active_ids[1],
		)
	);
	$preset_lead_payload = is_array( $preset_lead ) ? APD_Formats::frontend_payload( $preset_lead ) : array();
	$preset_lead_first   = '';
	if ( isset( $preset_lead_payload['presets'][0]['id'] ) ) {
		$preset_lead_first = (string) $preset_lead_payload['presets'][0]['id'];
	}
	if (
		! is_array( $preset_lead )
		|| ! isset( $preset_lead['default_preset_id'] )
		|| (string) $preset_lead['default_preset_id'] !== (string) $active_ids[1]
		|| $preset_lead_first !== (string) $active_ids[0]
		|| (string) $active_ids[1] !== APD_Formats::opening_preset_id( $preset_lead_payload['presets'], (string) $preset_lead['default_preset_id'] )
	) {
		fwrite( STDERR, 'PRESET_DEFAULT_FAIL ' . wp_json_encode( array( $preset_lead, $preset_lead_first ) ) . PHP_EOL );
		exit( 1 );
	}
	$preset_offer_order = APD_Formats::presets_with_default_first( $preset_lead_payload['presets'], (string) $preset_lead['default_preset_id'] );
	$preset_offer_first = isset( $preset_offer_order[0]['id'] ) ? (string) $preset_offer_order[0]['id'] : '';
	if ( $preset_offer_first !== (string) $active_ids[1] ) {
		fwrite( STDERR, 'PRESET_OFFER_ORDER_FAIL ' . $preset_offer_first . PHP_EOL );
		exit( 1 );
	}
	echo 'PRESET_DEFAULT_OK' . PHP_EOL;
}
if ( 10.0 !== APD_Formats::plate_corner_radius_mm( 110 ) || abs( APD_Formats::plate_corner_radius_mm( 40 ) - 4.8 ) > 0.001 || 10.0 !== APD_Formats::plate_corner_radius_mm( 90 ) ) {
	fwrite( STDERR, 'PLATE_CORNER_RADIUS_FAIL' . PHP_EOL );
	exit( 1 );
}
$template = (string) file_get_contents( dirname( __DIR__ ) . '/templates/product-configurator.php' );
if ( false === strpos( $template, 'plate_corner_radius_mm' ) || false !== strpos( $template, '10 / max( 1, (int) $format[\'width\'] )' ) || false === strpos( $template, 'opening_preset_id' ) ) {
	fwrite( STDERR, 'PLATE_CORNER_TEMPLATE_FAIL' . PHP_EOL );
	exit( 1 );
}
$preset_rows = array(
	array( 'id' => 'a', 'name' => 'A' ),
	array( 'id' => 'b', 'name' => 'B' ),
	array( 'id' => 'c', 'name' => 'C' ),
);
$preset_moved = APD_Presets::ordered_rows( $preset_rows, array( 'c', 'missing', 'a', 'c' ) );
$preset_moved_ids = array();
foreach ( $preset_moved as $preset_moved_row ) {
	$preset_moved_ids[] = (string) $preset_moved_row['id'];
}
$preset_open_sample = array(
	array( 'id' => 'a' ),
	array( 'id' => 'b' ),
);
if (
	array( 'c', 'a', 'b' ) !== $preset_moved_ids
	|| 'b' !== APD_Formats::opening_preset_id( $preset_open_sample, 'b' )
	|| 'a' !== APD_Formats::opening_preset_id( $preset_open_sample, 'missing' )
	|| 'a' !== APD_Formats::opening_preset_id( $preset_open_sample, '' )
) {
	fwrite( STDERR, 'PRESET_ORDER_FAIL ' . wp_json_encode( $preset_moved_ids ) . PHP_EOL );
	exit( 1 );
}
$admin_preset_tab = (string) file_get_contents( dirname( __DIR__ ) . '/templates/admin/presets-tab.php' );
$admin_product    = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-apd-admin-settings.php' );
$admin_preset_js  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/admin-settings.js' );
if (
	false === strpos( $admin_preset_tab, 'reorder_presets' )
	|| false === strpos( $admin_preset_tab, 'data-apd-preset-sortable' )
	|| false === strpos( $admin_product, 'META_DEFAULT_PRESET' )
	|| false === strpos( $admin_preset_js, 'bindPresetOrder' )
	|| false === strpos( $admin_preset_js, 'toggleProductPreset' )
) {
	fwrite( STDERR, "PRESET_ADMIN_ORDER_FAIL\n" );
	exit( 1 );
}
echo 'PRESET_ORDER_OK' . PHP_EOL;

$format_arranged = APD_Formats::arrange(
	array(
		array( 'id' => 'format-a1', 'name' => 'Alpha' ),
		array( 'id' => 'format-b2', 'name' => 'Beta' ),
		array( 'id' => 'format-c3', 'name' => 'Gamma' ),
	),
	array( 'format:format-c3', 'separator:sep-line1', 'format:format-a1', 'format:missing-id', 'separator:sep-line1' )
);
$format_arranged_ids = array();
foreach ( $format_arranged['formats'] as $format_arranged_row ) {
	$format_arranged_ids[] = (string) $format_arranged_row['id'];
}
$format_layout_types = array();
foreach ( $format_arranged['layout'] as $format_layout_row ) {
	$format_layout_types[] = $format_layout_row['type'] . ':' . $format_layout_row['id'];
}
$format_layout_kept = APD_Formats::layout_without_format( $format_arranged['layout'], 'format-c3' );
$format_tab = (string) file_get_contents( dirname( __DIR__ ) . '/templates/admin/formats-tab.php' );
$format_product = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-apd-admin-settings.php' );
if (
	array( 'format-c3', 'format-a1', 'format-b2' ) !== $format_arranged_ids
	|| 'Gamma' !== $format_arranged['formats'][0]['name']
	|| array( 'separator:sep-line1', 'format:format-a1', 'format:format-b2' ) !== array(
		$format_layout_kept[0]['type'] . ':' . $format_layout_kept[0]['id'],
		$format_layout_kept[1]['type'] . ':' . $format_layout_kept[1]['id'],
		$format_layout_kept[2]['type'] . ':' . $format_layout_kept[2]['id'],
	)
	|| array( 'format:format-c3', 'separator:sep-line1', 'format:format-a1', 'format:format-b2' ) !== $format_layout_types
	|| false === strpos( $format_tab, 'data-apd-format-sortable' )
	|| false === strpos( $format_tab, 'apd-format-separator' )
	|| false === strpos( $format_tab, 'add_format_separator' )
	|| false === strpos( $format_tab, 'shop_product_counts_by_format' )
	|| false === strpos( $format_tab, 'data-apd-format-products' )
	|| false === strpos( $format_tab, 'colspan="8"' )
	|| false === strpos( $format_tab, 'colspan="7"' )
	|| false === strpos( $format_product, 'reorder_formats' )
	|| false === strpos( $format_product, 'function shop_product_counts_by_format' )
) {
	fwrite( STDERR, 'FORMAT_ORDER_FAIL ' . wp_json_encode( array( $format_arranged_ids, $format_layout_types ) ) . PHP_EOL );
	exit( 1 );
}
echo 'FORMAT_ORDER_OK' . PHP_EOL;

if ( count( $active_ids ) >= 1 ) {
	$product_format = APD_Formats::save(
		array(
			'name'              => 'APD product opening preset',
			'type'              => 'eu',
			'preset_mode'       => 'selected',
			'preset_ids'        => array( $active_ids[0] ),
			'default_preset_id' => $active_ids[0],
		)
	);
	$product_post = 0;
	$product_saved = '';
	$product_cleared = 'kept';
	$product_html = '';
	$product_lock_untouched = 'missing';
	$product_lock_on = '';
	$product_lock_cleared = 'kept';
	$product_hide_untouched = 'missing';
	$product_hide_on = '';
	$product_hide_cleared = 'kept';
	if ( ! is_wp_error( $product_format ) ) {
		$product_post = wp_insert_post(
			array(
				'post_type'   => 'product',
				'post_status' => 'draft',
				'post_title'  => 'APD product opening preset',
			)
		);
		if ( $product_post && ! is_wp_error( $product_post ) ) {
			update_post_meta( $product_post, APD_Admin_Settings::META_ENABLED, 'yes' );
			update_post_meta( $product_post, APD_Admin_Settings::META_FORMAT, $product_format['id'] );
			ob_start();
			APD_Admin_Settings::instance()->render_product_meta_box( get_post( $product_post ) );
			$product_html = ob_get_clean();
			$admin_users = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			);
			if ( ! empty( $admin_users ) ) {
				wp_set_current_user( (int) $admin_users[0]->ID );
				$_POST['apd_product_nonce']     = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				$_POST['apd_enabled']           = '1';
				$_POST['apd_format_id']         = $product_format['id'];
				$_POST['apd_default_preset_id'] = (string) $active_ids[0];
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_saved = (string) get_post_meta( $product_post, APD_Admin_Settings::META_DEFAULT_PRESET, true );
				$_POST['apd_product_nonce']     = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				$_POST['apd_default_preset_id'] = 'not-a-preset';
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_cleared = (string) get_post_meta( $product_post, APD_Admin_Settings::META_DEFAULT_PRESET, true );
				$product_lock_untouched = (string) get_post_meta( $product_post, APD_Admin_Settings::META_LOCK_COLORS, true );
				$product_hide_untouched = (string) get_post_meta( $product_post, APD_Admin_Settings::META_HIDE_PLAIN_FRAME, true );
				$_POST['apd_product_nonce'] = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				$_POST['apd_lock_colors']   = '1';
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_lock_on = (string) get_post_meta( $product_post, APD_Admin_Settings::META_LOCK_COLORS, true );
				unset( $_POST['apd_lock_colors'] );
				$_POST['apd_product_nonce'] = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_lock_cleared = (string) get_post_meta( $product_post, APD_Admin_Settings::META_LOCK_COLORS, true );
				$_POST['apd_product_nonce']    = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				$_POST['apd_hide_plain_frame'] = '1';
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_hide_on = (string) get_post_meta( $product_post, APD_Admin_Settings::META_HIDE_PLAIN_FRAME, true );
				unset( $_POST['apd_hide_plain_frame'] );
				$_POST['apd_product_nonce'] = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
				APD_Admin_Settings::instance()->save_product_meta( $product_post );
				$product_hide_cleared = (string) get_post_meta( $product_post, APD_Admin_Settings::META_HIDE_PLAIN_FRAME, true );
				unset( $_POST['apd_product_nonce'], $_POST['apd_enabled'], $_POST['apd_format_id'], $_POST['apd_default_preset_id'] );
			}
			wp_delete_post( $product_post, true );
		}
		APD_Formats::delete( $product_format['id'] );
	}
	if (
		is_wp_error( $product_format )
		|| ! $product_post
		|| false === strpos( $product_html, 'name="apd_default_preset_id"' )
		|| false === strpos( $product_html, 'name="apd_lock_colors"' )
		|| false !== strpos( $product_html, 'name="apd_lock_colors" value="1" checked' )
		|| false === strpos( $product_html, 'name="apd_hide_plain_frame"' )
		|| false !== strpos( $product_html, 'name="apd_hide_plain_frame" value="1" checked' )
		|| $product_saved !== (string) $active_ids[0]
		|| '' !== $product_cleared
		|| '' !== $product_lock_untouched
		|| 'yes' !== $product_lock_on
		|| '' !== $product_lock_cleared
		|| '' !== $product_hide_untouched
		|| 'yes' !== $product_hide_on
		|| '' !== $product_hide_cleared
	) {
		fwrite( STDERR, 'PRODUCT_PRESET_FAIL ' . wp_json_encode( array( $product_format, $product_saved, $product_cleared ) ) . PHP_EOL );
		exit( 1 );
	}
	echo 'PRODUCT_PRESET_OK' . PHP_EOL;
}

$usage_format = APD_Formats::save(
	array(
		'name' => 'APD shop usage',
		'type' => 'eu',
	)
);
$usage_other = APD_Formats::save(
	array(
		'name' => 'APD shop usage other',
		'type' => 'eu',
	)
);
$usage_posts = array();
$usage_insert = static function ( $status, $format_id ) use ( &$usage_posts ) {
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => $status,
			'post_title'  => 'APD shop usage ' . $status,
		)
	);

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return 0;
	}

	$usage_posts[] = (int) $post_id;
	update_post_meta( (int) $post_id, APD_Admin_Settings::META_FORMAT, $format_id );

	return (int) $post_id;
};

if ( ! is_wp_error( $usage_format ) && ! is_wp_error( $usage_other ) ) {
	$usage_insert( 'publish', $usage_format['id'] );
	$usage_insert( 'publish', $usage_format['id'] );
	$usage_insert( 'draft', $usage_format['id'] );
	$usage_insert( 'private', $usage_format['id'] );
	$usage_trashed = $usage_insert( 'publish', $usage_format['id'] );
	if ( $usage_trashed ) {
		wp_trash_post( $usage_trashed );
	}
	$usage_insert( 'publish', $usage_other['id'] );
}

$usage_counts = APD_Admin_Settings::shop_product_counts_by_format();
$usage_count  = ( ! is_wp_error( $usage_format ) && isset( $usage_counts[ $usage_format['id'] ] ) ) ? (int) $usage_counts[ $usage_format['id'] ] : 0;
$other_count  = ( ! is_wp_error( $usage_other ) && isset( $usage_counts[ $usage_other['id'] ] ) ) ? (int) $usage_counts[ $usage_other['id'] ] : 0;

foreach ( $usage_posts as $usage_post ) {
	wp_delete_post( $usage_post, true );
}

if ( ! is_wp_error( $usage_format ) ) {
	APD_Formats::delete( $usage_format['id'] );
}

if ( ! is_wp_error( $usage_other ) ) {
	APD_Formats::delete( $usage_other['id'] );
}

if ( is_wp_error( $usage_format ) || is_wp_error( $usage_other ) || 2 !== $usage_count || 1 !== $other_count ) {
	fwrite( STDERR, 'FORMAT_USAGE_FAIL ' . wp_json_encode( array( $usage_count, $other_count ) ) . PHP_EOL );
	exit( 1 );
}

echo 'FORMAT_USAGE_OK' . PHP_EOL;

echo 'ALL_OK' . PHP_EOL;
