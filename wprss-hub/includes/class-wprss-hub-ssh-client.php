<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_SSH_Client {

    /**
     * Run a WP-CLI command on a remote site via SSH.
     *
     * @param int    $site_id    Hub site ID.
     * @param string $subcommand WP-CLI subcommand (e.g. 'option get blogname').
     * @return array { exit_code: int, output: string }
     */
    public static function run_wpcli( $site_id, $subcommand ) {
        $site = WPRSS_Hub_Site_Registry::get( $site_id );
        if ( ! $site ) {
            WPRSS_Hub_Logger::log( null, $site_id, 'ssh_run', 'fail', __( 'Site not found.', 'wprss-hub' ) );
            return [ 'exit_code' => 1, 'output' => 'Site not found.' ];
        }

        if ( empty( $site->ssh_host ) || empty( $site->ssh_user ) || empty( $site->ssh_key_path ) || empty( $site->wp_path ) ) {
            WPRSS_Hub_Logger::log( null, $site_id, 'ssh_run', 'fail', __( 'SSH not configured for this site.', 'wprss-hub' ) );
            return [ 'exit_code' => 1, 'output' => 'SSH not configured for this site.' ];
        }

        $ssh_port    = ! empty( $site->ssh_port ) ? (int) $site->ssh_port : 22;
        $key_path    = $site->ssh_key_path;
        $remote_user = $site->ssh_user;
        $remote_host = $site->ssh_host;
        $wp_path     = $site->wp_path;

        // Escape the WP-CLI subcommand for safe passage through SSH.
        $escaped_subcommand = escapeshellarg( "cd " . $wp_path . " && wp " . $subcommand . " --allow-root 2>&1" );

        $ssh_command = sprintf(
            'ssh -i %s -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=10 -p %d %s@%s %s',
            escapeshellarg( $key_path ),
            $ssh_port,
            escapeshellarg( $remote_user ),
            escapeshellarg( $remote_host ),
            $escaped_subcommand
        );

        $descriptors = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ],
        ];

        $process = proc_open( $ssh_command, $descriptors, $pipes );

        if ( ! is_resource( $process ) ) {
            $msg = __( 'Failed to open SSH process.', 'wprss-hub' );
            WPRSS_Hub_Logger::log( null, $site_id, 'ssh_run', 'fail', $msg );
            return [ 'exit_code' => 1, 'output' => $msg ];
        }

        // Close stdin — we don't send any input.
        fclose( $pipes[0] );

        // Read stdout and stderr with a timeout.
        $timeout  = 30;
        $start    = time();
        $stdout   = '';
        $stderr   = '';

        stream_set_blocking( $pipes[1], false );
        stream_set_blocking( $pipes[2], false );

        while ( true ) {
            $stdout .= stream_get_contents( $pipes[1] );
            $stderr .= stream_get_contents( $pipes[2] );

            $status = proc_get_status( $process );
            if ( ! $status['running'] ) {
                // Read any remaining output.
                $stdout .= stream_get_contents( $pipes[1] );
                $stderr .= stream_get_contents( $pipes[2] );
                break;
            }

            if ( ( time() - $start ) >= $timeout ) {
                proc_terminate( $process, 9 );
                fclose( $pipes[1] );
                fclose( $pipes[2] );
                $msg = __( 'SSH command timed out after 30 seconds.', 'wprss-hub' );
                WPRSS_Hub_Logger::log( null, $site_id, 'ssh_run', 'fail', $msg );
                return [ 'exit_code' => 124, 'output' => $msg ];
            }

            usleep( 100000 ); // 100ms poll interval.
        }

        fclose( $pipes[1] );
        fclose( $pipes[2] );

        $exit_code = $status['exitcode'];
        $output    = trim( $stdout . ( $stderr ? "\n" . $stderr : '' ) );

        $log_status = $exit_code === 0 ? 'pass' : 'fail';
        WPRSS_Hub_Logger::log( null, $site_id, 'ssh_run', $log_status, $subcommand . ' => ' . substr( $output, 0, 500 ) );

        return [ 'exit_code' => $exit_code, 'output' => $output ];
    }

    /**
     * Update a WordPress option on a remote site via WP-CLI.
     */
    public static function update_option( $site_id, $option, $json_value ) {
        $escaped_option = escapeshellarg( $option );
        $escaped_value  = escapeshellarg( $json_value );
        $result = self::run_wpcli( $site_id, "option update {$escaped_option} {$escaped_value} --format=json" );
        return $result['exit_code'] === 0;
    }

    /**
     * Force fetch a specific feed on a remote site via WP-CLI.
     */
    public static function force_fetch_feed( $site_id, $remote_feed_id ) {
        $feed_id = (int) $remote_feed_id;
        $result  = self::run_wpcli( $site_id, "eval 'do_action(\"wprss_fetch_single_feed_hook\", {$feed_id});'" );
        return $result['exit_code'] === 0;
    }

    /**
     * Test SSH connection to a remote site.
     * Returns true on success, or error message string on failure.
     */
    public static function test_connection( $site_id ) {
        $result = self::run_wpcli( $site_id, 'cli info' );
        if ( $result['exit_code'] === 0 ) {
            return true;
        }
        return $result['output'];
    }
}
