/**
 * Curated list of OpenAI-compatible AI providers and their base URLs.
 *
 * Sourced from https://models.dev/api.json (the registry used by the
 * sst/opencode coding agent) plus manual additions for major native-SDK
 * providers (Anthropic, Cohere, Mistral, Groq, xAI, Cerebras, Perplexity,
 * Gemini, OpenAI, Together) that publish an OpenAI-compatible endpoint.
 *
 * Each entry has:
 *   - id:    stable slug
 *   - name:  human-readable label
 *   - url:   OpenAI-compatible base URL (suitable for the "Endpoint URL" field)
 *   - doc:   documentation URL (may be empty)
 *   - group: "local" or "cloud"
 *
 * Local providers come first; cloud providers are sorted alphabetically.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

const PROVIDER_PRESETS = [
	{
		"id": "atomic-chat",
		"name": "Atomic Chat",
		"url": "http://127.0.0.1:1337/v1",
		"doc": "https://atomic.chat",
		"group": "local"
	},
	{
		"id": "llamacpp",
		"name": "llama.cpp / vLLM (generic localhost)",
		"url": "http://localhost:8000/v1",
		"doc": "https://github.com/ggerganov/llama.cpp",
		"group": "local"
	},
	{
		"id": "lmstudio",
		"name": "LMStudio",
		"url": "http://127.0.0.1:1234/v1",
		"doc": "https://lmstudio.ai/models",
		"group": "local"
	},
	{
		"id": "ollama",
		"name": "Ollama",
		"url": "http://localhost:11434/v1",
		"doc": "https://ollama.com",
		"group": "local"
	},
	{
		"id": "privatemode-ai",
		"name": "Privatemode AI",
		"url": "http://localhost:8080/v1",
		"doc": "https://docs.privatemode.ai/api/overview",
		"group": "local"
	},
	{
		"id": "302ai",
		"name": "302.AI",
		"url": "https://api.302.ai/v1",
		"doc": "https://doc.302.ai",
		"group": "cloud"
	},
	{
		"id": "abacus",
		"name": "Abacus",
		"url": "https://routellm.abacus.ai/v1",
		"doc": "https://abacus.ai/help/api",
		"group": "cloud"
	},
	{
		"id": "abliteration-ai",
		"name": "abliteration.ai",
		"url": "https://api.abliteration.ai/v1",
		"doc": "https://docs.abliteration.ai/models",
		"group": "cloud"
	},
	{
		"id": "alibaba",
		"name": "Alibaba",
		"url": "https://dashscope-intl.aliyuncs.com/compatible-mode/v1",
		"doc": "https://www.alibabacloud.com/help/en/model-studio/models",
		"group": "cloud"
	},
	{
		"id": "alibaba-cn",
		"name": "Alibaba (China)",
		"url": "https://dashscope.aliyuncs.com/compatible-mode/v1",
		"doc": "https://www.alibabacloud.com/help/en/model-studio/models",
		"group": "cloud"
	},
	{
		"id": "alibaba-coding-plan",
		"name": "Alibaba Coding Plan",
		"url": "https://coding-intl.dashscope.aliyuncs.com/v1",
		"doc": "https://www.alibabacloud.com/help/en/model-studio/coding-plan",
		"group": "cloud"
	},
	{
		"id": "alibaba-coding-plan-cn",
		"name": "Alibaba Coding Plan (China)",
		"url": "https://coding.dashscope.aliyuncs.com/v1",
		"doc": "https://help.aliyun.com/zh/model-studio/coding-plan",
		"group": "cloud"
	},
	{
		"id": "ambient",
		"name": "Ambient",
		"url": "https://api.ambient.xyz/v1",
		"doc": "https://ambient.xyz",
		"group": "cloud"
	},
	{
		"id": "anthropic-compat",
		"name": "Anthropic (Claude via OpenAI-compat)",
		"url": "https://api.anthropic.com/v1/",
		"doc": "https://docs.anthropic.com/en/api/openai-sdk",
		"group": "cloud"
	},
	{
		"id": "auriko",
		"name": "Auriko",
		"url": "https://api.auriko.ai/v1",
		"doc": "https://docs.auriko.ai",
		"group": "cloud"
	},
	{
		"id": "baseten",
		"name": "Baseten",
		"url": "https://inference.baseten.co/v1",
		"doc": "https://docs.baseten.co/development/model-apis/overview",
		"group": "cloud"
	},
	{
		"id": "berget",
		"name": "Berget.AI",
		"url": "https://api.berget.ai/v1",
		"doc": "https://api.berget.ai",
		"group": "cloud"
	},
	{
		"id": "cerebras",
		"name": "Cerebras",
		"url": "https://api.cerebras.ai/v1",
		"doc": "https://inference-docs.cerebras.ai",
		"group": "cloud"
	},
	{
		"id": "chutes",
		"name": "Chutes",
		"url": "https://llm.chutes.ai/v1",
		"doc": "https://llm.chutes.ai/v1/models",
		"group": "cloud"
	},
	{
		"id": "clarifai",
		"name": "Clarifai",
		"url": "https://api.clarifai.com/v2/ext/openai/v1",
		"doc": "https://docs.clarifai.com/compute/inference/",
		"group": "cloud"
	},
	{
		"id": "claudinio",
		"name": "Claudinio",
		"url": "https://api.claudin.io/v1",
		"doc": "https://claudin.io",
		"group": "cloud"
	},
	{
		"id": "cloudferro-sherlock",
		"name": "CloudFerro Sherlock",
		"url": "https://api-sherlock.cloudferro.com/openai/v1/",
		"doc": "https://docs.sherlock.cloudferro.com/",
		"group": "cloud"
	},
	{
		"id": "cohere-compat",
		"name": "Cohere (compatibility API)",
		"url": "https://api.cohere.ai/compatibility/v1",
		"doc": "https://docs.cohere.com/docs/compatibility-api",
		"group": "cloud"
	},
	{
		"id": "cortecs",
		"name": "Cortecs",
		"url": "https://api.cortecs.ai/v1",
		"doc": "https://api.cortecs.ai/v1/models",
		"group": "cloud"
	},
	{
		"id": "drun",
		"name": "D.Run (China)",
		"url": "https://chat.d.run/v1",
		"doc": "https://www.d.run",
		"group": "cloud"
	},
	{
		"id": "deepseek",
		"name": "DeepSeek",
		"url": "https://api.deepseek.com/v1",
		"doc": "https://api-docs.deepseek.com/quick_start/pricing",
		"group": "cloud"
	},
	{
		"id": "digitalocean",
		"name": "DigitalOcean",
		"url": "https://inference.do-ai.run/v1",
		"doc": "https://docs.digitalocean.com/products/gradient-ai-platform/details/models/",
		"group": "cloud"
	},
	{
		"id": "dinference",
		"name": "DInference",
		"url": "https://api.dinference.com/v1",
		"doc": "https://dinference.com",
		"group": "cloud"
	},
	{
		"id": "evroc",
		"name": "evroc",
		"url": "https://models.think.evroc.com/v1",
		"doc": "https://docs.evroc.com/products/think/overview.html",
		"group": "cloud"
	},
	{
		"id": "fastrouter",
		"name": "FastRouter",
		"url": "https://go.fastrouter.ai/api/v1",
		"doc": "https://fastrouter.ai/models",
		"group": "cloud"
	},
	{
		"id": "firepass",
		"name": "Fireworks (Firepass)",
		"url": "https://api.fireworks.ai/inference/v1/",
		"doc": "https://docs.fireworks.ai/firepass",
		"group": "cloud"
	},
	{
		"id": "fireworks-ai",
		"name": "Fireworks AI",
		"url": "https://api.fireworks.ai/inference/v1/",
		"doc": "https://fireworks.ai/docs/",
		"group": "cloud"
	},
	{
		"id": "friendli",
		"name": "Friendli",
		"url": "https://api.friendli.ai/serverless/v1",
		"doc": "https://friendli.ai/docs/guides/serverless_endpoints/introduction",
		"group": "cloud"
	},
	{
		"id": "frogbot",
		"name": "FrogBot",
		"url": "https://app.frogbot.ai/api/v1",
		"doc": "https://docs.frogbot.ai",
		"group": "cloud"
	},
	{
		"id": "github-copilot",
		"name": "GitHub Copilot",
		"url": "https://api.githubcopilot.com",
		"doc": "https://docs.github.com/en/copilot",
		"group": "cloud"
	},
	{
		"id": "github-models",
		"name": "GitHub Models",
		"url": "https://models.github.ai/inference",
		"doc": "https://docs.github.com/en/github-models",
		"group": "cloud"
	},
	{
		"id": "gmicloud",
		"name": "GMI Cloud",
		"url": "https://api.gmi-serving.com/v1",
		"doc": "https://docs.gmicloud.ai/inference-engine/api-reference/llm-api-reference",
		"group": "cloud"
	},
	{
		"id": "gemini-compat",
		"name": "Google Gemini (OpenAI-compat)",
		"url": "https://generativelanguage.googleapis.com/v1beta/openai/",
		"doc": "https://ai.google.dev/gemini-api/docs/openai",
		"group": "cloud"
	},
	{
		"id": "groq",
		"name": "Groq",
		"url": "https://api.groq.com/openai/v1",
		"doc": "https://console.groq.com/docs/openai",
		"group": "cloud"
	},
	{
		"id": "helicone",
		"name": "Helicone",
		"url": "https://ai-gateway.helicone.ai/v1",
		"doc": "https://helicone.ai/models",
		"group": "cloud"
	},
	{
		"id": "hpc-ai",
		"name": "HPC-AI",
		"url": "https://api.hpc-ai.com/inference/v1",
		"doc": "https://www.hpc-ai.com/doc/docs/quickstart/",
		"group": "cloud"
	},
	{
		"id": "huggingface",
		"name": "Hugging Face",
		"url": "https://router.huggingface.co/v1",
		"doc": "https://huggingface.co/docs/inference-providers",
		"group": "cloud"
	},
	{
		"id": "iflowcn",
		"name": "iFlow",
		"url": "https://apis.iflow.cn/v1",
		"doc": "https://platform.iflow.cn/en/docs",
		"group": "cloud"
	},
	{
		"id": "inception",
		"name": "Inception",
		"url": "https://api.inceptionlabs.ai/v1/",
		"doc": "https://platform.inceptionlabs.ai/docs",
		"group": "cloud"
	},
	{
		"id": "inference",
		"name": "Inference",
		"url": "https://inference.net/v1",
		"doc": "https://inference.net/models",
		"group": "cloud"
	},
	{
		"id": "io-net",
		"name": "IO.NET",
		"url": "https://api.intelligence.io.solutions/api/v1",
		"doc": "https://io.net/docs/guides/intelligence/io-intelligence",
		"group": "cloud"
	},
	{
		"id": "jiekou",
		"name": "Jiekou.AI",
		"url": "https://api.jiekou.ai/openai",
		"doc": "https://docs.jiekou.ai/docs/support/quickstart?utm_source=github_models.dev",
		"group": "cloud"
	},
	{
		"id": "kilo",
		"name": "Kilo Gateway",
		"url": "https://api.kilo.ai/api/gateway",
		"doc": "https://kilo.ai",
		"group": "cloud"
	},
	{
		"id": "kuae-cloud-coding-plan",
		"name": "KUAE Cloud Coding Plan",
		"url": "https://coding-plan-endpoint.kuaecloud.net/v1",
		"doc": "https://docs.mthreads.com/kuaecloud/kuaecloud-doc-online/coding_plan/",
		"group": "cloud"
	},
	{
		"id": "llama",
		"name": "Llama",
		"url": "https://api.llama.com/compat/v1/",
		"doc": "https://llama.developer.meta.com/docs/models",
		"group": "cloud"
	},
	{
		"id": "llmgateway",
		"name": "LLM Gateway",
		"url": "https://api.llmgateway.io/v1",
		"doc": "https://llmgateway.io/docs",
		"group": "cloud"
	},
	{
		"id": "lucidquery",
		"name": "LucidQuery AI",
		"url": "https://lucidquery.com/api/v1",
		"doc": "https://lucidquery.com/api/docs",
		"group": "cloud"
	},
	{
		"id": "mammouth-ai",
		"name": "Mammouth.ai",
		"url": "https://api.mammouth.ai/v1",
		"doc": "https://mammouth.ai",
		"group": "cloud"
	},
	{
		"id": "meganova",
		"name": "Meganova",
		"url": "https://api.meganova.ai/v1",
		"doc": "https://docs.meganova.ai",
		"group": "cloud"
	},
	{
		"id": "mistral",
		"name": "Mistral",
		"url": "https://api.mistral.ai/v1",
		"doc": "https://docs.mistral.ai/api/",
		"group": "cloud"
	},
	{
		"id": "mixlayer",
		"name": "Mixlayer",
		"url": "https://models.mixlayer.ai/v1",
		"doc": "https://docs.mixlayer.com",
		"group": "cloud"
	},
	{
		"id": "moark",
		"name": "Moark",
		"url": "https://moark.com/v1",
		"doc": "https://moark.com/docs/openapi/v1#tag/%E6%96%87%E6%9C%AC%E7%94%9F%E6%88%90",
		"group": "cloud"
	},
	{
		"id": "modelscope",
		"name": "ModelScope",
		"url": "https://api-inference.modelscope.cn/v1",
		"doc": "https://modelscope.cn/docs/model-service/API-Inference/intro",
		"group": "cloud"
	},
	{
		"id": "moonshotai",
		"name": "Moonshot AI",
		"url": "https://api.moonshot.ai/v1",
		"doc": "https://platform.moonshot.ai/docs/api/chat",
		"group": "cloud"
	},
	{
		"id": "moonshotai-cn",
		"name": "Moonshot AI (China)",
		"url": "https://api.moonshot.cn/v1",
		"doc": "https://platform.moonshot.cn/docs/api/chat",
		"group": "cloud"
	},
	{
		"id": "morph",
		"name": "Morph",
		"url": "https://api.morphllm.com/v1",
		"doc": "https://docs.morphllm.com/api-reference/introduction",
		"group": "cloud"
	},
	{
		"id": "nano-gpt",
		"name": "NanoGPT",
		"url": "https://nano-gpt.com/api/v1",
		"doc": "https://docs.nano-gpt.com",
		"group": "cloud"
	},
	{
		"id": "nearai",
		"name": "NEAR AI Cloud",
		"url": "https://cloud-api.near.ai/v1",
		"doc": "https://docs.near.ai/",
		"group": "cloud"
	},
	{
		"id": "nebius",
		"name": "Nebius Token Factory",
		"url": "https://api.tokenfactory.nebius.com/v1",
		"doc": "https://docs.tokenfactory.nebius.com/",
		"group": "cloud"
	},
	{
		"id": "neuralwatt",
		"name": "Neuralwatt",
		"url": "https://api.neuralwatt.com/v1",
		"doc": "https://portal.neuralwatt.com/docs",
		"group": "cloud"
	},
	{
		"id": "nova",
		"name": "Nova",
		"url": "https://api.nova.amazon.com/v1",
		"doc": "https://nova.amazon.com/dev/documentation",
		"group": "cloud"
	},
	{
		"id": "novita-ai",
		"name": "NovitaAI",
		"url": "https://api.novita.ai/openai",
		"doc": "https://novita.ai/docs/guides/introduction",
		"group": "cloud"
	},
	{
		"id": "nvidia",
		"name": "Nvidia",
		"url": "https://integrate.api.nvidia.com/v1",
		"doc": "https://docs.api.nvidia.com/nim/",
		"group": "cloud"
	},
	{
		"id": "ollama-cloud",
		"name": "Ollama Cloud",
		"url": "https://ollama.com/v1",
		"doc": "https://docs.ollama.com/cloud",
		"group": "cloud"
	},
	{
		"id": "openai",
		"name": "OpenAI",
		"url": "https://api.openai.com/v1",
		"doc": "https://platform.openai.com/docs",
		"group": "cloud"
	},
	{
		"id": "opencode-go",
		"name": "OpenCode Go",
		"url": "https://opencode.ai/zen/go/v1",
		"doc": "https://opencode.ai/docs/zen",
		"group": "cloud"
	},
	{
		"id": "opencode",
		"name": "OpenCode Zen",
		"url": "https://opencode.ai/zen/v1",
		"doc": "https://opencode.ai/docs/zen",
		"group": "cloud"
	},
	{
		"id": "openrouter",
		"name": "OpenRouter",
		"url": "https://openrouter.ai/api/v1",
		"doc": "https://openrouter.ai/models",
		"group": "cloud"
	},
	{
		"id": "orcarouter",
		"name": "OrcaRouter",
		"url": "https://api.orcarouter.ai/v1",
		"doc": "https://docs.orcarouter.ai",
		"group": "cloud"
	},
	{
		"id": "ovhcloud",
		"name": "OVHcloud AI Endpoints",
		"url": "https://oai.endpoints.kepler.ai.cloud.ovh.net/v1",
		"doc": "https://www.ovhcloud.com/en/public-cloud/ai-endpoints/catalog//",
		"group": "cloud"
	},
	{
		"id": "perplexity",
		"name": "Perplexity",
		"url": "https://api.perplexity.ai",
		"doc": "https://docs.perplexity.ai",
		"group": "cloud"
	},
	{
		"id": "perplexity-agent",
		"name": "Perplexity Agent",
		"url": "https://api.perplexity.ai/v1",
		"doc": "https://docs.perplexity.ai/docs/agent-api/models",
		"group": "cloud"
	},
	{
		"id": "poe",
		"name": "Poe",
		"url": "https://api.poe.com/v1",
		"doc": "https://creator.poe.com/docs/external-applications/openai-compatible-api",
		"group": "cloud"
	},
	{
		"id": "qihang-ai",
		"name": "QiHang",
		"url": "https://api.qhaigc.net/v1",
		"doc": "https://www.qhaigc.net/docs",
		"group": "cloud"
	},
	{
		"id": "qiniu-ai",
		"name": "Qiniu",
		"url": "https://api.qnaigc.com/v1",
		"doc": "https://developer.qiniu.com/aitokenapi",
		"group": "cloud"
	},
	{
		"id": "regolo-ai",
		"name": "Regolo AI",
		"url": "https://api.regolo.ai/v1",
		"doc": "https://docs.regolo.ai/",
		"group": "cloud"
	},
	{
		"id": "requesty",
		"name": "Requesty",
		"url": "https://router.requesty.ai/v1",
		"doc": "https://requesty.ai/solution/llm-routing/models",
		"group": "cloud"
	},
	{
		"id": "sarvam",
		"name": "Sarvam AI",
		"url": "https://api.sarvam.ai/v1",
		"doc": "https://docs.sarvam.ai/api-reference-docs/getting-started/models",
		"group": "cloud"
	},
	{
		"id": "scaleway",
		"name": "Scaleway",
		"url": "https://api.scaleway.ai/v1",
		"doc": "https://www.scaleway.com/en/docs/generative-apis/",
		"group": "cloud"
	},
	{
		"id": "siliconflow",
		"name": "SiliconFlow",
		"url": "https://api.siliconflow.com/v1",
		"doc": "https://cloud.siliconflow.com/models",
		"group": "cloud"
	},
	{
		"id": "siliconflow-cn",
		"name": "SiliconFlow (China)",
		"url": "https://api.siliconflow.cn/v1",
		"doc": "https://cloud.siliconflow.com/models",
		"group": "cloud"
	},
	{
		"id": "stackit",
		"name": "STACKIT",
		"url": "https://api.openai-compat.model-serving.eu01.onstackit.cloud/v1",
		"doc": "https://docs.stackit.cloud/products/data-and-ai/ai-model-serving/basics/available-shared-models",
		"group": "cloud"
	},
	{
		"id": "stepfun",
		"name": "StepFun",
		"url": "https://api.stepfun.com/v1",
		"doc": "https://platform.stepfun.com/docs/zh/overview/concept",
		"group": "cloud"
	},
	{
		"id": "submodel",
		"name": "submodel",
		"url": "https://llm.submodel.ai/v1",
		"doc": "https://submodel.gitbook.io",
		"group": "cloud"
	},
	{
		"id": "synthetic",
		"name": "Synthetic",
		"url": "https://api.synthetic.new/openai/v1",
		"doc": "https://synthetic.new/pricing",
		"group": "cloud"
	},
	{
		"id": "tencent-coding-plan",
		"name": "Tencent Coding Plan (China)",
		"url": "https://api.lkeap.cloud.tencent.com/coding/v3",
		"doc": "https://cloud.tencent.com/document/product/1772/128947",
		"group": "cloud"
	},
	{
		"id": "tencent-tokenhub",
		"name": "Tencent TokenHub",
		"url": "https://tokenhub.tencentmaas.com/v1",
		"doc": "https://cloud.tencent.com/document/product/1823/130050",
		"group": "cloud"
	},
	{
		"id": "the-grid-ai",
		"name": "The Grid AI",
		"url": "https://api.thegrid.ai/v1",
		"doc": "https://thegrid.ai/docs",
		"group": "cloud"
	},
	{
		"id": "togetherai",
		"name": "Together AI",
		"url": "https://api.together.ai/v1",
		"doc": "https://docs.together.ai/docs/openai-api-compatibility",
		"group": "cloud"
	},
	{
		"id": "umans-ai-coding-plan",
		"name": "Umans AI Coding Plan",
		"url": "https://api.code.umans.ai/v1",
		"doc": "https://app.umans.ai/offers/code/docs",
		"group": "cloud"
	},
	{
		"id": "upstage",
		"name": "Upstage",
		"url": "https://api.upstage.ai/v1/solar",
		"doc": "https://developers.upstage.ai/docs/apis/chat",
		"group": "cloud"
	},
	{
		"id": "vivgrid",
		"name": "Vivgrid",
		"url": "https://api.vivgrid.com/v1",
		"doc": "https://docs.vivgrid.com/models",
		"group": "cloud"
	},
	{
		"id": "vultr",
		"name": "Vultr",
		"url": "https://api.vultrinference.com/v1",
		"doc": "https://api.vultrinference.com/",
		"group": "cloud"
	},
	{
		"id": "wafer.ai",
		"name": "Wafer",
		"url": "https://pass.wafer.ai/v1",
		"doc": "https://docs.wafer.ai/wafer-pass",
		"group": "cloud"
	},
	{
		"id": "wandb",
		"name": "Weights & Biases",
		"url": "https://api.inference.wandb.ai/v1",
		"doc": "https://docs.wandb.ai/guides/integrations/inference/",
		"group": "cloud"
	},
	{
		"id": "xai",
		"name": "xAI (Grok)",
		"url": "https://api.x.ai/v1",
		"doc": "https://docs.x.ai",
		"group": "cloud"
	},
	{
		"id": "xiaomi",
		"name": "Xiaomi",
		"url": "https://api.xiaomimimo.com/v1",
		"doc": "https://platform.xiaomimimo.com/#/docs",
		"group": "cloud"
	},
	{
		"id": "xiaomi-token-plan-cn",
		"name": "Xiaomi Token Plan (China)",
		"url": "https://token-plan-cn.xiaomimimo.com/v1",
		"doc": "https://platform.xiaomimimo.com/#/docs",
		"group": "cloud"
	},
	{
		"id": "xiaomi-token-plan-ams",
		"name": "Xiaomi Token Plan (Europe)",
		"url": "https://token-plan-ams.xiaomimimo.com/v1",
		"doc": "https://platform.xiaomimimo.com/#/docs",
		"group": "cloud"
	},
	{
		"id": "xiaomi-token-plan-sgp",
		"name": "Xiaomi Token Plan (Singapore)",
		"url": "https://token-plan-sgp.xiaomimimo.com/v1",
		"doc": "https://platform.xiaomimimo.com/#/docs",
		"group": "cloud"
	},
	{
		"id": "xpersona",
		"name": "Xpersona",
		"url": "https://xpersona.co/v1",
		"doc": "https://xpersona.co/docs",
		"group": "cloud"
	},
	{
		"id": "zai",
		"name": "Z.AI",
		"url": "https://api.z.ai/api/paas/v4",
		"doc": "https://docs.z.ai/guides/overview/pricing",
		"group": "cloud"
	},
	{
		"id": "zai-coding-plan",
		"name": "Z.AI Coding Plan",
		"url": "https://api.z.ai/api/coding/paas/v4",
		"doc": "https://docs.z.ai/devpack/overview",
		"group": "cloud"
	},
	{
		"id": "zenmux",
		"name": "ZenMux",
		"url": "https://zenmux.ai/api/v1",
		"doc": "https://docs.zenmux.ai",
		"group": "cloud"
	},
	{
		"id": "zhipuai",
		"name": "Zhipu AI",
		"url": "https://open.bigmodel.cn/api/paas/v4",
		"doc": "https://docs.z.ai/guides/overview/pricing",
		"group": "cloud"
	},
	{
		"id": "zhipuai-coding-plan",
		"name": "Zhipu AI Coding Plan",
		"url": "https://open.bigmodel.cn/api/coding/paas/v4",
		"doc": "https://docs.bigmodel.cn/cn/coding-plan/overview",
		"group": "cloud"
	}
];

export default PROVIDER_PRESETS;
