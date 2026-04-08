<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Logger {

    /**
     * Insert a log entry.
     *
     * @param int|null $job_id  Associated job ID, or null.
     * @param int      $site_id Site ID.
     * @param string   $action  Action name.
     * @param string   $status  'pass' or 'fail'.
     * @param string   $message Optional message.
     * @return int|false Insert ID or false on failure.
     */
    public static function log( $job_id, $site_id, $action, $status, $message = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_log';

        $result = $wpdb->insert(
            $table,
            [
                'job_id'  => $job_id,
                'site_id' => (int) $site_id,
                'action'  => sanitize_text_field( $action ),
                'status'  => in_array( $status, [ 'pass', 'fail' ], true ) ? $status : 'fail',
                'message' => sanitize_text_field( $message ),
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Query log entries with optional filters.
     *
     * @param array $args {
     *     @type int    $site_id  Filter by site.
     *     @type string $status   Filter by 'pass' or 'fail'.
     *     @type int    $per_page Number of rows per page. Default 50.
     *     @type int    $page     Page number. Default 1.
     * }
     * @return array { items: array, total: int, pages: int }
     */
    public static function query( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_log';

        $where   = [];
        $values  = [];

        if ( ! empty( $args['site_id'] ) ) {
            $where[]  = 'site_id = %d';
            $values[] = (int) $args['site_id'];
        }

        if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'pass', 'fail' ], true ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $per_page     = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
        $page         = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $offset       = ( $page - 1 ) * $per_page;

        // Get total count.
        if ( ! empty( $values ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} {$where_clause}",
                ...$values
            ) );
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }

        // Get rows.
        $query = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge( $values, [ $per_page, $offset ] );
        $items = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ) );

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Delete logs older than N days.
     *
     * @param int $days Number of days.
     * @return int Number of rows deleted.
     */
    public static function prune( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_log';

        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            absint( $days )
        ) );
    }
}
