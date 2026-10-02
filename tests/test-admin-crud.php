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

if ( is_wp_error( $us ) || 0 !== (int) $us['base_image_id'] || ! APD_Formats::uses_base_image( 'us' ) || array( 'text' ) !== APD_Formats::palette_slot_keys_for( 'us' ) ) {
	fwrite( STDERR, "US_OPTIONAL_IMAGE_FAIL\n" );
	exit( 1 );
}

$us_palette = APD_Formats::sanitize(
	array(
		'name'          => 'US with text palette',
		'type'          => 'us',
		'palette_slots' => array(
			'text' => APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'],
		),
	)
);

if ( is_wp_error( $us_palette ) || APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] !== $us_palette['palette_slots']['text'] || isset( $us_palette['palette_slots']['background'] ) ) {
	fwrite( STDERR, "US_TEXT_PALETTE_FAIL\n" );
	exit( 1 );
}

echo 'US_OPTIONAL_IMAGE_OK' . PHP_EOL;

$us_split = APD_Formats::sanitize(
	array(
		'name'             => 'US split plate',
		'type'             => 'us',
		'split_text'       => '1',
		'max_chars_left'   => 3,
		'max_chars_right'  => 4,
		'text_box'         => array(
			'x'      => 8,
			'y'      => 40,
			'width'  => 36,
			'height' => 30,
			'align'  => 'center',
			'valign' => 'middle',
		),
		'text_box_right'   => array(
			'x'      => 52,
			'y'      => 10,
			'width'  => 34,
			'height' => 12,
			'align'  => 'center',
			'valign' => 'top',
		),
	)
);

$us_split_payload = is_wp_error( $us_split ) ? array() : APD_Formats::frontend_payload( $us_split );

if (
	is_wp_error( $us_split )
	|| empty( $us_split['split_text'] )
	|| 40.0 !== (float) $us_split['text_box_right']['y']
	|| 30.0 !== (float) $us_split['text_box_right']['height']
	|| (float) $us_split['text_box_right']['x'] < (float) $us_split['text_box']['x'] + (float) $us_split['text_box']['width']
	|| 7 !== (int) $us_split['max_chars']
	|| '3 + 4' !== APD_Formats::max_chars_label( $us_split )
	|| empty( $us_split_payload['split_text'] )
	|| array( 3, 4 ) !== $us_split_payload['side_max_chars']
	|| array( 3, 4 ) !== APD_Formats::active_us_side_limits(
		array(
			'type'            => 'us',
			'base_image_url'  => 'http://example.com/plate.png',
			'split_text'      => true,
			'side_max_chars'  => array( 3, 4 ),
		)
	)
	|| null !== APD_Formats::active_us_side_limits(
		array(
			'type'           => 'us',
			'base_image_url' => '',
			'split_text'     => true,
			'side_max_chars' => array( 3, 4 ),
			'designs'        => array(
				array(
					'id'         => 'az',
					'split_text' => false,
				),
			),
		)
	)
	|| array( 2, 5 ) !== APD_Formats::active_us_side_limits(
		array(
			'type'           => 'us',
			'base_image_url' => '',
			'designs'        => array(
				array(
					'id'             => 'ny',
					'split_text'     => true,
					'side_max_chars' => array( 2, 5 ),
				),
			),
		)
	)
) {
	fwrite( STDERR, 'US_SPLIT_FAIL ' . ( is_wp_error( $us_split ) ? $us_split->get_error_message() : wp_json_encode( $us_split ) ) . PHP_EOL );
	exit( 1 );
}

$us_single = APD_Formats::sanitize(
	array(
		'name' => 'US single plate',
		'type' => 'us',
	)
);

if ( is_wp_error( $us_single ) || ! empty( $us_single['split_text'] ) || null !== APD_Formats::active_us_side_limits( APD_Formats::frontend_payload( $us_single ) ) ) {
	fwrite( STDERR, "US_SINGLE_SPLIT_FAIL\n" );
	exit( 1 );
}

echo 'US_SPLIT_OK' . PHP_EOL;

$holder = APD_Formats::sanitize(
	array(
		'name'          => 'Grey holder',
		'type'          => 'holder',
		'base_image_id' => 0,
		'width'         => 800,
		'height'        => 400,
	)
);

if ( is_wp_error( $holder ) || 0 !== (int) $holder['base_image_id'] || 520 !== (int) $holder['width'] || 260 !== (int) $holder['height'] ) {
	fwrite( STDERR, "HOLDER_OPTIONAL_IMAGE_FAIL\n" );
	exit( 1 );
}

