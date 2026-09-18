<?php
/**
 * Provisions fixture credentials for the Playwright E2E suite. Run via
 * `wp eval-file` from blueprint.json, after the plugin is active.
 *
 * Writes plaintext passwords to wp-content/uploads/test-fixtures.json so
 * Playwright can fetch them over HTTP. This is fine for a throwaway
 * Playground instance and must never happen on a real site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issues one Application Password and, unless $scope is null, a matching
 * scope row, recording the result in $fixtures.
 *
 * @param string     $key      Fixture key, used as the credential name too.
 * @param array|null $scope    Scope_Store::create() args, or null for an
 *                             ordinary unscoped Application Password.
 * @param array      $fixtures Accumulator, keyed by $key.
 * @param int        $user_id  Owning user.
 */
function hlb_sap_provision_issue( string $key, ?array $scope, array &$fixtures, int $user_id ): void {
	$result = WP_Application_Passwords::create_new_application_password(
		$user_id,
		array( 'name' => 'fixture-' . $key )
	);

	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Could not create application password for ' . $key . ': ' . $result->get_error_message() );
	}

	list( $password, $item ) = $result;

	if ( null !== $scope ) {
		$saved = \HLB_SAP\Scope_Store::create(
			$user_id,
			$item['uuid'],
			'fixture-' . $key,
			$scope['abilities'] ?? null,
			$scope['post_types'] ?? null,
			$scope['allow_xmlrpc'] ?? false,
			$scope['expires_at'] ?? null
		);

		if ( ! $saved ) {
			WP_CLI::error( 'Could not save scope row for ' . $key );
		}

		if ( ! empty( $scope['revoke'] ) ) {
			\HLB_SAP\Scope_Store::revoke( $item['uuid'] );
		}
	}

	$fixtures[ $key ] = array(
		'login'    => 'admin',
		'password' => $password,
		'uuid'     => $item['uuid'],
	);
}

$user_id  = 1; // The admin user created by the blueprint's login step.
$fixtures = array();

// An ordinary Application Password with no scope row: must behave exactly
// as core does today.
hlb_sap_provision_issue( 'unscoped', null, $fixtures, $user_id );

// A scoped credential with both dimensions left unrestricted: exists in
// the companion table but should reach anything an unscoped credential can.
hlb_sap_provision_issue(
	'unrestricted',
	array(
		'abilities'  => null,
		'post_types' => null,
	),
	$fixtures,
	$user_id
);

// Restricted to the "post" post type only.
hlb_sap_provision_issue(
	'post_scoped',
	array(
		'abilities'  => null,
		'post_types' => array( 'post' ),
	),
	$fixtures,
	$user_id
);

// Restricted to one named ability. Scoped to a core-registered ability
// (core/get-site-info) rather than a custom one: abilities registered by a
// mu-plugin's own wp_abilities_api_init callback do not reliably fire in
// this Playground/WP build (has_action() true, did_action() stays 0 for
// the whole request - verified with an in-request diagnostic route), so
// core's own abilities are the only ones this environment can be trusted
// to expose.
hlb_sap_provision_issue(
	'ability_scoped',
	array(
		'abilities'  => array( 'core/get-site-info' ),
		'post_types' => null,
	),
	$fixtures,
	$user_id
);

// Revoked immediately after issuance.
hlb_sap_provision_issue(
	'revoked',
	array(
		'abilities'  => null,
		'post_types' => null,
		'revoke'     => true,
	),
	$fixtures,
	$user_id
);

// Already expired at issuance.
hlb_sap_provision_issue(
	'expired',
	array(
		'abilities'  => null,
		'post_types' => null,
		'expires_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
	),
	$fixtures,
	$user_id
);

// Not permitted over XML-RPC (the default).
hlb_sap_provision_issue(
	'xmlrpc_denied',
	array(
		'abilities'  => null,
		'post_types' => null,
	),
	$fixtures,
	$user_id
);

// Explicitly permitted over XML-RPC.
hlb_sap_provision_issue(
	'xmlrpc_allowed',
	array(
		'abilities'    => null,
		'post_types'   => null,
		'allow_xmlrpc' => true,
	),
	$fixtures,
	$user_id
);

$upload_dir = wp_upload_dir();
$written    = file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	trailingslashit( $upload_dir['basedir'] ) . 'test-fixtures.json',
	wp_json_encode( $fixtures, JSON_PRETTY_PRINT )
);

if ( false === $written ) {
	WP_CLI::error( 'Could not write test-fixtures.json' );
}

WP_CLI::success( 'Provisioned ' . count( $fixtures ) . ' fixture credentials.' );
