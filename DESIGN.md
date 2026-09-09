# Connector settings behaviour

The connector follows the WordPress Connectors page layout and components. A
single connector card manages multiple compatible endpoint configurations.

## Provider setup

- Provider presets are optional shortcuts that fill the endpoint URL and an
  empty provider name. Mammouth.ai uses `https://api.mammouth.ai/v1`.
- Generic is the default endpoint type and is recommended for Mammouth.ai and
  standard OpenAI-compatible APIs. DeepSeek and Ollama endpoint types only
  control how prior reasoning content is sent back to those APIs.
- WordPress sanitizes saved settings. The UI replaces its local provider list
  and order with the canonical settings returned by the save request.

## Model discovery

- Model lists are scoped by provider configuration ID and endpoint URL.
- A new provider or a changed endpoint/API key must be saved before models can
  be loaded, keeping credentials server-side.
- Saving clears stale model data and loads models with the saved provider
  configuration. API keys are never included in model-list query strings.
- Loading, success, empty, and error states remain visible. Empty and failed
  requests provide an explicit retry action.
- Superseded in-flight requests cannot replace results for a newly saved
  configuration.
