<?php
/**
 * Plugin Name:       Auto Plate Designer
 * Plugin URI:        https://example.com/auto-plate-designer
 * Description:       WooCommerce product configurator for custom vehicle plates with real-time canvas preview.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Gergana
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       auto-plate-designer
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.0
 *
 * @package Auto_Plate_Designer
 */

defined( 'ABSPATH' ) || exit;

define( 'APD_VERSION', '1.0.0' );
define( 'APD_MIN_PHP', '8.0' );
define( 'APD_MIN_WP', '6.0' );
define( 'APD_MIN_WC', '8.0' );
define( 'APD_PLUGIN_FILE', __FILE__ );
define( 'APD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'APD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APD_OPTION_KEY', 'apd_settings' );
define( 'APD_CACHE_KEY', 'apd_settings_cache_v13' );

require_once APD_PLUGIN_DIR . 'includes/class-apd-security.php';
require_once APD_PLUGIN_DIR . 'includes/class-apd-plugin.php';

register_activation_hook( APD_PLUGIN_FILE, array( 'APD_Plugin', 'activate' ) );
register_deactivation_hook( APD_PLUGIN_FILE, array( 'APD_Plugin', 'deactivate' ) );

add_action( 'before_woocommerce_init', array( 'APD_Plugin', 'declare_hpos_compatibility' ) );
add_action( 'plugins_loaded', array( 'APD_Plugin', 'instance' ) );
