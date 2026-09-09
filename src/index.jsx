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
import PROVIDER_PRESETS from './provider-presets.js';

const { createElement, useState, useEffect, useCallback, useRef } = wp.element;
const {
	Button,
	TextControl,
	SelectControl,
	ComboboxControl,
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
 * Sentinel value used for non-selectable group-header rows in the preset
 * combobox. ComboboxControl has no native optgroup support, so we inject
 * pseudo-options that we ignore in onChange.
 */
const PRESET_HEADER_PREFIX = '__header__';

/**
 * Build the options array for the provider-preset combobox.
 *
 * Returns an array of `{ value, label }` entries with non-selectable
 * `── Local ──` / `── Cloud ──` header rows separating the groups.
 * ComboboxControl has no native optgroup support, so header rows are
 * regular options whose value starts with PRESET_HEADER_PREFIX. We ignore
 * those in onChange (see ProviderCard).
 */
function buildPresetOptions() {
	const options = [];
	let lastGroup = null;
	for ( const preset of PROVIDER_PRESETS ) {
		if ( preset.group !== lastGroup ) {
			const headerLabel = preset.group === 'local'
				? __( '── Local providers ──' )
				: __( '── Cloud providers ──' );
			options.push( {
				value: PRESET_HEADER_PREFIX + preset.group,
				label: headerLabel,
			} );
			lastGroup = preset.group;
		}
		options.push( {
			value: preset.url,
			label: preset.name + ' — ' + preset.url,
		} );
	}
	return options;
}

// Static data — compute once at module load.
const PRESET_OPTIONS = buildPresetOptions();

/**
 * Generate a unique ID for a provider.
 */
function generateProviderId() {
	return 'provider_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).substr( 2, 5 );
}

/**
 * Build a model-cache key scoped to both the provider config and endpoint URL.
 */
function getModelsCacheKey( configId, url ) {
	return 'models_' + configId + '_' + url;
}

/**
 * Capture the saved connection fields that affect server-side discovery.
 */
function getConnectionSignature( provider ) {
	return ( provider.endpoint_url || '' ) + '\n' + ( provider.api_key || '' );
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
	modelState = { status: 'initial' },
	onLoadModels,
	canLoadModels,
	requiresSave,
} ) {
	const [ isExpanded, setIsExpanded ] = useState( initialExpanded );
	const [ name, setName ] = useState( provider.name || '' );
	const [ endpointUrl, setEndpointUrl ] = useState( provider.endpoint_url || '' );
	const [ apiKey, setApiKey ] = useState( provider.api_key || '' );
	const [ defaultModel, setDefaultModel ] = useState( provider.default_model || '' );
	const [ timeout, setTimeout ] = useState( provider.timeout ?? 360 );
	const [ enabled, setEnabled ] = useState( provider.enabled ?? true );
	const [ endpointType, setEndpointType ] = useState( provider.endpoint_type || 'generic' );
	const [ imageProtocol, setImageProtocol ] = useState( provider.image_protocol || 'none' );
	const [ imageModel, setImageModel ] = useState( provider.image_model || '' );
	const modelsFetchedRef = useRef( '' );

	// Sync with provider prop.
	useEffect( () => {
		setName( provider.name || '' );
		setEndpointUrl( provider.endpoint_url || '' );
		setApiKey( provider.api_key || '' );
		setDefaultModel( provider.default_model || '' );
		setTimeout( provider.timeout ?? 360 );
		setEnabled( provider.enabled ?? true );
		setEndpointType( provider.endpoint_type || 'generic' );
		setImageProtocol( provider.image_protocol || 'none' );
		setImageModel( provider.image_model || '' );
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
									'Enter the API base URL, not a complete /models or /chat/completions URL (for example, https://api.mammouth.ai/v1).'
								) }
							/>
							<HStack
								spacing={ 2 }
								justify="center"
								style={ {
									color: '#888',
									fontSize: '12px',
									textTransform: 'uppercase',
									letterSpacing: '0.5px',
								} }
							>
								<span
									style={ {
										flex: 1,
										height: '1px',
										background: '#ddd',
									} }
								/>
								<span>{ __( 'or pick from list' ) }</span>
								<span
									style={ {
										flex: 1,
										height: '1px',
										background: '#ddd',
									} }
								/>
							</HStack>
							<ComboboxControl
								__nextHasNoMarginBottom
								label={ __( 'Provider preset' ) }
								value={ null }
								options={ PRESET_OPTIONS }
								onChange={ ( value ) => {
									// Ignore non-selectable group-header rows.
									if (
										! value ||
										( typeof value === 'string' &&
											value.startsWith( PRESET_HEADER_PREFIX ) )
									) {
										return;
									}
									const preset = PROVIDER_PRESETS.find(
										( p ) => p.url === value
									);
									if ( ! preset ) {
										return;
									}
									setEndpointUrl( preset.url );
									const updates = { endpoint_url: preset.url };
									// Only auto-fill name if currently empty.
									if ( ! name ) {
										setName( preset.name );
										updates.name = preset.name;
									}
									onUpdate( { ...provider, ...updates } );
								} }
								help={ __(
									'Type to filter ~110 known OpenAI-compatible providers. Selecting one fills the Endpoint URL above (and the Name field if empty).'
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
							{ modelState.status === 'loading' && (
								<HStack spacing={ 2 } expanded={ false }>
									<Spinner />
									<span>{ __( 'Loading models\u2026' ) }</span>
								</HStack>
							) }
							{ modelState.status === 'success' && (
								<span>{ `${ models.length } ${ __( 'models loaded.' ) }` }</span>
							) }
							{ modelState.status === 'empty' && (
								<span>{ __( 'No models were returned by this endpoint.' ) }</span>
							) }
							{ modelState.status === 'error' && (
								<span style={ { color: '#cc1818' } }>
									{ modelState.error || __( 'Could not load models. Check the endpoint and saved credentials.' ) }
								</span>
							) }
							{ requiresSave && (
								<span>{ __( 'Save this provider before loading models so its credentials stay server-side.' ) }</span>
							) }
							{ ! requiresSave && modelState.status === 'initial' && (
								<span>{ __( 'Load models to choose a default model, or leave Auto-select enabled.' ) }</span>
							) }
							<Button
								variant="secondary"
								onClick={ onLoadModels }
								disabled={ isSaving || ! canLoadModels || modelState.status === 'loading' }
							>
								{ modelState.status === 'error' || modelState.status === 'empty'
									? __( 'Retry loading models' )
									: __( 'Load models' ) }
							</Button>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Image generation protocol' ) }
								value={ imageProtocol }
								options={ [
									{ label: __( 'Disabled' ), value: 'none' },
									{ label: __( 'OpenAI Images API (/images/generations)' ), value: 'openai' },
									{ label: __( 'Chat Completions image response' ), value: 'chat_completions' },
								] }
								onChange={ ( value ) => {
									setImageProtocol( value );
									handleChange( 'image_protocol', value );
								} }
								disabled={ isSaving }
								help={ __( 'Enable only when this provider has a configured image model. Protocol selection prevents requests from being sent to the wrong endpoint.' ) }
							/>
							{ imageProtocol !== 'none' && (
								<SelectControl
									__nextHasNoMarginBottom
									label={ __( 'Image generation model' ) }
									value={ imageModel }
									options={ [ { label: __( 'Select a model' ), value: '' }, ...( models || [] ).map( ( m ) => ( { label: m.name || m.id, value: m.id } ) ) ] }
									onChange={ ( value ) => {
										setImageModel( value );
										handleChange( 'image_model', value );
									} }
									disabled={ isSaving }
								/>
							) }
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Endpoint type' ) }
								value={ endpointType }
								options={ [
									{
										label: __( 'Generic (standard OpenAI-compatible)' ),
										value: 'generic',
									},
									{
										label: __( 'DeepSeek-compatible (thinking mode — adds reasoning_content)' ),
										value: 'deepseek',
									},
									{
										label: __( 'Ollama thinking-capable model (adds thinking field)' ),
										value: 'ollama',
									},
								] }
								onChange={ ( value ) => {
									setEndpointType( value );
									handleChange( 'endpoint_type', value );
								} }
								disabled={ isSaving }
								help={ __(
									'Generic is recommended for Mammouth.ai and most OpenAI-compatible APIs. This does not select a provider or model, and normally does not affect plain responses. DeepSeek-compatible reattaches prior reasoning through reasoning_content; Ollama reattaches prior thought content through thinking.'
								) }
							/>
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
	const savedConnectionSignaturesRef = useRef( {} );
	const modelRequestsRef = useRef( new Map() );

	const hasProviders = providers.length > 0;

	/**
	 * Apply the canonical settings returned by WordPress after sanitization.
	 *
	 * @param {Object} settings Settings REST response.
	 * @return {Array} Saved provider configurations.
	 */
	const applySettings = useCallback( ( settings ) => {
		const loadedProviders = settings.ultimate_ai_connector_providers || [];
		const loadedOrder = settings.ultimate_ai_connector_provider_order || [];
		let nextProviders = loadedProviders;

		// Handle legacy single-provider config for migration.
		const legacyUrl = settings.ultimate_ai_connector_endpoint_url;
		if ( ! loadedProviders.length && legacyUrl ) {
			nextProviders = [ {
				id: generateProviderId(),
				name: 'Default',
				endpoint_url: legacyUrl,
				api_key: settings.ultimate_ai_connector_api_key || '',
				default_model: settings.ultimate_ai_connector_default_model || '',
				timeout: settings.ultimate_ai_connector_timeout || 360,
				enabled: true,
				image_protocol: settings.ultimate_ai_connector_image_protocol || 'none',
				image_model: settings.ultimate_ai_connector_image_model || '',
			} ];
		}

		setProviders( nextProviders );
		setProviderOrder( loadedOrder );
		savedConnectionSignaturesRef.current = Object.fromEntries(
			nextProviders.map( ( provider ) => [ provider.id, getConnectionSignature( provider ) ] )
		);
		return nextProviders;
	}, [] );

	/**
	 * Fetch providers from settings.
	 */
	const fetchSettings = useCallback( async () => {
		try {
			const settings = await apiFetch( {
				path: '/wp/v2/settings?_fields=ultimate_ai_connector_providers,ultimate_ai_connector_provider_order,ultimate_ai_connector_endpoint_url,ultimate_ai_connector_api_key,ultimate_ai_connector_default_model,ultimate_ai_connector_timeout,ultimate_ai_connector_image_protocol,ultimate_ai_connector_image_model',
			} );
			applySettings( settings );
		} catch {
			// Silently fail.
		} finally {
			setIsLoading( false );
		}
	}, [ applySettings ] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	/**
	 * Fetch models for an endpoint URL.
	 */
	const fetchModelsForUrl = useCallback( async ( url, configId ) => {
		if ( ! url || ! configId ) {
			return;
		}
		const cacheKey = getModelsCacheKey( configId, url );
		if ( modelRequestsRef.current.has( cacheKey ) ) {
			return;
		}
		const requestToken = {};
		modelRequestsRef.current.set( cacheKey, requestToken );
		setModelsCache( ( prev ) => ( {
			...prev,
			[ cacheKey ]: { status: 'loading', models: [] },
		} ) );
		try {
			const params = new URLSearchParams( {
				endpoint_url: url,
				config_id: configId,
			} );
			const result = await apiFetch( {
				path: '/ultimate-ai-connector-compatible-endpoints/v1/models?' + params.toString(),
			} );
			const models = Array.isArray( result ) ? result : [];
			if ( modelRequestsRef.current.get( cacheKey ) !== requestToken ) {
				return;
			}
			setModelsCache( ( prev ) => ( {
				...prev,
				[ cacheKey ]: {
					status: models.length ? 'success' : 'empty',
					models,
				},
			} ) );
		} catch ( error ) {
			if ( modelRequestsRef.current.get( cacheKey ) !== requestToken ) {
				return;
			}
			setModelsCache( ( prev ) => ( {
				...prev,
				[ cacheKey ]: {
					status: 'error',
					models: [],
					error: error instanceof Error ? error.message : __( 'Could not load models. Check the endpoint and saved credentials.' ),
				},
			} ) );
		} finally {
			if ( modelRequestsRef.current.get( cacheKey ) === requestToken ) {
				modelRequestsRef.current.delete( cacheKey );
			}
		}
	}, [] );

	const invalidateModelsForProvider = useCallback( ( configId ) => {
		const keyPrefix = 'models_' + configId + '_';
		setModelsCache( ( prev ) => Object.fromEntries(
			Object.entries( prev ).filter( ( [ key ] ) => ! key.startsWith( keyPrefix ) )
		) );
		for ( const key of modelRequestsRef.current.keys() ) {
			if ( key.startsWith( keyPrefix ) ) {
				modelRequestsRef.current.delete( key );
			}
		}
	}, [] );

	/**
	 * Update a provider in the list.
	 */
	const updateProvider = useCallback( ( index, updatedProvider ) => {
		const previousProvider = providers[ index ];
		setProviders( ( prev ) => {
			const next = [ ...prev ];
			next[ index ] = {
				...updatedProvider,
				id: updatedProvider.id || generateProviderId(),
			};
			return next;
		} );

		if (
			previousProvider &&
			( updatedProvider.endpoint_url !== previousProvider.endpoint_url ||
				updatedProvider.api_key !== previousProvider.api_key )
		) {
			invalidateModelsForProvider( updatedProvider.id );
		}
	}, [ invalidateModelsForProvider, providers ] );

	/**
	 * Remove a provider.
	 */
	const removeProvider = useCallback( ( index ) => {
		setProviders( ( prev ) => prev.filter( ( _, i ) => i !== index ) );
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
			endpoint_type: 'generic',
			image_protocol: 'none',
			image_model: '',
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

			const savedSettings = await apiFetch( {
				method: 'POST',
				path: '/wp/v2/settings',
				data: {
					ultimate_ai_connector_providers: providersToSave,
					ultimate_ai_connector_provider_order: order,
				},
			} );
			const savedProviders = applySettings( savedSettings );
			setModelsCache( {} );
			modelRequestsRef.current.clear();
			savedProviders.forEach( ( provider ) => {
				fetchModelsForUrl( provider.endpoint_url, provider.id );
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
		setModelsCache( {} );
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
				(() => {
					const cacheKey = getModelsCacheKey( provider.id, provider.endpoint_url );
					const modelState = modelsCache[ cacheKey ] || { status: 'initial', models: [] };
					const requiresSave = savedConnectionSignaturesRef.current[ provider.id ] !== getConnectionSignature( provider );
					return <ProviderCard
						key={ provider.id || index }
						provider={ provider }
						initialExpanded={ !! provider._new }
						onUpdate={ ( updated ) => updateProvider( index, updated ) }
						onRemove={ () => removeProvider( index ) }
						isSaving={ isSaving }
						saveError={ null }
						models={ modelState.models }
						modelState={ modelState }
						onLoadModels={ () => fetchModelsForUrl( provider.endpoint_url, provider.id ) }
						canLoadModels={ !! provider.endpoint_url && ! requiresSave }
						requiresSave={ requiresSave }
					/>;
				})()
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
