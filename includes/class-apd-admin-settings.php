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
	const META_LAYOUTS      = '_apd_layouts';
	const META_HIDE_IMAGE   = '_apd_hide_image';
	const META_COLOR_FIELDS = '_apd_color_fields';
	const META_PALETTE_IDS  = '_apd_palette_ids';
	const META_DEFAULT_TEXT = '_apd_default_text';

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
			$text_boxes[ $type ] = APD_Formats::default_text_box(
				$type,
				array(
					'width'     => $size['width'],
					'height'    => $size['height'],
					'band_side' => 'left',
				)
			);
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
				'adminSampleSuv'    => APD_Formats::ADMIN_SAMPLE_SUV,
				'plateColorLabel'   => __( 'Plate color', 'auto-plate-designer' ),
				'stripColorLabel'   => __( 'White strip color', 'auto-plate-designer' ),
				'holderImageLabel'  => __( 'Holder photo', 'auto-plate-designer' ),
				'holderImageHelp'   => __( 'The standard car holder is already shown. Upload a PNG, JPEG, or WebP photo to replace it. An SVG plugin is not needed.', 'auto-plate-designer' ),
				'holderImageUrl'    => APD_Formats::bundled_holder_image_url(),
				'holderStrip'       => APD_Formats::holder_strip_box(),
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
				} elseif ( '' !== $posted_id && is_array( $result ) && ! empty( $result['id'] ) ) {
					$return_args = array( 'edit' => $result['id'] );
				}
				break;
			case 'save_preset':
				$result = APD_Presets::save( $this->unslash_array( isset( $_POST['apd_preset'] ) ? $_POST['apd_preset'] : array() ) );
				$tab    = 'presets';
				break;
			case 'save_design':
				$result = APD_Designs::save( $this->unslash_array( isset( $_POST['apd_design'] ) ? $_POST['apd_design'] : array() ) );
				$tab    = 'designs';
				if ( is_wp_error( $result ) ) {
					$posted_id   = isset( $_POST['apd_design']['id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_design']['id'] ) ) : '';
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				} elseif ( is_array( $result ) && ! empty( $result['id'] ) ) {
					$return_args = array( 'edit' => $result['id'] );
				}
				break;
			case 'save_palette':
				$result = APD_Color_Palettes::save( $this->unslash_array( isset( $_POST['apd_palette'] ) ? $_POST['apd_palette'] : array() ) );
				$tab    = 'palette';
				if ( is_wp_error( $result ) ) {
					$posted_id   = isset( $_POST['apd_palette']['id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_palette']['id'] ) ) : '';
					$return_args = '' !== $posted_id ? array( 'edit' => $posted_id ) : array( 'add' => '1' );
				} elseif ( is_array( $result ) && ! empty( $result['id'] ) ) {
					$return_args = array( 'edit' => $result['id'] );
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
					$result      = true;
					$return_args = array( 'edit_color' => $color_id );

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
			case 'save_catalog':
				$result = $this->save_catalog_tab();
				$tab    = 'catalog';
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
		$layouts      = get_post_meta( $post->ID, self::META_LAYOUTS, true );
		$hide_image   = 'no' !== get_post_meta( $post->ID, self::META_HIDE_IMAGE, true );
		$default_text = (string) get_post_meta( $post->ID, self::META_DEFAULT_TEXT, true );
		$formats    = APD_Formats::all();
		$available  = array( 'text_only', 'text_image_text', 'image_text', 'multiline_text' );

		if ( '' === $format && isset( $_GET['apd_format'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_text_field( wp_unslash( $_GET['apd_format'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( null !== APD_Formats::get( $requested ) ) {
				$format  = $requested;
				$enabled = true;
			}
		}

		$palette_ids  = self::product_palette_ids( $post->ID, $format );

		if ( ! is_array( $layouts ) ) {
			$layouts = $available;
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
			printf(
				'<option value="%1$s" data-apd-type="%2$s" data-apd-no-frame="%6$s" %3$s>%4$s (%5$s)</option>',
				esc_attr( $item['id'] ),
				esc_attr( $item['type'] ),
				selected( $format, $item['id'], false ),
				esc_html( $item['name'] ),
				esc_html( strtoupper( $item['type'] ) ),
				! empty( $item['no_frame'] ) ? '1' : '0'
			);
		}

		echo '</select></p>';

		if ( empty( $formats ) ) {
			echo '<p class="description">' . esc_html__( 'Add formats under WooCommerce → Auto Plate Designer first.', 'auto-plate-designer' ) . '</p>';
		}

		$selected_format = '' !== $format ? APD_Formats::get( $format ) : null;
		$selected_type   = is_array( $selected_format ) && isset( $selected_format['type'] ) ? (string) $selected_format['type'] : '';
		$suv_default     = APD_Formats::is_suv_kind( $selected_type );
		$suv_rows        = APD_Formats::suv_plate_rows( $default_text );
		$single_text     = str_replace( array( "\r\n", "\r", "\n" ), ' ', $default_text );

		echo '<p data-apd-default-single' . ( $suv_default ? ' hidden' : '' ) . '><label for="apd_default_text">' . esc_html__( 'Initial plate text', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text" name="apd_default_text" value="' . esc_attr( $single_text ) . '" maxlength="32" autocomplete="off"' . ( $suv_default ? ' disabled' : '' ) . '>';
		echo '</p>';
		echo '<div class="apd-default-rows" data-apd-default-rows' . ( $suv_default ? '' : ' hidden' ) . '>';
		echo '<p><label for="apd_default_text_row_1">' . esc_html__( 'First row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_row_1" name="apd_default_text_row_1" value="' . esc_attr( $suv_rows[0] ) . '" maxlength="32" autocomplete="off"' . ( $suv_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '<p><label for="apd_default_text_row_2">' . esc_html__( 'Second row', 'auto-plate-designer' ) . '</label><br>';
		echo '<input type="text" class="widefat" id="apd_default_text_row_2" name="apd_default_text_row_2" value="' . esc_attr( $suv_rows[1] ) . '" maxlength="32" autocomplete="off"' . ( $suv_default ? '' : ' disabled' ) . '>';
		echo '</p>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Shown on the product page until the shopper types their own plate. Leave blank to use the format sample.', 'auto-plate-designer' ) . '</p>';

		$purpose_labels = array(
			'text'       => __( 'Text color', 'auto-plate-designer' ),
			'border'     => __( 'Border color', 'auto-plate-designer' ),
			'background' => __( 'Plate color', 'auto-plate-designer' ),
		);

		echo '<div data-apd-color-fields>';
		echo '<p>' . esc_html__( 'Palettes in the configurator', 'auto-plate-designer' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Choose which palettes shoppers can pick from. Leave a group unchecked to hide that color control. New products start with none selected.', 'auto-plate-designer' ) . '</p>';

		$named = APD_Color_Palettes::all();

		foreach ( $purpose_labels as $purpose => $label ) {
			$label_attr = 'background' === $purpose ? ' data-apd-background-label' : '';
			echo '<div data-apd-color-cap="' . esc_attr( $purpose ) . '">';
			echo '<p><strong><span' . $label_attr . '>' . esc_html( $label ) . '</span></strong></p>';

			$found_palette = false;
			echo '<div class="apd-choice-list">';

			foreach ( $named as $palette ) {
				if ( empty( $palette['active'] ) || ! isset( $palette['purpose'] ) || $palette['purpose'] !== $purpose ) {
					continue;
				}

				$found_palette = true;
				printf(
					'<label class="apd-choice"><input type="checkbox" name="apd_palette_ids[]" value="%1$s" %2$s> %3$s</label>',
					esc_attr( $palette['id'] ),
					checked( in_array( $palette['id'], $palette_ids, true ), true, false ),
					esc_html( $palette['name'] )
				);
			}

			echo '</div>';

			if ( ! $found_palette ) {
				echo '<p class="description">' . esc_html__( 'No palettes for this part yet. Add them under Color palette.', 'auto-plate-designer' ) . '</p>';
			}

			echo '</div>';
		}

		echo '</div>';

		echo '<p>' . esc_html__( 'Allowed layouts', 'auto-plate-designer' ) . '</p>';
		echo '<div class="apd-choice-list">';

		foreach ( $available as $layout ) {
			printf(
				'<label class="apd-choice"><input type="checkbox" name="apd_layouts[]" value="%1$s" %2$s> %3$s</label>',
				esc_attr( $layout ),
				checked( in_array( $layout, $layouts, true ), true, false ),
				esc_html( str_replace( '_', ' ', $layout ) )
			);
		}

		echo '</div>';

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

		$layouts = array();

		if ( isset( $_POST['apd_layouts'] ) && is_array( $_POST['apd_layouts'] ) ) {
			foreach ( wp_unslash( $_POST['apd_layouts'] ) as $layout ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$layout = sanitize_key( $layout );

				if ( ! is_wp_error( APD_Security::validate_layout( $layout ) ) ) {
					$layouts[] = $layout;
				}
			}
		}

		$layouts = array_values( array_unique( $layouts ) );

		update_post_meta( $product_id, self::META_LAYOUTS, $layouts );

		$format_type = '';
		$format_row  = null;

		if ( '' !== $format_id ) {
			$format_row  = APD_Formats::get( $format_id );
			$format_type = is_array( $format_row ) && isset( $format_row['type'] ) ? (string) $format_row['type'] : '';
		}

		$multiline   = is_array( $format_row ) && ! empty( $format_row['multiline'] );
		$max_chars   = is_array( $format_row ) && isset( $format_row['max_chars'] ) ? (int) $format_row['max_chars'] : 12;
		$text_limit  = $max_chars > 0 ? $max_chars : 12;

		if ( APD_Formats::is_suv_kind( $format_type ) ) {
			$row_limits = APD_Formats::suv_row_limits( is_array( $format_row ) ? $format_row : array( 'type' => $format_type ) );
			$row1       = isset( $_POST['apd_default_text_row_1'] ) ? (string) wp_unslash( $_POST['apd_default_text_row_1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$row2       = isset( $_POST['apd_default_text_row_2'] ) ? (string) wp_unslash( $_POST['apd_default_text_row_2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_text = APD_Formats::limit_suv_rows( $row1, $row2, $row_limits[0], $row_limits[1] );
			$multiline  = true;
			$text_limit = $row_limits[0] + $row_limits[1] + ( false !== strpos( $clean_text, "\n" ) ? 1 : 0 );
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

		$text_ok = APD_Security::validate_plate_text(
			$clean_text,
			array(
				'max_length'  => $text_limit,
				'multiline'   => $multiline,
				'allow_empty' => true,
			)
		);
		update_post_meta( $product_id, self::META_DEFAULT_TEXT, is_wp_error( $text_ok ) ? '' : $clean_text );

		$posted_palettes = isset( $_POST['apd_palette_ids'] ) && is_array( $_POST['apd_palette_ids'] )
			? wp_unslash( $_POST['apd_palette_ids'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$palette_ids = APD_Color_Palettes::sanitize_product_ids( $posted_palettes, $format_type );
		update_post_meta( $product_id, self::META_PALETTE_IDS, $palette_ids );

		$derived_fields = array();

		foreach ( $palette_ids as $palette_id ) {
			$palette = APD_Color_Palettes::get( $palette_id );

			if ( is_array( $palette ) && isset( $palette['purpose'] ) ) {
				$derived_fields[] = $palette['purpose'];
			}
		}

		update_post_meta( $product_id, self::META_COLOR_FIELDS, APD_Formats::sanitize_color_fields( $derived_fields, $format_type ) );

		if ( 'yes' === $enabled && is_array( $format_row ) && isset( $format_row['type'] ) ) {
			APD_Catalog::maybe_assign_product_term( $product_id, $format_row['type'] );
		}
	}

	/**
	 * Plate text shown on the product page until the shopper edits it.
	 *
	 * @param int                    $product_id Product ID.
	 * @param array<string, mixed>|null $format  Format row.
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
		$ids = self::product_palette_ids( $product_id );
		$out = array(
			'text'       => array(),
			'border'     => array(),
			'background' => array(),
		);

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
			'catalog' => __( 'Catalog', 'auto-plate-designer' ),
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

		$attachment_id = isset( $posted['attachment_id'] ) ? absint( $posted['attachment_id'] ) : 0;
		$font_check    = APD_Security::validate_attachment(
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

		$weight = isset( $posted['weight'] ) ? absint( $posted['weight'] ) : 400;

		if ( $weight < 100 || $weight > 900 ) {
			$weight = 400;
		}

		$style = isset( $posted['style'] ) && 'italic' === $posted['style'] ? 'italic' : 'normal';
		$id    = isset( $posted['id'] ) ? sanitize_text_field( (string) $posted['id'] ) : '';

		if ( '' === $id ) {
			$id = APD_Plugin::new_id();
		}

		$font = array(
			'id'            => $id,
			'family'        => APD_Security::sanitize_admin_label( (string) $posted['family'] ),
			'attachment_id' => $attachment_id,
			'weight'        => $weight,
			'style'         => $style,
		);

		$settings = APD_Plugin::get_settings();
		$found    = false;

		foreach ( $settings['fonts'] as $index => $existing ) {
			if ( isset( $existing['id'] ) && $existing['id'] === $id ) {
				$settings['fonts'][ $index ] = $font;
				$found                       = true;
				break;
			}
		}

		if ( ! $found ) {
			$settings['fonts'][] = $font;
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
	 * Save layout limits and character whitelist.
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

		$max_lines = isset( $posted['max_lines'] ) ? absint( $posted['max_lines'] ) : 5;

		if ( $max_lines < 1 ) {
			$max_lines = 1;
		}

		if ( $max_lines > 5 ) {
			$max_lines = 5;
		}

		$settings['limits']['min_font_size'] = $min_font;
		$settings['limits']['max_lines']     = $max_lines;

		$layout_keys = array( 'text_only', 'text_image_text', 'image_text', 'multiline_text' );

		foreach ( $layout_keys as $layout ) {
			$row = isset( $posted['layouts'][ $layout ] ) && is_array( $posted['layouts'][ $layout ] )
				? $posted['layouts'][ $layout ]
				: array();

			$max_chars = isset( $row['max_chars'] ) ? absint( $row['max_chars'] ) : 12;

			if ( $max_chars < 1 ) {
				$max_chars = 1;
			}

			if ( $max_chars > APD_Security::ABSOLUTE_MAX_CHARS ) {
				$max_chars = APD_Security::ABSOLUTE_MAX_CHARS;
			}

			$lines = isset( $row['max_lines'] ) ? absint( $row['max_lines'] ) : 1;

			if ( $lines < 1 ) {
				$lines = 1;
			}

			if ( $lines > 5 ) {
				$lines = 5;
			}

			if ( 'multiline_text' !== $layout ) {
				$lines = 1;
			}

			$settings['limits']['layouts'][ $layout ] = array(
				'max_chars' => $max_chars,
				'max_lines' => $lines,
			);
		}

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
	 * Save which catalog categories are published.
	 *
	 * @return true|WP_Error
	 */
	private function save_catalog_tab() {
		$posted   = $this->unslash_array( isset( $_POST['apd_catalog'] ) ? $_POST['apd_catalog'] : array() );
		$settings = APD_Plugin::get_settings();

		$settings['catalog']['published'] = APD_Catalog::sanitize_published(
			isset( $posted['published'] ) ? $posted['published'] : array()
		);

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
