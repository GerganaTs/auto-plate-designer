<?php
/**
 * Master color library plus named palettes (text / border / plate fill).
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Color library and named palettes stored in plugin settings.
 */
final class APD_Color_Palettes {

	/**
	 * Stable IDs for palettes migrated from the old three lists.
	 *
	 * @var array<string, string>
	 */
	const DEFAULT_PALETTE_IDS = array(
		'text'       => 'apd-palette-text',
		'border'     => 'apd-palette-border',
		'background' => 'apd-palette-background',
	);

	/**
	 * Singleton instance.
	 *
	 * @var APD_Color_Palettes|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Color_Palettes
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
	 * Purpose slugs.
	 *
	 * @return array<int, string>
	 */
	public static function purposes() {
		return array( 'text', 'border', 'background' );
	}

	/**
	 * Human-readable purpose label.
	 *
	 * @param string $purpose text|border|background.
	 * @return string
	 */
	public static function purpose_label( $purpose ) {
		$labels = array(
			'text'       => __( 'Text', 'auto-plate-designer' ),
			'border'     => __( 'Border', 'auto-plate-designer' ),
			'background' => __( 'Plate fill', 'auto-plate-designer' ),
		);

		return isset( $labels[ $purpose ] ) ? $labels[ $purpose ] : $purpose;
	}

	/**
	 * Shop swatch size, shape, and border.
	 *
	 * @return array{size: int, shape: string, radius: int, border_color: string}
	 */
	public static function swatch_display() {
		$settings = APD_Plugin::get_settings();

		return self::sanitize_swatch_display(
			isset( $settings['swatch_display'] ) && is_array( $settings['swatch_display'] )
				? $settings['swatch_display']
				: array()
		);
	}

	/**
	 * Clamp shop swatch display settings.
	 *
	 * @param mixed $raw Raw values.
	 * @return array{size: int, shape: string, radius: int, border_color: string}
	 */
	public static function sanitize_swatch_display( $raw ) {
		$raw          = is_array( $raw ) ? $raw : array();
		$size         = isset( $raw['size'] ) ? absint( $raw['size'] ) : 28;
		$shape        = isset( $raw['shape'] ) ? sanitize_key( (string) $raw['shape'] ) : 'circle';
		$radius       = isset( $raw['radius'] ) ? absint( $raw['radius'] ) : 4;
		$border_color = isset( $raw['border_color'] ) ? APD_Security::sanitize_hex_color( $raw['border_color'] ) : '';

		if ( $size < 10 ) {
			$size = 10;
		}

		if ( $size > 100 ) {
			$size = 100;
		}

		if ( ! in_array( $shape, array( 'circle', 'square', 'rounded' ), true ) ) {
			$shape = 'circle';
		}

		if ( $radius > 50 ) {
			$radius = 50;
		}

		if ( '' === $border_color ) {
			$border_color = '#000000';
		}

		return array(
			'size'         => $size,
			'shape'        => $shape,
			'radius'       => $radius,
			'border_color' => $border_color,
		);
	}

