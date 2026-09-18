<?php
/**
 * Per-request holder for the authenticated scoped credential.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth_Guard stashes the scope row here once a scoped credential passes
 * its checks, so Scope_Guard can read it again at dispatch time without
 * re-deriving which credential authenticated the request.
 */
class Current_Credential {

	/**
	 * The active credential's scope row, or null if unset.
	 *
	 * @var array|null
	 */
	private static $scope = null;

	/**
	 * Whether the current request authenticated with a scoped credential.
	 *
	 * @var bool
	 */
	private static $is_scoped = false;

	/**
	 * Records the scope row for the request that just authenticated.
	 *
	 * @param array $scope Row from Scope_Store.
	 */
	public static function set( array $scope ): void {
		self::$scope     = $scope;
		self::$is_scoped = true;
	}

	/**
	 * The active credential's scope row, if any.
	 */
	public static function get(): ?array {
		return self::$scope;
	}

	/**
	 * Whether the current request is authenticated with a scoped credential.
	 */
	public static function is_scoped(): bool {
		return self::$is_scoped;
	}
}
