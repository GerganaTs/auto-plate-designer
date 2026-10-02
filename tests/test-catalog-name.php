<?php
/**
 * Shop categories stay in WooCommerce. The plugin does not create or assign them.
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

$plugin = file_get_contents( APD_PLUGIN_DIR . 'includes/class-apd-plugin.php' );
$admin  = file_get_contents( APD_PLUGIN_DIR . 'includes/class-apd-admin-settings.php' );

if (
	false === $plugin
	|| false === $admin
	|| class_exists( 'APD_Catalog' )
	|| file_exists( APD_PLUGIN_DIR . 'includes/class-apd-catalog.php' )
	|| file_exists( APD_PLUGIN_DIR . 'templates/admin/catalog-tab.php' )
	|| false !== strpos( $plugin, 'APD_Catalog' )
	|| false !== strpos( $admin, 'maybe_assign_product_term' )
	|| false !== strpos( $admin, 'save_catalog' )
	|| false !== strpos( $admin, "'catalog'" )
) {
	fwrite( STDERR, "CATALOG_STILL_LINKED\n" );
	exit( 1 );
}

$before = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
$count = is_array( $before ) ? count( $before ) : 0;

do_action( 'init' );

$after = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_array( $after ) || count( $after ) !== $count ) {
	fwrite( STDERR, "CATALOG_SEEDED\n" );
	exit( 1 );
}

echo 'CATALOG_DETACHED_OK' . PHP_EOL;
