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
	 * Toast HTML for cart lines removed because the plate offering changed.
	 *
	 * @var array<int, string>
	 */
	private $stale_toasts = array();

	/**
	 * Cart line to replace after a successful add from an edit visit.
	 *
	 * @var array{key: string, product_id: int, quantity: int}|null
	 */
	private $pending_cart_edit = null;

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_styles' ) );
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
		add_action( 'woocommerce_add_to_cart', array( $this, 'replace_edited_cart_line' ), 10, 6 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_permalink', array( $this, 'cart_item_permalink' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ) );
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_store_api_cart_item_images', array( $this, 'store_api_cart_images' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_item_thumbnail', array( $this, 'admin_order_item_thumbnail' ), 10, 3 );
		add_filter( 'woocommerce_order_item_thumbnail', array( $this, 'order_item_thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 2 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'audit_cart_configs' ), 20, 1 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'audit_cart_configs' ), 5, 1 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_price_adjustment' ), 20, 1 );
		add_action( 'wp_footer', array( $this, 'render_stale_toasts' ), 20 );
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
	 * Styles that keep a configured plate visible in the cart and on orders.
	 */
	private function enqueue_preview_style() {
		$css_file = APD_PLUGIN_DIR . 'assets/css/configurator.css';
		$deps     = array();

		foreach ( array( 'woocommerce-general', 'wc-blocks-style' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				$deps[] = $handle;
			}
		}

		wp_enqueue_style(
			'apd-configurator',
			APD_PLUGIN_URL . 'assets/css/configurator.css',
			$deps,
			is_readable( $css_file ) ? (string) filemtime( $css_file ) : APD_VERSION
		);
	}

	/**
	 * Same plate image on the WooCommerce order screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_order_styles( $hook ) {
		unset( $hook );

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ( false === strpos( (string) $screen->id, 'shop_order' ) && false === strpos( (string) $screen->id, 'wc-orders' ) ) ) {
			return;
		}

		$this->enqueue_preview_style();
	}

	/**
	 * Shop styles, including the configured-plate thumbnail used in the cart.
	 */
	public function enqueue_assets() {
		if ( function_exists( 'WC' ) ) {
			$this->enqueue_preview_style();
		}

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

		$js_file = APD_PLUGIN_DIR . 'assets/js/configurator.js';

		$this->enqueue_preview_style();

		$faces = '';

		foreach ( $payload['format']['fonts'] as $font ) {
			$family = isset( $font['family'] ) ? (string) $font['family'] : '';

			if ( '' !== $family && ! empty( $font['id'] ) ) {
				$family .= ' ' . $font['id'];
			}

			$faces .= sprintf(
				'@font-face{font-family:%1$s;src:url(%2$s) format("woff2");font-weight:400;font-style:%3$s;font-display:swap;}',
				wp_json_encode( $family ),
				wp_json_encode( isset( $font['url'] ) ? (string) $font['url'] : '' ),
				( isset( $font['style'] ) && 'italic' === $font['style'] ) ? 'italic' : 'normal'
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

		$this->pending_cart_edit = null;

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
		$this->pending_cart_edit         = $this->pending_edit_for_product( $product_id );

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
	 * Name and thumbnail of a configured line open that same cart line.
	 *
	 * Classic cart, classic mini-cart, and the Store API cart all read this filter.
	 * Order screens and emails do not.
	 *
	 * @param string               $permalink     Product URL.
	 * @param array<string, mixed> $cart_item     Cart line.
	 * @param string               $cart_item_key Cart key.
	 * @return string
	 */
	public function cart_item_permalink( $permalink, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) || '' === (string) $cart_item_key ) {
			return $permalink;
		}

		$product = isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product
			? $cart_item['data']
			: wc_get_product( isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0 );
		$url     = self::cart_edit_url( $product, (string) $cart_item_key );

		return '' === $url ? $permalink : $url;
	}

	/**
	 * Replace the cart line that this visit was opened from.
	 *
	 * A matching configuration stays on the same WooCommerce line and keeps its quantity.
	 * A changed configuration is added, then the previous line is removed and the new line
	 * keeps that quantity. Other cart lines are left as they are.
	 *
	 * @param string               $cart_item_key  New cart key.
	 * @param int                  $product_id     Product ID.
	 * @param int                  $quantity       Posted quantity.
	 * @param int                  $variation_id   Variation ID.
	 * @param array<string, mixed> $variation      Variation attributes.
	 * @param array<string, mixed> $cart_item_data Cart item data.
	 */
	public function replace_edited_cart_line( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $quantity, $variation_id, $variation, $cart_item_data );

		$pending                  = $this->pending_cart_edit;
		$this->pending_cart_edit = null;

		if ( ! is_array( $pending ) || (int) $pending['product_id'] !== (int) $product_id || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$old_key = (string) $pending['key'];
		$old_qty = max( 1, (int) $pending['quantity'] );

		if ( $old_key !== $cart_item_key && WC()->cart->get_cart_item( $old_key ) ) {
			WC()->cart->remove_cart_item( $old_key );
		}

		WC()->cart->set_quantity( $cart_item_key, $old_qty, true );
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
	 * Replace the product photo with the plate the shopper configured.
	 *
	 * @param string               $thumbnail Existing HTML.
	 * @param array<string, mixed> $cart_item Cart line.
	 * @return string
	 */
	public function cart_item_thumbnail( $thumbnail, $cart_item ) {
		$url = self::preview_url_from_config( isset( $cart_item[ self::CART_KEY ] ) ? $cart_item[ self::CART_KEY ] : null );

		return '' === $url ? $thumbnail : self::preview_img_html( $url );
	}

	/**
	 * Same configured plate for the block cart, mini-cart, and checkout.
	 *
	 * @param array<int, object>   $images    Product images.
	 * @param array<string, mixed> $cart_item Cart line.
	 * @return array<int, object>
	 */
	public function store_api_cart_images( $images, $cart_item ) {
		$url = self::preview_url_from_config( isset( $cart_item[ self::CART_KEY ] ) ? $cart_item[ self::CART_KEY ] : null );

		if ( '' === $url ) {
			return $images;
		}

		$image = ( ! empty( $images[0] ) && is_object( $images[0] ) ) ? clone $images[0] : new stdClass();
		if ( ! isset( $image->id ) ) {
			$image->id = 0;
		}
		$image->src       = $url;
		$image->thumbnail = $url;
		$image->srcset    = '';
		$image->sizes     = '';
		if ( ! isset( $image->name ) ) {
			$image->name = '';
		}
		if ( ! isset( $image->alt ) ) {
			$image->alt = '';
		}

		return array( $image );
	}

	/**
	 * Configured plate on the admin order line.
	 *
	 * @param string                $thumbnail Existing HTML.
	 * @param int                   $item_id   Order item ID.
	 * @param WC_Order_Item_Product $item      Order line.
	 * @return string
	 */
	public function admin_order_item_thumbnail( $thumbnail, $item_id, $item ) {
		unset( $item_id );
		$url = self::preview_url_from_order_item( $item );

		return '' === $url ? $thumbnail : self::preview_img_html( $url );
	}

	/**
	 * Configured plate in order emails.
	 *
	 * @param string                $thumbnail Existing HTML.
	 * @param WC_Order_Item_Product $item      Order line.
	 * @return string
	 */
	public function order_item_thumbnail( $thumbnail, $item ) {
		$url = self::preview_url_from_order_item( $item );

		return '' === $url ? $thumbnail : self::preview_img_html( $url );
	}

	/**
	 * Configured plate beside the name on customer order pages.
	 *
	 * Emails and the admin order screen already have a thumbnail column.
	 *
	 * @param string                $name Item name HTML.
	 * @param WC_Order_Item_Product $item Order line.
	 * @return string
	 */
	public function order_item_name( $name, $item ) {
		if ( is_admin() || did_action( 'woocommerce_email_header' ) || doing_action( 'woocommerce_email_order_details' ) ) {
			return $name;
		}

		$url = self::preview_url_from_order_item( $item );

		return '' === $url ? $name : self::preview_img_html( $url ) . $name;
	}

	/**
	 * Saved plate image for a cart configuration.
	 *
	 * @param mixed $config Cart config.
	 * @return string
	 */
	public static function preview_url_from_config( $config ) {
		if ( ! is_array( $config ) || empty( $config['preview_url'] ) ) {
			return '';
		}

		$url    = (string) $config['preview_url'];
		$upload = wp_upload_dir();
		$base   = trailingslashit( $upload['baseurl'] ) . 'apd-previews/';

		if ( 0 !== strpos( $url, $base ) || ! preg_match( '#/apd-previews/[a-f0-9]{64}\.png$#', $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Saved plate image for an order line.
	 *
	 * @param mixed $item Order item.
	 * @return string
	 */
	public static function preview_url_from_order_item( $item ) {
		if ( ! $item instanceof WC_Order_Item ) {
			return '';
		}

		$raw = $item->get_meta( '_apd_config', true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}

		$config = json_decode( $raw, true );

		return self::preview_url_from_config( $config );
	}

	/**
	 * Image tag for a stored plate preview.
	 *
	 * @param string $url Preview URL.
	 * @return string
	 */
	public static function preview_img_html( $url ) {
		return '<img class="apd-config-preview" src="' . esc_url( $url ) . '" alt="" width="180" style="display:block;width:180px;max-width:100%;height:auto;background:#fff;" />';
	}

	/**
	 * Write a shopper's canvas snapshot and return its URL.
	 *
	 * @param string $data_url data:image/png;base64,...
	 * @return string Empty when the payload is not a small PNG.
	 */
	public static function store_preview_png( $data_url ) {
		$check = APD_Security::validate_png_data_url( $data_url, 1500000 );

		if ( is_wp_error( $check ) ) {
			return '';
		}

		$raw = base64_decode( substr( $data_url, strlen( 'data:image/png;base64,' ) ), true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}

		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'apd-previews';

		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$name = hash( 'sha256', $raw ) . '.png';
		$path = $dir . '/' . $name;

		if ( ! is_file( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$written = file_put_contents( $path, $raw );

			if ( false === $written ) {
				return '';
			}
		}

		return trailingslashit( $upload['baseurl'] ) . 'apd-previews/' . $name;
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

		$this->stale_toasts[] = sprintf(
			/* translators: %s: product name, linked to the product page when possible */
			__( '“%s” must be configured again because the plate settings have changed. It was removed from your cart.', 'auto-plate-designer' ),
			$label
		);
	}

	/**
	 * White toast on the right. The theme notice bar was painting this sentence through the header.
	 */
	public function render_stale_toasts() {
		if ( empty( $this->stale_toasts ) ) {
			return;
		}

		$messages            = $this->stale_toasts;
		$this->stale_toasts  = array();
		$close               = __( 'Close', 'auto-plate-designer' );
		?>
		<div class="apd-stale-toasts" data-apd-stale-toasts>
			<?php foreach ( $messages as $message ) : ?>
				<div class="apd-stale-toast" role="status">
					<p><?php echo wp_kses_post( $message ); ?></p>
					<button type="button" class="apd-stale-toast-close" data-apd-stale-close aria-label="<?php echo esc_attr( $close ); ?>">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<style>
			.apd-stale-toasts {
				position: fixed;
				top: 1rem;
				right: 1rem;
				left: auto;
				z-index: 100000;
				display: flex;
				flex-direction: column;
				gap: 0.75rem;
				width: min(22rem, calc(100vw - 2rem));
				margin: 0;
				pointer-events: none;
			}
			body.admin-bar .apd-stale-toasts {
				top: 3.25rem;
			}
			@media (max-width: 782px) {
				body.admin-bar .apd-stale-toasts {
					top: 4.25rem;
				}
			}
			.apd-stale-toast {
				position: relative;
				pointer-events: auto;
				box-sizing: border-box;
				margin: 0;
				padding: 1rem 2.4rem 1rem 1rem;
				background: #fff;
				color: #1a1a1a;
				border: 1px solid #e4e4e4;
				border-radius: 8px;
				box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
				font-size: 0.95rem;
				line-height: 1.45;
			}
			.apd-stale-toast p {
				margin: 0;
				color: inherit;
				font-size: inherit;
				line-height: inherit;
			}
			.apd-stale-toast a {
				color: inherit;
				text-decoration: underline;
			}
			.apd-stale-toast-close {
				position: absolute;
				top: 0.35rem;
				right: 0.35rem;
				width: 1.75rem;
				height: 1.75rem;
				margin: 0;
				padding: 0;
				border: 0;
				border-radius: 4px;
				background: transparent;
				color: #1a1a1a;
				font-size: 1.25rem;
				line-height: 1;
				cursor: pointer;
			}
			.apd-stale-toast-close:hover,
			.apd-stale-toast-close:focus {
				background: #f2f2f2;
			}
		</style>
		<script>
			document.addEventListener('click', function (event) {
				var button = event.target.closest('[data-apd-stale-close]');
				if (!button) {
					return;
				}
				var toast = button.closest('.apd-stale-toast');
				var stack = button.closest('[data-apd-stale-toasts]');
				if (toast) {
					toast.remove();
				}
				if (stack && !stack.querySelector('.apd-stale-toast')) {
					stack.remove();
				}
			});
		</script>
		<?php
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

		$payload = APD_Admin_Settings::locked_design_payload( $product_id, APD_Formats::frontend_payload( $format ) );
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
				$design_row = array(
					'id'       => isset( $design['id'] ) ? (string) $design['id'] : '',
					'name'     => isset( $design['name'] ) ? (string) $design['name'] : '',
					'code'     => isset( $design['code'] ) ? (string) $design['code'] : '',
					'text_box' => APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() ),
				);

				if ( ! empty( $design['split_text'] ) ) {
					$design_row['split_text']     = true;
					$design_row['text_box_right'] = isset( $design['text_box_right'] ) && is_array( $design['text_box_right'] ) ? $design['text_box_right'] : array();
					$design_row['side_max_chars'] = isset( $design['side_max_chars'] ) && is_array( $design['side_max_chars'] ) ? $design['side_max_chars'] : array();
				}

				$designs[] = $design_row;
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

		$fingerprint = array(
			'enabled'          => true,
			'format_id'        => isset( $format['id'] ) ? (string) $format['id'] : '',
			'format_name'      => isset( $format['name'] ) ? (string) $format['name'] : '',
			'format_type'      => isset( $format['type'] ) ? (string) $format['type'] : '',
			'width'            => isset( $format['width'] ) ? (int) $format['width'] : 0,
			'height'           => isset( $format['height'] ) ? (int) $format['height'] : 0,
			'border_width'     => isset( $format['border_width'] ) ? (int) $format['border_width'] : 0,
			'border_color'     => isset( $format['border_color'] ) ? (string) $format['border_color'] : '',
			'no_frame'         => ! empty( $format['no_frame'] ),
			'frame_choice'     => ! isset( $format['type'] ) || APD_Formats::offers_frame_choice( (string) $format['type'] ),
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

		if ( ! empty( $payload['split_text'] ) ) {
			$fingerprint['split_text']     = true;
			$fingerprint['text_box_right'] = isset( $payload['text_box_right'] ) && is_array( $payload['text_box_right'] ) ? $payload['text_box_right'] : array();
			$fingerprint['side_max_chars'] = isset( $payload['side_max_chars'] ) && is_array( $payload['side_max_chars'] ) ? $payload['side_max_chars'] : array();
			$fingerprint['text_box']       = isset( $payload['text_box'] ) && is_array( $payload['text_box'] ) ? $payload['text_box'] : $fingerprint['text_box'];
		}

		if ( isset( $format['type'] ) && APD_Formats::is_french( (string) $format['type'] ) ) {
			$fingerprint['fr_groups']  = isset( $payload['fr_groups'] ) && is_array( $payload['fr_groups'] ) ? $payload['fr_groups'] : APD_Formats::french_group_limits( $format );
			$fingerprint['text_boxes'] = isset( $payload['text_boxes'] ) && is_array( $payload['text_boxes'] ) ? $payload['text_boxes'] : APD_Formats::stored_group_boxes( $format, 3 );
			if ( APD_Formats::is_french_temporary( (string) $format['type'] ) && ! empty( $format['fr_date_custom'] ) ) {
				$fingerprint['fr_month'] = isset( $format['fr_month'] ) ? $format['fr_month'] : array();
				$fingerprint['fr_year']  = isset( $format['fr_year'] ) ? $format['fr_year'] : array();
			}
			if ( APD_Formats::is_french_region( (string) $format['type'] ) ) {
				$fingerprint['fr_region_box'] = isset( $payload['fr_region_box'] ) && is_array( $payload['fr_region_box'] )
					? $payload['fr_region_box']
					: APD_Formats::default_french_region_box( $format );
			}
		}

		if ( isset( $format['type'] ) && APD_Formats::offers_country_choice( (string) $format['type'] ) && isset( $format['preset_mode'] ) && 'selected' === $format['preset_mode'] ) {
			$fingerprint['preset_ids']        = isset( $format['preset_ids'] ) && is_array( $format['preset_ids'] ) ? array_values( $format['preset_ids'] ) : array();
			$fingerprint['default_preset_id'] = isset( $format['default_preset_id'] ) ? (string) $format['default_preset_id'] : '';
		}

		if ( isset( $format['type'] ) && APD_Formats::is_english( (string) $format['type'] ) ) {
			$fingerprint['slogan_max'] = APD_Formats::slogan_limit( $format );
			$fingerprint['slogan_box'] = isset( $payload['slogan_box'] ) && is_array( $payload['slogan_box'] )
				? $payload['slogan_box']
				: APD_Formats::default_slogan_box( $format );
		}

		if ( isset( $format['type'] ) && APD_Formats::is_austria( (string) $format['type'] ) ) {
			$fingerprint['at_groups']  = isset( $payload['at_groups'] ) && is_array( $payload['at_groups'] ) ? $payload['at_groups'] : APD_Formats::austria_limits( $format );
			$fingerprint['at_region']  = isset( $payload['at_region'] ) && is_array( $payload['at_region'] ) ? $payload['at_region'] : APD_Formats::default_region_box( $format );
			$fingerprint['text_boxes'] = isset( $payload['text_boxes'] ) && is_array( $payload['text_boxes'] ) ? $payload['text_boxes'] : APD_Formats::stored_group_boxes( $format, 2 );
		}

		return $fingerprint;
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
	 * Latest configuration for this product already in the cart.
	 *
	 * The product page no longer prefills from this. A new visit starts from
	 * the product defaults.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function latest_cart_config( $product_id ) {
		$product_id = absint( $product_id );

		if ( $product_id < 1 || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$found = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			$item_product = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;

			if ( $item_product !== $product_id || empty( $item[ self::CART_KEY ] ) || ! is_array( $item[ self::CART_KEY ] ) ) {
				continue;
			}

			$found = $item[ self::CART_KEY ];
		}

		return $found;
	}

	/**
	 * Cart line opened from an edit link, or an empty visit when the key is missing or gone.
	 *
	 * @param int $product_id Product ID.
	 * @return array{key: string, restore: array<string, mixed>}
	 */
	public static function requested_cart_edit( $product_id ) {
		$raw = isset( $_GET['apd_cart_item'] ) ? sanitize_text_field( wp_unslash( $_GET['apd_cart_item'] ) ) : '';

		return self::cart_edit_from_key( $product_id, $raw );
	}

	/**
	 * Configurator fields stored on one cart line for this product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Cart item key.
	 * @return array{key: string, restore: array<string, mixed>}
	 */
	public static function cart_edit_from_key( $product_id, $key ) {
		$empty = array(
			'key'     => '',
			'restore' => array(),
		);
		$key   = is_string( $key ) ? $key : '';

		if ( '' === $key || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $empty;
		}

		$item = WC()->cart->get_cart_item( $key );

		if ( ! is_array( $item ) || (int) ( $item['product_id'] ?? 0 ) !== (int) $product_id ) {
			return $empty;
		}

		if ( empty( $item[ self::CART_KEY ] ) || ! is_array( $item[ self::CART_KEY ] ) ) {
			return $empty;
		}

		return array(
			'key'     => $key,
			'restore' => self::cart_restore_fields( $item[ self::CART_KEY ] ),
		);
	}

	/**
	 * Product URL that reopens one configured cart line.
	 *
	 * @param WC_Product $product       Product.
	 * @param string     $cart_item_key Cart key.
	 * @return string
	 */
	public static function cart_edit_url( $product, $cart_item_key ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$permalink = $product->get_permalink();

		if ( ! is_string( $permalink ) || '' === $permalink || '' === $cart_item_key ) {
			return '';
		}

		return add_query_arg( 'apd_cart_item', $cart_item_key, $permalink );
	}

	/**
	 * Fields the product form can paint. Price and checkout data stay on the cart line.
	 *
	 * @param array<string, mixed> $config Stored cart configuration.
	 * @return array<string, mixed>
	 */
	private static function cart_restore_fields( $config ) {
		$keys   = array(
			'text',
			'font_id',
			'preset_id',
			'design_id',
			'no_frame',
			'text_color',
			'holder_color',
			'background_color',
			'border_color',
			'slogan_color',
			'fr_month',
			'fr_year',
			'fr_region',
			'slogan',
			'unstyled',
			'comment',
			'script',
			'frame_plain',
		);
		$restore = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $config ) ) {
				$restore[ $key ] = $config[ $key ];
			}
		}

		return $restore;
	}

	/**
	 * Cart line posted with this add, when it still belongs to this product.
	 *
	 * Quantity is read before WooCommerce merges or adds a line.
	 *
	 * @param int $product_id Product ID.
	 * @return array{key: string, product_id: int, quantity: int}|null
	 */
	private function pending_edit_for_product( $product_id ) {
		if ( ! isset( $_POST['apd_cart_item'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return null;
		}

		$key  = sanitize_text_field( wp_unslash( $_POST['apd_cart_item'] ) );
		$edit = self::cart_edit_from_key( $product_id, $key );

		if ( '' === $edit['key'] ) {
			return null;
		}

		$item = WC()->cart->get_cart_item( $edit['key'] );

		return array(
			'key'        => $edit['key'],
			'product_id' => (int) $product_id,
			'quantity'   => isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1,
		);
	}

	/**
	 * Keep the fonts each alphabet on this product is allowed to use.
	 *
	 * @param array<string, mixed> $payload Format payload.
	 * @param array<string, mixed> $state   Script state.
	 * @return array<string, mixed>
	 */
	private static function filter_payload_fonts( $payload, $state ) {
		$scripts = isset( $state['scripts'] ) && is_array( $state['scripts'] ) ? $state['scripts'] : array();

		if ( empty( $scripts ) || empty( $payload['fonts'] ) || ! is_array( $payload['fonts'] ) ) {
			return $payload;
		}

		$latin_ids = isset( $state['latin_fonts'] ) && is_array( $state['latin_fonts'] ) ? $state['latin_fonts'] : array();
		$cyr_ids   = isset( $state['cyrillic_fonts'] ) && is_array( $state['cyrillic_fonts'] ) ? $state['cyrillic_fonts'] : array();
		$fonts     = array();

		foreach ( $payload['fonts'] as $font ) {
			if ( ! is_array( $font ) || empty( $font['id'] ) ) {
				continue;
			}

			$font_scripts = array();
			$font_id      = (string) $font['id'];

			if ( in_array( 'latin', $scripts, true ) && ( empty( $latin_ids ) || in_array( $font_id, $latin_ids, true ) ) ) {
				$font_scripts[] = 'latin';
			}

			if ( in_array( 'cyrillic', $scripts, true ) && in_array( $font_id, $cyr_ids, true ) ) {
				$font_scripts[] = 'cyrillic';
			}

			if ( empty( $font_scripts ) ) {
				continue;
			}

			$font['scripts'] = $font_scripts;
			$fonts[]         = $font;
		}

		if ( ! empty( $fonts ) ) {
			$payload['fonts'] = $fonts;
		}

		return $payload;
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
		$edit   = self::requested_cart_edit( $product_id );
		$format_payload = APD_Admin_Settings::locked_design_payload( $product_id, APD_Formats::frontend_payload( $format ) );
		$script_state   = APD_Admin_Settings::product_script_state( $product_id, $format );
		$format_payload = self::filter_payload_fonts( $format_payload, $script_state );

		return array(
			'format'       => $format_payload,
			'scripts'      => $script_state['scripts'],
			'layouts'      => $layouts,
			'color_fields' => APD_Admin_Settings::product_color_fields( $product_id ),
			'palettes'     => APD_Admin_Settings::product_offered_colors( $product_id ),
			'default_text'   => APD_Admin_Settings::product_initial_text( $product_id, $format ),
			'default_text_cyrillic' => APD_Admin_Settings::product_cyrillic_text( $product_id ),
			'default_slogan' => APD_Admin_Settings::product_initial_slogan( $product_id, $format ),
			'region_text'    => APD_Admin_Settings::product_region_text( $product_id ),
			'cart_restore'   => $edit['restore'],
			'cart_item_key'  => $edit['key'],
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
				'textColor'     => __( 'Text color', 'auto-plate-designer' ),
				'inkFrameColor' => __( 'Text and frame color', 'auto-plate-designer' ),
				'borderColor'   => __( 'Border color', 'auto-plate-designer' ),
				'plateColor'    => __( 'Plate color', 'auto-plate-designer' ),
				'sloganColor'   => __( 'Slogan color', 'auto-plate-designer' ),
				'sloganLabel'   => __( 'Slogan', 'auto-plate-designer' ),
				'stripColor'    => __( 'Strip color', 'auto-plate-designer' ),
				'holderColor'   => __( 'Holder color', 'auto-plate-designer' ),
				'frameLabel'    => __( 'Add frame', 'auto-plate-designer' ),
				'chars'         => __( '%1$s / %2$s characters', 'auto-plate-designer' ),
				'invalid'       => __( 'Please enter valid plate text before adding to cart.', 'auto-plate-designer' ),
				'digitalNotice' => __( 'Past the standard character count the letters are printed digitally. The frame stays embossed.', 'auto-plate-designer' ),
				'cyrillicNotice' => __( 'The plate letters are printed digitally and the frame stays embossed.', 'auto-plate-designer' ),
				'miniDigital'   => __( 'The letters are printed digitally.', 'auto-plate-designer' ),
				'latinOnly'     => __( 'Cyrillic letters are not allowed when Latin is selected.', 'auto-plate-designer' ),
				'cyrillicOnly'  => __( 'Latin letters are not allowed when Cyrillic is selected.', 'auto-plate-designer' ),
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

		$payload     = APD_Admin_Settings::locked_design_payload( $product_id, APD_Formats::frontend_payload( $format ) );
		$max_chars   = (int) $payload['max_chars'];
		$allow_empty = ! empty( $payload['allow_empty'] );
		$multiline   = ! empty( $payload['multiline'] );
		$format_type = isset( $format['type'] ) ? (string) $format['type'] : '';
		$text        = isset( $_POST['apd_text'] ) ? APD_Security::uppercase_plate_text( (string) wp_unslash( $_POST['apd_text'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$length_limit = $max_chars;

		if ( APD_Formats::format_uses_two_rows( $format ) && ( isset( $_POST['apd_text_row_1'] ) || isset( $_POST['apd_text_row_2'] ) ) ) {
			$row_limits = APD_Formats::suv_row_limits( $format );
			$row1       = isset( $_POST['apd_text_row_1'] ) ? APD_Security::uppercase_plate_text( (string) wp_unslash( $_POST['apd_text_row_1'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$row2       = isset( $_POST['apd_text_row_2'] ) ? APD_Security::uppercase_plate_text( (string) wp_unslash( $_POST['apd_text_row_2'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$row1_clean = APD_Security::sanitize_plate_text( $row1, false );
			$row2_clean = APD_Security::sanitize_plate_text( $row2, false );
			$row_length = static function ( $value ) {
				return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			};

			foreach ( array( array( $row1_clean, $row_limits[0] ), array( $row2_clean, $row_limits[1] ) ) as $row_check ) {
				if ( $row_length( $row_check[0] ) > $row_check[1] ) {
					return APD_Security::validate_plate_text(
						$row_check[0],
						array(
							'max_length'  => $row_check[1],
							'multiline'   => false,
							'allow_empty' => true,
						)
					);
				}
			}

			$text         = APD_Formats::join_suv_rows( $row1_clean, $row2_clean );
			$multiline    = true;
			$length_limit = $row_limits[0] + $row_limits[1] + ( false !== strpos( $text, "\n" ) ? 1 : 0 );
		}

		$posted_design = isset( $_POST['apd_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_design_id'] ) ) : '';
		$side_limits   = APD_Formats::active_us_side_limits( $payload, $posted_design );

		if ( is_array( $side_limits ) && ( isset( $_POST['apd_text_left'] ) || isset( $_POST['apd_text_right'] ) ) ) {
			$side_left  = isset( $_POST['apd_text_left'] ) ? APD_Security::uppercase_plate_text( (string) wp_unslash( $_POST['apd_text_left'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$side_right = isset( $_POST['apd_text_right'] ) ? APD_Security::uppercase_plate_text( (string) wp_unslash( $_POST['apd_text_right'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$left_clean  = APD_Security::sanitize_plate_text( $side_left, false );
			$right_clean = APD_Security::sanitize_plate_text( $side_right, false );
			$side_length = static function ( $value ) {
				return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			};

			foreach ( array( array( $left_clean, $side_limits[0] ), array( $right_clean, $side_limits[1] ) ) as $side_check ) {
				if ( $side_length( $side_check[0] ) > $side_check[1] ) {
					return APD_Security::validate_plate_text(
						$side_check[0],
						array(
							'max_length'  => $side_check[1],
							'multiline'   => false,
							'allow_empty' => true,
						)
					);
				}
			}

			$text         = APD_Formats::join_suv_rows( $left_clean, $right_clean );
			$multiline    = true;
			$length_limit = $side_limits[0] + $side_limits[1] + ( false !== strpos( $text, "\n" ) ? 1 : 0 );
			$max_chars    = $side_limits[0] + $side_limits[1];
		}

		$fr_month  = '';
		$fr_year   = '';
		$fr_region = '';
		$slogan    = '';

		if ( APD_Formats::is_french( $format_type ) ) {
			$fr_groups = array();

			if ( APD_Formats::is_french_temporary( $format_type ) ) {
				$fr_groups[] = isset( $_POST['apd_fr_b'] ) ? (string) wp_unslash( $_POST['apd_fr_b'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$fr_groups[] = isset( $_POST['apd_fr_c'] ) ? (string) wp_unslash( $_POST['apd_fr_c'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			} else {
				$fr_groups[] = isset( $_POST['apd_fr_a'] ) ? (string) wp_unslash( $_POST['apd_fr_a'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$fr_groups[] = isset( $_POST['apd_fr_b'] ) ? (string) wp_unslash( $_POST['apd_fr_b'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$fr_groups[] = isset( $_POST['apd_fr_c'] ) ? (string) wp_unslash( $_POST['apd_fr_c'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			$built = APD_Formats::french_posted_text(
				$format,
				$fr_groups,
				array(
					'month' => isset( $_POST['apd_fr_month'] ) ? (string) wp_unslash( $_POST['apd_fr_month'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'year'  => isset( $_POST['apd_fr_year'] ) ? (string) wp_unslash( $_POST['apd_fr_year'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				)
			);

			if ( is_wp_error( $built ) ) {
				return $built;
			}

			$text      = $built['text'];
			$fr_month  = $built['fr_month'];
			$fr_year   = $built['fr_year'];
			if ( APD_Formats::is_french_region( $format_type ) ) {
				$fr_region = APD_Formats::french_region_code( isset( $_POST['apd_fr_region'] ) ? (string) wp_unslash( $_POST['apd_fr_region'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			$multiline = false;
			$length_limit = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

			if ( $length_limit < 1 ) {
				$length_limit = 1;
			}
		}

		if ( APD_Formats::is_austria( $format_type ) ) {
			$built = APD_Formats::austria_posted_text(
				$format,
				isset( $_POST['apd_at_left'] ) ? (string) wp_unslash( $_POST['apd_at_left'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				isset( $_POST['apd_at_right'] ) ? (string) wp_unslash( $_POST['apd_at_right'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			);

			if ( is_wp_error( $built ) ) {
				return $built;
			}

			$text         = $built['text'];
			$multiline    = true;
			$length_limit = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

			if ( $length_limit < 1 ) {
				$length_limit = 1;
			}
		}

		if ( APD_Formats::is_english( $format_type ) ) {
			$slogan_raw   = isset( $_POST['apd_slogan'] ) ? (string) wp_unslash( $_POST['apd_slogan'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$slogan_clean = APD_Security::sanitize_plate_text( $slogan_raw, false );
			$slogan_max   = APD_Formats::slogan_limit( $format );
			$slogan_len   = function_exists( 'mb_strlen' ) ? mb_strlen( $slogan_clean, 'UTF-8' ) : strlen( $slogan_clean );

			if ( $slogan_len > $slogan_max ) {
				return APD_Security::validate_plate_text(
					$slogan_clean,
					array(
						'max_length'  => $slogan_max,
						'multiline'   => false,
						'allow_empty' => true,
					)
				);
			}

			$slogan = $slogan_clean;
		}

		$unstyled = APD_Formats::is_holder( $format_type ) && isset( $_POST['apd_plain'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apd_plain'] ) );

		if ( $unstyled ) {
			$text         = '';
			$multiline    = false;
			$length_limit = $max_chars;
		}

		$script_state  = APD_Admin_Settings::product_script_state( $product_id, $format );
		$script        = '';
		$posted_script = isset( $_POST['apd_script'] ) ? sanitize_key( wp_unslash( $_POST['apd_script'] ) ) : '';

		if ( ! empty( $script_state['scripts'] ) ) {
			$script = in_array( $posted_script, $script_state['scripts'], true ) ? $posted_script : (string) $script_state['scripts'][0];
		}

		$text_check = APD_Security::validate_plate_text(
			$text,
			array(
				'max_length'       => $length_limit,
				'multiline'        => $multiline,
				'allow_empty'      => $allow_empty,
				'skip_admin_regex' => 'cyrillic' === $script,
			)
		);

		if ( is_wp_error( $text_check ) ) {
			return $text_check;
		}

		$script_check = APD_Security::validate_plate_script( $text, $script );

		if ( is_wp_error( $script_check ) ) {
			return $script_check;
		}

		$comment = isset( $_POST['apd_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apd_comment'] ) ) : '';

		if ( function_exists( 'mb_substr' ) ) {
			$comment = mb_substr( $comment, 0, APD_Formats::COMMENT_MAX, 'UTF-8' );
		} else {
			$comment = substr( $comment, 0, APD_Formats::COMMENT_MAX );
		}

		$font_id    = isset( $_POST['apd_font_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_font_id'] ) ) : '';
		$font_label = '';

		foreach ( $payload['fonts'] as $font ) {
			if ( $font['id'] === $font_id ) {
				$font_label = APD_Formats::font_shop_label( $font );
				break;
			}
		}

		if ( '' !== $script && ! empty( $payload['fonts'] ) ) {
			$script_fonts = array();

			foreach ( $payload['fonts'] as $font ) {
				$font_id_row = isset( $font['id'] ) ? (string) $font['id'] : '';
				$allowed     = 'cyrillic' === $script ? $script_state['cyrillic_fonts'] : $script_state['latin_fonts'];
				$matches     = 'cyrillic' === $script
					? in_array( $font_id_row, $allowed, true )
					: ( empty( $allowed ) || in_array( $font_id_row, $allowed, true ) );

				if ( $matches ) {
					$script_fonts[] = $font;
				}
			}

			if ( ! empty( $script_fonts ) ) {
				$found_script_font = false;

				foreach ( $script_fonts as $font ) {
					if ( isset( $font['id'] ) && (string) $font['id'] === $font_id ) {
						$found_script_font = true;
						$font_label        = APD_Formats::font_shop_label( $font );
						break;
					}
				}

				if ( ! $found_script_font ) {
					$font_id    = '';
					$font_label = '';
					$payload['fonts'] = $script_fonts;
				}
			}
		}

		if ( $unstyled ) {
			$font_id    = '';
			$font_label = '';
		} elseif ( '' === $font_label && ! empty( $payload['fonts'] ) ) {
			$font_id = APD_Formats::default_font_id( $payload['fonts'], isset( $format['type'] ) ? (string) $format['type'] : '' );

			foreach ( $payload['fonts'] as $font ) {
				if ( $font['id'] === $font_id ) {
					$font_label = APD_Formats::font_shop_label( $font );
					break;
				}
			}
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

		$design_id  = '';
		$type       = isset( $format['type'] ) ? (string) $format['type'] : '';
		$design_box   = APD_Formats::sanitize_text_box(
			isset( $payload['text_box'] ) ? $payload['text_box'] : array(),
			$type,
			$format
		);

		if ( APD_Formats::uses_plate_designs( $type ) ) {
			$design_id = isset( $_POST['apd_design_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_design_id'] ) ) : '';
			$designs   = isset( $payload['designs'] ) && is_array( $payload['designs'] ) ? $payload['designs'] : array();

			$design_matched = false;

			foreach ( $designs as $design ) {
				if ( $design['id'] === $design_id ) {
					$design_box     = APD_Designs::sanitize_text_box( isset( $design['text_box'] ) ? $design['text_box'] : array() );
					$design_matched = true;
					break;
				}
			}

			if ( ! $design_matched && ! empty( $designs ) ) {
				$design_id  = $designs[0]['id'];
				$design_box = APD_Designs::sanitize_text_box( isset( $designs[0]['text_box'] ) ? $designs[0]['text_box'] : array() );
			} elseif ( ! $design_matched ) {
				$design_id = '';
			}
		}

		$color_fields   = APD_Admin_Settings::product_color_fields( $product_id );
		$plain_family   = APD_Formats::offers_uncolored_frame( $type );
		$format_framed  = empty( $format['no_frame'] ) && ( isset( $format['border_width'] ) ? (int) $format['border_width'] : 0 ) > 0;
		$letters        = '';
		$frame_plain    = null;

		if ( '' !== APD_Formats::letter_rule( $type ) ) {
			$letters = APD_Formats::letters_are_digital( $format, $script, $text ) ? 'digital' : 'embossed';
		}

		if ( $plain_family && $format_framed && ( 'color' === $type || 'embossed' === $letters || 'cyrillic' === $script ) ) {
			$frame_plain = isset( $_POST['apd_frame_plain'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apd_frame_plain'] ) );
		} elseif ( $plain_family && $format_framed && 'digital' === $letters ) {
			$frame_plain = false;
		}

		$legacy_no_frame = $plain_family && isset( $_POST['apd_legacy_no_frame'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apd_legacy_no_frame'] ) );
		$frame_chosen    = $legacy_no_frame ? false : ( $plain_family ? $format_framed : ( APD_Formats::offers_frame_choice( $type ) && isset( $_POST['apd_frame'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apd_frame'] ) ) ) );
		if ( $legacy_no_frame ) {
			$frame_plain = null;
		}
		$english_frame = APD_Formats::is_english( $type );
		$admin_border  = isset( $format['border_width'] ) ? (int) $format['border_width'] : 0;
		$offered       = APD_Admin_Settings::product_offered_colors( $product_id );
		$text_color    = $this->snapshot_palette_color( 'apd_text_color', APD_Formats::shop_swatch_colors( $type, 'text', $color_fields, $offered ) );
		$border_color  = $this->snapshot_palette_color( 'apd_border_color', ( $frame_chosen || $english_frame ) ? APD_Formats::shop_swatch_colors( $type, 'border', $color_fields, $offered ) : array() );
		$fill_color    = $this->snapshot_palette_color( 'apd_background_color', APD_Formats::shop_swatch_colors( $type, 'background', $color_fields, $offered ) );
		$holder_color  = $this->snapshot_palette_color( 'apd_holder_color', APD_Formats::shop_swatch_colors( $type, 'holder', $color_fields, $offered ) );
		$slogan_color  = $this->snapshot_palette_color( 'apd_slogan_color', $english_frame ? APD_Formats::shop_swatch_colors( $type, 'slogan', $color_fields, $offered ) : array() );

		if ( $frame_chosen && '' === $border_color['hex'] && isset( $format['border_color'] ) ) {
			$border_color['hex'] = (string) $format['border_color'];
		}

		$shared_ink = 'digital' === $letters && APD_Formats::letters_follow_count( $type ) && ! APD_Formats::is_mini( $type );

		if ( true === $frame_plain ) {
			$border_color['hex']   = '' !== $fill_color['hex'] ? $fill_color['hex'] : '#FFFFFF';
			$border_color['label'] = '';
		} elseif ( $shared_ink && '' !== $text_color['hex'] ) {
			$border_color['hex']   = $text_color['hex'];
			$border_color['label'] = $text_color['label'];
		}

		$posted_preview = isset( $_POST['apd_preview'] ) ? (string) wp_unslash( $_POST['apd_preview'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$preview_url    = self::store_preview_png( $posted_preview );

		return array(
			'format_id'              => $format['id'],
			'preview_url'            => $preview_url,
			'format_name'            => isset( $format['name'] ) ? (string) $format['name'] : '',
			'format_type'            => $type,
			'format_type_label'      => APD_Formats::type_label( $type ),
			'format_width'           => isset( $format['width'] ) ? (int) $format['width'] : 0,
			'format_height'          => isset( $format['height'] ) ? (int) $format['height'] : 0,
			'format_border_width'    => $frame_chosen ? $admin_border : 0,
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
			'design_label'           => '',
			'design_code'            => '',
			'text_box'               => $design_box,
			'color_fields'           => $color_fields,
			'no_frame'               => ! $frame_chosen,
			'script'                 => $script,
			'comment'                => $comment,
			'letters'                => $letters,
			'frame_plain'            => $frame_plain,
			'text_color'             => $text_color['hex'],
			'text_color_label'       => $text_color['label'],
			'border_color'           => $border_color['hex'],
			'border_color_label'     => $border_color['label'],
			'background_color'       => $fill_color['hex'],
			'background_color_label' => $fill_color['label'],
			'holder_color'           => $holder_color['hex'],
			'holder_color_label'     => $holder_color['label'],
			'unstyled'               => $unstyled,
			'price_adjustment'       => (float) $format['price_adjustment'],
			'offer_fingerprint'      => self::offer_fingerprint( $product_id ),
			'fr_month'               => $fr_month,
			'fr_year'                => $fr_year,
			'fr_region'              => $fr_region,
			'slogan'                 => $slogan,
			'slogan_color'           => $slogan_color['hex'],
			'slogan_color_label'     => $slogan_color['label'],
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
			$text_value = (string) $config['text'];

			if ( APD_Formats::is_austria( $type ) ) {
				$text_value = str_replace( array( "\r\n", "\r", "\n" ), ' ', $text_value );
			} elseif ( false !== strpos( $text_value, "\n" ) ) {
				$text_parts = explode( "\n", $text_value, 2 );
				$text_value = $text_parts[0];

				if ( isset( $text_parts[1] ) && '' !== $text_parts[1] ) {
					$text_value .= ' / ' . $text_parts[1];
				}
			}

			if ( APD_Formats::is_french_temporary( $type ) ) {
				$fr_month = isset( $config['fr_month'] ) ? (string) $config['fr_month'] : '';
				$fr_year  = isset( $config['fr_year'] ) ? (string) $config['fr_year'] : '';

				if ( '' !== $fr_month || '' !== $fr_year ) {
					$text_value .= ' ' . $fr_month . '/' . $fr_year;
				}
			} elseif ( APD_Formats::is_french_region( $type ) && ! empty( $config['fr_region'] ) ) {
				$text_value .= ' ' . (string) $config['fr_region'];
			}

			$rows[] = array(
				'key'   => __( 'Text', 'auto-plate-designer' ),
				'value' => $text_value,
			);
		}

		if ( APD_Formats::is_english( $type ) && ! empty( $config['slogan'] ) ) {
			$rows[] = array(
				'key'   => __( 'Slogan', 'auto-plate-designer' ),
				'value' => (string) $config['slogan'],
			);
		}

		if ( APD_Formats::is_english( $type ) && self::config_includes_color( $config, 'slogan' ) && ! empty( $config['slogan'] ) && ! empty( $config['slogan_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Slogan color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['slogan_color'],
					isset( $config['slogan_color_label'] ) ? (string) $config['slogan_color_label'] : ''
				),
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

		$holder_type = APD_Formats::is_holder( $type );
		$text_empty  = ! isset( $config['text'] ) || '' === trim( (string) $config['text'] );

		if ( $text_empty && ( self::config_includes_color( $config, 'text' ) || self::config_includes_color( $config, 'holder_text' ) || self::config_includes_color( $config, 'color_text' ) || ! empty( $config['unstyled'] ) ) ) {
			$rows[] = array(
				'key'   => __( 'NO TEXT!', 'auto-plate-designer' ),
				'value' => __( 'NO TEXT!', 'auto-plate-designer' ),
			);
		} elseif ( ( self::config_includes_color( $config, 'text' ) || self::config_includes_color( $config, 'holder_text' ) || self::config_includes_color( $config, 'color_text' ) ) && ! empty( $config['text_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Text color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['text_color'],
					isset( $config['text_color_label'] ) ? (string) $config['text_color_label'] : ''
				),
			);
		}

		if ( APD_Formats::offers_frame_choice( $type ) && ( ! array_key_exists( 'frame_plain', $config ) || null === $config['frame_plain'] ) ) {
			$rows[] = array(
				'key'   => __( 'Frame', 'auto-plate-designer' ),
				'value' => ! empty( $config['no_frame'] ) ? __( 'Without frame', 'auto-plate-designer' ) : __( 'With frame', 'auto-plate-designer' ),
			);
		}

		if ( ! empty( $config['letters'] ) ) {
			$rows[] = array(
				'key'   => __( 'Letters', 'auto-plate-designer' ),
				'value' => 'digital' === $config['letters'] ? __( 'Digital print', 'auto-plate-designer' ) : __( 'Embossed letters', 'auto-plate-designer' ),
			);
		}

		if ( array_key_exists( 'frame_plain', $config ) && null !== $config['frame_plain'] && APD_Formats::offers_uncolored_frame( $type ) && empty( $config['no_frame'] ) ) {
			$rows[] = array(
				'key'   => __( 'Frame', 'auto-plate-designer' ),
				'value' => ! empty( $config['frame_plain'] ) ? __( 'Embossed, without color', 'auto-plate-designer' ) : __( 'Embossed frame', 'auto-plate-designer' ),
			);
		}

		if ( ! empty( $config['comment'] ) ) {
			$rows[] = array(
				'key'   => __( 'Comment', 'auto-plate-designer' ),
				'value' => (string) $config['comment'],
			);
		}

		$border_visible = APD_Formats::is_english( $type ) || empty( $config['no_frame'] );

		if ( ( self::config_includes_color( $config, 'border' ) || self::config_includes_color( $config, 'color_border' ) ) && $border_visible && ! empty( $config['border_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Border color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['border_color'],
					isset( $config['border_color_label'] ) ? (string) $config['border_color_label'] : ''
				),
			);
		}

		if ( self::config_includes_color( $config, 'holder' ) && ! empty( $config['holder_color'] ) ) {
			$rows[] = array(
				'key'   => __( 'Holder color', 'auto-plate-designer' ),
				'value' => self::format_color_display(
					(string) $config['holder_color'],
					isset( $config['holder_color_label'] ) ? (string) $config['holder_color_label'] : ''
				),
			);
		}

		if ( $holder_type && ! empty( $config['unstyled'] ) ) {
			$rows[] = array(
				'key'   => __( 'Strip color', 'auto-plate-designer' ),
				'value' => __( 'Without styling', 'auto-plate-designer' ),
			);
		} elseif ( ( self::config_includes_color( $config, 'background' ) || self::config_includes_color( $config, 'holder_strip' ) || self::config_includes_color( $config, 'color_background' ) ) && ! empty( $config['background_color'] ) ) {
			$fill_key = $holder_type
				? __( 'Strip color', 'auto-plate-designer' )
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
	private function snapshot_palette_color( $field, $colors ) {
		$colors = is_array( $colors ) ? $colors : array();

		if ( empty( $colors ) ) {
			return array(
				'hex'   => '',
				'label' => '',
			);
		}

		$hex = $this->posted_palette_color_from_list( $field, $colors );

		return array(
			'hex'   => $hex,
			'label' => $this->label_for_color_list( $colors, $hex ),
		);
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
	private function posted_palette_color_from_list( $field, $colors ) {
		$prefer  = 'apd_background_color' === $field ? array( '#FFFFFF' ) : array( '#000000' );
		$default = APD_Color_Palettes::preferred_hex( $colors, $prefer );
		$raw     = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

		if ( is_wp_error( APD_Security::validate_hex_color( $raw ) ) ) {
			return $default;
		}

		$hex = APD_Security::sanitize_hex_color( $raw );

		foreach ( $colors as $color ) {
			if ( isset( $color['hex'] ) && 0 === strcasecmp( (string) $color['hex'], $hex ) ) {
				return $hex;
			}
		}

		return $default;
	}

	/**
	 * Palette label from the colors offered for this control.
	 *
	 * @param array<int, array<string, string>> $colors Color rows.
	 * @param string                             $hex    Hex color.
	 * @return string
	 */
	private function label_for_color_list( $colors, $hex ) {
		foreach ( $colors as $color ) {
			if ( isset( $color['hex'] ) && 0 === strcasecmp( (string) $color['hex'], $hex ) ) {
				return isset( $color['label'] ) ? (string) $color['label'] : $hex;
			}
		}

		return $hex;
	}
}