if ( abs( (float) $holder['text_box']['y'] - 77.3 ) > 0.1 ) {
	fwrite( STDERR, "HOLDER_STRIP_BOX_FAIL\n" );
	exit( 1 );
}

echo 'HOLDER_OPTIONAL_IMAGE' . PHP_EOL;

$open_fonts = APD_Formats::sanitize(
	array(
		'name'         => 'Open fonts',
		'type'         => 'holder',
		'font_ids_all' => '1',
		'font_ids'     => array( 'missing-font' ),
	)
);
$known_fonts = array();

foreach ( APD_Plugin::get_settings()['fonts'] as $font ) {
	if ( ! empty( $font['id'] ) ) {
		$known_fonts[] = (string) $font['id'];
	}
}

sort( $known_fonts );
$saved_fonts = is_array( $open_fonts ) && isset( $open_fonts['font_ids'] ) ? $open_fonts['font_ids'] : array();
sort( $saved_fonts );

if ( is_wp_error( $open_fonts ) || empty( $open_fonts['font_ids_all'] ) || $known_fonts !== $saved_fonts ) {
	fwrite( STDERR, "FONT_FOLLOW_SAVE_FAIL\n" );
	exit( 1 );
}

$closed_fonts = APD_Formats::sanitize(
	array(
		'name'     => 'Closed fonts',
		'type'     => 'holder',
		'font_ids' => array(),
	)
);
$granted = APD_Formats::grant_font_to_following_formats( array( $open_fonts, $closed_fonts ), 'font-new' );

if (
	is_wp_error( $closed_fonts )
	|| ! empty( $closed_fonts['font_ids_all'] )
	|| ! in_array( 'font-new', $granted[0]['font_ids'], true )
	|| in_array( 'font-new', $granted[1]['font_ids'], true )
) {
	fwrite( STDERR, "FONT_FOLLOW_GRANT_FAIL\n" );
	exit( 1 );
}

if ( ! empty( $known_fonts ) ) {
	$only_id = $known_fonts[0];
	$partial = APD_Formats::sanitize(
		array(
			'name'     => 'Partial fonts',
			'type'     => 'holder',
			'font_ids' => array( $only_id ),
		)
	);
	$stale                 = $open_fonts;
	$stale['font_ids']     = array( $only_id );
	$stale['font_ids_all'] = true;
	$open_shop             = APD_Formats::frontend_payload( $stale );
	$partial_shop          = is_wp_error( $partial ) ? array() : APD_Formats::frontend_payload( $partial );
	$open_shop_ids         = array();
	$partial_shop_ids      = array();

	foreach ( isset( $open_shop['fonts'] ) ? $open_shop['fonts'] : array() as $font ) {
		$open_shop_ids[] = (string) $font['id'];
	}

	foreach ( isset( $partial_shop['fonts'] ) ? $partial_shop['fonts'] : array() as $font ) {
		$partial_shop_ids[] = (string) $font['id'];
	}

	sort( $open_shop_ids );

	if (
		is_wp_error( $partial )
		|| ! empty( $partial['font_ids_all'] )
		|| array( $only_id ) !== $partial['font_ids']
		|| array( $only_id ) !== $partial_shop_ids
		|| $known_fonts !== $open_shop_ids
	) {
		fwrite( STDERR, "FONT_FOLLOW_SHOP_FAIL\n" );
		exit( 1 );
	}
}

$follow_saved = APD_Formats::save(
	array(
		'name'         => 'APD follow fonts',
		'type'         => 'holder',
		'font_ids_all' => '1',
	)
);
$fixed_saved = APD_Formats::save(
	array(
		'name'     => 'APD fixed fonts',
		'type'     => 'holder',
		'font_ids' => array_slice( $known_fonts, 0, 1 ),
	)
);

if ( is_wp_error( $follow_saved ) || is_wp_error( $fixed_saved ) ) {
	if ( ! is_wp_error( $follow_saved ) ) {
		APD_Formats::delete( $follow_saved['id'] );
	}
	if ( ! is_wp_error( $fixed_saved ) ) {
		APD_Formats::delete( $fixed_saved['id'] );
	}
	fwrite( STDERR, "FONT_FOLLOW_STORE_SETUP_FAIL\n" );
	exit( 1 );
}

