<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Rest_Client {

    /**
     * Send a request to a remote site's REST API.
     *
     * @param string $method   HTTP method.
     * @param int    $site_id  Hub site ID.
     * @param string $endpoint Relative endpoint path (e.g. '/wp/v2/wprss_feed').
     * @param array  $body     Request body for POST/PUT.
     * @return array|WP_Error  Decoded JSON response or WP_Error.
     */
    private static function request( $method, $site_id, $endpoint, $body = [] ) {
        $creds = WPRSS_Hub_Site_Registry::get_credentials( $site_id );
        if ( is_wp_error( $creds ) ) {
            return $creds;
        }

        $url = trailingslashit( $creds['rest_url'] ) . ltrim( $endpoint, '/' );

        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $creds['app_user'] . ':' . $creds['app_password'] ),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        if ( ! empty( $body ) && in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = isset( $data['message'] ) ? $data['message'] : $raw;
            return new WP_Error(
                'remote_api_error',
                sprintf(
                    /* translators: 1: HTTP status code, 2: error message */
                    __( 'Remote API returned %1$d: %2$s', 'wprss-hub' ),
                    $code,
                    $message
                ),
                [ 'status' => $code, 'response' => $data ]
            );
        }

        return $data;
    }

    /**
     * Get all feeds from a remote site.
     */
    public static function get_feeds( $site_id ) {
        return self::request( 'GET', $site_id, 'wp/v2/wprss_feed?per_page=100' );
    }

    /**
     * Create a feed on a remote site.
     */
    public static function create_feed( $site_id, $url, $title = '' ) {
        return self::request( 'POST', $site_id, 'wp/v2/wprss_feed', [
            'title'  => $title,
            'status' => 'publish',
            'meta'   => [
                'wprss_url' => $url,
            ],
        ] );
    }

    /**
     * Update a feed on a remote site.
     */
    public static function update_feed( $site_id, $remote_feed_id, $data ) {
        return self::request( 'PUT', $site_id, 'wp/v2/wprss_feed/' . (int) $remote_feed_id, $data );
    }

    /**
     * Delete a feed on a remote site.
     */
    public static function delete_feed( $site_id, $remote_feed_id ) {
        $result = self::request( 'DELETE', $site_id, 'wp/v2/wprss_feed/' . (int) $remote_feed_id . '?force=true' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    /**
     * Get WPRSS options from a remote site (via companion plugin).
     */
    public static function get_options( $site_id, $keys ) {
        if ( is_string( $keys ) ) {
            $keys = [ $keys ];
        }
        $query = http_build_query( [ 'keys' => $keys ] );
        return self::request( 'GET', $site_id, 'wprss-hub-remote/v1/options?' . $query );
    }

    /**
     * Set WPRSS options on a remote site (via companion plugin).
     */
    public static function set_options( $site_id, $options ) {
        return self::request( 'POST', $site_id, 'wprss-hub-remote/v1/options', $options );
    }

    /**
     * Get health data from a remote site (via companion plugin).
     */
    public static function get_health( $site_id ) {
        return self::request( 'GET', $site_id, 'wprss-hub-remote/v1/health' );
    }

    /**
     * Force fetch a feed on a remote site (via companion plugin).
     */
    public static function force_fetch( $site_id, $remote_feed_id ) {
        $result = self::request( 'POST', $site_id, 'wprss-hub-remote/v1/force-fetch', [
            'feed_id' => (int) $remote_feed_id,
        ] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    /**
     * Test REST connection to a remote site.
     * Returns true on success, WP_Error on failure.
     */
    public static function test_connection( $site_id ) {
        $result = self::get_health( $site_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }
}
