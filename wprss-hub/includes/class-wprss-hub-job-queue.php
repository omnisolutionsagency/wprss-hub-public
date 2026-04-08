<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Job_Queue {

    /**
     * Register the job processing hook.
     */
    public static function init() {
        add_action( 'wprss_hub_process_job', [ __CLASS__, 'process_job' ] );
    }

    /**
     * Enqueue a new job.
     *
     * @param string $job_type One of: push_setting, push_feed, remove_feed, force_fetch.
     * @param array  $payload  Job-specific data.
     * @param array  $site_ids Array of site IDs to target.
     * @return int Job ID.
     */
    public static function enqueue( $job_type, $payload, $site_ids ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_jobs';

        $wpdb->insert(
            $table,
            [
                'job_type' => sanitize_text_field( $job_type ),
                'payload'  => wp_json_encode( $payload ),
                'site_ids' => wp_json_encode( array_map( 'absint', $site_ids ) ),
                'status'   => 'queued',
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        $job_id = $wpdb->insert_id;

        // Schedule via Action Scheduler if available, otherwise fall back to WP-Cron.
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( time(), 'wprss_hub_process_job', [ 'job_id' => $job_id ] );
        } else {
            wp_schedule_single_event( time(), 'wprss_hub_process_job', [ $job_id ] );
        }

        return $job_id;
    }

    /**
     * Process a queued job.
     *
     * @param int $job_id Job ID.
     */
    public static function process_job( $job_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_jobs';

        $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ) );
        if ( ! $job || $job->status === 'running' ) {
            return;
        }

        // Mark as running.
        $wpdb->update(
            $table,
            [ 'status' => 'running', 'started_at' => current_time( 'mysql' ) ],
            [ 'id' => $job_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        $site_ids = json_decode( $job->site_ids, true );
        $payload  = json_decode( $job->payload, true );
        $results  = [];
        $has_fail = false;

        foreach ( $site_ids as $site_id ) {
            $result = self::dispatch( $job->job_type, $payload, (int) $site_id, $job_id );
            $results[ $site_id ] = $result;
            if ( $result['status'] === 'fail' ) {
                $has_fail = true;
            }
        }

        // Mark as done or failed.
        $wpdb->update(
            $table,
            [
                'status'       => $has_fail ? 'failed' : 'done',
                'results'      => wp_json_encode( $results ),
                'completed_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $job_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Dispatch a single site action based on job type.
     *
     * @param string $job_type Job type.
     * @param array  $payload  Job payload.
     * @param int    $site_id  Target site ID.
     * @param int    $job_id   Job ID for logging.
     * @return array { status: 'pass'|'fail', message: string }
     */
    private static function dispatch( $job_type, $payload, $site_id, $job_id ) {
        switch ( $job_type ) {
            case 'push_setting':
                return self::dispatch_push_setting( $payload, $site_id, $job_id );

            case 'push_feed':
                return self::dispatch_push_feed( $payload, $site_id, $job_id );

            case 'remove_feed':
                return self::dispatch_remove_feed( $payload, $site_id, $job_id );

            case 'force_fetch':
                return self::dispatch_force_fetch( $payload, $site_id, $job_id );

            default:
                $msg = sprintf( __( 'Unknown job type: %s', 'wprss-hub' ), $job_type );
                WPRSS_Hub_Logger::log( $job_id, $site_id, $job_type, 'fail', $msg );
                return [ 'status' => 'fail', 'message' => $msg ];
        }
    }

    /**
     * Push one or more WPRSS settings to a site via SSH.
     */
    private static function dispatch_push_setting( $payload, $site_id, $job_id ) {
        $option_key   = isset( $payload['option_key'] ) ? $payload['option_key'] : '';
        $option_value = isset( $payload['option_value'] ) ? $payload['option_value'] : '';

        // Try SSH first (Lane B).
        $site = WPRSS_Hub_Site_Registry::get( $site_id );
        if ( $site && ! empty( $site->ssh_host ) ) {
            // Read current general options, update the key, write back.
            $result = WPRSS_Hub_SSH_Client::run_wpcli( $site_id, 'option get wprss_settings_general --format=json' );
            if ( $result['exit_code'] === 0 ) {
                $current = json_decode( $result['output'], true );
                if ( is_array( $current ) ) {
                    $current[ $option_key ] = $option_value;
                    $success = WPRSS_Hub_SSH_Client::update_option( $site_id, 'wprss_settings_general', wp_json_encode( $current ) );
                    if ( $success ) {
                        WPRSS_Hub_Logger::log( $job_id, $site_id, 'push_setting', 'pass', $option_key . ' = ' . $option_value );
                        return [ 'status' => 'pass', 'message' => 'Setting updated via SSH.' ];
                    }
                }
            }
        }

        // Fallback to REST (Lane A).
        $rest_result = WPRSS_Hub_Rest_Client::set_options( $site_id, [ $option_key => $option_value ] );
        if ( is_wp_error( $rest_result ) ) {
            $msg = $rest_result->get_error_message();
            WPRSS_Hub_Logger::log( $job_id, $site_id, 'push_setting', 'fail', $msg );
            return [ 'status' => 'fail', 'message' => $msg ];
        }

        WPRSS_Hub_Logger::log( $job_id, $site_id, 'push_setting', 'pass', $option_key . ' = ' . $option_value );
        return [ 'status' => 'pass', 'message' => 'Setting updated via REST.' ];
    }

    /**
     * Push a feed to a remote site.
     */
    private static function dispatch_push_feed( $payload, $site_id, $job_id ) {
        $feed_url   = isset( $payload['feed_url'] ) ? $payload['feed_url'] : '';
        $feed_title = isset( $payload['feed_title'] ) ? $payload['feed_title'] : '';

        $result = WPRSS_Hub_Rest_Client::create_feed( $site_id, $feed_url, $feed_title );
        if ( is_wp_error( $result ) ) {
            $msg = $result->get_error_message();
            WPRSS_Hub_Logger::log( $job_id, $site_id, 'push_feed', 'fail', $msg );
            return [ 'status' => 'fail', 'message' => $msg ];
        }

        WPRSS_Hub_Logger::log( $job_id, $site_id, 'push_feed', 'pass', $feed_url );
        return [ 'status' => 'pass', 'message' => 'Feed created.' ];
    }

    /**
     * Remove a feed from a remote site.
     */
    private static function dispatch_remove_feed( $payload, $site_id, $job_id ) {
        $remote_feed_id = isset( $payload['remote_feed_id'] ) ? (int) $payload['remote_feed_id'] : 0;

        if ( ! $remote_feed_id ) {
            // Try to find feed by URL on the remote site.
            $feed_url = isset( $payload['feed_url'] ) ? $payload['feed_url'] : '';
            $feeds    = WPRSS_Hub_Rest_Client::get_feeds( $site_id );
            if ( is_wp_error( $feeds ) ) {
                $msg = $feeds->get_error_message();
                WPRSS_Hub_Logger::log( $job_id, $site_id, 'remove_feed', 'fail', $msg );
                return [ 'status' => 'fail', 'message' => $msg ];
            }
            foreach ( $feeds as $feed ) {
                $url = isset( $feed['meta']['wprss_url'] ) ? $feed['meta']['wprss_url'] : '';
                if ( $url === $feed_url ) {
                    $remote_feed_id = $feed['id'];
                    break;
                }
            }
            if ( ! $remote_feed_id ) {
                WPRSS_Hub_Logger::log( $job_id, $site_id, 'remove_feed', 'pass', 'Feed not found on remote — nothing to remove.' );
                return [ 'status' => 'pass', 'message' => 'Feed not found on remote.' ];
            }
        }

        $result = WPRSS_Hub_Rest_Client::delete_feed( $site_id, $remote_feed_id );
        if ( is_wp_error( $result ) ) {
            $msg = $result->get_error_message();
            WPRSS_Hub_Logger::log( $job_id, $site_id, 'remove_feed', 'fail', $msg );
            return [ 'status' => 'fail', 'message' => $msg ];
        }

        WPRSS_Hub_Logger::log( $job_id, $site_id, 'remove_feed', 'pass', 'Feed removed.' );
        return [ 'status' => 'pass', 'message' => 'Feed removed.' ];
    }

    /**
     * Force fetch a feed on a remote site.
     */
    private static function dispatch_force_fetch( $payload, $site_id, $job_id ) {
        $remote_feed_id = isset( $payload['remote_feed_id'] ) ? (int) $payload['remote_feed_id'] : 0;

        // Try SSH first.
        $site = WPRSS_Hub_Site_Registry::get( $site_id );
        if ( $site && ! empty( $site->ssh_host ) ) {
            $success = WPRSS_Hub_SSH_Client::force_fetch_feed( $site_id, $remote_feed_id );
            if ( $success ) {
                WPRSS_Hub_Logger::log( $job_id, $site_id, 'force_fetch', 'pass', 'Fetch triggered via SSH.' );
                return [ 'status' => 'pass', 'message' => 'Fetch triggered via SSH.' ];
            }
        }

        // Fallback to REST.
        $result = WPRSS_Hub_Rest_Client::force_fetch( $site_id, $remote_feed_id );
        if ( is_wp_error( $result ) ) {
            $msg = $result->get_error_message();
            WPRSS_Hub_Logger::log( $job_id, $site_id, 'force_fetch', 'fail', $msg );
            return [ 'status' => 'fail', 'message' => $msg ];
        }

        WPRSS_Hub_Logger::log( $job_id, $site_id, 'force_fetch', 'pass', 'Fetch triggered via REST.' );
        return [ 'status' => 'pass', 'message' => 'Fetch triggered via REST.' ];
    }

    /**
     * Get jobs, optionally filtered by status.
     *
     * @param string $status Optional status filter.
     * @return array
     */
    public static function get_jobs( $status = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_jobs';

        if ( $status ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
                $status
            ) );
        }

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
    }

    /**
     * Retry a job — re-run only for specified (or all failed) sites.
     *
     * @param int   $job_id   Job ID.
     * @param array $site_ids Optional: specific site IDs to retry. Defaults to all failed sites.
     */
    public static function retry_job( $job_id, $site_ids = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_jobs';

        $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $job_id ) );
        if ( ! $job ) {
            return;
        }

        $results = json_decode( $job->results, true );
        if ( empty( $site_ids ) && is_array( $results ) ) {
            // Collect all failed site IDs.
            foreach ( $results as $sid => $res ) {
                if ( isset( $res['status'] ) && $res['status'] === 'fail' ) {
                    $site_ids[] = (int) $sid;
                }
            }
        }

        if ( empty( $site_ids ) ) {
            return;
        }

        // Create a new job with only the failed sites.
        $payload = json_decode( $job->payload, true );
        self::enqueue( $job->job_type, $payload, $site_ids );
    }
}
