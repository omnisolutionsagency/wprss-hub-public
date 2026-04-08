<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_Site_Registry {

    /**
     * Get all sites.
     */
    public static function get_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_sites';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
    }

    /**
     * Get a single site by ID.
     */
    public static function get( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_sites';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Create a new site.
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_sites';

        $encrypted_password = WPRSS_Hub_Crypto::encrypt( $data['app_password'] );
        if ( ! $encrypted_password ) {
            return new WP_Error( 'encryption_failed', __( 'Failed to encrypt application password.', 'wprss-hub' ) );
        }

        $site_url = untrailingslashit( $data['site_url'] );
        $rest_url = ! empty( $data['rest_url'] ) ? $data['rest_url'] : $site_url . '/wp-json';

        $result = $wpdb->insert(
            $table,
            [
                'name'         => sanitize_text_field( $data['name'] ),
                'site_url'     => esc_url_raw( $site_url ),
                'rest_url'     => esc_url_raw( $rest_url ),
                'app_user'     => sanitize_text_field( $data['app_user'] ),
                'app_password' => $encrypted_password,
                'ssh_host'     => ! empty( $data['ssh_host'] ) ? sanitize_text_field( $data['ssh_host'] ) : null,
                'ssh_port'     => ! empty( $data['ssh_port'] ) ? absint( $data['ssh_port'] ) : 22,
                'ssh_user'     => ! empty( $data['ssh_user'] ) ? sanitize_text_field( $data['ssh_user'] ) : null,
                'ssh_key_path' => ! empty( $data['ssh_key_path'] ) ? sanitize_text_field( $data['ssh_key_path'] ) : null,
                'wp_path'      => ! empty( $data['wp_path'] ) ? sanitize_text_field( $data['wp_path'] ) : null,
                'status'       => 'active',
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return new WP_Error( 'db_insert_failed', __( 'Failed to save site.', 'wprss-hub' ) );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a site.
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_sites';

        $fields  = [];
        $formats = [];

        if ( isset( $data['name'] ) ) {
            $fields['name'] = sanitize_text_field( $data['name'] );
            $formats[]      = '%s';
        }
        if ( isset( $data['site_url'] ) ) {
            $fields['site_url'] = esc_url_raw( untrailingslashit( $data['site_url'] ) );
            $formats[]          = '%s';
        }
        if ( isset( $data['rest_url'] ) ) {
            $fields['rest_url'] = esc_url_raw( $data['rest_url'] );
            $formats[]          = '%s';
        }
        if ( isset( $data['app_user'] ) ) {
            $fields['app_user'] = sanitize_text_field( $data['app_user'] );
            $formats[]          = '%s';
        }
        if ( isset( $data['app_password'] ) && $data['app_password'] !== '' ) {
            $encrypted = WPRSS_Hub_Crypto::encrypt( $data['app_password'] );
            if ( ! $encrypted ) {
                return new WP_Error( 'encryption_failed', __( 'Failed to encrypt application password.', 'wprss-hub' ) );
            }
            $fields['app_password'] = $encrypted;
            $formats[]              = '%s';
        }
        if ( array_key_exists( 'ssh_host', $data ) ) {
            $fields['ssh_host'] = ! empty( $data['ssh_host'] ) ? sanitize_text_field( $data['ssh_host'] ) : null;
            $formats[]          = '%s';
        }
        if ( isset( $data['ssh_port'] ) ) {
            $fields['ssh_port'] = absint( $data['ssh_port'] );
            $formats[]          = '%d';
        }
        if ( array_key_exists( 'ssh_user', $data ) ) {
            $fields['ssh_user'] = ! empty( $data['ssh_user'] ) ? sanitize_text_field( $data['ssh_user'] ) : null;
            $formats[]          = '%s';
        }
        if ( array_key_exists( 'ssh_key_path', $data ) ) {
            $fields['ssh_key_path'] = ! empty( $data['ssh_key_path'] ) ? sanitize_text_field( $data['ssh_key_path'] ) : null;
            $formats[]              = '%s';
        }
        if ( array_key_exists( 'wp_path', $data ) ) {
            $fields['wp_path'] = ! empty( $data['wp_path'] ) ? sanitize_text_field( $data['wp_path'] ) : null;
            $formats[]         = '%s';
        }
        if ( isset( $data['status'] ) ) {
            $fields['status'] = in_array( $data['status'], [ 'active', 'disabled' ], true ) ? $data['status'] : 'active';
            $formats[]        = '%s';
        }

        if ( empty( $fields ) ) {
            return new WP_Error( 'no_fields', __( 'No fields to update.', 'wprss-hub' ) );
        }

        $result = $wpdb->update( $table, $fields, [ 'id' => $id ], $formats, [ '%d' ] );

        if ( $result === false ) {
            return new WP_Error( 'db_update_failed', __( 'Failed to update site.', 'wprss-hub' ) );
        }

        return true;
    }

    /**
     * Delete a site.
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wprss_hub_sites';
        return $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Get decrypted credentials for a site.
     */
    public static function get_credentials( $id ) {
        $site = self::get( $id );
        if ( ! $site ) {
            return new WP_Error( 'site_not_found', __( 'Site not found.', 'wprss-hub' ) );
        }

        $password = WPRSS_Hub_Crypto::decrypt( $site->app_password );
        if ( $password === false ) {
            return new WP_Error( 'decrypt_failed', __( 'Failed to decrypt credentials.', 'wprss-hub' ) );
        }

        return [
            'rest_url'     => $site->rest_url,
            'app_user'     => $site->app_user,
            'app_password' => $password,
            'ssh_host'     => $site->ssh_host,
            'ssh_port'     => $site->ssh_port,
            'ssh_user'     => $site->ssh_user,
            'ssh_key_path' => $site->ssh_key_path,
            'wp_path'      => $site->wp_path,
        ];
    }
}
