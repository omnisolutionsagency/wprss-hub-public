<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'wprss_hub_sites',
    $wpdb->prefix . 'wprss_hub_feeds',
    $wpdb->prefix . 'wprss_hub_jobs',
    $wpdb->prefix . 'wprss_hub_log',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'wprss_hub_db_version' );
delete_option( 'wprss_hub_encryption_key' );
