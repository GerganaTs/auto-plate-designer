<?php
/**
 * Nonce-protected AJAX / REST endpoints.
 *
 * Implemented in a later stage.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend and admin AJAX handlers.
 */
final class APD_Ajax {

	/**
	 * Singleton instance.
	 *
	 * @var APD_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Ajax
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reserved for AJAX route registration in a later stage.
	 */
	private function __construct() {}
}
