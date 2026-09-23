<?php
/**
 * Centralized validation, sanitization, escaping, and upload checks.
 *
 * Every later stage must call these helpers instead of ad-hoc sanitization.
 * User-facing plate text is rejected when it fails the whitelist — it is never
 * silently rewritten into a "safe" value.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers for Auto Plate Designer.
 */
final class APD_Security {

	/**
	 * Hard cap on plate text length. Per-layout limits may be lower; never higher.
	 *
	 * @var int
	 */
	const ABSOLUTE_MAX_CHARS = 500;

	/**
	 * Default AJAX / form nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'apd_request';

	/**
	 * Admin settings capability.
	 *
	 * @var string
	 */
	const ADMIN_CAPABILITY = 'manage_woocommerce';

	/**
	 * Default max upload size for raster images (bytes).
	 *
	 * @var int
	 */
	const MAX_IMAGE_BYTES = 5242880;

	/**
	 * Default max upload size for SVG flag files (bytes).
	 *
	 * @var int
	 */
	const MAX_SVG_BYTES = 512000;

	/**
	 * EU country-band max width (px).
	 *
	 * @var int
	 */
	const MAX_EU_BAND_WIDTH = 1200;

	/**
	 * EU country-band max height (px).
	 *
	 * @var int
	 */
	const MAX_EU_BAND_HEIGHT = 400;

	/**
	 * US base-image max width (px).
	 *
	 * @var int
	 */
	const MAX_US_BASE_WIDTH = 2000;

	/**
	 * US base-image max height (px).
	 *
	 * @var int
	 */
	const MAX_US_BASE_HEIGHT = 1000;

	/**
	 * Allowed raster MIME types for admin image uploads.
	 *
	 * @var array<int, string>
	 */
	const RASTER_MIMES = array(
		'image/png',
		'image/jpeg',
		'image/webp',
	);

	/**
	 * SVG MIME type. Allowed only when the caller opts in (country-band flags).
	 *
	 * @var string
	 */
	const SVG_MIME = 'image/svg+xml';

	/**
	 * Admin character-class default (Latin, Latin Extended, Cyrillic block,
	 * digits, space, hyphen, period). Encoded as code points so this file
	 * stays ASCII-only.
	 *
	 * @var string
	 */
	const DEFAULT_ADMIN_CHAR_CLASS = 'A-Za-z0-9 \.\-\\x{00C0}-\\x{024F}\\x{0400}-\\x{04FF}';

	/**
	 * Singleton instance.
	 *
	 * @var APD_Security|null
	 */
	private static $instance = null;

	/**
	 * Request-level settings cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $settings_cache = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Security
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * No runtime hooks in stage 2. Instantiation keeps the bootstrap stable.
	 */
	private function __construct() {}

	/**
	 * Hardcoded character class. Cannot be disabled or widened from admin.
	 *
	 * @param bool $multiline Whether LF is allowed.
	 * @return string Character class body (no delimiters, no brackets).
	 */
	public static function hardcoded_char_class( $multiline = false ) {
		$class = self::DEFAULT_ADMIN_CHAR_CLASS;

		if ( $multiline ) {
			$class .= '\\n';
		}

		return $class;
	}

	/**
	 * Compiled hardcoded regex. Always applied, even if admin settings are empty.
	 *
	 * @param bool $multiline Whether LF is allowed.
	 * @return string
	 */
	public static function hardcoded_pattern( $multiline = false ) {
		return '/^[' . self::hardcoded_char_class( $multiline ) . ']+$/u';
	}

	/**
	 * Admin-configured character class from settings, or the default class.
	 *
	 * Empty / invalid admin values fall back to the hardcoded class. They never
	 * disable validation.
	 *
	 * @return string
	 */
	public static function admin_char_class() {
		$settings = self::get_settings();
		$raw      = isset( $settings['char_whitelist'] ) ? (string) $settings['char_whitelist'] : '';
		$raw      = trim( $raw );

		if ( '' === $raw ) {
			return self::DEFAULT_ADMIN_CHAR_CLASS;
		}

		$check = self::validate_admin_char_class( $raw );

		if ( is_wp_error( $check ) ) {
			return self::DEFAULT_ADMIN_CHAR_CLASS;
		}

		return $raw;
	}

