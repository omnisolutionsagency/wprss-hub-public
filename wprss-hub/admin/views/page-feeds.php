<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wprss-hub-wrap">
    <h1><?php esc_html_e( 'Feeds', 'wprss-hub' ); ?></h1>

    <!-- Tabs -->
    <h2 class="nav-tab-wrapper" id="wprss-hub-feed-tabs">
        <a href="#manage" class="nav-tab nav-tab-active" data-tab="manage"><?php esc_html_e( 'Manage Feeds', 'wprss-hub' ); ?></a>
        <a href="#add" class="nav-tab" data-tab="add"><?php esc_html_e( 'Add Feed', 'wprss-hub' ); ?></a>
        <a href="#mirror" class="nav-tab" data-tab="mirror"><?php esc_html_e( 'Mirror Feeds', 'wprss-hub' ); ?></a>
    </h2>

    <!-- Tab: Manage Feeds -->
    <div class="wprss-hub-tab-content" id="wprss-hub-tab-manage">
        <table class="wp-list-table widefat fixed striped" id="wprss-hub-feeds-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'wprss-hub' ); ?></th>
                    <th><?php esc_html_e( 'URL', 'wprss-hub' ); ?></th>
                    <th><?php esc_html_e( 'Assigned Sites', 'wprss-hub' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wprss-hub' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr class="wprss-hub-loading-row"><td colspan="4"><?php esc_html_e( 'Loading...', 'wprss-hub' ); ?></td></tr>
            </tbody>
        </table>
    </div>

    <!-- Tab: Add Feed -->
    <div class="wprss-hub-tab-content" id="wprss-hub-tab-add" style="display:none;">
        <table class="form-table">
            <tr>
                <th><label for="feed-url"><?php esc_html_e( 'Feed URL', 'wprss-hub' ); ?></label></th>
                <td><input type="url" id="feed-url" class="regular-text" placeholder="https://example.com/feed/" /></td>
            </tr>
            <tr>
                <th><label for="feed-title"><?php esc_html_e( 'Feed Title', 'wprss-hub' ); ?></label></th>
                <td><input type="text" id="feed-title" class="regular-text" /></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Assign to Sites', 'wprss-hub' ); ?></th>
                <td>
                    <div id="wprss-hub-feed-sites-checkboxes" class="wprss-hub-checkboxes">
                        <p class="description"><?php esc_html_e( 'Loading sites...', 'wprss-hub' ); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="feed-notes"><?php esc_html_e( 'Notes', 'wprss-hub' ); ?></label></th>
                <td><textarea id="feed-notes" class="large-text" rows="3"></textarea></td>
            </tr>
        </table>
        <p>
            <button type="button" id="wprss-hub-save-feed" class="button button-primary"><?php esc_html_e( 'Add Feed', 'wprss-hub' ); ?></button>
        </p>
    </div>

    <!-- Tab: Mirror Feeds -->
    <div class="wprss-hub-tab-content" id="wprss-hub-tab-mirror" style="display:none;">
        <table class="form-table">
            <tr>
                <th><label for="mirror-source"><?php esc_html_e( 'Source Site', 'wprss-hub' ); ?></label></th>
                <td>
                    <select id="mirror-source" class="regular-text">
                        <option value=""><?php esc_html_e( 'Loading...', 'wprss-hub' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Target Sites', 'wprss-hub' ); ?></th>
                <td>
                    <div id="wprss-hub-mirror-targets" class="wprss-hub-checkboxes">
                        <p class="description"><?php esc_html_e( 'Loading sites...', 'wprss-hub' ); ?></p>
                    </div>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" id="wprss-hub-mirror-btn" class="button button-primary"><?php esc_html_e( 'Mirror Feeds', 'wprss-hub' ); ?></button>
        </p>
    </div>

    <!-- Inline Edit Modal for Site Assignments -->
    <div id="wprss-hub-assign-modal" class="wprss-hub-modal" style="display:none;">
        <div class="wprss-hub-modal-content">
            <h3><?php esc_html_e( 'Assign Sites', 'wprss-hub' ); ?></h3>
            <input type="hidden" id="assign-feed-id" value="" />
            <div id="wprss-hub-assign-checkboxes" class="wprss-hub-checkboxes"></div>
            <p>
                <button type="button" id="wprss-hub-save-assignments" class="button button-primary"><?php esc_html_e( 'Save', 'wprss-hub' ); ?></button>
                <button type="button" class="button wprss-hub-modal-close"><?php esc_html_e( 'Cancel', 'wprss-hub' ); ?></button>
            </p>
        </div>
    </div>
</div>
