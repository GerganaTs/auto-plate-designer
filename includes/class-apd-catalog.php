<?php
/**
 * WooCommerce product categories for plate kinds.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeds and publishes catalog categories via a filterable definition list.
 */
final class APD_Catalog {

	/**
	 * Singleton instance.
	 *
	 * @var APD_Catalog|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return APD_Catalog
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register runtime hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'seed_terms' ), 20 );
		add_filter( 'get_terms_args', array( $this, 'exclude_unpublished' ), 10, 2 );
	}

	/**
	 * Category definitions. Other plugins may add rows with the filter.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function definitions() {
		$defs = array(
			'plates-eu'     => array(
				'name' => __( 'Plates EU', 'auto-plate-designer' ),
				'kind' => 'eu',
			),
			'plates-us'     => array(
				'name' => __( 'Plates USA', 'auto-plate-designer' ),
				'kind' => 'us',
			),
			'plates-moto'   => array(
				'name' => __( 'Motorcycle plates', 'auto-plate-designer' ),
				'kind' => 'moto',
			),
			'plates-suv'    => array(
				'name' => __( 'SUV plates', 'auto-plate-designer' ),
				'kind' => 'suv',
			),
			'plates-custom' => array(
				'name' => __( 'Street plates', 'auto-plate-designer' ),
				'kind' => 'custom',
			),
			'plates-color'  => array(
				'name' => __( 'Color plates', 'auto-plate-designer' ),
				'kind' => 'color',
			),
			'holders'       => array(
				'name' => __( 'Holders', 'auto-plate-designer' ),
				'kind' => 'holder',
			),
		);

		/**
		 * Filter WooCommerce product categories created by Auto Plate Designer.
		 *
		 * Each item is slug => array( name, kind ) where kind is a format type.
		 *
		 * @param array<string, array<string, string>> $defs Definitions.
		 */
		$filtered = apply_filters( 'apd_catalog_categories', $defs );

		return is_array( $filtered ) ? $filtered : $defs;
	}

	/**
	 * Create missing product_cat terms.
	 */
	public function seed_terms() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}

		foreach ( self::definitions() as $slug => $def ) {
			if ( ! is_string( $slug ) || '' === $slug || ! is_array( $def ) ) {
				continue;
			}

			$name     = isset( $def['name'] ) ? sanitize_text_field( (string) $def['name'] ) : $slug;
			$existing = get_term_by( 'slug', $slug, 'product_cat' );

			if ( $existing instanceof WP_Term ) {
				if ( $existing->name !== $name ) {
					wp_update_term(
						$existing->term_id,
						'product_cat',
						array(
							'name' => $name,
						)
					);
				}

				continue;
			}

			wp_insert_term(
				$name,
				'product_cat',
				array(
					'slug' => sanitize_title( $slug ),
				)
			);
		}
	}

	/**
	 * Format types that are filed in this catalog category.
	 *
	 * @param string $kind Catalog kind, such as eu or moto.
	 * @return array<int, string>
	 */
	public static function format_types_for_kind( $kind ) {
		$kind  = (string) $kind;
		$types = class_exists( 'APD_Security' ) ? APD_Security::allowed_format_types() : array( $kind );
		$match = array();

		foreach ( $types as $type ) {
			$resolved = class_exists( 'APD_Formats' ) ? APD_Formats::catalog_kind( $type ) : (string) $type;

			if ( $resolved === $kind ) {
				$match[] = (string) $type;
			}
		}

		return $match;
	}

	/**
	 * Published category slugs from settings.
	 *
	 * @return array<int, string>
	 */
	public static function published_slugs() {
		$settings  = APD_Plugin::get_settings();
		$all       = array_keys( self::definitions() );
		$published = isset( $settings['catalog']['published'] ) && is_array( $settings['catalog']['published'] )
			? $settings['catalog']['published']
			: $all;

		$clean = array();

		foreach ( $published as $slug ) {
			$slug = sanitize_title( (string) $slug );

			if ( in_array( $slug, $all, true ) ) {
				$clean[] = $slug;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Hide unpublished APD categories on the storefront.
	 *
	 * @param array<string, mixed> $args       Term query args.
	 * @param array<int, string>   $taxonomies Taxonomies.
	 * @return array<string, mixed>
	 */
	public function exclude_unpublished( $args, $taxonomies ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $args;
		}

		if ( ! in_array( 'product_cat', (array) $taxonomies, true ) ) {
			return $args;
		}

		static $guard = false;

		if ( $guard ) {
			return $args;
		}

		$guard       = true;
		$exclude_ids = self::unpublished_term_ids();
		$guard       = false;

		if ( empty( $exclude_ids ) ) {
			return $args;
		}

		$existing = array();

		if ( isset( $args['exclude'] ) ) {
			$existing = is_array( $args['exclude'] ) ? $args['exclude'] : explode( ',', (string) $args['exclude'] );
		}

		$args['exclude'] = array_values( array_unique( array_map( 'intval', array_merge( $existing, $exclude_ids ) ) ) );

		return $args;
	}

	/**
	 * Term IDs for unpublished APD categories.
	 *
	 * @return array<int, int>
	 */
	public static function unpublished_term_ids() {
		static $running = false;

		if ( $running || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$running   = true;
		$published = self::published_slugs();
		$ids       = array();

		foreach ( self::definitions() as $slug => $def ) {
			unset( $def );

			if ( in_array( $slug, $published, true ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		$running = false;

		return $ids;
	}

	/**
	 * Assign the matching catalog category when the product has none.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $kind       Format type.
	 */
	public static function maybe_assign_product_term( $product_id, $kind ) {
		$product_id = absint( $product_id );

		if ( class_exists( 'APD_Formats' ) ) {
			$kind = APD_Formats::catalog_kind( $kind );
		}

		if ( $product_id < 1 || ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}

		$existing = wp_get_object_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			return;
		}

		$slug = '';

		foreach ( self::definitions() as $candidate => $def ) {
			if ( isset( $def['kind'] ) && $def['kind'] === $kind ) {
				$slug = $candidate;
				break;
			}
		}

		if ( '' === $slug || ! in_array( $slug, self::published_slugs(), true ) ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		wp_set_object_terms( $product_id, array( (int) $term->term_id ), 'product_cat', true );
	}

	/**
	 * Sanitize the published-slug list from admin POST.
	 *
	 * @param mixed $raw Raw slugs.
	 * @return array<int, string>
	 */
	public static function sanitize_published( $raw ) {
		$allowed = array_keys( self::definitions() );
		$clean   = array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		foreach ( $raw as $slug ) {
			$slug = sanitize_title( (string) $slug );

			if ( in_array( $slug, $allowed, true ) ) {
				$clean[] = $slug;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}