	/**
	 * Validate an admin-supplied character class. It may only tighten the
	 * hardcoded set — never add HTML, shortcode, or wildcard syntax.
	 *
	 * @param string $class Character class body.
	 * @return true|WP_Error
	 */
	public static function validate_admin_char_class( $class ) {
		if ( ! is_string( $class ) ) {
			return new WP_Error(
				'apd_whitelist_invalid',
				__( 'Character whitelist must be a string.', 'auto-plate-designer' )
			);
		}

		$class = trim( $class );

		if ( '' === $class ) {
			return new WP_Error(
				'apd_whitelist_empty',
				__( 'Character whitelist cannot be empty. Hardcoded protection would still apply, but the field is required.', 'auto-plate-designer' )
			);
		}

		if ( strlen( $class ) > 400 ) {
			return new WP_Error(
				'apd_whitelist_too_long',
				__( 'Character whitelist is too long.', 'auto-plate-designer' )
			);
		}

		$without_hex = preg_replace( '/\\\\x\{[0-9A-Fa-f]{2,6}\}/', '', $class );

		if ( ! is_string( $without_hex ) || strlen( $without_hex ) !== strcspn( $without_hex, "<>[]{}()|?*+^$#&`\"';=/" ) ) {
			return new WP_Error(
				'apd_whitelist_meta',
				__( 'Character whitelist contains disallowed regex syntax.', 'auto-plate-designer' )
			);
		}

		if ( preg_match( '/\\\\[pPCDXHSWVw]/', $class ) ) {
			return new WP_Error(
				'apd_whitelist_unicode_escape',
				__( 'Character whitelist cannot use Unicode property or generic escapes that widen the set.', 'auto-plate-designer' )
			);
		}

		$pattern = '/^[' . $class . ']+$/u';

		if ( false === @preg_match( $pattern, 'A' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- compile check only.
			return new WP_Error(
				'apd_whitelist_compile',
				__( 'Character whitelist is not a valid regular-expression character class.', 'auto-plate-designer' )
			);
		}

		$forbidden = array( '<', '>', '[', ']', '{', '}', '(', ')', '"', "'", '&', '/', '\\', '%', '=', ';', '*', '`', "\0", "\t", "\r" );

		foreach ( $forbidden as $char ) {
			if ( 1 === preg_match( $pattern, $char ) ) {
				return new WP_Error(
					'apd_whitelist_too_permissive',
					__( 'Character whitelist cannot allow markup, shortcodes, or control characters.', 'auto-plate-designer' )
				);
			}
		}

		return true;
	}

	/**
	 * Validate customer plate text. Rejects on failure; does not rewrite.
	 *
	 * @param mixed $text Raw input.
	 * @param array $args {
	 *     @type int  $max_length     Max characters (UTF-8). Capped by ABSOLUTE_MAX_CHARS.
	 *     @type bool $multiline      Allow newline characters.
	 *     @type bool $allow_empty    Whether an empty string is acceptable.
	 *     @type bool $skip_admin_regex Skip the extra admin class (tests / internal).
	 * }
	 * @return true|WP_Error
	 */
	public static function validate_plate_text( $text, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'max_length'        => self::ABSOLUTE_MAX_CHARS,
				'multiline'         => false,
				'allow_empty'       => false,
				'skip_admin_regex'  => false,
			)
		);

		if ( ! is_string( $text ) ) {
			return new WP_Error(
				'apd_text_type',
				__( 'Plate text must be a plain string.', 'auto-plate-designer' )
			);
		}

		if ( false !== strpos( $text, "\0" ) ) {
			return new WP_Error(
				'apd_text_null',
				__( 'Plate text contains a null byte.', 'auto-plate-designer' )
			);
		}

		if ( $text !== wp_check_invalid_utf8( $text ) ) {
			return new WP_Error(
				'apd_text_utf8',
				__( 'Plate text must be valid UTF-8.', 'auto-plate-designer' )
			);
		}

		if ( strlen( $text ) > ( self::ABSOLUTE_MAX_CHARS * 8 ) ) {
			return new WP_Error(
				'apd_text_too_long',
				__( 'Plate text is too long.', 'auto-plate-designer' )
			);
		}