$stored_settings             = APD_Plugin::get_settings();
$stored_settings['formats']  = APD_Formats::grant_font_to_following_formats( $stored_settings['formats'], 'apd-test-font-new' );
APD_Plugin::save_settings( $stored_settings );
$follow_loaded = APD_Formats::get( $follow_saved['id'] );
$fixed_loaded  = APD_Formats::get( $fixed_saved['id'] );
APD_Formats::delete( $follow_saved['id'] );
APD_Formats::delete( $fixed_saved['id'] );

if (
	! is_array( $follow_loaded )
	|| empty( $follow_loaded['font_ids_all'] )
	|| ! in_array( 'apd-test-font-new', $follow_loaded['font_ids'], true )
	|| ! is_array( $fixed_loaded )
	|| ! empty( $fixed_loaded['font_ids_all'] )
	|| in_array( 'apd-test-font-new', $fixed_loaded['font_ids'], true )
) {
	fwrite( STDERR, "FONT_FOLLOW_STORE_FAIL\n" );
	exit( 1 );
}

echo 'FONT_FOLLOW_OK' . PHP_EOL;

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

if ( ! APD_Formats::uses_country_band( 'eu' ) || ! APD_Formats::uses_country_band( 'moto' ) || ! APD_Formats::uses_country_band( 'suv_eu' ) ) {
	fwrite( STDERR, "COUNTRY_BAND_EU_FAIL\n" );
	exit( 1 );
}

if ( APD_Formats::uses_country_band( 'moto_plain' ) || ! APD_Formats::uses_painted_plate( 'moto_plain' ) ) {
	fwrite( STDERR, "MOTO_PLAIN_FLAGS_FAIL\n" );
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

if ( 18.0 !== $eu_default_box['x'] || 79.0 !== $eu_default_box['width'] || 11.0 !== $eu_default_box['y'] || 78.0 !== $eu_default_box['height'] ) {
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

$format_scope = APD_Designs::formats_for( array( 'allowed_format_ids' => array() ) );
$missing_scope = APD_Designs::formats_for( array( 'allowed_format_ids' => array( 'missing-format' ) ) );

if ( count( $format_scope ) !== count( APD_Formats::of_type( 'us' ) ) || ! empty( $missing_scope ) ) {
	fwrite( STDERR, "DESIGN_FORMATS_FOR_FAIL\n" );
	exit( 1 );
}

$open_designs = array(
	'type'    => 'us',
	'designs' => array(
		array(
			'id'   => 'd-all',
			'name' => 'Arizona',
		),
		array(
			'id'       => 'd-one',
			'name'     => 'California',
			'text_box' => array(
				'x' => 12,
			),
		),
	),
);
$cleared_designs = APD_Admin_Settings::locked_design_payload( 0, $open_designs );
$eu_designs      = APD_Admin_Settings::locked_design_payload(
	0,
	array(
		'type'    => 'eu',
		'designs' => array(
			array(
				'id' => 'keep-me',
			),
		),
	)
);
$lock_post = wp_insert_post(
	array(
		'post_type'   => 'product',
		'post_status' => 'draft',
		'post_title'  => 'APD design lock test',
	)
);
update_post_meta( $lock_post, APD_Admin_Settings::META_DESIGN, 'd-one' );
$locked_designs = APD_Admin_Settings::locked_design_payload( $lock_post, $open_designs );
wp_delete_post( $lock_post, true );

if ( ! empty( $cleared_designs['designs'] ) || 1 !== count( $eu_designs['designs'] ) || 1 !== count( $locked_designs['designs'] ) || 'd-one' !== $locked_designs['designs'][0]['id'] || 12 !== $locked_designs['designs'][0]['text_box']['x'] ) {
	fwrite( STDERR, "DESIGN_LOCK_FAIL\n" );
	exit( 1 );
}

echo 'DESIGN_LOCK_OK' . PHP_EOL;

$split_format = APD_Formats::save(
	array(
		'name'            => 'Split product fields',
		'type'            => 'us',
		'split_text'      => '1',
		'max_chars_left'  => 3,
		'max_chars_right' => 4,
	)
);
$plain_format = APD_Formats::save(
	array(
		'name' => 'Plain product fields',
		'type' => 'us',
	)
);
$side_post = 0;
$side_settings = APD_Plugin::get_settings();
$side_backup   = isset( $side_settings['designs'] ) && is_array( $side_settings['designs'] ) ? $side_settings['designs'] : array();

if ( ! is_wp_error( $split_format ) && ! is_wp_error( $plain_format ) ) {
	$side_settings['designs']   = $side_backup;
	$side_settings['designs'][] = array(
		'id'                 => 'd-split-fields',
		'name'               => 'New York',
		'code'               => 'NY',
		'image_id'           => 1,
		'active'             => true,
		'split_text'         => true,
		'max_chars_left'     => 3,
		'max_chars_right'    => 4,
		'allowed_format_ids' => array( $split_format['id'] ),
		'text_box'           => array(
			'x'      => 4.7,
			'y'      => 21.4,
			'width'  => 38.8,
			'height' => 61.2,
			'align'  => 'center',
			'valign' => 'middle',
		),
		'text_box_right'     => array(
			'x'      => 61,
			'width'  => 34.7,
			'align'  => 'center',
		),
	);
	APD_Plugin::save_settings( $side_settings );

	$side_post = wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => 'draft',
			'post_title'  => 'APD side fields',
		)
	);
	update_post_meta( $side_post, APD_Admin_Settings::META_ENABLED, 'yes' );
	update_post_meta( $side_post, APD_Admin_Settings::META_FORMAT, $split_format['id'] );
	update_post_meta( $side_post, APD_Admin_Settings::META_DESIGN, 'd-split-fields' );
	update_post_meta( $side_post, APD_Admin_Settings::META_DEFAULT_TEXT, "NY\n1234" );

	ob_start();
	APD_Admin_Settings::instance()->render_product_meta_box( get_post( $side_post ) );
	$side_html = ob_get_clean();

	update_post_meta( $side_post, APD_Admin_Settings::META_FORMAT, $plain_format['id'] );
	update_post_meta( $side_post, APD_Admin_Settings::META_DESIGN, '' );
	ob_start();
	APD_Admin_Settings::instance()->render_product_meta_box( get_post( $side_post ) );
	$plain_html = ob_get_clean();

	$admin_users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	);
	if ( ! empty( $admin_users ) ) {
		wp_set_current_user( (int) $admin_users[0]->ID );
		$_POST['apd_product_nonce']       = wp_create_nonce( APD_Admin_Settings::PRODUCT_NONCE_ACTION );
		$_POST['apd_enabled']             = '1';
		$_POST['apd_format_id']           = $split_format['id'];
		$_POST['apd_design_id']           = 'd-split-fields';
		$_POST['apd_default_text_left']   = 'NYX';
		$_POST['apd_default_text_right']  = '12345';
		$_POST['apd_layouts']             = array( 'text_only' );
		APD_Admin_Settings::instance()->save_product_meta( $side_post );
		unset( $_POST['apd_product_nonce'], $_POST['apd_enabled'], $_POST['apd_format_id'], $_POST['apd_design_id'], $_POST['apd_default_text_left'], $_POST['apd_default_text_right'], $_POST['apd_layouts'] );
	}
}

