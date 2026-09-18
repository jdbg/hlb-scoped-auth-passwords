<?php
/**
 * CRUD for credential scope rows.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes rows in the companion scope table.
 *
 * A null `abilities` or `post_types` value means that dimension is
 * unrestricted for the credential. An empty array means the dimension
 * is restricted and nothing in it is allowed.
 */
class Scope_Store {

	/**
	 * Creates a scope row for an already-issued Application Password.
	 *
	 * @param int         $user_id      Owning user ID.
	 * @param string      $uuid         The Application Password uuid.
	 * @param string      $name         Label, copied from the password's name.
	 * @param array|null  $abilities    Allowed ability names, or null for unrestricted.
	 * @param array|null  $post_types   Allowed post types, or null for unrestricted.
	 * @param bool        $allow_xmlrpc Whether this credential may authenticate over XML-RPC.
	 * @param string|null $expires_at   MySQL datetime (UTC), or null for no expiry.
	 */
	public static function create(
		int $user_id,
		string $uuid,
		string $name,
		?array $abilities,
		?array $post_types,
		bool $allow_xmlrpc,
		?string $expires_at
	): bool {
		global $wpdb;

		$inserted = $wpdb->insert(
			Schema::table_name(),
			array(
				'user_id'      => $user_id,
				'uuid'         => $uuid,
				'name'         => $name,
				'abilities'    => self::encode_list( $abilities ),
				'post_types'   => self::encode_list( $post_types ),
				'allow_xmlrpc' => $allow_xmlrpc ? 1 : 0,
				'expires_at'   => $expires_at,
				'revoked_at'   => null,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Fetches a scope row by the credential's uuid.
	 *
	 * @param string $uuid The Application Password uuid.
	 * @return array|null Null when the credential isn't scoped by this plugin.
	 */
	public static function get_by_uuid( string $uuid ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE uuid = %s',
				Schema::table_name(),
				$uuid
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['abilities']  = self::decode_list( $row['abilities'] );
		$row['post_types'] = self::decode_list( $row['post_types'] );

		return $row;
	}

	/**
	 * Lists scope rows, optionally limited to one user.
	 *
	 * @param int|null $user_id Owning user ID, or null for every user.
	 */
	public static function list_all( ?int $user_id = null ): array {
		global $wpdb;

		$table = Schema::table_name();

		if ( null !== $user_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC', $table, $user_id ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $table ),
				ARRAY_A
			);
		}

		foreach ( $rows as &$row ) {
			$row['abilities']  = self::decode_list( $row['abilities'] );
			$row['post_types'] = self::decode_list( $row['post_types'] );
		}

		return $rows;
	}

	/**
	 * Marks a credential revoked. Takes effect on its next use.
	 *
	 * @param string $uuid The Application Password uuid.
	 */
	public static function revoke( string $uuid ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			Schema::table_name(),
			array( 'revoked_at' => current_time( 'mysql', true ) ),
			array( 'uuid' => $uuid ),
			array( '%s' ),
			array( '%s' )
		);

		return false !== $updated;
	}

	/**
	 * Deletes a scope row outright.
	 *
	 * @param string $uuid The Application Password uuid.
	 */
	public static function delete( string $uuid ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( Schema::table_name(), array( 'uuid' => $uuid ), array( '%s' ) );

		return false !== $deleted;
	}

	/**
	 * Whether a scope row is past its expiry.
	 *
	 * @param array $scope Row from get_by_uuid()/list_all().
	 */
	public static function is_expired( array $scope ): bool {
		if ( empty( $scope['expires_at'] ) ) {
			return false;
		}

		return strtotime( $scope['expires_at'] . ' UTC' ) < time();
	}

	/**
	 * Whether a scope row has been revoked.
	 *
	 * @param array $scope Row from get_by_uuid()/list_all().
	 */
	public static function is_revoked( array $scope ): bool {
		return ! empty( $scope['revoked_at'] );
	}

	/**
	 * Encodes a nullable list for storage. Null stays null (unrestricted).
	 *
	 * @param array|null $items Ability names or post types.
	 */
	private static function encode_list( ?array $items ): ?string {
		if ( null === $items ) {
			return null;
		}

		return wp_json_encode( array_values( $items ) );
	}

	/**
	 * Decodes a stored list back to null or an array.
	 *
	 * @param string|null $json Column value.
	 */
	private static function decode_list( $json ): ?array {
		if ( null === $json ) {
			return null;
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
