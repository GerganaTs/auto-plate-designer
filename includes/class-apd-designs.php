<?php
/**
 * CRUD for US plate designs (state / graphic library).
 *
 * Designs are not formats. A design can apply to every USA format or only some.
 * Each shop product locks one design, so the catalog shows that graphic alone.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Full-plate graphics shoppers pick on US formats.
 */
final class APD_Designs {

	/**
	 * Singleton instance.
	 *
	 * @var APD_Designs|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Designs
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
	 * All designs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$settings = APD_Plugin::get_settings();

		return isset( $settings['designs'] ) && is_array( $settings['designs'] ) ? $settings['designs'] : array();
	}

	/**
	 * Active designs for a format row (or type slug).
	 *
	 * Empty allowed_format_ids means every current and future US format.
	 *
	 * @param array<string, mixed>|string $format Format row or type slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function active_for_format( $format ) {
		$type = is_array( $format ) ? ( isset( $format['type'] ) ? (string) $format['type'] : '' ) : (string) $format;
		$id   = is_array( $format ) && isset( $format['id'] ) ? (string) $format['id'] : '';

		if ( ! APD_Formats::uses_plate_designs( $type ) ) {
			return array();
		}

		$out = array();

		foreach ( self::all() as $design ) {
			if ( empty( $design['active'] ) ) {
				continue;
			}

			$allowed_types = isset( $design['allowed_format_types'] ) && is_array( $design['allowed_format_types'] )
				? $design['allowed_format_types']
				: array( 'us' );

			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$allowed_ids = isset( $design['allowed_format_ids'] ) && is_array( $design['allowed_format_ids'] )
				? $design['allowed_format_ids']
				: array();

			if ( ! empty( $allowed_ids ) && ( '' === $id || ! in_array( $id, $allowed_ids, true ) ) ) {
				continue;
			}

			$out[] = $design;
		}

		return $out;
	}

	/**
	 * USA formats this design may be sold on.
	 *
	 * An empty allowed list means every current USA format.
	 *
	 * @param array<string, mixed> $design Design row.
	 * @return array<int, array<string, mixed>>
	 */
	public static function formats_for( $design ) {
		$ids = isset( $design['allowed_format_ids'] ) && is_array( $design['allowed_format_ids'] )
			? $design['allowed_format_ids']
			: array();
		$out = array();

		foreach ( APD_Formats::of_type( 'us' ) as $format ) {
			if ( empty( $ids ) || in_array( $format['id'], $ids, true ) ) {
				$out[] = $format;
			}
		}

		return $out;
	}

	/**
	 * One design by ID.
	 *
	 * @param string $id Design ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $design ) {
			if ( isset( $design['id'] ) && $design['id'] === $id ) {
				return $design;
			}
		}

		return null;
	}

	/**
	 * Insert or replace a sanitized design.
	 *
	 * @param array<string, mixed> $raw Raw POST-like array.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save( $raw ) {
		$design = self::sanitize( $raw );

		if ( is_wp_error( $design ) ) {
			return $design;
		}

		$settings = APD_Plugin::get_settings();
		$found    = false;

		if ( ! isset( $settings['designs'] ) || ! is_array( $settings['designs'] ) ) {
			$settings['designs'] = array();
		}

		foreach ( $settings['designs'] as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $design['id'] ) {
				$settings['designs'][ $index ] = $design;
				$found                         = true;
				break;
			}
		}

		if ( ! $found ) {
			$settings['designs'][] = $design;
		}

		APD_Plugin::save_settings( $settings );

		return $design;
	}

	/**
	 * Delete a design by ID.
	 *
	 * @param string $id Design ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return new WP_Error(
				'apd_design_id',
				__( 'Invalid design.', 'auto-plate-designer' )
			);
		}

		$settings = APD_Plugin::get_settings();
		$next     = array();
		$found    = false;

		if ( ! isset( $settings['designs'] ) || ! is_array( $settings['designs'] ) ) {
			$settings['designs'] = array();
		}

		foreach ( $settings['designs'] as $design ) {
			if ( isset( $design['id'] ) && $design['id'] === $id ) {
				$found = true;
				continue;
			}

			$next[] = $design;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_design_missing',
				__( 'Design was not found.', 'auto-plate-designer' )
			);
		}

		$settings['designs'] = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Drop a deleted format ID from design restrictions.
	 *
	 * An emptied ID list means the design applies to all US formats again.
	 *
	 * @param string $format_id Format ID.
	 * @return void
	 */
	public static function detach_format( $format_id ) {
		if ( ! is_string( $format_id ) || '' === $format_id ) {
			return;
		}

		$settings = APD_Plugin::get_settings();

		if ( ! isset( $settings['designs'] ) || ! is_array( $settings['designs'] ) ) {
			return;
		}

		$changed = false;

		foreach ( $settings['designs'] as $index => $design ) {
			$ids = isset( $design['allowed_format_ids'] ) && is_array( $design['allowed_format_ids'] )
				? $design['allowed_format_ids']
				: array();

			if ( empty( $ids ) ) {
				continue;
			}

			$next = array();

			foreach ( $ids as $id ) {
				if ( (string) $id !== $format_id ) {
					$next[] = (string) $id;
				}
			}

			if ( $next !== $ids ) {
				$settings['designs'][ $index ]['allowed_format_ids'] = array_values( $next );
				$changed = true;
			}
		}

		if ( $changed ) {
			APD_Plugin::save_settings( $settings );
		}
	}