$side_settings            = APD_Plugin::get_settings();
$side_settings['designs'] = $side_backup;
APD_Plugin::save_settings( $side_settings );
if ( ! is_wp_error( $split_format ) ) {
	APD_Formats::delete( $split_format['id'] );
}
if ( ! is_wp_error( $plain_format ) ) {
	APD_Formats::delete( $plain_format['id'] );
}
$stored_sides = $side_post ? (string) get_post_meta( $side_post, APD_Admin_Settings::META_DEFAULT_TEXT, true ) : '';
if ( $side_post ) {
	wp_delete_post( $side_post, true );
}

$admin_css = file_get_contents( APD_PLUGIN_DIR . 'assets/css/admin-settings.css' );
$admin_js  = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );
if (
	! isset( $side_html, $plain_html )
	|| false === strpos( $side_html, 'type="hidden" name="apd_design_id"' )
	|| false !== strpos( $side_html, '<select name="apd_design_id"' )
	|| false !== strpos( $side_html, 'Plate design' )
	|| false === strpos( $side_html, 'value="d-split-fields"' )
	|| false === strpos( $side_html, 'data-apd-split="1"' )
	|| false !== strpos( $side_html, 'data-apd-default-sides hidden' )
	|| false === strpos( $side_html, 'name="apd_default_text_left"' )
	|| false === strpos( $side_html, 'value="NY"' )
	|| false === strpos( $side_html, 'value="1234"' )
	|| false === strpos( $side_html, 'maxlength="3"' )
	|| false === strpos( $side_html, 'maxlength="4"' )
	|| false === strpos( $side_html, 'data-apd-default-rows hidden' )
	|| false === strpos( $side_html, 'data-apd-default-single hidden' )
	|| false === strpos( $plain_html, 'data-apd-default-sides hidden' )
	|| false !== strpos( $plain_html, 'data-apd-default-single hidden' )
	|| "NYX\n1234" !== $stored_sides
	|| false === strpos( $admin_css, '.apd-product-metabox [hidden]' )
	|| false === strpos( $admin_js, 'data-apd-default-sides' )
) {
	fwrite( STDERR, "PRODUCT_SIDE_FIELDS_FAIL stored=" . wp_json_encode( $stored_sides ) . PHP_EOL );
	exit( 1 );
}

