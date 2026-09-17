<?php
/**
 * Stage 3 sanity checks (formats CRUD + sanitization).
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

$format = APD_Formats::save(
	array(
		'name'             => 'EU Standard',
		'type'             => 'eu',
		'width'            => 520,
		'height'           => 110,
		'border_width'     => 8,
		'border_color'     => '#000000',
		'band_ratio'       => 0.15,
		'band_side'        => 'left',
		'price_adjustment' => 0,
	)
);

if ( is_wp_error( $format ) ) {
	fwrite( STDERR, 'FORMAT_ERR ' . $format->get_error_message() . PHP_EOL );
	exit( 1 );
}

echo 'FORMAT_OK ' . $format['id'] . PHP_EOL;

if ( ! isset( $format['text_box']['x'], $format['text_box']['width'], $format['text_box']['align'] ) ) {
	fwrite( STDERR, "FORMAT_TEXT_BOX_FAIL\n" );
	exit( 1 );
}

$preset = APD_Presets::sanitize(
	array(
		'name'                 => 'Germany',
		'country_code'         => 'DE',
		'image_id'             => 0,
		'band_ratio'           => 0.15,
		'side'                 => 'left',
		'active'               => 1,
		'allowed_format_types' => array( 'eu' ),
	)
);

if ( ! is_wp_error( $preset ) ) {
	fwrite( STDERR, "PRESET_SHOULD_REQUIRE_IMAGE\n" );
	exit( 1 );
}

echo 'PRESET_NO_IMAGE ' . $preset->get_error_code() . PHP_EOL;

$bad = APD_Formats::sanitize(
	array(
		'name' => '<b>Hack</b>',
		'type' => 'eu',
	)
);

if ( ! is_wp_error( $bad ) ) {
	fwrite( STDERR, "LABEL_FAIL_OPEN\n" );
	exit( 1 );
}

echo 'LABEL_REJECTED' . PHP_EOL;

$us = APD_Formats::sanitize(
	array(
		'name'          => 'US passenger',
		'type'          => 'us',
		'base_image_id' => 0,
	)
);

if ( is_wp_error( $us ) || 0 !== (int) $us['base_image_id'] ) {
	fwrite( STDERR, "US_OPTIONAL_IMAGE_FAIL\n" );
	exit( 1 );
}

echo 'US_OPTIONAL_IMAGE_OK' . PHP_EOL;

$holder = APD_Formats::sanitize(
	array(
		'name'          => 'Grey holder',
		'type'          => 'holder',
		'base_image_id' => 0,
	)
);

if ( ! is_wp_error( $holder ) ) {
	fwrite( STDERR, "HOLDER_SHOULD_REQUIRE_IMAGE\n" );
	exit( 1 );
}

echo 'HOLDER_REQUIRES_IMAGE' . PHP_EOL;

if ( true !== APD_Security::validate_format_type( 'holder' ) ) {
	fwrite( STDERR, "HOLDER_TYPE_REJECTED\n" );
	exit( 1 );
}

echo 'HOLDER_TYPE_OK' . PHP_EOL;

$eu_chars = APD_Formats::sanitize(
	array(
		'name'      => 'EU Chars',
		'type'      => 'eu',
		'max_chars' => 7,
	)
);

if ( is_wp_error( $eu_chars ) || 7 !== (int) $eu_chars['max_chars'] ) {
	fwrite( STDERR, "MAX_CHARS_FAIL\n" );
	exit( 1 );
}

echo 'MAX_CHARS_OK' . PHP_EOL;

$payload = APD_Formats::frontend_payload( $eu_chars );

if ( 7 !== (int) $payload['max_chars'] || 'eu' !== $payload['type'] ) {
	fwrite( STDERR, "PAYLOAD_FAIL\n" );
	exit( 1 );
}

echo 'PAYLOAD_OK' . PHP_EOL;

$eu_ratio = APD_Formats::eu_band_ratio( 520, 110 );
if ( abs( $eu_ratio - ( 40 / 520 ) ) > 0.0002 || ! isset( $payload['eu_band_ratio'] ) || abs( (float) $payload['eu_band_ratio'] - $eu_ratio ) > 0.0002 ) {
	fwrite( STDERR, "EU_BAND_RATIO_FAIL\n" );
	exit( 1 );
}

echo 'EU_BAND_RATIO_OK' . PHP_EOL;

if ( ! isset( $payload['text_box']['x'], $payload['text_box']['height'] ) ) {
	fwrite( STDERR, "PAYLOAD_TEXT_BOX_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'us' ) || APD_Formats::uses_country_band( 'holder' ) || APD_Formats::uses_country_band( 'custom' ) || APD_Formats::uses_country_band( 'color' ) || APD_Formats::uses_country_band( 'suv' ) ) {
	fwrite( STDERR, "COUNTRY_BAND_NON_EU_FAIL\n" );
	exit( 1 );
}

if ( ! APD_Formats::uses_country_band( 'eu' ) || ! APD_Formats::uses_country_band( 'moto' ) ) {
	fwrite( STDERR, "COUNTRY_BAND_EU_FAIL\n" );
	exit( 1 );
}

$us_payload = APD_Formats::frontend_payload(
	array(
		'id'               => 'us-test',
		'name'             => 'US',
		'type'             => 'us',
		'width'            => 600,
		'height'           => 300,
		'border_width'     => 0,
		'border_color'     => '#000000',
		'band_ratio'       => 0.15,
		'band_side'        => 'left',
		'base_image_id'    => 0,
		'font_ids'         => array(),
		'max_chars'        => 8,
		'price_adjustment' => 0,
	)
);

if ( ! empty( $us_payload['presets'] ) || ! isset( $us_payload['designs'] ) || ! is_array( $us_payload['designs'] ) ) {
	fwrite( STDERR, "US_PRESETS_FAIL\n" );
	exit( 1 );
}

foreach ( $us_payload['designs'] as $design_row ) {
	if ( ! isset( $design_row['text_box']['x'], $design_row['text_box']['width'] ) ) {
		fwrite( STDERR, "US_TEXT_BOX_PAYLOAD_FAIL\n" );
		exit( 1 );
	}
}

echo 'US_NO_PRESETS_OK' . PHP_EOL;

if ( ! APD_Formats::uses_plate_designs( 'us' ) || APD_Formats::uses_plate_designs( 'eu' ) || APD_Formats::uses_plate_designs( 'holder' ) || APD_Formats::uses_plate_designs( 'suv' ) ) {
	fwrite( STDERR, "US_DESIGNS_TYPE_FAIL\n" );
	exit( 1 );
}

$no_design_image = APD_Designs::sanitize(
	array(
		'name'   => 'Arizona',
		'code'   => 'AZ',
		'image_id' => 0,
		'all_us' => 1,
		'active' => 1,
	)
);

if ( ! is_wp_error( $no_design_image ) ) {
	fwrite( STDERR, "DESIGN_SHOULD_REQUIRE_IMAGE\n" );
	exit( 1 );
}

echo 'DESIGN_NO_IMAGE_OK' . PHP_EOL;

$clamped_box = APD_Designs::sanitize_text_box(
	array(
		'x'      => -4,
		'y'      => 90,
		'width'  => 40,
		'height' => 40,
		'align'  => 'justify',
		'valign' => 'center',
	)
);

if ( 0.0 !== $clamped_box['x'] || 90.0 !== $clamped_box['y'] || 10.0 !== $clamped_box['height'] || 'center' !== $clamped_box['align'] || 'middle' !== $clamped_box['valign'] ) {
	fwrite( STDERR, "TEXT_BOX_CLAMP_FAIL\n" );
	exit( 1 );
}

if ( APD_Designs::default_text_box() !== APD_Designs::sanitize_text_box( array() ) ) {
	fwrite( STDERR, "TEXT_BOX_DEFAULT_FAIL\n" );
	exit( 1 );
}

echo 'TEXT_BOX_OK' . PHP_EOL;

$eu_default_box = APD_Formats::default_text_box(
	'eu',
	array(
		'band_ratio' => 0.15,
		'band_side'  => 'left',
	)
);

if ( 18.0 !== $eu_default_box['x'] || 79.0 !== $eu_default_box['width'] || 19.0 !== $eu_default_box['y'] ) {
	fwrite( STDERR, "FORMAT_TEXT_BOX_DEFAULT_FAIL\n" );
	exit( 1 );
}

echo 'FORMAT_TEXT_BOX_OK' . PHP_EOL;

$settings        = APD_Plugin::get_settings();
$backup_designs  = isset( $settings['designs'] ) && is_array( $settings['designs'] ) ? $settings['designs'] : array();
$settings['designs'] = array(
	array(
		'id'                   => 'd-all',
		'name'                 => 'Arizona',
		'code'                 => 'AZ',
		'image_id'             => 1,
		'active'               => true,
		'allowed_format_types' => array( 'us' ),
		'allowed_format_ids'   => array(),
	),
	array(
		'id'                   => 'd-one',
		'name'                 => 'California',
		'code'                 => 'CA',
		'image_id'             => 1,
		'active'               => true,
		'allowed_format_types' => array( 'us' ),
		'allowed_format_ids'   => array( 'fmt-car' ),
	),
	array(
		'id'                   => 'd-off',
		'name'                 => 'Oklahoma',
		'code'                 => 'OK',
		'image_id'             => 1,
		'active'               => false,
		'allowed_format_types' => array( 'us' ),
		'allowed_format_ids'   => array(),
	),
);
APD_Plugin::save_settings( $settings );

$car_ids  = wp_list_pluck( APD_Designs::active_for_format( array( 'id' => 'fmt-car', 'type' => 'us' ) ), 'id' );
$moto_ids = wp_list_pluck( APD_Designs::active_for_format( array( 'id' => 'fmt-moto', 'type' => 'us' ) ), 'id' );
$eu_ids   = APD_Designs::active_for_format( array( 'id' => 'fmt-eu', 'type' => 'eu' ) );

$settings            = APD_Plugin::get_settings();
$settings['designs'] = $backup_designs;
APD_Plugin::save_settings( $settings );

if ( array( 'd-all', 'd-one' ) !== array_values( $car_ids ) ) {
	fwrite( STDERR, "DESIGN_CAR_SCOPE_FAIL\n" );
	exit( 1 );
}

if ( array( 'd-all' ) !== array_values( $moto_ids ) ) {
	fwrite( STDERR, "DESIGN_MOTO_SCOPE_FAIL\n" );
	exit( 1 );
}

if ( ! empty( $eu_ids ) ) {
	fwrite( STDERR, "DESIGN_EU_SCOPE_FAIL\n" );
	exit( 1 );
}

echo 'DESIGN_SCOPE_OK' . PHP_EOL;

$defs = APD_Catalog::definitions();

if ( empty( $defs['holders']['kind'] ) || 'holder' !== $defs['holders']['kind'] ) {
	fwrite( STDERR, "CATALOG_FAIL\n" );
	exit( 1 );
}

if ( empty( $defs['plates-color']['kind'] ) || 'color' !== $defs['plates-color']['kind'] ) {
	fwrite( STDERR, "CATALOG_COLOR_FAIL\n" );
	exit( 1 );
}

if ( empty( $defs['plates-moto']['kind'] ) || 'moto' !== $defs['plates-moto']['kind'] ) {
	fwrite( STDERR, "CATALOG_MOTO_FAIL\n" );
	exit( 1 );
}

if ( empty( $defs['plates-suv']['kind'] ) || 'suv' !== $defs['plates-suv']['kind'] ) {
	fwrite( STDERR, "CATALOG_SUV_FAIL\n" );
	exit( 1 );
}

if ( true !== APD_Security::validate_format_type( 'color' ) ) {
	fwrite( STDERR, "COLOR_TYPE_VALIDATE_FAIL\n" );
	exit( 1 );
}

$color_saved = APD_Formats::save(
	array(
		'name'   => 'Color plate test',
		'type'   => 'color',
		'width'  => 520,
		'height' => 110,
	)
);

if ( is_wp_error( $color_saved ) || 520 !== (int) $color_saved['width'] || 110 !== (int) $color_saved['height'] ) {
	fwrite( STDERR, "COLOR_FORMAT_SAVE_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'color' ) || ! APD_Formats::uses_painted_plate( 'color' ) ) {
	fwrite( STDERR, "COLOR_TYPE_FLAGS_FAIL\n" );
	exit( 1 );
}

$color_caps = APD_Formats::color_field_capabilities( 'color' );
$color_def  = APD_Formats::default_color_fields( 'color' );

if ( array( 'text', 'border', 'background' ) !== $color_caps || array( 'text', 'border', 'background' ) !== $color_def ) {
	fwrite( STDERR, "COLOR_FIELDS_FAIL\n" );
	exit( 1 );
}

$color_payload = APD_Formats::frontend_payload( $color_saved );

if ( ! empty( $color_payload['presets'] ) || ! empty( $color_payload['multiline'] ) || 'color' !== $color_payload['type'] ) {
	fwrite( STDERR, "COLOR_PAYLOAD_FAIL\n" );
	exit( 1 );
}

APD_Formats::delete( $color_saved['id'] );

echo 'COLOR_TYPE_OK' . PHP_EOL;

echo 'CATALOG_OK' . PHP_EOL;

$deleted = APD_Formats::delete( $format['id'] );

if ( true !== $deleted ) {
	fwrite( STDERR, "CLEANUP_FAIL\n" );
	exit( 1 );
}

echo 'CLEANUP_OK' . PHP_EOL;

if ( '#FFFFFF' !== strtoupper( APD_Plugin::palette_preferred_hex( 'background', array( '#FFFFFF' ) ) ) ) {
	fwrite( STDERR, "PALETTE_WHITE_FAIL\n" );
	exit( 1 );
}

echo 'PALETTE_WHITE_OK' . PHP_EOL;

$eu_caps = APD_Formats::color_field_capabilities( 'eu' );
$eu_def  = APD_Formats::default_color_fields( 'eu' );

if ( ! in_array( 'background', $eu_caps, true ) || in_array( 'background', $eu_def, true ) ) {
	fwrite( STDERR, "EU_COLOR_FIELDS_FAIL\n" );
	exit( 1 );
}

$eu_bg = APD_Formats::sanitize_color_fields( array( 'text', 'border', 'background' ), 'eu' );

if ( array( 'text', 'border', 'background' ) !== $eu_bg ) {
	fwrite( STDERR, "EU_BG_ALLOW_FAIL\n" );
	exit( 1 );
}

$us_bg = APD_Formats::sanitize_color_fields( array( 'text', 'border', 'background' ), 'us' );

if ( array( 'text' ) !== $us_bg ) {
	fwrite( STDERR, "US_COLOR_FIELDS_FAIL\n" );
	exit( 1 );
}

$palettes = APD_Plugin::get_settings()['palettes'];
$library  = APD_Plugin::get_settings()['color_library'];
$named    = APD_Plugin::get_settings()['color_palettes'];

if ( isset( $palettes['mode'] ) || isset( $palettes['shared'] ) || empty( $palettes['text'] ) || empty( $library ) || empty( $named ) ) {
	fwrite( STDERR, "PALETTE_SHAPE_FAIL\n" );
	exit( 1 );
}

$named_ids = array();

foreach ( $named as $palette ) {
	if ( isset( $palette['id'] ) ) {
		$named_ids[] = $palette['id'];
	}
}

if ( ! in_array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'], $named_ids, true ) ) {
	fwrite( STDERR, "NAMED_PALETTE_FAIL\n" );
	exit( 1 );
}

$us_palettes = APD_Color_Palettes::sanitize_product_ids(
	array(
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['border'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['background'],
	),
	'us'
);

if ( array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] ) !== array_values( $us_palettes ) ) {
	fwrite( STDERR, "US_PRODUCT_PALETTE_FAIL\n" );
	exit( 1 );
}

$romania = APD_Color_Palettes::colors_for_product(
	array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['background'] ),
	'background'
);

if ( empty( $romania ) ) {
	fwrite( STDERR, "PRODUCT_COLORS_FAIL\n" );
	exit( 1 );
}

echo 'COLOR_MODEL_OK' . PHP_EOL;

$clamped = APD_Color_Palettes::sanitize_swatch_display(
	array(
		'size'    => 3,
		'shape'   => 'hexagon',
		'radius' => 99,
	)
);

if ( 10 !== $clamped['size'] || 'circle' !== $clamped['shape'] || 50 !== $clamped['radius'] || '#000000' !== $clamped['border_color'] ) {
	fwrite( STDERR, "SWATCH_CLAMP_FAIL\n" );
	exit( 1 );
}

$backup_swatch = APD_Color_Palettes::swatch_display();
APD_Color_Palettes::save_swatch_display(
	array(
		'size'         => 48,
		'shape'        => 'rounded',
		'radius'       => 8,
		'border_color' => '#1A2B3C',
	)
);

$saved_swatch = APD_Color_Palettes::swatch_display();

if ( 48 !== $saved_swatch['size'] || 'rounded' !== $saved_swatch['shape'] || 8 !== $saved_swatch['radius'] || '8px' !== APD_Color_Palettes::swatch_radius_css( $saved_swatch ) || '#1A2B3C' !== $saved_swatch['border_color'] ) {
	fwrite( STDERR, "SWATCH_SAVE_FAIL\n" );
	APD_Color_Palettes::save_swatch_display( $backup_swatch );
	exit( 1 );
}

APD_Color_Palettes::save_swatch_display(
	array(
		'size'  => 48,
		'shape' => 'square',
	)
);

$square_swatch = APD_Color_Palettes::swatch_display();

if ( 'square' !== $square_swatch['shape'] || 8 !== $square_swatch['radius'] || '0' !== APD_Color_Palettes::swatch_radius_css( $square_swatch ) || '#1A2B3C' !== $square_swatch['border_color'] ) {
	fwrite( STDERR, "SWATCH_SQUARE_FAIL\n" );
	APD_Color_Palettes::save_swatch_display( $backup_swatch );
	exit( 1 );
}

APD_Color_Palettes::save_swatch_display( $backup_swatch );

echo 'SWATCH_DISPLAY_OK' . PHP_EOL;

$settings        = APD_Plugin::get_settings();
$backup_library  = $settings['color_library'];
$backup_named    = $settings['color_palettes'];
$backup_palettes = $settings['palettes'];
$probe           = isset( $backup_library[0] ) && is_array( $backup_library[0] ) ? $backup_library[0] : array();
$probe_hex       = isset( $probe['hex'] ) ? (string) $probe['hex'] : '';
$probe_label     = isset( $probe['label'] ) ? (string) $probe['label'] : '';

if ( '' === $probe_hex || '' === $probe_label ) {
	fwrite( STDERR, "PALETTE_PROBE_FAIL\n" );
	exit( 1 );
}

$settings['color_library'][] = array(
	'id'    => 'dup-hex',
	'hex'   => '#1b365d',
	'label' => 'Navy copy',
);
$settings['color_library'][] = array(
	'id'    => 'dup-label',
	'hex'   => '#00AA00',
	'label' => 'Black',
);
APD_Plugin::save_settings( $settings );

$library_colors = APD_Color_Palettes::library();
$hexes          = array();
$labels         = array();

foreach ( $library_colors as $color ) {
	$hexes[]  = strtoupper( $color['hex'] );
	$labels[] = strtolower( $color['label'] );
}

$settings                   = APD_Plugin::get_settings();
$settings['color_library']  = $backup_library;
$settings['color_palettes'] = $backup_named;
$settings['palettes']       = $backup_palettes;
APD_Plugin::save_settings( $settings );

if ( count( $hexes ) !== count( array_unique( $hexes ) ) || count( $labels ) !== count( array_unique( $labels ) ) ) {
	fwrite( STDERR, "PALETTE_DUP_KEEP_FAIL\n" );
	exit( 1 );
}

if ( ! APD_Plugin::palette_list_has_entry( $library_colors, $probe_hex, 'Other' ) || ! APD_Plugin::palette_list_has_entry( $library_colors, '#ABCDEF', $probe_label ) ) {
	fwrite( STDERR, "PALETTE_DUP_DETECT_FAIL\n" );
	exit( 1 );
}

if ( APD_Plugin::palette_list_has_entry( $library_colors, '#ABCDEF', 'Brand new' ) ) {
	fwrite( STDERR, "PALETTE_DUP_FALSE_FAIL\n" );
	exit( 1 );
}

$dup_hex = APD_Color_Palettes::save_library_color( $probe_hex, 'Ink' );

if ( ! is_wp_error( $dup_hex ) ) {
	fwrite( STDERR, "LIBRARY_DUP_HEX_FAIL\n" );
	exit( 1 );
}

$dup_label = APD_Color_Palettes::save_library_color( '#00AA00', $probe_label );

if ( ! is_wp_error( $dup_label ) ) {
	fwrite( STDERR, "LIBRARY_DUP_LABEL_FAIL\n" );
	exit( 1 );
}

$edit_id = APD_Color_Palettes::save_library_color( '#010101', 'Ink test' );

if ( is_wp_error( $edit_id ) || '' === $edit_id ) {
	fwrite( STDERR, "LIBRARY_ADD_FAIL\n" );
	exit( 1 );
}

$renamed = APD_Color_Palettes::save_library_color( '#010101', 'Ink dark', $edit_id );

if ( is_wp_error( $renamed ) || $renamed !== $edit_id ) {
	fwrite( STDERR, "LIBRARY_EDIT_FAIL\n" );
	exit( 1 );
}

$edited = APD_Color_Palettes::get_color( $edit_id );

if ( ! is_array( $edited ) || 'Ink dark' !== $edited['label'] ) {
	fwrite( STDERR, "LIBRARY_EDIT_LABEL_FAIL\n" );
	exit( 1 );
}

$steal = APD_Color_Palettes::save_library_color( $probe_hex, 'Ink steal', $edit_id );

if ( ! is_wp_error( $steal ) ) {
	fwrite( STDERR, "LIBRARY_EDIT_STEAL_FAIL\n" );
	exit( 1 );
}

$deleted = APD_Color_Palettes::delete_color( $edit_id );

if ( true !== $deleted ) {
	fwrite( STDERR, "LIBRARY_DELETE_FAIL\n" );
	exit( 1 );
}

$readded = APD_Color_Palettes::save_library_color( '#010101', 'Ink dark' );

if ( is_wp_error( $readded ) ) {
	fwrite( STDERR, "LIBRARY_READD_FAIL\n" );
	exit( 1 );
}

$attached = APD_Color_Palettes::attach_color_to_palettes(
	$readded,
	array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] )
);

if ( true !== $attached ) {
	fwrite( STDERR, "LIBRARY_ATTACH_FAIL\n" );
	exit( 1 );
}

$text_after = APD_Color_Palettes::colors_for_product(
	array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] ),
	'text'
);
$has_ink    = false;

foreach ( $text_after as $color ) {
	if ( 0 === strcasecmp( $color['hex'], '#010101' ) ) {
		$has_ink = true;
		break;
	}
}

if ( ! $has_ink ) {
	fwrite( STDERR, "LIBRARY_ATTACH_MISSING_FAIL\n" );
	exit( 1 );
}

APD_Color_Palettes::delete_color( $readded );

$settings                   = APD_Plugin::get_settings();
$settings['color_library']  = $backup_library;
$settings['color_palettes'] = $backup_named;
$settings['palettes']       = $backup_palettes;
APD_Plugin::save_settings( $settings );

echo 'PALETTE_DUP_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
