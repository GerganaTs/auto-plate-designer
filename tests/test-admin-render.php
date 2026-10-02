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
$tabs  = array( 'formats', 'presets', 'designs', 'palette', 'fonts', 'limits' );

$gated_add = array(
	'formats' => 'Add Format',
	'presets' => 'Add preset',
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

	if ( 'limits' === $tab && ( false === strpos( $html, 'name="apd_limits[layouts][holder][max_chars]"' ) || false === strpos( $html, 'value="' . APD_Formats::HOLDER_TEXT_MAX . '"' ) ) ) {
		fwrite( STDERR, "HOLDER_LAYOUT_LIMIT_FAIL\n" );
		exit( 1 );
	}
}

$_GET['tab'] = 'formats';
unset( $_GET['add'], $_GET['edit'] );
ob_start();
$admin->render_page();
$list_html = ob_get_clean();
$list_ok = false !== strpos( $list_html, 'Add Format' )
	&& false !== strpos( $list_html, '>Duplicate<' )
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
	&& false !== strpos( $html, 'value="moto_240"' )
	&& false !== strpos( $html, 'value="moto_plain"' )
	&& false !== strpos( $html, 'value="moto_plain_240"' )
	&& false !== strpos( $html, 'data-apd-holder-plate' )
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

$fonts_preselected = true;

if ( preg_match_all( '/<input type="checkbox" name="apd_format\[font_ids\]\[\]"[^>]*>/', $html, $font_inputs ) && ! empty( $font_inputs[0] ) ) {
	foreach ( $font_inputs[0] as $font_input ) {
		if ( false === strpos( $font_input, 'checked=' ) ) {
			$fonts_preselected = false;
			break;
		}
	}
}

echo $format_ok ? "FORMAT_ROW_OK\n" : "FORMAT_ROW_MISSING\n";
echo $fonts_preselected ? "FONT_PRESELECT_OK\n" : "FONT_PRESELECT_FAIL\n";

