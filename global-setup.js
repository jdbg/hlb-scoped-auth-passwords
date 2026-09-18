const { runCLI } = require( '@wp-playground/cli' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const crypto = require( 'node:crypto' );

/**
 * Deterministic port from cwd hash so each git worktree gets its own
 * Playground instance. Override with WP_PLAYGROUND_PORT (e.g., in CI).
 * Range: 9400-9499.
 */
function resolvePort() {
	if ( process.env.WP_PLAYGROUND_PORT ) {
		return Number( process.env.WP_PLAYGROUND_PORT );
	}
	const hash = crypto
		.createHash( 'sha1' )
		.update( process.cwd() )
		.digest();
	return 9400 + ( hash.readUInt16BE( 0 ) % 100 );
}

/**
 * Where the resolved server URL is handed off to test/worker processes.
 * Playwright's workers do not reliably inherit env vars set by globalSetup
 * (process.env.WP_BASE_URL set here is invisible inside worker processes,
 * confirmed empirically - "Invalid URL" from every spec's first request),
 * so the URL is written to a file instead. See tests/e2e/helpers.js.
 */
const BASE_URL_FILE = path.resolve( __dirname, '.wp-base-url' );

/**
 * Playwright globalSetup: boots a single Playground instance for the whole
 * test run and writes its URL to both process.env.WP_BASE_URL (for anything
 * running in this same process) and BASE_URL_FILE (for worker processes).
 *
 * No-ops when WP_BASE_URL is already set, so an externally-managed Playground
 * (CI matrix server, local `npm run playground:start`) is not double-booted.
 *
 * Blueprint is read from ./blueprint.json in the project root. To use a
 * different blueprint, set WP_BLUEPRINT_PATH to an absolute path.
 */
module.exports = async () => {
	if ( process.env.WP_BASE_URL ) return;

	const blueprintPath = process.env.WP_BLUEPRINT_PATH
		? path.resolve( process.env.WP_BLUEPRINT_PATH )
		: path.resolve( process.cwd(), 'blueprint.json' );
	const blueprint = JSON.parse( fs.readFileSync( blueprintPath, 'utf8' ) );
	const port = resolvePort();

	const cli = await runCLI( {
		command: 'server',
		port,
		php: process.env.WP_PLAYGROUND_PHP || undefined,
		wp: process.env.WP_PLAYGROUND_WP || undefined,
		mount: [
			{
				hostPath: process.cwd(),
				vfsPath: '/wordpress/wp-content/plugins/hlb-scoped-auth-passwords',
			},
		],
		blueprint,
	} );

	// RunCLIServer has no serverUrl property (checked node_modules/@wp-playground/cli/run-cli.d.ts:
	// it exposes `server`, a plain Node http.Server, and `playground`). Read the
	// actual bound port from the server itself rather than assuming the
	// requested port was honored.
	const address = cli.server.address();
	const actualPort = address && typeof address === 'object' ? address.port : port;
	const serverUrl = `http://127.0.0.1:${ actualPort }`;

	process.env.WP_BASE_URL = serverUrl;
	fs.writeFileSync( BASE_URL_FILE, serverUrl );
	globalThis.__wpPlayground = cli;
};
