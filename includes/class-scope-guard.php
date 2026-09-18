<?php
/**
 * Dispatch-time enforcement: ability and post-type scope.
 *
 * @package HLB_SAP
 */

namespace HLB_SAP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks rest_request_before_callbacks, which fires for every matched REST
 * route before its own permission_callback runs, and can short-circuit by
 * returning a WP_Error. That covers both regular post-type content routes
 * and the Abilities API's own run route
 * (wp-abilities/v1/abilities/{name}/run) the same way, since both are
 * ordinary REST routes dispatched through the same server.
 *
 * Capability checks the route or ability already performs still run
 * afterwards, unchanged - this only adds a positive-list membership test
 * against what the credential was scoped to.
 */
class Scope_Guard {

	const ABILITY_ROUTE_PATTERN = '#^/wp-abilities/v1/abilities/(?P<name>[a-zA-Z0-9\-/]+)/run$#';

	/**
	 * Registers the dispatch-time check.
	 */
	public static function init(): void {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'enforce' ), 10, 3 );
	}

	/**
	 * Rejects a request outside the authenticated credential's scope.
	 *
	 * @param mixed            $response Current response/error, or null.
	 * @param array            $handler  Matched route handler. Unused; required by the hook signature.
	 * @param \WP_REST_Request $request  The request being dispatched.
	 * @return mixed
	 */
	public static function enforce( $response, $handler, \WP_REST_Request $request ) {
		if ( is_wp_error( $response ) || ! Current_Credential::is_scoped() ) {
			return $response;
		}

		$scope        = Current_Credential::get();
		$route        = $request->get_route();
		$ability_name = self::match_ability_route( $route );

		if ( null !== $ability_name ) {
			return self::check_ability( $scope, $ability_name, $response );
		}

		return self::check_post_type( $scope, $route, $response );
	}

	/**
	 * Extracts the ability name from an Abilities API "run" route.
	 *
	 * @param string $route The matched REST route.
	 */
	private static function match_ability_route( string $route ): ?string {
		if ( preg_match( self::ABILITY_ROUTE_PATTERN, $route, $matches ) ) {
			return rawurldecode( $matches['name'] );
		}

		return null;
	}

	/**
	 * Checks a requested ability against the credential's allow-list.
	 *
	 * @param array  $scope        Row from Scope_Store.
	 * @param string $ability_name The ability the request is invoking.
	 * @param mixed  $response     Current response/error, passed through when allowed.
	 * @return mixed
	 */
	private static function check_ability( array $scope, string $ability_name, $response ) {
		if ( null === $scope['abilities'] || in_array( $ability_name, $scope['abilities'], true ) ) {
			return $response;
		}

		return self::forbidden(
			sprintf(
				/* translators: %s: ability name. */
				__( 'This credential is not scoped to the "%s" ability.', 'hlb-scoped-auth-passwords' ),
				$ability_name
			)
		);
	}

	/**
	 * Checks the post type behind a content route against the
	 * credential's allow-list. Routes that aren't post-type content
	 * routes are left alone; post-type scope only applies to those.
	 *
	 * @param array  $scope    Row from Scope_Store.
	 * @param string $route    The matched REST route.
	 * @param mixed  $response Current response/error, passed through when allowed.
	 * @return mixed
	 */
	private static function check_post_type( array $scope, string $route, $response ) {
		if ( null === $scope['post_types'] ) {
			return $response;
		}

		$post_type = self::resolve_post_type_for_route( $route );

		if ( null === $post_type || in_array( $post_type, $scope['post_types'], true ) ) {
			return $response;
		}

		return self::forbidden(
			sprintf(
				/* translators: %s: post type. */
				__( 'This credential is not scoped to the "%s" post type.', 'hlb-scoped-auth-passwords' ),
				$post_type
			)
		);
	}

	/**
	 * Matches a REST route back to the post type whose controller owns it.
	 *
	 * @param string $route The matched REST route.
	 */
	private static function resolve_post_type_for_route( string $route ): ?string {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			$namespace = ! empty( $post_type->rest_namespace ) ? $post_type->rest_namespace : 'wp/v2';
			$base      = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
			$prefix    = '/' . trim( $namespace, '/' ) . '/' . trim( $base, '/' );

			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return $post_type->name;
			}
		}

		return null;
	}

	/**
	 * Builds the out-of-scope error response.
	 *
	 * @param string $message Human-readable reason.
	 */
	private static function forbidden( string $message ): \WP_Error {
		return new \WP_Error(
			'hlb_sap_out_of_scope',
			$message,
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