	/**
	 * Validate and normalize a design row.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'apd_design_type',
				__( 'Invalid design payload.', 'auto-plate-designer' )
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

		$code       = isset( $raw['code'] ) ? strtoupper( sanitize_text_field( (string) $raw['code'] ) ) : '';
		$code_check = APD_Security::validate_country_code( $code, 2 );

		if ( is_wp_error( $code_check ) ) {
			return new WP_Error(
				'apd_design_code',
				__( 'Design code must be 2 or 3 uppercase letters (for example AZ or CA).', 'auto-plate-designer' )
			);
		}

		$image_id    = isset( $raw['image_id'] ) ? absint( $raw['image_id'] ) : 0;
		$image_check = APD_Security::validate_attachment(
			$image_id,
			array(
				'mimes'     => APD_Security::RASTER_MIMES,
				'allow_svg' => false,
				'optional'  => false,
			)
		);

		if ( is_wp_error( $image_check ) ) {
			return $image_check;
		}

		$all_us = ! empty( $raw['all_us'] );
		$ids    = array();

		if ( ! $all_us ) {
			$posted = isset( $raw['allowed_format_ids'] ) ? (array) $raw['allowed_format_ids'] : array();

			foreach ( $posted as $format_id ) {
				$format_id = sanitize_text_field( (string) $format_id );
				$format    = '' !== $format_id ? APD_Formats::get( $format_id ) : null;

				if ( is_array( $format ) && 'us' === $format['type'] ) {
					$ids[] = $format_id;
				}
			}

			$ids = array_values( array_unique( $ids ) );

			if ( empty( $ids ) ) {
				return new WP_Error(
					'apd_design_formats',
					__( 'Choose at least one USA format, or apply this design to all USA formats.', 'auto-plate-designer' )
				);
			}

			$all_current = array();

			foreach ( APD_Formats::of_type( 'us' ) as $us_format ) {
				$all_current[] = $us_format['id'];
			}

			if ( $all_current && ! array_diff( $all_current, $ids ) ) {
				$ids = array();
			}
		}

		$text_box   = self::sanitize_text_box( isset( $raw['text_box'] ) ? $raw['text_box'] : array() );
		$split      = ! empty( $raw['split_text'] );
		$text_right = array();
		$side_left  = 0;
		$side_right = 0;

		if ( $split ) {
			$right_raw = isset( $raw['text_box_right'] ) && is_array( $raw['text_box_right'] ) ? $raw['text_box_right'] : array();
			$pair      = ( isset( $right_raw['x'] ) || isset( $right_raw['width'] ) )
				? APD_Formats::link_side_boxes( $text_box, $right_raw )
				: APD_Formats::seed_side_boxes( $text_box );
			$text_box   = $pair[0];
			$text_right = $pair[1];
			$sides      = APD_Formats::us_side_limits(
				array(
					'max_chars_left'  => isset( $raw['max_chars_left'] ) ? $raw['max_chars_left'] : 0,
					'max_chars_right' => isset( $raw['max_chars_right'] ) ? $raw['max_chars_right'] : 0,
				)
			);
			$side_left  = $sides[0];
			$side_right = $sides[1];
		}

		return array(
			'id'                   => $id,
			'name'                 => APD_Security::sanitize_admin_label( (string) $raw['name'] ),
			'code'                 => $code,
			'image_id'             => $image_id,
			'allowed_format_types' => array( 'us' ),
			'allowed_format_ids'   => $ids,
			'text_box'             => $text_box,
			'split_text'           => $split,
			'text_box_right'       => $text_right,
			'max_chars_left'       => $side_left,
			'max_chars_right'      => $side_right,
			'active'               => ! empty( $raw['active'] ),
		);
	}

	/**
	 * Fallback box matching the original canvas (82% × 42% centered at 50% / 55%).
	 *
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	public static function default_text_box() {
		return APD_Formats::default_text_box( 'us' );
	}

	/**
	 * Clamp a design text box. Values are percents of the plate canvas.
	 *
	 * @param mixed $raw Raw values.
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	public static function sanitize_text_box( $raw ) {
		return APD_Formats::sanitize_text_box( $raw, 'us' );
	}
}
