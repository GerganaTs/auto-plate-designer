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

if ( ! class_exists( 'APD_Admin_Settings' ) ) {
	fwrite( STDERR, "APD_Admin_Settings missing\n" );
	exit( 1 );
}

$admin = APD_Admin_Settings::instance();
$tabs  = array( 'formats', 'catalog', 'presets', 'designs', 'palette', 'fonts', 'limits' );

foreach ( $tabs as $tab ) {
	$_GET['page'] = 'apd-settings';
	$_GET['tab']  = $tab;

	ob_start();
	$admin->render_page();
	$html = ob_get_clean();

	$ok = false !== strpos( $html, 'nav-tab' )
		&& false !== strpos( $html, 'apd_settings_nonce' )
		&& false !== strpos( $html, 'apd-tab' );

	if ( ! $ok ) {
		fwrite( STDERR, "TAB_FAIL {$tab}\n" );
		exit( 1 );
	}

	echo 'TAB_OK ' . $tab . ' len=' . strlen( $html ) . PHP_EOL;
}

$_GET['tab'] = 'formats';
ob_start();
$admin->render_page();
$html = ob_get_clean();
$format_ok = false !== strpos( $html, 'name="apd_format[no_frame]"' )
	&& false !== strpos( $html, 'data-apd-no-frame' )
	&& false !== strpos( $html, 'data-apd-plate-studio' )
	&& false !== strpos( $html, 'data-apd-band-box' )
	&& false !== strpos( $html, 'data-apd-frame-box' )
	&& false !== strpos( $html, 'data-apd-frame-controls' )
	&& false !== strpos( $html, 'apd_format[band_box][x]' )
	&& false !== strpos( $html, 'apd_format[text_box][x]' )
	&& false !== strpos( $html, 'apd_format[border_width]' )
	&& false !== strpos( $html, 'apd_format[border_color]' )
	&& false !== strpos( $html, 'CA 0909 BX' )
	&& false !== strpos( $html, 'data-apd-center-text-box' )
	&& false !== strpos( $html, 'value="moto"' )
	&& false !== strpos( $html, 'value="suv"' )
	&& false !== strpos( $html, 'apd-metric-grid' )
	&& false !== strpos( $html, 'apd-table-scroll' )
	&& false === strpos( $html, 'Base image' );

echo $format_ok ? "FORMAT_ROW_OK\n" : "FORMAT_ROW_MISSING\n";

if ( ! $format_ok ) {
	exit( 1 );
}

$_GET['tab'] = 'palette';
ob_start();
$admin->render_page();
$html = ob_get_clean();
$palette_ok = false !== strpos( $html, 'apd_palette[purpose]' )
	&& false !== strpos( $html, 'apd_palette[name]' )
	&& false !== strpos( $html, 'apd_palette[color_ids][]' )
	&& false !== strpos( $html, 'apd_color[id]' )
	&& false !== strpos( $html, 'apd_swatch[size]' )
	&& false !== strpos( $html, 'apd_swatch[shape]' )
	&& false !== strpos( $html, 'apd_swatch[radius]' )
	&& false !== strpos( $html, 'apd_swatch[border_color]' )
	&& false === strpos( $html, 'apd_palette[sets][]' )
	&& false === strpos( $html, 'name="apd_palette[sets]' );

echo $palette_ok ? "PALETTE_TAB_OK\n" : "PALETTE_TAB_FAIL\n";

if ( ! $palette_ok ) {
	exit( 1 );
}

$_GET['tab'] = 'designs';
ob_start();
$admin->render_page();
$html = ob_get_clean();
$designs_ok = false !== strpos( $html, 'apd_design[all_us]' )
	&& false !== strpos( $html, 'save_design' )
	&& false !== strpos( $html, 'apd_design[text_box][x]' )
	&& false !== strpos( $html, 'data-apd-text-box-stage' );

echo $designs_ok ? "DESIGNS_TAB_OK\n" : "DESIGNS_TAB_FAIL\n";

if ( ! $designs_ok ) {
	exit( 1 );
}

echo "RENDER_OK\n";
