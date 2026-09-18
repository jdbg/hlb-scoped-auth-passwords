const { test, expect } = require( '@playwright/test' );
const { loadFixtures, authHeader, url } = require( './helpers' );

/**
 * XML-RPC has no HTTP-level status codes for auth failure - every call
 * returns 200 with a <fault> body on rejection. wp.getUsersBlogs is a
 * minimal method that requires nothing but valid credentials, which
 * makes it a clean probe for auth success/failure alone.
 */
function xmlrpcEnvelope( username, password ) {
	return `<?xml version="1.0"?>
<methodCall>
	<methodName>wp.getUsersBlogs</methodName>
	<params>
		<param><value><string>${ username }</string></value></param>
		<param><value><string>${ password }</string></value></param>
	</params>
</methodCall>`;
}

async function callXmlRpc( request, username, password ) {
	const response = await request.post( url( '/xmlrpc.php' ), {
		headers: { 'Content-Type': 'text/xml' },
		data: xmlrpcEnvelope( username, password ),
	} );

	const body = await response.text();

	return { response, isFault: body.includes( '<fault>' ) };
}

let fixtures;

test.beforeAll( async ( { request } ) => {
	fixtures = await loadFixtures( request );
} );

test.describe( 'Auth-time enforcement: XML-RPC gating', () => {
	test( 'a credential without allow_xmlrpc is rejected over XML-RPC', async ( { request } ) => {
		const { isFault } = await callXmlRpc(
			request,
			fixtures.xmlrpc_denied.login,
			fixtures.xmlrpc_denied.password
		);
		expect( isFault ).toBe( true );
	} );

	test( 'a credential without allow_xmlrpc still authenticates over REST', async ( { request } ) => {
		const response = await request.get( url( '/wp-json/wp/v2/users/me' ), {
			headers: authHeader( fixtures.xmlrpc_denied ),
		} );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'a credential with allow_xmlrpc authenticates over XML-RPC', async ( { request } ) => {
		const { isFault } = await callXmlRpc(
			request,
			fixtures.xmlrpc_allowed.login,
			fixtures.xmlrpc_allowed.password
		);
		expect( isFault ).toBe( false );
	} );
} );
