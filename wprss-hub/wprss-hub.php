<?php
/**
 * Plugin Name: WPRSS Hub
 * Description: Central dashboard to manage WP RSS Aggregator across multiple remote WordPress sites.
 * Version:     1.0.0
 * Author:      WPRSS Hub
 * Text Domain: wprss-hub
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPRSS_HUB_VERSION', '1.0.0' );
define( 'WPRSS_HUB_DB_VERSION', '1.0.0' );
define( 'WPRSS_HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPRSS_HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPRSS_HUB_ENCRYPTION_KEY_OPTION', 'wprss_hub_encryption_key' );

/**
 * Autoload plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'WPRSS_Hub_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $filename = 'class-wprss-hub-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';

    $dirs = [
        WPRSS_HUB_PLUGIN_DIR . 'includes/',
        WPRSS_HUB_PLUGIN_DIR . 'admin/',
        WPRSS_HUB_PLUGIN_DIR . 'api/',
        WPRSS_HUB_PLUGIN_DIR . 'db/',
    ];

    foreach ( $dirs as $dir ) {
        $file = $dir . $filename;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );

/**
 * Activation hook.
 */
function wprss_hub_activate() {
    if ( ! extension_loaded( 'sodium' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'WPRSS Hub requires the PHP Sodium extension. Please enable it and try again.', 'wprss-hub' ),
            esc_html__( 'Plugin Activation Error', 'wprss-hub' ),
            [ 'back_link' => true ]
        );
    }

    WPRSS_Hub_Activator::activate();
}
register_activation_hook( __FILE__, 'wprss_hub_activate' );

/**
 * Deactivation hook.
 */
function wprss_hub_deactivate() {
    WPRSS_Hub_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wprss_hub_deactivate' );

/**
 * Admin notice if sodium is missing at runtime.
 */
function wprss_hub_sodium_notice() {
    if ( extension_loaded( 'sodium' ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'WPRSS Hub requires the PHP Sodium extension. The plugin will not function until it is enabled.', 'wprss-hub' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'wprss_hub_sodium_notice' );

/**
 * Initialize the plugin after all plugins are loaded.
 */
function wprss_hub_init() {
    if ( ! extension_loaded( 'sodium' ) ) {
        return;
    }

    // Check if DB needs upgrade.
    WPRSS_Hub_DB::maybe_upgrade();

    // Initialize job queue hooks.
    WPRSS_Hub_Job_Queue::init();

    if ( is_admin() ) {
        $admin = new WPRSS_Hub_Admin();
        $admin->init();
    }
}
add_action( 'plugins_loaded', 'wprss_hub_init' );

/**
 * Register hub REST API routes.
 */
function wprss_hub_register_rest_routes() {
    WPRSS_Hub_Rest_Api::register_routes();
}
add_action( 'rest_api_init', 'wprss_hub_register_rest_routes' );
