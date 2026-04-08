<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRSS_Hub_DB {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$prefix}wprss_hub_sites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            site_url VARCHAR(255) NOT NULL,
            rest_url VARCHAR(255) NOT NULL,
            app_user VARCHAR(120) NOT NULL,
            app_password TEXT NOT NULL,
            ssh_host VARCHAR(255) DEFAULT NULL,
            ssh_port SMALLINT UNSIGNED DEFAULT 22,
            ssh_user VARCHAR(120) DEFAULT NULL,
            ssh_key_path VARCHAR(500) DEFAULT NULL,
            wp_path VARCHAR(500) DEFAULT NULL,
            status ENUM('active','disabled') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql );

        $sql = "CREATE TABLE {$prefix}wprss_hub_feeds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            feed_url TEXT NOT NULL,
            feed_title VARCHAR(255) DEFAULT '',
            assigned_sites LONGTEXT NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql );

        $sql = "CREATE TABLE {$prefix}wprss_hub_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(80) NOT NULL,
            payload LONGTEXT NOT NULL,
            site_ids LONGTEXT NOT NULL,
            status ENUM('queued','running','done','failed') DEFAULT 'queued',
            results LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql );

        $sql = "CREATE TABLE {$prefix}wprss_hub_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED DEFAULT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(120) NOT NULL,
            status ENUM('pass','fail') NOT NULL,
            message TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql );

        update_option( 'wprss_hub_db_version', WPRSS_HUB_DB_VERSION );
    }

    public static function maybe_upgrade() {
        $installed = get_option( 'wprss_hub_db_version', '0' );
        if ( version_compare( $installed, WPRSS_HUB_DB_VERSION, '<' ) ) {
            self::create_tables();
        }
    }
}
