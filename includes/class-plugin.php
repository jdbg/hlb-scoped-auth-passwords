<?php
/**
 * Plugin bootstrap.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once HLB_SAP_DIR . 'includes/class-schema.php';
require_once HLB_SAP_DIR . 'includes/class-scope-store.php';
require_once HLB_SAP_DIR . 'includes/class-current-credential.php';
require_once HLB_SAP_DIR . 'includes/class-auth-guard.php';
require_once HLB_SAP_DIR . 'includes/class-scope-guard.php';

/*
 * Registered here, at file-load time, not on plugins_loaded. Core itself
 * registers wp_validate_application_password against determine_current_user
 * unconditionally in default-filters.php, before any plugin loads. If
 * another active plugin resolves the current user from its own
 * plugins_loaded callback - a common pattern - authentication (and this
 * hook) can run before a plugins_loaded-deferred registration exists,
 * and a scoped credential would authenticate with no restriction at all.
 * Registering unconditionally at file scope, the same time core registers
 * its own auth filters, closes that gap.
 */
Auth_Guard::init();

/**
 * Wires the enforcement hooks described in
 * decisions/scope-application-passwords-by-ability-and-post-type.md.
 */
class Plugin {

	/**
	 * Hooks the rest of the plugin into WordPress. Runs on plugins_loaded.
	 * Auth_Guard registers earlier - see the top of this file.
	 */
	public static function init(): void {
		Scope_Guard::init();

		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_schema' ) );
		add_action( 'wp_delete_application_password', array( __CLASS__, 'on_application_password_deleted' ), 10, 2 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once HLB_SAP_DIR . 'includes/class-cli-command.php';
			\WP_CLI::add_command( 'hlb-sap', __NAMESPACE__ . '\\CLI_Command' );
		}
	}

	/**
	 * Creates the companion scope table. Runs on plugin activation.
	 */
	public static function activate(): void {
		Schema::install();
	}

	/**
	 * Catches a schema version bump on an already-active install. Checked
	 * only in wp-admin, not on every front-end or REST request.
	 */
	public static function maybe_upgrade_schema(): void {
		Schema::maybe_upgrade();
	}

	/**
	 * Clears an orphaned scope row when its Application Password is
	 * deleted through core (the Users screen, core's REST endpoint, or
	 * delete_all_application_passwords()), not just through this
	 * plugin's own revoke command.
	 *
	 * @param int   $user_id User ID. Unused; required by the hook signature.
	 * @param array $item    The deleted application password record, including its uuid.
	 */
	public static function on_application_password_deleted( int $user_id, array $item ): void {
		Scope_Store::delete( $item['uuid'] );
	}
}
