<?php
/**
 * Plugin Name: WPRSS Hub Remote
 * Description: Companion plugin for WPRSS Hub — exposes REST endpoints for centralized management of WP RSS Aggregator.
 * Version:     1.0.0
 * Author:      WPRSS Hub
 * Text Domain: wprss-hub-remote
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'wprss_hub_remote_register_routes' );

function wprss_hub_remote_register_routes() {
    $namespace = 'wprss-hub-remote/v1';

    register_rest_route( $namespace, '/health', [
        'methods'             => 'GET',
        'callback'            => 'wprss_hub_remote_health',
        'permission_callback' => 'wprss_hub_remote_check_permission',
    ] );

    register_rest_route( $namespace, '/options', [
        'methods'             => 'GET',
        'callback'            => 'wprss_hub_remote_get_options',
        'permission_callback' => 'wprss_hub_remote_check_permission',
        'args'                => [
            'keys' => [
                'required'          => true,
                'type'              => 'array',
                'items'             => [ 'type' => 'string' ],
                'sanitize_callback' => function ( $keys ) {
                    return array_map( 'sanitize_text_field', $keys );
                },
            ],
        ],
    ] );

    register_rest_route( $namespace, '/options', [
        'methods'             => 'POST',
        'callback'            => 'wprss_hub_remote_set_options',
        'permission_callback' => 'wprss_hub_remote_check_permission',
    ] );

    register_rest_route( $namespace, '/force-fetch', [
        'methods'             => 'POST',
        'callback'            => 'wprss_hub_remote_force_fetch',
        'permission_callback' => 'wprss_hub_remote_check_permission',
        'args'                => [
            'feed_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ] );
}

/**
 * Permission callback — require manage_options.
 */
function wprss_hub_remote_check_permission( WP_REST_Request $request ) {
    return current_user_can( 'manage_options' );
}

/**
 * GET /health — aggregate feed health data.
 */
function wprss_hub_remote_health( WP_REST_Request $request ) {
    $feeds = get_posts( [
        'post_type'      => 'wprss_feed',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    $item_count   = 0;
    $error_count  = 0;
    $last_fetch   = null;
    $feed_statuses = [];

    foreach ( $feeds as $feed ) {
        $feed_url       = get_post_meta( $feed->ID, 'wprss_url', true );
        $feed_error     = get_post_meta( $feed->ID, 'wprss_error_last_request', true );
        $feed_last_time = get_post_meta( $feed->ID, 'wprss_last_update', true );

        // Count imported items for this feed.
        $items = get_posts( [
            'post_type'      => 'wprss_feed_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => 'wprss_feed_id',
                    'value' => $feed->ID,
                ],
            ],
            'fields'         => 'ids',
        ] );

        $feed_item_count = count( $items );
        $item_count     += $feed_item_count;

        $has_error = ! empty( $feed_error );
        if ( $has_error ) {
            $error_count++;
        }

        if ( $feed_last_time && ( ! $last_fetch || $feed_last_time > $last_fetch ) ) {
            $last_fetch = $feed_last_time;
        }

        $feed_statuses[] = [
            'id'         => $feed->ID,
            'title'      => $feed->post_title,
            'url'        => $feed_url,
            'last_fetch' => $feed_last_time ? (int) $feed_last_time : null,
            'item_count' => $feed_item_count,
            'has_error'  => $has_error,
            'error'      => $has_error ? $feed_error : null,
        ];
    }

    return rest_ensure_response( [
        'last_fetch'    => $last_fetch ? (int) $last_fetch : null,
        'item_count'    => $item_count,
        'error_count'   => $error_count,
        'feed_statuses' => $feed_statuses,
    ] );
}

/**
 * GET /options — read specified wprss_general keys.
 */
function wprss_hub_remote_get_options( WP_REST_Request $request ) {
    $keys    = $request->get_param( 'keys' );
    $general = get_option( 'wprss_settings_general', [] );
    $result  = [];

    $allowed_keys = [
        'cron_interval',
        'limit_feed_items_enabled',
        'limit_feed_items_number',
        'feed_request_useragent',
        'delete_on_feed_delete',
        'source_link',
        'open_dd',
        'follow_feed_items_url',
    ];

    foreach ( $keys as $key ) {
        if ( in_array( $key, $allowed_keys, true ) ) {
            $result[ $key ] = isset( $general[ $key ] ) ? $general[ $key ] : null;
        }
    }

    return rest_ensure_response( $result );
}

/**
 * POST /options — write wprss_general keys.
 */
function wprss_hub_remote_set_options( WP_REST_Request $request ) {
    $body = $request->get_json_params();
    if ( empty( $body ) || ! is_array( $body ) ) {
        return new WP_Error( 'invalid_body', __( 'Request body must be a JSON object of key-value pairs.', 'wprss-hub-remote' ), [ 'status' => 400 ] );
    }

    $allowed_keys = [
        'cron_interval',
        'limit_feed_items_enabled',
        'limit_feed_items_number',
        'feed_request_useragent',
        'delete_on_feed_delete',
        'source_link',
        'open_dd',
        'follow_feed_items_url',
    ];

    $general = get_option( 'wprss_settings_general', [] );
    $updated = [];

    foreach ( $body as $key => $value ) {
        $key = sanitize_text_field( $key );
        if ( ! in_array( $key, $allowed_keys, true ) ) {
            continue;
        }
        $general[ $key ] = sanitize_text_field( $value );
        $updated[]       = $key;
    }

    update_option( 'wprss_settings_general', $general );

    return rest_ensure_response( [ 'updated' => $updated ] );
}

/**
 * POST /force-fetch — trigger immediate fetch for a feed.
 */
function wprss_hub_remote_force_fetch( WP_REST_Request $request ) {
    $feed_id = $request->get_param( 'feed_id' );

    $feed = get_post( $feed_id );
    if ( ! $feed || $feed->post_type !== 'wprss_feed' ) {
        return new WP_Error( 'feed_not_found', __( 'Feed not found.', 'wprss-hub-remote' ), [ 'status' => 404 ] );
    }

    // Schedule an immediate fetch via WPRSS's own mechanism.
    if ( function_exists( 'wprss_fetch_insert_single_feed_items' ) ) {
        wp_schedule_single_event( time(), 'wprss_fetch_single_feed_hook', [ $feed_id ] );
        spawn_cron();
        return rest_ensure_response( [ 'success' => true, 'message' => __( 'Feed fetch scheduled.', 'wprss-hub-remote' ) ] );
    }

    return new WP_Error( 'wprss_not_active', __( 'WP RSS Aggregator is not active on this site.', 'wprss-hub-remote' ), [ 'status' => 500 ] );
}
