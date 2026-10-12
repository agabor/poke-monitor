<?php
/**
 * Plugin Name: Poke Monitor
 * Plugin URI: https://github.com/agabor/poke-monitor
 * Description: Logs suspicious requests: Cloudflare bypasses, probing for secret files, direct PHP access, XML-RPC calls and scanner user agents.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Gabor Angyal
 * Author URI: https://github.com/agabor
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: poke-monitor
 *
 * @package PokeMonitor
 */

defined( 'ABSPATH' ) || exit;

define( 'poke_monitor_VERSION', '1.0.0' );

define( 'poke_monitor_PLUGIN_FILE', __FILE__ );

define( 'poke_monitor_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'poke_monitor_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function poke_monitor_autoload( $class_name ) {
	$namespace_prefix = 'PokeMonitor\\';

	if ( 0 !== strpos( $class_name, $namespace_prefix ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $namespace_prefix ) );
	$path_parts      = explode( '\\', $relative_class );
	$short_class     = array_pop( $path_parts );
	$file_name       = 'class-' . str_replace( '_', '-', strtolower( $short_class ) ) . '.php';

	if ( ! empty( $path_parts ) && 'Rules' === $path_parts[0] ) {
		$target_directory = poke_monitor_PLUGIN_DIR . 'includes/rules/';
	} elseif ( ! empty( $path_parts ) && 'Admin' === $path_parts[0] ) {
		$target_directory = poke_monitor_PLUGIN_DIR . 'admin/';
	} else {
		$target_directory = poke_monitor_PLUGIN_DIR . 'includes/';
	}

	$file_path = $target_directory . $file_name;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

spl_autoload_register( 'poke_monitor_autoload' );

register_activation_hook( poke_monitor_PLUGIN_FILE, array( 'PokeMonitor\\Activator', 'activate' ) );
register_deactivation_hook( poke_monitor_PLUGIN_FILE, array( 'PokeMonitor\\Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'PokeMonitor\\Schema', 'create_or_upgrade' ), 8 );
add_action( 'plugins_loaded', array( PokeMonitor\Plugin::instance(), 'boot' ) );
