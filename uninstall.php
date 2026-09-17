<?php
/**
 * Uninstall handler. Runs only when the plugin is deleted from wp-admin.
 *
 * Removes plugin options and transients. Order item meta and preview
 * attachments are left in place so historical orders stay intact.
 *
 * @package Auto_Plate_Designer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'apd_settings' );
delete_transient( 'apd_settings_cache' );
delete_transient( 'apd_settings_cache_v12' );
delete_transient( 'apd_settings_cache_v13' );

if ( function_exists( 'wp_cache_delete' ) ) {
	wp_cache_delete( 'apd_settings_cache', 'transient' );
	wp_cache_delete( 'apd_settings_cache_v12', 'transient' );
	wp_cache_delete( 'apd_settings_cache_v13', 'transient' );
}
