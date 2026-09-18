const { test, expect } = require( '@playwright/test' );
const { loadFixtures, authHeader, url } = require( './helpers' );

/**
 * Scoped against core's own built-in abilities rather than a custom one:
 * abilities registered by a mu-plugin's own wp_abilities_api_init callback
 * don't reliably fire in this Playground/WP build (confirmed with an
 * in-request diagnostic route: has_action() true, did_action() stays 0 for
 * the whole request), so core abilities are what this environment can be
 * trusted to expose. Both are annotated readonly, so they run over GET.
 */
const GRANTED_ABILITY_ROUTE = '/wp-json/wp-abilities/v1/abilities/core/get-site-info/run';
const OTHER_ABILITY_ROUTE = '/wp-json/wp-abilities/v1/abilities/core/get-user-info/run';

let fixtures;

test.beforeAll( async ( { request } ) => {
	fixtures = await loadFixtures( request );
} );

test.describe( 'Dispatch-time enforcement: ability scope (rest_request_before_callbacks)', () => {
	test( 'a credential scoped to an ability can run it', async ( { request } ) => {
		const response = await request.get( url( GRANTED_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.ability_scoped ),
		} );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'a credential scoped to an ability is rejected running a different one', async ( { request } ) => {
		const response = await request.get( url( OTHER_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.ability_scoped ),
		} );
		expect( response.status() ).toBe( 403 );

		const body = await response.json();
		expect( body.code ).toBe( 'hlb_sap_out_of_scope' );
	} );

	test( 'a credential with unrestricted ability scope can run either ability', async ( { request } ) => {
		const granted = await request.get( url( GRANTED_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.unrestricted ),
		} );
		const other = await request.get( url( OTHER_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.unrestricted ),
		} );

		expect( granted.status() ).toBe( 200 );
		expect( other.status() ).toBe( 200 );
	} );
} );
