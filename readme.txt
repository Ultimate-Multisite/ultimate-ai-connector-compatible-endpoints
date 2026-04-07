=== Ultimate AI Connector for Compatible Endpoints ===
Contributors: superdav42
Tags: ai, connector, ollama, llm, local-ai
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.1.1
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

== Screenshots ==

1. The Connectors settings page — enter your endpoint URL, optional API key, and default model.
2. Model selection in the WordPress AI Client — all models from your endpoint appear automatically.

== Changelog ==

= 1.1.1 - Released on 2026-04-07 =

* Fix: re-assert our `registerConnector()` call across multiple ticks (microtask + 0/50/250/1000 ms) so the WP core `registerDefaultConnectors()` auto-register can't clobber the custom card with the generic API-key UI. The two scripts can run in either order depending on dynamic-import resolution; this guarantees we end up last. The proper upstream fix is in https://github.com/WordPress/gutenberg/pull/77116 — once that ships in a Gutenberg release, this workaround can be removed.

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
