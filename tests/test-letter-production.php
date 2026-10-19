<?php
/**
 * Embossed counts, script checks, and order rows.
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

$mini = array(
	'type' => 'mini',
);
$check( 'count' === APD_Formats::letter_rule( 'mini' ), 'mini follows the count' );
$check( false === APD_Formats::letters_are_digital( $mini, 'latin', 'CA 1234' ), 'mini latin within 9 is embossed' );
$check( true === APD_Formats::letters_are_digital( $mini, 'latin', 'CA 1234 BX' ), 'mini latin from 10 is digital' );
$check( false === APD_Formats::offers_uncolored_frame( 'mini' ), 'mini has no frame choice' );
$check( true === APD_Formats::offers_script( 'mini' ), 'mini offers a script' );

$check( '' === APD_Formats::letter_rule( 'uk' ), 'english has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'custom' ), 'street has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'us' ), 'usa has no letter rule' );
$check( '' === APD_Formats::letter_rule( 'fr' ), 'french has no letter rule' );
$check( false === APD_Formats::offers_script( 'uk' ), 'english has no script choice' );
$check( false === APD_Formats::offers_script( 'holder' ), 'holder has no script choice' );

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
$check( '' === APD_Admin_Settings::product_cyrillic_text( 0 ), 'missing product has no cyrillic text' );

if ( $failed > 0 ) {
	fwrite( STDERR, $failed . " letter production checks failed\n" );
	exit( 1 );
}

echo "OK\n";