	/**
	 * Persist shop swatch size and shape.
	 *
	 * @param array<string, mixed> $raw Posted values.
	 * @return true
	 */
	public static function save_swatch_display( $raw ) {
		$raw     = is_array( $raw ) ? $raw : array();
		$current = self::swatch_display();
		$next    = self::sanitize_swatch_display( $raw );

		if ( 'rounded' !== $next['shape'] ) {
			$next['radius'] = $current['radius'];
		}

		if ( ! array_key_exists( 'border_color', $raw ) ) {
			$next['border_color'] = $current['border_color'];
		}

		$settings                    = APD_Plugin::get_settings();
		$settings['swatch_display']  = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * CSS border-radius for shop swatches.
	 *
	 * @param array{size?: int, shape?: string, radius?: int, border_color?: string}|null $display Display settings.
	 * @return string
	 */
	public static function swatch_radius_css( $display = null ) {
		$display = is_array( $display ) ? self::sanitize_swatch_display( $display ) : self::swatch_display();

		if ( 'square' === $display['shape'] ) {
			return '0';
		}

		if ( 'rounded' === $display['shape'] ) {
			return (string) (int) $display['radius'] . 'px';
		}

		return '50%';
	}

	/**
	 * CSS border color for shop swatches.
	 *
	 * @param array{size?: int, shape?: string, radius?: int, border_color?: string}|null $display Display settings.
	 * @return string
	 */
	public static function swatch_border_css( $display = null ) {
		$display = is_array( $display ) ? self::sanitize_swatch_display( $display ) : self::swatch_display();

		return $display['border_color'];
	}

	/**
	 * Master color library.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function library() {
		$settings = APD_Plugin::get_settings();

		return isset( $settings['color_library'] ) && is_array( $settings['color_library'] )
			? $settings['color_library']
			: array();
	}

	/**
	 * One library color.
	 *
	 * @param string $id Color ID.
	 * @return array<string, string>|null
	 */
	public static function get_color( $id ) {
		foreach ( self::library() as $color ) {
			if ( isset( $color['id'] ) && $color['id'] === $id ) {
				return $color;
			}
		}

		return null;
	}

	/**
	 * Named palettes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$settings = APD_Plugin::get_settings();

		return isset( $settings['color_palettes'] ) && is_array( $settings['color_palettes'] )
			? $settings['color_palettes']
			: array();
	}

	/**
	 * One named palette.
	 *
	 * @param string $id Palette ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $palette ) {
			if ( isset( $palette['id'] ) && $palette['id'] === $id ) {
				return $palette;
			}
		}

		return null;
	}

	/**
	 * Active palettes optionally filtered by purpose.
	 *
	 * @param string $purpose Optional purpose slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function active( $purpose = '' ) {
		$out = array();

		foreach ( self::all() as $palette ) {
			if ( empty( $palette['active'] ) ) {
				continue;
			}

			if ( '' !== $purpose && ( ! isset( $palette['purpose'] ) || $palette['purpose'] !== $purpose ) ) {
				continue;
			}

			$out[] = $palette;
		}

		return $out;
	}

	/**
	 * Resolved colors for one named palette.
	 *
	 * @param array<string, mixed> $palette Palette row.
	 * @return array<int, array<string, string>>
	 */
	public static function palette_colors( $palette ) {
		$out = array();
		$ids = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

		foreach ( $ids as $id ) {
			$color = self::get_color( (string) $id );

			if ( is_array( $color ) ) {
				$out[] = $color;
			}
		}

		return $out;
	}

	/**
	 * Colors offered by selected palettes for one purpose.
	 *
	 * @param array<int, string> $palette_ids Product palette IDs.
	 * @param string             $purpose     text|border|background.
	 * @return array<int, array<string, string>>
	 */
	public static function colors_for_product( $palette_ids, $purpose ) {
		$out  = array();
		$seen = array();

		if ( ! is_array( $palette_ids ) ) {
			return $out;
		}

		foreach ( $palette_ids as $id ) {
			$palette = self::get( (string) $id );

			if ( ! is_array( $palette ) || empty( $palette['active'] ) ) {
				continue;
			}

			if ( ! isset( $palette['purpose'] ) || $palette['purpose'] !== $purpose ) {
				continue;
			}

			foreach ( self::palette_colors( $palette ) as $color ) {
				$hex = strtoupper( $color['hex'] );

				if ( isset( $seen[ $hex ] ) ) {
					continue;
				}

				$seen[ $hex ] = true;
				$out[]        = $color;
			}
		}

		return $out;
	}

	/**
	 * All library colors used by active palettes of a purpose (legacy fallback).
	 *
	 * @param string $purpose text|border|background.
	 * @return array<int, array<string, string>>
	 */
	public static function colors_for_purpose( $purpose ) {
		$ids = array();

		foreach ( self::active( $purpose ) as $palette ) {
			$ids[] = $palette['id'];
		}

		return self::colors_for_product( $ids, $purpose );
	}

	/**
	 * Insert or replace a named palette. Optional new color is added to the library first.
	 *
	 * @param array<string, mixed> $raw Raw POST-like array.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save( $raw ) {
		$settings = APD_Plugin::get_settings();

		if ( ! empty( $raw['hex'] ) ) {
			$added = self::add_library_color( $settings, (string) $raw['hex'], isset( $raw['label'] ) ? (string) $raw['label'] : '' );

			if ( is_wp_error( $added ) ) {
				return $added;
			}

			if ( ! isset( $raw['color_ids'] ) || ! is_array( $raw['color_ids'] ) ) {
				$raw['color_ids'] = array();
			}

			$raw['color_ids'][] = $added;
			$settings           = APD_Plugin::get_settings();
		}

		$palette = self::sanitize( $raw, $settings );

		if ( is_wp_error( $palette ) ) {
			return $palette;
		}

		$found = false;

		if ( ! isset( $settings['color_palettes'] ) || ! is_array( $settings['color_palettes'] ) ) {
			$settings['color_palettes'] = array();
		}

		foreach ( $settings['color_palettes'] as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $palette['id'] ) {
				$settings['color_palettes'][ $index ] = $palette;
				$found                                = true;
				break;
			}
		}

		if ( ! $found ) {
			$settings['color_palettes'][] = $palette;
		}

		APD_Plugin::save_settings( $settings );

		return $palette;
	}

	/**
	 * Delete a named palette. Library colors stay.
	 *
	 * @param string $id Palette ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return new WP_Error(
				'apd_color_palette_id',
				__( 'Invalid palette.', 'auto-plate-designer' )
			);
		}

		$settings = APD_Plugin::get_settings();
		$next     = array();
		$found    = false;

		foreach ( self::all() as $palette ) {
			if ( isset( $palette['id'] ) && $palette['id'] === $id ) {
				$found = true;
				continue;
			}

			$next[] = $palette;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_color_palette_missing',
				__( 'Palette was not found.', 'auto-plate-designer' )
			);
		}

		$settings['color_palettes'] = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Detach a library color from every palette and delete it.
	 *
	 * @param string $id Color ID.
	 * @return true|WP_Error
	 */
	public static function delete_color( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return new WP_Error(
				'apd_color_id',
				__( 'Invalid color.', 'auto-plate-designer' )
			);
		}

		$settings = APD_Plugin::get_settings();
		$library  = array();
		$found    = false;

		foreach ( self::library() as $color ) {
			if ( isset( $color['id'] ) && $color['id'] === $id ) {
				$found = true;
				continue;
			}

			$library[] = $color;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_color_missing',
				__( 'Color was not found.', 'auto-plate-designer' )
			);
		}

