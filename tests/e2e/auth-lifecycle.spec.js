const { test, expect } = require( '@playwright/test' );
const { loadFixtures, authHeader, url } = require( './helpers' );

/**
 * /wp/v2/users/me requires authentication (401 anonymous, 200 logged in),
 * unlike /wp/v2/posts which is publicly readable regardless of auth. A
 * rejected credential doesn't produce its own error response - core's
 * wp_validate_application_password() just falls back to "no user", the
 * same as sending no credentials at all - so the route under test has to
 * be one where that distinction is visible.
 */
const ME_ROUTE = '/wp-json/wp/v2/users/me';

let fixtures;

test.beforeAll( async ( { request } ) => {
	fixtures = await loadFixtures( request );
} );

test.describe( 'Auth-time enforcement (wp_authenticate_application_password_errors)', () => {
	test( 'an ordinary, unscoped Application Password authenticates normally', async ( { request } ) => {
		const response = await request.get( url( ME_ROUTE ), { headers: authHeader( fixtures.unscoped ) } );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'a scoped credential with no restrictions authenticates normally', async ( { request } ) => {
		const response = await request.get( url( ME_ROUTE ), { headers: authHeader( fixtures.unrestricted ) } );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'a revoked credential is rejected', async ( { request } ) => {
		const response = await request.get( url( ME_ROUTE ), { headers: authHeader( fixtures.revoked ) } );
		expect( response.status() ).toBe( 401 );
	} );

	test( 'an expired credential is rejected', async ( { request } ) => {
		const response = await request.get( url( ME_ROUTE ), { headers: authHeader( fixtures.expired ) } );
		expect( response.status() ).toBe( 401 );
	} );

	test( 'a wrong password for a scoped credential is still just rejected', async ( { request } ) => {
		const response = await request.get( url( ME_ROUTE ), {
			headers: authHeader( { login: fixtures.post_scoped.login, password: 'not-the-right-password' } ),
		} );
		expect( response.status() ).toBe( 401 );
	} );
} );
