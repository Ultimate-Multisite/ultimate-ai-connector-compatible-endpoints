/**
 * Ultimate AI Connector for Compatible Endpoints — Connectors page integration.
 *
 * Registers a card on Settings > Connectors that lets users configure
 * multiple compatible AI endpoints with fallback routing.
 *
 * Compatible with WordPress 7.0+ (Script Modules API).
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const { createElement, useState, useEffect, useCallback, useRef } = wp.element;
const {
	Button,
	TextControl,
	SelectControl,
	Spinner,
	__experimentalNumberControl: NumberControl,
	__experimentalHStack: HStack,
	__experimentalVStack: VStack,
	Card,
	CardBody,
	CardHeader,
	CardDivider,
	CheckboxControl,
} = wp.components;
const { __ } = wp.i18n;
const apiFetch = wp.apiFetch;

/**
 * Generate a unique ID for a provider.
 */
function generateProviderId() {
	return 'provider_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).substr( 2, 5 );
}

/**
 * "ANY LLM" text icon used as the connector logo.
 */
function Logo() {
	return (
		<svg
			width={ 40 }
			height={ 40 }
			viewBox="0 0 40 40"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect width="40" height="40" rx="8" fill="#1a1a2e" />
			<text
				x="20"
				y="14"
				textAnchor="middle"
				fontFamily="system-ui, -apple-system, sans-serif"
				fontSize="9"
				fontWeight="700"
				fill="#a78bfa"
			>
				ANY
			</text>
			<text
				x="20"
				y="29"
				textAnchor="middle"
				fontFamily="system-ui, -apple-system, sans-serif"
				fontSize="13"
				fontWeight="800"
				fill="#60a5fa"
			>
				LLM
			</text>
		</svg>
	);
}

/**
 * Green "Connected" badge — matches the built-in connectors styling.
 */
function ConnectedBadge() {
	return (
		<span
			style={ {
				color: '#345b37',
				backgroundColor: '#eff8f0',
				padding: '4px 12px',
				borderRadius: '2px',
				fontSize: '13px',
				fontWeight: 500,
				whiteSpace: 'nowrap',
			} }
		>
			{ __( 'Connected' ) }
		</span>
	);
}

/**
 * Sortable provider card for the list.
 *
 * Each card owns its own expanded/collapsed state. The parent passes
 * `initialExpanded` only at mount time; subsequent renders do not reset it.
 */
