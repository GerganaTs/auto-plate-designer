<?php
/**
 * Offer plugin updates from GitHub tags in wp-admin.
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks the public GitHub repository for a newer tag.
 */
final class APD_Updater {

	const REPO = 'GerganaTs/auto-plate-designer';

	const CACHE_KEY = 'apd_github_update';

	/**
	 * Register the WordPress update hooks.
	 */
	public static function register() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'github_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_force_check' ), 1 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Turn a Git tag into a version WordPress can compare.
	 *
	 * @param string $tag Tag name, with or without a leading v.
	 * @return string Empty when the tag is not a version.
	 */
	public static function version_from_tag( $tag ) {
		$tag = preg_replace( '/^[vV]/', '', trim( (string) $tag ) );

		if ( ! is_string( $tag ) || ! preg_match( '/^\d+\.\d+\.\d+/', $tag ) ) {
			return '';
		}

		return $tag;
	}

	/**
	 * Pick the highest semantic version from a GitHub tags payload.
	 *
	 * @param array<int, mixed> $tags Tag objects or names.
	 * @return array{tag: string, version: string}|null
	 */
	public static function newest_from_tags( $tags ) {
		$best = null;

		foreach ( $tags as $tag ) {
			$name = '';

			if ( is_array( $tag ) && isset( $tag['name'] ) ) {
				$name = (string) $tag['name'];
			} elseif ( is_string( $tag ) ) {
				$name = $tag;
			}

			$version = self::version_from_tag( $name );

			if ( '' === $version ) {
				continue;
			}

			if ( null === $best || version_compare( $version, $best['version'], '>' ) ) {
				$best = array(
					'tag'     => $name,
					'version' => $version,
				);
			}
		}

		return $best;
	}

	/**
	 * Zip WordPress downloads for a tag. The archive has one root folder, which the upgrader unpacks into this plugin.
	 *
	 * @param string $tag Tag name as stored on GitHub.
	 * @return string
	 */
	public static function package_url( $tag ) {
		return 'https://github.com/' . self::REPO . '/archive/refs/tags/' . rawurlencode( (string) $tag ) . '.zip';
	}

	/**
	 * Update payload for this plugin when GitHub has a newer tag.
	 *
	 * @param array|false      $update      Existing update data.
	 * @param array            $plugin_data Plugin headers.
	 * @param string           $plugin_file Plugin basename.
	 * @param string[]         $locales     Installed locales.
	 * @return array|false
	 */
	public static function github_update( $update, $plugin_data, $plugin_file, $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( APD_PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}

		$remote = self::remote_release();

		if ( ! is_array( $remote ) || '' === $remote['version'] ) {
			return $update;
		}

		return array(
			'slug'         => dirname( APD_PLUGIN_BASENAME ),
			'version'      => $remote['version'],
			'url'          => $remote['url'],
			'package'      => $remote['package'],
			'requires'     => APD_MIN_WP,
			'requires_php' => APD_MIN_PHP,
			'tested'       => '6.8',
		);
	}

	/**
	 * Details popup on the Plugins screen.
	 *
	 * @param false|object|array $result Current API result.
	 * @param string             $action Requested action.
	 * @param object             $args   Query arguments.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		$slug = dirname( APD_PLUGIN_BASENAME );

		if ( 'plugin_information' !== $action || empty( $args->slug ) || $slug !== $args->slug ) {
			return $result;
		}

		$remote = self::remote_release();

		if ( ! is_array( $remote ) || '' === $remote['version'] ) {
			return $result;
		}

		$notes = '' !== $remote['notes']
			? wpautop( esc_html( $remote['notes'] ) )
			: '<p>' . esc_html__( 'Details are on the GitHub tag.', 'auto-plate-designer' ) . '</p>';

		return (object) array(
			'name'           => 'Auto Plate Designer',
			'slug'           => $slug,
			'version'        => $remote['version'],
			'author'         => '<a href="https://github.com/GerganaTs">Gergana</a>',
			'homepage'       => 'https://github.com/' . self::REPO,
			'download_link'  => $remote['package'],
			'requires'       => APD_MIN_WP,
			'requires_php'   => APD_MIN_PHP,
			'tested'         => '6.8',
			'last_updated'   => $remote['published'],
			'sections'       => array(
				'description' => esc_html__( 'WooCommerce product configurator for custom vehicle plates.', 'auto-plate-designer' ),
				'changelog'   => $notes,
			),
		);
	}

	/**
	 * The Updates screen “Check again” link does not by itself rebuild plugin updates.
	 * Drop the GitHub cache and the recent-check timestamp so the next lookup runs.
	 */
	public static function maybe_force_check() {
		if ( ! isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		delete_transient( self::CACHE_KEY );

		$current = get_site_transient( 'update_plugins' );

		if ( is_object( $current ) ) {
			$current->last_checked = 0;
			set_site_transient( 'update_plugins', $current );
		}
	}

	/**
	 * Drop the cached tag after this plugin is updated.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade context.
	 */
	public static function clear_cache( $upgrader, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] ) ? $options['plugins'] : array();

		if ( in_array( APD_PLUGIN_BASENAME, $plugins, true ) ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Latest GitHub tag, cached so the admin does not call GitHub on every screen.
	 *
	 * @return array{tag: string, version: string, package: string, url: string, notes: string, published: string}|null
	 */
	public static function remote_release() {
		if ( self::cache_bypassed() ) {
			delete_transient( self::CACHE_KEY );
		}

		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return '' === ( isset( $cached['version'] ) ? (string) $cached['version'] : '' ) ? null : $cached;
		}

		$tags = self::request_json( 'https://api.github.com/repos/' . self::REPO . '/tags?per_page=100' );

		if ( ! is_array( $tags ) ) {
			set_transient( self::CACHE_KEY, array( 'version' => '' ), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$best = self::newest_from_tags( $tags );

		if ( null === $best ) {
			set_transient( self::CACHE_KEY, array( 'version' => '' ), HOUR_IN_SECONDS );
			return null;
		}

		$release   = self::request_json( 'https://api.github.com/repos/' . self::REPO . '/releases/tags/' . rawurlencode( $best['tag'] ) );
		$notes     = '';
		$published = '';
		$url       = 'https://github.com/' . self::REPO . '/releases/tag/' . rawurlencode( $best['tag'] );

		if ( is_array( $release ) && ! empty( $release['tag_name'] ) ) {
			$notes     = isset( $release['body'] ) ? (string) $release['body'] : '';
			$published = isset( $release['published_at'] ) ? (string) $release['published_at'] : '';

			if ( ! empty( $release['html_url'] ) ) {
				$url = (string) $release['html_url'];
			}
		}

		$payload = array(
			'tag'       => $best['tag'],
			'version'   => $best['version'],
			'package'   => self::package_url( $best['tag'] ),
			'url'       => $url,
			'notes'     => $notes,
			'published' => $published,
		);

		set_transient( self::CACHE_KEY, $payload, HOUR_IN_SECONDS );

		return $payload;
	}

	/**
	 * Whether the Updates screen asked for a fresh check.
	 *
	 * @return bool
	 */
	private static function cache_bypassed() {
		return is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * GET a GitHub JSON endpoint.
	 *
	 * @param string $url Absolute API URL.
	 * @return array<mixed>|null
	 */
	private static function request_json( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Auto-Plate-Designer/' . APD_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}
}
