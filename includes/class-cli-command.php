<?php
/**
 * WP-CLI commands for issuing and managing scoped credentials.
 *
 * Loaded only under WP-CLI; it extends WP_CLI_Command, which doesn't
 * exist on a normal web request.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `wp hlb-sap issue|revoke|list-credentials`.
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * Issues a new scoped Application Password.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email that will own the credential.
	 *
	 * --name=<name>
	 * : Label for the credential.
	 *
	 * [--abilities=<abilities>]
	 * : Comma-separated ability names to allow. Omit for unrestricted; pass an empty string to allow none.
	 *
	 * [--post-types=<post_types>]
	 * : Comma-separated post types to allow. Omit for unrestricted; pass an empty string to allow none.
	 *
	 * [--expires=<datetime>]
	 * : Expiry, anything strtotime() understands (e.g. "2026-12-01", "+30 days"). Omit for no expiry.
	 *
	 * [--allow-xmlrpc]
	 * : Also permit this credential to authenticate over XML-RPC.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hlb-sap issue 12 --name="Content agent" --abilities=my-plugin/export-users --post-types=post
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function issue( $args, $assoc_args ) {
		$user = self::find_user( $args[0] );

		if ( ! $user ) {
			\WP_CLI::error( 'No such user.' );
		}

		$name = \WP_CLI\Utils\get_flag_value( $assoc_args, 'name' );

		if ( empty( $name ) ) {
			\WP_CLI::error( 'A --name is required.' );
		}

		$abilities  = self::collect_list( $assoc_args, 'abilities' );
		$post_types = self::collect_list( $assoc_args, 'post-types' );
		$expires_at = self::collect_expiry( $assoc_args );

		if ( false === $expires_at ) {
			\WP_CLI::error( 'Could not parse --expires.' );
		}

		$allow_xmlrpc = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'allow-xmlrpc', false );

		$result = \WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array( 'name' => $name )
		);

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		list( $password, $item ) = $result;

		$saved = Scope_Store::create(
			$user->ID,
			$item['uuid'],
			$name,
			$abilities,
			$post_types,
			$allow_xmlrpc,
			$expires_at
		);

		if ( ! $saved ) {
			\WP_Application_Passwords::delete_application_password( $user->ID, $item['uuid'] );
			\WP_CLI::error( 'Could not save the credential scope; the application password was rolled back.' );
		}

		\WP_CLI::success( sprintf( 'Credential issued. UUID: %s', $item['uuid'] ) );
		\WP_CLI::line( sprintf( 'Password (shown once): %s', $password ) );
	}

	/**
	 * Revokes a scoped credential immediately.
	 *
	 * ## OPTIONS
	 *
	 * <uuid>
	 * : The credential's uuid, as shown by `wp hlb-sap list-credentials`.
	 *
	 * [--delete]
	 * : Also delete the underlying Application Password.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function revoke( $args, $assoc_args ) {
		$uuid  = $args[0];
		$scope = Scope_Store::get_by_uuid( $uuid );

		if ( null === $scope ) {
			\WP_CLI::error( 'No scoped credential found for that uuid.' );
		}

		Scope_Store::revoke( $uuid );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'delete', false ) ) {
			\WP_Application_Passwords::delete_application_password( (int) $scope['user_id'], $uuid );
			Scope_Store::delete( $uuid );
			\WP_CLI::success( 'Credential revoked and deleted.' );
			return;
		}

		\WP_CLI::success( 'Credential revoked.' );
	}

	/**
	 * Lists scoped credentials.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<user>]
	 * : Limit to one user (ID, login, or email).
	 *
	 * [--format=<format>]
	 * : Output format. Default table. Also accepts csv, json, yaml, count.
	 *
	 * @subcommand list-credentials
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_credentials( $args, $assoc_args ) {
		$user_id = null;

		$user_flag = \WP_CLI\Utils\get_flag_value( $assoc_args, 'user' );

		if ( ! empty( $user_flag ) ) {
			$user = self::find_user( $user_flag );

			if ( ! $user ) {
				\WP_CLI::error( 'No such user.' );
			}

			$user_id = $user->ID;
		}

		$rows = Scope_Store::list_all( $user_id );

		foreach ( $rows as &$row ) {
			$row['abilities']  = null === $row['abilities'] ? '(unrestricted)' : implode( ',', $row['abilities'] );
			$row['post_types'] = null === $row['post_types'] ? '(unrestricted)' : implode( ',', $row['post_types'] );
			$row['status']     = self::status_label( $row );
		}

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'uuid', 'user_id', 'name', 'abilities', 'post_types', 'allow_xmlrpc', 'expires_at', 'status' )
		);
	}

	/**
	 * Human status for a scope row.
	 *
	 * @param array $row Row from Scope_Store, with decoded lists.
	 */
	private static function status_label( array $row ): string {
		if ( Scope_Store::is_revoked( $row ) ) {
			return 'revoked';
		}

		if ( Scope_Store::is_expired( $row ) ) {
			return 'expired';
		}

		return 'active';
	}

	/**
	 * Resolves a user by ID, login, or email.
	 *
	 * @param string $identifier User ID, login, or email.
	 */
	private static function find_user( string $identifier ): ?\WP_User {
		if ( is_numeric( $identifier ) ) {
			$user = get_user_by( 'id', (int) $identifier );
		} elseif ( is_email( $identifier ) ) {
			$user = get_user_by( 'email', $identifier );
		} else {
			$user = get_user_by( 'login', $identifier );
		}

		return $user ? $user : null;
	}

	/**
	 * Reads a comma-separated flag into a list, or null if omitted.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $flag       Flag name.
	 */
	private static function collect_list( array $assoc_args, string $flag ): ?array {
		if ( ! array_key_exists( $flag, $assoc_args ) ) {
			return null;
		}

		$raw = (string) $assoc_args[ $flag ];

		if ( '' === trim( $raw ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', $raw ) );
	}

	/**
	 * Parses --expires into a MySQL UTC datetime, or null if omitted.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @return string|null|false Datetime string, null, or false on a parse failure.
	 */
	private static function collect_expiry( array $assoc_args ) {
		$expires_flag = \WP_CLI\Utils\get_flag_value( $assoc_args, 'expires' );

		if ( empty( $expires_flag ) ) {
			return null;
		}

		$timestamp = strtotime( $expires_flag );

		if ( false === $timestamp ) {
			return false;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