if ( false === strpos( $html, 'data-apd-fonts-all' ) || false === strpos( $html, 'Select all fonts' ) || preg_match( '/name="apd_format\[font_ids_all\]"[^>]*checked/', $html ) ) {
	fwrite( STDERR, "FONT_FOLLOW_CHECKBOX_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'name="apd_base_image"' ) || false === strpos( $html, 'name="apd_format[base_image_id]"' ) || false === strpos( $html, 'data-apd-media="image"' ) ) {
	fwrite( STDERR, "HOLDER_IMAGE_FIELDS_FAIL\n" );
	exit( 1 );
}

if ( ! $format_ok || ! $fonts_preselected ) {
	exit( 1 );
}

if ( false === strpos( $html, 'data-apd-custom-size' ) || false === strpos( $html, 'data-apd-custom-size hidden' ) || false === strpos( $html, 'data-apd-wrap' ) || false === strpos( $html, 'name="apd_format[wrap_text]"' ) || ! preg_match( '/name="apd_format\[wrap_text\]"[^>]*disabled/', $html ) ) {
	fwrite( STDERR, "STREET_FORM_FAIL\n" );
	exit( 1 );
}

echo "STREET_FORM_OK\n";

$admin_js = file_get_contents( APD_PLUGIN_DIR . 'assets/js/admin-settings.js' );

if ( false === strpos( $html, 'name="apd_format[palette_ids][]"' ) || false === strpos( $html, 'data-apd-format-palettes' ) || false === strpos( $html, 'apd-palette-chip' ) || false === strpos( $html, 'name="apd_format[palette_slots][text]"' ) || false === strpos( $html, 'name="apd_format[palette_slots][background]"' ) || false === strpos( $html, 'name="apd_format[palette_slots][border]"' ) || false === strpos( $html, 'value="holder_moto"' ) || false === strpos( $html, 'value="holder_d"' ) || false === strpos( $html, 'data-apd-photo-palettes' ) || false === strpos( $admin_js, 'function toggleFormatPalettes' ) || false === strpos( $admin_js, 'function usesPaletteSlots' ) || false === strpos( $admin_js, 'function isPhotoHolder' ) ) {
	fwrite( STDERR, "FORMAT_PALETTE_FORM_FAIL\n" );
	exit( 1 );
}

if ( false === strpos( $admin_js, 'bindSelectAllFonts' ) || false === strpos( $admin_js, 'data-apd-fonts-all' ) || false === strpos( $admin_js, 'master.checked = false' ) || false === strpos( $admin_js, 'box.checked = master.checked' ) ) {
	fwrite( STDERR, "FONT_FOLLOW_JS_FAIL\n" );
	exit( 1 );
}

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
	&& false !== strpos( $html, 'value="holder"' )
	&& false !== strpos( $html, 'Holder color' )
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
	&& false !== strpos( $html, 'Create product opens one WooCommerce product' )
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
	&& false !== strpos( $html, 'data-apd-text-box-stage' )
	&& false !== strpos( $html, 'data-apd-design-stage' );

if ( false === strpos( $admin_js, 'function updateDesignStage' ) || false === strpos( $admin_js, 'function isDesignEditor' ) || false === strpos( $admin_js, 'data-apd-design-stage' ) || false === strpos( $admin_js, 'function toggleProductDesign' ) ) {
	fwrite( STDERR, "DESIGN_STAGE_JS_FAIL\n" );
	exit( 1 );
}

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
	&& false !== strpos( $html, 'apd_font[family]' )
	&& false !== strpos( $html, 'apd_font[note]' )
	&& false === strpos( $html, 'apd_font[weight]' )
	&& 'Oswald — кирилица' === APD_Formats::font_shop_label( array( 'family' => 'Oswald', 'note' => 'кирилица' ) )
	&& 'Oswald' === APD_Formats::font_shop_label( array( 'family' => 'Oswald' ) );

echo $fonts_ok ? "FONTS_TAB_OK\n" : "FONTS_TAB_FAIL\n";

if ( ! $fonts_ok ) {
	exit( 1 );
}

$font_rows = APD_Plugin::get_settings()['fonts'];

if ( ! empty( $font_rows[0]['id'] ) ) {
	$_GET['tab']  = 'fonts';
	$_GET['edit'] = $font_rows[0]['id'];
	unset( $_GET['add'] );
	ob_start();
	$admin->render_page();
	$html = ob_get_clean();
	unset( $_GET['edit'] );
	$font_edit_ok = false !== strpos( $html, 'Update font' )
		&& false !== strpos( $html, 'name="apd_font[id]"' )
		&& false !== strpos( $html, 'value="' . $font_rows[0]['id'] . '"' )
		&& false !== strpos( $html, 'value="' . $font_rows[0]['family'] . '"' )
		&& false !== strpos( $html, 'Leave this empty to keep the current file.' );

	echo $font_edit_ok ? "FONT_EDIT_OK\n" : "FONT_EDIT_FAIL\n";

	if ( ! $font_edit_ok ) {
		exit( 1 );
	}
}

$font_target = null;

foreach ( $font_rows as $font_row ) {
	if ( ! empty( $font_row['id'] ) && ! empty( $font_row['attachment_id'] ) && ! empty( $font_row['family'] ) ) {
		$font_target = $font_row;
		break;
	}
}

if ( ! is_array( $font_target ) ) {
	fwrite( STDERR, "FONT_NOTE_SAVE_FAIL no uploaded font\n" );
	exit( 1 );
}

$font_backup = APD_Plugin::get_settings();
$font_fail   = function ( $message ) use ( $font_backup ) {
	APD_Plugin::save_settings( $font_backup );
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
};

$save_font = new ReflectionMethod( $admin, 'save_font_row' );
$save_font->setAccessible( true );

$post_font = function ( $note, $attachment_id ) use ( $font_target, $save_font, $admin ) {
	$_POST['apd_font'] = array(
		'id'            => $font_target['id'],
		'family'        => $font_target['family'],
		'note'          => $note,
		'weight'        => '700',
		'style'         => isset( $font_target['style'] ) ? $font_target['style'] : 'normal',
		'attachment_id' => (string) $attachment_id,
	);

	return $save_font->invoke( $admin );
};

if ( true !== APD_Security::validate_admin_label( 'само латиница', 40 ) || true === APD_Security::validate_admin_label( 'Oswald — кирилица', 40 ) ) {
	$font_fail( 'FONT_NOTE_CHARS_FAIL' );
}

$_POST['apd_font'] = array(
	'id'            => 'missing-font-note',
	'family'        => 'Missing',
	'note'          => 'кирилица',
	'style'         => 'normal',
	'attachment_id' => (string) $font_target['attachment_id'],
);
$missing = $save_font->invoke( $admin );

if ( ! is_wp_error( $missing ) ) {
	$font_fail( 'FONT_NOTE_MISSING_ID_FAIL' );
}

$bad_note = $post_font( '<b>кирилица</b>', $font_target['attachment_id'] );

if ( ! is_wp_error( $bad_note ) ) {
	$font_fail( 'FONT_NOTE_HTML_FAIL' );
}

$saved = $post_font( 'кирилица', $font_target['attachment_id'] );

if ( true !== $saved ) {
	$font_fail( 'FONT_NOTE_SAVE_FAIL ' . ( is_wp_error( $saved ) ? $saved->get_error_message() : 'not saved' ) );
}

$stored_note = null;

foreach ( APD_Plugin::get_settings()['fonts'] as $stored_font ) {
	if ( isset( $stored_font['id'] ) && $stored_font['id'] === $font_target['id'] ) {
		$stored_note = $stored_font;
		break;
	}
}

if ( ! is_array( $stored_note ) || 'кирилица' !== $stored_note['note'] || 400 !== (int) $stored_note['weight'] || (int) $font_target['attachment_id'] !== (int) $stored_note['attachment_id'] ) {
	$font_fail( 'FONT_NOTE_STORED_FAIL' );
}

$probe = APD_Formats::sanitize(
	array(
		'id'           => 'font-note-probe',
		'name'         => 'Font note probe',
		'type'         => 'eu',
		'width'        => 520,
		'height'       => 110,
		'border_width' => 8,
		'border_color' => '#000000',
		'font_ids_all' => 1,
	)
);
$probe_shop = is_wp_error( $probe ) ? array() : APD_Formats::frontend_payload( $probe );
$probe_row  = null;

foreach ( isset( $probe_shop['fonts'] ) ? $probe_shop['fonts'] : array() as $probe_font ) {
	if ( isset( $probe_font['id'] ) && $probe_font['id'] === $font_target['id'] ) {
		$probe_row = $probe_font;
		break;
	}
}

if ( ! is_array( $probe_row ) || $font_target['family'] !== $probe_row['family'] || 'кирилица' !== $probe_row['note'] || APD_Formats::font_shop_label( $probe_row ) !== $font_target['family'] . ' — кирилица' ) {
	$font_fail( 'FONT_NOTE_PAYLOAD_FAIL' );
}

$_GET['tab']  = 'fonts';
$_GET['edit'] = $font_target['id'];
unset( $_GET['add'] );
ob_start();
$admin->render_page();
$note_html = ob_get_clean();
unset( $_GET['edit'] );

if ( false === strpos( $note_html, 'name="apd_font[note]"' ) || false === strpos( $note_html, 'value="кирилица"' ) ) {
	$font_fail( 'FONT_NOTE_EDIT_FAIL' );
}

$cleared = $post_font( '', 0 );

if ( true !== $cleared ) {
	$font_fail( 'FONT_NOTE_CLEAR_FAIL ' . ( is_wp_error( $cleared ) ? $cleared->get_error_message() : 'not saved' ) );
}

$cleared_row = null;

foreach ( APD_Plugin::get_settings()['fonts'] as $stored_font ) {
	if ( isset( $stored_font['id'] ) && $stored_font['id'] === $font_target['id'] ) {
		$cleared_row = $stored_font;
		break;
	}
}

if ( ! is_array( $cleared_row ) || '' !== $cleared_row['note'] || (int) $font_target['attachment_id'] !== (int) $cleared_row['attachment_id'] || $font_target['family'] !== APD_Formats::font_shop_label( $cleared_row ) ) {
	$font_fail( 'FONT_NOTE_CLEARED_FAIL' );
}

APD_Plugin::save_settings( $font_backup );
unset( $_POST['apd_font'] );

echo "FONT_NOTE_OK\n";

echo "RENDER_OK\n";
