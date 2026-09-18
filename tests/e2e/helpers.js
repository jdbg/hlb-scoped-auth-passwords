const fs = require( 'node:fs' );
const path = require( 'node:path' );

const BASE_URL_FILE = path.resolve( __dirname, '..', '..', '.wp-base-url' );

let cachedBase;

/**
 * Playwright's `use.baseURL` is captured when playwright.config.js is first
 * loaded, which happens before global-setup.js knows the server's URL - so
 * it's frozen as undefined for the plain @playwright/test `request` fixture.
 * Worse, process.env.WP_BASE_URL set later by globalSetup isn't visible
 * inside worker processes either (confirmed empirically: every spec's first
 * request failed with "Invalid URL" even after switching to it). So the
 * fallback is a file globalSetup writes, read directly here.
 *
 * process.env.WP_BASE_URL still wins when set beforehand, for the
 * externally-managed-Playground case where globalSetup no-ops entirely.
 *
 * @param {string} pathname Path starting with "/".
 */
function url( pathname ) {
	if ( ! cachedBase ) {
		cachedBase = process.env.WP_BASE_URL || fs.readFileSync( BASE_URL_FILE, 'utf8' ).trim();
	}

	return `${ cachedBase }${ pathname }`;
}

/**
 * Loads the fixture credentials provisioned by
 * tests/e2e/fixtures/provision.php at Playground boot.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @return {Promise<Record<string, {login: string, password: string, uuid: string}>>}
 */
async function loadFixtures( request ) {
	const response = await request.get( url( '/wp-content/uploads/test-fixtures.json' ) );

	if ( ! response.ok() ) {
		throw new Error(
			`Could not load test-fixtures.json (status ${ response.status() }). Did provision.php run?`
		);
	}

	return response.json();
}

/**
 * Basic Auth header for a fixture credential.
 *
 * @param {{login: string, password: string}} fixture
 */
function authHeader( fixture ) {
	const token = Buffer.from( `${ fixture.login }:${ fixture.password }` ).toString( 'base64' );
	return { Authorization: `Basic ${ token }` };
}

module.exports = { loadFixtures, authHeader, url };
