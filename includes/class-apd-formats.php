<?php
/**
 * CRUD for plate formats.
 *
 * Type slugs are the source of truth for how a plate is drawn. Do not add a
 * "show preset" checkbox on the format row — that would let a saved product
 * change shop behaviour after the fact. Pair each size with two types when a
 * country band is optional:
 *
 * - eu          painted car plate with euroband (520×110, BDS 15980)
 * - eu_plain    car plate without preset, same studio as a color plate (520×110)
 * - moto            EU motorcycle plate with euroband (199×154)
 * - moto_240        EU motorcycle plate with euroband (240×130)
 * - moto_plain      motorcycle plate without country band (199×154)
 * - moto_plain_240  motorcycle plate without country band (240×130)
 * - suv_eu      two-line / SUV plate with euroband (280×200, BDS 15980)
 * - suv         two-line / SUV plate without preset (painted, 280×200, BDS 15980)
 * - us          state graphics
 * - custom      street / painted, no band, size is typed in millimetres
 * - color       painted, no band
 * - holder      car holder mask (body recolor, strip, inscription)
 * - holder_moto motorcycle holder photo (strip and letters share palettes)
 * - holder_d    type D holder photo (strip and letters share palettes)
 *
 * `type_capabilities()` is the PHP map. Admin JS reads the same map from
 * `apdAdmin.typeCapabilities`. The shop payload copies it onto `format.capabilities`.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format definitions stored in plugin settings.
 */
final class APD_Formats {

	/**
	 * Real EU euroband width in millimetres (Council Regulation / Vienna plates).
	 */
	public const EU_BAND_WIDTH_MM = 40;

	/**
	 * Clear space inside a painted frame before the text area may begin.
	 * The same gap is kept beside a full-height country band.
	 */
	public const TEXT_FRAME_GAP_MM = 8;

	/**
	 * Real EU plate (and euroband) height in millimetres.
	 */
	public const EU_BAND_HEIGHT_MM = 110;

	/**
	 * Display cap for admin studio and shop canvas (CSS pixels).
	 */
	public const CANVAS_DISPLAY_MAX_PX = 500;

	/**
	 * Tallest shop preview, matching a 520×110 car plate at the max width.
	 */
	public const CANVAS_DISPLAY_MAX_H_PX = 106;

	/**
	 * Shop preview width in CSS pixels. Every plate uses the car-plate width.
	 * Height follows the format aspect ratio.
	 *
	 * @param int $width  Plate width in millimetres. Unused; kept for callers.
	 * @param int $height Plate height in millimetres. Unused; kept for callers.
	 * @return int
	 */
	public static function canvas_display_width( $width, $height ) {
		unset( $width, $height );

		return self::CANVAS_DISPLAY_MAX_PX;
	}

	/**
	 * Shop and admin preview width in CSS pixels.
	 *
	 * Plates stay at the shared car-plate width. A holder is as wide as the
	 * plate it is made for, on the same scale (520 mm = 500 px). The photo
	 * aspect stays the stored holder millimetres.
	 *
	 * @param string $type  Format type.
	 * @param int    $width Format width in millimetres.
	 * @return int
	 */
	public static function preview_frame_width( $type, $width ) {
		if ( self::is_holder( $type ) ) {
			$plate = self::holder_plate_size( $type );
			$plate_width = isset( $plate['width'] ) ? (int) $plate['width'] : max( 1, (int) $width );

			return max( 80, (int) round( self::CANVAS_DISPLAY_MAX_PX * ( $plate_width / 520 ) ) );
		}

		return self::canvas_display_width( $width, 0 );
	}

	/**
	 * Shoppers can add or hide the admin frame. USA plates and holders cannot.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function offers_frame_choice( $type ) {
		return ! in_array( (string) $type, array( 'holder', 'holder_moto', 'holder_d', 'us' ), true );
	}

	/**
	 * Admin studio sample for painted plates. Shorter than a full Bulgarian
	 * registration so the letters fill the number area at EU proportions.
	 */
	public const ADMIN_SAMPLE_PAINTED = 'CA 1234';

	/**
	 * Default strip length for holders. The Limits tab can raise or lower it.
	 */
	public const HOLDER_TEXT_MAX = 100;

	/**
	 * Four letters on the first SUV row, shown as two pairs.
	 */
	public const ADMIN_SAMPLE_SUV = 'CAAA 1234';

	public const SUV_ROW_1_MAX = 5;

	public const SUV_ROW_2_MAX = 4;

	/**
	 * Default character caps for a USA plate split around a center graphic.
	 */
	public const US_SIDE_LEFT_MAX = 3;

	public const US_SIDE_RIGHT_MAX = 4;

	/**
	 * Sample letters as a fraction of the dashed text-box height.
	 *
	 * Real EU glyphs are about 75 mm on a 110 mm plate (~68% of plate height).
	 * The text box itself is ~78% of the plate, so 0.90 of the box ≈ 70%.
	 */
	public const SAMPLE_FONT_FILL = 0.90;

	/**
	 * Singleton instance.
	 *
	 * @var APD_Formats|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Formats
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
	 * All formats.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all() {
		$settings = APD_Plugin::get_settings();

		return isset( $settings['formats'] ) && is_array( $settings['formats'] ) ? $settings['formats'] : array();
	}

	/**
	 * One format by ID.
	 *
	 * @param string $id Format ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $format ) {
			if ( isset( $format['id'] ) && $format['id'] === $id ) {
				return $format;
			}
		}

		return null;
	}

	/**
	 * Insert or replace a sanitized format.
	 *
	 * @param array<string, mixed> $raw Raw POST-like array.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save( $raw ) {
		$format = self::sanitize( $raw );

		if ( is_wp_error( $format ) ) {
			return $format;
		}

		$settings = APD_Plugin::get_settings();
		$found    = false;

		foreach ( $settings['formats'] as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $format['id'] ) {
				$settings['formats'][ $index ] = $format;
				$found                         = true;
				break;
			}
		}

		if ( ! $found ) {
			$settings['formats'][] = $format;
		}

		APD_Plugin::save_settings( $settings );

		return $format;
	}

	/**
	 * Copy a saved format under a new id. The name gains " (Copy)".
	 *
	 * The stored row is copied as saved, so a custom size stays as stored.
	 *
	 * @param string $id Source format ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function duplicate( $id ) {
		$source = self::get( $id );

		if ( ! is_array( $source ) ) {
			return new WP_Error(
				'apd_format_missing',
				__( 'Format was not found.', 'auto-plate-designer' )
			);
		}

		$copy         = $source;
		$copy['id']   = APD_Plugin::new_id();
		$copy['name'] = self::duplicate_name( isset( $source['name'] ) ? (string) $source['name'] : '' );
		$name_check   = APD_Security::validate_admin_label( $copy['name'], 80 );

		if ( is_wp_error( $name_check ) ) {
			return $name_check;
		}

		$settings              = APD_Plugin::get_settings();
		$settings['formats'][] = $copy;
		APD_Plugin::save_settings( $settings );

		return $copy;
	}

	/**
	 * Format name for a duplicate, trimmed so the label still fits.
	 *
	 * @param string $name Source name.
	 * @return string
	 */
	public static function duplicate_name( $name ) {
		$suffix = ' ' . __( '(Copy)', 'auto-plate-designer' );
		$name   = trim( $name );
		$max    = 80;

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			$room = $max - mb_strlen( $suffix, 'UTF-8' );

			if ( $room < 1 ) {
				$room = 1;
			}

			if ( mb_strlen( $name, 'UTF-8' ) > $room ) {
				$name = rtrim( mb_substr( $name, 0, $room, 'UTF-8' ) );
			}
		} else {
			$room = $max - strlen( $suffix );

			if ( $room < 1 ) {
				$room = 1;
			}

			if ( strlen( $name ) > $room ) {
				$name = rtrim( substr( $name, 0, $room ) );
			}
		}