echo 'PRODUCT_SIDE_FIELDS_OK' . PHP_EOL;

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

if ( array( 'text', 'border', 'background', 'color_text', 'color_border', 'color_background' ) !== $color_caps || array( 'text', 'border', 'background' ) !== $color_def ) {
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

$holder_only = array( 'holder', 'holder_text', 'holder_strip' );
$holder_caps = APD_Formats::color_field_capabilities( 'holder' );
$holder_def  = APD_Formats::default_color_fields( 'holder' );
$plate_caps  = array( 'text', 'border', 'background' );
$color_extra = array( 'color_text', 'color_border', 'color_background' );

if ( $holder_only !== $holder_caps || $holder_caps !== $holder_def ) {
	fwrite( STDERR, "HOLDER_COLOR_CAPS_FAIL\n" );
	exit( 1 );
}

foreach ( array( 'eu', 'eu_plain', 'moto', 'moto_240', 'moto_plain', 'moto_plain_240', 'suv', 'suv_eu', 'custom' ) as $plate_type ) {
	if ( $plate_caps !== APD_Formats::color_field_capabilities( $plate_type ) ) {
		fwrite( STDERR, "PLATE_COLOR_CAPS_FAIL {$plate_type}\n" );
		exit( 1 );
	}
}

if ( array( 'text' ) !== APD_Formats::color_field_capabilities( 'us' ) || array_merge( $plate_caps, $color_extra ) !== APD_Formats::color_field_capabilities( 'color' ) ) {
	fwrite( STDERR, "TYPE_COLOR_CAPS_FAIL\n" );
	exit( 1 );
}

foreach ( APD_Security::allowed_format_types() as $format_type ) {
	$got = APD_Formats::color_field_capabilities( $format_type );

	if ( APD_Formats::is_holder( $format_type ) ) {
		foreach ( array_merge( $plate_caps, $color_extra ) as $foreign ) {
			if ( in_array( $foreign, $got, true ) ) {
				fwrite( STDERR, "HOLDER_FOREIGN_CAP_FAIL {$format_type} {$foreign}\n" );
				exit( 1 );
			}
		}
		if ( APD_Formats::is_photo_holder( $format_type ) && in_array( 'holder', $got, true ) ) {
			fwrite( STDERR, "PHOTO_HOLDER_BODY_CAP_FAIL {$format_type}\n" );
			exit( 1 );
		}
		continue;
	}

	foreach ( $holder_only as $foreign ) {
		if ( in_array( $foreign, $got, true ) ) {
			fwrite( STDERR, "PLATE_HOLDER_CAP_FAIL {$format_type} {$foreign}\n" );
			exit( 1 );
		}
	}

	if ( 'color' !== $format_type ) {
		foreach ( $color_extra as $foreign ) {
			if ( in_array( $foreign, $got, true ) ) {
				fwrite( STDERR, "STANDARD_COLOR_CAP_FAIL {$format_type} {$foreign}\n" );
				exit( 1 );
			}
		}
	}
}

$holder_fields = APD_Formats::sanitize_color_fields(
	array( 'holder', 'holder_text', 'holder_strip', 'text', 'border', 'background', 'color_text', 'color_border', 'color_background' ),
	'holder'
);

if ( $holder_only !== $holder_fields ) {
	fwrite( STDERR, "HOLDER_FIELD_SANITIZE_FAIL\n" );
	exit( 1 );
}

$holder_palette_ids = APD_Color_Palettes::sanitize_product_ids(
	array(
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['border'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['background'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder'],
		APD_Color_Palettes::DEFAULT_PALETTE_IDS['color_text'],
	),
	'holder'
);

if ( array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder'] ) !== array_values( $holder_palette_ids ) ) {
	fwrite( STDERR, "HOLDER_PRODUCT_PALETTE_FAIL\n" );
	exit( 1 );
}

$format_with_colors = APD_Formats::sanitize(
	array(
		'name'        => 'EU with text palette',
		'type'        => 'eu',
		'palette_ids' => array(
			APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'],
			APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder'],
		),
	)
);

if ( is_wp_error( $format_with_colors ) || array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] ) !== array_values( $format_with_colors['palette_ids'] ) ) {
	fwrite( STDERR, "FORMAT_PALETTE_SANITIZE_FAIL\n" );
	exit( 1 );
}

echo 'FORMAT_PALETTE_OK' . PHP_EOL;

$saved_palette_format = APD_Formats::save( $format_with_colors );

if ( is_wp_error( $saved_palette_format ) ) {
	fwrite( STDERR, "FORMAT_PALETTE_SAVE_FAIL\n" );
	exit( 1 );
}

$palette_product = wp_insert_post(
	array(
		'post_type'   => 'product',
		'post_status' => 'draft',
		'post_title'  => 'Palette probe',
	)
);
update_post_meta( $palette_product, APD_Admin_Settings::META_FORMAT, $saved_palette_format['id'] );
update_post_meta( $palette_product, APD_Admin_Settings::META_PALETTE_IDS, array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder'] ) );
$format_wins = APD_Admin_Settings::product_palette_ids( $palette_product );

if ( array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] ) !== array_values( $format_wins ) ) {
	fwrite( STDERR, "FORMAT_PALETTE_WINS_FAIL\n" );
	wp_delete_post( $palette_product, true );
	APD_Formats::delete( $saved_palette_format['id'] );
	exit( 1 );
}

