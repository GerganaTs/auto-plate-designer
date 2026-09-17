<?php
/**
 * WooCommerce cart, order, and product-page configurator.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce hooks for the plate configurator.
 */
final class APD_WooCommerce {

	const CART_KEY = 'apd_config';

	/**
	 * Prevent re-entry while stale cart lines are removed.
	 *
	 * @var bool
	 */
	private $auditing_cart = false;

	/**
	 * Product IDs already notified as stale in this request.
	 *
	 * @var array<int, true>
	 */
	private $stale_notices = array();

	/**
	 * Singleton instance.
	 *
	 * @var APD_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_WooCommerce
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register storefront and cart hooks.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp', array( $this, 'maybe_remove_classic_gallery' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_configurator' ) );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'loop_button_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_description', array( $this, 'loop_button_description' ), 10, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'single_button_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'loop_button_url' ), 10, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_args', array( $this, 'loop_button_args' ), 10, 2 );
		add_filter( 'woocommerce_product_supports', array( $this, 'disable_ajax_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ) );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'audit_cart_configs' ), 20, 1 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'audit_cart_configs' ), 5, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_price_adjustment' ), 20, 1 );
	}

	/**
	 * Whether the product has a usable configurator.
	 *
	 * @param int|WC_Product $product Product or ID.
	 * @return bool
	 */
	public static function is_enabled( $product ) {
		$product_id = self::product_id( $product );

		if ( $product_id < 1 ) {
			return false;
		}

		if ( 'yes' !== get_post_meta( $product_id, APD_Admin_Settings::META_ENABLED, true ) ) {
			return false;
		}

		$format_id = (string) get_post_meta( $product_id, APD_Admin_Settings::META_FORMAT, true );

		return null !== APD_Formats::get( $format_id );
	}

	/**
	 * Whether the featured image/gallery should be hidden on the product page.
	 *
	 * Defaults to hidden for configurator products unless the shop manager
	 * explicitly unchecks the option.
	 *
	 * @param int|WC_Product $product Product or ID.
	 * @return bool
	 */
	public static function hide_product_image( $product ) {
		if ( ! self::is_enabled( $product ) ) {
			return false;
		}

		$product_id = self::product_id( $product );

		return 'no' !== get_post_meta( $product_id, APD_Admin_Settings::META_HIDE_IMAGE, true );
	}

	/**
	 * Product ID from mixed input.
	 *
	 * @param mixed $product Product object or ID.
	 * @return int
	 */
	private static function product_id( $product ) {
		if ( $product instanceof WC_Product ) {
			return $product->get_id();
		}

		return absint( $product );
	}

