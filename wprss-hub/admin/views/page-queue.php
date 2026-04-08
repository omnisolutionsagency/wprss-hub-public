<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wprss-hub-wrap">
    <h1><?php esc_html_e( 'Job Queue', 'wprss-hub' ); ?></h1>

    <table class="wp-list-table widefat fixed striped" id="wprss-hub-jobs-table">
        <thead>
            <tr>
                <th style="width:50px;"><?php esc_html_e( 'ID', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Type', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Sites', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Created', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Completed', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'wprss-hub' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr class="wprss-hub-loading-row"><td colspan="7"><?php esc_html_e( 'Loading...', 'wprss-hub' ); ?></td></tr>
        </tbody>
    </table>

    <!-- Job Detail Expand Row (template) -->
    <script type="text/html" id="tmpl-wprss-hub-job-detail">
        <tr class="wprss-hub-job-detail" data-job-id="{{jobId}}">
            <td colspan="7">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Site', 'wprss-hub' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'wprss-hub' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'wprss-hub' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>{{rows}}</tbody>
                </table>
            </td>
        </tr>
    </script>
</div>
