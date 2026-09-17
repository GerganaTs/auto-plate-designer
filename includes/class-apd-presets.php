<?php
/**
 * CRUD for country-band presets (flag image + metadata).
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Country / regional band presets.
 */
final class APD_Presets {

	/**
	 * Singleton instance.
	 *
	 * @var APD_Presets|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Presets
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * No runtime hooks. Data methods are static.
	 */
	private function __construct() {}

	/**
	 * All presets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$settings = APD_Plugin::get_settings();

		return isset( $settings['presets'] ) && is_array( $settings['presets'] ) ? $settings['presets'] : array();
	}

	/**
	 * Active presets optionally filtered by format type.
	 *
	 * @param string $format_type eu|us|custom.
	 * @return array<int, array<string, mixed>>
	 */
	public static function active_for_format( $format_type ) {
		$out = array();

		foreach ( self::all() as $preset ) {
			if ( empty( $preset['active'] ) ) {
				continue;
			}

			$allowed = isset( $preset['allowed_format_types'] ) && is_array( $preset['allowed_format_types'] )
				? $preset['allowed_format_types']
				: array( 'eu' );

			if ( ! APD_Formats::uses_country_band( $format_type ) ) {
				continue;
			}

			$matches = in_array( $format_type, $allowed, true );

			// Legacy rows stored only "eu" before motorcycle existed.
			if ( ! $matches && 'moto' === $format_type && array( 'eu' ) === array_values( $allowed ) ) {
				$matches = true;
			}

			if ( ! $matches ) {
				continue;
			}

			$out[] = $preset;
		}

		return $out;
	}

	/**
	 * One preset by ID.
	 *
	 * @param string $id Preset ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $preset ) {
			if ( isset( $preset['id'] ) && $preset['id'] === $id ) {
				return $preset;
			}
		}

		return null;
	}

	/**
	 * Insert or replace a sanitized preset.
	 *
	 * @param array<string, mixed> $raw Raw POST-like array.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save( $raw ) {
		$preset = self::sanitize( $raw );

		if ( is_wp_error( $preset ) ) {
			return $preset;
		}

		$settings = APD_Plugin::get_settings();
		$found    = false;

		foreach ( $settings['presets'] as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $preset['id'] ) {
				$settings['presets'][ $index ] = $preset;
				$found                         = true;
				break;
			}
		}

		if ( ! $found ) {
			$settings['presets'][] = $preset;
		}

		APD_Plugin::save_settings( $settings );

		return $preset;
	}

	/**
	 * Delete a preset by ID.
	 *
	 * @param string $id Preset ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return new WP_Error(
				'apd_preset_id',
				__( 'Invalid preset.', 'auto-plate-designer' )
			);
		}

		$settings = APD_Plugin::get_settings();
		$next     = array();
		$found    = false;

		foreach ( $settings['presets'] as $preset ) {
			if ( isset( $preset['id'] ) && $preset['id'] === $id ) {
				$found = true;
				continue;
			}

			$next[] = $preset;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_preset_missing',
				__( 'Preset was not found.', 'auto-plate-designer' )
			);
		}

		$settings['presets'] = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Validate and normalize a preset row.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'apd_preset_type',
				__( 'Invalid preset payload.', 'auto-plate-designer' )
			);
		}

		$id = isset( $raw['id'] ) ? sanitize_text_field( (string) $raw['id'] ) : '';

		if ( '' === $id ) {
			$id = APD_Plugin::new_id();
		}

		$name_check = APD_Security::validate_admin_label( isset( $raw['name'] ) ? $raw['name'] : '', 80 );

		if ( is_wp_error( $name_check ) ) {
			return $name_check;
		}

		$code = isset( $raw['country_code'] ) ? strtoupper( sanitize_text_field( (string) $raw['country_code'] ) ) : '';
		$code_check = APD_Security::validate_country_code( $code );

		if ( is_wp_error( $code_check ) ) {
			return $code_check;
		}

		$image_id    = isset( $raw['image_id'] ) ? absint( $raw['image_id'] ) : 0;
		$image_check = APD_Security::validate_attachment(
			$image_id,
			array(
				'mimes'     => APD_Security::RASTER_MIMES,
				'allow_svg' => true,
				'optional'  => false,
			)
		);

		if ( is_wp_error( $image_check ) ) {
			return $image_check;
		}

		$allowed_types = array();
		$posted_types  = isset( $raw['allowed_format_types'] ) ? (array) $raw['allowed_format_types'] : array();

		foreach ( $posted_types as $type ) {
			$type = sanitize_key( (string) $type );

			if ( APD_Formats::uses_country_band( $type ) ) {
				$allowed_types[] = $type;
			}
		}

		$allowed_types = array_values( array_unique( $allowed_types ) );

		if ( empty( $allowed_types ) ) {
			$allowed_types = array( 'eu' );
		}

		$band_ratio = isset( $raw['band_ratio'] ) && is_numeric( $raw['band_ratio'] ) ? (float) $raw['band_ratio'] : APD_Formats::eu_band_ratio( 520, 110 );

		if ( $band_ratio < 0.05 ) {
			$band_ratio = 0.05;
		}

		if ( $band_ratio > 0.4 ) {
			$band_ratio = 0.4;
		}

		return array(
			'id'                   => $id,
			'name'                 => APD_Security::sanitize_admin_label( (string) $raw['name'] ),
			'country_code'         => $code,
			'image_id'             => $image_id,
			'band_ratio'           => round( $band_ratio, 4 ),
			'side'                 => ( isset( $raw['side'] ) && 'right' === $raw['side'] ) ? 'right' : 'left',
			'allowed_format_types' => $allowed_types,
			'active'               => ! empty( $raw['active'] ),
		);
	}
}
