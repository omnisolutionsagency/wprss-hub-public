<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Rest_Api {

    const NAMESPACE = 'wprss-hub/v1';

    /**
     * Register all hub REST routes.
     */
    public static function register_routes() {
        // Sites.
        register_rest_route( self::NAMESPACE, '/sites', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list_sites' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_site' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update_site' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete_site' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)/test', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'test_site' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Feeds.
        register_rest_route( self::NAMESPACE, '/feeds', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list_feeds' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create_feed' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/feeds/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update_feed' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete_feed' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/feeds/mirror', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'mirror_feeds' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Settings.
        register_rest_route( self::NAMESPACE, '/settings/(?P<site_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_settings' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'set_settings' ],
                'permission_callback' => [ __CLASS__, 'check_permission' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/settings/global', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'push_global_setting' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Jobs.
        register_rest_route( self::NAMESPACE, '/jobs', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_jobs' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/jobs/(?P<id>\d+)/retry', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'retry_job' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Health.
        register_rest_route( self::NAMESPACE, '/health', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_health' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        // Logs.
        register_rest_route( self::NAMESPACE, '/logs', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'list_logs' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/logs/prune', [
            'methods'             => 'DELETE',
            'callback'            => [ __CLASS__, 'prune_logs' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );
    }

    /**
     * Permission callback — require manage_options.
     */
    public static function check_permission() {
        return current_user_can( 'manage_options' );
    }

    // -------------------------------------------------------------------------
    // Sites
    // -------------------------------------------------------------------------

    public static function list_sites( WP_REST_Request $request ) {
        $sites = WPRSS_Hub_Site_Registry::get_all();
        // Strip encrypted passwords from response.
        foreach ( $sites as &$site ) {
            unset( $site->app_password );
        }
        return rest_ensure_response( $sites );
    }

    public static function create_site( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        $required = [ 'name', 'site_url', 'app_user', 'app_password' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', sprintf( __( 'Field "%s" is required.', 'wprss-hub' ), $field ), [ 'status' => 400 ] );
            }
        }

        $result = WPRSS_Hub_Site_Registry::create( $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'id' => $result ] );
    }

    public static function update_site( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $data = $request->get_json_params();

        $result = WPRSS_Hub_Site_Registry::update( $id, $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function delete_site( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = WPRSS_Hub_Site_Registry::delete( $id );

        if ( $result === false ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete site.', 'wprss-hub' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function test_site( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $site = WPRSS_Hub_Site_Registry::get( $id );

        if ( ! $site ) {
            return new WP_Error( 'not_found', __( 'Site not found.', 'wprss-hub' ), [ 'status' => 404 ] );
        }

        $results = [];

        // Test REST connection.
        $rest_result       = WPRSS_Hub_Rest_Client::test_connection( $id );
        $results['rest']   = [
            'status'  => is_wp_error( $rest_result ) ? 'fail' : 'pass',
            'message' => is_wp_error( $rest_result ) ? $rest_result->get_error_message() : __( 'REST connection successful.', 'wprss-hub' ),
        ];

        // Test SSH connection if configured.
        if ( ! empty( $site->ssh_host ) ) {
            $ssh_result       = WPRSS_Hub_SSH_Client::test_connection( $id );
            $results['ssh']   = [
                'status'  => $ssh_result === true ? 'pass' : 'fail',
                'message' => $ssh_result === true ? __( 'SSH connection successful.', 'wprss-hub' ) : $ssh_result,
            ];
        } else {
            $results['ssh'] = [
                'status'  => 'skipped',
                'message' => __( 'SSH not configured.', 'wprss-hub' ),
            ];
        }

        return rest_ensure_response( $results );
    }

    // -------------------------------------------------------------------------
    // Feeds
    // -------------------------------------------------------------------------

    public static function list_feeds( WP_REST_Request $request ) {
        return rest_ensure_response( WPRSS_Hub_Feed_Manager::get_all() );
    }

    public static function create_feed( WP_REST_Request $request ) {
        $data   = $request->get_json_params();
        $result = WPRSS_Hub_Feed_Manager::create( $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'id' => $result ] );
    }

    public static function update_feed( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $data   = $request->get_json_params();
        $result = WPRSS_Hub_Feed_Manager::update( $id, $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function delete_feed( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = WPRSS_Hub_Feed_Manager::delete( $id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function mirror_feeds( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        if ( empty( $data['source_site_id'] ) || empty( $data['target_site_ids'] ) ) {
            return new WP_Error( 'missing_params', __( 'source_site_id and target_site_ids are required.', 'wprss-hub' ), [ 'status' => 400 ] );
        }

        $result = WPRSS_Hub_Feed_Manager::mirror(
            (int) $data['source_site_id'],
            array_map( 'absint', (array) $data['target_site_ids'] )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'job_ids' => $result ] );
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public static function get_settings( WP_REST_Request $request ) {
        $site_id = (int) $request->get_param( 'site_id' );
        $result  = WPRSS_Hub_Settings_Manager::get_site_settings( $site_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public static function set_settings( WP_REST_Request $request ) {
        $site_id = (int) $request->get_param( 'site_id' );
        $data    = $request->get_json_params();
        $result  = WPRSS_Hub_Settings_Manager::set_site_settings( $site_id, $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    public static function push_global_setting( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        if ( empty( $data['option_key'] ) || ! isset( $data['option_value'] ) ) {
            return new WP_Error( 'missing_params', __( 'option_key and option_value are required.', 'wprss-hub' ), [ 'status' => 400 ] );
        }

        $result = WPRSS_Hub_Settings_Manager::push_global( $data['option_key'], $data['option_value'] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [ 'job_id' => $result ] );
    }

    // -------------------------------------------------------------------------
    // Jobs
    // -------------------------------------------------------------------------

    public static function list_jobs( WP_REST_Request $request ) {
        $status = $request->get_param( 'status' );

        if ( $status && strpos( $status, ',' ) !== false ) {
            // Multiple statuses: query each.
            $statuses = array_map( 'trim', explode( ',', $status ) );
            $jobs     = [];
            foreach ( $statuses as $s ) {
                $jobs = array_merge( $jobs, WPRSS_Hub_Job_Queue::get_jobs( $s ) );
            }
            // Sort by created_at desc.
            usort( $jobs, function ( $a, $b ) {
                return strcmp( $b->created_at, $a->created_at );
            } );
        } else {
            $jobs = WPRSS_Hub_Job_Queue::get_jobs( $status ?: '' );
        }

        // Decode JSON fields for the response.
        foreach ( $jobs as &$job ) {
            $job->payload  = json_decode( $job->payload );
            $job->site_ids = json_decode( $job->site_ids );
            $job->results  = json_decode( $job->results );
        }

        return rest_ensure_response( $jobs );
    }

    public static function retry_job( WP_REST_Request $request ) {
        $id        = (int) $request->get_param( 'id' );
        $data      = $request->get_json_params();
        $site_ids  = isset( $data['site_ids'] ) ? array_map( 'absint', (array) $data['site_ids'] ) : [];

        WPRSS_Hub_Job_Queue::retry_job( $id, $site_ids );

        return rest_ensure_response( [ 'success' => true ] );
    }

    // -------------------------------------------------------------------------
    // Health
    // -------------------------------------------------------------------------

    public static function get_health( WP_REST_Request $request ) {
        return rest_ensure_response( WPRSS_Hub_Health::poll_all() );
    }

    // -------------------------------------------------------------------------
    // Logs
    // -------------------------------------------------------------------------

    public static function list_logs( WP_REST_Request $request ) {
        $args = [
            'site_id'  => $request->get_param( 'site_id' ) ? (int) $request->get_param( 'site_id' ) : 0,
            'status'   => $request->get_param( 'status' ) ?: '',
            'per_page' => $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 50,
            'page'     => $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1,
        ];

        return rest_ensure_response( WPRSS_Hub_Logger::query( $args ) );
    }

    public static function prune_logs( WP_REST_Request $request ) {
        $days    = $request->get_param( 'days' ) ? (int) $request->get_param( 'days' ) : 30;
        $deleted = WPRSS_Hub_Logger::prune( $days );

        return rest_ensure_response( [ 'deleted' => $deleted ] );
    }
}
