<?php
/**
 * WordPress Settings page: Auto Plate Designer.
 *
 * Tabbed CRUD with nonce and capability checks. Admin CSS/JS load only here.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin settings screen and product meta boxes.
 */
final class APD_Admin_Settings {

	const PAGE_SLUG = 'apd-settings';

	const META_ENABLED      = '_apd_enabled';
	const META_FORMAT       = '_apd_format_id';
	const META_DESIGN       = '_apd_design_id';
	const META_LAYOUTS      = '_apd_layouts';
	const META_HIDE_IMAGE   = '_apd_hide_image';
	const META_COLOR_FIELDS = '_apd_color_fields';
	const META_PALETTE_IDS  = '_apd_palette_ids';
	const META_DEFAULT_TEXT   = '_apd_default_text';
	const META_DEFAULT_TEXT_CYR = '_apd_default_text_cyrillic';
	const META_DEFAULT_SLOGAN = '_apd_default_slogan';
	const META_FR_REGION   = '_apd_fr_region';
	const META_LATIN       = '_apd_script_latin';
	const META_CYRILLIC    = '_apd_script_cyrillic';
	const META_LATIN_FONTS = '_apd_font_ids_latin';
	const META_CYRILLIC_FONTS = '_apd_font_ids_cyrillic';

	const PRODUCT_NONCE_ACTION = 'apd_product_meta';

