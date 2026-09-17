<?php
/**
 * Plugin bootstrap: version checks, includes, and component wiring.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class APD_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var APD_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared plugin instance.
	 *
	 * @return APD_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load includes and register runtime hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load class files. Later stages fill these classes in; they must exist now
	 * so activation and boot never fatal on a missing include.
	 */
	private function includes() {
		require_once APD_PLUGIN_DIR . 'includes/class-apd-security.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-formats.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-presets.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-designs.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-color-palettes.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-catalog.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-admin-settings.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-ajax.php';
		require_once APD_PLUGIN_DIR . 'includes/class-apd-woocommerce.php';
	}

	/**
	 * Register WordPress hooks. Namespaced class methods only — no global functions.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_boot_components' ), 20 );
		add_action( 'admin_notices', array( $this, 'maybe_show_dependency_notice' ) );
	}

	/**
	 * Load translations from /languages.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'auto-plate-designer',
			false,
			dirname( APD_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Instantiate feature classes only when WooCommerce meets the minimum version.
	 */
	public function maybe_boot_components() {
		if ( ! $this->is_woocommerce_ready() ) {
			return;
		}

		APD_Security::instance();
		APD_Formats::instance();
		APD_Presets::instance();
		APD_Designs::instance();
		APD_Color_Palettes::instance();
		APD_Catalog::instance();
		APD_Admin_Settings::instance();
		APD_Ajax::instance();
		APD_WooCommerce::instance();
	}

	/**
	 * Admin notice when WooCommerce is missing or too old. Avoids a fatal on load.
	 */
	public function maybe_show_dependency_notice() {
		if ( $this->is_woocommerce_ready() ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = $this->get_dependency_error_message();

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Declare WooCommerce High-Performance Order Storage (HPOS) compatibility.
	 *
	 * Must run on `before_woocommerce_init` so WooCommerce records compatibility
	 * before it boots custom order tables.
	 */
	public static function declare_hpos_compatibility() {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			APD_PLUGIN_FILE,
			true
		);
	}

	/**
	 * Activation: check PHP / WordPress / WooCommerce, then seed default options.
	 *
	 * Uses plugin headers via get_plugin_data() so version requirements stay in
	 * one place (the plugin header) and WordPress admin can read them too.
	 */
	public static function activate() {
		$plugin_data = self::get_header_data();

		$requires_php = isset( $plugin_data['RequiresPHP'] ) && '' !== $plugin_data['RequiresPHP']
			? $plugin_data['RequiresPHP']
			: APD_MIN_PHP;
		$requires_wp  = isset( $plugin_data['RequiresWP'] ) && '' !== $plugin_data['RequiresWP']
			? $plugin_data['RequiresWP']
			: APD_MIN_WP;

		if ( version_compare( PHP_VERSION, $requires_php, '<' ) ) {
			self::abort_activation(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Auto Plate Designer requires PHP %1$s or higher. This server is running PHP %2$s.', 'auto-plate-designer' ),
					$requires_php,
					PHP_VERSION
				)
			);
		}

		global $wp_version;

		if ( version_compare( $wp_version, $requires_wp, '<' ) ) {
			self::abort_activation(
				sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version */
					__( 'Auto Plate Designer requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'auto-plate-designer' ),
					$requires_wp,
					$wp_version
				)
			);
		}

		if ( ! self::is_woocommerce_plugin_active() ) {
			self::abort_activation(
				__( 'Auto Plate Designer requires WooCommerce to be installed and active.', 'auto-plate-designer' )
			);
		}

		$wc_version = self::get_woocommerce_version();

		if ( '' === $wc_version || version_compare( $wc_version, APD_MIN_WC, '<' ) ) {
			self::abort_activation(
				sprintf(
					/* translators: 1: required WooCommerce version, 2: current WooCommerce version */
					__( 'Auto Plate Designer requires WooCommerce %1$s or higher. Detected version: %2$s.', 'auto-plate-designer' ),
					APD_MIN_WC,
					'' !== $wc_version ? $wc_version : __( 'unknown', 'auto-plate-designer' )
				)
			);
		}

		self::seed_default_options();
	}

	/**
	 * Deactivation: drop runtime transients. Persistent settings stay until uninstall.
	 */
	public static function deactivate() {
		delete_transient( APD_CACHE_KEY );
	}

	/**
	 * Default option payload. Later stages extend this shape; seed only if missing.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_default_settings() {
		$starter = class_exists( 'APD_Color_Palettes' )
			? APD_Color_Palettes::starter()
			: array(
				'library'  => array(),
				'palettes' => array(),
			);

		return array(
			'formats'          => array(),
			'presets'          => array(),
			'designs'          => array(),
			'color_library'    => $starter['library'],
			'color_palettes'   => $starter['palettes'],
			'palettes'         => array(
				'text'       => self::starter_palette( 'text' ),
				'border'     => self::starter_palette( 'border' ),
				'background' => self::starter_palette( 'background' ),
			),
			'fonts'            => array(),
			'catalog'          => array(
				'published' => array( 'plates-eu', 'plates-us', 'plates-moto', 'plates-suv', 'plates-custom', 'plates-color', 'holders' ),
			),
			'swatch_display'   => array(
				'size'         => 28,
				'shape'        => 'circle',
				'radius'      => 4,
				'border_color' => '#000000',
			),
			'limits'           => array(
				'min_font_size' => 12,
				'max_lines'     => 5,
				'layouts'       => array(
					'text_only'        => array(
						'max_chars' => 12,
						'max_lines' => 1,
					),
					'text_image_text'  => array(
						'max_chars' => 8,
						'max_lines' => 1,
					),
					'image_text'       => array(
						'max_chars' => 12,
						'max_lines' => 1,
					),
					'multiline_text'   => array(
						'max_chars' => 40,
						'max_lines' => 5,
					),
				),
			),
			'char_whitelist'   => APD_Security::DEFAULT_ADMIN_CHAR_CLASS,
			'enabled_layouts'  => array(
				'text_only',
				'text_image_text',
				'image_text',
				'multiline_text',
			),
		);
	}

	/**
	 * Cached settings: defaults merged with the stored option.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$cached = get_transient( APD_CACHE_KEY );

		if ( is_array( $cached ) && isset( $cached['formats'], $cached['limits'], $cached['palettes'], $cached['color_library'], $cached['color_palettes'], $cached['swatch_display']['border_color'] ) ) {
			return $cached;
		}

		$defaults = self::get_default_settings();
		$stored   = get_option( APD_OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = self::merge_settings( $defaults, $stored );

		set_transient( APD_CACHE_KEY, $settings, HOUR_IN_SECONDS );

		return $settings;
	}

	/**
	 * Persist settings, drop caches, and keep Security in sync.
	 *
	 * @param array<string, mixed> $settings Full settings payload.
	 * @return bool
	 */
	public static function save_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$defaults = self::get_default_settings();
		$payload  = self::merge_settings( $defaults, $settings );

		$updated = update_option( APD_OPTION_KEY, $payload, false );

		delete_transient( APD_CACHE_KEY );
		APD_Security::flush_settings_cache();

		return $updated || get_option( APD_OPTION_KEY ) === $payload;
	}

	/**
	 * Palette set slugs shoppers can pick from.
	 *
	 * @return array<int, string>
	 */
	public static function palette_sets() {
		return array( 'text', 'border', 'background' );
	}

	/**
	 * Palette colors for a shopper-facing field.
	 *
	 * @param string $field text|border|background.
	 * @return array<int, array<string, string>>
	 */
	public static function palette_colors( $field ) {
		$field = sanitize_key( (string) $field );

		if ( ! in_array( $field, self::palette_sets(), true ) ) {
			$field = 'text';
		}

		if ( class_exists( 'APD_Color_Palettes' ) ) {
			return APD_Color_Palettes::colors_for_purpose( $field );
		}

		$settings = self::get_settings();
		$palettes = isset( $settings['palettes'] ) && is_array( $settings['palettes'] ) ? $settings['palettes'] : array();
		$colors   = isset( $palettes[ $field ] ) && is_array( $palettes[ $field ] ) ? $palettes[ $field ] : array();

		return self::normalize_palette_list( $colors );
	}

	/**
	 * First matching palette hex, or the first color in the set.
	 *
	 * @param string             $field     text|border|background.
	 * @param array<int, string> $preferred Preferred hex values in order.
	 * @return string
	 */
	public static function palette_preferred_hex( $field, $preferred ) {
		$colors = self::palette_colors( $field );
		$want   = array_values( array_filter( (array) $preferred ) );

		foreach ( $want as $hex ) {
			foreach ( $colors as $color ) {
				if ( 0 === strcasecmp( $color['hex'], $hex ) ) {
					return $color['hex'];
				}
			}
		}

		if ( ! empty( $colors ) ) {
			return $colors[0]['hex'];
		}

		$fallback = isset( $want[0] ) ? (string) $want[0] : '#000000';

		return $fallback ? $fallback : '#000000';
	}

	/**
	 * Starter colors for one purpose-specific palette.
	 *
	 * @param string $prefix Set slug used in default IDs.
	 * @return array<int, array<string, string>>
	 */
	private static function starter_palette( $prefix ) {
		$rows = array(
			array(
				'hex'   => '#000000',
				'label' => 'Black',
				'slug'  => 'black',
			),
			array(
				'hex'   => '#FFFFFF',
				'label' => 'White',
				'slug'  => 'white',
			),
			array(
				'hex'   => '#1B365D',
				'label' => 'Navy',
				'slug'  => 'navy',
			),
			array(
				'hex'   => '#C41E3A',
				'label' => 'Red',
				'slug'  => 'red',
			),
		);

		if ( 'background' === $prefix ) {
			$rows = array( $rows[1], $rows[0], $rows[2], $rows[3] );
		}

		$out = array();

		foreach ( $rows as $row ) {
			$out[] = array(
				'id'    => 'apd-color-' . $prefix . '-' . $row['slug'],
				'hex'   => $row['hex'],
				'label' => $row['label'],
			);
		}

		return $out;
	}

	/**
	 * Whether a palette list already has this hex or label.
	 *
	 * @param array<int, array<string, string>> $list  Palette rows.
	 * @param string                            $hex   Color hex.
	 * @param string                            $label Color label.
	 * @return bool
	 */
	public static function palette_list_has_entry( $list, $hex, $label ) {
		if ( ! is_array( $list ) ) {
			return false;
		}

		$hex_key   = strtoupper( (string) $hex );
		$label_key = self::palette_label_key( $label );

		foreach ( $list as $color ) {
			if ( ! is_array( $color ) ) {
				continue;
			}

			if ( $hex_key === strtoupper( isset( $color['hex'] ) ? (string) $color['hex'] : '' ) ) {
				return true;
			}

			if ( '' !== $label_key && $label_key === self::palette_label_key( isset( $color['label'] ) ? (string) $color['label'] : '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Comparable label key (trimmed, case-insensitive).
	 *
	 * @param mixed $label Raw label.
	 * @return string
	 */
	private static function palette_label_key( $label ) {
		$label = trim( (string) $label );

		if ( '' === $label ) {
			return '';
		}

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $label, 'UTF-8' );
		}

		return strtolower( $label );
	}

	/**
	 * Keep hex/label rows only, dropping duplicate hex or label values.
	 *
	 * @param mixed $colors Raw list.
	 * @return array<int, array<string, string>>
	 */
	public static function normalize_palette_list( $colors ) {
		$out = array();

		if ( ! is_array( $colors ) ) {
			return $out;
		}

		foreach ( $colors as $color ) {
			if ( ! is_array( $color ) || empty( $color['hex'] ) ) {
				continue;
			}

			$hex   = (string) $color['hex'];
			$label = isset( $color['label'] ) && '' !== (string) $color['label'] ? (string) $color['label'] : $hex;

			if ( self::palette_list_has_entry( $out, $hex, $label ) ) {
				continue;
			}

			$out[] = array(
				'id'    => isset( $color['id'] ) ? (string) $color['id'] : '',
				'hex'   => $hex,
				'label' => $label,
			);
		}

		return $out;
	}

	/**
	 * Copy palette rows with new IDs so each purpose list can be edited alone.
	 *
	 * @param array<int, array<string, string>> $colors Source rows.
	 * @return array<int, array<string, string>>
	 */
	private static function clone_palette_colors( $colors ) {
		$out = array();

		foreach ( self::normalize_palette_list( $colors ) as $color ) {
			$out[] = array(
				'id'    => self::new_id(),
				'hex'   => $color['hex'],
				'label' => $color['label'],
			);
		}

		return $out;
	}

	/**
	 * Three purpose lists. Legacy shared/split palettes are copied once into empty lists.
	 *
	 * @param array<string, mixed> $defaults Default palettes.
	 * @param array<string, mixed> $stored   Stored palettes.
	 * @return array<string, mixed>
	 */
	private static function normalize_palettes( $defaults, $stored ) {
		$merged      = array_merge( $defaults, $stored );
		$mode        = isset( $stored['mode'] ) ? (string) $stored['mode'] : '';
		$assignments = isset( $stored['assignments'] ) && is_array( $stored['assignments'] ) ? $stored['assignments'] : array();
		$shared      = isset( $stored['shared'] ) && is_array( $stored['shared'] ) ? $stored['shared'] : array();
		$out         = array();

		foreach ( self::palette_sets() as $field ) {
			$list = isset( $stored[ $field ] ) && is_array( $stored[ $field ] ) ? $stored[ $field ] : null;

			if ( null === $list ) {
				$list = isset( $defaults[ $field ] ) && is_array( $defaults[ $field ] ) ? $defaults[ $field ] : array();
			}

			$list = self::normalize_palette_list( $list );

			if ( empty( $list ) ) {
				$source = array();

				if ( 'split' === $mode && ! empty( $assignments[ $field ] ) ) {
					$assigned = sanitize_key( (string) $assignments[ $field ] );

					if ( 'shared' === $assigned ) {
						$source = $shared;
					} elseif ( $assigned !== $field && isset( $stored[ $assigned ] ) && is_array( $stored[ $assigned ] ) ) {
						$source = $stored[ $assigned ];
					} else {
						$source = $shared;
					}
				} elseif ( ! empty( $shared ) ) {
					$source = $shared;
				}

				if ( ! empty( $source ) ) {
					$list = self::clone_palette_colors( $source );
				}
			}

			$out[ $field ] = $list;
		}

		$any_color = false;

		foreach ( $out as $list ) {
			if ( ! empty( $list ) ) {
				$any_color = true;
				break;
			}
		}

		if ( ! $any_color ) {
			foreach ( self::palette_sets() as $field ) {
				$out[ $field ] = self::normalize_palette_list(
					isset( $defaults[ $field ] ) ? $defaults[ $field ] : array()
				);
			}
		}

		unset( $merged );

		return $out;
	}

	/**
	 * Merge stored settings onto defaults without dropping nested keys.
	 *
	 * @param array<string, mixed> $defaults Default payload.
	 * @param array<string, mixed> $stored   Stored payload.
	 * @return array<string, mixed>
	 */
	private static function merge_settings( $defaults, $stored ) {
		$settings = array_merge( $defaults, $stored );

		$legacy_lists = self::normalize_palettes(
			$defaults['palettes'],
			isset( $stored['palettes'] ) && is_array( $stored['palettes'] ) ? $stored['palettes'] : array()
		);

		$colors = APD_Color_Palettes::normalize_stored( $stored, $legacy_lists );

		$settings['color_library']  = $colors['library'];
		$settings['color_palettes'] = $colors['palettes'];
		$settings['palettes']       = $colors['lists'];

		$settings['limits'] = array_merge(
			$defaults['limits'],
			isset( $stored['limits'] ) && is_array( $stored['limits'] ) ? $stored['limits'] : array()
		);

		$settings['limits']['layouts'] = array_merge(
			$defaults['limits']['layouts'],
			isset( $settings['limits']['layouts'] ) && is_array( $settings['limits']['layouts'] )
				? $settings['limits']['layouts']
				: array()
		);

		foreach ( $defaults['limits']['layouts'] as $layout => $layout_defaults ) {
			$current = isset( $settings['limits']['layouts'][ $layout ] ) && is_array( $settings['limits']['layouts'][ $layout ] )
				? $settings['limits']['layouts'][ $layout ]
				: array();

			$settings['limits']['layouts'][ $layout ] = array_merge( $layout_defaults, $current );
		}

		if ( ! is_array( $settings['formats'] ) ) {
			$settings['formats'] = array();
		}

		if ( ! is_array( $settings['presets'] ) ) {
			$settings['presets'] = array();
		}

		if ( ! is_array( $settings['designs'] ) ) {
			$settings['designs'] = array();
		}

		if ( ! is_array( $settings['fonts'] ) ) {
			$settings['fonts'] = array();
		}

		$settings['catalog'] = array_merge(
			$defaults['catalog'],
			isset( $stored['catalog'] ) && is_array( $stored['catalog'] ) ? $stored['catalog'] : array()
		);

		if ( ! isset( $settings['catalog']['published'] ) || ! is_array( $settings['catalog']['published'] ) ) {
			$settings['catalog']['published'] = $defaults['catalog']['published'];
		} else {
			$published = array_values( $settings['catalog']['published'] );
			$legacy    = array( 'plates-eu', 'plates-us', 'plates-custom', 'holders' );
			$with_color = array( 'plates-eu', 'plates-us', 'plates-custom', 'plates-color', 'holders' );

			if ( $published === $legacy || $published === $with_color ) {
				foreach ( array( 'plates-color', 'plates-moto', 'plates-suv' ) as $extra ) {
					if ( ! in_array( $extra, $settings['catalog']['published'], true ) ) {
						$settings['catalog']['published'][] = $extra;
					}
				}
			}
		}

		$settings['swatch_display'] = APD_Color_Palettes::sanitize_swatch_display(
			isset( $stored['swatch_display'] ) && is_array( $stored['swatch_display'] )
				? $stored['swatch_display']
				: $defaults['swatch_display']
		);

		return $settings;
	}

	/**
	 * Stable unique ID for settings rows.
	 *
	 * @return string
	 */
	public static function new_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return md5( uniqid( 'apd', true ) );
	}

	/**
	 * Write default settings on first activation.
	 */
	private static function seed_default_options() {
		if ( false === get_option( APD_OPTION_KEY ) ) {
			add_option( APD_OPTION_KEY, self::get_default_settings(), '', false );
		}
	}

	/**
	 * Read plugin headers that WordPress admin also displays.
	 *
	 * @return array<string, string>
	 */
	private static function get_header_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( APD_PLUGIN_FILE, false, false );
	}

	/**
	 * Whether WooCommerce is an active plugin (works during activation).
	 *
	 * @return bool
	 */
	private static function is_woocommerce_plugin_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * WooCommerce version from the loaded constant, or the plugin header as fallback.
	 *
	 * @return string
	 */
	private static function get_woocommerce_version() {
		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		}

		$wc_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

		if ( ! is_readable( $wc_file ) ) {
			return '';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( $wc_file, false, false );

		return isset( $data['Version'] ) ? (string) $data['Version'] : '';
	}

	/**
	 * Runtime check: WooCommerce class loaded and version high enough.
	 *
	 * @return bool
	 */
	private function is_woocommerce_ready() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$version = self::get_woocommerce_version();

		return '' !== $version && version_compare( $version, APD_MIN_WC, '>=' );
	}

	/**
	 * Human-readable dependency error for admin notices.
	 *
	 * @return string
	 */
	private function get_dependency_error_message() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return __( 'Auto Plate Designer requires WooCommerce to be installed and active.', 'auto-plate-designer' );
		}

		$version = self::get_woocommerce_version();

		return sprintf(
			/* translators: 1: required WooCommerce version, 2: current WooCommerce version */
			__( 'Auto Plate Designer requires WooCommerce %1$s or higher. Detected version: %2$s.', 'auto-plate-designer' ),
			APD_MIN_WC,
			'' !== $version ? $version : __( 'unknown', 'auto-plate-designer' )
		);
	}

	/**
	 * Deactivate this plugin and stop activation with an admin-readable message.
	 *
	 * @param string $message Error shown to the administrator.
	 */
	private static function abort_activation( $message ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( APD_PLUGIN_BASENAME );

		wp_die(
			esc_html( $message ),
			esc_html__( 'Plugin Activation Error', 'auto-plate-designer' ),
			array( 'back_link' => true )
		);
	}
}
