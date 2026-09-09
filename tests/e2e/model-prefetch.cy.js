/**
 * E2E regression tests for connector model discovery behaviour.
 *
 * Run: npx cypress run --spec tests/e2e/model-prefetch.cy.js
 */

describe( 'Model discovery regression', () => {
	it( 'uses deliberate loading with cache invalidation and visible states', () => {
		cy.readFile( 'src/index.jsx' ).then( ( source ) => {
			expect( source ).to.contain( 'modelRequestsRef.current.has( cacheKey )' );
			expect( source ).to.contain( 'invalidateModelsForProvider' );
			expect( source ).to.contain( 'Save this provider before loading models' );
			expect( source ).to.contain( 'Retry loading models' );
			expect( source ).not.to.contain( 'Fetch models for all providers once initial settings are loaded.' );
		} );
	} );
} );
