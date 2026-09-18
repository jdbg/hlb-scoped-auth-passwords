<?php
/**
 * Auth-time enforcement: expiry, revocation, and XML-RPC gating.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks wp_authenticate_application_password_errors, the action core fires
 * with a WP_Error after a password has checked out as correct but before
 * the session is established. $item carries the credential's uuid, which
 * is the only place core exposes it during authentication.
 *
 * Scope (ability/post-type) enforcement happens later, at dispatch time,
 * in Scope_Guard - it needs the matched route, which doesn't exist yet
 * here. This class only rules on whether the credential may authenticate
 * at all.
 */
class Auth_Guard {

	/**
	 * Registers the auth-time check.
	 */
	public static function init(): void {
		add_action( 'wp_authenticate_application_password_errors', array( __CLASS__, 'check' ), 10, 3 );
	}

	/**
	 * Rejects an authenticating credential that's revoked, expired, or
	 * using XML-RPC without being scoped for it. Stashes the scope row
	 * for Scope_Guard when the credential is otherwise let through.
	 *
	 * @param \WP_Error $error Accumulates errors; core checks has_errors() after this fires.
	 * @param \WP_User  $user  The user authenticating. Unused; required by the hook signature.
	 * @param array     $item  The application password record, including its uuid.
	 */
	public static function check( \WP_Error $error, \WP_User $user, array $item ): void {
		if ( $error->has_errors() ) {
			return;
		}

		$scope = Scope_Store::get_by_uuid( $item['uuid'] );

		if ( null === $scope ) {
			// An ordinary, unscoped Application Password. Leave it alone.
			return;
		}

		if ( Scope_Store::is_revoked( $scope ) ) {
			$error->add(
				'hlb_sap_credential_revoked',
				__( 'This credential has been revoked.', 'hlb-scoped-auth-passwords' )
			);
			return;
		}

		if ( Scope_Store::is_expired( $scope ) ) {
			$error->add(
				'hlb_sap_credential_expired',
				__( 'This credential has expired.', 'hlb-scoped-auth-passwords' )
			);
			return;
		}

		$is_xmlrpc_request = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;

		if ( $is_xmlrpc_request && empty( $scope['allow_xmlrpc'] ) ) {
			$error->add(
				'hlb_sap_credential_xmlrpc_denied',
				__( 'This credential is not permitted to authenticate over XML-RPC.', 'hlb-scoped-auth-passwords' )
			);
			return;
		}

		Current_Credential::set( $scope );
	}
}
