<?php
/**
 * Embossed counts, script checks, and order rows.
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

$failed = 0;

$check = static function ( $ok, $label ) use ( &$failed ) {
	if ( ! $ok ) {
		fwrite( STDERR, 'FAIL ' . $label . PHP_EOL );
		$failed++;
	}
};

$eu = array(
	'type' => 'eu',
);
$limits = APD_Formats::embossed_limits( $eu );
$check( 9 === $limits[0] && 0 === $limits[1], 'one-row embossed default is 9' );
$check( false === APD_Formats::letters_are_digital( $eu, 'latin', 'CA 1234' ), 'short latin stays embossed' );
$check( true === APD_Formats::letters_are_digital( $eu, 'latin', 'CA 1234 BX' ), 'space counts and 10 is digital' );
$check( true === APD_Formats::letters_are_digital( $eu, 'cyrillic', 'СА' ), 'cyrillic is always digital' );

$moto = array(
	'type' => 'moto',
);
$moto_limits = APD_Formats::embossed_limits( $moto );
$check( 5 === $moto_limits[0] && 4 === $moto_limits[1], 'two-row embossed default stays 5 and 4' );
$check( false === APD_Formats::letters_are_digital( $moto, 'latin', "CA\n12" ), 'short two-row latin stays embossed' );
$check( true === APD_Formats::letters_are_digital( $moto, 'latin', "CA\n12", true ), 'a digital-only plate is digital at every length' );
$check( false === APD_Formats::letters_are_digital( array( 'type' => 'uk' ), 'latin', 'LK00 FDN', true ), 'english stays without a letter rule' );
$check( false === APD_WooCommerce::product_is_digital_only( 0 ), 'a missing product is not digital-only' );
$check( true === APD_Formats::letters_are_digital( $moto, 'latin', "CA 123\n1234" ), 'either long row is digital' );
$typing = APD_Formats::typing_row_defaults( 'moto' );
$check( 7 === $typing[0] && 5 === $typing[1], 'new moto typing defaults are 7 and 5' );
$suv_typing = APD_Formats::typing_row_defaults( 'suv' );
$check( 8 === $suv_typing[0] && 6 === $suv_typing[1], 'new suv typing defaults are 8 and 6' );

$color = array(
	'type' => 'color',
);
$check( 'digital' === APD_Formats::letter_rule( 'color' ), 'color letters are digital' );
$check( true === APD_Formats::letters_are_digital( $color, 'latin', 'A' ), 'a short color plate is still digital' );
$check( true === APD_Formats::offers_uncolored_frame( 'color' ), 'color can leave the frame uncolored' );
$check( false === APD_Formats::offers_digital_palette( 'color' ), 'color has no paired digital palette' );
$framed = array(
	'type'         => 'suv',
	'no_frame'     => false,
	'border_width' => 4,
);
$open = array(
	'type'         => 'suv',
	'no_frame'     => true,
	'border_width' => 0,
);
$check( true === APD_Formats::shares_text_frame_color( 'eu' ), 'eu can share text and frame color' );
$check( true === APD_Formats::shares_text_frame_color( 'eu', array( 'type' => 'eu', 'no_frame' => false, 'border_width' => 4 ) ), 'eu with a frame shares text and frame color' );
$check( false === APD_Formats::shares_text_frame_color( 'eu', array( 'type' => 'eu', 'no_frame' => true, 'border_width' => 0 ) ), 'eu without a frame keeps a text color' );
$check( true === APD_Formats::shares_text_frame_color( 'eu_plain' ), 'plain car plate can share text and frame color' );
$check( true === APD_Formats::shares_text_frame_color( 'suv', $framed ), 'suv with a frame shares text and frame color' );
$check( false === APD_Formats::shares_text_frame_color( 'suv', $open ), 'suv without a frame keeps a text color' );
$check( true === APD_Formats::shares_text_frame_color( 'moto', array( 'type' => 'moto', 'no_frame' => false, 'border_width' => 4 ) ), 'motorcycle with a frame shares text and frame color' );
$check( true === APD_Formats::shares_text_frame_color( 'color', array( 'type' => 'color', 'no_frame' => false, 'border_width' => 4 ) ), 'color plate with a frame shares text and frame color' );
$check( true === APD_Formats::shares_text_frame_color( 'mini' ), 'mini can share text and frame color' );
$check( true === APD_Formats::shares_text_frame_color( 'mini', array( 'type' => 'mini', 'no_frame' => false, 'border_width' => 4 ) ), 'mini with a frame shares text and frame color' );
$check( false === APD_Formats::shares_text_frame_color( 'mini', array( 'type' => 'mini', 'no_frame' => true, 'border_width' => 0 ) ), 'mini without a frame keeps a text color' );
$check( true === APD_Formats::shares_text_frame_color( 'super_mini' ), 'super mini can share text and frame color' );
$check( false === APD_Formats::shares_text_frame_color( 'uk' ), 'english keeps a separate frame color' );
$check( false === APD_Formats::shares_text_frame_color( 'us' ), 'usa keeps a text color' );
$check( false === APD_Formats::shares_text_frame_color( 'austria' ), 'austria keeps its fixed colors' );
$check( false === APD_Formats::shares_text_frame_color( 'fr' ), 'french keeps its fixed colors' );
$check( array( 'text', 'background' ) === APD_Formats::palette_slot_keys_for( 'eu' ), 'eu format tab has no separate frame palette' );
$check( array( 'text', 'background' ) === APD_Formats::palette_slot_keys_for( 'eu_plain' ), 'plain car plate format tab has no separate frame palette' );
$check( array( 'text', 'background' ) === APD_Formats::palette_slot_keys_for( 'suv' ), 'suv format tab hides the separate frame palette' );
$check( array( 'text', 'background', 'border', 'slogan' ) === APD_Formats::palette_slot_keys_for( 'uk' ), 'english format tab keeps text, plate, frame, and slogan' );
$check( true === APD_Formats::js_capabilities( 'eu' )['sharesTextFrame'], 'eu format tab can share the text palette' );
$check( true === APD_Formats::js_capabilities( 'suv' )['sharesTextFrame'], 'suv format tab can share the text palette' );
$check( false === APD_Formats::js_capabilities( 'uk' )['sharesTextFrame'], 'english format tab keeps separate palettes' );

$split_eu = array(
	'type'                 => 'eu',
	'no_frame'             => false,
	'border_width'         => 4,
	'separate_text_frame'  => true,
);
$split_suv = array(
	'type'                 => 'suv',
	'no_frame'             => false,
	'border_width'         => 4,
	'separate_text_frame'  => true,
);
$check( false === APD_Formats::shares_text_frame_color( 'eu', $split_eu ), 'eu with separate colors does not share the frame' );
$check( true === APD_Formats::format_splits_text_frame( $split_eu ), 'eu can store a separate frame palette' );
$check( array( 'text', 'background', 'border' ) === APD_Formats::palette_slot_keys_for( 'eu', $split_eu ), 'split eu exposes a frame palette' );
$check( array( 'text', 'background' ) === APD_Formats::palette_slot_keys_for( 'eu', array( 'type' => 'eu', 'no_frame' => false, 'border_width' => 4 ) ), 'eu without the option keeps one palette' );
$check( false === APD_Formats::shares_text_frame_color( 'suv', $split_suv ), 'split suv does not share the frame' );
$check( array( 'text', 'background', 'border' ) === APD_Formats::palette_slot_keys_for( 'suv', $split_suv ), 'split suv shows a frame palette' );
$check( array( 'text', 'background' ) === APD_Formats::palette_slot_keys_for( 'suv', $framed ), 'suv without the option hides the frame palette' );
$check( false === APD_Formats::frame_uses_text_color( 'eu', $split_eu, 'embossed' ), 'split eu keeps the frame color while letters are embossed' );
$check( false === APD_Formats::frame_uses_text_color( 'eu', $split_eu, 'digital' ), 'split eu keeps the frame color while letters are digital' );
$check( true === APD_Formats::frame_uses_text_color( 'eu', array( 'type' => 'eu', 'no_frame' => false, 'border_width' => 4 ), 'digital' ), 'shared eu still paints a digital frame with the letter color' );
$check( true === APD_Formats::frame_uses_text_color( 'eu', array( 'type' => 'eu', 'no_frame' => false, 'border_width' => 4 ), 'embossed' ), 'shared eu still paints an embossed frame with the letter color' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'uk', 'separate_text_frame' => true, 'no_frame' => false, 'border_width' => 4 ) ), 'english ignores separate colors' );
$check( array( 'text', 'background', 'border', 'slogan' ) === APD_Formats::palette_slot_keys_for( 'uk', array( 'type' => 'uk', 'separate_text_frame' => true, 'no_frame' => false, 'border_width' => 4 ) ), 'english slots stay text, plate, frame, and slogan' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'mini', 'separate_text_frame' => true ) ), 'mini without a frame ignores separate colors' );
$check( true === APD_Formats::format_splits_text_frame( array( 'type' => 'mini', 'separate_text_frame' => true, 'no_frame' => false, 'border_width' => 4 ) ), 'framed mini can keep a separate frame color' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'fr', 'separate_text_frame' => true, 'no_frame' => false, 'border_width' => 4 ) ), 'french ignores separate colors' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'austria', 'separate_text_frame' => true ) ), 'austria ignores separate colors' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'us', 'separate_text_frame' => true ) ), 'usa ignores separate colors' );
$check( false === APD_Formats::format_splits_text_frame( array( 'type' => 'holder', 'separate_text_frame' => true ) ), 'holder ignores separate colors' );
$check( false === APD_Formats::frame_uses_text_color( 'mini', array( 'type' => 'mini' ), 'digital' ), 'frameless mini digital does not borrow a frame color' );

$mini = array(
	'type' => 'mini',
);
$check( 'count' === APD_Formats::letter_rule( 'mini' ), 'mini follows the count' );
$check( false === APD_Formats::letters_are_digital( $mini, 'latin', 'CA 1234' ), 'mini latin within 9 is embossed' );
$check( true === APD_Formats::letters_are_digital( $mini, 'latin', 'CA 1234 BX' ), 'mini latin from 10 is digital' );
$check( true === APD_Formats::offers_uncolored_frame( 'mini' ), 'mini can leave the frame uncolored' );
$check( true === APD_Formats::offers_uncolored_frame( 'super_mini' ), 'super mini can leave the frame uncolored' );
$check( true === APD_Formats::offers_script( 'mini' ), 'mini offers a script' );
$check( true === APD_Formats::offers_script( 'super_mini_plain' ), 'super mini offers a script' );
$check( 'count' === APD_Formats::letter_rule( 'super_mini' ), 'super mini follows the count' );

$check( '' === APD_Formats::letter_rule( 'uk' ), 'english has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'custom' ), 'street has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'us' ), 'usa has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'fr' ), 'french has no letter rule' );
$check( false === APD_Formats::offers_script( 'uk' ), 'english has no script choice' );
$check( true === APD_Formats::offers_script( 'holder' ), 'holder offers a script' );
$check( true === APD_Formats::offers_script( 'holder_moto' ), 'motorcycle holder offers a script' );
$check( true === APD_Formats::offers_script( 'holder_d' ), 'type D holder offers a script' );

$latin_ok = APD_Security::validate_plate_script( 'CA 1234', 'latin' );
$cyr_ok   = APD_Security::validate_plate_script( 'СА 1234', 'cyrillic' );
$latin_bad = APD_Security::validate_plate_script( 'СА', 'latin' );
$cyr_bad   = APD_Security::validate_plate_script( 'CA', 'cyrillic' );
$check( true === $latin_ok, 'latin accepts latin' );
$check( true === $cyr_ok, 'cyrillic accepts cyrillic and digits' );
$check( is_wp_error( $latin_bad ), 'latin rejects cyrillic' );
$check( is_wp_error( $cyr_bad ), 'cyrillic rejects latin' );
$mixed = APD_Security::validate_plate_script( 'СА 12 AB', 'cyrillic' );
$check( is_wp_error( $mixed ) && __( 'Latin letters are not allowed when Cyrillic is selected.', 'auto-plate-designer' ) === $mixed->get_error_message(), 'cyrillic then latin names the latin letters' );

$empty_product = APD_Admin_Settings::product_script_state( 0, array( 'type' => 'eu' ) );
$check( array( 'latin' ) === $empty_product['scripts'], 'a product with no script meta stays latin' );
$usa_product = APD_Admin_Settings::product_script_state( 0, array( 'type' => 'us' ) );
$check( array() === $usa_product['scripts'], 'usa products have no script' );
$check( true === APD_Security::validate_plate_script( 'СА 12 AB', 'combined' ), 'combined accepts both alphabets' );
$holder_product = APD_Admin_Settings::product_script_state( 0, array( 'type' => 'holder' ) );
$check( array( 'latin' ) === $holder_product['scripts'], 'a holder with no script meta stays latin' );

$combined_rows = APD_WooCommerce::config_display_rows(
	array(
		'format_type'         => 'holder',
		'text'                => 'СА AB',
		'script'              => 'combined',
		'font_label'          => 'Oswald',
		'font_label_cyrillic' => 'Roboto',
	)
);
$combined_hit = false;
$font_hit     = false;
foreach ( $combined_rows as $row ) {
	if ( __( 'Combined font, Cyrillic and Latin', 'auto-plate-designer' ) === $row['key'] && sprintf( __( 'Latin: %1$s. Cyrillic: %2$s.', 'auto-plate-designer' ), 'Oswald', 'Roboto' ) === $row['value'] ) {
		$combined_hit = true;
	}
	if ( __( 'Font', 'auto-plate-designer' ) === $row['key'] ) {
		$font_hit = true;
	}
}
$check( $combined_hit && ! $font_hit, 'combined holder names both fonts' );

$latin_holder_rows = APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'holder',
		'text'        => 'AB',
		'script'      => 'latin',
		'font_label'  => 'Oswald',
	)
);
$latin_holder_hit = false;
foreach ( $latin_holder_rows as $row ) {
	if ( __( 'Latin', 'auto-plate-designer' ) === $row['key'] && 'Oswald' === $row['value'] ) {
		$latin_holder_hit = true;
	}
}
$check( $latin_holder_hit, 'latin holder names its font' );

$street = APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'custom',
		'text'        => 'HELLO',
		'no_frame'    => false,
		'frame_plain' => null,
	)
);
$street_frame = '';
foreach ( $street as $row ) {
	if ( __( 'Frame', 'auto-plate-designer' ) === $row['key'] ) {
		$street_frame = $row['value'];
	}
}
$check( __( 'With frame', 'auto-plate-designer' ) === $street_frame, 'street still says with frame' );

$registration = APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'eu',
		'text'        => 'CA 1234',
		'no_frame'    => false,
		'letters'     => 'embossed',
		'frame_plain' => false,
		'comment'     => 'Call me',
	)
);
$found_letters = '';
$found_frame   = '';
$found_comment = '';
foreach ( $registration as $row ) {
	if ( __( 'Letters', 'auto-plate-designer' ) === $row['key'] ) {
		$found_letters = $row['value'];
	}
	if ( __( 'Frame', 'auto-plate-designer' ) === $row['key'] ) {
		$found_frame = $row['value'];
	}
	if ( __( 'Comment', 'auto-plate-designer' ) === $row['key'] ) {
		$found_comment = $row['value'];
	}
}
$check( __( 'Embossed letters', 'auto-plate-designer' ) === $found_letters, 'order names embossed letters' );
$check( __( 'Embossed frame', 'auto-plate-designer' ) === $found_frame, 'order names an embossed frame' );
$check( 'Call me' === $found_comment, 'comment is on the order' );

$plain = APD_WooCommerce::config_display_rows(
	array(
		'format_type' => 'color',
		'text'        => 'A',
		'no_frame'    => false,
		'letters'     => 'digital',
		'frame_plain' => true,
	)
);
$plain_frame = '';
foreach ( $plain as $row ) {
	if ( __( 'Frame', 'auto-plate-designer' ) === $row['key'] ) {
		$plain_frame = $row['value'];
	}
}
$check( __( 'Embossed, without color', 'auto-plate-designer' ) === $plain_frame, 'uncolored frame stays embossed' );

$digital_plate = APD_WooCommerce::config_display_rows(
	array(
		'format_type'  => 'moto_plain_240',
		'text'         => "P PP\n6987",
		'no_frame'     => false,
		'letters'      => 'digital',
		'frame_plain'  => false,
		'digital_only' => true,
	)
);
$digital_plain = APD_WooCommerce::config_display_rows(
	array(
		'format_type'  => 'moto_plain_240',
		'text'         => "P PP\n6987",
		'no_frame'     => false,
		'letters'      => 'digital',
		'frame_plain'  => true,
		'digital_only' => true,
	)
);
$digital_frame = '';
$digital_plain_frame = '';
$digital_letters = '';
foreach ( $digital_plate as $row ) {
	if ( __( 'Frame', 'auto-plate-designer' ) === $row['key'] ) {
		$digital_frame = $row['value'];
	}
	if ( __( 'Letters', 'auto-plate-designer' ) === $row['key'] ) {
		$digital_letters = $row['value'];
	}
}
foreach ( $digital_plain as $row ) {
	if ( __( 'Frame', 'auto-plate-designer' ) === $row['key'] ) {
		$digital_plain_frame = $row['value'];
	}
}
$check( __( 'Digital print', 'auto-plate-designer' ) === $digital_letters, 'digital-only order says digital print' );
$check( __( 'With frame', 'auto-plate-designer' ) === $digital_frame, 'digital-only frame is not called embossed' );
$check( __( 'Without color', 'auto-plate-designer' ) === $digital_plain_frame, 'digital-only uncolored frame does not say embossed' );
$check( '' === APD_Admin_Settings::product_cyrillic_text( 0 ), 'missing product has no cyrillic text' );

if ( $failed > 0 ) {
	fwrite( STDERR, $failed . " letter production checks failed\n" );
	exit( 1 );
}

echo "OK\n";
