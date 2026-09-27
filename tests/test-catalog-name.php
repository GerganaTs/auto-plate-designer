<?php
/**
 * A renamed WooCommerce catalog category keeps its name in the plugin.
 *
 * Run from the plugin root:
 *
 *     php tests/test-catalog-name.php
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

if ( ! class_exists( 'APD_Catalog' ) ) {
	fwrite( STDERR, "APD_Catalog was not loaded. Is the plugin active?\n" );
	exit( 1 );
}

APD_Catalog::instance()->seed_terms();

$term = get_term_by( 'slug', 'plates-eu', 'product_cat' );

if ( ! $term instanceof WP_Term ) {
	fwrite( STDERR, "TERM_MISSING\n" );
	exit( 1 );
}

$original_name = $term->name;
$settings      = APD_Plugin::get_settings();
$original_names = isset( $settings['catalog']['names'] ) && is_array( $settings['catalog']['names'] )
	? $settings['catalog']['names']
	: array();
$renamed       = 'Европейски номера';
$failed        = '';

$updated = wp_update_term(
	$term->term_id,
	'product_cat',
	array(
		'name' => $renamed,
	)
);

if ( is_wp_error( $updated ) ) {
	fwrite( STDERR, 'RENAME_FAIL ' . $updated->get_error_message() . "\n" );
	exit( 1 );
}

APD_Catalog::instance()->seed_terms();

$after = get_term_by( 'slug', 'plates-eu', 'product_cat' );
$defs  = APD_Catalog::definitions();

if ( ! $after instanceof WP_Term || $renamed !== $after->name ) {
	$failed = 'SEED_REVERTED ' . ( $after instanceof WP_Term ? $after->name : 'missing' );
} elseif ( ! isset( $defs['plates-eu']['name'] ) || $renamed !== $defs['plates-eu']['name'] ) {
	$failed = 'PLUGIN_NAME_MISS ' . ( isset( $defs['plates-eu']['name'] ) ? $defs['plates-eu']['name'] : '' );
}

wp_update_term(
	$term->term_id,
	'product_cat',
	array(
		'name' => $original_name,
	)
);

$settings = APD_Plugin::get_settings();

if ( empty( $original_names ) ) {
	unset( $settings['catalog']['names'] );
} else {
	$settings['catalog']['names'] = $original_names;
}

APD_Plugin::save_settings( $settings );

if ( '' !== $failed ) {
	fwrite( STDERR, $failed . "\n" );
	exit( 1 );
}

echo 'CATALOG_NAME_OK' . PHP_EOL;