		$palettes = array();

		foreach ( self::all() as $palette ) {
			$ids = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();
			$palette['color_ids'] = array_values(
				array_filter(
					$ids,
					static function ( $color_id ) use ( $id ) {
						return (string) $color_id !== $id;
					}
				)
			);
			$palettes[] = $palette;
		}

		$settings['color_library']  = $library;
		$settings['color_palettes'] = $palettes;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Add a unique color to the library. Returns existing ID on hex/label match.
	 *
	 * @param array<string, mixed> $settings Settings payload (unused after reload).
	 * @param string               $hex      Hex color.
	 * @param string               $label    Label.
	 * @return string|WP_Error Color ID.
	 */
	public static function add_library_color( $settings, $hex, $label ) {
		unset( $settings );

		$hex_check = APD_Security::validate_hex_color( $hex );

		if ( is_wp_error( $hex_check ) ) {
			return $hex_check;
		}

		$label_check = APD_Security::validate_admin_label( $label, 40 );

		if ( is_wp_error( $label_check ) ) {
			return $label_check;
		}

		$hex   = APD_Security::sanitize_hex_color( $hex );
		$label = APD_Security::sanitize_admin_label( $label );
		$store = APD_Plugin::get_settings();

		if ( ! isset( $store['color_library'] ) || ! is_array( $store['color_library'] ) ) {
			$store['color_library'] = array();
		}

		foreach ( $store['color_library'] as $color ) {
			if ( 0 === strcasecmp( isset( $color['hex'] ) ? (string) $color['hex'] : '', $hex ) ) {
				return isset( $color['id'] ) ? (string) $color['id'] : '';
			}

			if ( APD_Plugin::palette_list_has_entry( array( $color ), '#NOMATCH', $label ) ) {
				return new WP_Error(
					'apd_palette_duplicate',
					__( 'This color or label already exists.', 'auto-plate-designer' )
				);
			}
		}

		$id = APD_Plugin::new_id();

		$store['color_library'][] = array(
			'id'    => $id,
			'hex'   => $hex,
			'label' => $label,
		);

		APD_Plugin::save_settings( $store );

		return $id;
	}

