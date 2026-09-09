/**
 * E2E regression tests for connector model prefetch behaviour.
 *
 * Run: npx cypress run --spec tests/e2e/model-prefetch.cy.js
 */

describe( 'Model prefetch regression', () => {
	it( 'reruns model prefetch after provider list changes', () => {
		cy.readFile( 'src/index.jsx' ).then( ( source ) => {
			expect( source ).to.contain( '// Fetch models for all providers once initial settings are loaded.' );
			expect( source ).to.contain( '}, [ isLoading, providers ] );' );
		} );
	} );

	it( 'fetches models with saved credentials without reloading the page', () => {
		let saved = false;
		cy.wpLogin();
		cy.intercept( 'GET', '**/wp/v2/settings?_fields=ultimate_ai_connector_providers*', {
			ultimate_ai_connector_providers: [],
			ultimate_ai_connector_provider_order: [],
		} );
		cy.intercept( 'POST', '**/wp/v2/settings*', ( request ) => {
			saved = true;
			request.reply( request.body );
		} ).as( 'saveProviders' );
		cy.intercept( 'GET', '**/ultimate-ai-connector-compatible-endpoints/v1/models*', ( request ) => {
			expect( request.query ).not.to.have.property( 'api_key' );
			if ( ! saved ) {
				request.reply( {
					statusCode: 502,
					body: { code: 'upstream_error', message: 'Upstream returned HTTP 401.' },
				} );
				return;
			}
			request.alias = 'savedModels';
			request.reply( [ { id: 'authenticated-model', name: 'Authenticated model' } ] );
		} );
		cy.visit( '/wp-admin/options-connectors.php' );
		cy.contains( 'button', 'Set up' ).click();
		cy.contains( 'button', '+ Add provider' ).click();
		cy.get( '.connector-settings' ).within( () => {
			cy.contains( 'label', 'Endpoint URL' ).invoke( 'attr', 'for' ).then( ( id ) => {
				cy.get( '#' + id ).type( 'https://authenticated.example.test/v1' );
			} );
			cy.get( 'input[type="password"]' ).type( 'test-only-key' );
			cy.contains( 'button', /^Save$/ ).click();
		} );
		cy.wait( '@saveProviders' );
		cy.wait( '@savedModels' );
		cy.contains( 'button', 'Manage' ).click();
		cy.contains( 'button', 'Expand' ).click();
		cy.get( '.connector-settings select' ).first()
			.find( 'option[value="authenticated-model"]' ).should( 'exist' );
	} );
} );