	/**
	 * Body class so CSS can hide the gallery on block and classic templates.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public function body_class( $classes ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $classes;
		}

		$product_id = get_queried_object_id();

		if ( self::is_enabled( $product_id ) ) {
			$classes[] = 'apd-configurator-product';
		}

		if ( self::hide_product_image( $product_id ) ) {
			$classes[] = 'apd-hide-product-image';
		}

		return $classes;
	}

	/**
	 * Drop the classic single-product gallery when the live preview replaces it.
	 */
	public function maybe_remove_classic_gallery() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! self::hide_product_image( get_queried_object_id() ) ) {
			return;
		}

		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );
	}

	/**
	 * Scripts and styles on configurable product pages only.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = get_queried_object_id();

		if ( ! self::is_enabled( $product_id ) ) {
			return;
		}

		$payload = $this->frontend_payload( $product_id );

		if ( null === $payload ) {
			return;
		}

		$css_file = APD_PLUGIN_DIR . 'assets/css/configurator.css';
		$js_file  = APD_PLUGIN_DIR . 'assets/js/configurator.js';

		$style_deps = wp_style_is( 'woocommerce-general', 'registered' ) ? array( 'woocommerce-general' ) : array();

		wp_enqueue_style(
			'apd-configurator',
			APD_PLUGIN_URL . 'assets/css/configurator.css',
			$style_deps,
			is_readable( $css_file ) ? (string) filemtime( $css_file ) : APD_VERSION
		);

		$faces = '';

		foreach ( $payload['format']['fonts'] as $font ) {
			$faces .= sprintf(
				'@font-face{font-family:%1$s;src:url(%2$s) format("woff2");font-weight:%3$d;font-style:%4$s;font-display:swap;}',
				wp_json_encode( $font['family'] ),
				wp_json_encode( $font['url'] ),
				(int) $font['weight'],
				'italic' === $font['style'] ? 'italic' : 'normal'
			);
		}

		if ( '' !== $faces ) {
			wp_add_inline_style( 'apd-configurator', $faces );
		}

		wp_enqueue_script(
			'apd-configurator',
			APD_PLUGIN_URL . 'assets/js/configurator.js',
			array(),
			is_readable( $js_file ) ? (string) filemtime( $js_file ) : APD_VERSION,
			true
		);

		wp_localize_script(
			'apd-configurator',
			'apdConfig',
			$payload
		);
	}

	/**
	 * Configurator markup inside the add-to-cart form.
	 */
	public function render_configurator() {
		global $product;

		if ( ! $product instanceof WC_Product || ! self::is_enabled( $product ) ) {
			return;
		}

		$payload = $this->frontend_payload( $product->get_id() );

		if ( null === $payload ) {
			return;
		}

		$apd_payload = $payload;

		include APD_PLUGIN_DIR . 'templates/product-configurator.php';
	}

	/**
	 * Shop loop button label.
	 *
	 * @param string     $text    Default text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function loop_button_text( $text, $product ) {
		if ( self::is_enabled( $product ) ) {
			return __( 'Configure', 'auto-plate-designer' );
		}

		return $text;
	}

	/**
	 * Accessible name for the loop button.
	 *
	 * @param string     $text    Default description.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function loop_button_description( $text, $product ) {
		if ( self::is_enabled( $product ) ) {
			return sprintf(
				/* translators: %s: product name */
				__( 'Configure “%s”', 'auto-plate-designer' ),
				$product->get_name()
			);
		}

		return $text;
	}

	/**
	 * Keep Add to cart on the product page after the shopper has configured.
	 *
	 * @param string     $text    Default text.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function single_button_text( $text, $product ) {
		unset( $product );

		return $text;
	}

	/**
	 * Send shoppers to the product page instead of adding from the loop.
	 *
	 * @param string     $url     Default URL.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public function loop_button_url( $url, $product ) {
		if ( self::is_enabled( $product ) ) {
			return $product->get_permalink();
		}

		return $url;
	}

	/**
	 * Drop the AJAX class so the loop button navigates to the product.
	 *
	 * @param array<string, mixed> $args    Button args.
	 * @param WC_Product           $product Product.
	 * @return array<string, mixed>
	 */
	public function loop_button_args( $args, $product ) {
		if ( self::is_enabled( $product ) && isset( $args['class'] ) ) {
			$args['class'] = trim( str_replace( 'ajax_add_to_cart', '', (string) $args['class'] ) );
		}

		return $args;
	}

	/**
	 * Disable AJAX add-to-cart for configurable products in the loop.
	 *
	 * @param bool       $supports Feature supported.
	 * @param string     $feature  Feature name.
	 * @param WC_Product $product  Product.
	 * @return bool
	 */
	public function disable_ajax_add_to_cart( $supports, $feature, $product ) {
		if ( 'ajax_add_to_cart' === $feature && self::is_enabled( $product ) ) {
			return false;
		}

		return $supports;
	}

	/**
	 * Validate posted configuration before it reaches the cart.
	 *
	 * @param bool $passed     Current result.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		unset( $quantity );

		if ( ! $passed || ! self::is_enabled( $product_id ) ) {
			return $passed;
		}

		$parsed = $this->parse_posted_config( $product_id );

		if ( is_wp_error( $parsed ) ) {
			wc_add_notice( $parsed->get_error_message(), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Attach sanitized config to the cart item.
	 *
	 * @param array<string, mixed> $cart_item_data Existing data.
	 * @param int                  $product_id     Product ID.
	 * @return array<string, mixed>
	 */
	public function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! self::is_enabled( $product_id ) ) {
			return $cart_item_data;
		}

		$parsed = $this->parse_posted_config( $product_id );

		if ( is_wp_error( $parsed ) ) {
			return $cart_item_data;
		}

		$cart_item_data[ self::CART_KEY ] = $parsed;
		$cart_item_data['apd_hash']       = md5( (string) wp_json_encode( $parsed ) );

		return $cart_item_data;
	}

	/**
	 * Show configuration on cart and checkout.
	 *
	 * @param array<int, array<string, string>> $item_data Cart display rows.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $item_data;
		}

		foreach ( self::config_display_rows( $cart_item[ self::CART_KEY ] ) as $row ) {
			$item_data[] = $row;
		}

		return $item_data;
	}

	/**
	 * Copy config onto the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart key.
	 * @param array<string, mixed>  $values        Cart item.
	 * @param WC_Order              $order         Order.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		if ( empty( $values[ self::CART_KEY ] ) || ! is_array( $values[ self::CART_KEY ] ) ) {
			return;
		}

		$config = $values[ self::CART_KEY ];

		foreach ( self::config_display_rows( $config ) as $row ) {
			$item->add_meta_data( $row['key'], $row['value'], true );
		}

		$item->add_meta_data( '_apd_config', wp_json_encode( $config ), true );
	}

	/**
	 * Keep the machine snapshot off customer and admin line-item lists.
	 *
	 * @param array<int, string> $hidden Hidden keys.
	 * @return array<int, string>
	 */
	public function hide_order_item_meta( $hidden ) {
		$hidden[] = '_apd_config';

		return $hidden;
	}

	/**
	 * Cart-page and checkout hook with no cart argument.
	 */
	public function check_cart_items() {
		if ( function_exists( 'WC' ) && WC()->cart instanceof WC_Cart ) {
			$this->audit_cart_configs( WC()->cart );
		}
	}

	/**
	 * Drop cart lines whose plate offering no longer matches the stored snapshot.
	 *
	 * Placed orders are not touched — they keep the denormalized line meta.
	 *
	 * @param WC_Cart|null $cart Cart.
	 */
	public function audit_cart_configs( $cart = null ) {
		if ( $this->auditing_cart ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			$cart = function_exists( 'WC' ) && WC()->cart instanceof WC_Cart ? WC()->cart : null;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$this->auditing_cart = true;

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( empty( $item[ self::CART_KEY ] ) || ! is_array( $item[ self::CART_KEY ] ) ) {
				continue;
			}

			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;

			if ( self::cart_config_matches_product( $product_id, $item[ self::CART_KEY ] ) ) {
				continue;
			}

			$cart->remove_cart_item( $key );
			$this->notice_stale_cart_item( $item, $product_id );
		}

		$this->auditing_cart = false;
	}

	/**
	 * Tell the shopper to configure the product again.
	 *
	 * @param array<string, mixed> $item       Removed cart item.
	 * @param int                  $product_id Product ID.
	 */
	private function notice_stale_cart_item( $item, $product_id ) {
		if ( isset( $this->stale_notices[ $product_id ] ) ) {
			return;
		}

		$this->stale_notices[ $product_id ] = true;

		$name = '';
		$url  = '';

		if ( ! empty( $item['data'] ) && $item['data'] instanceof WC_Product ) {
			$name = $item['data']->get_name();
			$url  = $item['data']->get_permalink();
		} elseif ( $product_id > 0 ) {
			$name = get_the_title( $product_id );
			$url  = get_permalink( $product_id );
		}

		if ( '' === $name ) {
			$name = __( 'This plate', 'auto-plate-designer' );
		}

		$label = esc_html( $name );

		if ( is_string( $url ) && '' !== $url ) {
			$label = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: product name, linked to the product page when possible */
				__( '“%s” must be configured again because the plate settings have changed. It was removed from your cart.', 'auto-plate-designer' ),
				$label
			),
			'notice'
		);
	}

	/**
	 * Hash of the configurator offering a shopper can pick on this product right now.
	 *
	 * @param int $product_id Product ID.
	 * @return string Empty when the configurator is not available.
	 */
	public static function offer_fingerprint( $product_id ) {
		$catalog = self::product_offer_catalog( $product_id );

		if ( empty( $catalog['enabled'] ) ) {
			return '';
		}

		return md5( (string) wp_json_encode( self::stable_tree( $catalog ) ) );
	}

	/**
	 * Whether a stored cart snapshot still matches the live product offering.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $config     Stored cart config.
	 * @return bool
	 */
	public static function cart_config_matches_product( $product_id, $config ) {
		if ( ! is_array( $config ) ) {
			return false;
		}

		$expected = self::offer_fingerprint( $product_id );
		$stored   = isset( $config['offer_fingerprint'] ) ? (string) $config['offer_fingerprint'] : '';

		if ( '' === $expected || '' === $stored ) {
			return false;
		}

		return hash_equals( $expected, $stored );
	}

	/**
	 * Live catalog a shopper can configure for this product.
	 *
	 * URLs are omitted so a site-url change does not empty carts. Attachment IDs stay.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function product_offer_catalog( $product_id ) {
		$product_id = absint( $product_id );

		if ( $product_id < 1 || ! self::is_enabled( $product_id ) ) {
			return array(
				'enabled' => false,
			);
		}

		$format_id = (string) get_post_meta( $product_id, APD_Admin_Settings::META_FORMAT, true );
		$format    = APD_Formats::get( $format_id );

		if ( ! is_array( $format ) ) {
			return array(
				'enabled' => false,
			);
		}

		$payload = APD_Formats::frontend_payload( $format );
		$layouts = get_post_meta( $product_id, APD_Admin_Settings::META_LAYOUTS, true );

		if ( ! is_array( $layouts ) || empty( $layouts ) ) {
			$layouts = array( 'text_only' );
		}

		$fonts = array();

		if ( isset( $payload['fonts'] ) && is_array( $payload['fonts'] ) ) {
			foreach ( $payload['fonts'] as $font ) {
				$fonts[] = array(
					'id'     => isset( $font['id'] ) ? (string) $font['id'] : '',
					'family' => isset( $font['family'] ) ? (string) $font['family'] : '',
					'weight' => isset( $font['weight'] ) ? (int) $font['weight'] : 400,
					'style'  => isset( $font['style'] ) ? (string) $font['style'] : 'normal',
				);
			}
		}

		$presets = array();

		if ( isset( $payload['presets'] ) && is_array( $payload['presets'] ) ) {
			foreach ( $payload['presets'] as $preset ) {
				$presets[] = array(
					'id'           => isset( $preset['id'] ) ? (string) $preset['id'] : '',
					'name'         => isset( $preset['name'] ) ? (string) $preset['name'] : '',
					'country_code' => isset( $preset['country_code'] ) ? (string) $preset['country_code'] : '',
					'side'         => isset( $preset['side'] ) ? (string) $preset['side'] : '',
					'band_ratio'   => isset( $preset['band_ratio'] ) ? (float) $preset['band_ratio'] : 0,
				);
			}
		}

		$designs = array();

		if ( isset( $payload['designs'] ) && is_array( $payload['designs'] ) ) {
			foreach ( $payload['designs'] as $design ) {
				$designs[] = array(
					'id'       => isset( $design['id'] ) ? (string) $design['id'] : '',
					'name'     => isset( $design['name'] ) ? (string) $design['name'] : '',
					'code'     => isset( $design['code'] ) ? (string) $design['code'] : '',
					'text_box' => APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() ),
				);
			}
		}

		$palettes = array();
		$offered  = APD_Admin_Settings::product_offered_colors( $product_id );

		foreach ( APD_Plugin::palette_sets() as $set ) {
			$palettes[ $set ] = array();

			if ( empty( $offered[ $set ] ) || ! is_array( $offered[ $set ] ) ) {
				continue;
			}

			foreach ( $offered[ $set ] as $color ) {
				$palettes[ $set ][] = array(
					'id'    => isset( $color['id'] ) ? (string) $color['id'] : '',
					'hex'   => isset( $color['hex'] ) ? (string) $color['hex'] : '',
					'label' => isset( $color['label'] ) ? (string) $color['label'] : '',
				);
			}
		}

		return array(
			'enabled'          => true,
			'format_id'        => isset( $format['id'] ) ? (string) $format['id'] : '',
			'format_name'      => isset( $format['name'] ) ? (string) $format['name'] : '',
			'format_type'      => isset( $format['type'] ) ? (string) $format['type'] : '',
			'width'            => isset( $format['width'] ) ? (int) $format['width'] : 0,
			'height'           => isset( $format['height'] ) ? (int) $format['height'] : 0,
			'border_width'     => ! empty( $format['no_frame'] ) ? 0 : ( isset( $format['border_width'] ) ? (int) $format['border_width'] : 0 ),
			'border_color'     => isset( $format['border_color'] ) ? (string) $format['border_color'] : '',
			'no_frame'         => ! empty( $format['no_frame'] ),
			'band_ratio'       => isset( $format['band_ratio'] ) ? (float) $format['band_ratio'] : 0,
			'band_side'        => isset( $format['band_side'] ) ? (string) $format['band_side'] : '',
			'band_box'         => isset( $format['band_box'] ) && is_array( $format['band_box'] ) ? $format['band_box'] : array(),
			'base_image_id'    => isset( $format['base_image_id'] ) ? absint( $format['base_image_id'] ) : 0,
			'max_chars'        => isset( $payload['max_chars'] ) ? (int) $payload['max_chars'] : 0,
			'price_adjustment' => isset( $format['price_adjustment'] ) ? (float) $format['price_adjustment'] : 0,
			'allow_empty'      => ! empty( $payload['allow_empty'] ),
			'multiline'        => ! empty( $payload['multiline'] ),
			'font_ids'         => isset( $format['font_ids'] ) && is_array( $format['font_ids'] ) ? array_values( $format['font_ids'] ) : array(),
			'fonts'            => $fonts,
			'presets'          => $presets,
			'designs'          => $designs,
			'text_box'         => APD_Formats::sanitize_text_box(
				isset( $payload['text_box'] ) ? $payload['text_box'] : array(),
				isset( $format['type'] ) ? (string) $format['type'] : 'eu',
				$format
			),
			'layouts'          => array_values( $layouts ),
			'palette_ids'      => APD_Admin_Settings::product_palette_ids( $product_id ),
			'color_fields'     => APD_Admin_Settings::product_color_fields( $product_id ),
			'palettes'         => $palettes,
			'rules'            => APD_Security::frontend_text_rules(),
		);
	}

	/**
	 * Sort associative arrays so the fingerprint is stable.
	 *
	 * @param mixed $value Tree.
	 * @return mixed
	 */
	private static function stable_tree( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$out = array();

		foreach ( $value as $key => $child ) {
			$out[ $key ] = self::stable_tree( $child );
		}

		if ( $out !== array_values( $out ) ) {
			ksort( $out );
		}

		return $out;
	}

	/**
	 * Apply format price adjustment using the original product price.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_price_adjustment( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item[ self::CART_KEY ]['price_adjustment'] ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			$adjustment = (float) $item[ self::CART_KEY ]['price_adjustment'];
			$base       = (float) $item['data']->get_regular_price();

			if ( $item['data']->is_on_sale() && '' !== $item['data']->get_sale_price() ) {
				$base = (float) $item['data']->get_sale_price();
			}

			$item['data']->set_price( $base + $adjustment );
		}
	}

	/**
	 * Payload for JS and the PHP template.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	private function frontend_payload( $product_id ) {
		$format_id = (string) get_post_meta( $product_id, APD_Admin_Settings::META_FORMAT, true );
		$format    = APD_Formats::get( $format_id );

		if ( ! is_array( $format ) ) {
			return null;
		}

		$layouts = get_post_meta( $product_id, APD_Admin_Settings::META_LAYOUTS, true );

		if ( ! is_array( $layouts ) || empty( $layouts ) ) {
			$layouts = array( 'text_only' );
		}

		$limits = APD_Plugin::get_settings();
		$min    = isset( $limits['limits']['min_font_size'] ) ? (int) $limits['limits']['min_font_size'] : 12;

		return array(
			'format'       => APD_Formats::frontend_payload( $format ),
			'layouts'      => $layouts,
			'color_fields' => APD_Admin_Settings::product_color_fields( $product_id ),
			'palettes'     => APD_Admin_Settings::product_offered_colors( $product_id ),
			'rules'    => APD_Security::frontend_text_rules(),
			'minFont'  => $min,
			'i18n'     => array(
				'textLabel'     => __( 'Text', 'auto-plate-designer' ),
				'fontLabel'     => __( 'Font', 'auto-plate-designer' ),
				'countryLabel'  => __( 'Country band', 'auto-plate-designer' ),
				'changeCountry' => __( 'Change country', 'auto-plate-designer' ),
				'chooseCountry' => __( 'Choose country', 'auto-plate-designer' ),
				'closeDialog'   => __( 'Close', 'auto-plate-designer' ),
				'bandHint'      => __( 'Click the country band to change country', 'auto-plate-designer' ),
				'designLabel'   => __( 'Plate design', 'auto-plate-designer' ),
				'textColor'     => __( 'Text color', 'auto-plate-designer' ),
				'borderColor'   => __( 'Border color', 'auto-plate-designer' ),
				'plateColor'    => __( 'Plate color', 'auto-plate-designer' ),
				'stripColor'    => __( 'Text background', 'auto-plate-designer' ),
				'chars'         => __( '%1$s / %2$s characters', 'auto-plate-designer' ),
				'invalid'       => __( 'Please enter valid plate text before adding to cart.', 'auto-plate-designer' ),
			),
		);
	}

	/**
	 * Read and sanitize the posted configuration.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private function parse_posted_config( $product_id ) {
		$nonce = isset( $_POST['apd_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_nonce'] ) ) : '';
		$check = APD_Security::verify_nonce( $nonce, 'apd_configure' );

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$format_id = (string) get_post_meta( $product_id, APD_Admin_Settings::META_FORMAT, true );
		$format    = APD_Formats::get( $format_id );

		if ( ! is_array( $format ) ) {
			return new WP_Error( 'apd_format_missing', __( 'This product has no plate format.', 'auto-plate-designer' ) );
		}

		$payload    = APD_Formats::frontend_payload( $format );
		$max_chars  = (int) $payload['max_chars'];
		$allow_empty = ! empty( $payload['allow_empty'] );
		$multiline  = ! empty( $payload['multiline'] );
		$text       = isset( $_POST['apd_text'] ) ? (string) wp_unslash( $_POST['apd_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$text_check = APD_Security::validate_plate_text(
			$text,
			array(
				'max_length'  => $max_chars,
				'multiline'   => $multiline,
				'allow_empty' => $allow_empty,
			)
		);

		if ( is_wp_error( $text_check ) ) {
			return $text_check;
		}

		$font_id    = isset( $_POST['apd_font_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_font_id'] ) ) : '';
		$font_label = '';

		foreach ( $payload['fonts'] as $font ) {
			if ( $font['id'] === $font_id ) {
				$font_label = $font['family'];
				break;
			}
		}

		if ( '' === $font_label && ! empty( $payload['fonts'] ) ) {
			$font_id    = $payload['fonts'][0]['id'];
			$font_label = $payload['fonts'][0]['family'];
		}

		$preset_id    = '';
		$preset_label = '';

		if ( APD_Formats::uses_country_band( isset( $format['type'] ) ? (string) $format['type'] : '' ) ) {
			$preset_id = isset( $_POST['apd_preset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_preset_id'] ) ) : '';

			foreach ( $payload['presets'] as $preset ) {
				if ( $preset['id'] === $preset_id ) {
					$preset_label = $preset['name'];
					break;
				}
			}

			if ( '' === $preset_label && ! empty( $payload['presets'] ) ) {
				$preset_id    = $payload['presets'][0]['id'];
				$preset_label = $payload['presets'][0]['name'];
			}
		}

		$design_id    = '';
		$design_label = '';
		$design_code  = '';
		$type         = isset( $format['type'] ) ? (string) $format['type'] : '';
		$design_box   = APD_Formats::sanitize_text_box(
			isset( $payload['text_box'] ) ? $payload['text_box'] : array(),
			$type,
			$format
		);

		if ( APD_Formats::uses_plate_designs( $type ) ) {
			$design_id = isset( $_POST['apd_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_design_id'] ) ) : '';
			$designs   = isset( $payload['designs'] ) && is_array( $payload['designs'] ) ? $payload['designs'] : array();

			foreach ( $designs as $design ) {
				if ( $design['id'] === $design_id ) {
					$design_label = $design['name'];
					$design_code  = isset( $design['code'] ) ? (string) $design['code'] : '';
					$design_box   = APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() );
					break;
				}
			}

			if ( '' === $design_label && ! empty( $designs ) ) {
				$design_id    = $designs[0]['id'];
				$design_label = $designs[0]['name'];
				$design_code  = isset( $designs[0]['code'] ) ? (string) $designs[0]['code'] : '';
				$design_box   = APD_Designs::sanitize_text_box( isset( $designs[0]['text_box'] ) ? $designs[0]['text_box'] : array() );
			}
		}

		$color_fields = APD_Admin_Settings::product_color_fields( $product_id );
		$no_frame     = APD_Formats::uses_painted_plate( $type ) && ! empty( $format['no_frame'] );
		$text_color   = $this->snapshot_palette_color( $product_id, 'apd_text_color', 'text', in_array( 'text', $color_fields, true ) );
		$border_color = $this->snapshot_palette_color( $product_id, 'apd_border_color', 'border', ! $no_frame && in_array( 'border', $color_fields, true ) );
		$fill_color   = $this->snapshot_palette_color( $product_id, 'apd_background_color', 'background', in_array( 'background', $color_fields, true ) );

		return array(
			'format_id'              => $format['id'],
			'format_name'            => isset( $format['name'] ) ? (string) $format['name'] : '',
			'format_type'            => $type,
			'format_type_label'      => APD_Formats::type_label( $type ),
			'format_width'           => isset( $format['width'] ) ? (int) $format['width'] : 0,
			'format_height'          => isset( $format['height'] ) ? (int) $format['height'] : 0,
			'format_border_width'    => $no_frame ? 0 : ( isset( $format['border_width'] ) ? (int) $format['border_width'] : 0 ),
			'format_border_color'    => isset( $format['border_color'] ) ? (string) $format['border_color'] : '',
			'format_band_ratio'      => isset( $format['band_ratio'] ) ? (float) $format['band_ratio'] : 0,
			'format_band_side'       => isset( $format['band_side'] ) ? (string) $format['band_side'] : '',
			'format_max_chars'       => (int) $max_chars,
			'text'                   => $text,
			'font_id'                => $font_id,
			'font_label'             => $font_label,
			'preset_id'              => $preset_id,
			'preset_label'           => $preset_label,
			'design_id'              => $design_id,
			'design_label'           => $design_label,
			'design_code'            => $design_code,
			'text_box'               => $design_box,
			'color_fields'           => $color_fields,
			'no_frame'               => $no_frame,
			'text_color'             => $text_color['hex'],
			'text_color_label'       => $text_color['label'],
			'border_color'           => $border_color['hex'],
			'border_color_label'     => $border_color['label'],
			'background_color'       => $fill_color['hex'],
			'background_color_label' => $fill_color['label'],
			'price_adjustment'       => (float) $format['price_adjustment'],
			'offer_fingerprint'      => self::offer_fingerprint( $product_id ),
		);
	}

	/**
	 * Visible cart / order rows from a stored configuration snapshot.
	 *
	 * Uses only values frozen at add-to-cart / checkout. Live catalog
	 * changes must not rewrite these rows on placed orders.
	 *
	 * @param array<string, mixed> $config Cart or order config.
	 * @return array<int, array<string, string>>
	 */
	public static function config_display_rows( $config ) {
		$rows = array();
		$type = isset( $config['format_type'] ) ? (string) $config['format_type'] : '';

		if ( ! empty( $config['format_name'] ) ) {
			$format_value = (string) $config['format_name'];
			$type_label   = isset( $config['format_type_label'] ) ? (string) $config['format_type_label'] : '';

			if ( '' === $type_label && '' !== $type ) {
				$type_label = APD_Formats::type_label( $type );
			}

			if ( '' !== $type_label ) {
				$format_value .= ' (' . $type_label . ')';
			}

			$rows[] = array(
				'key'   => __( 'Format', 'auto-plate-designer' ),
				'value' => $format_value,
			);
		}

		if ( ! empty( $config['text'] ) ) {
			$rows[] = array(
				'key'   => __( 'Text', 'auto-plate-designer' ),
				'value' => (string) $config['text'],
			);
		}

		if ( ! empty( $config['font_label'] ) ) {
			$rows[] = array(
				'key'   => __( 'Font', 'auto-plate-designer' ),
				'value' => (string) $config['font_label'],
			);
		}

		if ( ! empty( $config['preset_label'] ) ) {
			$rows[] = array(
				'key'   => __( 'Country', 'auto-plate-designer' ),
				'value' => (string) $config['preset_label'],
			);
		}

		if ( ! empty( $config['design_label'] ) ) {
			$design_value = (string) $config['design_label'];

			if ( ! empty( $config['design_code'] ) ) {
				$design_value .= ' (' . $config['design_code'] . ')';
			}

			$rows[] = array(
				'key'   => __( 'Plate design', 'auto-plate-designer' ),
				'value' => $design_value,
			);
		}

		if ( self::config_includes_color( $config, 'text' ) && ! empty( $config['text_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Text color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['text_color'],
					isset( $config['text_color_label'] ) ? (string) $config['text_color_label'] : ''
				),
			);
		}

		if ( ! empty( $config['no_frame'] ) ) {
			$rows[] = array(
				'key'   => __( 'Frame', 'auto-plate-designer' ),
				'value' => __( 'Without frame', 'auto-plate-designer' ),
			);
		}

		if ( self::config_includes_color( $config, 'border' ) && empty( $config['no_frame'] ) && ! empty( $config['border_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Border color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['border_color'],
					isset( $config['border_color_label'] ) ? (string) $config['border_color_label'] : ''
				),
			);
		}

		if ( self::config_includes_color( $config, 'background' ) && ! empty( $config['background_color'] ) ) {
			$fill_key = 'holder' === $type
				? __( 'Text background', 'auto-plate-designer' )
				: __( 'Plate color', 'auto-plate-designer' );

			$rows[] = array(
				'key'   => $fill_key,
				'value' => self::format_color_display(
					(string) $config['background_color'],
					isset( $config['background_color_label'] ) ? (string) $config['background_color_label'] : ''
				),
			);
		}

		return $rows;
	}

	/**
	 * Whether this snapshot included a shopper-facing color field.
	 *
	 * @param array<string, mixed> $config Stored config.
	 * @param string               $field  text|border|background.
	 * @return bool
	 */
	private static function config_includes_color( $config, $field ) {
		if ( ! isset( $config['color_fields'] ) || ! is_array( $config['color_fields'] ) ) {
			return false;
		}

		return in_array( $field, $config['color_fields'], true );
	}

	/**
	 * Store a color only when the shopper could choose it.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $field      POST key.
	 * @param string $set        Palette set.
	 * @param bool   $enabled    Whether the field was offered.
	 * @return array{hex: string, label: string}
	 */
	private function snapshot_palette_color( $product_id, $field, $set, $enabled ) {
		if ( ! $enabled ) {
			return array(
				'hex'   => '',
				'label' => '',
			);
		}

		$hex = $this->posted_palette_color( $product_id, $field, $set, true );

		return array(
			'hex'   => $hex,
			'label' => $this->palette_label_for_hex( $product_id, $set, $hex ),
		);
	}

	/**
	 * Palette label for a stored hex, captured at add-to-cart time.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $set        Palette set.
	 * @param string $hex        Hex color.
	 * @return string
	 */
	private function palette_label_for_hex( $product_id, $set, $hex ) {
		foreach ( $this->offered_colors( $product_id, $set ) as $color ) {
			if ( 0 === strcasecmp( $color['hex'], $hex ) ) {
				return $color['label'];
			}
		}

		return $hex;
	}

	/**
	 * Colors this product offers for one purpose.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $set        text|border|background.
	 * @return array<int, array<string, string>>
	 */
	private function offered_colors( $product_id, $set ) {
		$offered = APD_Admin_Settings::product_offered_colors( $product_id );

		return isset( $offered[ $set ] ) && is_array( $offered[ $set ] ) ? $offered[ $set ] : array();
	}

	/**
	 * Human-readable color for cart and orders.
	 *
	 * @param string $hex   Stored hex.
	 * @param string $label Stored label.
	 * @return string
	 */
	private static function format_color_display( $hex, $label ) {
		if ( '' !== $label && 0 !== strcasecmp( $label, $hex ) ) {
			return $label . ' (' . $hex . ')';
		}

		return $hex;
	}

	/**
	 * Allow only palette hex values for enabled color fields.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $field      POST key.
	 * @param string $set        Palette field.
	 * @param bool   $enabled    Whether the shopper may choose this field.
	 * @return string
	 */
	private function posted_palette_color( $product_id, $field, $set, $enabled = true ) {
		$colors  = $this->offered_colors( $product_id, $set );
		$prefer  = 'background' === $set ? array( '#FFFFFF' ) : array( '#000000' );
		$default = APD_Color_Palettes::preferred_hex( $colors, $prefer );

		if ( ! $enabled ) {
			return $default;
		}

		$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		if ( is_wp_error( APD_Security::validate_hex_color( $raw ) ) ) {
			return $default;
		}

		$hex = APD_Security::sanitize_hex_color( $raw );

		foreach ( $colors as $color ) {
			if ( 0 === strcasecmp( $color['hex'], $hex ) ) {
				return $hex;
			}
		}

		return $default;
	}
}
