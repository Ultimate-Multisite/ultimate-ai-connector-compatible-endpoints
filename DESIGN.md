# Connector settings behaviour

The connector uses WordPress components and the existing Connectors page layout.
Each provider has an independent model list keyed by its configuration ID and
endpoint URL.

Model discovery uses the credentials saved on the server. After a successful
save, clear cached model lists and fetch them again for the saved providers.
Adding an authenticated provider or changing its key must not require a page
reload before the model dropdown updates. Never include API keys in model-list
query strings.
