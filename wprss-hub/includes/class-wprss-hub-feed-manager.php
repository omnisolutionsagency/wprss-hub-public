<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Feed_Manager {

    /**
     * Get all hub feeds.
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_feeds';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    /**
     * Get a single feed by ID.
     */
    public static function get( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_feeds';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Create a new hub feed and dispatch to assigned sites.
     *
     * @param array $data { feed_url, feed_title, assigned_sites: int[], notes }
     * @return int|WP_Error Feed ID or error.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_feeds';

        $feed_url       = esc_url_raw( $data['feed_url'] );
        $feed_title     = isset( $data['feed_title'] ) ? sanitize_text_field( $data['feed_title'] ) : '';
        $assigned_sites = isset( $data['assigned_sites'] ) ? array_map( 'absint', (array) $data['assigned_sites'] ) : [];
        $notes          = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';

        if ( empty( $feed_url ) ) {
            return new WP_Error( 'missing_url', __( 'Feed URL is required.', 'wprss-hub' ) );
        }

        $result = $wpdb->insert(
            $table,
            [
                'feed_url'       => $feed_url,
                'feed_title'     => $feed_title,
                'assigned_sites' => wp_json_encode( $assigned_sites ),
                'notes'          => $notes,
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return new WP_Error( 'db_insert_failed', __( 'Failed to save feed.', 'wprss-hub' ) );
        }

        $feed_id = $wpdb->insert_id;

        // Dispatch feed creation to assigned sites.
        if ( ! empty( $assigned_sites ) ) {
            self::push_feed_to_sites( $feed_url, $feed_title, $assigned_sites );
        }

        return $feed_id;
    }

    /**
     * Update a hub feed and sync site assignments.
     *
     * @param int   $id   Feed ID.
     * @param array $data Fields to update.
     * @return true|WP_Error
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_feeds';

        $existing = self::get( $id );
        if ( ! $existing ) {
            return new WP_Error( 'not_found', __( 'Feed not found.', 'wprss-hub' ) );
        }

        $fields  = [];
        $formats = [];

        if ( isset( $data['feed_url'] ) ) {
            $fields['feed_url'] = esc_url_raw( $data['feed_url'] );
            $formats[]          = '%s';
        }
        if ( isset( $data['feed_title'] ) ) {
            $fields['feed_title'] = sanitize_text_field( $data['feed_title'] );
            $formats[]            = '%s';
        }
        if ( isset( $data['notes'] ) ) {
            $fields['notes'] = sanitize_textarea_field( $data['notes'] );
            $formats[]       = '%s';
        }

        $old_sites = json_decode( $existing->assigned_sites, true ) ?: [];
        $new_sites = null;

        if ( isset( $data['assigned_sites'] ) ) {
            $new_sites              = array_map( 'absint', (array) $data['assigned_sites'] );
            $fields['assigned_sites'] = wp_json_encode( $new_sites );
            $formats[]              = '%s';
        }

        if ( ! empty( $fields ) ) {
            $wpdb->update( $table, $fields, [ 'id' => $id ], $formats, [ '%d' ] );
        }

        // Sync site assignments if changed.
        if ( $new_sites !== null ) {
            $feed_url   = isset( $fields['feed_url'] ) ? $fields['feed_url'] : $existing->feed_url;
            $feed_title = isset( $fields['feed_title'] ) ? $fields['feed_title'] : $existing->feed_title;

            $added   = array_diff( $new_sites, $old_sites );
            $removed = array_diff( $old_sites, $new_sites );

            if ( ! empty( $added ) ) {
                self::push_feed_to_sites( $feed_url, $feed_title, $added );
            }
            if ( ! empty( $removed ) ) {
                self::remove_feed_from_sites( $feed_url, $removed );
            }
        }

        return true;
    }

    /**
     * Delete a hub feed and remove from all assigned sites.
     *
     * @param int $id Feed ID.
     * @return true|WP_Error
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_feeds';

        $feed = self::get( $id );
        if ( ! $feed ) {
            return new WP_Error( 'not_found', __( 'Feed not found.', 'wprss-hub' ) );
        }

        $assigned = json_decode( $feed->assigned_sites, true ) ?: [];
        if ( ! empty( $assigned ) ) {
            self::remove_feed_from_sites( $feed->feed_url, $assigned );
        }

        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        return true;
    }

    /**
     * Push a feed to one or more sites.
     * Uses Lane A (REST) for single site, Lane B (queue) for multiple.
     */
    private static function push_feed_to_sites( $feed_url, $feed_title, $site_ids ) {
        if ( count( $site_ids ) === 1 ) {
            // Lane A: real-time REST.
            $result = WPRSS_Hub_Rest_Client::create_feed( $site_ids[0], $feed_url, $feed_title );
            $status = is_wp_error( $result ) ? 'fail' : 'pass';
            $msg    = is_wp_error( $result ) ? $result->get_error_message() : 'Feed created via REST.';
            WPRSS_Hub_Logger::log( null, $site_ids[0], 'push_feed', $status, $msg );
        } else {
            // Lane B: queued job.
            WPRSS_Hub_Job_Queue::enqueue( 'push_feed', [
                'feed_url'   => $feed_url,
                'feed_title' => $feed_title,
            ], $site_ids );
        }
    }

    /**
     * Remove a feed from one or more sites.
     * Uses Lane A (REST) for single site, Lane B (queue) for multiple.
     */
    private static function remove_feed_from_sites( $feed_url, $site_ids ) {
        if ( count( $site_ids ) === 1 ) {
            // Lane A: find and delete via REST.
            $feeds = WPRSS_Hub_Rest_Client::get_feeds( $site_ids[0] );
            if ( ! is_wp_error( $feeds ) ) {
                foreach ( $feeds as $feed ) {
                    $url = isset( $feed['meta']['wprss_url'] ) ? $feed['meta']['wprss_url'] : '';
                    if ( $url === $feed_url ) {
                        $result = WPRSS_Hub_Rest_Client::delete_feed( $site_ids[0], $feed['id'] );
                        $status = is_wp_error( $result ) ? 'fail' : 'pass';
                        $msg    = is_wp_error( $result ) ? $result->get_error_message() : 'Feed removed via REST.';
                        WPRSS_Hub_Logger::log( null, $site_ids[0], 'remove_feed', $status, $msg );
                        break;
                    }
                }
            }
        } else {
            // Lane B: queued job.
            WPRSS_Hub_Job_Queue::enqueue( 'remove_feed', [
                'feed_url' => $feed_url,
            ], $site_ids );
        }
    }

    /**
     * Mirror feeds from a source site to target sites.
     *
     * Fetches all feeds from source, diffs against each target, and queues
     * creation of missing feeds on targets.
     *
     * @param int   $source_site_id Source site ID.
     * @param array $target_site_ids Target site IDs.
     * @return int|WP_Error Job ID or error.
     */
    public static function mirror( $source_site_id, $target_site_ids ) {
        $source_feeds = WPRSS_Hub_Rest_Client::get_feeds( $source_site_id );
        if ( is_wp_error( $source_feeds ) ) {
            return $source_feeds;
        }

        if ( empty( $source_feeds ) ) {
            return new WP_Error( 'no_feeds', __( 'Source site has no feeds.', 'wprss-hub' ) );
        }

        // For each target site, find missing feeds and queue creation.
        $jobs = [];
        foreach ( $target_site_ids as $target_id ) {
            $target_feeds = WPRSS_Hub_Rest_Client::get_feeds( (int) $target_id );
            $target_urls  = [];

            if ( ! is_wp_error( $target_feeds ) ) {
                foreach ( $target_feeds as $tf ) {
                    $url = isset( $tf['meta']['wprss_url'] ) ? $tf['meta']['wprss_url'] : '';
                    if ( $url ) {
                        $target_urls[] = $url;
                    }
                }
            }

            foreach ( $source_feeds as $sf ) {
                $source_url   = isset( $sf['meta']['wprss_url'] ) ? $sf['meta']['wprss_url'] : '';
                $source_title = isset( $sf['title']['rendered'] ) ? $sf['title']['rendered'] : '';

                if ( $source_url && ! in_array( $source_url, $target_urls, true ) ) {
                    $jobs[] = [
                        'feed_url'   => $source_url,
                        'feed_title' => $source_title,
                        'target_id'  => (int) $target_id,
                    ];
                }
            }
        }

        if ( empty( $jobs ) ) {
            return new WP_Error( 'no_diff', __( 'All target sites already have all source feeds.', 'wprss-hub' ) );
        }

        // Group by target site and enqueue jobs.
        $by_site = [];
        foreach ( $jobs as $job ) {
            $by_site[ $job['target_id'] ][] = $job;
        }

        $job_ids = [];
        foreach ( $by_site as $target_id => $feeds_to_push ) {
            foreach ( $feeds_to_push as $feed_data ) {
                $job_ids[] = WPRSS_Hub_Job_Queue::enqueue( 'push_feed', [
                    'feed_url'   => $feed_data['feed_url'],
                    'feed_title' => $feed_data['feed_title'],
                ], [ $target_id ] );
            }
        }

        return $job_ids;
    }
}
