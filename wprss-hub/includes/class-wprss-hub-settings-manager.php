<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Settings_Manager {

    /**
     * The WPRSS settings keys we manage.
     */
    const SETTING_KEYS = [
        'cron_interval',
        'limit_feed_items_enabled',
        'limit_feed_items_number',
        'feed_request_useragent',
        'delete_on_feed_delete',
        'source_link',
        'open_dd',
        'follow_feed_items_url',
    ];

    /**
     * Get all managed settings from a single remote site.
     *
     * @param int $site_id Hub site ID.
     * @return array|WP_Error Associative array of key => value.
     */
    public static function get_site_settings( $site_id ) {
        return WPRSS_Hub_Rest_Client::get_options( $site_id, self::SETTING_KEYS );
    }

    /**
     * Set one or more settings on a single remote site (Lane A — real-time REST).
     *
     * @param int   $site_id Hub site ID.
     * @param array $options Key-value pairs to set.
     * @return array|WP_Error Response from remote.
     */
    public static function set_site_settings( $site_id, $options ) {
        // Filter to only allowed keys.
        $filtered = [];
        foreach ( $options as $key => $value ) {
            if ( in_array( $key, self::SETTING_KEYS, true ) ) {
                $filtered[ $key ] = $value;
            }
        }

        if ( empty( $filtered ) ) {
            return new WP_Error( 'no_valid_keys', __( 'No valid setting keys provided.', 'wprss-hub' ) );
        }

        $result = WPRSS_Hub_Rest_Client::set_options( $site_id, $filtered );

        if ( ! is_wp_error( $result ) ) {
            foreach ( $filtered as $key => $value ) {
                WPRSS_Hub_Logger::log( null, $site_id, 'set_setting', 'pass', $key . ' = ' . $value );
            }
        } else {
            WPRSS_Hub_Logger::log( null, $site_id, 'set_setting', 'fail', $result->get_error_message() );
        }

        return $result;
    }

    /**
     * Push a setting to all active sites (Lane B — queued via SSH).
     *
     * @param string $option_key   The wprss_general option key.
     * @param string $option_value The value to set.
     * @return int|WP_Error Job ID or error.
     */
    public static function push_global( $option_key, $option_value ) {
        if ( ! in_array( $option_key, self::SETTING_KEYS, true ) ) {
            return new WP_Error( 'invalid_key', __( 'Invalid setting key.', 'wprss-hub' ) );
        }

        $sites    = WPRSS_Hub_Site_Registry::get_all();
        $site_ids = [];
        foreach ( $sites as $site ) {
            if ( $site->status === 'active' ) {
                $site_ids[] = (int) $site->id;
            }
        }

        if ( empty( $site_ids ) ) {
            return new WP_Error( 'no_sites', __( 'No active sites to push to.', 'wprss-hub' ) );
        }

        return WPRSS_Hub_Job_Queue::enqueue( 'push_setting', [
            'option_key'   => sanitize_text_field( $option_key ),
            'option_value' => sanitize_text_field( $option_value ),
        ], $site_ids );
    }

    /**
     * Get settings from all active sites in parallel.
     * Returns [ site_id => [ key => value, ... ] | WP_Error, ... ]
     *
     * @return array
     */
    public static function get_all_sites_settings() {
        $sites  = WPRSS_Hub_Site_Registry::get_all();
        $result = [];

        foreach ( $sites as $site ) {
            if ( $site->status !== 'active' ) {
                $result[ $site->id ] = new WP_Error( 'disabled', __( 'Site is disabled.', 'wprss-hub' ) );
                continue;
            }
            $result[ $site->id ] = self::get_site_settings( (int) $site->id );
        }

        return $result;
    }
}
