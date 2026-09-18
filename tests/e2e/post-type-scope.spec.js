const { test, expect } = require( '@playwright/test' );
const { loadFixtures, authHeader, url } = require( './helpers' );

let fixtures;

test.beforeAll( async ( { request } ) => {
	fixtures = await loadFixtures( request );
} );

test.describe( 'Dispatch-time enforcement: post-type scope (rest_request_before_callbacks)', () => {
	test( 'a credential scoped to "post" can list posts', async ( { request } ) => {
		const response = await request.get( url( '/wp-json/wp/v2/posts' ), {
			headers: authHeader( fixtures.post_scoped ),
		} );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'a credential scoped to "post" is rejected on /wp/v2/pages', async ( { request } ) => {
		const response = await request.get( url( '/wp-json/wp/v2/pages' ), {
			headers: authHeader( fixtures.post_scoped ),
		} );
		expect( response.status() ).toBe( 403 );

		const body = await response.json();
		expect( body.code ).toBe( 'hlb_sap_out_of_scope' );
	} );

	test( 'a credential with unrestricted post-type scope can reach /wp/v2/pages', async ( { request } ) => {
		const response = await request.get( url( '/wp-json/wp/v2/pages' ), {
			headers: authHeader( fixtures.unrestricted ),
		} );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'post-type scope does not restrict non-post-type routes (documented boundary)', async ( { request } ) => {
		const response = await request.get( url( '/wp-json/wp/v2/users/me' ), {
			headers: authHeader( fixtures.post_scoped ),
		} );
		expect( response.status() ).toBe( 200 );
	} );
} );
