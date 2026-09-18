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

/**
 * Bootstraps the plugin. Enforcement logic (auth-time expiry/revoke check
 * on wp_authenticate_application_password_errors, dispatch-time ability and
 * post-type scope check) lands here as it's implemented.
 */
class Plugin {

	/**
	 * Hook the plugin into WordPress.
	 */
	public static function init(): void {
		// Enforcement hooks are added incrementally; see docs/decisions.
	}
}
