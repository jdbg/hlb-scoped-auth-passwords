<?php
/**
 * Companion table for credential scope.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and versions the table that holds scope for a credential,
 * keyed by the Application Password uuid. Core's own application
 * password record has no room for extra fields, so scope lives here.
 */
class Schema {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'hlb_sap_db_version';

	/**
	 * Creates or updates the table for the current schema version.
	 */
	public static function install(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			uuid VARCHAR(36) NOT NULL,
			name VARCHAR(255) NOT NULL DEFAULT '',
			abilities LONGTEXT NULL,
			post_types LONGTEXT NULL,
			allow_xmlrpc TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATETIME NULL,
			revoked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Runs install() again if the stored schema version is stale.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * The scope table name, with the site's table prefix applied.
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'hlb_sap_credential_scopes';
	}
}
