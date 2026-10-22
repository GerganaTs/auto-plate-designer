<?php
/**
 * A product can hide text, frame, and plate colors without a new format.
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

if ( ! class_exists( 'APD_WooCommerce' ) || ! class_exists( 'APD_Admin_Settings' ) ) {
	fwrite( STDERR, "APD classes missing\n" );
	exit( 1 );
}

$photo_hexes = APD_Admin_Settings::locked_color_hexes( 'holder_d', '#C41E3A' );

if ( '#000000' !== $photo_hexes['text'] || '#FFFFFF' !== $photo_hexes['background'] || '#000000' !== $photo_hexes['border'] ) {
	fwrite( STDERR, 'LOCK_PHOTO_HEX_FAIL ' . wp_json_encode( $photo_hexes ) . PHP_EOL );
	exit( 1 );
}

$slots = APD_Color_Palettes::DEFAULT_PALETTE_IDS;

foreach ( array( 'text', 'background', 'border', 'slogan' ) as $purpose ) {
	$palette = APD_Color_Palettes::get( $slots[ $purpose ] );

	if ( ! is_array( $palette ) || empty( $palette['active'] ) ) {
		fwrite( STDERR, "LOCK_PALETTE_MISSING {$purpose}\n" );
		exit( 1 );
	}
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);

if ( empty( $admins ) ) {
	fwrite( STDERR, "LOCK_ADMIN_MISSING\n" );
	exit( 1 );
}

wp_set_current_user( (int) $admins[0]->ID );

$created_formats = array();
$created_posts   = array();
$cleanup         = static function () use ( &$created_formats, &$created_posts ) {
	foreach ( $created_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	foreach ( $created_formats as $format_id ) {
		APD_Formats::delete( $format_id );
	}

	unset(
		$_POST['apd_nonce'],
		$_POST['apd_text'],
		$_POST['apd_text_color'],
		$_POST['apd_background_color'],
		$_POST['apd_border_color'],
		$_POST['apd_frame_plain'],
		$_POST['apd_slogan'],
		$_POST['apd_slogan_color'],
		$_POST['apd_legacy_no_frame']
	);
};

$fail = static function ( $message ) use ( $cleanup ) {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$save_format = static function ( $raw ) use ( &$created_formats, $fail ) {
	$format = APD_Formats::save( $raw );

	if ( is_wp_error( $format ) ) {
		$fail( 'LOCK_FORMAT_FAIL ' . $format->get_error_message() );
	}

	$created_formats[] = $format['id'];

	return $format;
};

$make_product = static function ( $format_id, $title, $lock, $fill = '' ) use ( &$created_posts, $fail ) {
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'product',
			'post_status' => 'draft',
			'post_title'  => $title,
		)
	);

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		$fail( 'LOCK_PRODUCT_FAIL ' . $title );
	}

	$created_posts[] = (int) $post_id;
	update_post_meta( $post_id, APD_Admin_Settings::META_ENABLED, 'yes' );
	update_post_meta( $post_id, APD_Admin_Settings::META_FORMAT, $format_id );

	if ( $lock ) {
		update_post_meta( $post_id, APD_Admin_Settings::META_LOCK_COLORS, 'yes' );
	}

	if ( '' !== $fill ) {
		update_post_meta( $post_id, APD_Admin_Settings::META_DEFAULT_FILL, $fill );
	}

	return (int) $post_id;
};

$posted_red = '#C41E3A';
$post_config = static function ( $product_id, $plain = false, $extra = array() ) use ( $posted_red ) {
	$_POST['apd_nonce']             = wp_create_nonce( 'apd_configure' );
	$_POST['apd_text']              = 'AB12';
	$_POST['apd_text_color']        = $posted_red;
	$_POST['apd_background_color']  = $posted_red;
	$_POST['apd_border_color']      = $posted_red;

	if ( $plain ) {
		$_POST['apd_frame_plain'] = '1';
	} else {
		unset( $_POST['apd_frame_plain'] );
	}

	foreach ( $extra as $key => $value ) {
		$_POST[ $key ] = $value;
	}

	$item = APD_WooCommerce::instance()->add_cart_item_data( array(), $product_id );

	return isset( $item[ APD_WooCommerce::CART_KEY ] ) && is_array( $item[ APD_WooCommerce::CART_KEY ] ) ? $item[ APD_WooCommerce::CART_KEY ] : null;
};

$same = static function ( $left, $right ) {
	return 0 === strcasecmp( (string) $left, (string) $right );
};

$separate = $save_format(
	array(
		'name'                => 'APD lock separate colors',
		'type'                => 'eu_plain',
		'width'               => 520,
		'height'              => 110,
		'border_width'        => 8,
		'border_color'        => '#111111',
		'separate_text_frame' => true,
		'initial_fill'        => true,
		'palette_slots'       => array(
			'text'       => $slots['text'],
			'background' => $slots['background'],
			'border'     => $slots['border'],
		),
	)
);
$shared = $save_format(
	array(
		'name'          => 'APD lock shared colors',
		'type'          => 'eu_plain',
		'width'         => 520,
		'height'        => 110,
		'border_width'  => 8,
		'border_color'  => '#111111',
		'palette_slots' => array(
			'text'       => $slots['text'],
			'background' => $slots['background'],
		),
	)
);
$english = $save_format(
	array(
		'name'          => 'APD lock english colors',
		'type'          => 'uk',
		'palette_slots' => array(
			'text'       => $slots['text'],
			'background' => $slots['background'],
			'border'     => $slots['border'],
			'slogan'     => $slots['slogan'],
		),
	)
);

$open_id    = $make_product( $separate['id'], 'APD lock open', false, '#1B365D' );
$locked_id  = $make_product( $separate['id'], 'APD lock closed', true, '#1B365D' );
$shared_id  = $make_product( $shared['id'], 'APD lock shared open', false );
$shared_lock = $make_product( $shared['id'], 'APD lock shared closed', true );
$english_id = $make_product( $english['id'], 'APD lock english', true );

$open_catalog = APD_WooCommerce::product_offer_catalog( $open_id );
$locked_catalog = APD_WooCommerce::product_offer_catalog( $locked_id );

if ( array_key_exists( 'lock_colors', $open_catalog ) || empty( $locked_catalog['lock_colors'] ) ) {
	$fail( 'LOCK_FINGERPRINT_KEY_FAIL' );
}

$open_fingerprint = APD_WooCommerce::offer_fingerprint( $open_id );
$locked_fingerprint = APD_WooCommerce::offer_fingerprint( $locked_id );

if ( '' === $open_fingerprint || $open_fingerprint === $locked_fingerprint ) {
	$fail( 'LOCK_FINGERPRINT_FAIL' );
}

delete_post_meta( $locked_id, APD_Admin_Settings::META_LOCK_COLORS );

if ( APD_WooCommerce::offer_fingerprint( $locked_id ) !== $open_fingerprint || array_key_exists( 'lock_colors', APD_WooCommerce::product_offer_catalog( $locked_id ) ) ) {
	$fail( 'LOCK_FINGERPRINT_RESTORE_FAIL' );
}

update_post_meta( $locked_id, APD_Admin_Settings::META_LOCK_COLORS, 'yes' );

$open_config = $post_config( $open_id );
$locked_config = $post_config( $locked_id );
$plain_config = $post_config( $locked_id, true );
$shared_config = $post_config( $shared_id );
$shared_locked_config = $post_config( $shared_lock );
$shared_plain_config = $post_config( $shared_lock, true );
$english_config = $post_config(
	$english_id,
	false,
	array(
		'apd_slogan'       => 'HELLO',
		'apd_slogan_color' => $posted_red,
	)
);

foreach (
	array(
		'open'          => $open_config,
		'locked'        => $locked_config,
		'plain'         => $plain_config,
		'shared'        => $shared_config,
		'shared_locked' => $shared_locked_config,
		'shared_plain'  => $shared_plain_config,
		'english'       => $english_config,
	) as $label => $config
) {
	if ( ! is_array( $config ) ) {
		$fail( 'LOCK_CONFIG_MISSING ' . $label );
	}
}

if ( ! $same( $open_config['text_color'], $posted_red ) || ! $same( $open_config['background_color'], $posted_red ) || ! $same( $open_config['border_color'], $posted_red ) ) {
	$fail( 'LOCK_OPEN_COLOR_FAIL ' . wp_json_encode( $open_config ) );
}

if ( ! $same( $locked_config['text_color'], '#000000' ) || ! $same( $locked_config['background_color'], '#1B365D' ) || ! $same( $locked_config['border_color'], '#000000' ) || $same( $locked_config['border_color'], '#111111' ) ) {
	$fail( 'LOCK_CLOSED_COLOR_FAIL ' . wp_json_encode( $locked_config ) );
}

if ( empty( $plain_config['frame_plain'] ) || ! $same( $plain_config['text_color'], '#000000' ) || ! $same( $plain_config['background_color'], '#1B365D' ) || ! $same( $plain_config['border_color'], '#1B365D' ) ) {
	$fail( 'LOCK_PLAIN_COLOR_FAIL ' . wp_json_encode( $plain_config ) );
}

if ( ! $same( $shared_config['text_color'], $posted_red ) || ! $same( $shared_config['border_color'], $posted_red ) ) {
	$fail( 'LOCK_SHARED_OPEN_FAIL ' . wp_json_encode( $shared_config ) );
}

if ( ! $same( $shared_locked_config['text_color'], '#000000' ) || ! $same( $shared_locked_config['background_color'], '#FFFFFF' ) || ! $same( $shared_locked_config['border_color'], '#000000' ) ) {
	$fail( 'LOCK_SHARED_CLOSED_FAIL ' . wp_json_encode( $shared_locked_config ) );
}

if ( empty( $shared_plain_config['frame_plain'] ) || ! $same( $shared_plain_config['border_color'], '#FFFFFF' ) || ! $same( $shared_plain_config['text_color'], '#000000' ) ) {
	$fail( 'LOCK_SHARED_PLAIN_FAIL ' . wp_json_encode( $shared_plain_config ) );
}

if ( ! $same( $english_config['text_color'], '#000000' ) || ! $same( $english_config['background_color'], '#FFFFFF' ) || ! $same( $english_config['border_color'], '#000000' ) || ! $same( $english_config['slogan_color'], $posted_red ) || 'HELLO' !== $english_config['slogan'] ) {
	$fail( 'LOCK_ENGLISH_FAIL ' . wp_json_encode( $english_config ) );
}

$open_before_hide = APD_WooCommerce::offer_fingerprint( $open_id );
update_post_meta( $open_id, APD_Admin_Settings::META_HIDE_PLAIN_FRAME, 'yes' );
update_post_meta( $locked_id, APD_Admin_Settings::META_HIDE_PLAIN_FRAME, 'yes' );

if ( array_key_exists( 'hide_plain_frame', $open_catalog ) || empty( APD_WooCommerce::product_offer_catalog( $open_id )['hide_plain_frame'] ) || APD_WooCommerce::offer_fingerprint( $open_id ) === $open_before_hide ) {
	$fail( 'HIDE_PLAIN_FINGERPRINT_FAIL' );
}

$hidden_open = $post_config( $open_id, true );
$hidden_locked = $post_config( $locked_id, true );
$hidden_legacy = $post_config( $open_id, false, array( 'apd_legacy_no_frame' => '1' ) );

if ( ! is_array( $hidden_open ) || false !== $hidden_open['frame_plain'] || ! $same( $hidden_open['border_color'], $posted_red ) || ! empty( $hidden_open['no_frame'] ) || 8 !== (int) $hidden_open['format_border_width'] ) {
	$fail( 'HIDE_PLAIN_OPEN_FAIL ' . wp_json_encode( $hidden_open ) );
}

if ( ! is_array( $hidden_locked ) || false !== $hidden_locked['frame_plain'] || ! $same( $hidden_locked['border_color'], '#000000' ) || ! $same( $hidden_locked['background_color'], '#1B365D' ) ) {
	$fail( 'HIDE_PLAIN_LOCKED_FAIL ' . wp_json_encode( $hidden_locked ) );
}

if ( ! is_array( $hidden_legacy ) || ! empty( $hidden_legacy['no_frame'] ) || 8 !== (int) $hidden_legacy['format_border_width'] ) {
	$fail( 'HIDE_PLAIN_LEGACY_FAIL ' . wp_json_encode( $hidden_legacy ) );
}

delete_post_meta( $open_id, APD_Admin_Settings::META_HIDE_PLAIN_FRAME );

if ( APD_WooCommerce::offer_fingerprint( $open_id ) !== $open_before_hide || array_key_exists( 'hide_plain_frame', APD_WooCommerce::product_offer_catalog( $open_id ) ) ) {
	$fail( 'HIDE_PLAIN_FINGERPRINT_RESTORE_FAIL' );
}

$cleanup();

echo 'LOCK_COLORS_OK' . PHP_EOL;
echo 'ALL_OK' . PHP_EOL;