wp_delete_post( $palette_product, true );
APD_Formats::delete( $saved_palette_format['id'] );

$slot_ids = APD_Color_Palettes::DEFAULT_PALETTE_IDS;
$slot_format = APD_Formats::sanitize(
	array(
		'name'          => 'Street palette slots',
		'type'          => 'custom',
		'width'         => 340,
		'height'        => 200,
		'palette_slots' => array(
			'text'       => $slot_ids['holder'],
			'background' => $slot_ids['color_background'],
			'border'     => $slot_ids['border'],
		),
	)
);
$slot_missing = APD_Formats::sanitize(
	array(
		'name'          => 'Street palette slots missing',
		'type'          => 'custom',
		'width'         => 340,
		'height'        => 200,
		'palette_slots' => array(
			'text'       => $slot_ids['text'],
			'background' => '',
			'border'     => $slot_ids['border'],
		),
	)
);
$slot_editor = APD_Formats::palette_slots_for_editor(
	array(),
	array( $slot_ids['text'], $slot_ids['color_text'], $slot_ids['background'] )
);

if (
	is_wp_error( $slot_format )
	|| $slot_ids['holder'] !== $slot_format['palette_slots']['text']
	|| $slot_ids['color_background'] !== $slot_format['palette_slots']['background']
	|| ! is_wp_error( $slot_missing )
	|| '' !== $slot_editor['text']
	|| $slot_ids['background'] !== $slot_editor['background']
) {
	fwrite( STDERR, "PALETTE_SLOT_SANITIZE_FAIL\n" );
	exit( 1 );
}

$saved_slot_format = APD_Formats::save( $slot_format );

if ( is_wp_error( $saved_slot_format ) ) {
	fwrite( STDERR, "PALETTE_SLOT_SAVE_FAIL\n" );
	exit( 1 );
}

$slot_product = wp_insert_post(
	array(
		'post_type'   => 'product',
		'post_status' => 'draft',
		'post_title'  => 'Street palette probe',
	)
);
update_post_meta( $slot_product, APD_Admin_Settings::META_FORMAT, $saved_slot_format['id'] );
update_post_meta( $slot_product, APD_Admin_Settings::META_PALETTE_IDS, array( $slot_ids['text'] ) );
$slot_offered = APD_Admin_Settings::product_offered_colors( $slot_product );
$slot_fields  = APD_Admin_Settings::product_color_fields( $slot_product );
$slot_text    = isset( $slot_offered['text'][0]['hex'] ) ? strtoupper( (string) $slot_offered['text'][0]['hex'] ) : '';
$holder_row   = APD_Color_Palettes::get( $slot_ids['holder'] );
$holder_hex   = '';

