<?php
/**
 * Cart fingerprints and frozen order display rows.
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

if ( ! class_exists( 'APD_WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
	fwrite( STDERR, "APD_WooCommerce or WooCommerce missing\n" );
	exit( 1 );
}

$rows = APD_WooCommerce::config_display_rows(
	array(
		'format_name'       => 'Frozen EU',
		'format_type'       => 'eu',
		'format_type_label' => 'EU plate',
		'text'              => 'B 023 APD',
		'font_label'        => 'Stored Font',
		'preset_label'      => 'Bulgaria',
		'color_fields'      => array( 'text' ),
		'text_color'        => '#000000',
		'text_color_label'  => 'Ink black',
	)
);

$found = array(
	'format' => false,
	'font'   => false,
	'preset' => false,
	'color'  => false,
	'text'   => false,
);

foreach ( $rows as $row ) {
	$value = isset( $row['value'] ) ? (string) $row['value'] : '';

	if ( false !== strpos( $value, 'Frozen EU' ) ) {
		$found['format'] = true;
	}

	if ( 'Stored Font' === $value ) {
		$found['font'] = true;
	}

	if ( 'Bulgaria' === $value ) {
		$found['preset'] = true;
	}

	if ( false !== strpos( $value, 'Ink black' ) ) {
		$found['color'] = true;
	}

	if ( 'B 023 APD' === $value ) {
		$found['text'] = true;
	}
}

if ( in_array( false, $found, true ) ) {
	fwrite( STDERR, 'SNAPSHOT_DISPLAY_FAIL ' . wp_json_encode( $found ) . PHP_EOL );
	exit( 1 );
}

echo 'SNAPSHOT_DISPLAY_OK' . PHP_EOL;

$no_frame_rows = APD_WooCommerce::config_display_rows(
	array(
		'format_name'  => 'Color',
		'format_type'  => 'color',
		'no_frame'     => true,
		'color_fields' => array( 'border' ),
		'border_color' => '#000000',
	)
);
$no_frame_found = false;
$border_shown   = false;

foreach ( $no_frame_rows as $row ) {
	if ( __( 'Without frame', 'auto-plate-designer' ) === (string) $row['value'] ) {
		$no_frame_found = true;
	}
	if ( __( 'Border color', 'auto-plate-designer' ) === (string) $row['key'] ) {
		$border_shown = true;
	}
}

if ( ! $no_frame_found || $border_shown ) {
	fwrite( STDERR, "NO_FRAME_DISPLAY_FAIL\n" );
	exit( 1 );
}

echo 'NO_FRAME_DISPLAY_OK' . PHP_EOL;

$format = APD_Formats::save(
	array(
		'name'             => 'APD fingerprint A',
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

$other = APD_Formats::save(
	array(
		'name'             => 'APD fingerprint B',
		'type'             => 'us',
		'width'            => 300,
		'height'           => 150,
		'border_width'     => 0,
		'border_color'     => '#000000',
		'band_ratio'       => 0,
		'band_side'        => 'left',
		'price_adjustment' => 0,
	)
);

if ( is_wp_error( $format ) || is_wp_error( $other ) ) {
	fwrite( STDERR, "FORMAT_SETUP_FAIL\n" );
	exit( 1 );
}

$product = new WC_Product_Simple();
$product->set_name( 'APD fingerprint product' );
$product->set_status( 'publish' );
$product->set_regular_price( '10' );
$product->save();
$product_id = $product->get_id();

update_post_meta( $product_id, APD_Admin_Settings::META_ENABLED, 'yes' );
update_post_meta( $product_id, APD_Admin_Settings::META_FORMAT, $format['id'] );
update_post_meta( $product_id, APD_Admin_Settings::META_PALETTE_IDS, array( APD_Color_Palettes::DEFAULT_PALETTE_IDS['text'] ) );

$first = APD_WooCommerce::offer_fingerprint( $product_id );

if ( '' === $first || $first !== APD_WooCommerce::offer_fingerprint( $product_id ) ) {
	fwrite( STDERR, "FINGERPRINT_STABLE_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

$other['name'] = 'APD fingerprint B renamed';
$saved_other   = APD_Formats::save( $other );

if ( is_wp_error( $saved_other ) || $first !== APD_WooCommerce::offer_fingerprint( $product_id ) ) {
	fwrite( STDERR, "UNRELATED_FORMAT_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

$format['price_adjustment'] = 12;
$saved_format               = APD_Formats::save( $format );
$after_price                = APD_WooCommerce::offer_fingerprint( $product_id );

if ( is_wp_error( $saved_format ) || $first === $after_price ) {
	fwrite( STDERR, "PRICE_FINGERPRINT_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

update_post_meta( $product_id, APD_Admin_Settings::META_PALETTE_IDS, array() );
$after_palettes = APD_WooCommerce::offer_fingerprint( $product_id );

if ( $after_price === $after_palettes ) {
	fwrite( STDERR, "PALETTE_FINGERPRINT_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

$config = array(
	'offer_fingerprint' => $after_palettes,
	'text'              => 'ABC',
);

if ( ! APD_WooCommerce::cart_config_matches_product( $product_id, $config ) ) {
	fwrite( STDERR, "MATCH_CURRENT_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

if ( APD_WooCommerce::cart_config_matches_product( $product_id, array( 'text' => 'ABC' ) ) ) {
	fwrite( STDERR, "LEGACY_MATCH_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

update_post_meta( $product_id, APD_Admin_Settings::META_ENABLED, 'no' );

if ( '' !== APD_WooCommerce::offer_fingerprint( $product_id )
	|| APD_WooCommerce::cart_config_matches_product( $product_id, $config ) ) {
	fwrite( STDERR, "DISABLED_PRODUCT_FAIL\n" );
	APD_Formats::delete( $format['id'] );
	APD_Formats::delete( $other['id'] );
	$product->delete( true );
	exit( 1 );
}

APD_Formats::delete( $format['id'] );
APD_Formats::delete( $other['id'] );
$product->delete( true );

echo 'FINGERPRINT_OK' . PHP_EOL;

$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );
$stored = APD_WooCommerce::store_preview_png( 'data:image/png;base64,' . base64_encode( $png ) );
$kept   = APD_WooCommerce::preview_url_from_config( array( 'preview_url' => $stored ) );
$thumb  = APD_WooCommerce::preview_img_html( $kept );

if ( '' === $stored || $kept !== $stored || false === strpos( $thumb, 'apd-config-preview' ) || false === strpos( $thumb, 'apd-previews/' ) ) {
	fwrite( STDERR, "PREVIEW_THUMB_FAIL {$stored}\n" );
	exit( 1 );
}

if ( '' !== APD_WooCommerce::store_preview_png( 'data:image/svg+xml;base64,PHN2Zz4=' ) ) {
	fwrite( STDERR, "PREVIEW_REJECT_FAIL\n" );
	exit( 1 );
}

if ( '' !== APD_WooCommerce::preview_url_from_config( array( 'preview_url' => 'https://evil.example/apd-previews/' . str_repeat( 'a', 64 ) . '.png' ) ) ) {
	fwrite( STDERR, "PREVIEW_URL_GUARD_FAIL\n" );
	exit( 1 );
}

$upload = wp_upload_dir();
$file   = trailingslashit( $upload['basedir'] ) . 'apd-previews/' . basename( (string) wp_parse_url( $stored, PHP_URL_PATH ) );

if ( is_file( $file ) ) {
	unlink( $file );
}

$shop_css = file_get_contents( APD_PLUGIN_DIR . 'assets/css/configurator.css' );

if ( false === strpos( $shop_css, 'img[src*="apd-previews"]' ) || false === strpos( $shop_css, 'img.apd-config-preview' ) ) {
	fwrite( STDERR, "PREVIEW_CSS_FAIL\n" );
	exit( 1 );
}

echo 'PREVIEW_IMAGE_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
