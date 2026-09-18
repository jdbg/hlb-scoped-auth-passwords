const fs = require( 'node:fs' );
const path = require( 'node:path' );

/**
 * Playwright globalTeardown: shuts down the Playground instance started by
 * global-setup.js, and removes the URL handoff file so a later run can't
 * accidentally see a stale one before its own globalSetup writes a fresh
 * one. Prefers Symbol.asyncDispose (newer API) with a fallback to
 * server.close() for older @wp-playground/cli versions.
 */
module.exports = async () => {
	fs.rmSync( path.resolve( __dirname, '.wp-base-url' ), { force: true } );

	const cli = globalThis.__wpPlayground;
	if ( ! cli ) return;
	if ( typeof cli[ Symbol.asyncDispose ] === 'function' ) {
		await cli[ Symbol.asyncDispose ]();
	} else {
		await cli.server?.close();
	}
};
