/**
 * E2E regression tests for connector model discovery behaviour.
 *
 * Run: npx cypress run --spec tests/e2e/model-prefetch.cy.js
 */

/**
 * Visit the Connectors page exposed by WordPress core or Gutenberg.
 *
 * WordPress 6.9 does not expose this page unless another plugin supplies the
 * AI Client SDK. Skip the browser scenario when neither supported route is
 * registered; the plugin activation suite still covers that compatibility
 * mode.
 *
 * @param {Mocha.Context} testContext Current test context.
 */
function visitConnectorsPageOrSkip( testContext ) {
	const paths = [
		'/wp-admin/options-connectors.php',
		'/wp-admin/options-general.php?page=options-connectors-wp-admin',
	];

	const tryPath = ( index ) => cy.request( {
		url: paths[ index ],
		failOnStatusCode: false,
	} ).then( ( response ) => {
		if ( response.status === 200 ) {
			cy.visit( paths[ index ] );
			return;
		}
		if ( index + 1 < paths.length ) {
			return tryPath( index + 1 );
		}
		testContext.skip();
	} );

	return tryPath( 0 );
}

describe( 'Model discovery regression', () => {
	it( 'includes the canonical Mammouth.ai preset', () => {
		cy.readFile( 'src/provider-presets.js' ).then( ( source ) => {
			expect( source ).to.contain( '"name": "Mammouth.ai"' );
			expect( source ).to.contain( '"url": "https://api.mammouth.ai/v1"' );
		} );
	} );

	it( 'loads models with saved credentials and canonical server settings', function () {
		let savedProviderId = '';

		cy.wpLogin();
		cy.intercept( {
			method: 'GET',
			pathname: '/index.php',
			query: { rest_route: '/wp/v2/settings' },
		}, {
			ultimate_ai_connector_providers: [],
			ultimate_ai_connector_provider_order: [],
		} );
		cy.intercept( {
			method: 'POST',
			pathname: '/index.php',
			query: { rest_route: '/wp/v2/settings' },
		}, ( request ) => {
			const provider = request.body.ultimate_ai_connector_providers[ 0 ];
			savedProviderId = provider.id;
			request.reply( {
				ultimate_ai_connector_providers: [ {
					...provider,
					name: 'Server-sanitized Mammouth',
					endpoint_url: 'https://api.mammouth.ai/v1',
				} ],
				ultimate_ai_connector_provider_order: [ provider.id ],
			} );
		} ).as( 'saveProviders' );
		cy.intercept( {
			method: 'GET',
			pathname: '/index.php',
			query: { rest_route: '/ultimate-ai-connector-compatible-endpoints/v1/models' },
		}, ( request ) => {
			request.reply( [ { id: 'authenticated-model', name: 'Authenticated model' } ] );
		} ).as( 'savedModels' );

		visitConnectorsPageOrSkip( this );
		cy.get( '.connector-item--ultimate-ai-connector-compatible-endpoints' ).within( () => {
			cy.contains( 'button', 'Set up' ).click();
			cy.contains( 'button', 'Add provider' ).click();
			cy.contains( 'label', 'Endpoint URL' ).invoke( 'attr', 'for' ).then( ( id ) => {
				cy.get( '#' + id ).type( 'https://api.mammouth.ai/v1' );
			} );
			cy.get( 'input[type="password"]' ).type( 'test-only-key' );
			cy.contains( 'button', /^Save$/ ).click();
		} );

		cy.wait( '@saveProviders' );
		cy.wait( '@savedModels' ).then( ( interception ) => {
			expect( interception.request.query ).not.to.have.property( 'api_key' );
			expect( interception.request.query.config_id ).to.equal( savedProviderId );
			expect( interception.request.query.endpoint_url ).to.equal( 'https://api.mammouth.ai/v1' );
		} );
		cy.get( '.connector-item--ultimate-ai-connector-compatible-endpoints' ).within( () => {
			cy.contains( 'button', 'Manage' ).click();
			cy.contains( 'Server-sanitized Mammouth' ).should( 'exist' );
			cy.contains( 'button', 'Expand' ).click();
			cy.contains( '1 model loaded.' ).should( 'exist' );
			cy.contains( 'label', 'Default Model' ).invoke( 'attr', 'for' ).then( ( id ) => {
				cy.get( '#' + id )
					.find( 'option[value="authenticated-model"]' )
					.should( 'exist' );
			} );
		} );
	} );
} );