if ( is_array( $holder_row ) ) {
	$holder_colors = APD_Color_Palettes::palette_colors( $holder_row );
	$holder_hex    = isset( $holder_colors[0]['hex'] ) ? strtoupper( (string) $holder_colors[0]['hex'] ) : '';
}

if ( '' === $holder_hex || $holder_hex !== $slot_text || array( 'text', 'background', 'border' ) !== $slot_fields || ! empty( $slot_offered['holder'] ) ) {
	fwrite( STDERR, "PALETTE_SLOT_SHOP_FAIL\n" );
	exit( 1 );
}

wp_delete_post( $slot_product, true );
APD_Formats::delete( $saved_slot_format['id'] );

$swatch_rows = array(
	'text'         => array( array( 'id' => 'plate-text', 'hex' => '#111111', 'label' => 'Plate text' ) ),
	'background'   => array( array( 'id' => 'plate-fill', 'hex' => '#222222', 'label' => 'Plate fill' ) ),
	'holder'       => array( array( 'id' => 'body', 'hex' => '#7D7D7D', 'label' => 'Gray' ) ),
	'holder_text'  => array( array( 'id' => 'ink', 'hex' => '#000000', 'label' => 'Ink' ) ),
	'holder_strip' => array( array( 'id' => 'strip', 'hex' => '#FFFFFF', 'label' => 'Strip' ) ),
	'color_text'   => array( array( 'id' => 'color-text', 'hex' => '#333333', 'label' => 'Color text' ) ),
);
$stale_fields = array( 'holder', 'holder_text', 'holder_strip', 'text', 'background', 'color_text' );
$holder_ink   = APD_Formats::shop_swatch_colors( 'holder', 'text', $stale_fields, $swatch_rows );
$holder_fill  = APD_Formats::shop_swatch_colors( 'holder', 'background', $stale_fields, $swatch_rows );
$eu_ink       = APD_Formats::shop_swatch_colors( 'eu', 'text', array( 'text', 'color_text', 'holder_text' ), $swatch_rows );
$color_ink    = APD_Formats::shop_swatch_colors( 'color', 'text', array( 'text', 'color_text' ), $swatch_rows );
$holder_ink_ids  = array_column( $holder_ink, 'id' );
$holder_fill_ids = array_column( $holder_fill, 'id' );
$eu_ink_ids      = array_column( $eu_ink, 'id' );
$color_ink_ids   = array_column( $color_ink, 'id' );

$photo_shared = APD_Formats::shop_swatch_colors( 'holder_moto', 'text', array( 'holder_text', 'holder_strip' ), $swatch_rows );
$photo_fill   = APD_Formats::shop_swatch_colors( 'holder_d', 'background', array( 'holder_text', 'holder_strip' ), $swatch_rows );
$photo_ids    = array_column( $photo_shared, 'id' );
$photo_fill_ids = array_column( $photo_fill, 'id' );

if ( array( 'strip', 'ink' ) !== $photo_ids || $photo_ids !== $photo_fill_ids ) {
	fwrite( STDERR, "PHOTO_HOLDER_SWATCH_FAIL\n" );
	exit( 1 );
}

if ( array( 'ink' ) !== $holder_ink_ids || array( 'strip' ) !== $holder_fill_ids || array( 'plate-text' ) !== $eu_ink_ids || array( 'plate-text', 'color-text' ) !== $color_ink_ids ) {
	fwrite( STDERR, "SWATCH_SCOPE_FAIL\n" );
	exit( 1 );
}

$admin_js = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );

