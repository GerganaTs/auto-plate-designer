<?php
/**
 * Render each admin tab as the shop administrator.
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

if ( defined( 'ABSPATH' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
}

if ( function_exists( 'wp_set_current_user' ) ) {
	wp_set_current_user( 1 );
}

if ( function_exists( 'switch_to_locale' ) ) {
	switch_to_locale( 'en_US' );
}

if ( ! class_exists( 'APD_Admin_Settings' ) ) {
	fwrite( STDERR, "APD_Admin_Settings missing\n" );
	exit( 1 );
}

$admin = APD_Admin_Settings::instance();
$tabs  = array( 'formats', 'catalog', 'presets', 'designs', 'palette', 'fonts', 'limits' );

$gated_add = array(
	'formats' => 'Add Format',
	'designs' => 'Add design',
	'fonts'   => 'Add font',
);

foreach ( $tabs as $tab ) {
	$_GET['page'] = 'apd-settings';
	$_GET['tab']  = $tab;
	unset( $_GET['add'], $_GET['edit'], $_GET['add_color'], $_GET['edit_color'] );

	ob_start();
	$admin->render_page();
	$html = ob_get_clean();

	$ok = false !== strpos( $html, 'nav-tab' )
		&& false !== strpos( $html, 'apd-tab' );

	if ( isset( $gated_add[ $tab ] ) ) {
		$ok = $ok && ( false !== strpos( $html, $gated_add[ $tab ] ) || false !== strpos( $html, 'apd_settings_nonce' ) );
	} else {
		$ok = $ok && false !== strpos( $html, 'apd_settings_nonce' );
	}

	if ( ! $ok ) {
		fwrite( STDERR, "TAB_FAIL {$tab}\n" );
		exit( 1 );
	}

	echo 'TAB_OK ' . $tab . ' len=' . strlen( $html ) . PHP_EOL;
}

$_GET['tab'] = 'formats';
unset( $_GET['add'], $_GET['edit'] );
ob_start();
$admin->render_page();
$list_html = ob_get_clean();
$list_ok = false !== strpos( $list_html, 'Add Format' )
	&& false === strpos( $list_html, 'data-apd-format-type' )
	&& false === strpos( $list_html, 'data-apd-plate-studio' )
	&& false !== strpos( $list_html, 'apd-table-scroll' );

echo $list_ok ? "FORMAT_LIST_OK\n" : "FORMAT_LIST_FAIL\n";

if ( ! $list_ok ) {
	exit( 1 );
}

$_GET['add'] = '1';
ob_start();
$admin->render_page();
$html = ob_get_clean();
unset( $_GET['add'] );
$format_ok = false !== strpos( $html, 'name="apd_format[no_frame]"' )
	&& false !== strpos( $html, 'data-apd-no-frame' )
	&& false !== strpos( $html, 'data-apd-plate-studio' )
	&& false !== strpos( $html, 'data-apd-plate-studio hidden' )
	&& false !== strpos( $html, 'data-apd-band-box' )
	&& false !== strpos( $html, 'data-apd-frame-box' )
	&& false !== strpos( $html, 'data-apd-frame-controls' )
	&& false !== strpos( $html, 'apd_format[band_box][x]' )
	&& false !== strpos( $html, 'apd_format[text_box][x]' )
	&& false !== strpos( $html, 'apd_format[border_width]' )
	&& false !== strpos( $html, 'apd_format[border_color]' )
	&& false !== strpos( $html, 'CA 1234' )
	&& false !== strpos( $html, 'apd_format[max_chars_row_1]' )
	&& false !== strpos( $html, 'apd_format[max_chars_row_2]' )
	&& false !== strpos( $html, 'data-apd-max-rows' )
	&& false === strpos( $html, 'CA 0909 BX' )
	&& false !== strpos( $html, 'data-apd-center-text-box' )
	&& false !== strpos( $html, 'value="moto"' )
	&& false !== strpos( $html, 'value="moto_plain"' )
	&& false !== strpos( $html, 'value="suv"' )
	&& false !== strpos( $html, 'value="suv_eu"' )
	&& false !== strpos( $html, 'Select type' )
	&& false !== strpos( $html, 'Country preset' )
	&& false !== strpos( $html, 'apd-metric-heading' )
	&& false !== strpos( $html, 'apd-metric-grid' )
	&& false !== strpos( $html, 'data-apd-row-styles' )
	&& false !== strpos( $html, 'Space between' )
	&& false !== strpos( $html, 'data-apd-align="justify"' )
	&& false !== strpos( $html, 'apd-table-scroll' )
	&& false === strpos( $html, 'Base image' );

echo $format_ok ? "FORMAT_ROW_OK\n" : "FORMAT_ROW_MISSING\n";

if ( ! $format_ok ) {
	exit( 1 );
}

$admin_js = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );
if ( false === strpos( $admin_js, 'adminSampleSuv' ) || false === strpos( $admin_js, 'CAAA 1234' ) || false === strpos( $admin_js, 'toggleSuvCharLimits' ) ) {
	fwrite( STDERR, "SUV_STUDIO_JS_FAIL\n" );
	exit( 1 );
}

echo "SUV_STUDIO_JS_OK\n";

$_GET['tab'] = 'palette';
unset( $_GET['add'], $_GET['edit'], $_GET['add_color'], $_GET['edit_color'] );
ob_start();
$admin->render_page();
$html = ob_get_clean();
$palette_list_ok = false !== strpos( $html, 'Add palette' )
	&& false !== strpos( $html, 'Add color' )
	&& false !== strpos( $html, 'apd_swatch[size]' )
	&& false !== strpos( $html, 'apd_swatch[shape]' )
	&& false !== strpos( $html, 'apd_swatch[radius]' )
	&& false !== strpos( $html, 'apd_swatch[border_color]' )
	&& false !== strpos( $html, 'apd-tab--palette' )
	&& false !== strpos( $html, 'apd-card' )
	&& false === strpos( $html, 'apd_palette[purpose]' )
	&& false === strpos( $html, 'name="apd_color[id]"' )
	&& false === strpos( $html, 'apd_palette[sets][]' )
	&& false === strpos( $html, 'name="apd_palette[sets]' );

echo $palette_list_ok ? "PALETTE_LIST_OK\n" : "PALETTE_LIST_FAIL\n";

if ( ! $palette_list_ok ) {
	exit( 1 );
}

$_GET['add'] = '1';
ob_start();
$admin->render_page();
$html = ob_get_clean();
unset( $_GET['add'] );
$palette_ok = false !== strpos( $html, 'apd_palette[purpose]' )
	&& false !== strpos( $html, 'apd_palette[name]' )
	&& false !== strpos( $html, 'apd_palette[color_ids][]' )
	&& false !== strpos( $html, 'apd_swatch[size]' )
	&& false === strpos( $html, 'apd_palette[sets][]' )
	&& false === strpos( $html, 'name="apd_palette[sets]' );

echo $palette_ok ? "PALETTE_TAB_OK\n" : "PALETTE_TAB_FAIL\n";

if ( ! $palette_ok ) {
	exit( 1 );
}

$_GET['add_color'] = '1';
ob_start();
$admin->render_page();
$html = ob_get_clean();
unset( $_GET['add_color'] );
$color_add_ok = false !== strpos( $html, 'name="apd_color[id]"' )
	&& false !== strpos( $html, 'apd_color[hex]' )
	&& false === strpos( $html, 'apd_palette[purpose]' );

echo $color_add_ok ? "COLOR_ADD_OK\n" : "COLOR_ADD_FAIL\n";

if ( ! $color_add_ok ) {
	exit( 1 );
}

$_GET['tab'] = 'designs';
unset( $_GET['add'], $_GET['edit'] );
ob_start();
$admin->render_page();
$html = ob_get_clean();
$designs_list_ok = false !== strpos( $html, 'Add design' )
	&& false === strpos( $html, 'save_design' )
	&& false === strpos( $html, 'apd_design[all_us]' );

echo $designs_list_ok ? "DESIGNS_LIST_OK\n" : "DESIGNS_LIST_FAIL\n";

if ( ! $designs_list_ok ) {
	exit( 1 );
}

$_GET['add'] = '1';
ob_start();
$admin->render_page();
$html = ob_get_clean();
unset( $_GET['add'] );
$designs_ok = false !== strpos( $html, 'apd_design[all_us]' )
	&& false !== strpos( $html, 'save_design' )
	&& false !== strpos( $html, 'apd_design[text_box][x]' )
	&& false !== strpos( $html, 'data-apd-text-box-stage' );

echo $designs_ok ? "DESIGNS_TAB_OK\n" : "DESIGNS_TAB_FAIL\n";

if ( ! $designs_ok ) {
	exit( 1 );
}

$_GET['tab'] = 'fonts';
unset( $_GET['add'] );
ob_start();
$admin->render_page();
$html = ob_get_clean();
$fonts_list_ok = false !== strpos( $html, 'Add font' )
	&& false === strpos( $html, 'save_font' );

echo $fonts_list_ok ? "FONTS_LIST_OK\n" : "FONTS_LIST_FAIL\n";

if ( ! $fonts_list_ok ) {
	exit( 1 );
}

$_GET['add'] = '1';
ob_start();
$admin->render_page();
$html = ob_get_clean();
unset( $_GET['add'] );
$fonts_ok = false !== strpos( $html, 'save_font' )
	&& false !== strpos( $html, 'apd_font[family]' );

echo $fonts_ok ? "FONTS_TAB_OK\n" : "FONTS_TAB_FAIL\n";

if ( ! $fonts_ok ) {
	exit( 1 );
}

echo "RENDER_OK\n";
