<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wprss-hub-wrap">
    <h1><?php esc_html_e( 'Action Logs', 'wprss-hub' ); ?></h1>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="wprss-hub-log-site-filter">
                <option value=""><?php esc_html_e( 'All Sites', 'wprss-hub' ); ?></option>
            </select>
            <select id="wprss-hub-log-status-filter">
                <option value=""><?php esc_html_e( 'All Statuses', 'wprss-hub' ); ?></option>
                <option value="pass"><?php esc_html_e( 'Pass', 'wprss-hub' ); ?></option>
                <option value="fail"><?php esc_html_e( 'Fail', 'wprss-hub' ); ?></option>
            </select>
            <button type="button" id="wprss-hub-log-filter-btn" class="button"><?php esc_html_e( 'Filter', 'wprss-hub' ); ?></button>
        </div>
        <div class="alignright">
            <button type="button" id="wprss-hub-log-prune-btn" class="button"><?php esc_html_e( 'Clear Logs Older Than 30 Days', 'wprss-hub' ); ?></button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped" id="wprss-hub-logs-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Timestamp', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Site', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Action', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Message', 'wprss-hub' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr class="wprss-hub-loading-row"><td colspan="5"><?php esc_html_e( 'Loading...', 'wprss-hub' ); ?></td></tr>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages" id="wprss-hub-log-pagination"></div>
    </div>
</div>