	/**
	 * Insert or update a library color. Duplicate hex/label against other rows is rejected.
	 *
	 * @param string $hex   Hex color.
	 * @param string $label Label.
	 * @param string $id    Existing color ID when editing.
	 * @return string|WP_Error Color ID.
	 */
	public static function save_library_color( $hex, $label, $id = '' ) {
		$hex_check = APD_Security::validate_hex_color( $hex );

		if ( is_wp_error( $hex_check ) ) {
			return $hex_check;
		}

		$label_check = APD_Security::validate_admin_label( $label, 40 );

		if ( is_wp_error( $label_check ) ) {
			return $label_check;
		}

		$hex   = APD_Security::sanitize_hex_color( $hex );
		$label = APD_Security::sanitize_admin_label( $label );
		$id    = sanitize_text_field( (string) $id );
		$store = APD_Plugin::get_settings();

		if ( ! isset( $store['color_library'] ) || ! is_array( $store['color_library'] ) ) {
			$store['color_library'] = array();
		}

		$found = null;

		foreach ( $store['color_library'] as $index => $color ) {
			$color_id = isset( $color['id'] ) ? (string) $color['id'] : '';

			if ( '' !== $id && $color_id === $id ) {
				$found = $index;
				continue;
			}

			if ( 0 === strcasecmp( isset( $color['hex'] ) ? (string) $color['hex'] : '', $hex ) ) {
				return new WP_Error(
					'apd_palette_duplicate',
					__( 'This color already exists. Use Edit to change its label.', 'auto-plate-designer' )
				);
			}

			if ( APD_Plugin::palette_list_has_entry( array( $color ), '#NOMATCH', $label ) ) {
				return new WP_Error(
					'apd_palette_duplicate',
					__( 'This label is already used by another color.', 'auto-plate-designer' )
				);
			}
		}

		if ( '' !== $id ) {
			if ( null === $found ) {
				return new WP_Error(
					'apd_color_missing',
					__( 'Color was not found.', 'auto-plate-designer' )
				);
			}

			$store['color_library'][ $found ]['hex']   = $hex;
			$store['color_library'][ $found ]['label'] = $label;
			APD_Plugin::save_settings( $store );

			return $id;
		}

		$id = APD_Plugin::new_id();

		$store['color_library'][] = array(
			'id'    => $id,
			'hex'   => $hex,
			'label' => $label,
		);

		APD_Plugin::save_settings( $store );

		return $id;
	}

