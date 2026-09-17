<?php
/**
 * CRUD for plate formats (EU parametric, US base-image, custom street).
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
	 * Real EU plate (and euroband) height in millimetres.
	 */
	public const EU_BAND_HEIGHT_MM = 110;

	/**
	 * Display cap for admin studio and shop canvas (CSS pixels).
	 */
	public const CANVAS_DISPLAY_MAX_PX = 500;

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

		$size   = self::default_size( $type );
		$width  = self::int_in_range( isset( $raw['width'] ) ? $raw['width'] : $size['width'], 100, 2000, $size['width'] );
		$height = self::int_in_range( isset( $raw['height'] ) ? $raw['height'] : $size['height'], 40, 1200, $size['height'] );
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
				$band_box = self::sanitize_band_box( $posted_box, $width, $height, $band_side );
			} elseif ( isset( $raw['band_ratio'] ) && is_numeric( $raw['band_ratio'] ) ) {
				$legacy_ratio = self::float_in_range( $raw['band_ratio'], 0.04, 0.4, $eu_ratio );
				$band_box     = self::sanitize_band_box(
					self::band_box_from_ratio( $legacy_ratio, $band_side ),
					$width,
					$height,
					$band_side
				);
			} else {
				$band_box = self::sanitize_band_box( array(), $width, $height, $band_side );
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
			$image_check = APD_Security::validate_attachment(
				$base_image_id,
				array(
					'mimes'     => APD_Security::RASTER_MIMES,
					'allow_svg' => false,
					'optional'  => false,
				)
			);

			if ( is_wp_error( $image_check ) ) {
				return new WP_Error(
					'apd_format_base_image',
					'suv' === $type
						? __( 'SUV formats require a photo of the full plate.', 'auto-plate-designer' )
						: __( 'Holder formats require a photo of the plate holder.', 'auto-plate-designer' )
				);
			}
		} else {
			$base_image_id = 0;
		}

		$max_default = self::default_max_chars( $type );
		$max_chars   = self::int_in_range( isset( $raw['max_chars'] ) ? $raw['max_chars'] : $max_default, 1, APD_Security::ABSOLUTE_MAX_CHARS, $max_default );
		$font_ids    = self::sanitize_font_ids( isset( $raw['font_ids'] ) ? $raw['font_ids'] : array() );
		$normalized  = array(
			'type'       => $type,
			'band_ratio' => $band_ratio,
			'band_side'  => $band_side,
		);

		return array(
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
			'text_box'         => self::sanitize_text_box( isset( $raw['text_box'] ) ? $raw['text_box'] : array(), $type, $normalized ),
			'max_chars'        => $max_chars,
			'font_ids'         => $font_ids,
			'price_adjustment' => round( $price, 2 ),
		);
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
				return 24;
			case 'custom':
				return 40;
			default:
				return 12;
		}
	}

	/**
	 * Human-readable type label.
	 *
	 * @param string $type Format type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'eu'     => __( 'EU plate', 'auto-plate-designer' ),
			'us'     => __( 'USA plate', 'auto-plate-designer' ),
			'moto'   => __( 'Motorcycle plate', 'auto-plate-designer' ),
			'suv'    => __( 'SUV / crossover plate', 'auto-plate-designer' ),
			'custom' => __( 'Street plate', 'auto-plate-designer' ),
			'color'  => __( 'Color plate', 'auto-plate-designer' ),
			'holder' => __( 'Plate holder', 'auto-plate-designer' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : strtoupper( $type );
	}

	/**
	 * Whether shoppers pick an EU country band on this format.
	 *
	 * US plates use the design library, so country presets never apply.
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_country_band( $type ) {
		return in_array( $type, array( 'eu', 'moto' ), true );
	}

	/**
	 * Whether the plate is drawn as a filled rectangle with a border (not a photo).
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_painted_plate( $type ) {
		return in_array( $type, array( 'eu', 'moto', 'custom', 'color' ), true );
	}

	/**
	 * Whether this type stores a full product photo (holder or SUV graphic).
	 *
	 * @param string $type Format type.
	 * @return bool
	 */
	public static function uses_base_image( $type ) {
		return in_array( $type, array( 'holder', 'suv' ), true );
	}

	/**
	 * Default millimetre canvas for a type (EU 52×11, USA 30×15, moto 24×13, SUV type C 34×20).
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
				return array(
					'width'  => 240,
					'height' => 130,
				);
			case 'suv':
				return array(
					'width'  => 340,
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
	public static function default_band_box( $width = 520, $height = 110, $side = 'left' ) {
		return self::band_box_from_ratio( self::eu_band_ratio( $width, $height ), $side );
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
	public static function sanitize_band_box( $raw, $plate_width = 520, $plate_height = 110, $side = 'left' ) {
		$defaults = self::default_band_box( $plate_width, $plate_height, $side );
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
		return 'us' === $type;
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

		if ( 'holder' === $type ) {
			return array(
				'x'      => 0.0,
				'y'      => 82.0,
				'width'  => 100.0,
				'height' => 18.0,
			);
		}

		$region = array(
			'x'      => 0.0,
			'y'      => 0.0,
			'width'  => 100.0,
			'height' => 100.0,
		);

		if ( self::uses_frame( $format ) ) {
			$bw      = isset( $format['border_width'] ) ? (int) $format['border_width'] : 0;
			$inset_x = ( $bw / $width ) * 100;
			$inset_y = ( $bw / $height ) * 100;
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
				? self::sanitize_band_box( $format['band_box'], $width, $height, $side )
				: self::default_band_box( $width, $height, $side );
			$region = self::subtract_band_from_region( $region, $band );
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
	 * Policy (which of these the shopper may change) lives on the product.
	 *
	 * @param string $type Format type.
	 * @return array<int, string>
	 */
	public static function color_field_capabilities( $type ) {
		switch ( $type ) {
			case 'us':
			case 'suv':
				return array( 'text' );
			case 'holder':
				return array( 'text', 'background' );
			case 'eu':
			case 'moto':
			case 'custom':
			case 'color':
				return array( 'text', 'border', 'background' );
			default:
				return array();
		}
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
				return array( 'text', 'border', 'background' );
			case 'us':
			case 'suv':
				return array( 'text' );
			case 'holder':
				return array( 'text', 'background' );
			case 'eu':
			case 'moto':
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
		$format = is_array( $format ) ? $format : array();

		switch ( $type ) {
			case 'us':
			case 'suv':
				return array(
					'x'      => 9.0,
					'y'      => 34.0,
					'width'  => 82.0,
					'height' => 42.0,
					'align'  => 'center',
					'valign' => 'middle',
				);
			case 'holder':
				return array(
					'x'      => 5.0,
					'y'      => 85.0,
					'width'  => 90.0,
					'height' => 12.0,
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
					'y'      => 19.0,
					'width'  => $width,
					'height' => 62.0,
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

		$x      = self::clamp_percent( isset( $raw['x'] ) ? $raw['x'] : $defaults['x'], 0, 95 );
		$y      = self::clamp_percent( isset( $raw['y'] ) ? $raw['y'] : $defaults['y'], 0, 95 );
		$width  = self::clamp_percent( isset( $raw['width'] ) ? $raw['width'] : $defaults['width'], 5, 100 );
		$height = self::clamp_percent( isset( $raw['height'] ) ? $raw['height'] : $defaults['height'], 5, 100 );

		if ( $x + $width > 100 ) {
			$width = round( 100 - $x, 1 );
		}

		if ( $y + $height > 100 ) {
			$height = round( 100 - $y, 1 );
		}

		if ( $width < 5 ) {
			$width = 5.0;
			$x     = min( $x, 95.0 );
		}

		if ( $height < 5 ) {
			$height = 5.0;
			$y     = min( $y, 95.0 );
		}

		$align  = isset( $raw['align'] ) ? sanitize_key( (string) $raw['align'] ) : $defaults['align'];
		$valign = isset( $raw['valign'] ) ? sanitize_key( (string) $raw['valign'] ) : $defaults['valign'];

		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'center';
		}

		if ( ! in_array( $valign, array( 'top', 'middle', 'bottom' ), true ) ) {
			$valign = 'middle';
		}

		return array(
			'x'      => $x,
			'y'      => $y,
			'width'  => $width,
			'height' => $height,
			'align'  => $align,
			'valign' => $valign,
		);
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
	 * JSON-safe payload for the product-page configurator.
	 *
	 * @param array<string, mixed> $format Format row.
	 * @return array<string, mixed>
	 */
	public static function frontend_payload( $format ) {
		$settings = APD_Plugin::get_settings();
		$library  = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();
		$selected = isset( $format['font_ids'] ) && is_array( $format['font_ids'] ) ? $format['font_ids'] : array();
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
				'weight' => isset( $font['weight'] ) ? (int) $font['weight'] : 400,
				'style'  => isset( $font['style'] ) ? (string) $font['style'] : 'normal',
				'url'    => $url,
			);
		}

		$type      = isset( $format['type'] ) ? (string) $format['type'] : 'eu';
		$max_chars = isset( $format['max_chars'] ) ? (int) $format['max_chars'] : self::default_max_chars( $type );
		$image_id  = ( self::uses_base_image( $type ) && isset( $format['base_image_id'] ) ) ? absint( $format['base_image_id'] ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

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
				$designs[]    = array(
					'id'        => $design['id'],
					'name'      => $design['name'],
					'code'      => isset( $design['code'] ) ? (string) $design['code'] : '',
					'image_url' => $design_image ? $design_image : '',
					'text_box'  => APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() ),
				);
			}
		}

		$no_frame = self::uses_painted_plate( $type ) && ! empty( $format['no_frame'] );
		$band_box = array();

		if ( self::uses_country_band( $type ) ) {
			$stored_box = isset( $format['band_box'] ) && is_array( $format['band_box'] ) ? $format['band_box'] : array();
			$has_box    = isset( $stored_box['width'] ) || isset( $stored_box['x'] );
			$band_box   = self::sanitize_band_box(
				$has_box ? $stored_box : self::default_band_box(
					(int) $format['width'],
					(int) $format['height'],
					isset( $format['band_side'] ) ? (string) $format['band_side'] : 'left'
				),
				(int) $format['width'],
				(int) $format['height'],
				isset( $format['band_side'] ) ? (string) $format['band_side'] : 'left'
			);
		}

		return array(
			'id'               => $format['id'],
			'name'             => $format['name'],
			'type'             => $type,
			'width'            => (int) $format['width'],
			'height'           => (int) $format['height'],
			'border_width'     => $no_frame ? 0 : (int) $format['border_width'],
			'border_color'     => $format['border_color'],
			'no_frame'         => $no_frame,
			'band_ratio'       => (float) $format['band_ratio'],
			'eu_band_ratio'    => self::eu_band_ratio( (int) $format['width'], (int) $format['height'] ),
			'band_side'        => $format['band_side'],
			'band_box'         => $band_box,
			'base_image_url'   => $image_url ? $image_url : '',
			'max_chars'        => $max_chars,
			'price_adjustment' => (float) $format['price_adjustment'],
			'text_box'         => self::sanitize_text_box( isset( $format['text_box'] ) ? $format['text_box'] : array(), $type, $format ),
			'fonts'            => $fonts,
			'presets'          => $presets,
			'designs'          => $designs,
			'allow_empty'      => 'holder' === $type,
			'multiline'        => 'custom' === $type,
		);
	}
}
