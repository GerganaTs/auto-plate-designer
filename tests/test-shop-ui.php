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
	'textColor'     => 'Text color',
	'borderColor'   => 'Border color',
	'plateColor'    => 'Plate color',
	'stripColor'    => 'Strip color',
	'holderColor'   => 'Holder color',
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

if ( empty( $payload['allow_empty'] ) ) {
	fwrite( STDERR, "SHOP_EMPTY_TEXT_FAIL\n" );
	exit( 1 );
}

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

if ( false === strpos( $html, 'apd-product-layout__preview' ) || false === strpos( $html, 'apd-product-layout__details' ) ) {
	fwrite( STDERR, "SHOP_LAYOUT_HOIST_FAIL\n" );
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

$us_ui['designs'] = array(
	array(
		'id'        => 'ca',
		'name'      => 'California',
		'code'      => 'CA',
		'image_url' => 'http://example.com/ca.png',
		'text_box'  => array(
			'x'      => 10,
			'y'      => 20,
			'width'  => 70,
			'height' => 40,
			'align'  => 'center',
			'valign' => 'middle',
		),
	),
	array(
		'id'        => 'az',
		'name'      => 'Arizona',
		'code'      => 'AZ',
		'image_url' => 'http://example.com/az.png',
	),
);
$apd_payload['format'] = $us_ui;
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$us_design_html = ob_get_clean();

if ( false !== strpos( $us_design_html, 'data-apd-design-id' ) || false === strpos( $us_design_html, 'name="apd_design_id"' ) || false === strpos( $us_design_html, 'value="ca"' ) ) {
	fwrite( STDERR, "SHOP_US_DESIGN_PICKER_FAIL\n" );
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

$moto_format = APD_Formats::sanitize(
	array(
		'id'     => 'ui-moto',
		'name'   => 'Moto UI',
		'type'   => 'moto',
		'width'  => 199,
		'height' => 154,
	)
);
if ( is_wp_error( $moto_format ) ) {
	fwrite( STDERR, 'MOTO_FORMAT_ERR ' . $moto_format->get_error_message() . PHP_EOL );
	exit( 1 );
}
$moto_ui = APD_Formats::frontend_payload( $moto_format );
$moto_ui['fonts']   = array();
$moto_ui['presets'] = array();
$moto_ui['designs'] = array();
$apd_payload = array(
	'format'       => $moto_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border', 'background' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
	'default_text' => "CA\n1234",
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$moto_html = ob_get_clean();

if ( false === strpos( $moto_html, 'name="apd_text_row_1"' ) || false === strpos( $moto_html, 'name="apd_text_row_2"' ) || false === strpos( $moto_html, 'value="CA"' ) || false === strpos( $moto_html, 'value="1234"' ) || false === strpos( $moto_html, 'maxlength="5"' ) || false === strpos( $moto_html, 'maxlength="4"' ) ) {
	fwrite( STDERR, "SHOP_MOTO_ROWS_FAIL\n" );
	exit( 1 );
}

$plain_format = APD_Formats::sanitize(
	array(
		'id'     => 'ui-moto-plain',
		'name'   => 'Moto plain UI',
		'type'   => 'moto_plain',
	)
);
if ( is_wp_error( $plain_format ) ) {
	fwrite( STDERR, 'MOTO_PLAIN_FORMAT_ERR ' . $plain_format->get_error_message() . PHP_EOL );
	exit( 1 );
}
$plain_ui = APD_Formats::frontend_payload( $plain_format );
$plain_ui['fonts']   = array();
$plain_ui['presets'] = array();
$plain_ui['designs'] = array();
$apd_payload = array(
	'format'       => $plain_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border', 'background' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
	'default_text' => "CA\n1234",
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$plain_html = ob_get_clean();

if ( empty( $plain_ui['capabilities']['two_row'] ) || false === strpos( $plain_html, 'name="apd_text_row_1"' ) || false === strpos( $plain_html, 'name="apd_text_row_2"' ) || false === strpos( $plain_html, 'value="CA"' ) || false === strpos( $plain_html, 'value="1234"' ) || false === strpos( $plain_html, 'maxlength="5"' ) || false === strpos( $plain_html, 'maxlength="4"' ) || false === strpos( $plain_html, 'class="apd-row-fields"' ) || false !== strpos( $plain_html, 'id="apd_text" name="apd_text" value="CA 1234"' ) ) {
	fwrite( STDERR, "SHOP_MOTO_PLAIN_ROWS_FAIL\n" );
	exit( 1 );
}

foreach ( array( 'moto_240', 'moto_plain_240' ) as $wide_type ) {
	$wide_format = APD_Formats::sanitize(
		array(
			'id'   => 'ui-' . str_replace( '_', '-', $wide_type ),
			'name' => 'Wide ' . str_replace( '_', ' ', $wide_type ),
			'type' => $wide_type,
		)
	);
	if ( is_wp_error( $wide_format ) || 240 !== (int) $wide_format['width'] || 130 !== (int) $wide_format['height'] ) {
		fwrite( STDERR, "SHOP_MOTO_240_FORMAT_FAIL {$wide_type}\n" );
		exit( 1 );
	}
	$wide_ui = APD_Formats::frontend_payload( $wide_format );
	$wide_ui['fonts']   = array();
	$wide_ui['presets'] = array();
	$wide_ui['designs'] = array();
	$apd_payload = array(
		'format'       => $wide_ui,
		'palettes'     => $palettes,
		'i18n'         => $i18n,
		'color_fields' => array( 'text', 'border', 'background' ),
		'layouts'      => array( 'text_only' ),
		'minFont'      => 12,
		'default_text' => "CA\n1234",
	);
	unset( $apd_payload['cart_restore'] );
	ob_start();
	include APD_PLUGIN_DIR . 'templates/product-configurator.php';
	$wide_html = ob_get_clean();
	$expects_band = ( 'moto_240' === $wide_type );
	if (
		empty( $wide_ui['capabilities']['two_row'] )
		|| empty( $wide_ui['allow_empty'] )
		|| $expects_band !== ! empty( $wide_ui['capabilities']['country_band'] )
		|| false === strpos( $wide_html, 'name="apd_text_row_1"' )
		|| false === strpos( $wide_html, 'name="apd_text_row_2"' )
		|| false !== strpos( $wide_html, 'data-apd-row-styles' )
	) {
		fwrite( STDERR, "SHOP_MOTO_240_ROWS_FAIL {$wide_type}\n" );
		exit( 1 );
	}
}

if ( empty( $suv_ui['allow_empty'] ) || empty( $moto_ui['allow_empty'] ) || empty( $us_ui['allow_empty'] ) || empty( $plain_ui['allow_empty'] ) ) {
	fwrite( STDERR, "SHOP_EMPTY_TEXT_TYPES_FAIL\n" );
	exit( 1 );
}

$holder_payload                           = $apd_payload;
$holder_payload['format']['type']         = 'holder';
$holder_payload['color_fields']           = array( 'holder', 'holder_text', 'holder_strip', 'text', 'background' );
$holder_payload['i18n']['holderTextColor'] = 'Holder inscription';
$holder_payload['palettes']['holder']     = array(
	array(
		'id'    => 'body',
		'hex'   => '#000000',
		'label' => 'Black',
	),
);
$holder_payload['palettes']['holder_text'] = array(
	array(
		'id'    => 'ink',
		'hex'   => '#111111',
		'label' => 'Ink',
	),
);
$holder_payload['palettes']['holder_strip'] = array(
	array(
		'id'    => 'strip',
		'hex'   => '#FFFFFF',
		'label' => 'White',
	),
);
$holder_payload['palettes']['text'] = array(
	array(
		'id'    => 'plate-text',
		'hex'   => '#010101',
		'label' => 'PlateTextDecoy',
	),
);
$holder_payload['palettes']['background'] = array(
	array(
		'id'    => 'plate-fill',
		'hex'   => '#020202',
		'label' => 'PlateFillDecoy',
	),
);
$apd_payload = $holder_payload;
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$holder_html = ob_get_clean();

if ( false === strpos( $holder_html, 'name="apd_holder_color"' ) || false === strpos( $holder_html, '>Holder color<' ) || false === strpos( $holder_html, 'name="apd_background_color"' ) || false === strpos( $holder_html, '>Strip color<' ) || false === strpos( $holder_html, 'data-apd-plain' ) || false === strpos( $holder_html, 'name="apd_plain"' ) || false === strpos( $holder_html, '>Holder inscription<' ) || false === strpos( $holder_html, 'title="Ink"' ) || strpos( $holder_html, 'name="apd_holder_color"' ) > strpos( $holder_html, 'name="apd_background_color"' ) || false !== strpos( $holder_html, 'PlateTextDecoy' ) || false !== strpos( $holder_html, 'PlateFillDecoy' ) || false !== strpos( $holder_html, 'name="apd_border_color"' ) || false !== strpos( $holder_html, 'name="apd_frame"' ) ) {
	fwrite( STDERR, "SHOP_HOLDER_COLORS_FAIL\n" );
	exit( 1 );
}

$shop_css = file_get_contents( APD_PLUGIN_DIR . 'assets/css/configurator.css' );
if ( false === strpos( $shop_css, '.apd-row-fields,' ) || false === strpos( $shop_css, 'flex-direction: column' ) || false === strpos( $shop_css, 'padding-bottom: 5px' ) || false !== strpos( $shop_css, 'grid-template-columns: 1fr 1fr' ) ) {
	fwrite( STDERR, "SHOP_FIELD_STACK_CSS_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $moto_html, 'class="apd-row-fields"' ) || false === strpos( $suv_html, 'class="apd-row-fields"' ) ) {
	fwrite( STDERR, "SHOP_ROW_STACK_MARKUP_FAIL\n" );
	exit( 1 );
}

$split_ui = $us_ui;
$split_ui['split_text']      = true;
$split_ui['base_image_url']  = 'http://example.com/ny.png';
$split_ui['text_box_right']  = array(
	'x'      => 61,
	'y'      => 21.4,
	'width'  => 34.7,
	'height' => 61.2,
	'align'  => 'center',
	'valign' => 'middle',
);
$split_ui['side_max_chars'] = array( 3, 4 );
$split_ui['designs']        = array();
$apd_payload = array(
	'format'       => $split_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
	'default_text' => "230\n7196",
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$split_html = ob_get_clean();
$left_label  = __( 'Left text', 'auto-plate-designer' );
$right_label = __( 'Right text', 'auto-plate-designer' );

if ( false === strpos( $split_html, 'class="apd-side-fields"' ) || false === strpos( $split_html, '>' . $left_label . '<' ) || false === strpos( $split_html, '>' . $right_label . '<' ) || false === strpos( $split_html, 'value="230"' ) || false === strpos( $split_html, 'value="7196"' ) || false === strpos( $split_html, 'maxlength="3"' ) || false === strpos( $split_html, 'maxlength="4"' ) || strpos( $split_html, 'apd_text_left' ) > strpos( $split_html, 'apd_text_right' ) ) {
	fwrite( STDERR, "SHOP_SIDE_FIELDS_FAIL\n" );
	exit( 1 );
}

$editor = file_get_contents( APD_PLUGIN_DIR . 'templates/admin/partials/text-box-editor.php' );
if ( false === strpos( $editor, 'Left maximum' ) || false === strpos( $editor, 'Right maximum' ) || false === strpos( $editor, '$apd_side_left_name' ) || false === strpos( $editor, '$apd_side_right_name' ) ) {
	fwrite( STDERR, "ADMIN_SIDE_MAX_FIELDS_FAIL\n" );
	exit( 1 );
}

$shop_js = file_get_contents( APD_PLUGIN_DIR . 'assets/js/configurator.js' );
if ( false === strpos( $shop_js, 'groups.join' ) || false === strpos( $shop_js, 'bindStayOnProduct' ) || false === strpos( $shop_js, 'function resetConfigurator' ) || false === strpos( $shop_js, 'data-apd-initial' ) || false === strpos( $shop_js, 'function openKadenceCart' ) || false === strpos( $shop_js, '.header-cart-button' ) || false === strpos( $shop_js, "format.type === 'us'" ) || false === strpos( $shop_js, "new CustomEvent('wc-blocks_added_to_cart', { bubbles: true })" ) || false === strpos( $shop_js, 'placeBandInsideFrame' ) || false === strpos( $shop_js, 'apd-band-layer' ) || false === strpos( $shop_js, 'plateSnapshot' ) || false === strpos( $shop_js, 'drawImageCover' ) || false === strpos( $shop_js, 'drawPlateArtwork' ) || false === strpos( $shop_js, 'artworkSourceBox' ) || false === strpos( $shop_js, 'design.text_box' ) || false === strpos( $shop_js, 'rowsForPlate' ) || false === strpos( $shop_js, 'syncSuvRows' ) || false === strpos( $shop_js, 'rowLimit' ) || false === strpos( $shop_js, 'plateGlyphs' ) || false === strpos( $shop_js, 'glyphInkScale' ) || false === strpos( $shop_js, 'wrapLines' ) || false === strpos( $shop_js, 'clampBoxInsideFrame' ) || false === strpos( $shop_js, 'paintHolderBody' ) || false === strpos( $shop_js, "source-in" ) || false === strpos( $shop_js, 'function upperPlateFields' ) || false === strpos( $shop_js, 'function applyPlainHolder' ) || false === strpos( $shop_js, "toLocaleUpperCase('bg')" ) || false === strpos( $shop_js, 'cfg.format.allow_empty' ) ) {
	fwrite( STDERR, "SHOP_JS_RULES_FAIL\n" );
	exit( 1 );
}

$shop_php = file_get_contents( APD_PLUGIN_DIR . 'includes/class-apd-woocommerce.php' );
if ( false !== strpos( $shop_js, "'100 900'" ) || false === strpos( $shop_js, "weight: '400'" ) || false === strpos( $shop_js, 'function drawingFont' ) || false === strpos( $shop_php, 'font-weight:400;' ) || false === strpos( $shop_js, "ctx.letterSpacing = '0px'" ) || false === strpos( $shop_js, 'function lineBlockHeight' ) || false !== strpos( $shop_js, '(chars.length - 1)' ) || false === strpos( $shop_css, 'letter-spacing: 0' ) || false === strpos( $shop_css, 'line-height: 1' ) ) {
	fwrite( STDERR, "SHOP_BOLD_FONT_FACE_FAIL\n" );
	exit( 1 );
}

$label_ui           = APD_Formats::frontend_payload( $format );
$label_ui['fonts']  = array(
	array(
		'id'     => 'font-note',
		'family' => 'Oswald',
		'note'   => 'кирилица',
		'weight' => 400,
		'style'  => 'normal',
		'url'    => 'https://example.invalid/oswald.woff2',
	),
	array(
		'id'     => 'font-plain',
		'family' => 'Barlow',
		'note'   => '',
		'weight' => 400,
		'style'  => 'normal',
		'url'    => 'https://example.invalid/barlow.woff2',
	),
);
$apd_payload = array(
	'format'       => $label_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$label_html = ob_get_clean();

if ( false === strpos( $label_html, '>Oswald — кирилица<' ) || false === strpos( $label_html, 'value="font-note"' ) || false === strpos( $label_html, '>Barlow<' ) || false !== strpos( $label_html, 'Barlow —' ) || false === strpos( $label_html, 'class="apd-font-hint"' ) || false === strpos( $label_html, 'За перфектна визия изберете шрифт' ) || false === strpos( $label_html, 'Позволените символи са цифри, букви' ) || false === strpos( $label_html, '&quot;!&quot;' ) || false === strpos( $label_html, '&quot;?&quot;' ) || false === strpos( $shop_css, 'font-weight: 700' ) || false !== strpos( $shop_css, 'padding-bottom: 0' ) || false === strpos( substr( $shop_css, (int) strpos( $shop_css, '.apd-field select {' ), 80 ), 'width: 100%' ) || false !== strpos( $shop_css, 'max-width: 50%' ) || false === strpos( $shop_js, "align === 'justify'" ) ) {
	fwrite( STDERR, "SHOP_FONT_LABEL_FAIL\n" );
	exit( 1 );
}

echo "SHOP_FONT_LABEL_OK\n";

$usa_fonts = array(
	array(
		'id'     => 'font-oswald',
		'family' => 'Oswald',
		'note'   => '',
		'weight' => 400,
		'style'  => 'normal',
		'url'    => 'https://example.invalid/oswald.woff2',
	),
	array(
		'id'     => 'font-usa',
		'family' => 'USA',
		'note'   => '',
		'weight' => 400,
		'style'  => 'normal',
		'url'    => 'https://example.invalid/usa.woff2',
	),
	array(
		'id'     => 'font-later',
		'family' => 'USABLE',
		'note'   => '',
		'weight' => 400,
		'style'  => 'normal',
		'url'    => 'https://example.invalid/usable.woff2',
	),
);

if (
	'font-usa' !== APD_Formats::default_font_id( $usa_fonts, 'us' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $usa_fonts, 'eu' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $usa_fonts, 'suv' )
	|| 'font-oswald' !== APD_Formats::default_font_id(
		array(
			$usa_fonts[0],
			array(
				'id'     => 'font-middle',
				'family' => 'My USA',
				'note'   => '',
			),
		),
		'us'
	)
) {
	fwrite( STDERR, "SHOP_USA_FONT_FAIL helper\n" );
	exit( 1 );
}

$usa_ui          = $label_ui;
$usa_ui['type']  = 'us';
$usa_ui['fonts'] = $usa_fonts;
$apd_payload     = array(
	'format'       => $usa_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);
unset( $apd_payload['cart_restore'] );
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$usa_html = ob_get_clean();

if ( ! preg_match( '/<option value="font-usa"[^>]*selected/', $usa_html ) || preg_match( '/<option value="font-oswald"[^>]*selected/', $usa_html ) ) {
	fwrite( STDERR, "SHOP_USA_FONT_FAIL html\n" );
	exit( 1 );
}

$eu_ui          = $label_ui;
$eu_ui['type']  = 'eu';
$eu_ui['fonts'] = $usa_fonts;
$apd_payload    = array(
	'format'       => $eu_ui,
	'palettes'     => $palettes,
	'i18n'         => $i18n,
	'color_fields' => array( 'text', 'border' ),
	'layouts'      => array( 'text_only' ),
	'minFont'      => 12,
);
ob_start();
include APD_PLUGIN_DIR . 'templates/product-configurator.php';
$eu_font_html = ob_get_clean();

if ( ! preg_match( '/<option value="font-oswald"[^>]*selected/', $eu_font_html ) || preg_match( '/<option value="font-usa"[^>]*selected/', $eu_font_html ) ) {
	fwrite( STDERR, "SHOP_USA_FONT_FAIL eu\n" );
	exit( 1 );
}

$german_first = array(
	array(
		'id'     => 'font-german',
		'family' => 'German Font',
	),
	array(
		'id'     => 'font-oswald',
		'family' => 'Oswald',
	),
);

$usa_over_german = array(
	array(
		'id'     => 'font-german',
		'family' => 'german plate',
	),
	array(
		'id'     => 'font-usa',
		'family' => 'USA Plate',
	),
);
$cyrillic_german = array(
	array(
		'id'     => 'font-bg',
		'family' => 'немски шрифт',
	),
	array(
		'id'     => 'font-oswald',
		'family' => 'Oswald',
	),
);

if (
	'font-oswald' !== APD_Formats::default_font_id( $german_first, 'eu' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $german_first, 'suv' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $german_first, 'holder' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $german_first, 'us' )
	|| 'font-german' !== APD_Formats::default_font_id( array( $german_first[0] ), 'eu' )
	|| 'font-usa' !== APD_Formats::default_font_id( $usa_over_german, 'us' )
	|| 'font-usa' !== APD_Formats::default_font_id( $usa_over_german, 'eu' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $cyrillic_german, 'moto' )
	|| 'font-oswald' !== APD_Formats::default_font_id( $cyrillic_german, 'holder_moto' )
) {
	fwrite( STDERR, "SHOP_GERMAN_FONT_FAIL\n" );
	exit( 1 );
}

echo "SHOP_USA_FONT_OK\n";
echo 'SHOP_US_AND_RESTORE_OK' . PHP_EOL;

$woo   = APD_WooCommerce::instance();
$tell  = new ReflectionMethod( APD_WooCommerce::class, 'notice_stale_cart_item' );
$tell->setAccessible( true );
$tell->invoke( $woo, array(), 0 );
ob_start();
$woo->render_stale_toasts();
$toast = ob_get_clean();
ob_start();
$woo->render_stale_toasts();
$again = ob_get_clean();
$plate = __( 'This plate', 'auto-plate-designer' );

if (
	false === strpos( $toast, 'class="apd-stale-toasts"' )
	|| false === strpos( $toast, 'class="apd-stale-toast"' )
	|| false === strpos( $toast, 'data-apd-stale-close' )
	|| false === strpos( $toast, 'position: fixed' )
	|| false === strpos( $toast, 'right: 1rem' )
	|| false === strpos( $toast, 'background: #fff' )
	|| false === strpos( $toast, $plate )
	|| '' !== $again
	|| false !== strpos( $toast, 'woocommerce-info' )
	|| false !== strpos( $toast, 'woocommerce-message' )
) {
	fwrite( STDERR, "SHOP_STALE_TOAST_FAIL\n" );
	exit( 1 );
}

echo "SHOP_STALE_TOAST_OK\n";
echo 'ALL_OK' . PHP_EOL;
