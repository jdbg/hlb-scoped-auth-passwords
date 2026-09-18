const { test, expect } = require( '@playwright/test' );
const { loadFixtures, authHeader, url } = require( './helpers' );

/**
 * Granted ability is core/get-site-info: it's readonly-annotated (GET) and
 * confirmed present on every WP version this plugin supports (6.9+).
 * core/get-user-info, tried here first, turned out not to exist on real
 * WP 6.9 (only added in a later core release) - a WP-version gap, not a
 * plugin bug, but it means "some other real ability" isn't a safe fixture
 * across the version matrix.
 *
 * The "other" ability is fictional on purpose. Scope_Guard runs on
 * rest_request_before_callbacks, before core's own controller checks
 * whether the ability exists, so it denies by name alone - testing
 * rejection doesn't need a second real ability, and a fictional one is
 * portable across every WP version without relying on which core
 * abilities happen to ship in it.
 */
const GRANTED_ABILITY_ROUTE = '/wp-json/wp-abilities/v1/abilities/core/get-site-info/run';
const FICTIONAL_ABILITY_ROUTE = '/wp-json/wp-abilities/v1/abilities/hlb-sap-fixtures/not-a-real-ability/run';

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
		const response = await request.get( url( FICTIONAL_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.ability_scoped ),
		} );
		expect( response.status() ).toBe( 403 );

		const body = await response.json();
		expect( body.code ).toBe( 'hlb_sap_out_of_scope' );
	} );

	test( 'a credential with unrestricted ability scope can run a real ability', async ( { request } ) => {
		const response = await request.get( url( GRANTED_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.unrestricted ),
		} );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'unrestricted ability scope never produces the plugin\'s own rejection', async ( { request } ) => {
		const response = await request.get( url( FICTIONAL_ABILITY_ROUTE ), {
			headers: authHeader( fixtures.unrestricted ),
		} );
		// Core legitimately 404s a nonexistent ability. The point here is
		// narrower: Scope_Guard must not be the one blocking it.
		expect( response.status() ).not.toBe( 403 );

		const body = await response.json();
		expect( body.code ).not.toBe( 'hlb_sap_out_of_scope' );
	} );
} );