		if ( $args['multiline'] ) {
			$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

		if ( 0 === $length ) {
			if ( $args['allow_empty'] ) {
				return true;
			}

			return new WP_Error(
				'apd_text_empty',
				__( 'Plate text cannot be empty.', 'auto-plate-designer' )
			);
		}

		$max_length = min( (int) $args['max_length'], self::ABSOLUTE_MAX_CHARS );

		if ( $length > $max_length ) {
			return new WP_Error(
				'apd_text_too_long',
				sprintf(
					/* translators: %d: maximum character count */
					__( 'Plate text exceeds the maximum length of %d characters.', 'auto-plate-designer' ),
					$max_length
				)
			);
		}

		if ( false !== strpbrk( $text, '<>' ) || wp_strip_all_tags( $text ) !== $text ) {
			return new WP_Error(
				'apd_text_html',
				__( 'HTML is not allowed in plate text.', 'auto-plate-designer' )
			);
		}

		if ( false !== strpbrk( $text, '[]{}' ) ) {
			return new WP_Error(
				'apd_text_shortcode',
				__( 'Plate text contains disallowed characters.', 'auto-plate-designer' )
			);
		}

		$hardcoded = self::hardcoded_pattern( (bool) $args['multiline'] );

		if ( 1 !== preg_match( $hardcoded, $text ) ) {
			return new WP_Error(
				'apd_text_whitelist',
				__( 'Plate text contains characters that are not allowed.', 'auto-plate-designer' )
			);
		}

		if ( ! $args['skip_admin_regex'] ) {
			$admin_class = self::admin_char_class();
			$admin_body  = $admin_class;

			if ( $args['multiline'] && false === strpos( $admin_class, '\\n' ) ) {
				$admin_body .= '\\n';
			}

			$admin_pattern = '/^[' . $admin_body . ']+$/u';

			if ( false === @preg_match( $admin_pattern, $text ) || 1 !== preg_match( $admin_pattern, $text ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return new WP_Error(
					'apd_text_whitelist',
					__( 'Plate text contains characters that are not allowed.', 'auto-plate-designer' )
				);
			}
		}

		$sanitized = $args['multiline']
			? self::sanitize_textarea_baseline( $text )
			: self::sanitize_text_baseline( $text );

		if ( $sanitized !== $text ) {
			return new WP_Error(
				'apd_text_sanitize_mismatch',
				__( 'Plate text contains characters that are not allowed.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Sanitize plate text for storage. Call only after validate_plate_text() succeeds.
	 *
	 * @param string $text      Already-validated text.
	 * @param bool   $multiline Whether to preserve newlines.
	 * @return string
	 */
	public static function sanitize_plate_text( $text, $multiline = false ) {
		$text = (string) $text;

		if ( $multiline ) {
			$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
			return self::sanitize_textarea_baseline( $text );
		}

		return self::sanitize_text_baseline( $text );
	}

	/**
	 * Escape plate text for HTML element content.
	 *
	 * @param string $text Stored text.
	 * @return string
	 */
	public static function escape_html( $text ) {
		return esc_html( (string) $text );
	}

	/**
	 * Escape plate text for HTML attributes.
	 *
	 * @param string $text Stored text.
	 * @return string
	 */
	public static function escape_attr( $text ) {
		return esc_attr( (string) $text );
	}

	/**
	 * Escape plate text for textarea content.
	 *
	 * @param string $text Stored text.
	 * @return string
	 */
	public static function escape_textarea( $text ) {
		return esc_textarea( (string) $text );
	}

	/**
	 * Validate a #RGB or #RRGGBB color from the admin palette / customer picker.
	 *
	 * @param mixed $color Raw input.
	 * @return true|WP_Error
	 */
	public static function validate_hex_color( $color ) {
		if ( ! is_string( $color ) ) {
			return new WP_Error(
				'apd_color_type',
				__( 'Color must be a string.', 'auto-plate-designer' )
			);
		}

		if ( 1 !== preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			return new WP_Error(
				'apd_color_invalid',
				__( 'Color must be a hex value such as #1A2B3C.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Sanitize a hex color. Returns empty string if invalid.
	 *
	 * @param mixed $color Raw input.
	 * @return string
	 */
	public static function sanitize_hex_color( $color ) {
		$check = self::validate_hex_color( $color );

		if ( is_wp_error( $check ) ) {
			return '';
		}

		return strtoupper( (string) $color );
	}

	/**
	 * Validate a layout slug.
	 *
	 * @param mixed $layout Raw input.
	 * @return true|WP_Error
	 */
	public static function validate_layout( $layout ) {
		$allowed = array( 'text_only', 'text_image_text', 'image_text', 'multiline_text' );

		if ( ! is_string( $layout ) || ! in_array( $layout, $allowed, true ) ) {
			return new WP_Error(
				'apd_layout_invalid',
				__( 'Selected layout is not allowed.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Known format type slugs. Extra values from the filter are ignored.
	 *
	 * @return array<int, string>
	 */
	public static function allowed_format_types() {
		$known = array( 'eu', 'eu_plain', 'us', 'moto', 'moto_plain', 'suv_eu', 'suv', 'custom', 'color', 'holder' );

		/**
		 * Filter the format types offered in admin and accepted on save.
		 *
		 * Values outside the known set are discarded.
		 *
		 * @param array<int, string> $known Type slugs.
		 */
		$filtered = apply_filters( 'apd_format_types', $known );

		if ( ! is_array( $filtered ) ) {
			return $known;
		}

		$clean = array();

		foreach ( $filtered as $type ) {
			if ( is_string( $type ) && in_array( $type, $known, true ) ) {
				$clean[] = $type;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		return empty( $clean ) ? $known : $clean;
	}

	/**
	 * Validate a format type slug.
	 *
	 * @param mixed $type Raw input.
	 * @return true|WP_Error
	 */
	public static function validate_format_type( $type ) {
		if ( ! is_string( $type ) || ! in_array( $type, self::allowed_format_types(), true ) ) {
			return new WP_Error(
				'apd_format_invalid',
				__( 'Selected format is not allowed.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Validate a positive integer ID (preset, attachment, format).
	 *
	 * @param mixed $value Raw input.
	 * @return true|WP_Error
	 */
	public static function validate_positive_int( $value ) {
		if ( is_int( $value ) && $value > 0 ) {
			return true;
		}

		if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return true;
		}

		return new WP_Error(
			'apd_id_invalid',
			__( 'Invalid identifier.', 'auto-plate-designer' )
		);
	}

	/**
	 * Create a nonce for plugin requests.
	 *
	 * @param string $action Action name. Defaults to NONCE_ACTION.
	 * @return string
	 */
	public static function create_nonce( $action = self::NONCE_ACTION ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify a nonce. Use on every AJAX / admin POST.
	 *
	 * @param mixed  $nonce  Nonce value.
	 * @param string $action Action name.
	 * @return true|WP_Error
	 */
	public static function verify_nonce( $nonce, $action = self::NONCE_ACTION ) {
		if ( ! is_string( $nonce ) || 1 !== wp_verify_nonce( $nonce, $action ) ) {
			return new WP_Error(
				'apd_nonce',
				__( 'Security check failed. Reload the page and try again.', 'auto-plate-designer' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Require a capability. Defaults to WooCommerce merchant cap with
	 * manage_options as fallback on sites without that cap.
	 *
	 * @param string $capability Required capability.
	 * @return true|WP_Error
	 */
	public static function require_capability( $capability = self::ADMIN_CAPABILITY ) {
		if ( current_user_can( $capability ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'apd_capability',
			__( 'You do not have permission to perform this action.', 'auto-plate-designer' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Simple transient rate limiter for preview / upload endpoints.
	 *
	 * @param string $bucket         Action key, e.g. "preview" or "upload".
	 * @param int    $max_attempts   Allowed hits inside the window.
	 * @param int    $window_seconds Window length.
	 * @return true|WP_Error
	 */
	public static function assert_rate_limit( $bucket, $max_attempts = 20, $window_seconds = 60 ) {
		$ip  = self::request_ip();
		$key = 'apd_rl_' . md5( $bucket . '|' . $ip );

		$count = (int) get_transient( $key );

		if ( $count >= (int) $max_attempts ) {
			return new WP_Error(
				'apd_rate_limited',
				__( 'Too many requests. Please wait a moment and try again.', 'auto-plate-designer' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, (int) $window_seconds );

		return true;
	}

	/**
	 * Validate an uploaded file from $_FILES.
	 *
	 * @param array $file {
	 *     Typical $_FILES element.
	 *     @type string $name
	 *     @type string $type
	 *     @type string $tmp_name
	 *     @type int    $error
	 *     @type int    $size
	 * }
	 * @param array $args {
	 *     @type array<int, string> $mimes      Allowed MIME types.
	 *     @type int                $max_bytes  Size limit.
	 *     @type int                $max_width  Raster width limit (0 = skip).
	 *     @type int                $max_height Raster height limit (0 = skip).
	 *     @type bool               $allow_svg        Whether SVG is permitted.
	 *     @type bool               $require_uploaded Require PHP is_uploaded_file() (true for HTTP uploads).
	 * }
	 * @return true|WP_Error
	 */
	public static function validate_upload( $file, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'mimes'            => self::RASTER_MIMES,
				'max_bytes'        => self::MAX_IMAGE_BYTES,
				'max_width'        => 0,
				'max_height'       => 0,
				'allow_svg'        => false,
				'require_uploaded' => true,
			)
		);

		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error(
				'apd_upload_missing',
				__( 'No file was uploaded.', 'auto-plate-designer' )
			);
		}

		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error(
				'apd_upload_error',
				__( 'The file upload failed.', 'auto-plate-designer' )
			);
		}

		$tmp = $file['tmp_name'];

		if ( $args['require_uploaded'] && ! is_uploaded_file( $tmp ) ) {
			return new WP_Error(
				'apd_upload_tmp',
				__( 'The uploaded file could not be read.', 'auto-plate-designer' )
			);
		}

		if ( ! is_readable( $tmp ) ) {
			return new WP_Error(
				'apd_upload_tmp',
				__( 'The uploaded file could not be read.', 'auto-plate-designer' )
			);
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size <= 0 || $size > (int) $args['max_bytes'] ) {
			return new WP_Error(
				'apd_upload_size',
				__( 'The uploaded file exceeds the maximum allowed size.', 'auto-plate-designer' )
			);
		}

		$mime = self::detect_mime( $tmp );

		if ( '' === $mime ) {
			return new WP_Error(
				'apd_upload_mime',
				__( 'The uploaded file type could not be determined.', 'auto-plate-designer' )
			);
		}

		$allowed = $args['mimes'];

		if ( $args['allow_svg'] && ! in_array( self::SVG_MIME, $allowed, true ) ) {
			$allowed[] = self::SVG_MIME;
		}

		if ( ! $args['allow_svg'] ) {
			$allowed = array_values( array_diff( $allowed, array( self::SVG_MIME ) ) );
		}

		if ( ! in_array( $mime, $allowed, true ) ) {
			return new WP_Error(
				'apd_upload_mime',
				__( 'That file type is not allowed.', 'auto-plate-designer' )
			);
		}

		$ext_ok = self::extension_matches_mime( isset( $file['name'] ) ? (string) $file['name'] : '', $mime );

		if ( ! $ext_ok ) {
			return new WP_Error(
				'apd_upload_extension',
				__( 'File extension does not match the detected file type.', 'auto-plate-designer' )
			);
		}

		if ( self::SVG_MIME === $mime ) {
			$svg = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp file.

			if ( false === $svg ) {
				return new WP_Error(
					'apd_upload_read',
					__( 'The uploaded file could not be read.', 'auto-plate-designer' )
				);
			}

			$clean = self::sanitize_svg( $svg );

			if ( is_wp_error( $clean ) ) {
				return $clean;
			}

			return true;
		}

		if ( (int) $args['max_width'] > 0 || (int) $args['max_height'] > 0 ) {
			$dim = self::validate_image_dimensions( $tmp, (int) $args['max_width'], (int) $args['max_height'] );

			if ( is_wp_error( $dim ) ) {
				return $dim;
			}
		}

		return true;
	}

	/**
	 * Detect MIME using finfo, never the client-supplied type or extension alone.
	 *
	 * @param string $path Filesystem path.
	 * @return string Empty string on failure.
	 */
	public static function detect_mime( $path ) {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		if ( ! class_exists( 'finfo' ) ) {
			return '';
		}

		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $path );

		if ( ! is_string( $mime ) || '' === $mime ) {
			return '';
		}

		if ( 'text/plain' === $mime || 'text/html' === $mime || 'text/xml' === $mime || 'application/xml' === $mime ) {
			$head = file_get_contents( $path, false, null, 0, 256 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( is_string( $head ) && false !== stripos( $head, '<svg' ) ) {
				return self::SVG_MIME;
			}
		}

		return $mime;
	}

	/**
	 * Raster dimension check via getimagesize().
	 *
	 * @param string $path      Filesystem path.
	 * @param int    $max_width Max width, 0 to skip.
	 * @param int    $max_height Max height, 0 to skip.
	 * @return true|WP_Error
	 */
	public static function validate_image_dimensions( $path, $max_width, $max_height ) {
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid images return false.

		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return new WP_Error(
				'apd_image_dimensions',
				__( 'The image dimensions could not be read.', 'auto-plate-designer' )
			);
		}

		$width  = (int) $info[0];
		$height = (int) $info[1];

		if ( $max_width > 0 && $width > $max_width ) {
			return new WP_Error(
				'apd_image_width',
				sprintf(
					/* translators: %d: maximum width in pixels */
					__( 'Image width cannot exceed %d pixels.', 'auto-plate-designer' ),
					$max_width
				)
			);
		}

		if ( $max_height > 0 && $height > $max_height ) {
			return new WP_Error(
				'apd_image_height',
				sprintf(
					/* translators: %d: maximum height in pixels */
					__( 'Image height cannot exceed %d pixels.', 'auto-plate-designer' ),
					$max_height
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize SVG markup: drop scripts, event handlers, foreignObject, and
	 * javascript: URLs. Returns cleaned XML or WP_Error.
	 *
	 * @param string $svg Raw SVG contents.
	 * @return string|WP_Error
	 */
	public static function sanitize_svg( $svg ) {
		if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
			return new WP_Error(
				'apd_svg_empty',
				__( 'SVG file is empty.', 'auto-plate-designer' )
			);
		}

		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			return new WP_Error(
				'apd_svg_size',
				__( 'SVG file exceeds the maximum allowed size.', 'auto-plate-designer' )
			);
		}

		if ( false !== stripos( $svg, '<!ENTITY' ) || false !== stripos( $svg, '<!DOCTYPE' ) ) {
			return new WP_Error(
				'apd_svg_doctype',
				__( 'SVG files with a DOCTYPE or entity declarations are not allowed.', 'auto-plate-designer' )
			);
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error(
				'apd_svg_dom',
				__( 'SVG sanitization requires PHP DOMDocument.', 'auto-plate-designer' )
			);
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement ) {
			return new WP_Error(
				'apd_svg_parse',
				__( 'SVG file could not be parsed.', 'auto-plate-designer' )
			);
		}

		if ( 'svg' !== strtolower( $dom->documentElement->tagName ) && 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return new WP_Error(
				'apd_svg_root',
				__( 'SVG file must have an <svg> root element.', 'auto-plate-designer' )
			);
		}

		$banned_tags = array(
			'script',
			'foreignObject',
			'foreignobject',
			'iframe',
			'embed',
			'object',
			'link',
			'meta',
			'style',
			'animate',
			'animateTransform',
			'set',
			'use',
		);

		self::remove_elements_by_names( $dom, $banned_tags );
		self::strip_unsafe_svg_attributes( $dom );

		$clean = $dom->saveXML( $dom->documentElement );

		if ( ! is_string( $clean ) || '' === $clean ) {
			return new WP_Error(
				'apd_svg_write',
				__( 'Sanitized SVG could not be exported.', 'auto-plate-designer' )
			);
		}

		if ( false !== stripos( $clean, '<script' ) || false !== stripos( $clean, 'javascript:' ) || false !== stripos( $clean, 'onerror=' ) ) {
			return new WP_Error(
				'apd_svg_residual',
				__( 'SVG file still contained unsafe content after sanitization.', 'auto-plate-designer' )
			);
		}

		return $clean;
	}

	/**
	 * Validate a canvas PNG data URL before it is stored as an attachment.
	 *
	 * @param mixed $data_url Raw POST value.
	 * @param int   $max_bytes Decoded payload limit.
	 * @return true|WP_Error
	 */
	public static function validate_png_data_url( $data_url, $max_bytes = 2097152 ) {
		if ( ! is_string( $data_url ) || 0 !== strpos( $data_url, 'data:image/png;base64,' ) ) {
			return new WP_Error(
				'apd_png_format',
				__( 'Preview image must be a PNG data URL.', 'auto-plate-designer' )
			);
		}

		$encoded = substr( $data_url, strlen( 'data:image/png;base64,' ) );

		if ( '' === $encoded || strlen( $encoded ) > ( (int) $max_bytes * 2 ) ) {
			return new WP_Error(
				'apd_png_size',
				__( 'Preview image is too large.', 'auto-plate-designer' )
			);
		}

		$binary = base64_decode( $encoded, true );

		if ( false === $binary || '' === $binary ) {
			return new WP_Error(
				'apd_png_decode',
				__( 'Preview image could not be decoded.', 'auto-plate-designer' )
			);
		}

		if ( strlen( $binary ) > (int) $max_bytes ) {
			return new WP_Error(
				'apd_png_size',
				__( 'Preview image is too large.', 'auto-plate-designer' )
			);
		}

		if ( 0 !== strpos( $binary, "\x89PNG\r\n\x1a\n" ) ) {
			return new WP_Error(
				'apd_png_magic',
				__( 'Preview image is not a valid PNG file.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Character class rewritten for JavaScript Unicode regex (`u` flag).
	 *
	 * @param bool $multiline Whether to allow newline.
	 * @return string
	 */
	public static function javascript_char_class( $multiline = false ) {
		$class = str_replace( '\\x{', '\\u{', self::admin_char_class() );

		if ( $multiline && false === strpos( $class, '\\n' ) ) {
			$class .= '\\n';
		}

		return $class;
	}

	/**
	 * Payload the frontend can embed once on page load (no per-keystroke fetch).
	 *
	 * @return array<string, mixed>
	 */
	public static function frontend_text_rules() {
		return array(
			'charClass'      => self::javascript_char_class( false ),
			'multilineClass' => self::javascript_char_class( true ),
			'absoluteMax'    => self::ABSOLUTE_MAX_CHARS,
		);
	}

	/**
	 * Validate an admin label (format names, palette labels). Stricter than
	 * free text, looser than plate text — letters, numbers, spaces, hyphen,
	 * period, and parentheses only.
	 *
	 * @param mixed $text Raw input.
	 * @param int   $max  Max length.
	 * @return true|WP_Error
	 */
	public static function validate_admin_label( $text, $max = 80 ) {
		if ( ! is_string( $text ) ) {
			return new WP_Error(
				'apd_label_type',
				__( 'Label must be a string.', 'auto-plate-designer' )
			);
		}

		$text = trim( $text );

		if ( '' === $text ) {
			return new WP_Error(
				'apd_label_empty',
				__( 'Label cannot be empty.', 'auto-plate-designer' )
			);
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

		if ( $length > (int) $max ) {
			return new WP_Error(
				'apd_label_too_long',
				__( 'Label is too long.', 'auto-plate-designer' )
			);
		}

		if ( wp_strip_all_tags( $text ) !== $text || 1 !== preg_match( '/^[\p{L}\p{N} .\-()]+$/u', $text ) ) {
			return new WP_Error(
				'apd_label_chars',
				__( 'Label contains characters that are not allowed.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Sanitize an admin label after validation.
	 *
	 * @param string $text Raw input.
	 * @return string
	 */
	public static function sanitize_admin_label( $text ) {
		return sanitize_text_field( (string) $text );
	}

	/**
	 * Validate an oval / country-band code. Empty is rejected; one letter is allowed
	 * (Austria A, Germany D, France F). US design codes pass $min_letters = 2.
	 *
	 * @param mixed $code        Raw input, already uppercased by the caller.
	 * @param int   $min_letters Minimum letter count (1–3).
	 * @return true|WP_Error
	 */
	public static function validate_country_code( $code, $min_letters = 1 ) {
		if ( ! is_string( $code ) || '' === $code ) {
			return new WP_Error(
				'apd_country_code',
				__( 'Country code cannot be empty.', 'auto-plate-designer' )
			);
		}

		$min = absint( $min_letters );

		if ( $min < 1 ) {
			$min = 1;
		}

		if ( $min > 3 ) {
			$min = 3;
		}

		if ( 1 !== preg_match( '/^[A-Z]{' . $min . ',3}$/', $code ) ) {
			return new WP_Error(
				'apd_country_code',
				2 === $min
					? __( 'Country code must be 2 or 3 uppercase letters.', 'auto-plate-designer' )
					: __( 'Country code must be 1 to 3 uppercase letters (for example A, BG, or SLO).', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Validate a media library attachment against allowed MIME types.
	 *
	 * @param mixed $id   Attachment ID.
	 * @param array $args {
	 *     @type array<int, string> $mimes      Allowed MIME types.
	 *     @type bool               $allow_svg  Allow SVG.
	 *     @type bool               $allow_font Allow WOFF2 fonts.
	 *     @type bool               $optional   Whether 0 / empty is allowed.
	 * }
	 * @return true|WP_Error
	 */
	public static function validate_attachment( $id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'mimes'      => self::RASTER_MIMES,
				'allow_svg'  => false,
				'allow_font' => false,
				'optional'   => false,
			)
		);

		if ( '' === $id || null === $id || 0 === $id || '0' === $id ) {
			if ( $args['optional'] ) {
				return true;
			}

			return new WP_Error(
				'apd_attachment_required',
				__( 'Please select a file from the media library.', 'auto-plate-designer' )
			);
		}

		$check = self::validate_positive_int( $id );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$attachment_id = absint( $id );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'apd_attachment_missing',
				__( 'Selected media item was not found.', 'auto-plate-designer' )
			);
		}

		$allowed = $args['mimes'];

		if ( $args['allow_svg'] && ! in_array( self::SVG_MIME, $allowed, true ) ) {
			$allowed[] = self::SVG_MIME;
		}

		if ( $args['allow_font'] ) {
			$allowed[] = 'font/woff2';
			$allowed[] = 'application/font-woff2';
		}

		$path     = get_attached_file( $attachment_id );
		$detected = is_string( $path ) && '' !== $path ? self::detect_mime( $path ) : '';
		$stored   = (string) get_post_mime_type( $attachment_id );

		if ( $args['allow_font'] && is_string( $path ) && self::is_woff2_file( $path ) ) {
			return true;
		}

		if ( ! in_array( $stored, $allowed, true ) && ! in_array( $detected, $allowed, true ) ) {
			return new WP_Error(
				'apd_attachment_mime',
				__( 'Selected file type is not allowed.', 'auto-plate-designer' )
			);
		}

		return true;
	}

	/**
	 * Whether a file starts with the WOFF2 magic bytes.
	 *
	 * @param string $path Filesystem path.
	 * @return bool
	 */
	public static function is_woff2_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}

		$head = file_get_contents( $path, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		return is_string( $head ) && 'wOF2' === $head;
	}

	/**
	 * Flush the in-request settings cache (after admin saves).
	 */
	public static function flush_settings_cache() {
		self::$settings_cache = null;
	}

	/**
	 * Settings array from the options table.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		if ( class_exists( 'APD_Plugin' ) ) {
			self::$settings_cache = APD_Plugin::get_settings();
			return self::$settings_cache;
		}

		$stored = get_option( APD_OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$settings_cache = $stored;

		return self::$settings_cache;
	}

	/**
	 * Baseline sanitization that still preserves inner spaces.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	private static function sanitize_text_baseline( $text ) {
		$text = wp_check_invalid_utf8( $text );
		$text = wp_kses_no_null( $text );
		$text = wp_strip_all_tags( $text );

		return $text;
	}

	/**
	 * Baseline sanitization that preserves newlines.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	private static function sanitize_textarea_baseline( $text ) {
		$text = wp_check_invalid_utf8( $text );
		$text = wp_kses_no_null( $text );
		$text = wp_strip_all_tags( $text );

		return $text;
	}

	/**
	 * Client IP for rate limiting. Not used for security decisions beyond throttling.
	 *
	 * @return string
	 */
	private static function request_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			if ( '' !== $ip ) {
				return $ip;
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Confirm the filename extension matches the detected MIME.
	 *
	 * @param string $filename Original name.
	 * @param string $mime     Detected MIME.
	 * @return bool
	 */
	private static function extension_matches_mime( $filename, $mime ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		$map = array(
			'image/png'     => array( 'png' ),
			'image/jpeg'    => array( 'jpg', 'jpeg' ),
			'image/webp'    => array( 'webp' ),
			'image/svg+xml' => array( 'svg' ),
		);

		if ( ! isset( $map[ $mime ] ) ) {
			return false;
		}

		return in_array( $ext, $map[ $mime ], true );
	}

	/**
	 * Drop banned SVG elements.
	 *
	 * @param DOMDocument      $dom  Document.
	 * @param array<int, string> $names Tag names.
	 */
	private static function remove_elements_by_names( $dom, $names ) {
		$xpath = new DOMXPath( $dom );

		foreach ( $names as $name ) {
			$nodes = $xpath->query( '//*[local-name()="' . $name . '"]' );

			if ( ! $nodes ) {
				continue;
			}

			$queue = array();

			foreach ( $nodes as $node ) {
				$queue[] = $node;
			}

			foreach ( $queue as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

	/**
	 * Remove on* handlers and javascript: URLs from SVG attributes.
	 *
	 * @param DOMDocument $dom Document.
	 */
	private static function strip_unsafe_svg_attributes( $dom ) {
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//*' );

		if ( ! $nodes ) {
			return;
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			$remove = array();

			foreach ( $node->attributes as $attr ) {
				$attr_name  = strtolower( $attr->name );
				$attr_value = trim( $attr->value );

				if ( 0 === strpos( $attr_name, 'on' ) ) {
					$remove[] = $attr->name;
					continue;
				}

				if ( in_array( $attr_name, array( 'href', 'xlink:href' ), true ) || false !== strpos( $attr_name, ':href' ) ) {
					$lower = strtolower( $attr_value );

					if ( 0 === strpos( $lower, 'javascript:' ) || 0 === strpos( $lower, 'data:' ) || 0 === strpos( $lower, 'http:' ) || 0 === strpos( $lower, 'https:' ) ) {
						$remove[] = $attr->name;
					}
				}

				if ( 'style' === $attr_name && ( false !== stripos( $attr_value, 'javascript:' ) || false !== stripos( $attr_value, 'expression(' ) ) ) {
					$remove[] = $attr->name;
				}
			}

			foreach ( $remove as $name ) {
				$node->removeAttribute( $name );
			}
		}
	}
}
