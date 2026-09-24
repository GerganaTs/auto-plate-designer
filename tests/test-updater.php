<?php
/**
 * GitHub tag selection for the wp-admin Update button.
 *
 * Run from the plugin root:
 *
 *     php tests/test-updater.php
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

if ( ! class_exists( 'APD_Updater' ) ) {
	fwrite( STDERR, "APD_Updater was not loaded. Is the plugin active?\n" );
	exit( 1 );
}

if ( '1.0.1' !== APD_Updater::version_from_tag( 'v1.0.1' ) || '1.0.1' !== APD_Updater::version_from_tag( '1.0.1' ) ) {
	fwrite( STDERR, "VERSION_PARSE_FAIL\n" );
	exit( 1 );
}

if ( '' !== APD_Updater::version_from_tag( 'not-a-version' ) || '' !== APD_Updater::version_from_tag( '' ) ) {
	fwrite( STDERR, "VERSION_REJECT_FAIL\n" );
	exit( 1 );
}

$best = APD_Updater::newest_from_tags(
	array(
		array( 'name' => 'v1.0.0' ),
		array( 'name' => 'v1.2.0' ),
		array( 'name' => 'v1.10.0' ),
		array( 'name' => 'not-a-version' ),
		'v1.10.0-beta',
	)
);

if ( ! is_array( $best ) || 'v1.10.0' !== $best['tag'] || '1.10.0' !== $best['version'] ) {
	fwrite( STDERR, 'NEWEST_FAIL ' . wp_json_encode( $best ) . "\n" );
	exit( 1 );
}

$package = APD_Updater::package_url( 'v1.0.1' );

if ( 'https://github.com/GerganaTs/auto-plate-designer/archive/refs/tags/v1.0.1.zip' !== $package ) {
	fwrite( STDERR, "PACKAGE_FAIL {$package}\n" );
	exit( 1 );
}

if ( ! has_filter( 'update_plugins_github.com', array( 'APD_Updater', 'github_update' ) ) ) {
	fwrite( STDERR, "HOOK_MISSING\n" );
	exit( 1 );
}

if ( 1 !== has_action( 'load-update-core.php', array( 'APD_Updater', 'maybe_force_check' ) ) ) {
	fwrite( STDERR, "FORCE_CHECK_HOOK_MISSING\n" );
	exit( 1 );
}

delete_transient( APD_Updater::CACHE_KEY );

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		unset( $args );

		if ( is_string( $url ) && false !== strpos( $url, '/repos/GerganaTs/auto-plate-designer/tags' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						array( 'name' => 'v1.0.0' ),
						array( 'name' => 'v9.9.9' ),
						array( 'name' => 'not-a-version' ),
					)
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		if ( is_string( $url ) && false !== strpos( $url, '/releases/tags/' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'tag_name'     => 'v9.9.9',
						'body'         => 'Bug fixes',
						'html_url'     => 'https://github.com/GerganaTs/auto-plate-designer/releases/tag/v9.9.9',
						'published_at' => '2026-09-24T00:00:00Z',
					)
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		return $pre;
	},
	10,
	3
);

$update = APD_Updater::github_update( false, array( 'Version' => APD_VERSION ), APD_PLUGIN_BASENAME, array() );
$other  = APD_Updater::github_update( false, array( 'Version' => '1.0.0' ), 'other/other.php', array() );

delete_transient( APD_Updater::CACHE_KEY );

if ( false !== $other ) {
	fwrite( STDERR, "OTHER_PLUGIN_FAIL\n" );
	exit( 1 );
}

if ( ! is_array( $update ) || '9.9.9' !== $update['version'] || false === strpos( $update['package'], 'v9.9.9.zip' ) ) {
	fwrite( STDERR, 'UPDATE_FAIL ' . wp_json_encode( $update ) . "\n" );
	exit( 1 );
}

echo 'UPDATER_OK' . PHP_EOL;