	/**
	 * Singleton instance.
	 *
	 * @var APD_Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Admin_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register admin hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 64 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes_product', array( $this, 'add_product_meta_box' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
		add_filter( 'manage_product_posts_columns', array( $this, 'product_columns' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'allow_woff2_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_woff2_filetype' ), 10, 4 );
	}

	/**
	 * Top-level menu plus a WooCommerce shortcut to the same screen.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Auto Plate Designer', 'auto-plate-designer' ),
			__( 'Auto Plate Designer', 'auto-plate-designer' ),
			APD_Security::ADMIN_CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-car',
			56
		);

		add_submenu_page(
			'woocommerce',
			__( 'Auto Plate Designer', 'auto-plate-designer' ),
			__( 'Auto Plate Designer', 'auto-plate-designer' ),
			APD_Security::ADMIN_CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option so WordPress knows it exists. Mutations go through
	 * the tab handlers so nested CRUD can be sanitized per field.
	 */
	public function register_setting() {
		register_setting(
			'apd_settings',
			APD_OPTION_KEY,
			array(
				'type'              => 'array',
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'protect_registered_option' ),
			)
		);
	}

	/**
	 * Block unauthenticated option updates via the generic Settings API form.
	 *
	 * @param mixed $value Incoming value.
	 * @return mixed
	 */
	public function protect_registered_option( $value ) {
		$cap = APD_Security::require_capability();

		if ( is_wp_error( $cap ) ) {
			return APD_Plugin::get_settings();
		}

		return is_array( $value ) ? $value : APD_Plugin::get_settings();
	}

	/**
	 * Settings CSS/JS only on this plugin screen.
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$allowed_hooks = array(
			'toplevel_page_' . self::PAGE_SLUG,
			'woocommerce_page_' . self::PAGE_SLUG,
		);
		$is_settings   = in_array( $hook, $allowed_hooks, true );
		$screen        = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product    = $screen && 'product' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_settings && ! $is_product ) {
			return;
		}

		if ( $is_settings ) {
		wp_enqueue_media();
		}

		$css_file = APD_PLUGIN_DIR . 'assets/css/admin-settings.css';

		wp_enqueue_style(
			'apd-admin-settings',
			APD_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			is_readable( $css_file ) ? (string) filemtime( $css_file ) : APD_VERSION
		);

		$js_file = APD_PLUGIN_DIR . 'assets/js/admin-settings.js';

		wp_enqueue_script(
			'apd-admin-settings',
			APD_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			is_readable( $js_file ) ? (string) filemtime( $js_file ) : APD_VERSION,
			true
		);

		$caps       = array();
		$sizes      = array();
		$type_caps  = array();
		$text_boxes = array();

		foreach ( APD_Security::allowed_format_types() as $type ) {
			$caps[ $type ]      = APD_Formats::color_field_capabilities( $type );
			$size               = APD_Formats::default_size( $type );
			$sizes[ $type ]     = array( $size['width'], $size['height'] );
			$type_caps[ $type ] = APD_Formats::js_capabilities( $type );
			$box_format = array(
				'width'     => $size['width'],
				'height'    => $size['height'],
				'band_side' => 'left',
			);
			if ( 'atyp_300' === $type ) {
				$box_format['type']      = 'atyp_300';
				$box_format['text_rows'] = 2;
			}
			$text_boxes[ $type ] = APD_Formats::default_text_box( $type, $box_format );
		}

		wp_localize_script(
			'apd-admin-settings',
			'apdAdmin',
			array(
				'confirmDelete'     => __( 'Delete this item? This cannot be undone.', 'auto-plate-designer' ),
				'selectImage'       => __( 'Select image', 'auto-plate-designer' ),
				'selectFont'        => __( 'Select WOFF2 font', 'auto-plate-designer' ),
				'colorCaps'         => $caps,
				'defaultSizes'      => $sizes,
				'typeCapabilities'   => $type_caps,
				'defaultTextBoxes'   => $text_boxes,
				'canvasMaxPx'        => APD_Formats::CANVAS_DISPLAY_MAX_PX,
				'sampleFontFill'     => APD_Formats::SAMPLE_FONT_FILL,
				'adminSamplePainted' => APD_Formats::ADMIN_SAMPLE_PAINTED,
				'adminSampleSuv'     => APD_Formats::ADMIN_SAMPLE_SUV,
				'adminSampleEnglish' => APD_Formats::admin_sample_plate_text( 'uk' ),
				'adminSampleMini'    => APD_Formats::admin_sample_plate_text( 'mini' ),
				'holderImageLabel'  => __( 'Holder photo', 'auto-plate-designer' ),
				'holderImageHelp'   => __( 'The standard car holder is already shown. Upload a PNG, JPEG, or WebP photo to replace it. An SVG plugin is not needed.', 'auto-plate-designer' ),
				'holderImageUrl'    => APD_Formats::bundled_holder_image_url(),
				'photoHolderImages' => array(
					'holder_moto' => APD_Formats::bundled_holder_image_url( 'holder_moto' ),
					'holder_d'    => APD_Formats::bundled_holder_image_url( 'holder_d' ),
				),
				'paletteSlotTypes'  => array( 'color', 'custom', 'eu_plain', 'moto_plain', 'moto_plain_240', 'suv', 'suv_340', 'atyp_300', 'us', 'uk', 'uk_plain' ),
				'holderPlateLabels' => array(
					'holder'      => APD_Formats::format_size_label( array( 'type' => 'holder' ) ),
					'holder_moto' => APD_Formats::format_size_label( array( 'type' => 'holder_moto' ) ),
					'holder_d'    => APD_Formats::format_size_label( array( 'type' => 'holder_d' ) ),
				),
				'usImageLabel'      => __( 'Plate graphic', 'auto-plate-designer' ),
				'usImageHelp'       => __( 'Upload the plate graphic, then drag the text area onto the number hole. Set the character limit above.', 'auto-plate-designer' ),
				'holderStrip'       => APD_Formats::holder_strip_box(),
				'holderStrips'      => array(
					'holder'      => APD_Formats::holder_strip_box( 'holder' ),
					'holder_moto' => APD_Formats::holder_strip_box( 'holder_moto' ),
					'holder_d'    => APD_Formats::holder_strip_box( 'holder_d' ),
				),
				'holderStripRadii'  => array(
					'holder'      => APD_Formats::holder_strip_radius( 'holder' ),
					'holder_moto' => APD_Formats::holder_strip_radius( 'holder_moto' ),
					'holder_d'    => APD_Formats::holder_strip_radius( 'holder_d' ),
				),
				'suvImageLabel'     => __( 'Plate graphic', 'auto-plate-designer' ),
				'suvImageHelp'      => __( 'Upload the full SUV / crossover plate image. Shoppers only change the text in the number area.', 'auto-plate-designer' ),
			)
		);
	}

	/**
	 * Allow WOFF2 uploads for users who can manage these settings.
	 *
	 * @param array<string, string> $mimes MIME map.
	 * @return array<string, string>
	 */
	public function allow_woff2_mime( $mimes ) {
		if ( is_wp_error( APD_Security::require_capability() ) ) {
			return $mimes;
		}

		$mimes['woff2'] = 'font/woff2';

		return $mimes;
	}

	/**
	 * WordPress rejects many font types in wp_check_filetype_and_ext.
	 *
	 * @param array  $data     Filetype data.
	 * @param string $file     Temp path.
	 * @param string $filename Original name.
	 * @param array  $mimes    Allowed mimes.
	 * @return array
	 */
	public function allow_woff2_filetype( $data, $file, $filename, $mimes ) {
		unset( $mimes );

		if ( is_wp_error( APD_Security::require_capability() ) ) {
			return $data;
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'woff2' !== $ext ) {
			return $data;
		}

		if ( is_string( $file ) && '' !== $file && ! APD_Security::is_woff2_file( $file ) ) {
			return $data;
		}

		$data['ext']  = 'woff2';
		$data['type'] = 'font/woff2';

		return $data;
	}

	/**
	 * Handle settings POST / GET delete after nonce + capability checks.
	 */
	public function handle_post() {
		if ( ! is_admin() ) {
			return;
		}

		if ( isset( $_GET['apd_delete'], $_GET['apd_id'], $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->handle_delete();
			return;
		}

		if ( isset( $_GET['apd_duplicate'], $_GET['apd_id'], $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->handle_duplicate();
			return;
		}

		if ( empty( $_POST['apd_settings_action'] ) ) {
			return;
		}

		$cap = APD_Security::require_capability();

		if ( is_wp_error( $cap ) ) {
			wp_die( esc_html( $cap->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_POST['apd_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_settings_nonce'] ) ) : '';
		$check = APD_Security::verify_nonce( $nonce, 'apd_save_settings' );

		if ( is_wp_error( $check ) ) {
			wp_die( esc_html( $check->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$action      = sanitize_key( wp_unslash( $_POST['apd_settings_action'] ) );
		$result      = null;
		$tab         = '';
		$return_args = array();

		switch ( $action ) {
			case 'save_format':
				$posted    = $this->unslash_array( isset( $_POST['apd_format'] ) ? $_POST['apd_format'] : array() );
				$uploaded  = $this->attach_uploaded_base_image( $posted );
				$result    = is_wp_error( $uploaded ) ? $uploaded : APD_Formats::save( $uploaded );
				$tab       = 'formats';
				$posted_id = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
				if ( is_wp_error( $result ) ) {
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				}
				break;
			case 'save_preset':
				$posted = $this->unslash_array( isset( $_POST['apd_preset'] ) ? $_POST['apd_preset'] : array() );
				$result = APD_Presets::save( $posted );
				$tab    = 'presets';
				if ( is_wp_error( $result ) ) {
					$posted_id   = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				}
				break;
			case 'save_design':
				$posted = $this->unslash_array( isset( $_POST['apd_design'] ) ? $_POST['apd_design'] : array() );
				$result = APD_Designs::save( $posted );
				$tab    = 'designs';
				if ( is_wp_error( $result ) ) {
					$posted_id   = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				}
				break;
			case 'save_palette':
				$posted = $this->unslash_array( isset( $_POST['apd_palette'] ) ? $_POST['apd_palette'] : array() );
				$result = APD_Color_Palettes::save( $posted );
				$tab    = 'palette';
				if ( is_wp_error( $result ) ) {
					$posted_id   = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				}
				break;
			case 'save_swatch_display':
				$result = APD_Color_Palettes::save_swatch_display( $this->unslash_array( isset( $_POST['apd_swatch'] ) ? $_POST['apd_swatch'] : array() ) );
				$tab    = 'palette';
				break;
			case 'save_color':
				$posted = $this->unslash_array( isset( $_POST['apd_color'] ) ? $_POST['apd_color'] : array() );
				$color_id = APD_Color_Palettes::save_library_color(
					isset( $posted['hex'] ) ? (string) $posted['hex'] : '',
					isset( $posted['label'] ) ? (string) $posted['label'] : '',
					isset( $posted['id'] ) ? (string) $posted['id'] : ''
				);

				if ( is_wp_error( $color_id ) ) {
					$result      = $color_id;
					$posted_id   = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
					$return_args = '' !== $posted_id ? array( 'edit_color' => $posted_id ) : array( 'add_color' => '1' );
				} else {
					$result = true;

					if ( empty( $posted['id'] ) && ! empty( $posted['palette_ids'] ) && is_array( $posted['palette_ids'] ) ) {
						$attached = APD_Color_Palettes::attach_color_to_palettes( $color_id, $posted['palette_ids'] );
						$result   = is_wp_error( $attached ) ? $attached : true;
					}
				}

				$tab = 'palette';
				break;
			case 'save_font':
				$result = $this->save_font_row();
				$tab    = 'fonts';
				if ( is_wp_error( $result ) ) {
					$return_args = array( 'add' => '1' );
				}
				break;
			case 'save_limits':
				$result = $this->save_limits_tab();
				$tab    = 'limits';
				break;
			default:
				return;
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect( $tab, 'error', $result->get_error_message(), $return_args );
		}

		$this->redirect( $tab, 'updated', __( 'Settings saved.', 'auto-plate-designer' ), $return_args );
	}

	/**
	 * Render the settings screen.
	 */
	public function render_page() {
		$cap = APD_Security::require_capability();

		if ( is_wp_error( $cap ) ) {
			wp_die( esc_html( $cap->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$tabs        = $this->tabs();
		$current_tab = $this->current_tab();
		$notice      = $this->consume_notice();

		echo '<div class="wrap apd-wrap">';
		echo '<h1>' . esc_html__( 'Auto Plate Designer', 'auto-plate-designer' ) . '</h1>';

		if ( is_array( $notice ) ) {
			$class = 'updated' === $notice['type'] ? 'notice-success' : 'notice-error';
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $notice['message'] )
			);
		}

		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
				esc_url( $this->tab_url( $slug ) ),
				$current_tab === $slug ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';

		$template = APD_PLUGIN_DIR . 'templates/admin/' . $current_tab . '-tab.php';

		if ( is_readable( $template ) ) {
			$this->render_tab_template( $template, $current_tab );
		}

		echo '</div>';
	}

	/**
	 * Product meta box: enable configurator, format, layouts.
	 */
	public function add_product_meta_box() {
		add_meta_box(
			'apd_product_config',
			__( 'Plate Configurator', 'auto-plate-designer' ),
			array( $this, 'render_product_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Product meta box markup.
	 *
	 * @param WP_Post $post Product post.
	 */
	public function render_product_meta_box( $post ) {
		wp_nonce_field( self::PRODUCT_NONCE_ACTION, 'apd_product_nonce' );

		$enabled      = 'yes' === get_post_meta( $post->ID, self::META_ENABLED, true );
		$format       = (string) get_post_meta( $post->ID, self::META_FORMAT, true );
		$hide_image   = 'no' !== get_post_meta( $post->ID, self::META_HIDE_IMAGE, true );
		$default_text = (string) get_post_meta( $post->ID, self::META_DEFAULT_TEXT, true );
		$design_id    = (string) get_post_meta( $post->ID, self::META_DESIGN, true );
		$formats    = APD_Formats::all();

		if ( '' === $format && isset( $_GET['apd_format'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_text_field( wp_unslash( $_GET['apd_format'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( null !== APD_Formats::get( $requested ) ) {
				$format  = $requested;
				$enabled = true;
			}
		}

		if ( '' === $design_id && isset( $_GET['apd_design'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_design = sanitize_text_field( wp_unslash( $_GET['apd_design'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( is_array( APD_Designs::get( $requested_design ) ) ) {
				$design_id = $requested_design;
				$enabled   = true;
			}
		}

		echo '<div class="apd-product-metabox">';
		echo '<p><label class="apd-choice"><input type="checkbox" name="apd_enabled" value="1" ' . checked( $enabled, true, false ) . '> ';
		echo esc_html__( 'Enable plate configurator', 'auto-plate-designer' );
		echo '</label></p>';

		echo '<div class="apd-product-metabox__options" data-apd-product-options' . ( $enabled ? '' : ' hidden' ) . '>';

		if ( $enabled && function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $post->ID );

			if ( $wc_product instanceof WC_Product && ! $wc_product->is_purchasable() ) {
				echo '<p class="notice notice-warning inline" style="margin:0 0 12px;padding:8px 12px;">';
				echo esc_html__( 'WooCommerce will not show Add to cart or the configurator until this product has a price.', 'auto-plate-designer' );
				echo '</p>';
			}
		}

		echo '<p><label class="apd-choice"><input type="checkbox" name="apd_hide_image" value="1" ' . checked( $hide_image, true, false ) . '> ';
		echo esc_html__( 'Hide product image on the product page', 'auto-plate-designer' );
		echo '</label></p>';
		echo '<p class="description">' . esc_html__( 'Keeps the shop thumbnail. The live preview replaces the gallery on the product page.', 'auto-plate-designer' ) . '</p>';

		echo '<p><label for="apd_format_id">' . esc_html__( 'Format', 'auto-plate-designer' ) . '</label><br>';
		echo '<select name="apd_format_id" id="apd_format_id" class="widefat">';
		echo '<option value="">' . esc_html__( 'Select a format', 'auto-plate-designer' ) . '</option>';

		foreach ( $formats as $item ) {
			$item_limits = APD_Formats::us_side_limits( $item );
			$group_limits = array( '', '', '' );
			if ( APD_Formats::is_french( $item['type'] ) ) {
				$group_limits = APD_Formats::french_group_limits( $item );
			} elseif ( APD_Formats::is_austria( $item['type'] ) ) {
				$group_limits = APD_Formats::austria_limits( $item );
			}
			printf(
				'<option value="%1$s" data-apd-type="%2$s" data-apd-rows="%11$s" data-apd-no-frame="%6$s" data-apd-split="%7$s" data-apd-photo="%8$s" data-apd-left="%9$s" data-apd-right="%10$s" data-apd-g1="%12$s" data-apd-g2="%13$s" data-apd-g3="%14$s" data-apd-slogan-max="%15$s" data-apd-scripts="%16$s" data-apd-font-ids="%17$s" data-apd-fonts-all="%18$s" %3$s>%4$s (%5$s)</option>',
				esc_attr( $item['id'] ),
				esc_attr( $item['type'] ),
				selected( $format, $item['id'], false ),
				esc_html( $item['name'] ),
				esc_html( strtoupper( $item['type'] ) ),
				! empty( $item['no_frame'] ) ? '1' : '0',
				! empty( $item['split_text'] ) ? '1' : '0',
				! empty( $item['base_image_id'] ) ? '1' : '0',
				esc_attr( (string) $item_limits[0] ),
				esc_attr( (string) $item_limits[1] ),
				APD_Formats::format_uses_two_rows( $item ) ? '1' : '0',
				esc_attr( (string) $group_limits[0] ),
				esc_attr( isset( $group_limits[1] ) ? (string) $group_limits[1] : '' ),
				esc_attr( isset( $group_limits[2] ) ? (string) $group_limits[2] : '' ),
				esc_attr( (string) ( APD_Formats::is_english( $item['type'] ) ? APD_Formats::slogan_limit( $item ) : 0 ) ),
				APD_Formats::offers_script( $item['type'] ) ? '1' : '0',
				esc_attr( isset( $item['font_ids'] ) && is_array( $item['font_ids'] ) ? implode( ',', $item['font_ids'] ) : '' ),
				! empty( $item['font_ids_all'] ) ? '1' : '0'
			);
		}

		echo '</select></p>';

		$script_notice = get_transient( 'apd_product_script_notice_' . get_current_user_id() );
		if ( is_string( $script_notice ) && '' !== $script_notice ) {
			delete_transient( 'apd_product_script_notice_' . get_current_user_id() );
			echo '<p class="notice notice-warning inline" style="margin:0 0 12px;padding:8px 12px;">' . esc_html( $script_notice ) . '</p>';
		}

		$latin_on = 'yes' === (string) get_post_meta( $post->ID, self::META_LATIN, true ) || ( '' === (string) get_post_meta( $post->ID, self::META_LATIN, true ) && '' === (string) get_post_meta( $post->ID, self::META_CYRILLIC, true ) );
		$cyr_on   = 'yes' === (string) get_post_meta( $post->ID, self::META_CYRILLIC, true );
		$latin_font_ids = get_post_meta( $post->ID, self::META_LATIN_FONTS, true );
		$cyr_font_ids   = get_post_meta( $post->ID, self::META_CYRILLIC_FONTS, true );
		$latin_font_ids = is_array( $latin_font_ids ) ? array_map( 'strval', $latin_font_ids ) : array();
		$cyr_font_ids   = is_array( $cyr_font_ids ) ? array_map( 'strval', $cyr_font_ids ) : array();
		$library         = APD_Plugin::get_settings();
		$library_fonts   = isset( $library['fonts'] ) && is_array( $library['fonts'] ) ? $library['fonts'] : array();
		$script_format   = '' !== $format ? APD_Formats::get( $format ) : null;
		$script_type     = is_array( $script_format ) && isset( $script_format['type'] ) ? (string) $script_format['type'] : '';
		$script_open     = '' !== $script_type && APD_Formats::offers_script( $script_type );

		echo '<div data-apd-script-panel' . ( $script_open ? '' : ' hidden' ) . '>';
		echo '<p><label class="apd-choice"><input type="checkbox" name="apd_script_latin" value="1" ' . checked( $latin_on, true, false ) . '> ';
		echo esc_html__( 'Latin', 'auto-plate-designer' );
		echo '</label></p>';
		echo '<p><label class="apd-choice"><input type="checkbox" name="apd_script_cyrillic" value="1" ' . checked( $cyr_on, true, false ) . '> ';
		echo esc_html__( 'Cyrillic letters', 'auto-plate-designer' );
		echo '</label></p>';
		echo '<p class="description">' . esc_html__( 'One alphabet shows a sentence on the product. Both show two choices. Existing products stay Latin until you tick Cyrillic.', 'auto-plate-designer' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Latin fonts', 'auto-plate-designer' ) . '</strong></p>';
		echo '<div class="apd-choice-list" data-apd-script-fonts="latin">';
		foreach ( $library_fonts as $library_font ) {
			if ( empty( $library_font['id'] ) ) {
				continue;
			}
			$font_id = (string) $library_font['id'];
			echo '<label class="apd-choice" data-apd-product-font="' . esc_attr( $font_id ) . '"><input type="checkbox" name="apd_font_ids_latin[]" value="' . esc_attr( $font_id ) . '" ' . checked( in_array( $font_id, $latin_font_ids, true ), true, false ) . '> ';
			echo esc_html( APD_Formats::font_shop_label( $library_font ) );
			echo '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'An empty Latin list uses every font on the format.', 'auto-plate-designer' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Cyrillic fonts', 'auto-plate-designer' ) . '</strong></p>';
		echo '<div class="apd-choice-list" data-apd-script-fonts="cyrillic">';
		foreach ( $library_fonts as $library_font ) {
			if ( empty( $library_font['id'] ) ) {
				continue;
			}
			$font_id = (string) $library_font['id'];
			echo '<label class="apd-choice" data-apd-product-font="' . esc_attr( $font_id ) . '"><input type="checkbox" name="apd_font_ids_cyrillic[]" value="' . esc_attr( $font_id ) . '" ' . checked( in_array( $font_id, $cyr_font_ids, true ), true, false ) . '> ';
			echo esc_html( APD_Formats::font_shop_label( $library_font ) );
			echo '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Cyrillic needs at least one font from this list.', 'auto-plate-designer' ) . '</p>';
		echo '</div>';

		$selected_format = '' !== $format ? APD_Formats::get( $format ) : null;
		$selected_type   = is_array( $selected_format ) && isset( $selected_format['type'] ) ? (string) $selected_format['type'] : '';
		$design_open     = 'us' === $selected_type;

		$locked_design  = '' !== $design_id ? APD_Designs::get( $design_id ) : null;
		$locked_limits  = is_array( $locked_design ) ? APD_Formats::us_side_limits( $locked_design ) : array( APD_Formats::US_SIDE_LEFT_MAX, APD_Formats::US_SIDE_RIGHT_MAX );
		$locked_formats = ( is_array( $locked_design ) && isset( $locked_design['allowed_format_ids'] ) && is_array( $locked_design['allowed_format_ids'] ) )
			? implode( ',', $locked_design['allowed_format_ids'] )
			: '';

			printf(
			'<input type="hidden" name="apd_design_id" id="apd_design_id" data-apd-product-design value="%1$s" data-apd-formats="%2$s" data-apd-split="%3$s" data-apd-left="%4$s" data-apd-right="%5$s"%6$s>',
			esc_attr( $design_id ),
			esc_attr( $locked_formats ),
			is_array( $locked_design ) && ! empty( $locked_design['split_text'] ) ? '1' : '0',
			esc_attr( (string) $locked_limits[0] ),
			esc_attr( (string) $locked_limits[1] ),
			$design_open ? '' : ' disabled'
		);

		if ( empty( $formats ) ) {
			echo '<p class="description">' . esc_html__( 'Add formats under WooCommerce → Auto Plate Designer first.', 'auto-plate-designer' ) . '</p>';
		}

		$suv_default     = is_array( $selected_format ) && APD_Formats::format_uses_two_rows( $selected_format );
		$suv_rows        = APD_Formats::suv_plate_rows( $default_text );
		$single_text     = str_replace( array( "\r\n", "\r", "\n" ), ' ', $default_text );
		$side_limits     = ( is_array( $selected_format ) && 'us' === $selected_type )
			? APD_Formats::active_us_side_limits( APD_Formats::frontend_payload( $selected_format ), $design_id )
			: null;
		$sides_default   = is_array( $side_limits );
		$side_source     = str_replace( array( "\r\n", "\r" ), "\n", $default_text );
		$side_parts      = false !== strpos( $side_source, "\n" ) ? explode( "\n", $side_source, 2 ) : array( $default_text, '' );
		$side_left_max   = $sides_default ? (int) $side_limits[0] : APD_Formats::US_SIDE_LEFT_MAX;
		$side_right_max  = $sides_default ? (int) $side_limits[1] : APD_Formats::US_SIDE_RIGHT_MAX;
		$french_default  = is_array( $selected_format ) && APD_Formats::is_french( $selected_type );
		$austria_default = is_array( $selected_format ) && APD_Formats::is_austria( $selected_type );
		$french_values   = $french_default ? APD_Formats::french_field_values( $selected_type, $default_text ) : array( '', '', '' );
		$austria_values  = $austria_default ? APD_Formats::austria_field_values( $default_text ) : array( '', '' );
		$french_limits   = $french_default ? APD_Formats::french_group_limits( $selected_format ) : array( 2, 3, 2 );
		$austria_limits  = $austria_default ? APD_Formats::austria_limits( $selected_format ) : array( 2, 6 );
		$french_temp     = $french_default && APD_Formats::is_french_temporary( $selected_type );
		$cyr_text        = (string) get_post_meta( $post->ID, self::META_DEFAULT_TEXT_CYR, true );
		$both_scripts    = $script_open && $latin_on && $cyr_on;
		$only_cyrillic   = $script_open && $cyr_on && ! $latin_on;
		if ( $both_scripts && '' === trim( $cyr_text ) && '' !== trim( $default_text ) && true === APD_Security::validate_plate_script( $default_text, 'cyrillic' ) && is_wp_error( APD_Security::validate_plate_script( $default_text, 'latin' ) ) ) {
			$cyr_text     = $default_text;
			$default_text = '';
		}
		$cyr_single      = str_replace( array( "\r\n", "\r", "\n" ), ' ', $cyr_text );
		$cyr_rows        = APD_Formats::suv_plate_rows( $cyr_text );
		$single_text     = str_replace( array( "\r\n", "\r", "\n" ), ' ', $default_text );
		$suv_rows        = APD_Formats::suv_plate_rows( $default_text );
		$single_open     = ! $suv_default && ! $sides_default && ! $french_default && ! $austria_default;
		$english_product = is_array( $selected_format ) && APD_Formats::is_english( $selected_type );
		$slogan_max      = $english_product ? APD_Formats::slogan_limit( $selected_format ) : APD_Formats::UK_SLOGAN_MAX;
		if ( $slogan_max < 1 ) {
			$slogan_max = APD_Formats::UK_SLOGAN_MAX;
		}
		$default_slogan = (string) get_post_meta( $post->ID, self::META_DEFAULT_SLOGAN, true );

		echo '<p data-apd-default-single' . ( $single_open ? '' : ' hidden' ) . '><label for="apd_default_text">';
		echo '<span data-apd-latin-only' . ( ( $both_scripts || $only_cyrillic ) ? ' hidden' : '' ) . '>' . esc_html__( 'Initial plate text', 'auto-plate-designer' ) . '</span>';
		echo '<span data-apd-latin-both' . ( $both_scripts ? '' : ' hidden' ) . '>' . esc_html__( 'Latin', 'auto-plate-designer' ) . '</span>';
		echo '<span data-apd-cyr-only' . ( $only_cyrillic ? '' : ' hidden' ) . '>' . esc_html__( 'Cyrillic letters', 'auto-plate-designer' ) . '</span>';
		echo '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text" name="apd_default_text" value="' . esc_attr( $single_text ) . '" maxlength="32" autocomplete="off"' . ( $single_open ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p data-apd-cyrillic-defaults="single"' . ( ( $both_scripts && $single_open ) ? '' : ' hidden' ) . '><label for="apd_default_text_cyrillic">' . esc_html__( 'Cyrillic letters', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_cyrillic" name="apd_default_text_cyrillic" value="' . esc_attr( $cyr_single ) . '" maxlength="32" autocomplete="off"' . ( ( $both_scripts && $single_open ) ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p data-apd-default-slogan' . ( $english_product ? '' : ' hidden' ) . '><label for="apd_default_slogan">' . esc_html__( 'Initial slogan', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_slogan" name="apd_default_slogan" value="' . esc_attr( $default_slogan ) . '" maxlength="' . esc_attr( (string) $slogan_max ) . '" autocomplete="off"' . ( $english_product ? '' : ' disabled' ) . '>';
		echo '<span class="description">' . esc_html__( 'Shown on the product page until the shopper types a slogan. Leave blank for no slogan.', 'auto-plate-designer' ) . '</span>';
		echo '</p>';
		echo '<div class="apd-default-sides" data-apd-default-sides' . ( $sides_default ? '' : ' hidden' ) . '>';
		echo '<p><label for="apd_default_text_left">' . esc_html__( 'Left text', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_left" name="apd_default_text_left" value="' . esc_attr( $side_parts[0] ) . '" maxlength="' . esc_attr( (string) $side_left_max ) . '" autocomplete="off"' . ( $sides_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_text_right">' . esc_html__( 'Right text', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_right" name="apd_default_text_right" value="' . esc_attr( isset( $side_parts[1] ) ? $side_parts[1] : '' ) . '" maxlength="' . esc_attr( (string) $side_right_max ) . '" autocomplete="off"' . ( $sides_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<p data-apd-latin-both' . ( ( $both_scripts && $suv_default ) ? '' : ' hidden' ) . '><strong>' . esc_html__( 'Latin', 'auto-plate-designer' ) . '</strong></p>';
		echo '<div class="apd-default-rows" data-apd-default-rows' . ( $suv_default ? '' : ' hidden' ) . '>';
		echo '<p><label for="apd_default_text_row_1">' . esc_html__( 'First row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_row_1" name="apd_default_text_row_1" value="' . esc_attr( $suv_rows[0] ) . '" maxlength="32" autocomplete="off"' . ( $suv_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_text_row_2">' . esc_html__( 'Second row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_row_2" name="apd_default_text_row_2" value="' . esc_attr( $suv_rows[1] ) . '" maxlength="32" autocomplete="off"' . ( $suv_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<div data-apd-cyrillic-defaults="rows"' . ( ( $both_scripts && $suv_default ) ? '' : ' hidden' ) . '>';
		echo '<p><strong>' . esc_html__( 'Cyrillic letters', 'auto-plate-designer' ) . '</strong></p>';
		echo '<p><label for="apd_default_text_cyr_row_1">' . esc_html__( 'First row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_cyr_row_1" name="apd_default_text_cyr_row_1" value="' . esc_attr( $cyr_rows[0] ) . '" maxlength="32" autocomplete="off"' . ( ( $both_scripts && $suv_default ) ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_text_cyr_row_2">' . esc_html__( 'Second row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_cyr_row_2" name="apd_default_text_cyr_row_2" value="' . esc_attr( $cyr_rows[1] ) . '" maxlength="32" autocomplete="off"' . ( ( $both_scripts && $suv_default ) ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<p class="description" data-apd-script-text-help' . ( $both_scripts ? '' : ' hidden' ) . '>' . esc_html__( 'Each alphabet keeps its own starting text. The product page shows the text for the alphabet the customer chooses.', 'auto-plate-designer' ) . '</p>';
		echo '<div class="apd-default-french" data-apd-default-french' . ( $french_default ? '' : ' hidden' ) . '>';
		echo '<p data-apd-fr-field="1"' . ( $french_temp ? ' hidden' : '' ) . '><label for="apd_default_fr_1">' . esc_html__( 'Letters', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_fr_1" name="apd_default_fr_1" value="' . esc_attr( $french_temp ? '' : (string) $french_values[0] ) . '" maxlength="' . esc_attr( (string) $french_limits[0] ) . '" autocomplete="off"' . ( ( $french_default && ! $french_temp ) ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p data-apd-fr-fixed' . ( $french_temp ? '' : ' hidden' ) . '><label>' . esc_html__( 'Letters', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" value="' . esc_attr( APD_Formats::FR_WW_MARK ) . '" readonly>';
		echo '</p>';
		echo '<p><label for="apd_default_fr_2">' . esc_html__( 'Numbers', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_fr_2" name="apd_default_fr_2" value="' . esc_attr( (string) ( $french_temp ? $french_values[0] : ( isset( $french_values[1] ) ? $french_values[1] : '' ) ) ) . '" maxlength="' . esc_attr( (string) $french_limits[1] ) . '" autocomplete="off"' . ( $french_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_fr_3">' . esc_html__( 'Letters', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_fr_3" name="apd_default_fr_3" value="' . esc_attr( (string) ( $french_temp ? ( isset( $french_values[1] ) ? $french_values[1] : '' ) : ( isset( $french_values[2] ) ? $french_values[2] : '' ) ) ) . '" maxlength="' . esc_attr( (string) $french_limits[2] ) . '" autocomplete="off"' . ( $french_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		$region_default = APD_Formats::is_french_region( $selected_type );
		$region_value   = $region_default ? (string) get_post_meta( $post->ID, self::META_FR_REGION, true ) : '';
		if ( $region_default && '' === $region_value ) {
			$region_value = APD_Formats::FR_REGION_SAMPLE;
		}
		echo '<p data-apd-fr-region' . ( $region_default ? '' : ' hidden' ) . '><label for="apd_default_fr_region">' . esc_html__( 'Region', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_fr_region" name="apd_default_fr_region" value="' . esc_attr( $region_value ) . '" maxlength="' . esc_attr( (string) APD_Formats::FR_REGION_MAX ) . '" inputmode="numeric" autocomplete="off"' . ( $region_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<div class="apd-default-austria" data-apd-default-austria' . ( $austria_default ? '' : ' hidden' ) . '>';
		echo '<p><label for="apd_default_at_1">' . esc_html__( 'Letters', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_at_1" name="apd_default_at_1" value="' . esc_attr( (string) $austria_values[0] ) . '" maxlength="' . esc_attr( (string) $austria_limits[0] ) . '" autocomplete="off"' . ( $austria_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_at_2">' . esc_html__( 'Registration', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_at_2" name="apd_default_at_2" value="' . esc_attr( isset( $austria_values[1] ) ? (string) $austria_values[1] : '' ) . '" maxlength="' . esc_attr( (string) $austria_limits[1] ) . '" autocomplete="off"' . ( $austria_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Shown on the product page until the shopper types their own plate. Leave blank to use the format sample.', 'auto-plate-designer' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Colors come from the palettes chosen on this format.', 'auto-plate-designer' ) . '</p>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Save product configurator meta.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_product_meta( $product_id ) {
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$nonce = isset( $_POST['apd_product_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_product_nonce'] ) ) : '';
		$check = APD_Security::verify_nonce( $nonce, self::PRODUCT_NONCE_ACTION );

		if ( is_wp_error( $check ) ) {
			return;
		}

		$enabled = isset( $_POST['apd_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, self::META_ENABLED, $enabled );

		$hide_image = isset( $_POST['apd_hide_image'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, self::META_HIDE_IMAGE, $hide_image );

		$format_id = isset( $_POST['apd_format_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_format_id'] ) ) : '';

		if ( '' !== $format_id && null === APD_Formats::get( $format_id ) ) {
			$format_id = '';
		}

		update_post_meta( $product_id, self::META_FORMAT, $format_id );

		$format_type = '';
		$format_row  = null;

		if ( '' !== $format_id ) {
			$format_row  = APD_Formats::get( $format_id );
			$format_type = is_array( $format_row ) && isset( $format_row['type'] ) ? (string) $format_row['type'] : '';
		}

		if ( ! APD_Formats::offers_script( $format_type ) ) {
			delete_post_meta( $product_id, self::META_LATIN );
			delete_post_meta( $product_id, self::META_CYRILLIC );
			delete_post_meta( $product_id, self::META_LATIN_FONTS );
			delete_post_meta( $product_id, self::META_CYRILLIC_FONTS );
		} else {
			$allowed_fonts = array();

			if ( is_array( $format_row ) ) {
				if ( ! empty( $format_row['font_ids_all'] ) ) {
					$settings = APD_Plugin::get_settings();
					$library  = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();

					foreach ( $library as $library_font ) {
						if ( ! empty( $library_font['id'] ) ) {
							$allowed_fonts[] = (string) $library_font['id'];
						}
					}
				} elseif ( isset( $format_row['font_ids'] ) && is_array( $format_row['font_ids'] ) ) {
					$allowed_fonts = array_map( 'strval', $format_row['font_ids'] );
				}
			}

			$keep_font = static function ( $key ) use ( $allowed_fonts ) {
				$posted = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$clean  = array();

				if ( ! is_array( $posted ) ) {
					return $clean;
				}

				foreach ( $posted as $font_id ) {
					$font_id = sanitize_text_field( (string) $font_id );

					if ( '' !== $font_id && in_array( $font_id, $allowed_fonts, true ) ) {
						$clean[] = $font_id;
					}
				}

				return array_values( array_unique( $clean ) );
			};

			$latin_fonts = $keep_font( 'apd_font_ids_latin' );
			$cyr_fonts   = $keep_font( 'apd_font_ids_cyrillic' );
			$latin_yes   = isset( $_POST['apd_script_latin'] );
			$cyr_yes     = isset( $_POST['apd_script_cyrillic'] );

			if ( $cyr_yes && empty( $cyr_fonts ) ) {
				$cyr_yes = false;
				set_transient(
					'apd_product_script_notice_' . get_current_user_id(),
					__( 'Cyrillic needs at least one font from this list.', 'auto-plate-designer' ),
					MINUTE_IN_SECONDS
				);
			}

			if ( ! $latin_yes && ! $cyr_yes ) {
				$latin_yes = true;
			}

			update_post_meta( $product_id, self::META_LATIN, $latin_yes ? 'yes' : 'no' );
			update_post_meta( $product_id, self::META_CYRILLIC, $cyr_yes ? 'yes' : 'no' );
			update_post_meta( $product_id, self::META_LATIN_FONTS, $latin_fonts );
			update_post_meta( $product_id, self::META_CYRILLIC_FONTS, $cyr_fonts );
		}

		$design_id = isset( $_POST['apd_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_design_id'] ) ) : '';
		$matches   = false;

		if ( 'us' === $format_type && '' !== $design_id && is_array( $format_row ) ) {
			foreach ( APD_Designs::active_for_format( $format_row ) as $candidate ) {
				if ( isset( $candidate['id'] ) && (string) $candidate['id'] === $design_id ) {
					$matches = true;
					break;
				}
			}
		}

		if ( ! $matches ) {
			$design_id = '';
		}

		if ( '' === $design_id ) {
			delete_post_meta( $product_id, self::META_DESIGN );
		} else {
			update_post_meta( $product_id, self::META_DESIGN, $design_id );
		}

		$multiline   = is_array( $format_row ) && ! empty( $format_row['multiline'] );
		$max_chars   = APD_Formats::is_holder( $format_type )
			? APD_Formats::holder_text_limit()
			: ( is_array( $format_row ) && isset( $format_row['max_chars'] ) ? (int) $format_row['max_chars'] : 12 );
		$text_limit  = $max_chars > 0 ? $max_chars : 12;

		$side_limits = ( is_array( $format_row ) && 'us' === $format_type )
			? APD_Formats::active_us_side_limits( APD_Formats::frontend_payload( $format_row ), $design_id )
			: null;

		if ( is_array( $format_row ) && APD_Formats::format_uses_two_rows( $format_row ) ) {
			$row_limits = APD_Formats::suv_row_limits( is_array( $format_row ) ? $format_row : array( 'type' => $format_type ) );
			$row1       = isset( $_POST['apd_default_text_row_1'] ) ? (string) wp_unslash( $_POST['apd_default_text_row_1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$row2       = isset( $_POST['apd_default_text_row_2'] ) ? (string) wp_unslash( $_POST['apd_default_text_row_2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_text = APD_Formats::limit_suv_rows( $row1, $row2, $row_limits[0], $row_limits[1] );
			$multiline  = true;
			$text_limit = $row_limits[0] + $row_limits[1] + ( false !== strpos( $clean_text, "\n" ) ? 1 : 0 );
		} elseif ( is_array( $format_row ) && APD_Formats::is_french( $format_type ) ) {
			$fr_groups = APD_Formats::is_french_temporary( $format_type )
				? array(
					isset( $_POST['apd_default_fr_2'] ) ? (string) wp_unslash( $_POST['apd_default_fr_2'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					isset( $_POST['apd_default_fr_3'] ) ? (string) wp_unslash( $_POST['apd_default_fr_3'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				)
				: array(
					isset( $_POST['apd_default_fr_1'] ) ? (string) wp_unslash( $_POST['apd_default_fr_1'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					isset( $_POST['apd_default_fr_2'] ) ? (string) wp_unslash( $_POST['apd_default_fr_2'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					isset( $_POST['apd_default_fr_3'] ) ? (string) wp_unslash( $_POST['apd_default_fr_3'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				);
			$fr_built = APD_Formats::french_posted_text(
				$format_row,
				$fr_groups,
				array(
					'month' => '05',
					'year'  => '26',
				)
			);
			$clean_text = is_wp_error( $fr_built ) ? '' : $fr_built['text'];
			$multiline  = false;
			$text_limit = isset( $format_row['max_chars'] ) ? (int) $format_row['max_chars'] : 12;
			if ( APD_Formats::is_french_region( $format_type ) ) {
				$region_code = APD_Formats::french_region_code( isset( $_POST['apd_default_fr_region'] ) ? (string) wp_unslash( $_POST['apd_default_fr_region'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				update_post_meta( $product_id, self::META_FR_REGION, $region_code );
			} else {
				delete_post_meta( $product_id, self::META_FR_REGION );
			}
		} elseif ( is_array( $format_row ) && APD_Formats::is_austria( $format_type ) ) {
			$at_left  = isset( $_POST['apd_default_at_1'] ) ? (string) wp_unslash( $_POST['apd_default_at_1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$at_right = isset( $_POST['apd_default_at_2'] ) ? (string) wp_unslash( $_POST['apd_default_at_2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$at_built = APD_Formats::austria_posted_text( $format_row, $at_left, $at_right );
			$clean_text = is_wp_error( $at_built ) ? '' : $at_built['text'];
			$multiline  = true;
			$text_limit = function_exists( 'mb_strlen' ) ? mb_strlen( $clean_text, 'UTF-8' ) : strlen( $clean_text );
			if ( $text_limit < 1 ) {
				$text_limit = 1;
			}
		} elseif ( is_array( $side_limits ) ) {
			$side_left  = isset( $_POST['apd_default_text_left'] ) ? (string) wp_unslash( $_POST['apd_default_text_left'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$side_right = isset( $_POST['apd_default_text_right'] ) ? (string) wp_unslash( $_POST['apd_default_text_right'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_text = APD_Formats::limit_suv_rows( $side_left, $side_right, $side_limits[0], $side_limits[1] );
			$multiline  = true;
			$text_limit = $side_limits[0] + $side_limits[1] + ( false !== strpos( $clean_text, "\n" ) ? 1 : 0 );
		} else {
			$posted_text = isset( $_POST['apd_default_text'] ) ? (string) wp_unslash( $_POST['apd_default_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_text  = APD_Security::sanitize_plate_text( $posted_text, $multiline );
			if ( $max_chars > 0 ) {
				$length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean_text, 'UTF-8' ) : strlen( $clean_text );
				if ( $length > $max_chars ) {
					$clean_text = function_exists( 'mb_substr' ) ? mb_substr( $clean_text, 0, $max_chars, 'UTF-8' ) : substr( $clean_text, 0, $max_chars );
				}
			}
		}

		$this->save_script_plate_text( $product_id, $format_row, $format_type, $clean_text, $text_limit, $multiline );

		if ( APD_Formats::is_english( $format_type ) && is_array( $format_row ) ) {
			$posted_slogan = isset( $_POST['apd_default_slogan'] ) ? (string) wp_unslash( $_POST['apd_default_slogan'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$slogan_clean  = APD_Security::sanitize_plate_text( $posted_slogan, false );
			$slogan_max    = APD_Formats::slogan_limit( $format_row );
			if ( $slogan_max > 0 ) {
				$slogan_length = function_exists( 'mb_strlen' ) ? mb_strlen( $slogan_clean, 'UTF-8' ) : strlen( $slogan_clean );
				if ( $slogan_length > $slogan_max ) {
					$slogan_clean = function_exists( 'mb_substr' ) ? mb_substr( $slogan_clean, 0, $slogan_max, 'UTF-8' ) : substr( $slogan_clean, 0, $slogan_max );
				}
			}
			$slogan_ok = APD_Security::validate_plate_text(
				$slogan_clean,
				array(
					'max_length'  => $slogan_max > 0 ? $slogan_max : APD_Formats::UK_SLOGAN_MAX,
					'multiline'   => false,
					'allow_empty' => true,
				)
			);
			update_post_meta( $product_id, self::META_DEFAULT_SLOGAN, is_wp_error( $slogan_ok ) ? '' : $slogan_clean );
		} else {
			delete_post_meta( $product_id, self::META_DEFAULT_SLOGAN );
		}

		if ( ! APD_Formats::is_french( $format_type ) ) {
			delete_post_meta( $product_id, self::META_FR_REGION );
		}
	}

	/**
	 * Two-digit region stored on a French region product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	/**
	 * Alphabets and font lists saved on a product.
	 *
	 * An empty Latin list means every font on the format.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $format     Format row.
	 * @return array{scripts: array<int, string>, latin_fonts: array<int, string>, cyrillic_fonts: array<int, string>}
	 */
	public static function product_script_state( $product_id, $format ) {
		$empty = array(
			'scripts'         => array(),
			'latin_fonts'     => array(),
			'cyrillic_fonts'  => array(),
		);
		$type = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : '';

		if ( ! APD_Formats::offers_script( $type ) ) {
			return $empty;
		}

		$product_id = absint( $product_id );
		$latin      = $product_id ? (string) get_post_meta( $product_id, self::META_LATIN, true ) : '';
		$cyrillic   = $product_id ? (string) get_post_meta( $product_id, self::META_CYRILLIC, true ) : '';
		$scripts    = array();

		if ( '' === $latin && '' === $cyrillic ) {
			$scripts[] = 'latin';
		} else {
			if ( 'yes' === $latin ) {
				$scripts[] = 'latin';
			}

			if ( 'yes' === $cyrillic ) {
				$scripts[] = 'cyrillic';
			}
		}

		if ( empty( $scripts ) ) {
			$scripts[] = 'latin';
		}

		$latin_fonts = $product_id ? get_post_meta( $product_id, self::META_LATIN_FONTS, true ) : array();
		$cyr_fonts   = $product_id ? get_post_meta( $product_id, self::META_CYRILLIC_FONTS, true ) : array();

		return array(
			'scripts'        => $scripts,
			'latin_fonts'    => is_array( $latin_fonts ) ? array_map( 'strval', $latin_fonts ) : array(),
			'cyrillic_fonts' => is_array( $cyr_fonts ) ? array_map( 'strval', $cyr_fonts ) : array(),
		);
	}

	/**
	 * Two-digit region stored on a French region product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function product_region_text( $product_id ) {
		$product_id = absint( $product_id );
		$stored     = $product_id ? (string) get_post_meta( $product_id, self::META_FR_REGION, true ) : '';

		return APD_Formats::french_region_code( $stored );
	}

	/**
	 * Plate text shown on the product page until the shopper edits it.
	 *
	 * @param int                    $product_id Product ID.
	 * @param array<string, mixed>|null $format  Format row.
	 * @return string
	 */
	/**
	 * Keep the Latin and Cyrillic starting texts apart when both alphabets are on.
	 *
	 * @param int                       $product_id  Product ID.
	 * @param array<string, mixed>|null $format_row  Format row.
	 * @param string                    $format_type Format type.
	 * @param string                    $clean_text  Text from the main fields.
	 * @param int                       $text_limit  Character ceiling.
	 * @param bool                      $multiline   Whether newlines are part of the plate.
	 */
	private function save_script_plate_text( $product_id, $format_row, $format_type, $clean_text, $text_limit, $multiline ) {
		$latin_text = $clean_text;
		$cyr_text   = '';
		$latin_on   = false;
		$cyr_on     = false;

		if ( APD_Formats::offers_script( $format_type ) ) {
			$latin_on = 'yes' === (string) get_post_meta( $product_id, self::META_LATIN, true );
			$cyr_on   = 'yes' === (string) get_post_meta( $product_id, self::META_CYRILLIC, true );

			if ( $cyr_on && $latin_on ) {
				$cyr_text = $this->posted_cyrillic_default( $format_row, $format_type );

				if ( is_array( $format_row ) && APD_Formats::format_uses_two_rows( $format_row ) ) {
					$row_limits = APD_Formats::suv_row_limits( $format_row );
					$text_limit = $row_limits[0] + $row_limits[1] + 1;
				}
			} elseif ( $cyr_on ) {
				$cyr_text   = $clean_text;
				$latin_text = '';
			}
		}

		$latin_text = $this->accept_script_default( $latin_text, $latin_on && $cyr_on ? 'latin' : '', $text_limit, $multiline );
		$cyr_text   = $this->accept_script_default( $cyr_text, $cyr_on ? 'cyrillic' : '', $text_limit, $multiline );

		update_post_meta( $product_id, self::META_DEFAULT_TEXT, $latin_text );

		if ( '' === $cyr_text ) {
			delete_post_meta( $product_id, self::META_DEFAULT_TEXT_CYR );
		} else {
			update_post_meta( $product_id, self::META_DEFAULT_TEXT_CYR, $cyr_text );
		}
	}

	/**
	 * Cyrillic starting text posted beside the Latin fields.
	 *
	 * @param array<string, mixed>|null $format_row  Format row.
	 * @param string                    $format_type Format type.
	 * @return string
	 */
	private function posted_cyrillic_default( $format_row, $format_type ) {
		if ( is_array( $format_row ) && APD_Formats::format_uses_two_rows( $format_row ) ) {
			$limits = APD_Formats::suv_row_limits( $format_row );
			$row1   = isset( $_POST['apd_default_text_cyr_row_1'] ) ? (string) wp_unslash( $_POST['apd_default_text_cyr_row_1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$row2   = isset( $_POST['apd_default_text_cyr_row_2'] ) ? (string) wp_unslash( $_POST['apd_default_text_cyr_row_2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			return APD_Formats::limit_suv_rows( $row1, $row2, $limits[0], $limits[1] );
		}

		$posted = isset( $_POST['apd_default_text_cyrillic'] ) ? (string) wp_unslash( $_POST['apd_default_text_cyrillic'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean  = APD_Security::sanitize_plate_text( $posted, false );
		$max    = is_array( $format_row ) && isset( $format_row['max_chars'] ) ? (int) $format_row['max_chars'] : 12;

		if ( $max > 0 ) {
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $clean, 'UTF-8' ) : strlen( $clean );

			if ( $length > $max ) {
				$clean = function_exists( 'mb_substr' ) ? mb_substr( $clean, 0, $max, 'UTF-8' ) : substr( $clean, 0, $max );
			}
		}

		unset( $format_type );

		return $clean;
	}

	/**
	 * Store a starting text only when it matches the alphabet.
	 *
	 * An empty script skips the alphabet check. The admin character class still applies to Latin.
	 *
	 * @param string $text       Plate text.
	 * @param string $script     latin, cyrillic, or empty.
	 * @param int    $text_limit Character ceiling.
	 * @param bool   $multiline  Whether newlines are part of the plate.
	 * @return string
	 */
	private function accept_script_default( $text, $script, $text_limit, $multiline ) {
		$check = APD_Security::validate_plate_text(
			$text,
			array(
				'max_length'       => $text_limit,
				'multiline'        => $multiline,
				'allow_empty'      => true,
				'skip_admin_regex' => 'cyrillic' === $script,
			)
		);

		if ( is_wp_error( $check ) ) {
			if ( '' !== $script && '' !== trim( $text ) ) {
				set_transient(
					'apd_product_script_notice_' . get_current_user_id(),
					$check->get_error_message(),
					MINUTE_IN_SECONDS
				);
			}

			return '';
		}

		if ( '' === $script || '' === trim( $text ) ) {
			return $text;
		}

		$script_check = APD_Security::validate_plate_script( $text, $script );

		if ( is_wp_error( $script_check ) ) {
			set_transient(
				'apd_product_script_notice_' . get_current_user_id(),
				$script_check->get_error_message(),
				MINUTE_IN_SECONDS
			);

			return '';
		}

		return $text;
	}

	/**
	 * Plate text shown on the product until the shopper types their own.
	 *
	 * @param int                       $product_id Product ID.
	 * @param array<string, mixed>|null $format     Format row.
	 * @return string
	 */
	public static function product_initial_text( $product_id, $format = null ) {
		$product_id = absint( $product_id );
		$stored     = $product_id ? trim( (string) get_post_meta( $product_id, self::META_DEFAULT_TEXT, true ) ) : '';

		if ( '' !== $stored ) {
			return $stored;
		}

		if ( ! is_array( $format ) && $product_id ) {
			$format_id = (string) get_post_meta( $product_id, self::META_FORMAT, true );
			$format    = '' !== $format_id ? APD_Formats::get( $format_id ) : null;
		}

		$type = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : 'eu';

		return APD_Formats::sample_plate_text( $type );
	}

	/**
	 * Cyrillic plate text shown until the shopper types their own.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function product_cyrillic_text( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return '';
		}

		return trim( (string) get_post_meta( $product_id, self::META_DEFAULT_TEXT_CYR, true ) );
	}

	/**
	 * Slogan shown on an English product until the shopper edits it.
	 *
	 * @param int                        $product_id Product ID.
	 * @param array<string, mixed>|null  $format     Format row.
	 * @return string
	 */
	public static function product_initial_slogan( $product_id, $format = null ) {
		$product_id = absint( $product_id );

		if ( ! is_array( $format ) && $product_id ) {
			$format_id = (string) get_post_meta( $product_id, self::META_FORMAT, true );
			$format    = '' !== $format_id ? APD_Formats::get( $format_id ) : null;
		}

		$type = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : '';

		if ( ! APD_Formats::is_english( $type ) || ! $product_id ) {
			return '';
		}

		$stored = trim( (string) get_post_meta( $product_id, self::META_DEFAULT_SLOGAN, true ) );
		$max    = APD_Formats::slogan_limit( is_array( $format ) ? $format : array() );

		if ( '' === $stored || $max < 1 ) {
			return $max < 1 ? '' : $stored;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $stored, 'UTF-8' ) : strlen( $stored );

		if ( $length > $max ) {
			$stored = function_exists( 'mb_substr' ) ? mb_substr( $stored, 0, $max, 'UTF-8' ) : substr( $stored, 0, $max );
		}

		return $stored;
	}

	/**
	 * Palette IDs already stored on products that use this format.
	 *
	 * Used once, when an older format has no palettes of its own yet.
	 *
	 * @param string $format_id Format ID.
	 * @return array<int, string>
	 */
	public static function palette_ids_on_products( $format_id ) {
		$format_id = sanitize_text_field( (string) $format_id );
		$out       = array();

		if ( '' === $format_id || ! function_exists( 'get_posts' ) ) {
			return $out;
		}

		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_FORMAT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $format_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $product_ids as $product_id ) {
			$stored = get_post_meta( (int) $product_id, self::META_PALETTE_IDS, true );

			if ( ! is_array( $stored ) ) {
				continue;
			}

			foreach ( $stored as $palette_id ) {
				$out[] = sanitize_text_field( (string) $palette_id );
			}
		}

		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Keep only the design locked on this product.
	 *
	 * USA shoppers do not pick among state graphics. The catalog photo stays
	 * the WooCommerce product image. The plate on the product page is the
	 * picture stored on the design, including that design's text box.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $payload    Format payload.
	 * @return array<string, mixed>
	 */
	public static function locked_design_payload( $product_id, array $payload ) {
		$type = isset( $payload['type'] ) ? (string) $payload['type'] : '';

		if ( ! APD_Formats::uses_plate_designs( $type ) ) {
			return $payload;
		}

		$locked = (string) get_post_meta( absint( $product_id ), self::META_DESIGN, true );
		$kept   = array();

		if ( '' !== $locked && isset( $payload['designs'] ) && is_array( $payload['designs'] ) ) {
			foreach ( $payload['designs'] as $design ) {
				if ( isset( $design['id'] ) && (string) $design['id'] === $locked ) {
					$kept[] = $design;
				}
			}
		}

		$payload['designs'] = $kept;

		return $payload;
	}

	/**
	 * Palettes attached to a product.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $format_id  Format ID override.
	 * @return array<int, string>
	 */
	public static function product_palette_ids( $product_id, $format_id = null ) {
		$product_id = absint( $product_id );

		if ( null === $format_id || '' === $format_id ) {
			$format_id = (string) get_post_meta( $product_id, self::META_FORMAT, true );
		}

		$format = APD_Formats::get( (string) $format_id );
		$type   = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : '';

		if ( is_array( $format ) && APD_Formats::uses_palette_slots( $type ) && isset( $format['palette_slots'] ) && is_array( $format['palette_slots'] ) ) {
			$ids = array();

			foreach ( APD_Formats::palette_slot_keys_for( $type ) as $slot ) {
				$id = isset( $format['palette_slots'][ $slot ] ) ? sanitize_text_field( (string) $format['palette_slots'][ $slot ] ) : '';

				if ( '' !== $id ) {
					$ids[] = $id;
				}
			}

			return array_values( array_unique( $ids ) );
		}

		if ( is_array( $format ) && array_key_exists( 'palette_ids', $format ) ) {
			return APD_Color_Palettes::sanitize_product_ids( $format['palette_ids'], $type );
		}

		if ( $product_id && metadata_exists( 'post', $product_id, self::META_PALETTE_IDS ) ) {
			$stored = get_post_meta( $product_id, self::META_PALETTE_IDS, true );

			return APD_Color_Palettes::sanitize_product_ids( is_array( $stored ) ? $stored : array(), $type );
		}

		$fields = array();

		if ( $product_id && metadata_exists( 'post', $product_id, self::META_COLOR_FIELDS ) ) {
			$stored = get_post_meta( $product_id, self::META_COLOR_FIELDS, true );
			$fields = APD_Formats::sanitize_color_fields( is_array( $stored ) ? $stored : array(), $type );
		}

		$ids = array();

		foreach ( $fields as $field ) {
			if ( isset( APD_Color_Palettes::DEFAULT_PALETTE_IDS[ $field ] ) ) {
				$ids[] = APD_Color_Palettes::DEFAULT_PALETTE_IDS[ $field ];
			}
		}

		return APD_Color_Palettes::sanitize_product_ids( $ids, $type );
	}

	/**
	 * Colors a shopper may pick on this product, grouped by purpose.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, array<int, array<string, string>>>
	 */
	public static function product_offered_colors( $product_id ) {
		$product_id = absint( $product_id );
		$format_id  = $product_id ? (string) get_post_meta( $product_id, self::META_FORMAT, true ) : '';
		$format     = APD_Formats::get( $format_id );
		$type       = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : '';
		$out        = array();

		foreach ( APD_Color_Palettes::purposes() as $purpose ) {
			$out[ $purpose ] = array();
		}

		if ( is_array( $format ) && APD_Formats::uses_palette_slots( $type ) && isset( $format['palette_slots'] ) && is_array( $format['palette_slots'] ) ) {
			foreach ( APD_Formats::palette_slot_keys_for( $type ) as $slot ) {
				$id      = isset( $format['palette_slots'][ $slot ] ) ? sanitize_text_field( (string) $format['palette_slots'][ $slot ] ) : '';
				$palette = '' !== $id ? APD_Color_Palettes::get( $id ) : null;

				if ( ! is_array( $palette ) || empty( $palette['active'] ) ) {
					continue;
				}

				$seen = array();

				foreach ( APD_Color_Palettes::palette_colors( $palette ) as $color ) {
					$hex = isset( $color['hex'] ) ? strtoupper( (string) $color['hex'] ) : '';

					if ( '' === $hex || isset( $seen[ $hex ] ) ) {
						continue;
					}

					$seen[ $hex ] = true;
					$out[ $slot ][] = $color;
				}
			}

			return $out;
		}

		$ids = self::product_palette_ids( $product_id );

		foreach ( array_keys( $out ) as $purpose ) {
			$out[ $purpose ] = APD_Color_Palettes::colors_for_product( $ids, $purpose );
		}

		return $out;
	}

	/**
	 * Shopper-changeable color parts for a product.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $format_id  Format ID override (unsaved prefill).
	 * @return array<int, string>
	 */
	public static function product_color_fields( $product_id, $format_id = null ) {
		$product_id = absint( $product_id );

		if ( null === $format_id || '' === $format_id ) {
			$format_id = (string) get_post_meta( $product_id, self::META_FORMAT, true );
		}

		$format = APD_Formats::get( (string) $format_id );
		$type   = is_array( $format ) && isset( $format['type'] ) ? (string) $format['type'] : '';
		$fields = array();

		if ( is_array( $format ) && APD_Formats::uses_palette_slots( $type ) && isset( $format['palette_slots'] ) && is_array( $format['palette_slots'] ) ) {
			foreach ( APD_Formats::palette_slot_keys_for( $type ) as $slot ) {
				if ( ! empty( $format['palette_slots'][ $slot ] ) ) {
					$fields[] = $slot;
				}
			}

			return APD_Formats::sanitize_color_fields( $fields, $type );
		}

		if ( is_array( $format ) && array_key_exists( 'palette_ids', $format ) ) {
			foreach ( self::product_palette_ids( $product_id, $format_id ) as $palette_id ) {
				$palette = APD_Color_Palettes::get( $palette_id );

				if ( is_array( $palette ) && isset( $palette['purpose'] ) ) {
					$fields[] = $palette['purpose'];
				}
			}

			return APD_Formats::sanitize_color_fields( $fields, $type );
		}

		if ( $product_id && metadata_exists( 'post', $product_id, self::META_PALETTE_IDS ) ) {
			foreach ( self::product_palette_ids( $product_id, $format_id ) as $palette_id ) {
				$palette = APD_Color_Palettes::get( $palette_id );

				if ( is_array( $palette ) && isset( $palette['purpose'] ) ) {
					$fields[] = $palette['purpose'];
				}
			}
		} elseif ( $product_id && metadata_exists( 'post', $product_id, self::META_COLOR_FIELDS ) ) {
			$stored = get_post_meta( $product_id, self::META_COLOR_FIELDS, true );
			$fields = is_array( $stored ) ? $stored : array();
		}

		return APD_Formats::sanitize_color_fields( $fields, $type );
	}

	/**
	 * Extra Products list column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function product_columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'name' === $key ) {
				$out['apd_format'] = __( 'Plate format', 'auto-plate-designer' );
			}
		}

		return $out;
	}

	/**
	 * Render the format column.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Product ID.
	 */
	public function render_product_column( $column, $post_id ) {
		if ( 'apd_format' !== $column ) {
			return;
		}

		if ( 'yes' !== get_post_meta( $post_id, self::META_ENABLED, true ) ) {
			echo '&mdash;';
			return;
		}

		$format_id = (string) get_post_meta( $post_id, self::META_FORMAT, true );
		$format    = '' !== $format_id ? APD_Formats::get( $format_id ) : null;

		if ( ! is_array( $format ) ) {
			echo '&mdash;';
			return;
		}

		echo esc_html( $format['name'] . ' (' . APD_Formats::type_label( $format['type'] ) . ')' );
	}

	/**
	 * Tab slugs and labels.
	 *
	 * @return array<string, string>
	 */
	private function tabs() {
		return array(
			'formats' => __( 'Formats', 'auto-plate-designer' ),
			'presets' => __( 'Country presets', 'auto-plate-designer' ),
			'designs' => __( 'USA designs', 'auto-plate-designer' ),
			'palette' => __( 'Color palette', 'auto-plate-designer' ),
			'fonts'   => __( 'Fonts', 'auto-plate-designer' ),
			'limits'  => __( 'Limits', 'auto-plate-designer' ),
		);
	}

	/**
	 * Current tab slug.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'formats'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = $this->tabs();

		return isset( $tabs[ $tab ] ) ? $tab : 'formats';
	}

	/**
	 * Settings tab URL.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Duplicate URL with nonce.
	 *
	 * @param string $id Format ID.
	 * @return string
	 */
	public function duplicate_url( $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'          => self::PAGE_SLUG,
					'tab'           => 'formats',
					'apd_duplicate' => 'formats',
					'apd_id'        => $id,
				),
				admin_url( 'admin.php' )
			),
			'apd_duplicate_formats_' . $id
		);
	}

	/**
	 * Delete URL with nonce.
	 *
	 * @param string $type formats|presets|fonts|colors|color_palettes.
	 * @param string $id   Row ID.
	 * @return string
	 */
	public function delete_url( $type, $id ) {
		$tab = $type;

		if ( in_array( $type, array( 'colors', 'color_palettes' ), true ) ) {
			$tab = 'palette';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'tab'        => $tab,
					'apd_delete' => $type,
					'apd_id'     => $id,
				),
				admin_url( 'admin.php' )
			),
			'apd_delete_' . $type . '_' . $id
		);
	}

	/**
	 * Include a tab template with helpers in scope.
	 *
	 * @param string $template Path.
	 * @param string $tab      Tab slug.
	 */
	private function render_tab_template( $template, $tab ) {
		$apd_admin = $this;
		$settings  = APD_Plugin::get_settings();

		unset( $tab );

		include $template;
	}

	/**
	 * Store a holder or SUV photo posted with the format form.
	 *
	 * @param array<string, mixed> $posted Format fields.
	 * @return array<string, mixed>|WP_Error
	 */
	private function attach_uploaded_base_image( $posted ) {
		if ( empty( $_FILES['apd_base_image'] ) || ! isset( $_FILES['apd_base_image']['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $posted;
		}

		$error = (int) $_FILES['apd_base_image']['error']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return $posted;
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error(
				'apd_upload',
				__( 'The photo could not be uploaded. Use a PNG, JPEG, or WebP image.', 'auto-plate-designer' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'apd_base_image', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'apd_upload',
				__( 'The photo could not be uploaded. Use a PNG, JPEG, or WebP image.', 'auto-plate-designer' )
			);
		}

		$posted['base_image_id'] = (int) $attachment_id;

		return $posted;
	}

	/**
	 * Handle a signed request to copy one format.
	 */
	private function handle_duplicate() {
		$cap = APD_Security::require_capability();

		if ( is_wp_error( $cap ) ) {
			wp_die( esc_html( $cap->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$id    = isset( $_GET['apd_id'] ) ? sanitize_text_field( wp_unslash( $_GET['apd_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$check = APD_Security::verify_nonce( $nonce, 'apd_duplicate_formats_' . $id );

		if ( is_wp_error( $check ) ) {
			wp_die( esc_html( $check->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$result = APD_Formats::duplicate( $id );

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'formats', 'error', $result->get_error_message() );
		}

		$this->redirect( 'formats', 'updated', __( 'Format duplicated.', 'auto-plate-designer' ) );
	}

	/**
	 * Handle signed delete requests.
	 */
	private function handle_delete() {
		$cap = APD_Security::require_capability();

		if ( is_wp_error( $cap ) ) {
			wp_die( esc_html( $cap->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$type = isset( $_GET['apd_delete'] ) ? sanitize_key( wp_unslash( $_GET['apd_delete'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_GET['apd_id'] ) ? sanitize_text_field( wp_unslash( $_GET['apd_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$check = APD_Security::verify_nonce( $nonce, 'apd_delete_' . $type . '_' . $id );

		if ( is_wp_error( $check ) ) {
			wp_die( esc_html( $check->get_error_message() ), '', array( 'response' => 403 ) );
		}

		$result = null;
		$tab    = 'formats';

		switch ( $type ) {
			case 'formats':
				$result = APD_Formats::delete( $id );
				$tab    = 'formats';
				break;
			case 'presets':
				$result = APD_Presets::delete( $id );
				$tab    = 'presets';
				break;
			case 'designs':
				$result = APD_Designs::delete( $id );
				$tab    = 'designs';
				break;
			case 'fonts':
				$result = $this->delete_font( $id );
				$tab    = 'fonts';
				break;
			case 'color_palettes':
				$result = APD_Color_Palettes::delete( $id );
				$tab    = 'palette';
				break;
			case 'colors':
				$result = APD_Color_Palettes::delete_color( $id );
				$tab    = 'palette';
				break;
			default:
				return;
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect( $tab, 'error', $result->get_error_message() );
		}

		$this->redirect( $tab, 'updated', __( 'Item deleted.', 'auto-plate-designer' ) );
	}

	/**
	 * Save one font family row.
	 *
	 * @return true|WP_Error
	 */
	private function save_font_row() {
		$posted = $this->unslash_array( isset( $_POST['apd_font'] ) ? $_POST['apd_font'] : array() );

		$family_check = APD_Security::validate_admin_label( isset( $posted['family'] ) ? $posted['family'] : '', 60 );

		if ( is_wp_error( $family_check ) ) {
			return $family_check;
		}

		$settings    = APD_Plugin::get_settings();
		$id          = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';
		$existing    = null;
		$found_index = null;

		if ( '' !== $id ) {
			foreach ( $settings['fonts'] as $index => $font ) {
				if ( isset( $font['id'] ) && $font['id'] === $id ) {
					$existing    = $font;
					$found_index = $index;
					break;
				}
			}

			if ( null === $existing ) {
			return new WP_Error(
					'apd_font_missing',
					__( 'Font was not found.', 'auto-plate-designer' )
				);
			}
		}

		$attachment_id = isset( $posted['attachment_id'] ) ? absint( $posted['attachment_id'] ) : 0;

		if ( 0 === $attachment_id && is_array( $existing ) ) {
			$attachment_id = isset( $existing['attachment_id'] ) ? absint( $existing['attachment_id'] ) : 0;
		}

		$font_check = APD_Security::validate_attachment(
			$attachment_id,
			array(
				'allow_font' => true,
				'mimes'      => array( 'font/woff2', 'application/font-woff2' ),
				'optional'   => false,
			)
		);

		if ( is_wp_error( $font_check ) ) {
			return $font_check;
		}

		$note = isset( $posted['note'] ) ? trim( (string) $posted['note'] ) : '';

		if ( '' !== $note ) {
			$note_check = APD_Security::validate_admin_label( $note, 40 );

			if ( is_wp_error( $note_check ) ) {
				return $note_check;
			}

			$note = APD_Security::sanitize_admin_label( $note );
		}

		$style = isset( $posted['style'] ) && 'italic' === $posted['style'] ? 'italic' : 'normal';

		if ( '' === $id ) {
			$id = APD_Plugin::new_id();
		}

		$font = array(
			'id'            => $id,
			'family'        => APD_Security::sanitize_admin_label( (string) $posted['family'] ),
			'note'          => $note,
			'attachment_id' => $attachment_id,
			'weight'        => 400,
			'style'         => $style,
		);

		if ( null !== $found_index ) {
			$settings['fonts'][ $found_index ] = $font;
		} else {
			$settings['fonts'][] = $font;
			$settings['formats'] = APD_Formats::grant_font_to_following_formats(
				isset( $settings['formats'] ) && is_array( $settings['formats'] ) ? $settings['formats'] : array(),
				$id
			);
		}

		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Delete a font row.
	 *
	 * @param string $id Font ID.
	 * @return true|WP_Error
	 */
	private function delete_font( $id ) {
		$settings = APD_Plugin::get_settings();
		$next     = array();
		$found    = false;

		foreach ( $settings['fonts'] as $font ) {
			if ( isset( $font['id'] ) && $font['id'] === $id ) {
				$found = true;
				continue;
			}

			$next[] = $font;
		}

		if ( ! $found ) {
			return new WP_Error(
				'apd_font_missing',
				__( 'Font was not found.', 'auto-plate-designer' )
			);
		}

		$settings['fonts'] = $next;
		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Save the font floor, street-plate wrap lines, and character class.
	 *
	 * @return true|WP_Error
	 */
	private function save_limits_tab() {
		$posted   = $this->unslash_array( isset( $_POST['apd_limits'] ) ? $_POST['apd_limits'] : array() );
		$settings = APD_Plugin::get_settings();

		$min_font = isset( $posted['min_font_size'] ) ? absint( $posted['min_font_size'] ) : 12;

		if ( $min_font < 6 ) {
			$min_font = 6;
		}

		if ( $min_font > 48 ) {
			$min_font = 48;
		}

		$wrap_lines = isset( $posted['wrap_lines'] ) ? absint( $posted['wrap_lines'] ) : 5;

		if ( $wrap_lines < 1 ) {
			$wrap_lines = 1;
		}

		if ( $wrap_lines > 5 ) {
			$wrap_lines = 5;
		}

		$settings['limits']['min_font_size'] = $min_font;
		$settings['limits']['wrap_lines']    = $wrap_lines;
		unset( $settings['limits']['layouts'], $settings['limits']['max_lines'] );

		if ( isset( $posted['char_whitelist'] ) ) {
			$whitelist = (string) $posted['char_whitelist'];
			$wl_check  = APD_Security::validate_admin_char_class( $whitelist );

			if ( is_wp_error( $wl_check ) ) {
				return $wl_check;
			}

			$settings['char_whitelist'] = $whitelist;
		}

		APD_Plugin::save_settings( $settings );

		return true;
	}

	/**
	 * Recursively unslash an array.
	 *
	 * @param mixed $value Raw request value.
	 * @return mixed
	 */
	private function unslash_array( $value ) {
		return wp_unslash( $value );
	}

	/**
	 * Redirect back to a tab with a flash notice.
	 *
	 * @param string $tab     Tab slug.
	 * @param string $type    updated|error.
	 * @param string $message Notice text.
	 */
	private function redirect( $tab, $type, $message, $extra = array() ) {
		set_transient(
			'apd_admin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			30
		);

		$url = $this->tab_url( $tab );

		if ( is_array( $extra ) && ! empty( $extra ) ) {
			$url = add_query_arg( $extra, $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Read and delete the flash notice.
	 *
	 * @return array<string, string>|null
	 */
	private function consume_notice() {
		$key    = 'apd_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( $key );

		return $notice;
	}
}