function ProviderCard( {
	provider,
	initialExpanded = false,
	onUpdate,
	onRemove,
	isSaving,
	saveError,
	models = [],
	isLoadingModels,
} ) {
	const [ isExpanded, setIsExpanded ] = useState( initialExpanded );
	const [ name, setName ] = useState( provider.name || '' );
	const [ endpointUrl, setEndpointUrl ] = useState( provider.endpoint_url || '' );
	const [ apiKey, setApiKey ] = useState( provider.api_key || '' );
	const [ defaultModel, setDefaultModel ] = useState( provider.default_model || '' );
	const [ timeout, setTimeout ] = useState( provider.timeout ?? 360 );
	const [ enabled, setEnabled ] = useState( provider.enabled ?? true );
	const modelsFetchedRef = useRef( '' );

	// Sync with provider prop.
	useEffect( () => {
		setName( provider.name || '' );
		setEndpointUrl( provider.endpoint_url || '' );
		setApiKey( provider.api_key || '' );
		setDefaultModel( provider.default_model || '' );
		setTimeout( provider.timeout ?? 360 );
		setEnabled( provider.enabled ?? true );
	}, [ provider ] );

	const modelOptions = [
		{ label: __( 'Auto-select (SDK chooses)' ), value: '' },
		...( models || [] ).map( ( m ) => ( {
			label: m.name || m.id,
			value: m.id,
		} ) ),
	];

	const handleChange = ( key, value ) => {
		onUpdate( {
			...provider,
			[ key ]: value,
		} );
	};

	return (
		<Card size="small">
			<CardHeader>
				<HStack expanded={ false }>
					<span
						style={ {
							cursor: 'grab',
							color: '#888',
							fontSize: '16px',
							lineHeight: 1,
							userSelect: 'none',
							padding: '0 4px',
						} }
						title={ __( 'Drag to reorder' ) }
					>
						&#x2630;
					</span>
					<span style={ { flex: 1, fontWeight: 500 } }>
						{ name || endpointUrl || __( 'New provider' ) }
					</span>
					<CheckboxControl
						label={ __( 'Enabled' ) }
						checked={ enabled }
						onChange={ ( value ) => handleChange( 'enabled', value ) }
						disabled={ isSaving }
					/>
					<Button
						variant="tertiary"
						size="small"
						onClick={ () => setIsExpanded( ( v ) => ! v ) }
					>
						{ isExpanded ? __( 'Collapse' ) : __( 'Expand' ) }
					</Button>
				</HStack>
			</CardHeader>
			{ isExpanded && (
				<>
					<CardDivider />
					<CardBody>
						<VStack spacing={ 3 }>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Name' ) }
								value={ name }
								onChange={ ( value ) => {
									setName( value );
									handleChange( 'name', value );
								} }
								placeholder={ __( 'My Ollama Server' ) }
								disabled={ isSaving }
							/>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Endpoint URL' ) }
								value={ endpointUrl }
								onChange={ ( value ) => {
									setEndpointUrl( value );
									handleChange( 'endpoint_url', value );
								} }
								placeholder="http://localhost:11434/v1"
								disabled={ isSaving }
								help={ __(
									'Base URL (e.g. Ollama, LM Studio, OpenRouter)'
								) }
							/>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'API Key' ) }
								type="password"
								value={ apiKey }
								onChange={ ( value ) => {
									setApiKey( value );
									handleChange( 'api_key', value );
								} }
								placeholder="sk-..."
								disabled={ isSaving }
								help={ __(
									'Optional. Leave blank for servers without auth.'
								) }
							/>
							{ isLoadingModels ? (
								<HStack spacing={ 2 } expanded={ false }>
									<Spinner />
									<span>{ __( 'Loading models\u2026' ) }</span>
								</HStack>
							) : (
								<SelectControl
									__nextHasNoMarginBottom
									label={ __( 'Default Model' ) }
									value={ defaultModel }
									options={ modelOptions }
									onChange={ ( value ) => {
										setDefaultModel( value );
										handleChange( 'default_model', value );
									} }
									disabled={ isSaving }
								/>
							) }
							<NumberControl
								__next40pxDefaultSize
								label={ __( 'Timeout (seconds)' ) }
								value={ timeout }
								onChange={ ( value ) => {
									setTimeout( parseInt( value, 10 ) || 360 );
									handleChange( 'timeout', parseInt( value, 10 ) || 360 );
								} }
								min={ 10 }
								max={ 600 }
								step={ 10 }
								disabled={ isSaving }
							/>
							{ saveError && (
								<span style={ { color: '#cc1818' } }>
									{ saveError }
								</span>
							) }
							<Button
								variant="link"
								isDestructive
								onClick={ onRemove }
								disabled={ isSaving }
							>
								{ __( 'Remove this provider' ) }
							</Button>
						</VStack>
					</CardBody>
				</>
			) }
		</Card>
	);
}

/**
 * Main connector card component rendered on the Connectors page.
 */