if ( false === $admin_js || false !== strpos( $admin_js, 'stripColorLabel' ) || false !== strpos( $admin_js, 'data-apd-background-label' ) ) {
	fwrite( STDERR, "HOLDER_LABEL_LEAK_FAIL\n" );
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

$holder_palette = false;

foreach ( $named as $palette ) {
	if ( isset( $palette['purpose'] ) && 'holder' === $palette['purpose'] && ! empty( $palette['color_ids'] ) ) {
		$holder_palette = true;
	}
}

if ( ! $holder_palette ) {
	fwrite( STDERR, "HOLDER_PALETTE_FAIL\n" );
	exit( 1 );
}

$special_purposes = array();
$gray_hex         = false;

foreach ( $library as $color ) {
	if ( isset( $color['hex'] ) && '#7D7D7D' === strtoupper( (string) $color['hex'] ) ) {
		$gray_hex = true;
	}
}

foreach ( $named as $palette ) {
	if ( isset( $palette['purpose'] ) ) {
		$special_purposes[] = (string) $palette['purpose'];
	}
}

if ( ! $gray_hex || ! in_array( 'color_text', $special_purposes, true ) || ! in_array( 'color_border', $special_purposes, true ) || ! in_array( 'color_background', $special_purposes, true ) ) {
	fwrite( STDERR, "SPECIAL_PALETTE_FAIL\n" );
	exit( 1 );
}

if ( in_array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder_text'], $named_ids, true ) || in_array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['holder_strip'], $named_ids, true ) || ! in_array( 'holder_text', APD_Color_Palettes::purposes(), true ) || ! in_array( 'holder_strip', APD_Color_Palettes::purposes(), true ) ) {
	fwrite( STDERR, "HOLDER_PALETTE_SEED_FAIL\n" );
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

$source = APD_Formats::save(
	array(
		'name'             => 'Copy source plate',
		'type'             => 'custom',
		'width'            => 340,
		'height'           => 200,
		'price_adjustment' => 4.5,
	)
);

if ( is_wp_error( $source ) ) {
	fwrite( STDERR, 'FORMAT_DUP_SAVE_FAIL ' . $source->get_error_message() . PHP_EOL );
	exit( 1 );
}

$copy = APD_Formats::duplicate( $source['id'] );
$again = is_wp_error( $copy ) ? $copy : APD_Formats::get( $copy['id'] );
$kept  = APD_Formats::get( $source['id'] );
$suffix = ' ' . __( '(Copy)', 'auto-plate-designer' );

if (
	is_wp_error( $copy )
	|| ! is_array( $again )
	|| $again['id'] === $source['id']
	|| $source['name'] . $suffix !== $again['name']
	|| 340 !== (int) $again['width']
	|| 200 !== (int) $again['height']
	|| 'custom' !== $again['type']
	|| 4.5 !== (float) $again['price_adjustment']
	|| ! is_array( $kept )
	|| $kept['name'] !== $source['name']
) {
	fwrite( STDERR, 'FORMAT_DUP_FAIL ' . wp_json_encode( is_wp_error( $copy ) ? $copy->get_error_message() : $again ) . PHP_EOL );
	APD_Formats::delete( $source['id'] );
	if ( is_array( $copy ) && isset( $copy['id'] ) ) {
		APD_Formats::delete( $copy['id'] );
	}
	exit( 1 );
}

$long = APD_Formats::save(
	array(
		'name'   => str_repeat( 'N', 80 ),
		'type'   => 'eu',
		'width'  => 520,
		'height' => 110,
	)
);
$long_copy = is_wp_error( $long ) ? $long : APD_Formats::duplicate( $long['id'] );
$long_name = ( ! is_wp_error( $long_copy ) && isset( $long_copy['name'] ) ) ? (string) $long_copy['name'] : '';
$long_len  = function_exists( 'mb_strlen' ) ? mb_strlen( $long_name, 'UTF-8' ) : strlen( $long_name );
$long_tail = function_exists( 'mb_substr' ) ? mb_substr( $long_name, -mb_strlen( $suffix, 'UTF-8' ), null, 'UTF-8' ) : substr( $long_name, -strlen( $suffix ) );

if (
	is_wp_error( $long )
	|| is_wp_error( $long_copy )
	|| $long_len > 80
	|| $long_len < 1
	|| $suffix !== $long_tail
) {
	fwrite( STDERR, 'FORMAT_DUP_LONG_FAIL ' . wp_json_encode( is_wp_error( $long_copy ) ? $long_copy->get_error_message() : ( is_array( $long_copy ) ? $long_copy['name'] : '' ) ) . PHP_EOL );
	if ( is_array( $long ) && isset( $long['id'] ) ) {
		APD_Formats::delete( $long['id'] );
	}
	if ( is_array( $long_copy ) && isset( $long_copy['id'] ) ) {
		APD_Formats::delete( $long_copy['id'] );
	}
	APD_Formats::delete( $source['id'] );
	APD_Formats::delete( $copy['id'] );
	exit( 1 );
}

APD_Formats::delete( $source['id'] );
APD_Formats::delete( $copy['id'] );
APD_Formats::delete( $long['id'] );
APD_Formats::delete( $long_copy['id'] );

echo 'FORMAT_DUP_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
