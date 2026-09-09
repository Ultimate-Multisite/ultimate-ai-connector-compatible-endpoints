=== Ultimate AI Connector for Compatible Endpoints ===
Contributors: superdav42
Tags: ai, connector, ollama, llm, local-ai
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.2.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects the WordPress AI Client to Ollama, LM Studio, or any AI endpoint that uses the standard chat completions API format.

== Description ==

This plugin extends the WordPress AI Client to support **any AI service or server that uses the standard chat completions API format** (`/v1/chat/completions` and `/v1/models` endpoints).

**Supported services include:**

* **Ollama** - Run open-source models (Llama, Mistral, Gemma, etc.) locally on your own hardware.
* **LM Studio** - Desktop application for local LLM inference with a one-click server.
* **OpenRouter** - Unified API providing access to 100+ models from multiple providers.
* **vLLM** - High-throughput inference server for production deployments.
* **LocalAI** - Drop-in replacement for running models locally.
* **text-generation-webui** - Popular web UI with API server mode.
* **Any compatible endpoint** - Works with any server implementing the standard format.

**Requirements:**

* **WordPress 7.0+** - The AI Client SDK is included in core. This plugin works on its own without any additional dependencies.
* **WordPress 6.9** - Also supported when the Gutenberg plugin (23.0+) is active, which provides the AI Client SDK.

**Why it matters:**

Other AI-powered plugins that use the WordPress AI Client (such as AI Experiments) can automatically discover and use any model you connect through this plugin. Configure your endpoint once and every AI feature on your site can use it.

**How it works:**

1. Install and activate the plugin.
2. Go to **Settings > Connectors** and configure the connector with your endpoint URL (e.g. `http://localhost:11434/v1` for Ollama).
3. Optionally provide an API key for services that require authentication.
4. The plugin registers a provider with the WordPress AI Client and dynamically discovers all available models from your endpoint.

The plugin also handles practical concerns like extended HTTP timeouts for slow local inference and non-standard port support.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ultimate-ai-connector-compatible-endpoints/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Connectors** and configure the connector.
4. Optionally enter an API key if your endpoint requires one.

== Frequently Asked Questions ==

= What endpoints are compatible? =

Any AI inference server that implements the standard `/v1/chat/completions` and `/v1/models` endpoints. This includes Ollama, LM Studio, vLLM, LocalAI, text-generation-webui, and many cloud services.

= Do I need an API key? =

It depends on your endpoint. Local servers like Ollama and LM Studio typically do not require a key. Cloud services like OpenRouter require one. Leave the API Key field blank for servers that do not need authentication.

= What models will be available? =

The plugin automatically queries your endpoint's `/models` resource and registers every model it finds. Whatever models your server offers will appear in the WordPress AI Client.

= Does this work on WordPress 7.0 without the AI Experiments plugin? =

Yes. WordPress 7.0 ships the AI Client SDK in core, so this connector plugin works on its own. You only need the AI Experiments plugin if you want the experimental AI features it provides (excerpt generation, summarization, etc.).

= Can I use this on WordPress 6.9? =

Yes, provided the Gutenberg plugin (version 23.0 or later) is active. Gutenberg ships the AI Client SDK that this connector extends. If the SDK is not present, the connector loads safely as a no-op and exposes no provider until the SDK becomes available.

== Screenshots ==

1. The Connectors settings page — enter your endpoint URL, optional API key, and default model.
2. Model selection in the WordPress AI Client — all models from your endpoint appear automatically.

== Changelog ==

= 2.2.0 =
Version 2.2.0 - Released on 2026-09-09
- New: Compatible endpoints can now generate images through the WordPress AI Client when the endpoint supports image generation.
- New: Model discovery now prefetches and presents available endpoint models more reliably in the connector settings.
- Fix: Cloud model selection and structured output requests now work correctly with compatible endpoints.
- Fix: Image support is guarded for older AI Client SDK versions so the plugin remains compatible while newer SDKs are unavailable.

= 2.1.3 =
Version 2.1.3 - Released on 2026-08-19
- Improved: WordPress compatibility metadata now reflects testing through WordPress 7.1.

= 2.1.2 - Released on 2026-05-26 =