function CompatibleEndpointConnectorCard( { slug, label, description, logo } ) {
	const [ providers, setProviders ] = useState( [] );
	const [ providerOrder, setProviderOrder ] = useState( [] );
	const [ isExpanded, setIsExpanded ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ saveError, setSaveError ] = useState( null );
	const [ modelsCache, setModelsCache ] = useState( {} );

	const hasProviders = providers.length > 0;

	/**
	 * Fetch providers from settings.
	 */
	const fetchSettings = useCallback( async () => {
		try {
			const settings = await apiFetch( {
				path: '/wp/v2/settings?_fields=ultimate_ai_connector_providers,ultimate_ai_connector_provider_order',
			} );
			const loadedProviders = settings.ultimate_ai_connector_providers || [];
			const loadedOrder = settings.ultimate_ai_connector_provider_order || [];

			// Handle legacy single-provider config for migration.
			const legacyUrl = settings.ultimate_ai_connector_endpoint_url;
			if ( ! loadedProviders.length && legacyUrl ) {
				setProviders( [ {
					id: generateProviderId(),
					name: 'Default',
					endpoint_url: legacyUrl,
					api_key: settings.ultimate_ai_connector_api_key || '',
					default_model: settings.ultimate_ai_connector_default_model || '',
					timeout: settings.ultimate_ai_connector_timeout || 360,
					enabled: true,
				} ] );
			} else {
				setProviders( loadedProviders );
			}

			setProviderOrder( loadedOrder );
		} catch {
			// Silently fail.
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	/**
	 * Fetch models for an endpoint URL.
	 */
	const fetchModelsForUrl = useCallback( async ( url, key ) => {
		if ( ! url || ! key ) {
			return;
		}
		const cacheKey = 'models_' + key;
		if ( modelsCache[ cacheKey ] ) {
			return;
		}
		try {
			const params = new URLSearchParams( { endpoint_url: url } );
			const result = await apiFetch( {
				path: '/ultimate-ai-connector-compatible-endpoints/v1/models?' + params.toString(),
			} );
			setModelsCache( ( prev ) => ( {
				...prev,
				[ cacheKey ]: result,
			} ) );
		} catch {
			// Ignore errors.
		}
	}, [ modelsCache ] );

	/**
	 * Update a provider in the list.
	 */
	const updateProvider = useCallback( ( index, updatedProvider ) => {
		setProviders( ( prev ) => {
			const next = [ ...prev ];
			next[ index ] = {
				...updatedProvider,
				id: updatedProvider.id || generateProviderId(),
			};
			return next;
		} );

		// Fetch models if endpoint changed.
		if ( updatedProvider.endpoint_url ) {
			fetchModelsForUrl( updatedProvider.endpoint_url, updatedProvider.id );
		}
	}, [ fetchModelsForUrl ] );

	/**
	 * Remove a provider.
	 */
	const removeProvider = useCallback( ( index ) => {
		setProviders( ( prev ) => prev.filter( ( _, i ) => i !== index ) );
		setExpandedProviders( ( prev ) => {
			const next = { ...prev };
			delete next[ index ];
			return next;
		} );
	}, [] );

	/**
	 * Add a new provider.
	 */
	const addProvider = useCallback( () => {
		const newProvider = {
			id: generateProviderId(),
			name: '',
			endpoint_url: '',
			api_key: '',
			default_model: '',
			timeout: 360,
			enabled: true,
			_new: true, // signals ProviderCard to start expanded
		};
		setProviders( ( prev ) => [ ...prev, newProvider ] );
	}, [] );

	/**
	 * Move a provider up/down in the list.
	 */
	const moveProvider = useCallback( ( fromIndex, direction ) => {
		setProviders( ( prev ) => {
			const toIndex = direction === 'up' ? fromIndex - 1 : fromIndex + 1;
			if ( toIndex < 0 || toIndex >= prev.length ) {
				return prev;
			}
			const next = prev.slice();
			const temp = next[ fromIndex ];
			next[ fromIndex ] = next[ toIndex ];
			next[ toIndex ] = temp;
			return next;
		} );
	}, [] );

	/**
	 * Save all providers.
	 */
	const handleSave = async () => {
		setSaveError( null );
		setIsSaving( true );
		try {
			// Build order array from provider IDs.
			const order = providers
				.filter( ( p ) => p.enabled )
				.map( ( p ) => p.id );

		// Strip internal-only marker before persisting.
		const providersToSave = providers.map( ( { _new, ...p } ) => p );

		await apiFetch( {
			method: 'POST',
			path: '/wp/v2/settings',
			data: {
				ultimate_ai_connector_providers: providersToSave,
				ultimate_ai_connector_provider_order: order,
			},
		} );
			setIsExpanded( false );
		} catch ( error ) {
			setSaveError(
				error instanceof Error
					? error.message
					: __( 'Failed to save settings.' )
			);
		} finally {
			setIsSaving( false );
		}
	};

	/**
	 * Cancel and reload.
	 */
	const handleCancel = async () => {
		await fetchSettings();
		setIsExpanded( false );
		setSaveError( null );
	};

	const getButtonLabel = () => {
		if ( isLoading ) {
			return __( 'Loading\u2026' );
		}
		if ( isExpanded ) {
			return __( 'Cancel' );
		}
		return hasProviders ? __( 'Manage' ) : __( 'Set up' );
	};

	// Action area: badge + button.
	const actionArea = (
		<HStack spacing={ 3 } expanded={ false }>
			{ hasProviders && ! isExpanded && <ConnectedBadge /> }
			<Button
				variant={ isExpanded || hasProviders ? 'tertiary' : 'secondary' }
				size={ isExpanded || hasProviders ? undefined : 'compact' }
				onClick={ () => setIsExpanded( ! isExpanded ) }
				disabled={ isLoading }
			>
				{ getButtonLabel() }
			</Button>
		</HStack>
	);

	// Settings form.
	const settingsForm = isExpanded ? (
		<VStack spacing={ 4 } className="connector-settings">
			<p style={ { color: '#555', fontSize: '13px' } }>
				{ __(
					'Add multiple providers and drag to reorder. The SDK will try them in order until one succeeds.'
				) }
			</p>

			{ providers.map( ( provider, index ) => (
				<ProviderCard
					key={ provider.id || index }
					provider={ provider }
					initialExpanded={ !! provider._new }
					onUpdate={ ( updated ) => updateProvider( index, updated ) }
					onRemove={ () => removeProvider( index ) }
					isSaving={ isSaving }
					saveError={ null }
					models={ modelsCache[ 'models_' + provider.id ] || [] }
					isLoadingModels={ false }
				/>
			) ) }

			<HStack expanded={ false }>
				<Button
					variant="secondary"
					onClick={ addProvider }
					disabled={ isSaving }
				>
					+ { __( 'Add provider' ) }
				</Button>
			</HStack>

			{ saveError && (
				<span style={ { color: '#cc1818' } }>{ saveError }</span>
			) }

			<HStack justify="flex-start">
				<Button
					variant="primary"
					disabled={ ! providers.length || isSaving }
					accessibleWhenDisabled
					isBusy={ isSaving }
					onClick={ handleSave }
				>
					{ __( 'Save' ) }
				</Button>
				<Button variant="tertiary" onClick={ handleCancel } disabled={ isSaving }>
					{ __( 'Cancel' ) }
				</Button>
			</HStack>
		</VStack>
	) : null;

	return (
		<ConnectorItem
			className="connector-item--ultimate-ai-connector-compatible-endpoints"
			logo={ logo || <Logo /> }
			name={ label }
			description={ description }
			actionArea={ actionArea }
		>
			{ settingsForm }
		</ConnectorItem>
	);
}

// Register the connector card.
// The slug matches the provider ID used in the PHP AI Client registry so that
// this JS registration overrides the auto-discovered entry in WP 7.0+.
const SLUG = 'ultimate-ai-connector-compatible-endpoints';
const CONFIG = {
	label: __( 'Compatible Endpoint' ),
	description: __(
		'Connect to Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.'
	),
	logo: <Logo />,
	render: CompatibleEndpointConnectorCard,
};

// WP core's `routes/connectors-home/content` module runs
// `registerDefaultConnectors()` from inside an async dynamic import. By the
// time it executes, our top-level registerConnector() has already populated
// the store — and the store reducer spreads new config over existing
// entries, so the default's `args.render = ApiKeyConnector` overwrites our
// custom render. The fix in WordPress/gutenberg#77116 will solve this in
// core, but until that lands and ships we re-assert our registration on
// multiple ticks (sync + microtask + setTimeout 0/50/250/1000ms) to
// guarantee we end up last regardless of dynamic-import resolution order.
function registerOurs() {
	registerConnector( SLUG, CONFIG );
}

registerOurs();
Promise.resolve().then( registerOurs );
setTimeout( registerOurs, 0 );
setTimeout( registerOurs, 50 );
setTimeout( registerOurs, 250 );
setTimeout( registerOurs, 1000 );
