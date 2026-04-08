<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wprss-hub-wrap">
    <h1><?php esc_html_e( 'Sites', 'wprss-hub' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Install the companion plugin (wprss-hub-remote.php) on each remote site for full functionality.', 'wprss-hub' ); ?></p>

    <!-- Add / Edit Site Form -->
    <div id="wprss-hub-site-form-wrap" style="display:none;">
        <h2 id="wprss-hub-site-form-title"><?php esc_html_e( 'Add Site', 'wprss-hub' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="site-name"><?php esc_html_e( 'Site Name', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="site-url"><?php esc_html_e( 'Site URL', 'wprss-hub' ); ?></label></th>
                <td><input type="url" id="site-url" class="regular-text" placeholder="https://example.com" /></td>
            </tr>
            <tr>
                <th><label for="site-app-user"><?php esc_html_e( 'WP Username', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-app-user" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="site-app-password"><?php esc_html_e( 'Application Password', 'wprss-hub' ); ?></label></th>
                <td><input type="password" id="site-app-password" class="regular-text" /></td>
            </tr>
            <tr>
                <th colspan="2"><hr /><strong><?php esc_html_e( 'SSH Configuration (optional)', 'wprss-hub' ); ?></strong></th>
            </tr>
            <tr>
                <th><label for="site-ssh-host"><?php esc_html_e( 'SSH Host', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-ssh-host" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="site-ssh-port"><?php esc_html_e( 'SSH Port', 'wprss-hub' ); ?></label></th>
                <td><input type="number" id="site-ssh-port" class="small-text" value="22" /></td>
            </tr>
            <tr>
                <th><label for="site-ssh-user"><?php esc_html_e( 'SSH User', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-ssh-user" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="site-ssh-key-path"><?php esc_html_e( 'SSH Key Path', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-ssh-key-path" class="regular-text" placeholder="/home/user/.ssh/id_rsa" /></td>
            </tr>
            <tr>
                <th><label for="site-wp-path"><?php esc_html_e( 'Remote WP Path', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="site-wp-path" class="regular-text" placeholder="/home/user/public_html" /></td>
            </tr>
        </table>
        <input type="hidden" id="site-edit-id" value="" />
        <p>
            <button type="button" id="wprss-hub-save-site" class="button button-primary"><?php esc_html_e( 'Save Site', 'wprss-hub' ); ?></button>
            <button type="button" id="wprss-hub-cancel-site" class="button"><?php esc_html_e( 'Cancel', 'wprss-hub' ); ?></button>
        </p>
    </div>

    <p>
        <button type="button" id="wprss-hub-add-site-btn" class="button button-primary"><?php esc_html_e( 'Add Site', 'wprss-hub' ); ?></button>
    </p>

    <!-- Sites Table -->
    <table class="wp-list-table widefat fixed striped" id="wprss-hub-sites-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'URL', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Status', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'SSH', 'wprss-hub' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'wprss-hub' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr class="wprss-hub-loading-row"><td colspan="5"><?php esc_html_e( 'Loading...', 'wprss-hub' ); ?></td></tr>
        </tbody>
    </table>

    <!-- Test Connection Modal -->
    <div id="wprss-hub-test-modal" class="wprss-hub-modal" style="display:none;">
        <div class="wprss-hub-modal-content">
            <h3><?php esc_html_e( 'Connection Test', 'wprss-hub' ); ?></h3>
            <div id="wprss-hub-test-results">
                <p class="wprss-hub-spinner"><?php esc_html_e( 'Testing connection...', 'wprss-hub' ); ?></p>
            </div>
            <p><button type="button" class="button wprss-hub-modal-close"><?php esc_html_e( 'Close', 'wprss-hub' ); ?></button></p>
        </div>
    </div>
</div>
