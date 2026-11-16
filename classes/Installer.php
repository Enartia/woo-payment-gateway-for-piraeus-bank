<?php

namespace Papaki\PiraeusBank\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create and upgrade the plugin's own piraeusbank_transactions table.
 *
 * Replaces the SHOW TABLES check the gateway ran every time it was constructed, which happens
 * several times on every request.
 */
class Installer {
    const DB_VERSION        = '1';
    const DB_VERSION_OPTION = 'piraeusbank_db_version';

    /**
     * Run install() once per DB_VERSION, also for sites that updated without re-activating.
     *
     * @return void
     */
    public static function maybe_install() {
        if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    /**
     * Create the transactions table or bring its columns up to date, then store the schema version.
     *
     * Side effects: CREATE or ALTER TABLE through dbDelta(), and an option write.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'piraeusbank_transactions';

        dbDelta(
            "CREATE TABLE {$table} (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  merch_ref varchar(50) NOT NULL,
  trans_ticket varchar(32) NOT NULL,
  timestamp datetime DEFAULT NULL,
  PRIMARY KEY  (id)
) {$wpdb->get_charset_collate()};"
        );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }
}