* Fix: Connector now ignores the AI Client's persistent model metadata cache and rebuilds fresh model lists, preventing stale or corrupted model entries across cache backends and SDK updates.
* Fix: Plugin now defers SDK-dependent loading until the AI Client SDK is available, so companion plugins on WordPress 6.9 can register the connector reliably.

= 2.1.1 - Released on 2026-05-21 =

* Fix: Connector now registers with `api_key` authentication method so WordPress 7.0 and the AI plugin recognise it as a valid provider. Previously the connector used `none`, which caused AI features to report "This feature requires an AI Connector" even though the connector appeared connected on the Connectors page.

= 2.1.0 - Released on 2026-05-15 =

* New: Provider preset picker — a searchable combobox below the Endpoint URL field lists ~110 known OpenAI-compatible AI providers (Ollama, LM Studio, OpenRouter, Groq, Together, Fireworks, DeepSeek, Mistral, xAI, Cerebras, Cohere, Perplexity, OpenAI, Gemini via OpenAI-compat, and many more). Selecting a preset fills the Endpoint URL automatically and sets the Name field when blank, dramatically reducing setup friction.
* Improved: Lowered minimum WordPress requirement to 6.9 (with the Gutenberg plugin 23.0+ providing the AI Client SDK). The plugin loads safely as a no-op until the SDK becomes available — via WordPress 7.0+ core or any sibling plugin that bundles `wordpress/php-ai-client`.
* Fix: Connectors settings page now shows a single canonical card for this plugin instead of one auto-discovered card per registered SDK provider, restoring the intended multi-provider management UI.
* Fix: Multi-provider model listing now returns each provider's own models. Previously every OpenAI-compatible provider in a multi-provider setup showed the same list (the primary provider's). Callers can pass a `provider_id` request param to resolve the correct per-provider endpoint and API key, and the SDK model-directory cache key is now scoped per endpoint URL so cached model lists no longer collide across providers.
* Fix: Defer SDK-dependent class loading to `plugins_loaded:5` so SDKs registered by alphabetically-later plugins (e.g. companion AI agent plugins on WP 6.9) are detected. Previously the SDK guard ran at file-include time, before later plugins had a chance to register their autoloader.

= 2.0.0 - Released on 2026-04-24 =

* New: Multi-provider support — configure multiple AI endpoints and route requests with automatic fallback across providers.
* Fix: Multi-provider SDK integration with correct provider IDs, registration URLs, and HTTP filter scoping per provider.
* Fix: New provider card now auto-expands on add; script cache busting on plugin update.
* Fix: Dynamic provider class namespace for eval() and auto-expand behaviour for newly added providers.
* Fix: Uses stable Card/CardBody/CardHeader/CardDivider components (no longer experimental) for WordPress 6.9+ compatibility.
* Fix: Replaced undefined DragHandle with unicode grip icon for provider drag-to-reorder.
* Fix: Eliminated blocking HTTP request that fired on every page load.
* Improved: GitHub Actions workflows upgraded to Node.js 24.

= 1.2.0 - Released on 2026-04-09 =

* New: Improved WordPress.org discoverability with updated tags, plugin icon, banner, and screenshots.
* Fix: Updated plugin for WordPress 7.0 RC2 compatibility.
* Fix: Re-assert registerConnector() call across multiple ticks so WP core auto-register cannot clobber the custom connector card.
* Improved: Added wp-cli.yml for local development environment.

= 1.1.0 - Released on 2026-04-01 =

* Improved: Renamed plugin to "Ultimate AI Connector for Compatible Endpoints" for clarity and trademark compliance.
* Fix: Resolved namespace declaration order that could cause fatal errors on activation.
* Fix: Corrected CI failures related to PHP 8.2 compatibility and SDK availability guard.
* Fix: Corrected plugin slug references in E2E tests.
* Improved: Added PHPUnit, Cypress E2E, and wp-env test infrastructure with GitHub Actions CI.

= 1.0.0 =

* Initial release.
* Provider registration with the WordPress AI Client.
* Settings page for endpoint URL and optional API key.
* Dynamic model discovery from any compatible endpoint.
* Extended HTTP timeout support for local inference servers.
* Non-standard port support (e.g. Ollama on 11434, LM Studio on 1234).