		return $name . $suffix;
	}

	/**
	 * Delete a format by ID.
	 *
	 * @param string $id Format ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		if ( ! is_string( $id ) || '' === $id ) {
			return new WP_Error(
				'apd_format_id',
				__( 'Invalid format.', 'auto-plate-designer' )
			);
		}

		$settings = APD_Plugin::get_settings();
		$next     = array();
		$found    = false;

		foreach ( $settings['formats'] as $format ) {
			if ( isset( $format['id'] ) && $format['id'] === $id ) {
				$found = true;
				continue;
			}

			$next[] = $format;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_format_missing',
				__( 'Format was not found.', 'auto-plate-designer' )
			);
		}

		$settings['formats'] = $next;
		APD_Plugin::save_settings( $settings );

		if ( class_exists( 'APD_Designs' ) ) {
			APD_Designs::detach_format( $id );
		}

		return true;
	}

	/**
	 * Validate and normalize a format row.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error(
				'apd_format_type',
				__( 'Invalid format payload.', 'auto-plate-designer' )
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

		$type_check = APD_Security::validate_format_type( isset( $raw['type'] ) ? $raw['type'] : '' );

		if ( is_wp_error( $type_check ) ) {
			return $type_check;
		}

		$type = (string) $raw['type'];

		$size = self::default_size( $type );

		if ( 'custom' === $type ) {
			$width  = self::int_in_range( isset( $raw['width'] ) ? $raw['width'] : $size['width'], 100, 2000, $size['width'] );
			$height = self::int_in_range( isset( $raw['height'] ) ? $raw['height'] : $size['height'], 40, 1200, $size['height'] );
		} else {
			$width  = $size['width'];
			$height = $size['height'];
		}
		$border = self::int_in_range( isset( $raw['border_width'] ) ? $raw['border_width'] : 8, 0, 40, 8 );

		$color = isset( $raw['border_color'] ) ? (string) $raw['border_color'] : '#000000';
		$color_check = APD_Security::validate_hex_color( $color );

		if ( is_wp_error( $color_check ) ) {
			return $color_check;
		}

		$eu_ratio  = self::eu_band_ratio( $width, $height );
		$band_side = ( isset( $raw['band_side'] ) && 'right' === $raw['band_side'] ) ? 'right' : 'left';
		$no_frame  = self::uses_painted_plate( $type ) && ! empty( $raw['no_frame'] );
		$band_box  = array();

		if ( self::uses_country_band( $type ) ) {
			$posted_box = isset( $raw['band_box'] ) && is_array( $raw['band_box'] ) ? $raw['band_box'] : array();
			$has_box    = isset( $posted_box['width'] ) || isset( $posted_box['x'] ) || isset( $posted_box['height'] );

			if ( $has_box ) {
				$band_box = self::sanitize_band_box( $posted_box, $width, $height, $band_side, $type );
			} elseif ( isset( $raw['band_ratio'] ) && is_numeric( $raw['band_ratio'] ) ) {
				$legacy_ratio = self::float_in_range( $raw['band_ratio'], 0.04, 0.4, $eu_ratio );
				$band_box     = self::sanitize_band_box(
					self::band_box_from_ratio( $legacy_ratio, $band_side ),
					$width,
					$height,
					$band_side,
					$type
				);
			} else {
				$band_box = self::sanitize_band_box( array(), $width, $height, $band_side, $type );
			}

			$band_ratio = round( $band_box['width'] / 100, 4 );
			$band_side  = ( ( $band_box['x'] + ( $band_box['width'] / 2 ) ) >= 50 ) ? 'right' : 'left';
		} else {
			$band_ratio = self::float_in_range( isset( $raw['band_ratio'] ) ? $raw['band_ratio'] : $eu_ratio, 0.04, 0.4, $eu_ratio );
		}

		$price = isset( $raw['price_adjustment'] ) ? (float) $raw['price_adjustment'] : 0.0;

		if ( $price < -10000 || $price > 10000 ) {
			return new WP_Error(
				'apd_format_price',
				__( 'Price adjustment is out of range.', 'auto-plate-designer' )
			);
		}

		$base_image_id = isset( $raw['base_image_id'] ) ? absint( $raw['base_image_id'] ) : 0;

		if ( self::uses_base_image( $type ) ) {
			$image_optional = ( self::is_holder( $type ) && '' !== self::bundled_holder_image_url( $type ) ) || 'us' === $type;
			$image_check    = APD_Security::validate_attachment(
				$base_image_id,
				array(
					'mimes'     => APD_Security::RASTER_MIMES,
					'allow_svg' => false,
					'optional'  => $image_optional,
				)
			);

			if ( is_wp_error( $image_check ) ) {
				if ( 'us' === $type ) {
					$image_message = __( 'USA formats require a photo of the plate.', 'auto-plate-designer' );
				} elseif ( 'suv' === $type ) {
					$image_message = __( 'SUV formats require a photo of the full plate.', 'auto-plate-designer' );
				} else {
					$image_message = __( 'Holder formats require a photo of the plate holder.', 'auto-plate-designer' );
				}

				return new WP_Error( 'apd_format_base_image', $image_message );
			}
		} else {
			$base_image_id = 0;
		}

		$max_default    = self::default_max_chars( $type );
		$max_chars      = self::int_in_range( isset( $raw['max_chars'] ) ? $raw['max_chars'] : $max_default, 1, APD_Security::ABSOLUTE_MAX_CHARS, $max_default );
		$row_1_max      = 0;
		$row_2_max      = 0;
		$holder_max_set = false;

		if ( self::uses_two_rows( $type ) ) {
			$row_1_max = self::int_in_range( isset( $raw['max_chars_row_1'] ) ? $raw['max_chars_row_1'] : self::SUV_ROW_1_MAX, 1, APD_Security::ABSOLUTE_MAX_CHARS, self::SUV_ROW_1_MAX );
			$row_2_max = self::int_in_range( isset( $raw['max_chars_row_2'] ) ? $raw['max_chars_row_2'] : self::SUV_ROW_2_MAX, 1, APD_Security::ABSOLUTE_MAX_CHARS, self::SUV_ROW_2_MAX );
			$max_chars = min( APD_Security::ABSOLUTE_MAX_CHARS, $row_1_max + $row_2_max );
		} elseif ( self::is_holder( $type ) ) {
			$posted_max = isset( $raw['max_chars'] ) ? trim( (string) $raw['max_chars'] ) : '';
			if ( '' === $posted_max || '0' === $posted_max ) {
				$max_chars      = 0;
				$holder_max_set = false;
			} else {
				$max_chars      = self::int_in_range( $posted_max, 1, APD_Security::ABSOLUTE_MAX_CHARS, self::holder_text_limit() );
				$holder_max_set = true;
			}
		}
		$font_ids_all = ! empty( $raw['font_ids_all'] );
		$font_ids     = $font_ids_all
			? self::all_font_ids()
			: self::sanitize_font_ids( isset( $raw['font_ids'] ) ? $raw['font_ids'] : array() );
		$normalized  = array(
			'type'         => $type,
			'width'        => $width,
			'height'       => $height,
			'border_width' => $border,
			'no_frame'     => $no_frame,
			'band_ratio'   => $band_ratio,
			'band_side'    => $band_side,
			'band_box'     => $band_box,
		);
		$text_raw = isset( $raw['text_box'] ) && is_array( $raw['text_box'] ) ? $raw['text_box'] : array();

		if ( self::is_holder( $type ) && isset( $raw['text_box_metric'] ) && 'strip' === sanitize_key( (string) $raw['text_box_metric'] ) ) {
			$text_raw = self::strip_percent_box_to_canvas( $text_raw, $type );
		}

		$row = array(
			'id'               => $id,
			'name'             => APD_Security::sanitize_admin_label( (string) $raw['name'] ),
			'type'             => $type,
			'width'            => $width,
			'height'           => $height,
			'border_width'     => $border,
			'border_color'     => APD_Security::sanitize_hex_color( $color ),
			'no_frame'         => $no_frame,
			'band_ratio'       => $band_ratio,
			'band_side'        => $band_side,
			'band_box'         => $band_box,
			'base_image_id'    => $base_image_id,
			'color_zones'      => array(),
			'text_box'         => self::sanitize_text_box( $text_raw, $type, $normalized ),
			'max_chars'        => $max_chars,
			'holder_max_set'   => $holder_max_set,
			'max_chars_row_1'  => $row_1_max,
			'max_chars_row_2'  => $row_2_max,
			'font_ids'         => $font_ids,
			'font_ids_all'     => $font_ids_all,
			'wrap_text'        => self::is_street_plate( $type ) && ! empty( $raw['wrap_text'] ),
			'price_adjustment' => round( $price, 2 ),
		);

		if ( 'us' === $type && ! empty( $raw['split_text'] ) ) {
			$right_raw = isset( $raw['text_box_right'] ) && is_array( $raw['text_box_right'] ) ? $raw['text_box_right'] : array();
			$pair      = ( isset( $right_raw['x'] ) || isset( $right_raw['width'] ) )
				? self::link_side_boxes( $row['text_box'], $right_raw )
				: self::seed_side_boxes( $row['text_box'] );
			$side_left  = self::int_in_range( isset( $raw['max_chars_left'] ) ? $raw['max_chars_left'] : self::US_SIDE_LEFT_MAX, 1, APD_Security::ABSOLUTE_MAX_CHARS, self::US_SIDE_LEFT_MAX );
			$side_right = self::int_in_range( isset( $raw['max_chars_right'] ) ? $raw['max_chars_right'] : self::US_SIDE_RIGHT_MAX, 1, APD_Security::ABSOLUTE_MAX_CHARS, self::US_SIDE_RIGHT_MAX );
			$row['split_text']      = true;
			$row['text_box']        = $pair[0];
			$row['text_box_right']  = $pair[1];
			$row['max_chars_left']  = $side_left;
			$row['max_chars_right'] = $side_right;
			$row['max_chars']       = min( APD_Security::ABSOLUTE_MAX_CHARS, $side_left + $side_right );
		}

		$palette_ids = APD_Color_Palettes::sanitize_product_ids( isset( $raw['palette_ids'] ) ? $raw['palette_ids'] : array(), $type );

		if ( self::uses_palette_slots( $type ) && isset( $raw['palette_slots'] ) ) {
			$palette_slots = self::sanitize_palette_slots( $raw['palette_slots'], $type );

			if ( is_wp_error( $palette_slots ) ) {
				return $palette_slots;
			}

			$row['palette_slots'] = $palette_slots;
			$palette_ids          = array_values( $palette_slots );
		}

		$row['palette_ids'] = $palette_ids;

		return $row;
	}

	/**
	 * Format types that pick one palette for text, plate fill, and frame.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_palette_slots( $type ) {
		return in_array( (string) $type, array( 'color', 'custom', 'eu_plain', 'moto_plain', 'moto_plain_240', 'suv', 'us' ), true );
	}

	/**
	 * Palette menus shown for a format type.
	 *
	 * USA plates are a graphic, so only the text color is chosen.
	 *
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function palette_slot_keys_for( $type ) {
		if ( 'us' === (string) $type ) {
			return array( 'text' );
		}

		return self::palette_slot_keys();
	}

	/**
	 * Shop rows that use a palette menu.
	 *
	 * @return array<int, string>
	 */
	public static function palette_slot_keys() {
		return array( 'text', 'background', 'border' );
	}

	/**
	 * Older checkbox purposes that belong on one shop row.
	 *
	 * @param string $slot text|background|border.
	 * @return array<int, string>
	 */
	public static function palette_slot_purposes( $slot ) {
		$map = array(
			'text'       => array( 'text', 'color_text' ),
			'background' => array( 'background', 'color_background' ),
			'border'     => array( 'border', 'color_border' ),
		);

		return isset( $map[ $slot ] ) ? $map[ $slot ] : array();
	}

	/**
	 * One active palette for each shop row.
	 *
	 * @param mixed $raw Posted slot map.
	 * @return array<string, string>|WP_Error
	 */
	public static function sanitize_palette_slots( $raw, $type = '' ) {
		$clean = array();

		foreach ( self::palette_slot_keys_for( $type ) as $slot ) {
			$clean[ $slot ] = '';
		}

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		foreach ( array_keys( $clean ) as $slot ) {
			$id      = isset( $raw[ $slot ] ) ? sanitize_text_field( (string) $raw[ $slot ] ) : '';
			$palette = '' !== $id ? APD_Color_Palettes::get( $id ) : null;
			$colors  = is_array( $palette ) && isset( $palette['color_ids'] ) && is_array( $palette['color_ids'] ) ? $palette['color_ids'] : array();

			if ( ! is_array( $palette ) || empty( $palette['active'] ) || empty( $colors ) ) {
				$message = 'us' === (string) $type
					? __( 'Choose a palette for the text.', 'auto-plate-designer' )
					: __( 'Choose a palette for the text, the plate, and the frame.', 'auto-plate-designer' );

				return new WP_Error( 'apd_format_palette_slot', $message );
			}

			$clean[ $slot ] = $id;
		}

		return $clean;
	}

	/**
	 * Menu values for the format editor.
	 *
	 * A saved slot map is kept. An older checkbox list selects a row only when exactly one palette matches that row.
	 *
	 * @param array<string, mixed> $format     Format row.
	 * @param array<int, string>   $legacy_ids Palette IDs from the format or its products.
	 * @return array<string, string>
	 */
	public static function palette_slots_for_editor( $format, $legacy_ids ) {
		$slots = array(
			'text'       => '',
			'background' => '',
			'border'     => '',
		);

		if ( is_array( $format ) && isset( $format['palette_slots'] ) && is_array( $format['palette_slots'] ) ) {
			foreach ( array_keys( $slots ) as $slot ) {
				$slots[ $slot ] = isset( $format['palette_slots'][ $slot ] ) ? sanitize_text_field( (string) $format['palette_slots'][ $slot ] ) : '';
			}

			return $slots;
		}

		if ( ! is_array( $legacy_ids ) ) {
			$legacy_ids = array();
		}

		foreach ( array_keys( $slots ) as $slot ) {
			$matches = array();

			foreach ( $legacy_ids as $id ) {
				$id      = sanitize_text_field( (string) $id );
				$palette = '' !== $id ? APD_Color_Palettes::get( $id ) : null;
				$purpose = is_array( $palette ) && isset( $palette['purpose'] ) ? (string) $palette['purpose'] : '';

				if ( is_array( $palette ) && ! empty( $palette['active'] ) && in_array( $purpose, self::palette_slot_purposes( $slot ), true ) ) {
					$matches[] = $id;
				}
			}

			$matches = array_values( array_unique( $matches ) );

			if ( 1 === count( $matches ) ) {
				$slots[ $slot ] = $matches[0];
			}
		}

		return $slots;
	}

	/**
	 * Clamp an integer.
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $min     Minimum.
	 * @param int   $max     Maximum.
	 * @param int   $default Fallback.
	 * @return int
	 */
	private static function int_in_range( $value, $min, $max, $default ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$int = (int) $value;

		if ( $int < $min ) {
			return $min;
		}

		if ( $int > $max ) {
			return $max;
		}

		return $int;
	}

	/**
	 * Clamp a float.
	 *
	 * @param mixed $value   Raw value.
	 * @param float $min     Minimum.
	 * @param float $max     Maximum.
	 * @param float $default Fallback.
	 * @return float
	 */
	private static function float_in_range( $value, $min, $max, $default ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$float = (float) $value;

		if ( $float < $min ) {
			return $min;
		}

		if ( $float > $max ) {
			return $max;
		}

		return round( $float, 4 );
	}

	/**
	 * Default character cap for a format type.
	 *
	 * @param string $type Format type.
	 * @return int
	 */
	public static function default_max_chars( $type ) {
		switch ( $type ) {
			case 'us':
				return 8;
			case 'holder':
			case 'holder_moto':
			case 'holder_d':
				return self::HOLDER_TEXT_MAX;
			case 'custom':
				return 40;
			default:
				return 12;
		}
	}

	/**
	 * Drawing capabilities for a format type.
	 *
	 * @param string $type Format type slug.
	 * @return array{painted: bool, country_band: bool, base_image: bool, plate_designs: bool}
	 */
	public static function type_capabilities( $type ) {
		switch ( (string) $type ) {
			case 'eu':
			case 'moto':
			case 'moto_240':
			case 'suv_eu':
				$caps = array(
					'painted'       => true,
					'country_band'  => true,
					'base_image'    => false,
					'plate_designs' => false,
				);
				break;
			case 'moto_plain':
			case 'moto_plain_240':
			case 'eu_plain':
			case 'suv':
			case 'custom':
			case 'color':
				$caps = array(
					'painted'       => true,
					'country_band'  => false,
					'base_image'    => false,
					'plate_designs' => false,
				);
				break;
			case 'holder':
			case 'holder_moto':
			case 'holder_d':
				$caps = array(
					'painted'       => false,
					'country_band'  => false,
					'base_image'    => true,
					'plate_designs' => false,
				);
				break;
			case 'us':
				$caps = array(
					'painted'       => false,
					'country_band'  => false,
					'base_image'    => true,
					'plate_designs' => true,
				);
				break;
			default:
				$caps = array(
					'painted'       => false,
					'country_band'  => false,
					'base_image'    => false,
					'plate_designs' => false,
				);
				break;
		}

		$caps['two_row'] = self::uses_two_rows( $type );

		return $caps;
	}

	/**
	 * Camel-cased copy of type_capabilities for admin JS.
	 *
	 * @param string $type Format type slug.
	 * @return array{painted: bool, countryBand: bool, baseImage: bool, plateDesigns: bool}
	 */
	public static function js_capabilities( $type ) {
		$caps = self::type_capabilities( $type );

		return array(
			'painted'      => ! empty( $caps['painted'] ),
			'countryBand'  => ! empty( $caps['country_band'] ),
			'baseImage'    => ! empty( $caps['base_image'] ),
			'plateDesigns' => ! empty( $caps['plate_designs'] ),
			'twoRow'       => ! empty( $caps['two_row'] ),
		);
	}

	/**
	 * Family for related format types (moto_plain → moto, suv_eu → suv).
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function catalog_kind( $type ) {
		switch ( (string) $type ) {
			case 'moto_plain':
			case 'moto_240':
			case 'moto_plain_240':
				return 'moto';
			case 'eu_plain':
				return 'eu';
			case 'suv_eu':
				return 'suv';
			case 'holder_moto':
			case 'holder_d':
				return 'holder';
			default:
				return (string) $type;
		}
	}

	/**
	 * Car, motorcycle, and type D holders share the strip and the character limit.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function is_holder( $type ) {
		return in_array( (string) $type, array( 'holder', 'holder_moto', 'holder_d' ), true );
	}

	/**
	 * Photo holders keep the plastic from the picture. Only the strip and the letters are painted.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function is_photo_holder( $type ) {
		return in_array( (string) $type, array( 'holder_moto', 'holder_d' ), true );
	}

	/**
	 * Street plate: painted, no country band, size typed in millimetres.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function is_street_plate( $type ) {
		return 'custom' === (string) $type;
	}

	/**
	 * SUV family covers the EU SUV plate and the SUV plate without a preset.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function is_suv_kind( $type ) {
		return 'suv' === self::catalog_kind( $type );
	}

	/**
	 * Character caps for the two rows on motorcycle and SUV plates.
	 *
	 * Formats saved before these fields existed use 5 on the first row and 4 on the second.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return array{0: int, 1: int}
	 */
	public static function suv_row_limits( $format ) {
		if ( ! is_array( $format ) || ! self::uses_two_rows( isset( $format['type'] ) ? (string) $format['type'] : '' ) ) {
			return array( 0, 0 );
		}

		$row1 = isset( $format['max_chars_row_1'] ) ? (int) $format['max_chars_row_1'] : 0;
		$row2 = isset( $format['max_chars_row_2'] ) ? (int) $format['max_chars_row_2'] : 0;

		if ( $row1 < 1 ) {
			$row1 = self::SUV_ROW_1_MAX;
		}

		if ( $row2 < 1 ) {
			$row2 = self::SUV_ROW_2_MAX;
		}

		$ceiling = APD_Security::ABSOLUTE_MAX_CHARS;

		return array( min( $ceiling, $row1 ), min( $ceiling, $row2 ) );
	}

	/**
	 * Character caps for the left and right fields of a split USA plate.
	 *
	 * @param array<string, mixed> $row Format or design row.
	 * @return array{0: int, 1: int}
	 */
	public static function us_side_limits( $row ) {
		$row   = is_array( $row ) ? $row : array();
		$left  = isset( $row['max_chars_left'] ) ? (int) $row['max_chars_left'] : 0;
		$right = isset( $row['max_chars_right'] ) ? (int) $row['max_chars_right'] : 0;

		if ( $left < 1 ) {
			$left = self::US_SIDE_LEFT_MAX;
		}

		if ( $right < 1 ) {
			$right = self::US_SIDE_RIGHT_MAX;
		}

		$ceiling = APD_Security::ABSOLUTE_MAX_CHARS;

		return array( min( $ceiling, $left ), min( $ceiling, $right ) );
	}

	/**
	 * Side limits for the graphic the shopper is actually typing on.
	 *
	 * A format photo wins over a design. Otherwise the locked design is used.
	 *
	 * @param array<string, mixed> $payload   Frontend format payload.
	 * @param string               $design_id Design id, or empty for the first design.
	 * @return array{0: int, 1: int}|null
	 */
	public static function active_us_side_limits( $payload, $design_id = '' ) {
		if ( ! is_array( $payload ) || 'us' !== ( isset( $payload['type'] ) ? (string) $payload['type'] : '' ) ) {
			return null;
		}

		if ( ! empty( $payload['base_image_url'] ) ) {
			if ( empty( $payload['split_text'] ) ) {
				return null;
			}

			$limits = isset( $payload['side_max_chars'] ) && is_array( $payload['side_max_chars'] ) ? $payload['side_max_chars'] : array();

			return self::us_side_limits(
				array(
					'max_chars_left'  => isset( $limits[0] ) ? $limits[0] : 0,
					'max_chars_right' => isset( $limits[1] ) ? $limits[1] : 0,
				)
			);
		}

		$designs = isset( $payload['designs'] ) && is_array( $payload['designs'] ) ? $payload['designs'] : array();
		$chosen  = null;

		foreach ( $designs as $design ) {
			if ( ! is_array( $design ) ) {
				continue;
			}

			if ( '' !== (string) $design_id && isset( $design['id'] ) && (string) $design['id'] === (string) $design_id ) {
				$chosen = $design;
				break;
			}
		}

		if ( null === $chosen && ! empty( $designs ) && is_array( $designs[0] ) ) {
			$chosen = $designs[0];
		}

		if ( ! is_array( $chosen ) || empty( $chosen['split_text'] ) ) {
			return null;
		}

		$limits = isset( $chosen['side_max_chars'] ) && is_array( $chosen['side_max_chars'] ) ? $chosen['side_max_chars'] : array();

		return self::us_side_limits(
			array(
				'max_chars_left'  => isset( $limits[0] ) ? $limits[0] : 0,
				'max_chars_right' => isset( $limits[1] ) ? $limits[1] : 0,
			)
		);
	}

	/**
	 * Character cap for the holder strip, from Limits → Per-layout limits.
	 *
	 * @return int
	 */
	public static function holder_text_limit() {
		$settings = APD_Plugin::get_settings();
		$row      = isset( $settings['limits']['layouts']['holder'] ) && is_array( $settings['limits']['layouts']['holder'] )
			? $settings['limits']['layouts']['holder']
			: array();
		$chars    = isset( $row['max_chars'] ) ? (int) $row['max_chars'] : self::HOLDER_TEXT_MAX;

		if ( $chars < 1 ) {
			$chars = self::HOLDER_TEXT_MAX;
		}

		if ( $chars > APD_Security::ABSOLUTE_MAX_CHARS ) {
			$chars = APD_Security::ABSOLUTE_MAX_CHARS;
		}

		return $chars;
	}

	/**
	 * How many lines a wrapped street plate may use.
	 *
	 * @return int
	 */
	public static function wrap_line_limit() {
		$settings = APD_Plugin::get_settings();
		$row      = isset( $settings['limits']['layouts']['multiline_text'] ) && is_array( $settings['limits']['layouts']['multiline_text'] )
			? $settings['limits']['layouts']['multiline_text']
			: array();
		$lines    = isset( $row['max_lines'] ) ? (int) $row['max_lines'] : 5;

		if ( $lines < 1 ) {
			$lines = 1;
		}

		if ( $lines > 20 ) {
			$lines = 20;
		}

		return $lines;
	}

	/**
	 * Characters column for the formats list.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return string
	 */
	public static function max_chars_label( $format ) {
		if ( self::is_holder( isset( $format['type'] ) ? (string) $format['type'] : '' ) ) {
			if ( empty( $format['holder_max_set'] ) ) {
				return '—';
			}

			return (string) (int) $format['max_chars'];
		}

		if ( self::uses_two_rows( isset( $format['type'] ) ? (string) $format['type'] : '' ) ) {
			$limits = self::suv_row_limits( $format );

			return $limits[0] . ' / ' . $limits[1];
		}

		if ( 'us' === ( isset( $format['type'] ) ? (string) $format['type'] : '' ) && ! empty( $format['split_text'] ) ) {
			$sides = self::us_side_limits( $format );

			return $sides[0] . ' + ' . $sides[1];
		}

		$chars = isset( $format['max_chars'] ) ? (int) $format['max_chars'] : self::default_max_chars( isset( $format['type'] ) ? (string) $format['type'] : '' );

		return (string) $chars;
	}

	/**
	 * Two lines for an SUV plate. A newline is an explicit split. Older
	 * single-line text keeps letters on the first row and digits on the second.
	 *
	 * @param string $text Plate text.
	 * @return array{0: string, 1: string}
	 */
	public static function suv_plate_rows( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );

		if ( false !== strpos( $text, "\n" ) ) {
			$parts = explode( "\n", $text, 2 );

			return array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
		}

		$letters = '';
		$numbers = '';
		$chars   = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $chars ) ) {
			return array( '', '' );
		}

		foreach ( $chars as $char ) {
			if ( preg_match( '/[0-9]/', $char ) ) {
				$numbers .= $char;
			} elseif ( preg_match( '/\p{L}/u', $char ) ) {
				$letters .= $char;
			}
		}

		$groups = array();
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $letters, 'UTF-8' ) : strlen( $letters );

		for ( $index = 0; $index < $length; $index += 2 ) {
			$groups[] = function_exists( 'mb_substr' )
				? mb_substr( $letters, $index, 2, 'UTF-8' )
				: substr( $letters, $index, 2 );
		}

		return array( implode( ' ', $groups ), $numbers );
	}

	/**
	 * Join two SUV rows into the single stored plate string.
	 *
	 * @param string $row1 First row.
	 * @param string $row2 Second row.
	 * @return string
	 */
	public static function join_suv_rows( $row1, $row2 ) {
		$row1 = APD_Security::sanitize_plate_text( (string) $row1, false );
		$row2 = APD_Security::sanitize_plate_text( (string) $row2, false );

		if ( '' === $row1 && '' === $row2 ) {
			return '';
		}

		return $row1 . "\n" . $row2;
	}

	/**
	 * Keep the combined SUV rows inside the format character limit.
	 *
	 * The joining newline is not counted.
	 *
	 * @param string $text      Joined plate text.
	 * @param int    $max_chars Visible character limit.
	 * @return string
	 */
	public static function fit_suv_text( $text, $max_chars ) {
		$text      = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$max_chars = (int) $max_chars;

		if ( '' === $text ) {
			return '';
		}

		$parts = explode( "\n", $text, 2 );
		$row1  = $parts[0];
		$row2  = isset( $parts[1] ) ? $parts[1] : '';

		if ( $max_chars <= 0 ) {
			return $row1 . "\n" . $row2;
		}

		$length = static function ( $value ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		};
		$cut    = static function ( $value ) use ( $length ) {
			$size = $length( $value );

			if ( $size <= 0 ) {
				return '';
			}

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $size - 1, 'UTF-8' ) : substr( $value, 0, -1 );
		};

		while ( $length( $row1 ) + $length( $row2 ) > $max_chars ) {
			if ( '' !== $row2 ) {
				$row2 = $cut( $row2 );
			} else {
				$row1 = $cut( $row1 );
			}
		}

		if ( '' === $row1 && '' === $row2 ) {
			return '';
		}

		return $row1 . "\n" . $row2;
	}

	/**
	 * Cut each SUV row to its own character cap.
	 *
	 * @param string $row1   First row.
	 * @param string $row2   Second row.
	 * @param int    $limit1 First-row cap.
	 * @param int    $limit2 Second-row cap.
	 * @return string
	 */
	public static function limit_suv_rows( $row1, $row2, $limit1, $limit2 ) {
		$cut = static function ( $value, $max ) {
			$value = (string) $value;
			$max   = (int) $max;

			if ( $max < 1 ) {
				return '';
			}

			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );

			if ( $length <= $max ) {
				return $value;
			}

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
		};

		return self::join_suv_rows( $cut( $row1, $limit1 ), $cut( $row2, $limit2 ) );
	}

	/**
	 * Human-readable type label.
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'eu'         => __( 'EU plate', 'auto-plate-designer' ),
			'eu_plain'   => __( 'Car plate without preset', 'auto-plate-designer' ),
			'us'         => __( 'USA plate', 'auto-plate-designer' ),
			'moto'            => __( 'EU motorcycle plate 199×154', 'auto-plate-designer' ),
			'moto_240'        => __( 'EU motorcycle plate 240×130', 'auto-plate-designer' ),
			'moto_plain'      => __( 'Motorcycle plate without country band 199×154', 'auto-plate-designer' ),
			'moto_plain_240'  => __( 'Motorcycle plate without country band 240×130', 'auto-plate-designer' ),
			'suv_eu'     => __( 'EU SUV plate', 'auto-plate-designer' ),
			'suv'        => __( 'SUV plate without preset', 'auto-plate-designer' ),
			'custom'     => __( 'Street plate', 'auto-plate-designer' ),
			'color'      => __( 'Color plate', 'auto-plate-designer' ),
			'holder'      => __( 'Car plate holders', 'auto-plate-designer' ),
			'holder_moto' => __( 'Motorcycle plate holder 199×154', 'auto-plate-designer' ),
			'holder_d'    => __( 'Type D plate holder', 'auto-plate-designer' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : strtoupper( $type );
	}

	/**
	 * Whether shoppers pick an EU country band on this format.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_country_band( $type ) {
		$caps = self::type_capabilities( $type );

		return ! empty( $caps['country_band'] );
	}

	/**
	 * Whether the plate is drawn as a filled rectangle with a border (not a photo).
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_painted_plate( $type ) {
		$caps = self::type_capabilities( $type );

		return ! empty( $caps['painted'] );
	}

	/**
	 * Motorcycle and two-line SUV plates: letters on the first row, numbers on the second.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_two_rows( $type ) {
		return in_array( (string) $type, array( 'moto', 'moto_240', 'moto_plain', 'moto_plain_240', 'suv', 'suv_eu' ), true );
	}

	/**
	 * Fallback letters drawn before a shopper types their own plate.
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function sample_plate_text( $type ) {
		if ( self::is_suv_kind( $type ) ) {
			return "CA AA\n4935";
		}

		if ( self::uses_two_rows( $type ) ) {
			return "CA\n1234";
		}

		if ( self::uses_painted_plate( $type ) ) {
			return 'CA 0909 BX';
		}

		return self::is_holder( $type ) ? '' : 'TEXT';
	}

	/**
	 * Letters drawn in the admin Formats studio (not the shop placeholder).
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function admin_sample_plate_text( $type ) {
		if ( self::is_suv_kind( $type ) ) {
			return self::ADMIN_SAMPLE_SUV;
		}

		if ( self::uses_painted_plate( $type ) || self::uses_two_rows( $type ) ) {
			return self::ADMIN_SAMPLE_PAINTED;
		}

		return __( 'TEXT', 'auto-plate-designer' );
	}

	/**
	 * Whether this type stores a full product photo (holder or SUV graphic).
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_base_image( $type ) {
		$caps = self::type_capabilities( $type );

		return ! empty( $caps['base_image'] );
	}

	/**
	 * Default millimetre canvas for a type.
	 *
	 * Bulgarian plates follow BDS 15980: car 520×110, motorcycle 199×154 or 240×130,
	 * two-line 280×200. USA plates stay 12×6 in (305×152).
	 * The car holder stays 520×260. The motorcycle holder is square and uses
	 * the motorcycle plate width (199×199). Type D uses the two-line width;
	 * 159 mm is that width at the product photo's proportion (1400×795).
	 *
	 * @param string $type Format type.
	 * @return array{width: int, height: int}
	 */
	public static function default_size( $type ) {
		switch ( $type ) {
			case 'us':
				return array(
					'width'  => 305,
					'height' => 152,
				);
			case 'moto':
			case 'moto_plain':
				return array(
					'width'  => 199,
					'height' => 154,
				);
			case 'moto_240':
			case 'moto_plain_240':
				return array(
					'width'  => 240,
					'height' => 130,
				);
			case 'suv':
			case 'suv_eu':
				return array(
					'width'  => 280,
					'height' => 200,
				);
			case 'holder':
				return array(
					'width'  => 520,
					'height' => 260,
				);
			case 'holder_moto':
				return array(
					'width'  => 199,
					'height' => 199,
				);
			case 'holder_d':
				return array(
					'width'  => 280,
					'height' => 159,
				);
			default:
				return array(
					'width'  => 520,
					'height' => 110,
				);
		}
	}

	/**
	 * Plate this holder is built for, in millimetres.
	 *
	 * The holder canvas stays the photo size. This size is the number the
	 * formats list prints and the width the preview uses.
	 *
	 * @param string $type Holder type.
	 * @return array{width: int, height: int}
	 */
	public static function holder_plate_size( $type ) {
		switch ( (string) $type ) {
			case 'holder_moto':
				return array(
					'width'  => 199,
					'height' => 154,
				);
			case 'holder_d':
				return array(
					'width'  => 280,
					'height' => 200,
				);
			default:
				return array(
					'width'  => 520,
					'height' => 110,
				);
		}
	}

	/**
	 * Size cell for the formats list.
	 *
	 * Holders name the plate they fit. Other types keep their canvas millimetres.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return string
	 */
	public static function format_size_label( $format ) {
		$format = is_array( $format ) ? $format : array();
		$type   = isset( $format['type'] ) ? (string) $format['type'] : '';

		if ( self::is_holder( $type ) ) {
			$plate = self::holder_plate_size( $type );

			return sprintf(
				/* translators: 1: plate width in millimetres, 2: plate height in millimetres. */
				__( 'For a plate measuring %1$d×%2$d mm', 'auto-plate-designer' ),
				(int) $plate['width'],
				(int) $plate['height']
			);
		}

		$width  = isset( $format['width'] ) ? (int) $format['width'] : 0;
		$height = isset( $format['height'] ) ? (int) $format['height'] : 0;

		return $width . ' x ' . $height;
	}

	/**
	 * Whether this stored format draws a millimetre border.
	 *
	 * @param array<string, mixed>|null $format Format row.
	 * @return bool
	 */
	public static function uses_frame( $format ) {
		if ( ! is_array( $format ) ) {
			return false;
		}

		$type = isset( $format['type'] ) ? (string) $format['type'] : '';

		return self::uses_painted_plate( $type ) && empty( $format['no_frame'] );
	}

	/**
	 * Default country-band box as percents of the plate.
	 *
	 * @param int    $width  Canvas width (mm).
	 * @param int    $height Canvas height (mm).
	 * @param string $side   left|right.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function default_band_box( $width = 520, $height = 110, $side = 'left', $type = '' ) {
		if ( 'suv_eu' === (string) $type ) {
			return self::suv_band_box( $width, $height, $side );
		}

		return self::band_box_from_ratio( self::eu_band_ratio( $width, $height ), $side );
	}

	/**
	 * Euroband on the first row of a Bulgarian two-line SUV plate.
	 *
	 * The band is half the plate tall. Its width is a 40 mm strip scaled to
	 * that row, so a 280×200 plate gets about a 36×100 mm band on the top line.
	 *
	 * @param int    $width  Canvas width (mm).
	 * @param int    $height Canvas height (mm).
	 * @param string $side   left|right.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function suv_band_box( $width, $height, $side = 'left' ) {
		$width  = max( 1, (int) $width );
		$height = max( 1, (int) $height );
		$ratio  = ( self::EU_BAND_WIDTH_MM / self::EU_BAND_HEIGHT_MM ) * ( $height / 2 ) / $width;
		$box    = self::band_box_from_ratio( $ratio, $side );
		$box['y']      = 0.0;
		$box['height'] = 50.0;

		return $box;
	}

	/**
	 * Country-band box from a width ratio and a side.
	 *
	 * @param float  $ratio Width of the band as a fraction of the plate.
	 * @param string $side  left|right.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function band_box_from_ratio( $ratio, $side = 'left' ) {
		$width = round( max( 4.0, min( 25.0, (float) $ratio * 100 ) ), 1 );
		$side  = ( 'right' === $side ) ? 'right' : 'left';

		return array(
			'x'      => ( 'right' === $side ) ? round( 100 - $width, 1 ) : 0.0,
			'y'      => 0.0,
			'width'  => $width,
			'height' => 100.0,
		);
	}

	/**
	 * Clamp a country-band box. Values are percents of the plate canvas.
	 *
	 * @param mixed  $raw          Raw values.
	 * @param int    $plate_width  Canvas width (mm).
	 * @param int    $plate_height Canvas height (mm).
	 * @param string $side         Fallback side.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function sanitize_band_box( $raw, $plate_width = 520, $plate_height = 110, $side = 'left', $type = '' ) {
		$defaults = self::default_band_box( $plate_width, $plate_height, $side, $type );
		$raw      = is_array( $raw ) ? $raw : array();

		$x      = self::clamp_percent( isset( $raw['x'] ) ? $raw['x'] : $defaults['x'], 0, 96 );
		$y      = self::clamp_percent( isset( $raw['y'] ) ? $raw['y'] : $defaults['y'], 0, 60 );
		$width  = self::clamp_percent( isset( $raw['width'] ) ? $raw['width'] : $defaults['width'], 4, 25 );
		$height = self::clamp_percent( isset( $raw['height'] ) ? $raw['height'] : $defaults['height'], 40, 100 );

		if ( $x + $width > 100 ) {
			$x = round( 100 - $width, 1 );
		}

		if ( $y + $height > 100 ) {
			$y = round( 100 - $height, 1 );
		}

		if ( $x < 0 ) {
			$x = 0.0;
		}

		if ( $y < 0 ) {
			$y = 0.0;
		}

		return array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $width,
			'height' => $height,
		);
	}

	/**
	 * Width of a real euroband as a fraction of this canvas.
	 *
	 * 40 mm band on a 110 mm-tall plate, scaled by the format size so a
	 * 520×110 EU plate gets a 40 mm band (≈ 7.7%), not a stretched 15% strip.
	 *
	 * @param int $width  Canvas width (mm).
	 * @param int $height Canvas height (mm).
	 * @return float
	 */
	public static function eu_band_ratio( $width, $height ) {
		$width  = max( 1, (int) $width );
		$height = max( 1, (int) $height );

		return round( ( self::EU_BAND_WIDTH_MM / self::EU_BAND_HEIGHT_MM ) * $height / $width, 4 );
	}

	/**
	 * Whether shoppers pick a full-plate graphic (state / design) on this type.
	 *
	 * Every US format shares this capability. Extra US formats (motorcycle, …)
	 * do not need a new type.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_plate_designs( $type ) {
		$caps = self::type_capabilities( $type );

		return ! empty( $caps['plate_designs'] );
	}

	/**
	 * Percent box the dashed text area lives in (plate minus frame and country band).
	 *
	 * Holder text sits on the bottom inscription strip.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function text_area_region( $format ) {
		$format = is_array( $format ) ? $format : array();
		$type   = isset( $format['type'] ) ? (string) $format['type'] : 'eu';
		$width  = isset( $format['width'] ) ? max( 1, (int) $format['width'] ) : 520;
		$height = isset( $format['height'] ) ? max( 1, (int) $format['height'] ) : 110;

		if ( self::is_holder( $type ) ) {
			return self::holder_strip_box( $type );
		}

		$region = array(
			'x'      => 0.0,
			'y'      => 0.0,
			'width'  => 100.0,
			'height' => 100.0,
		);

		if ( self::uses_frame( $format ) ) {
			$bw    = isset( $format['border_width'] ) ? (int) $format['border_width'] : 0;
			$clear = $bw > 0 ? $bw + self::TEXT_FRAME_GAP_MM : 0;
			$inset_x = ( $clear / $width ) * 100;
			$inset_y = ( $clear / $height ) * 100;
			$region  = array(
				'x'      => round( $inset_x, 2 ),
				'y'      => round( $inset_y, 2 ),
				'width'  => round( 100 - ( $inset_x * 2 ), 2 ),
				'height' => round( 100 - ( $inset_y * 2 ), 2 ),
			);
		}

		if ( self::uses_country_band( $type ) ) {
			$side = ( isset( $format['band_side'] ) && 'right' === $format['band_side'] ) ? 'right' : 'left';
			$band = isset( $format['band_box'] ) && is_array( $format['band_box'] )
				? self::sanitize_band_box( $format['band_box'], $width, $height, $side, $type )
				: self::default_band_box( $width, $height, $side, $type );
			$band_height = isset( $band['height'] ) ? (float) $band['height'] : 100.0;

			if ( $band_height >= 80 ) {
				$region = self::subtract_band_from_region( $region, $band );
				$gap    = ( self::TEXT_FRAME_GAP_MM / $width ) * 100;
				$mid    = ( isset( $band['x'] ) ? (float) $band['x'] : 0.0 ) + ( ( isset( $band['width'] ) ? (float) $band['width'] : 0.0 ) / 2 );

				if ( $mid < 50 ) {
					$region['x']     = round( (float) $region['x'] + $gap, 2 );
					$region['width'] = round( max( 5.0, (float) $region['width'] - $gap ), 2 );
				} else {
					$region['width'] = round( max( 5.0, (float) $region['width'] - $gap ), 2 );
				}
			}
		}

		if ( $region['width'] < 5 ) {
			$region['width'] = 5.0;
		}

		if ( $region['height'] < 5 ) {
			$region['height'] = 5.0;
		}

		return $region;
	}

	/**
	 * Cut a left or right country band out of a percent region.
	 *
	 * @param array{x: float, y: float, width: float, height: float} $region Remaining plate.
	 * @param array{x: float, y: float, width: float, height: float} $band   Country band.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function subtract_band_from_region( $region, $band ) {
		$region = is_array( $region ) ? $region : array();
		$band   = is_array( $band ) ? $band : array();

		$rx    = isset( $region['x'] ) ? (float) $region['x'] : 0.0;
		$ry    = isset( $region['y'] ) ? (float) $region['y'] : 0.0;
		$rw    = isset( $region['width'] ) ? (float) $region['width'] : 100.0;
		$rh    = isset( $region['height'] ) ? (float) $region['height'] : 100.0;
		$bx    = isset( $band['x'] ) ? (float) $band['x'] : 0.0;
		$bw    = isset( $band['width'] ) ? (float) $band['width'] : 0.0;
		$mid   = $bx + ( $bw / 2 );
		$right = $rx + $rw;

		if ( $mid < 50 ) {
			$x = max( $rx, $bx + $bw );

			return array(
				'x'      => round( $x, 2 ),
				'y'      => $ry,
				'width'  => round( max( 5.0, $right - $x ), 2 ),
				'height' => $rh,
			);
		}

		return array(
			'x'      => $rx,
			'y'      => $ry,
			'width'  => round( max( 5.0, $bx - $rx ), 2 ),
			'height' => $rh,
		);
	}

	/**
	 * Move a text box to the center of a percent region without changing its size.
	 *
	 * @param mixed                $box    Text box.
	 * @param array<string, float> $region Containing area.
	 * @param string               $type   Format type.
	 * @param array<string, mixed> $format Format row.
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	public static function center_text_box( $box, $region, $type = 'eu', $format = array() ) {
		$box    = self::sanitize_text_box( $box, $type, is_array( $format ) ? $format : array() );
		$region = is_array( $region ) ? $region : array();
		$rx     = isset( $region['x'] ) ? (float) $region['x'] : 0.0;
		$ry     = isset( $region['y'] ) ? (float) $region['y'] : 0.0;
		$rw     = isset( $region['width'] ) ? (float) $region['width'] : 100.0;
		$rh     = isset( $region['height'] ) ? (float) $region['height'] : 100.0;

		$x = $rx + ( ( $rw - $box['width'] ) / 2 );
		$y = $ry + ( ( $rh - $box['height'] ) / 2 );

		$min_x = $rx;
		$max_x = $rx + $rw - $box['width'];
		$min_y = $ry;
		$max_y = $ry + $rh - $box['height'];

		if ( $max_x < $min_x ) {
			$x = $rx;
		} else {
			$x = max( $min_x, min( $max_x, $x ) );
		}

		if ( $max_y < $min_y ) {
			$y = $ry;
		} else {
			$y = max( $min_y, min( $max_y, $y ) );
		}

		$box['x'] = round( $x, 1 );
		$box['y'] = round( $y, 1 );

		return $box;
	}

	/**
	 * Formats of one type.
	 *
	 * @param string $type Format type.
	 * @return array<int, array<string, mixed>>
	 */
	public static function of_type( $type ) {
		$out = array();

		foreach ( self::all() as $format ) {
			if ( isset( $format['type'] ) && $format['type'] === $type ) {
				$out[] = $format;
			}
		}

		return $out;
	}

	/**
	 * Color parts this format type can actually paint.
	 *
	 * Which of these the shopper may change is the palettes saved on the format.
	 *
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function color_field_capabilities( $type ) {
		switch ( $type ) {
			case 'us':
				return array( 'text' );
			case 'holder':
				return array( 'holder', 'holder_text', 'holder_strip' );
			case 'holder_moto':
			case 'holder_d':
				return array( 'holder_text', 'holder_strip' );
			case 'color':
				return array( 'text', 'border', 'background', 'color_text', 'color_border', 'color_background' );
			case 'eu':
			case 'moto':
			case 'moto_240':
			case 'moto_plain':
			case 'moto_plain_240':
			case 'eu_plain':
			case 'suv':
			case 'suv_eu':
			case 'custom':
				return array( 'text', 'border', 'background' );
			default:
				return array();
		}
	}

	/**
	 * Colors shown for one shop control.
	 *
	 * A holder reads only its body, inscription, and strip palettes. Color plates merge an extra palette into the standard row.
	 *
	 * @param string                                           $type         Format type.
	 * @param string                                           $slot         text|border|background|holder.
	 * @param array<int, string>                               $color_fields Enabled purpose slugs.
	 * @param array<string, array<int, array<string, mixed>>>  $palettes     Colors by purpose.
	 * @return array<int, array<string, mixed>>
	 */
	public static function shop_swatch_colors( $type, $slot, $color_fields, $palettes ) {
		$keys  = array( $slot );
		$merge = false;

		if ( self::is_photo_holder( $type ) && in_array( $slot, array( 'text', 'background' ), true ) ) {
			$keys  = array( 'holder_strip', 'holder_text' );
			$merge = true;
		} elseif ( 'holder' === $type && 'text' === $slot ) {
			$keys = array( 'holder_text' );
		} elseif ( 'holder' === $type && 'background' === $slot ) {
			$keys = array( 'holder_strip' );
		} elseif ( 'color' === $type ) {
			$extra = array(
				'text'       => 'color_text',
				'border'     => 'color_border',
				'background' => 'color_background',
			);

			if ( isset( $extra[ $slot ] ) ) {
				$keys  = array( $slot, $extra[ $slot ] );
				$merge = true;
			}
		}

		if ( ! is_array( $color_fields ) ) {
			$color_fields = array();
		}

		if ( ! is_array( $palettes ) ) {
			$palettes = array();
		}

		$colors = array();
		$seen   = array();
		$dedupe = self::is_photo_holder( $type );

		foreach ( $keys as $key ) {
			if ( ! in_array( $key, $color_fields, true ) ) {
				continue;
			}

			$rows = isset( $palettes[ $key ] ) && is_array( $palettes[ $key ] ) ? $palettes[ $key ] : array();

			if ( empty( $rows ) ) {
				continue;
			}

			if ( ! $merge ) {
				return $rows;
			}

			foreach ( $rows as $row ) {
				if ( $dedupe ) {
					$hex = isset( $row['hex'] ) ? strtoupper( (string) $row['hex'] ) : '';

					if ( '' !== $hex && isset( $seen[ $hex ] ) ) {
						continue;
					}

					if ( '' !== $hex ) {
						$seen[ $hex ] = true;
					}
				}

				$colors[] = $row;
			}
		}

		return $colors;
	}

	/**
	 * Suggested shopper color fields for a new product of this type.
	 *
	 * Admin can enable any capability, including plate fill on EU.
	 *
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function default_color_fields( $type ) {
		switch ( $type ) {
			case 'custom':
			case 'color':
			case 'eu_plain':
				return array( 'text', 'border', 'background' );
			case 'us':
				return array( 'text' );
			case 'holder':
				return array( 'holder', 'holder_text', 'holder_strip' );
			case 'holder_moto':
			case 'holder_d':
				return array( 'holder_text', 'holder_strip' );
			case 'eu':
			case 'moto':
			case 'moto_240':
			case 'moto_plain':
			case 'moto_plain_240':
			case 'suv':
			case 'suv_eu':
				return array( 'text', 'border' );
			default:
				return array();
		}
	}

	/**
	 * Keep only paintable fields for this type.
	 *
	 * @param mixed  $raw  Posted or stored field slugs.
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function sanitize_color_fields( $raw, $type ) {
		$allowed = self::color_field_capabilities( $type );
		$clean   = array();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		foreach ( $raw as $field ) {
			$field = sanitize_key( (string) $field );

			if ( in_array( $field, $allowed, true ) ) {
				$clean[] = $field;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Add a newly created font to every format that follows the full library.
	 *
	 * @param array<int, array<string, mixed>> $formats Format rows.
	 * @param string                            $font_id New font ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function grant_font_to_following_formats( $formats, $font_id ) {
		if ( ! is_array( $formats ) ) {
			return array();
		}

		$font_id = sanitize_text_field( (string) $font_id );

		if ( '' === $font_id ) {
			return $formats;
		}

		foreach ( $formats as $index => $format ) {
			if ( ! is_array( $format ) || empty( $format['font_ids_all'] ) ) {
				continue;
			}

			$ids   = isset( $format['font_ids'] ) && is_array( $format['font_ids'] ) ? $format['font_ids'] : array();
			$ids[] = $font_id;
			$formats[ $index ]['font_ids'] = array_values( array_unique( array_map( 'strval', $ids ) ) );
		}

		return $formats;
	}

	/**
	 * Every font ID currently in the library.
	 *
	 * @return array<int, string>
	 */
	private static function all_font_ids() {
		$settings = APD_Plugin::get_settings();
		$fonts    = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();
		$ids      = array();

		foreach ( $fonts as $font ) {
			if ( isset( $font['id'] ) && '' !== (string) $font['id'] ) {
				$ids[] = (string) $font['id'];
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Keep font IDs that exist in the library.
	 *
	 * @param mixed $raw Raw IDs.
	 * @return array<int, string>
	 */
	private static function sanitize_font_ids( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$known = array();
		$settings = APD_Plugin::get_settings();
		$fonts    = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();

		foreach ( $fonts as $font ) {
			if ( isset( $font['id'] ) ) {
				$known[ (string) $font['id'] ] = true;
			}
		}

		$clean = array();

		foreach ( $raw as $id ) {
			$id = sanitize_text_field( (string) $id );

			if ( '' !== $id && isset( $known[ $id ] ) ) {
				$clean[] = $id;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Fallback text box as percents of the plate canvas.
	 *
	 * EU defaults sit in the white number area beside a typical country band.
	 * USA defaults match the original 82% × 42% hole. Holder defaults sit on
	 * the bottom strip. Custom uses a full-plate inset.
	 *
	 * @param string               $type   Format type.
	 * @param array<string, mixed> $format Format row (band_ratio / band_side).
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	public static function default_text_box( $type = 'eu', $format = array() ) {
		$box = self::layout_text_box( $type, $format );

		if ( self::uses_two_rows( $type ) ) {
			$box['letter_align'] = 'justify';
			$box['number_align'] = 'justify';
		}

		return $box;
	}

	/**
	 * Position of the text area before two-row alignment is attached.
	 *
	 * @param string               $type   Format type.
	 * @param array<string, mixed> $format Format row.
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	private static function layout_text_box( $type = 'eu', $format = array() ) {
		$format = is_array( $format ) ? $format : array();

		switch ( $type ) {
			case 'us':
				return array(
					'x'      => 9.0,
					'y'      => 34.0,
					'width'  => 82.0,
					'height' => 42.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'suv':
				return array(
					'x'      => 8.0,
					'y'      => 8.0,
					'width'  => 84.0,
					'height' => 84.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'suv_eu':
				return array(
					'x'      => 4.0,
					'y'      => 6.0,
					'width'  => 92.0,
					'height' => 88.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'holder':
			case 'holder_moto':
			case 'holder_d':
				$strip = self::holder_strip_box( $type );

				return array(
					'x'      => $strip['x'],
					'y'      => $strip['y'],
					'width'  => $strip['width'],
					'height' => $strip['height'],
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'custom':
				return array(
					'x'      => 6.0,
					'y'      => 18.0,
					'width'  => 88.0,
					'height' => 64.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'color':
			case 'eu_plain':
			case 'moto_plain':
			case 'moto_plain_240':
				return array(
					'x'      => 6.0,
					'y'      => 12.0,
					'width'  => 88.0,
					'height' => 76.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			default:
				$width  = isset( $format['width'] ) ? (int) $format['width'] : 520;
				$height = isset( $format['height'] ) ? (int) $format['height'] : 110;
				$ratio  = isset( $format['band_ratio'] ) && is_numeric( $format['band_ratio'] ) ? (float) $format['band_ratio'] : self::eu_band_ratio( $width, $height );
				$side   = ( isset( $format['band_side'] ) && 'right' === $format['band_side'] ) ? 'right' : 'left';
				$pad    = 3.0;
				$band   = round( $ratio * 100, 1 );

				if ( ! self::uses_country_band( $type ) ) {
					return array(
						'x'      => 6.0,
						'y'      => 11.0,
						'width'  => 88.0,
						'height' => 78.0,
						'align'  => 'center',
						'valign' => 'middle',
					);
				}

				if ( $band < 5 ) {
					$band = round( self::eu_band_ratio( $width, $height ) * 100, 1 );
				}

				if ( 'right' === $side ) {
					$x     = $pad;
					$width = round( 100 - $band - ( $pad * 2 ), 1 );
				} else {
					$x     = round( $band + $pad, 1 );
					$width = round( 100 - $x - $pad, 1 );
				}

				if ( $width < 5 ) {
					$width = 5.0;
				}

				return array(
					'x'      => $x,
					'y'      => 11.0,
					'width'  => $width,
					'height' => 78.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
		}
	}

	/**
	 * Clamp a text box. Values are percents of the plate canvas.
	 *
	 * @param mixed                $raw    Raw values.
	 * @param string               $type   Format type for defaults.
	 * @param array<string, mixed> $format Format row for type defaults.
	 * @return array{x: float, y: float, width: float, height: float, align: string, valign: string}
	 */
	public static function sanitize_text_box( $raw, $type = 'eu', $format = array() ) {
		$defaults = self::default_text_box( $type, is_array( $format ) ? $format : array() );
		$raw      = is_array( $raw ) ? $raw : array();
		$holder   = self::is_holder( $type );
		$strip    = $holder ? self::holder_strip_box( $type ) : array();
		$min_w    = $holder ? max( 0.1, round( (float) $strip['width'] * 0.05, 1 ) ) : 5.0;
		$min_h    = $holder ? max( 0.1, round( (float) $strip['height'] * 0.05, 1 ) ) : 5.0;

		$x      = self::clamp_percent( isset( $raw['x'] ) ? $raw['x'] : $defaults['x'], 0, 95 );
		$y      = self::clamp_percent( isset( $raw['y'] ) ? $raw['y'] : $defaults['y'], 0, 95 );
		$width  = self::clamp_percent( isset( $raw['width'] ) ? $raw['width'] : $defaults['width'], $min_w, 100 );
		$height = self::clamp_percent( isset( $raw['height'] ) ? $raw['height'] : $defaults['height'], $min_h, 100 );

		if ( $x + $width > 100 ) {
			$width = round( 100 - $x, 1 );
		}

		if ( $y + $height > 100 ) {
			$height = round( 100 - $y, 1 );
		}

		if ( ! $holder && $width < 5 ) {
			$width = 5.0;
			$x     = min( $x, 95.0 );
		}

		if ( ! $holder && $height < 5 ) {
			$height = 5.0;
			$y      = min( $y, 95.0 );
		}

		$align  = isset( $raw['align'] ) ? sanitize_key( (string) $raw['align'] ) : $defaults['align'];
		$valign = isset( $raw['valign'] ) ? sanitize_key( (string) $raw['valign'] ) : $defaults['valign'];

		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'center';
		}

		if ( ! in_array( $valign, array( 'top', 'middle', 'bottom' ), true ) ) {
			$valign = 'middle';
		}

		$box = array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $width,
			'height' => $height,
			'align'  => $align,
			'valign' => $valign,
		);

		if ( self::uses_two_rows( $type ) ) {
			$allowed = array( 'left', 'center', 'right', 'justify' );
			$letter  = isset( $raw['letter_align'] ) ? sanitize_key( (string) $raw['letter_align'] ) : ( isset( $defaults['letter_align'] ) ? $defaults['letter_align'] : 'justify' );
			$number  = isset( $raw['number_align'] ) ? sanitize_key( (string) $raw['number_align'] ) : ( isset( $defaults['number_align'] ) ? $defaults['number_align'] : 'justify' );

			if ( ! in_array( $letter, $allowed, true ) ) {
				$letter = 'justify';
			}

			if ( ! in_array( $number, $allowed, true ) ) {
				$number = 'justify';
			}

			$box['letter_align'] = $letter;
			$box['number_align'] = $number;
		}

		if ( self::is_holder( $type ) ) {
			$box = self::clamp_box_to_region( $box, self::holder_strip_box( $type ) );
		} else {
			$layout         = is_array( $format ) ? $format : array();
			$layout['type'] = $type;
			$plate_w        = isset( $layout['width'] ) ? (int) $layout['width'] : 0;
			$plate_h        = isset( $layout['height'] ) ? (int) $layout['height'] : 0;

			if ( $plate_w > 0 && $plate_h > 0 && self::uses_frame( $layout ) ) {
				$box = self::clamp_box_to_region( $box, self::text_area_region( $layout ) );
			}
		}

		return $box;
	}

	/**
	 * Split one USA text box into a left and right box with a gap between them.
	 *
	 * Both boxes keep the original Y, height, and vertical alignment.
	 *
	 * @param mixed $left_raw Left box or a single box to divide.
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
	 */
	public static function seed_side_boxes( $left_raw ) {
		$left    = self::sanitize_text_box( $left_raw, 'us' );
		$gap     = 8.0;
		$usable  = (float) $left['width'] - $gap;
		$left_w  = round( max( 5, $usable * 0.45 ), 1 );
		$right_w = round( max( 5, $usable - $left_w ), 1 );
		$right_x = round( (float) $left['x'] + $left_w + $gap, 1 );
		$left['width'] = $left_w;
		$right         = $left;
		$right['x']    = $right_x;
		$right['width'] = $right_w;
		$right['align'] = 'center';

		return self::link_side_boxes( $left, $right );
	}

	/**
	 * Keep two USA boxes on one line without letting them overlap.
	 *
	 * The right box copies Y, height, and vertical alignment from the left.
	 * A wider left box stops at the right box. The gap is whatever space remains.
	 *
	 * @param mixed $left_raw  Left box.
	 * @param mixed $right_raw Right box.
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
	 */
	public static function link_side_boxes( $left_raw, $right_raw ) {
		$left  = self::sanitize_text_box( $left_raw, 'us' );
		$right = self::sanitize_text_box( $right_raw, 'us' );
		$right['y']      = $left['y'];
		$right['height'] = $left['height'];
		$right['valign'] = $left['valign'];
		$left_end        = round( (float) $left['x'] + (float) $left['width'], 1 );

		if ( (float) $right['x'] < $left_end ) {
			$fit = round( (float) $right['x'] - (float) $left['x'], 1 );

			if ( $fit >= 5 ) {
				$left['width'] = $fit;
			} else {
				$right['x'] = round( (float) $left['x'] + (float) $left['width'], 1 );
			}
		}

		if ( (float) $right['x'] > 95 ) {
			$right['x'] = 95.0;
		}

		if ( (float) $right['x'] + (float) $right['width'] > 100 ) {
			$right['width'] = round( 100 - (float) $right['x'], 1 );
		}

		if ( (float) $right['width'] < 5 ) {
			$right['width'] = 5.0;
			$right['x']     = min( 95.0, (float) $right['x'] );
		}

		return array( $left, $right );
	}

	/**
	 * Turn admin percentages of the white strip into percentages of the holder photo.
	 *
	 * @param array<string, mixed> $box  Strip-relative box.
	 * @param string               $type Holder type.
	 * @return array<string, mixed>
	 */
	public static function strip_percent_box_to_canvas( $box, $type = 'holder' ) {
		$box   = is_array( $box ) ? $box : array();
		$strip = self::holder_strip_box( $type );
		$sx    = isset( $box['x'] ) ? (float) $box['x'] : 0.0;
		$sy    = isset( $box['y'] ) ? (float) $box['y'] : 0.0;
		$sw    = isset( $box['width'] ) ? (float) $box['width'] : 100.0;
		$sh    = isset( $box['height'] ) ? (float) $box['height'] : 100.0;

		$box['x']      = round( (float) $strip['x'] + ( (float) $strip['width'] * $sx / 100 ), 2 );
		$box['y']      = round( (float) $strip['y'] + ( (float) $strip['height'] * $sy / 100 ), 2 );
		$box['width']  = round( (float) $strip['width'] * $sw / 100, 2 );
		$box['height'] = round( (float) $strip['height'] * $sh / 100, 2 );

		return $box;
	}

	/**
	 * Slogan strip as percents of the holder photo.
	 *
	 * The car holder keeps the gray-mask slot. Motorcycle and type D use the white bar measured on their photos.
	 *
	 * @param string $type Format type.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function holder_strip_box( $type = 'holder' ) {
		if ( 'holder_moto' === $type ) {
			return array(
				'x'      => 10.16,
				'y'      => 84.18,
				'width'  => 79.3,
				'height' => 12.11,
			);
		}

		if ( 'holder_d' === $type ) {
			return array(
				'x'      => 2.0,
				'y'      => 85.28,
				'width'  => 96.0,
				'height' => 11.32,
			);
		}

		return array(
			'x'      => 2.73,
			'y'      => 77.3,
			'width'  => 94.34,
			'height' => 6.26,
		);
	}

	/**
	 * Corner radius of the painted strip, as a fraction of the strip height.
	 *
	 * @param string $type Format type.
	 * @return float
	 */
	public static function holder_strip_radius( $type = 'holder' ) {
		if ( 'holder_d' === $type ) {
			return 0.12;
		}

		if ( 'holder_moto' === $type ) {
			return 0.08;
		}

		return 0.25;
	}

	/**
	 * Bundled holder picture. The car type is the gray mask; the other two are product photos.
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function bundled_holder_image_url( $type = 'holder' ) {
		$files = array(
			'holder'      => 'assets/images/gray-plate-holder-hole.png',
			'holder_moto' => 'assets/images/holder-moto.png',
			'holder_d'    => 'assets/images/holder-type-d.png',
		);
		$relative = isset( $files[ $type ] ) ? $files[ $type ] : '';

		if ( '' === $relative || ! is_readable( APD_PLUGIN_DIR . $relative ) ) {
			return '';
		}

		return APD_PLUGIN_URL . $relative;
	}

	/**
	 * Keep a text box inside a percent region.
	 *
	 * @param array{x: float, y: float, width: float, height: float} $box    Text box.
	 * @param array{x: float, y: float, width: float, height: float} $region Allowed region.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	private static function clamp_box_to_region( $box, $region ) {
		$box['width']  = min( (float) $box['width'], (float) $region['width'] );
		$box['height'] = min( (float) $box['height'], (float) $region['height'] );
		$max_x         = (float) $region['x'] + (float) $region['width'] - (float) $box['width'];
		$max_y         = (float) $region['y'] + (float) $region['height'] - (float) $box['height'];
		$box['x']      = min( max( (float) $box['x'], (float) $region['x'] ), $max_x );
		$box['y']      = min( max( (float) $box['y'], (float) $region['y'] ), $max_y );
		$box['x']      = round( $box['x'], 1 );
		$box['y']      = round( $box['y'], 1 );
		$box['width']  = round( $box['width'], 1 );
		$box['height'] = round( $box['height'], 1 );

		return $box;
	}

	/**
	 * Clamp a percent to one decimal.
	 *
	 * @param mixed $value Raw number.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return float
	 */
	private static function clamp_percent( $value, $min, $max ) {
		$number = is_numeric( $value ) ? (float) $value : $min;

		if ( $number < $min ) {
			$number = $min;
		}

		if ( $number > $max ) {
			$number = $max;
		}

		return round( $number, 1 );
	}

	/**
	 * Name shown to shoppers. A note is appended only when the font has one.
	 *
	 * @param array<string, mixed> $font Font row.
	 * @return string
	 */
	public static function font_shop_label( $font ) {
		$family = isset( $font['family'] ) ? trim( (string) $font['family'] ) : '';
		$note   = isset( $font['note'] ) ? trim( (string) $font['note'] ) : '';

		if ( '' === $note ) {
			return $family;
		}

		if ( '' === $family ) {
			return $note;
		}

		return $family . ' — ' . $note;
	}

	/**
	 * Font selected when the product page opens.
	 *
	 * Every plate type skips a German family when another font is offered.
	 * USA plates still prefer a family whose name starts with "USA".
	 *
	 * @param array<int, array<string, mixed>> $fonts Fonts offered on this format.
	 * @param string                            $type  Format type.
	 * @return string
	 */
	public static function default_font_id( $fonts, $type ) {
		if ( ! is_array( $fonts ) || empty( $fonts ) ) {
			return '';
		}

		$fallback = '';
		$usa      = '';

		foreach ( $fonts as $font ) {
			if ( empty( $font['id'] ) ) {
				continue;
			}

			$id     = (string) $font['id'];
			$family = isset( $font['family'] ) ? trim( (string) $font['family'] ) : '';

			if ( '' === $fallback && ! self::is_german_font_family( $family ) ) {
				$fallback = $id;
			}

			if ( 'us' === (string) $type && '' === $usa && '' !== $family && 0 === stripos( $family, 'USA' ) ) {
				$usa = $id;
			}
		}

		if ( 'us' === (string) $type && '' !== $usa ) {
			return $usa;
		}

		if ( '' !== $fallback ) {
			return $fallback;
		}

		return isset( $fonts[0]['id'] ) ? (string) $fonts[0]['id'] : '';
	}

	/**
	 * German plate face. It must not win the shop default when another font exists.
	 *
	 * @param string $family Font family name.
	 * @return bool
	 */
	private static function is_german_font_family( $family ) {
		$family = trim( (string) $family );

		if ( '' === $family ) {
			return false;
		}

		if ( 0 === stripos( $family, 'German' ) ) {
			return true;
		}

		return false !== stripos( $family, 'немски' );
	}

	/**
	 * JSON-safe payload for the product-page configurator.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return array<string, mixed>
	 */
	public static function frontend_payload( $format ) {
		$settings = APD_Plugin::get_settings();
		$library  = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();
		$selected = ! empty( $format['font_ids_all'] )
			? array()
			: ( isset( $format['font_ids'] ) && is_array( $format['font_ids'] ) ? $format['font_ids'] : array() );
		$fonts    = array();

		foreach ( $library as $font ) {
			if ( ! isset( $font['id'] ) ) {
				continue;
			}

			if ( ! empty( $selected ) && ! in_array( $font['id'], $selected, true ) ) {
				continue;
			}

			$url = isset( $font['attachment_id'] ) ? wp_get_attachment_url( absint( $font['attachment_id'] ) ) : '';

			if ( ! $url ) {
				continue;
			}

			$fonts[] = array(
				'id'     => $font['id'],
				'family' => isset( $font['family'] ) ? (string) $font['family'] : '',
				'note'   => isset( $font['note'] ) ? (string) $font['note'] : '',
				'weight' => isset( $font['weight'] ) ? (int) $font['weight'] : 400,
				'style'  => isset( $font['style'] ) ? (string) $font['style'] : 'normal',
				'url'    => $url,
			);
		}

		$type      = isset( $format['type'] ) ? (string) $format['type'] : 'eu';
		$max_chars = isset( $format['max_chars'] ) ? (int) $format['max_chars'] : self::default_max_chars( $type );
		$row_limits = self::suv_row_limits( $format );

		if ( self::uses_two_rows( $type ) ) {
			$max_chars = $row_limits[0] + $row_limits[1];
		} elseif ( self::is_holder( $type ) ) {
			$max_chars = ! empty( $format['holder_max_set'] ) ? (int) $format['max_chars'] : self::holder_text_limit();
		}
		$image_id  = ( self::uses_base_image( $type ) && isset( $format['base_image_id'] ) ) ? absint( $format['base_image_id'] ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

		if ( self::is_holder( $type ) && ! $image_url ) {
			$image_url = self::bundled_holder_image_url( $type );
		}

		$presets = array();

		if ( self::uses_country_band( $type ) ) {
			foreach ( APD_Presets::active_for_format( $type ) as $preset ) {
				$preset_image = isset( $preset['image_id'] ) ? wp_get_attachment_image_url( absint( $preset['image_id'] ), 'full' ) : '';
				$presets[]    = array(
					'id'           => $preset['id'],
					'name'         => $preset['name'],
					'country_code' => $preset['country_code'],
					'image_url'    => $preset_image ? $preset_image : '',
					'side'         => $preset['side'],
					'band_ratio'   => (float) $preset['band_ratio'],
				);
			}
		}

		$designs = array();

		if ( self::uses_plate_designs( $type ) && class_exists( 'APD_Designs' ) ) {
			foreach ( APD_Designs::active_for_format( $format ) as $design ) {
				$design_image = isset( $design['image_id'] ) ? wp_get_attachment_image_url( absint( $design['image_id'] ), 'full' ) : '';
				$design_split = ! empty( $design['split_text'] );
				$design_box   = APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() );
				$design_right = array();
				$design_sides = array( 0, 0 );

				if ( $design_split ) {
					$right_raw = isset( $design['text_box_right'] ) && is_array( $design['text_box_right'] ) ? $design['text_box_right'] : array();
					$pair      = ( isset( $right_raw['x'] ) || isset( $right_raw['width'] ) )
						? self::link_side_boxes( $design_box, $right_raw )
						: self::seed_side_boxes( $design_box );
					$design_box   = $pair[0];
					$design_right = $pair[1];
					$design_sides = self::us_side_limits( $design );
				}

				$designs[] = array(
					'id'              => $design['id'],
					'name'            => $design['name'],
					'code'            => isset( $design['code'] ) ? (string) $design['code'] : '',
					'image_url'       => $design_image ? $design_image : '',
					'text_box'        => $design_box,
					'split_text'      => $design_split,
					'text_box_right'  => $design_right,
					'side_max_chars'  => $design_sides,
				);
			}
		}

		$split_text = 'us' === $type && ! empty( $format['split_text'] );
		$text_left  = self::sanitize_text_box( isset( $format['text_box'] ) ? $format['text_box'] : array(), $type, $format );
		$text_right = array();
		$side_max   = array( 0, 0 );

		if ( $split_text ) {
			$right_raw = isset( $format['text_box_right'] ) && is_array( $format['text_box_right'] ) ? $format['text_box_right'] : array();
			$pair      = ( isset( $right_raw['x'] ) || isset( $right_raw['width'] ) )
				? self::link_side_boxes( $text_left, $right_raw )
				: self::seed_side_boxes( $text_left );
			$text_left  = $pair[0];
			$text_right = $pair[1];
			$side_max   = self::us_side_limits( $format );
		}

		$no_frame = self::uses_painted_plate( $type ) && ! empty( $format['no_frame'] );
		$band_box = array();

		if ( self::uses_country_band( $type ) ) {
			$stored_box = isset( $format['band_box'] ) && is_array( $format['band_box'] ) ? $format['band_box'] : array();
			$has_box    = isset( $stored_box['width'] ) || isset( $stored_box['x'] );
			$band_box   = self::sanitize_band_box(
				$has_box ? $stored_box : array(),
				(int) $format['width'],
				(int) $format['height'],
				isset( $format['band_side'] ) ? (string) $format['band_side'] : 'left',
				$type
			);
		}

		return array(
			'id'               => $format['id'],
			'name'             => $format['name'],
			'type'             => $type,
			'width'            => (int) $format['width'],
			'height'           => (int) $format['height'],
			'border_width'     => isset( $format['border_width'] ) ? (int) $format['border_width'] : 0,
			'border_color'     => $format['border_color'],
			'no_frame'         => $no_frame,
			'frame_choice'     => self::offers_frame_choice( $type ),
			'band_ratio'       => (float) $format['band_ratio'],
			'eu_band_ratio'    => self::eu_band_ratio( (int) $format['width'], (int) $format['height'] ),
			'band_side'        => $format['band_side'],
			'band_box'         => $band_box,
			'base_image_url'   => $image_url ? $image_url : '',
			'strip_box'        => self::is_holder( $type ) ? self::holder_strip_box( $type ) : array(),
			'strip_radius'     => self::is_holder( $type ) ? self::holder_strip_radius( $type ) : 0,
			'max_chars'        => $max_chars,
			'row_max_chars'    => $row_limits,
			'split_text'       => $split_text,
			'text_box_right'   => $text_right,
			'side_max_chars'   => $side_max,
			'price_adjustment' => (float) $format['price_adjustment'],
			'text_box'         => $text_left,
			'fonts'            => $fonts,
			'presets'          => $presets,
			'designs'          => $designs,
			'capabilities'     => self::type_capabilities( $type ),
			'allow_empty'      => true,
			'multiline'        => self::is_street_plate( $type ),
			'wrap_text'        => self::is_street_plate( $type ) && ! empty( $format['wrap_text'] ),
			'max_lines'        => self::wrap_line_limit(),
		);
	}
}
