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

/**
 * Wires the enforcement hooks described in
 * decisions/scope-application-passwords-by-ability-and-post-type.md.
 */
class Plugin {

	/**
	 * Hooks the plugin into WordPress. Runs on plugins_loaded.
	 */
	public static function init(): void {
		Schema::maybe_upgrade();

		Auth_Guard::init();
		Scope_Guard::init();

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
