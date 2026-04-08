<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Health {

    /**
     * Poll health data from all active sites.
     *
     * @return array Array of per-site health data.
     */
    public static function poll_all() {
        $sites   = WPRSS_Hub_Site_Registry::get_all();
        $results = [];

        foreach ( $sites as $site ) {
            if ( $site->status !== 'active' ) {
                $results[] = [
                    'site_id'     => (int) $site->id,
                    'site_name'   => $site->name,
                    'status'      => 'disabled',
                    'last_fetch'  => null,
                    'item_count'  => 0,
                    'error_count' => 0,
                ];
                continue;
            }

            $health = WPRSS_Hub_Rest_Client::get_health( (int) $site->id );

            if ( is_wp_error( $health ) ) {
                $results[] = [
                    'site_id'     => (int) $site->id,
                    'site_name'   => $site->name,
                    'status'      => 'fail',
                    'error'       => $health->get_error_message(),
                    'last_fetch'  => null,
                    'item_count'  => 0,
                    'error_count' => 0,
                ];
                continue;
            }

            $results[] = [
                'site_id'     => (int) $site->id,
                'site_name'   => $site->name,
                'status'      => ( isset( $health['error_count'] ) && $health['error_count'] > 0 ) ? 'warning' : 'pass',
                'last_fetch'  => isset( $health['last_fetch'] ) ? (int) $health['last_fetch'] : null,
                'item_count'  => isset( $health['item_count'] ) ? (int) $health['item_count'] : 0,
                'error_count' => isset( $health['error_count'] ) ? (int) $health['error_count'] : 0,
            ];
        }

        return $results;
    }
}
