<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wprss-hub-wrap">
    <h1><?php esc_html_e( 'Settings', 'wprss-hub' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Click a cell to edit. Save to push via REST (single site) or use the Global column to push to all sites via queue.', 'wprss-hub' ); ?></p>

    <div class="wprss-hub-settings-grid-wrap">
        <table class="wp-list-table widefat fixed striped wprss-hub-settings-grid" id="wprss-hub-settings-grid">
            <thead>
                <tr>
                    <th class="wprss-hub-sticky-col"><?php esc_html_e( 'Setting', 'wprss-hub' ); ?></th>
                    <th class="wprss-hub-global-col"><?php esc_html_e( 'Global', 'wprss-hub' ); ?></th>
                    <!-- Site columns rendered by JS -->
                </tr>
            </thead>
            <tbody id="wprss-hub-settings-body">
                <tr><td colspan="2" class="wprss-hub-loading-row"><?php esc_html_e( 'Loading settings...', 'wprss-hub' ); ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
