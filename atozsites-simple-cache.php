<?php
/**
 * Plugin Name: atozsites Simple Cache
 * Plugin URI: https://github.com/atozsites/simple-cache-plugin
 * Description: A simple caching plugin that just works.
 * Author: atozsites
 * Version: 1.0
 * Text Domain: atozsites-atozsites-simple-cache
 * Domain Path: /languages
 * Author URI: http://atozsites.com
 *
 * @package  atozsites-simple-cache
 */

defined( 'ABSPATH' ) || exit;

define( 'atozsites_VERSION', '1.0' );
define( 'atozsites_PATH', dirname( __FILE__ ) );

$active_plugins = get_site_option( 'active_sitewide_plugins' );

if ( is_multisite() && isset( $active_plugins[ plugin_basename( __FILE__ ) ] ) ) {
	define( 'atozsites_IS_NETWORK', true );
} else {
	define( 'atozsites_IS_NETWORK', false );
}

require_once atozsites_PATH . '/inc/pre-wp-functions.php';
require_once atozsites_PATH . '/inc/functions.php';
require_once atozsites_PATH . '/inc/class-sc-notices.php';
require_once atozsites_PATH . '/inc/class-sc-settings.php';
require_once atozsites_PATH . '/inc/class-sc-config.php';
require_once atozsites_PATH . '/inc/class-sc-advanced-cache.php';
require_once atozsites_PATH . '/inc/class-sc-object-cache.php';
require_once atozsites_PATH . '/inc/class-sc-cron.php';

atozsites_Settings::factory();
atozsites_Advanced_Cache::factory();
atozsites_Object_Cache::factory();
atozsites_Cron::factory();
atozsites_Notices::factory();

/**
 * Load text domain
 *
 * @since 1.0
 */
function atozsites_load_textdomain() {

	load_plugin_textdomain( 'atozsites-simple-cache', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'atozsites_load_textdomain' );


/**
 * Add settings link to plugin actions
 *
 * @param  array  $plugin_actions Each action is HTML.
 * @param  string $plugin_file Path to plugin file.
 * @since  1.0
 * @return array
 */
function atozsites_filter_plugin_action_links( $plugin_actions, $plugin_file ) {

	$new_actions = array();

	if ( basename( dirname( __FILE__ ) ) . '/atozsites-simple-cache.php' === $plugin_file ) {
		/* translators: Param 1 is link to settings page. */
		$new_actions['atozsites_settings'] = '<a href="' . esc_url( admin_url( 'options-general.php?page=atozsites-simple-cache' ) ) . '">' . esc_html__( 'Settings', 'atozsites-simple-cache' ) . '</a>';
	}

	return array_merge( $new_actions, $plugin_actions );
}
add_filter( 'plugin_action_links', 'atozsites_filter_plugin_action_links', 10, 2 );

/**
 * Clean up necessary files
 *
 * @param  bool $network Whether the plugin is network wide
 * @since 1.0
 */
function atozsites_deactivate( $network ) {
	if ( ! apply_filters( 'atozsites_disable_auto_edits', false ) ) {
		atozsites_Advanced_Cache::factory()->clean_up();
		atozsites_Advanced_Cache::factory()->toggle_caching( false );
		atozsites_Object_Cache::factory()->clean_up();
	}

	atozsites_Config::factory()->clean_up();

	atozsites_cache_flush( $network );
}
add_action( 'deactivate_' . plugin_basename( __FILE__ ), 'atozsites_deactivate' );

/**
 * Create config file
 *
 * @param  bool $network Whether the plugin is network wide
 * @since 1.0
 */
function atozsites_activate( $network ) {
	if ( $network ) {
		atozsites_Config::factory()->write( array(), true );
	} else {
		atozsites_Config::factory()->write( array() );
	}
}
add_action( 'activate_' . plugin_basename( __FILE__ ), 'atozsites_activate' );


