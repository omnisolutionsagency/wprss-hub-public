<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Admin {

    /**
     * Initialize admin hooks.
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus() {
        add_menu_page(
            __( 'WPRSS Hub', 'wprss-hub' ),
            __( 'WPRSS Hub', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-sites',
            [ $this, 'render_sites_page' ],
            'dashicons-rss',
            30
        );

        add_submenu_page(
            'wprss-hub-sites',
            __( 'Sites', 'wprss-hub' ),
            __( 'Sites', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-sites',
            [ $this, 'render_sites_page' ]
        );

        add_submenu_page(
            'wprss-hub-sites',
            __( 'Feeds', 'wprss-hub' ),
            __( 'Feeds', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-feeds',
            [ $this, 'render_feeds_page' ]
        );

        add_submenu_page(
            'wprss-hub-sites',
            __( 'Settings', 'wprss-hub' ),
            __( 'Settings', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'wprss-hub-sites',
            __( 'Queue', 'wprss-hub' ),
            __( 'Queue', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-queue',
            [ $this, 'render_queue_page' ]
        );

        add_submenu_page(
            'wprss-hub-sites',
            __( 'Logs', 'wprss-hub' ),
            __( 'Logs', 'wprss-hub' ),
            'manage_options',
            'wprss-hub-logs',
            [ $this, 'render_logs_page' ]
        );
    }

    /**
     * Enqueue admin CSS and JS on hub pages only.
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( strpos( $hook_suffix, 'wprss-hub' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wprss-hub-admin',
            WPRSS_HUB_PLUGIN_URL . 'admin/css/wprss-hub-admin.css',
            [],
            WPRSS_HUB_VERSION
        );

        wp_enqueue_script(
            'wprss-hub-admin',
            WPRSS_HUB_PLUGIN_URL . 'admin/js/wprss-hub-admin.js',
            [],
            WPRSS_HUB_VERSION,
            true
        );

        wp_localize_script( 'wprss-hub-admin', 'wprssHub', [
            'restUrl' => esc_url_raw( rest_url( 'wprss-hub/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'confirm_delete' => __( 'Are you sure you want to delete this?', 'wprss-hub' ),
                'saving'         => __( 'Saving...', 'wprss-hub' ),
                'saved'          => __( 'Saved!', 'wprss-hub' ),
                'error'          => __( 'An error occurred.', 'wprss-hub' ),
                'testing'        => __( 'Testing connection...', 'wprss-hub' ),
                'loading'        => __( 'Loading...', 'wprss-hub' ),
            ],
        ] );
    }

    /**
     * Render page callbacks — include view files.
     */
    public function render_sites_page() {
        include WPRSS_HUB_PLUGIN_DIR . 'admin/views/page-sites.php';
    }

    public function render_feeds_page() {
        include WPRSS_HUB_PLUGIN_DIR . 'admin/views/page-feeds.php';
    }

    public function render_settings_page() {
        include WPRSS_HUB_PLUGIN_DIR . 'admin/views/page-settings.php';
    }

    public function render_queue_page() {
        include WPRSS_HUB_PLUGIN_DIR . 'admin/views/page-queue.php';
    }

    public function render_logs_page() {
        include WPRSS_HUB_PLUGIN_DIR . 'admin/views/page-logs.php';
    }
}