	/**
	 * Append a library color to named palettes.
	 *
	 * @param string             $color_id    Library color ID.
	 * @param array<int, string> $palette_ids Palette IDs.
	 * @return true|WP_Error
	 */
	public static function attach_color_to_palettes( $color_id, $palette_ids ) {
		$color_id = sanitize_text_field( (string) $color_id );

		if ( '' === $color_id || null === self::get_color( $color_id ) ) {
			return new WP_Error(
				'apd_color_missing',
				__( 'Color was not found.', 'auto-plate-designer' )
			);
		}

		$want = array();

		if ( is_array( $palette_ids ) ) {
			foreach ( $palette_ids as $palette_id ) {
				$palette_id = sanitize_text_field( (string) $palette_id );

				if ( '' !== $palette_id ) {
					$want[ $palette_id ] = true;
				}
			}
		}

		if ( empty( $want ) ) {
			return true;
		}

		$settings = APD_Plugin::get_settings();
		$next     = array();

		foreach ( self::all() as $palette ) {
			$id = isset( $palette['id'] ) ? (string) $palette['id'] : '';

			if ( isset( $want[ $id ] ) ) {
				$ids = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

				if ( ! in_array( $color_id, $ids, true ) ) {
					$ids[] = $color_id;
				}

				$palette['color_ids'] = array_values( $ids );
			}

			$next[] = $palette;
		}

		$settings['color_palettes'] = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * First matching hex in a color list, or the first row.
	 *
	 * @param array<int, array<string, string>> $colors    Color rows.
	 * @param array<int, string>                $preferred Preferred hex values in order.
	 * @return string
	 */
	public static function preferred_hex( $colors, $preferred ) {
		$want = array_values( array_filter( (array) $preferred ) );

		foreach ( $want as $hex ) {
			foreach ( $colors as $color ) {
				if ( isset( $color['hex'] ) && 0 === strcasecmp( (string) $color['hex'], (string) $hex ) ) {
					return $color['hex'];
				}
			}
		}

		if ( ! empty( $colors ) && isset( $colors[0]['hex'] ) ) {
			return (string) $colors[0]['hex'];
		}

		return isset( $want[0] ) ? (string) $want[0] : '';
	}

	/**
	 * Validate a named palette row.
	 *
	 * @param array<string, mixed> $raw      Raw values.
	 * @param array<string, mixed> $settings Settings with library.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize( $raw, $settings = null ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'apd_color_palette_type',
				__( 'Invalid palette payload.', 'auto-plate-designer' )
			);
		}

		if ( ! is_array( $settings ) ) {
			$settings = APD_Plugin::get_settings();
		}

		$id = isset( $raw['id'] ) ? sanitize_text_field( (string) $raw['id'] ) : '';

		if ( '' === $id ) {
			$id = APD_Plugin::new_id();
		}

		$name_check = APD_Security::validate_admin_label( isset( $raw['name'] ) ? $raw['name'] : '', 80 );

		if ( is_wp_error( $name_check ) ) {
			return $name_check;
		}

		$purpose = isset( $raw['purpose'] ) ? sanitize_key( (string) $raw['purpose'] ) : '';

		if ( ! in_array( $purpose, self::purposes(), true ) ) {
			return new WP_Error(
				'apd_color_palette_purpose',
				__( 'Choose whether this palette is for text, border, or plate fill.', 'auto-plate-designer' )
			);
		}

		$known = array();

		if ( isset( $settings['color_library'] ) && is_array( $settings['color_library'] ) ) {
			foreach ( $settings['color_library'] as $color ) {
				if ( isset( $color['id'] ) ) {
					$known[ (string) $color['id'] ] = true;
				}
			}
		}

		$color_ids = array();
		$posted    = isset( $raw['color_ids'] ) ? (array) $raw['color_ids'] : array();

		foreach ( $posted as $color_id ) {
			$color_id = sanitize_text_field( (string) $color_id );

			if ( '' !== $color_id && isset( $known[ $color_id ] ) ) {
				$color_ids[] = $color_id;
			}
		}

		$color_ids = array_values( array_unique( $color_ids ) );

		if ( empty( $color_ids ) ) {
			return new WP_Error(
				'apd_color_palette_colors',
				__( 'Add at least one color to this palette.', 'auto-plate-designer' )
			);
		}

		return array(
			'id'        => $id,
			'name'      => APD_Security::sanitize_admin_label( (string) $raw['name'] ),
			'purpose'   => $purpose,
			'color_ids' => $color_ids,
			'active'    => ! empty( $raw['active'] ),
		);
	}

	/**
	 * Keep only palette IDs that exist, are active, and the format can paint.
	 *
	 * @param mixed  $raw  Posted or stored IDs.
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function sanitize_product_ids( $raw, $type ) {
		$allowed = APD_Formats::color_field_capabilities( $type );
		$clean   = array();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		foreach ( $raw as $id ) {
			$id      = sanitize_text_field( (string) $id );
			$palette = '' !== $id ? self::get( $id ) : null;

			if ( ! is_array( $palette ) || empty( $palette['active'] ) ) {
				continue;
			}

			$purpose = isset( $palette['purpose'] ) ? (string) $palette['purpose'] : '';
			$ids     = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

			if ( in_array( $purpose, $allowed, true ) && ! empty( $ids ) ) {
				$clean[] = $id;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Default named palettes and library for a fresh install.
	 *
	 * @return array{library: array<int, array<string, string>>, palettes: array<int, array<string, mixed>>}
	 */
	public static function starter() {
		$library = array(
			array(
				'id'    => 'apd-color-black',
				'hex'   => '#000000',
				'label' => 'Black',
			),
			array(
				'id'    => 'apd-color-white',
				'hex'   => '#FFFFFF',
				'label' => 'White',
			),
			array(
				'id'    => 'apd-color-navy',
				'hex'   => '#1B365D',
				'label' => 'Navy',
			),
			array(
				'id'    => 'apd-color-red',
				'hex'   => '#C41E3A',
				'label' => 'Red',
			),
		);

		$all_ids = array( 'apd-color-black', 'apd-color-white', 'apd-color-navy', 'apd-color-red' );
		$fill_ids = array( 'apd-color-white', 'apd-color-black', 'apd-color-navy', 'apd-color-red' );

		$palettes = array(
			array(
				'id'        => self::DEFAULT_PALETTE_IDS['text'],
				'name'      => 'Text colors',
				'purpose'   => 'text',
				'color_ids' => $all_ids,
				'active'    => true,
			),
			array(
				'id'        => self::DEFAULT_PALETTE_IDS['border'],
				'name'      => 'Border colors',
				'purpose'   => 'border',
				'color_ids' => $all_ids,
				'active'    => true,
			),
			array(
				'id'        => self::DEFAULT_PALETTE_IDS['background'],
				'name'      => 'Plate / strip colors',
				'purpose'   => 'background',
				'color_ids' => $fill_ids,
				'active'    => true,
			),
		);

		return array(
			'library'  => $library,
			'palettes' => $palettes,
		);
	}

	/**
	 * Normalize stored color data, migrating the old three lists once.
	 *
	 * @param array<string, mixed>                              $stored       Full stored settings.
	 * @param array<string, array<int, array<string, string>>> $legacy_lists Old purpose lists.
	 * @return array{library: array<int, array<string, string>>, palettes: array<int, array<string, mixed>>, lists: array<string, array<int, array<string, string>>>}
	 */
	public static function normalize_stored( $stored, $legacy_lists = array() ) {
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( isset( $stored['color_library'] ) && is_array( $stored['color_library'] ) ) {
			$library = APD_Plugin::normalize_palette_list( $stored['color_library'] );
			$named   = isset( $stored['color_palettes'] ) && is_array( $stored['color_palettes'] )
				? $stored['color_palettes']
				: array();
			$palettes = self::normalize_named_list( $named, $library );

			return array(
				'library'  => $library,
				'palettes' => $palettes,
				'lists'    => self::synthesize_purpose_lists( $library, $palettes ),
			);
		}

		$legacy   = is_array( $legacy_lists ) ? $legacy_lists : array();
		$migrated = self::migrate_purpose_lists( $legacy );

		return array(
			'library'  => $migrated['library'],
			'palettes' => $migrated['palettes'],
			'lists'    => self::synthesize_purpose_lists( $migrated['library'], $migrated['palettes'] ),
		);
	}

	/**
	 * Turn old text/border/background lists into a library and three palettes.
	 *
	 * @param array<string, array<int, array<string, string>>> $lists Purpose lists.
	 * @return array{library: array<int, array<string, string>>, palettes: array<int, array<string, mixed>>}
	 */
	public static function migrate_purpose_lists( $lists ) {
		$library = array();
		$by_hex  = array();

		foreach ( APD_Plugin::palette_sets() as $purpose ) {
			$rows = isset( $lists[ $purpose ] ) && is_array( $lists[ $purpose ] ) ? $lists[ $purpose ] : array();

			foreach ( $rows as $color ) {
				$hex = isset( $color['hex'] ) ? strtoupper( (string) $color['hex'] ) : '';

				if ( '' === $hex ) {
					continue;
				}

				if ( isset( $by_hex[ $hex ] ) ) {
					continue;
				}

				$id = isset( $color['id'] ) && '' !== (string) $color['id'] ? (string) $color['id'] : APD_Plugin::new_id();
				$row = array(
					'id'    => $id,
					'hex'   => isset( $color['hex'] ) ? (string) $color['hex'] : '',
					'label' => isset( $color['label'] ) ? (string) $color['label'] : $hex,
				);
				$library[]     = $row;
				$by_hex[ $hex ] = $id;
			}
		}

		if ( empty( $library ) ) {
			return self::starter();
		}

		$palettes = array();
		$names    = array(
			'text'       => 'Text colors',
			'border'     => 'Border colors',
			'background' => 'Plate / strip colors',
		);

		foreach ( APD_Plugin::palette_sets() as $purpose ) {
			$ids  = array();
			$rows = isset( $lists[ $purpose ] ) && is_array( $lists[ $purpose ] ) ? $lists[ $purpose ] : array();

			foreach ( $rows as $color ) {
				$hex = isset( $color['hex'] ) ? strtoupper( (string) $color['hex'] ) : '';

				if ( isset( $by_hex[ $hex ] ) ) {
					$ids[] = $by_hex[ $hex ];
				}
			}

			$ids = array_values( array_unique( $ids ) );

			if ( empty( $ids ) ) {
				continue;
			}

			$palettes[] = array(
				'id'        => self::DEFAULT_PALETTE_IDS[ $purpose ],
				'name'      => $names[ $purpose ],
				'purpose'   => $purpose,
				'color_ids' => $ids,
				'active'    => true,
			);
		}

		if ( empty( $palettes ) ) {
			return self::starter();
		}

		return array(
			'library'  => $library,
			'palettes' => $palettes,
		);
	}

	/**
	 * Purpose lists for leftover callers of palettes.text etc.
	 *
	 * @param array<int, array<string, string>> $library  Library.
	 * @param array<int, array<string, mixed>>  $palettes Named palettes.
	 * @return array<string, array<int, array<string, string>>>
	 */
	private static function synthesize_purpose_lists( $library, $palettes ) {
		$index = array();

		foreach ( $library as $color ) {
			if ( isset( $color['id'] ) ) {
				$index[ $color['id'] ] = $color;
			}
		}

		$out = array(
			'text'       => array(),
			'border'     => array(),
			'background' => array(),
		);

		foreach ( $palettes as $palette ) {
			$purpose = isset( $palette['purpose'] ) ? (string) $palette['purpose'] : '';

			if ( ! isset( $out[ $purpose ] ) ) {
				continue;
			}

			$ids = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

			foreach ( $ids as $id ) {
				if ( isset( $index[ $id ] ) ) {
					$out[ $purpose ][] = $index[ $id ];
				}
			}

			$out[ $purpose ] = APD_Plugin::normalize_palette_list( $out[ $purpose ] );
		}

		return $out;
	}

	/**
	 * Drop invalid named palettes.
	 *
	 * @param array<int, mixed>                 $named   Raw palettes.
	 * @param array<int, array<string, string>> $library Library.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_named_list( $named, $library ) {
		$known = array();

		foreach ( $library as $color ) {
			if ( isset( $color['id'] ) ) {
				$known[ (string) $color['id'] ] = true;
			}
		}

		$out = array();

		foreach ( $named as $palette ) {
			if ( ! is_array( $palette ) ) {
				continue;
			}

			$purpose = isset( $palette['purpose'] ) ? sanitize_key( (string) $palette['purpose'] ) : '';

			if ( ! in_array( $purpose, self::purposes(), true ) ) {
				continue;
			}

			$ids = array();
			$raw = isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

			foreach ( $raw as $id ) {
				$id = sanitize_text_field( (string) $id );

				if ( isset( $known[ $id ] ) ) {
					$ids[] = $id;
				}
			}

			$out[] = array(
				'id'        => isset( $palette['id'] ) && '' !== (string) $palette['id'] ? sanitize_text_field( (string) $palette['id'] ) : APD_Plugin::new_id(),
				'name'      => isset( $palette['name'] ) ? APD_Security::sanitize_admin_label( (string) $palette['name'] ) : '',
				'purpose'   => $purpose,
				'color_ids' => array_values( array_unique( $ids ) ),
				'active'    => ! empty( $palette['active'] ),
			);
		}

		return $out;
	}
}
