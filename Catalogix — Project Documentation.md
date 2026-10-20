# Catalogix — Project Documentation

Oct 20, 2026 · @M.A Fayez

**Catalogix** is an AI-powered multi-site product extractor: paste one or many website URLs, preview every product with its category, name, price and image, and export it per site or all together as CSV or Excel. Built on Laravel 13 and PHP 8.3+ with MySQL; AI agents run on paid or free providers (including local Ollama) configured from the admin panel.

## Project snapshot

| Field | Value |
| --- | --- |
| Project name | Catalogix |
| Project title | Catalogix: AI-Powered Multi-Site Product Extractor |
| Tagline | Paste URLs. Preview products. Export anywhere. |
| Product type | Web application (SaaS-ready, single-tenant to start) |
| Owner | Intelifa Soft |
| PHP | 8.3 minimum (8.4 recommended; Laravel 13 supports 8.3–8.5) |
| Backend | Laravel 13 (current major, released March 17, 2026) |
| Admin panel | Filament 5 (Livewire 4, Tailwind CSS 4) |
| User frontend | Laravel React starter kit: Inertia + React + TypeScript + Tailwind CSS 4 + shadcn/ui |
| Database | MySQL 8 (default) · SQL Server 2019+ (supported) |
| Queue | Laravel database queue (Redis optional in production) |
| Realtime | Laravel Reverb + Laravel Echo |
| HTML fetch & parse | Laravel HTTP client + Symfony DomCrawler |
| JS-rendered pages | Spatie Browsershot (headless Chrome via Node, already present for Vite) |
| AI layer | Official Laravel AI SDK (`laravel/ai`) |
| AI providers | Google Gemini, OpenAI, Anthropic Claude, Groq, OpenRouter, Ollama (local) |
| Free AI options | Ollama (local, unlimited) · Gemini free tier · Groq free tier · OpenRouter `:free` models |
| Export | Laravel Excel (maatwebsite/excel): CSV, XLSX, ZIP |
| Python | Not used anywhere |
| Core modules | AI Providers · AI Models · AI Agents · AI Training · Sites · Extract · Live Preview · Export · History · Global Settings |

## Description & goals

Catalogix turns any public e-commerce listing page into a clean, exportable product table in minutes, without writing a scraper per site.

**Problem.** Sellers, sourcing agents and agencies copy product data (name, price, image, category) from other sites by hand. Every site has a different layout, so one-off scrapers break and nobody maintains them.

**Solution.** The user pastes one or many URLs, such as `https://chaldal.com/popular` or `https://v2.sawdahexpress.com.bd/category/shoes`. Catalogix fetches each page, extracts the products, follows pagination, and streams results into a live preview grouped by site. The user exports one site or everything as CSV, XLSX or a ZIP. AI agents learn each new site layout once and save it as a reusable recipe, so later runs need no AI.

**Users**

| Role | What they do |
| --- | --- |
| Admin | Adds AI providers and models, creates agents, sets active/default, writes training instructions, manages site rules and limits |
| User | Submits URLs, watches the live preview, filters, exports, re-runs past jobs |

**Goals**

1. Extract name, price, sale price, image URL, product URL, category and stock from any public listing page.
2. Handle many URLs per submission, across different domains, in parallel.
3. Let the admin control every AI decision (provider, model, instructions, which agent is active and default) without code changes.
4. Store every product per site and per source URL, deduplicated, so re-runs update instead of duplicating.
5. Export per site or combined, in CSV, XLSX or ZIP.

**Out of scope (v1)**

- Pages behind a login, CAPTCHA or bot challenge; these are marked `blocked`, never bypassed.
- Downloading and hosting product images (image URLs only).
- Product detail pages with variants; v1 reads listing pages.
- Multi-tenant billing and plans.

## Architecture

One Laravel codebase holds the admin panel, the user app, the queue jobs and the realtime server; the only outside services are the target websites, the active AI provider (cloud or local Ollama), and headless Chrome launched by Browsershot.

&#91;embedded content: Catalogix architecture · one Laravel app, no Python\]

The user submits URLs, controllers dispatch queue jobs with a config snapshot from `AgentResolver`, jobs fetch pages (through Browsershot when JavaScript is needed), call the AI provider only when code-based extraction fails, upsert products into the database, and Reverb pushes new rows to the preview.

## Modules & features

The app has two surfaces: the Filament admin panel at `/admin` and the Inertia + React user app at `/app`.

### Admin panel (Filament 3)

| Module | Features |
| --- | --- |
| AI Providers | Global Settings card (default provider, max tokens, free-only mode, rate-limit fallback) + one card per provider (Gemini, OpenAI, Claude, Groq, OpenRouter, Ollama) with Active / Configured / Free badges, API host, masked API key, default model, "Test connection" |
| AI Models | Table per provider with model key, context length, input/output price, **Free** flag; active toggle; "Sync models" action per provider |
| AI Agents | Name, role, provider, model, fallback model, temperature, max tokens; **active** and **default** toggles; grouped by role; "Test run" row action |
| AI Training | Custom page with tabs: Instructions, Examples, Playground, Test Set, Logs (details in AI agent configuration) |
| Sites | One row per domain: platform, saved extraction recipe, pagination rule, max pages, forced agent, blocked flag, last success, product count |
| Scrape Jobs | Read-only list of all jobs and targets with status, strategy used, errors; retry action |
| Settings | Max URLs per submission, max pages per URL, per-domain concurrency, request delay, allowed export formats, user agent string |
| Dashboard | Widgets: jobs today, products extracted, AI cost today, free vs paid calls, failing sites |

### User app (Inertia + React)

| Page | Features |
| --- | --- |
| Extract | Multi-line URL input that turns into chips with favicons; invalid and duplicate URLs flagged before submit; limit from Settings; a bare domain opens a category picker step |
| Live Preview | One tab per site plus an "All" tab; status badge per site (queued, crawling page N, done, blocked, failed); product grid with thumbnail, name, price, sale price, category, stock, link; search, category filter, price range, column picker, row selection; rows arrive live |
| Export | Dialog with scope (this site, selected rows, all sites), format (CSV, XLSX, ZIP of per-site CSVs), column picker, XLSX layout (one sheet per site, or one sheet with a `source` column) |
| History | Past jobs with date, sites, product count; re-run, re-export, delete |
| Exports | Past export files with download links (signed, 24-hour expiry) |

## AI agent configuration

Only agents that are **active** ever run, and each role has exactly **one default** agent. No model name is hardcoded anywhere; every AI call goes through `AgentResolver`.

### Roles (PHP enum `App\Enums\AgentRole`)

| Role | Job | When it is called |
| --- | --- | --- |
| `discovery` | Classify ambiguous links as category/listing or not | Only when URL patterns and sitemap are not enough |
| `extractor` | Read a cleaned page snapshot and return CSS selectors + pagination rule | Once per domain, when the site has no valid recipe |
| `fallback_extractor` | Read page text and return products as JSON directly | When selectors return zero products |
| `normalizer` | Clean names, map categories | Optional, in batches |
| `validator` | Flag junk rows (zero price, nav items, duplicates) | Optional, at job end |

### Rules

1. An inactive agent is never called and never shown in user-facing or training dropdowns.
2. Exactly one default per role. Setting a new default unsets the others for that role inside a DB transaction.
3. The default agent cannot be deactivated until another agent of that role is made default (Filament validation message).
4. If an agent's model is inactive, the agent shows a warning badge and runs on `fallback_model_id`; with no fallback, the run fails with a clear error.
5. Each agent has exactly one `live` prompt version.

### Resolver

```php
final class AgentResolver
{
    public function for(AgentRole $role, ?Site $site = null): AiAgent
    {
        if ($site?->forceAgent?->is_active && $site->forceAgent->role === $role) {
            return $site->forceAgent->load(['model.provider', 'fallbackModel.provider', 'livePrompt', 'activeExamples']);
        }

        return AiAgent::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->where('is_default', true)
            ->with(['model.provider', 'fallbackModel.provider', 'livePrompt', 'activeExamples'])
            ->firstOr(fn () => throw new NoActiveAgentException($role));
    }
}
```

### Config snapshot

When a scrape job is dispatched, `ConfigSnapshot::build($site)` resolves every role's agent, model, live prompt, examples and the site recipe into one array stored on the job. The job uses only that snapshot, so an admin editing a prompt mid-run never breaks a running job, and every `agent_runs` row records which prompt version produced it.

### AI Training page

Training here means managing instructions, examples and site recipes, then testing before going live. No model fine-tuning.

1. **Instructions:** system prompt editor, output JSON schema, notes. Saving always creates a new `draft` version.
2. **Examples:** few-shot pairs (page snippet → expected JSON). Max 3 active examples are sent per call to control cost.
3. **Playground:** enter a URL, pick agent + version, run. Shows the product table, raw AI output, tokens, cost and time. Actions: "Save as example", "Save as site recipe".
4. **Test Set:** benchmark URLs per agent. "Run draft vs live" compares products found, field fill rate and cost; "Make live" archives the old version.
5. **Logs:** `agent_runs` with filters and a daily cost chart. Any archived version can be made live again (rollback).

Prompt order sent to the model: system prompt + JSON schema + site notes → up to 3 example pairs as user/assistant turns → the current cleaned page content.

## AI providers & free options

Catalogix supports six providers through the official Laravel AI SDK (`laravel/ai`), and four of them can run at zero cost, so the whole product can be built and tested without paying for AI. Recommended free setup: **Gemini (free tier) as default**, Ollama for private or offline work.

| Provider | Driver | Default host | Free option | Best use in Catalogix |
| --- | --- | --- | --- | --- |
| Ollama (local) | `ollama` | `http://localhost:11434` | Fully free, unlimited, no API key; runs on your PC | Development, private client data, Normalizer and Validator |
| Google Gemini | `gemini` | `https://generativelanguage.googleapis.com` | Free tier on Flash models, no card needed | **Default free provider**; Extractor and Fallback Extractor (long context) |
| Groq | `groq` | `https://api.groq.com/openai/v1` | Free tier, rate-limited (around 30 requests/min) | Fast Discovery and Normalizer calls |
| OpenRouter | `openrouter` | `https://openrouter.ai/api/v1` | Models with a `:free` suffix, around 50 requests/day | Trying many open models with one key |
| OpenAI | `openai` | `https://api.openai.com` | Paid | Production fallback |
| Anthropic Claude | `anthropic` | `https://api.anthropic.com` | Paid | Production Extractor for difficult sites |

Free tiers are rate-limited, change without notice, and may use your prompts to improve the provider's models. Never send private client data to a free cloud tier; use Ollama for that.

### Admin UI: AI Providers page

**Global Settings card**

- **Default AI provider:** dropdown of active, configured providers. Used by any agent that doesn't pick its own provider.
- **Max tokens:** number input; the hint shows the selected model's output limit.
- **Free-only mode:** toggle. When on, `AgentResolver` only uses models marked `is_free`; paid agents are skipped and their role falls back to a free agent.
- **Rate-limit fallback provider:** when a free provider returns HTTP 429, the call retries once on this provider.

**Provider cards (one per provider, two per row)**

- Logo, name, subtitle (for example "Local AI - Free & Private").
- Badges: **Active** (toggle on), **Configured** / **Not configured** (key saved, or Ollama reachable), and a green **Free** badge on providers with a free option.
- API Host field ("change only for a proxy or gateway"); Ollama shows Base URL instead.
- API Key field: masked, with show and copy buttons; hidden for Ollama.
- Default model dropdown; free models carry a "(Free)" label.
- "Get API key" link and a "Test connection" button that sends one tiny prompt and shows latency.

### How it connects to agents

Each agent picks a provider + model (or inherits the default provider). Resolution order: site override → role's active default agent → that agent's fallback model → rate-limit fallback provider. Provider keys come from the database at runtime, never from `.env`:

```php
use Laravel\Ai\Ai;

final class ProviderFactory
{
    public function make(AiProvider $provider)
    {
        return Ai::build(array_filter([
            'driver' => $provider->driver->value, // gemini, ollama, groq, openrouter, openai, anthropic
            'key'    => $provider->api_key,       // null for Ollama
            'url'    => $provider->base_url,      // confirm the base-URL config key in the laravel/ai docs
        ]));
    }
}
```

Agents use the SDK's structured output so every extraction returns validated JSON (selectors or product arrays), not free text.

## Extraction engine (PHP only)

Everything runs inside Laravel queue jobs; no Python. Static pages use the Laravel HTTP client + Symfony DomCrawler. JavaScript-rendered pages use Spatie Browsershot, which drives headless Chrome through Node and Puppeteer (Node is already installed for Vite).

### Strategy ladder

Each page tries strategies in this order and stops at the first that returns at least 3 products with a name and a price. The winning strategy is saved on the site, so the next run starts there.

1. **Saved recipe:** `sites.selectors` applied with DomCrawler to static HTML, or to Browsershot HTML when `sites.needs_js = true`.
2. **Structured data:** `<script type="application/ld+json">` blocks with `Product` or `ItemList`; schema.org microdata.
3. **Platform API:** WooCommerce detected (`wp-content`, generator meta) → `/wp-json/wc/store/v1/products?category=…&page=N`; Shopify detected → `/collections/{handle}/products.json?page=N`.
4. **Embedded state JSON:** `__NEXT_DATA__`, `window.__NUXT__`, `window.__INITIAL_STATE__`. A recursive `JsonProductFinder` walks the JSON and returns arrays of objects that have name-like, price-like and image-like keys.
5. **Headless render:** Browsershot `waitUntilNetworkIdle()->bodyHtml()`, then repeat steps 2 and 4 on the rendered HTML. Also read `triggeredRequests()`, re-fetch the JSON/XHR URLs with the HTTP client and run `JsonProductFinder` on them.
6. **Extractor agent:** send a cleaned DOM snapshot (scripts, styles, SVG, header and footer removed; truncated to the model's budget). The agent returns selectors for card, name, price, sale price, image, link, category and next page. Validate them on the page; if valid, save to `sites.selectors`.
7. **Fallback Extractor agent:** send cleaned page text; the agent returns products as JSON.

If a saved recipe suddenly returns zero products, it is marked invalid and step 6 regenerates it (self-healing).

### Jobs and pagination

- `ProcessPageJob(target, page)` handles one page, then dispatches itself for the next page with a 1–3 second delay.
- Next page comes from the recipe, `rel=next`, `?page=N`, or the platform API's page param. It stops when a page adds no new product hashes or `max_pages` is reached.
- Per-domain concurrency of 1 with the `WithoutOverlapping("domain:{host}")` job middleware; different domains run in parallel across workers.
- Timeouts: HTTP 20 s with 2 retries; Browsershot 60 s; queue worker `--timeout=180`.
- `robots.txt` is checked with `spatie/robots-txt`; disallowed URLs are marked `blocked`. A Cloudflare challenge or CAPTCHA page is also marked `blocked` and never bypassed.

### Normalization (`ProductNormalizer`)

- **Price:** convert Bengali digits ০–৯ to 0–9, strip `৳`, `Tk`, `BDT`, commas and spaces, parse decimal; currency defaults to BDT.
- **Sale price:** if two prices are found, the lower one is `sale_price`, the higher one `price`.
- **Image:** first of `src`, `data-src`, `data-lazy-src`, or the largest `srcset` entry; made absolute.
- **Links:** made absolute against the page URL; tracking params (`utm_*`, `fbclid`) removed.
- **Category:** from the listing page heading, breadcrumb, or the URL path segment.
- **Hash:** `sha1(normalized product_url)`; without a product URL, `sha1(name|price|image_url)`.

### Saving and live updates

`ProductUpserter` writes products in batches of 50 with `Product::upsert()` on the unique key `(site_id, hash)`, attaches the `product_scrape_target` pivot, updates counters, and broadcasts `ProductsFound` on the private channel `scrape-job.{id}` through Reverb.

## Database schema

MySQL 8 (utf8mb4) is the default; every migration uses Laravel schema builder only, so the same migrations run on SQL Server. All tables have `id` (bigint) and timestamps unless noted.

### AI configuration

| Table | Columns | Indexes / rules |
| --- | --- | --- |
| `ai_providers` | name, slug, driver (gemini/openai/anthropic/groq/openrouter/ollama/openai\_compatible), subtitle, logo, base\_url, api\_key (text null, encrypted cast; null for Ollama), has\_free\_tier (bool), is\_active, last\_tested\_at, last\_test\_ok (bool), sort\_order | unique slug; "Configured" = key present or Ollama reachable |
| `ai_models` | ai\_provider\_id (FK), model\_key, label, context\_length (int), max\_output\_tokens (int), input\_price, output\_price (decimal 12,6 per 1M tokens; 0 for free), is\_free (bool), supports\_json (bool), is\_active | unique (ai\_provider\_id, model\_key) |
| `ai_agents` | name, role (string, enum cast), ai\_provider\_id (FK null = use default provider), ai\_model\_id (FK null), fallback\_model\_id (FK null), temperature (decimal 3,2), max\_tokens (int null = global), is\_active, is\_default, sort\_order | index (role, is\_active, is\_default); one default per role enforced in observer |
| `agent_prompts` | ai\_agent\_id (FK), version (int), system\_prompt (longText), output\_schema (json), status (draft/live/archived), notes (text), created\_by (FK users) | unique (ai\_agent\_id, version); one live per agent in observer |
| `agent_examples` | ai\_agent\_id (FK), title, input\_snippet (longText), expected\_output (json), source\_url, is\_active | index (ai\_agent\_id, is\_active) |
| `agent_test_cases` | ai\_agent\_id (FK), url, expected\_min\_products (int), required\_fields (json) |  |
| `agent_runs` | ai\_agent\_id, agent\_prompt\_id, ai\_provider\_id, ai\_model\_id, scrape\_target\_id (null), tokens\_in, tokens\_out (int), cost\_usd (decimal 12,6), was\_free (bool), latency\_ms (int), status, error (text) | index (ai\_agent\_id, created\_at) |
| `settings` | key (unique), value (json). Keys: `ai.default_provider_id`, `ai.max_tokens`, `ai.free_only`, `ai.rate_limit_fallback_provider_id`, plus extraction limits |  |

### Extraction data

| Table | Columns | Indexes / rules |
| --- | --- | --- |
| `sites` | domain, name, favicon\_url, platform (woocommerce/shopify/custom/unknown), strategy, needs\_js (bool), selectors (json), pagination (json), recipe\_valid (bool), force\_agent\_id (FK null), max\_pages (int), is\_blocked, notes (text), products\_count (int), last\_success\_at | unique domain |
| `scrape_jobs` | user\_id (FK), status (queued/running/completed/failed), total\_targets, total\_products, started\_at, finished\_at | index (user\_id, created\_at) |
| `scrape_targets` | scrape\_job\_id (FK), site\_id (FK), parent\_id (FK null), url (text), url\_hash (char 40), type (domain/listing), status, strategy\_used, pages\_crawled, products\_found, error (text) | index (scrape\_job\_id), index url\_hash |
| `categories` | site\_id (FK), parent\_id (null), name, slug, source\_url (text) | unique (site\_id, slug) |
| `products` | site\_id (FK), category\_id (FK null), hash (char 40), name, price, sale\_price (decimal 12,2 null), currency (char 3), image\_url, product\_url (text), brand, sku, in\_stock (bool null), raw (json), first\_seen\_at, last\_seen\_at | **unique (site\_id, hash)**, index (site\_id, category\_id) |
| `product_scrape_target` | product\_id (FK), scrape\_target\_id (FK) | primary (product\_id, scrape\_target\_id); no timestamps |
| `product_price_history` | product\_id (FK), price, sale\_price, recorded\_at | index (product\_id, recorded\_at); optional in v1 |
| `exports` | user\_id (FK), scrape\_job\_id (FK null), scope (json), format (csv/xlsx/zip), columns (json), file\_path, row\_count, status, expires\_at | index (user\_id) |

Plus Laravel defaults: `users` (add `is_admin` bool), `jobs`, `failed_jobs`, `job_batches`, `cache`, `sessions`.

### SQL Server notes

- Use driver `sqlsrv` (PHP `sqlsrv` + `pdo_sqlsrv` extensions and Microsoft ODBC Driver 18).
- `json()` columns become `nvarchar(max)`; query JSON in PHP, not with database JSON functions, so code stays portable.
- `upsert()` works on both (MySQL `ON DUPLICATE KEY`, SQL Server `MERGE`); it needs the unique index above.
- Keep indexed string columns ≤ 450 characters on SQL Server; long URLs are `text` and are matched through `url_hash`.
- Avoid MySQL-only features: no `FULLTEXT`, no `JSON_TABLE`, no raw `GROUP_CONCAT`.

## Technical skills & stack

The whole project needs one PHP/Laravel developer comfortable with React; no Python, no separate frontend framework.

| Area | Technology | Skills needed | Used for |
| --- | --- | --- | --- |
| Language | PHP 8.3+ (8.4 recommended) | Enums, readonly classes, typed class constants, property hooks (8.4), attributes | All backend code |
| Framework | Laravel 13 | Eloquent, migrations, observers, policies, service container, form requests | Core app |
| AI layer | Laravel AI SDK (`laravel/ai`) | Dynamic providers with `Ai::build()`, agents, structured output, token usage | All AI calls, every provider |
| AI providers | Gemini, OpenAI, Anthropic, Groq, OpenRouter, Ollama | API keys, model limits, free-tier rate limits, prompt design, JSON-only responses | Agents |
| Local AI | Ollama | Installing, pulling and serving a local instruct model | Free, private AI calls |
| Queues | Laravel queues (database driver) | Job middleware, batching, retries, delayed dispatch, `WithoutOverlapping` | Scraping and export jobs |
| Realtime | Laravel Reverb, Laravel Echo | Private channels, broadcast events | Live preview rows and status |
| Admin UI | Filament 5 (Livewire 4, Tailwind CSS 4) | Resources, custom pages, schemas, actions, widgets | AI providers, agents, training, sites |
| User UI | Laravel React starter kit (Inertia, React, TypeScript, Tailwind CSS 4, shadcn/ui) | Components, hooks, TanStack Table, typed props | Extract, preview, export pages |
| HTTP + parsing | Laravel HTTP client, Symfony DomCrawler, CSS Selector | Retries, timeouts, CSS selectors, XPath, JSON-LD parsing | Static page extraction |
| Headless browser | Spatie Browsershot (Node + Puppeteer) | Waiting for network idle, reading rendered HTML and triggered requests | JavaScript-rendered sites |
| Database | MySQL 8 or SQL Server 2019+ | Indexing, unique keys, upsert, JSON columns | Storage |
| Export | maatwebsite/excel | `FromQuery`, chunked reading, multiple sheets, queued exports | CSV, XLSX, ZIP |
| Security | Laravel encryption, signed URLs, policies | Encrypted API keys, rate limiting | Keys, downloads, abuse limits |
| Testing | Pest | Feature tests, HTTP fakes, AI SDK fakes, HTML fixtures | Resolver rules, extractors, exports |
| Dev environment | Laragon or Herd (Windows), PHP 8.3+, Composer 2, Node 20+, Ollama | Local PHP, MySQL, Node, local AI setup | Local development |

## Folder structure, packages, setup

### Folder structure

```text
catalogix/
├── app/
│   ├── Enums/                 AgentRole, AiDriver, JobStatus, TargetStatus, ExportFormat, Platform
│   ├── Models/                AiProvider, AiModel, AiAgent, AgentPrompt, AgentExample, AgentTestCase,
│   │                          AgentRun, Site, ScrapeJob, ScrapeTarget, Category, Product, Export, Setting
│   ├── Observers/             AiAgentObserver, AgentPromptObserver
│   ├── Ai/Agents/             ExtractorAgent, FallbackExtractorAgent, DiscoveryAgent,
│   │                          NormalizerAgent, ValidatorAgent (laravel/ai agents, structured output)
│   ├── Services/
│   │   ├── AI/                ProviderFactory, AgentResolver, ConfigSnapshot, ModelSync,
│   │   │                      PromptBuilder, ProviderHealthCheck
│   │   └── Extraction/        PageFetcher, HtmlCleaner, PlatformDetector, JsonProductFinder,
│   │                          ProductNormalizer, ProductUpserter, UrlNormalizer, RecipeValidator,
│   │                          Strategies/ (RecipeStrategy, JsonLdStrategy, PlatformApiStrategy,
│   │                          EmbeddedStateStrategy, HeadlessStrategy, AiRecipeStrategy, AiDirectStrategy)
│   ├── Jobs/                  StartScrapeJob, DiscoverDomainJob, ProcessPageJob, ExportProductsJob
│   ├── Events/                ProductsFound, TargetStatusChanged, ExportReady
│   ├── Exports/               ProductsExport, SiteSheet
│   ├── Filament/              Resources/, Pages/AiProviders.php, Pages/AiTraining.php,
│   │                          Pages/Settings.php, Widgets/
│   └── Http/Controllers/      ExtractController, PreviewController, ExportController, HistoryController
├── resources/js/
│   ├── pages/                 extract.tsx, preview.tsx, history.tsx, exports.tsx
│   └── components/            url-chip-input.tsx, product-table.tsx, site-tabs.tsx,
│                              export-dialog.tsx, status-badge.tsx
├── database/migrations/
├── database/seeders/          AiProviderSeeder (6 providers, free flags), AgentSeeder (5 roles)
├── routes/                    web.php, channels.php
└── tests/Feature/             AgentResolverTest, FreeOnlyModeTest, ExtractionStrategiesTest, ExportTest
```

### Packages

| Type | Packages |
| --- | --- |
| Composer | `laravel/framework:^13.0`, `laravel/ai`, `filament/filament:^5.0`, `inertiajs/inertia-laravel`, `laravel/reverb`, `spatie/browsershot`, `symfony/dom-crawler`, `symfony/css-selector`, `spatie/robots-txt`, `maatwebsite/excel`, `pestphp/pest` |
| npm | React starter kit defaults (`@inertiajs/react`, `react`, `typescript`, `tailwindcss` 4, shadcn/ui), plus `@tanstack/react-table`, `laravel-echo`, `pusher-js`, `puppeteer` |
| PHP extensions | `pdo_mysql` (or `pdo_sqlsrv` + `sqlsrv`), `zip`, `gd`, `mbstring`, `intl`, `curl` |
| Optional local AI | Ollama with one instruct model pulled (for example a Llama or Qwen 7B–8B model) |

Confirm each package's Laravel 13 / Filament 5 support at install time; on Windows PowerShell use `~` instead of `^` in Composer version constraints.

### Environment

```dotenv
APP_NAME=Catalogix
DB_CONNECTION=mysql          # or sqlsrv
DB_HOST=127.0.0.1
DB_PORT=3306                 # 1433 for SQL Server
DB_DATABASE=catalogix
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=catalogix
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
BROWSERSHOT_NODE_BINARY="C:\\Program Files\\nodejs\\node.exe"
BROWSERSHOT_NPM_BINARY="C:\\Program Files\\nodejs\\npm.cmd"
```

AI provider keys are **not** in `.env`; they are entered in the admin panel and stored encrypted.

### Run locally

```bash
laravel new catalogix              # choose: React starter kit, Pest, MySQL
cd catalogix
composer require laravel/ai filament/filament:"~5.0" laravel/reverb spatie/browsershot \
  symfony/dom-crawler symfony/css-selector spatie/robots-txt maatwebsite/excel
php artisan filament:install --panels
php artisan install:broadcasting   # Reverb
npm install puppeteer @tanstack/react-table
php artisan migrate --seed         # admin user, 6 providers, 5 default agents
npm run dev
php artisan serve
php artisan reverb:start
php artisan queue:work --queue=scrape,export,default --timeout=180
ollama serve                       # optional: free local AI
```

Run 2–4 `queue:work` processes to scrape several domains in parallel. In production, use Supervisor (Linux) or NSSM (Windows) to keep workers and Reverb alive.

## Build phases

Build in this order; each phase ends with its acceptance check before the next starts.

### Phase 1: Foundation & AI configuration

- [ ] Laravel 13 (React starter kit, PHP 8.3+) + Filament 5 + `laravel/ai`; MySQL connection; `is_admin` gate for `/admin`
- [ ] Migrations and models for AI tables and `settings`; seeder for 6 providers (with free flags) and 5 default agents on Gemini free tier
- [ ] `AgentRole` and `AiDriver` enums, `AiAgentObserver` (single default, no deactivating the default), `AgentPromptObserver` (single live)
- [ ] AI Providers page: Global Settings card + provider cards with Active / Configured / Free badges and "Test connection"; AI Models resource; AI Agents resource
- [ ] `ProviderFactory`, `AgentResolver` (with free-only mode and rate-limit fallback), `ConfigSnapshot`, with Pest tests using the AI SDK fakes

**Accept:** admin can add agents, toggle active/default; resolver returns the right agent per role; an inactive agent is never returned; tests pass.

### Phase 2: Extraction core (no AI)

- [ ] Migrations and models for sites, jobs, targets, categories, products, pivot
- [ ] Extract page with `UrlChipInput`; `UrlNormalizer`; `StartScrapeJob`
- [ ] `PageFetcher`, `JsonLdStrategy`, `PlatformApiStrategy`, `EmbeddedStateStrategy`, `HeadlessStrategy` (Browsershot)
- [ ] Pagination in `ProcessPageJob`; `ProductNormalizer`; `ProductUpserter`
- [ ] Preview page (loads from DB)

**Accept:** chaldal.com/popular and one WooCommerce shop return products with name, price and image; a second run updates rows without duplicates.

### Phase 3: AI in the loop

- [ ] `HtmlCleaner`, `PromptBuilder`, `AiRecipeStrategy`, `RecipeValidator`, `AiDirectStrategy`
- [ ] `agent_runs` logging with tokens and cost; fallback model on API error
- [ ] Self-healing: invalid recipe triggers regeneration
- [ ] `DiscoverDomainJob` (sitemap, nav links, URL patterns) + category picker step

**Accept:** an unknown custom site gets a recipe from the Extractor agent on the first run; the second run makes zero AI calls and returns the same products.

### Phase 4: Live preview & export

- [ ] Reverb events `ProductsFound`, `TargetStatusChanged`; Echo listener; streaming `ProductTable` with `SiteTabs`
- [ ] Filters, search, column picker, row selection
- [ ] `ExportDialog`, `ExportProductsJob` (CSV, XLSX multi-sheet, ZIP), signed download, `ExportReady` event
- [ ] History and Exports pages

**Accept:** a user submits 3 sites, sees rows appear live per tab, downloads one site as CSV and all sites as one XLSX with a sheet per site.

### Phase 5: AI Training & hardening

- [ ] AI Training page: Instructions, Examples, Playground, Test Set, Logs
- [ ] Draft → test → live → rollback flow; save-as-example; save-as-site-recipe
- [ ] Dashboard widgets (jobs, products, AI cost, failing sites); robots.txt check; rate limits per user
- [ ] Verify the full test suite on SQL Server

**Accept:** admin drafts a new prompt, compares it to live on the test set, makes it live, and can roll back; all tests pass on MySQL and SQL Server.

## AI build prompt

Paste this whole document into your AI coding tool, then paste the prompt below. It builds the full application end to end, phase by phase, without waiting for you between phases.

```text
ROLE
You are two people working as one:
1. A senior Laravel engineer.
2. A senior product designer from a top product studio, responsible for the admin dashboard and the
   user-facing UI. You design before you code and hold the UI to studio-level craft.

MISSION
Build the complete, production-ready "Catalogix" application: an AI-powered multi-site product
extractor. A user pastes one or many website URLs (for example https://chaldal.com/popular), the app
extracts every product (name, price, sale price, image URL, product URL, category, stock), follows
pagination, streams results into a live preview grouped by site, and exports per site or all
together as CSV, XLSX or ZIP. Admins configure AI providers, models and agents, and train agents
with instructions, examples and site recipes. The attached document is the source of truth; if
something is missing, choose the simplest Laravel-native option and list the assumption.

Build ALL FIVE PHASES below, in order, in one continuous run. Do not stop after a phase to ask for
permission. After each phase, verify its Accept criteria, fix anything failing, then continue.

HARD CONSTRAINTS
- PHP 8.3 minimum (target 8.4). Laravel 13. No Python anywhere.
- MySQL 8 by default; must also run on SQL Server (schema builder only, no MySQL-specific SQL,
  JSON handled in PHP).
- Admin: Filament 5 at /admin, gated by users.is_admin. User app: Laravel React starter kit
  (Inertia, React, TypeScript, shadcn/ui) at /app, TanStack Table.
- Styling: Tailwind CSS 4 only, everywhere. No other CSS framework, no inline styles, no CSS files
  beyond the Tailwind entry file and @theme tokens.
- Queue: database driver. Realtime: Laravel Reverb + Echo.
- Scraping: Laravel HTTP client + Symfony DomCrawler; Spatie Browsershot for JS-rendered pages.
- AI: official Laravel AI SDK (laravel/ai). Providers: Gemini, OpenAI, Anthropic, Groq, OpenRouter,
  Ollama, built at runtime from the ai_providers table with Ai::build(). Keys encrypted in the DB,
  never in .env. Never hardcode a model name; every AI call goes through AgentResolver.
- Only active agents run; exactly one default agent per role. Free-only mode limits the resolver to
  models with is_free = true. HTTP 429 retries once on the rate-limit fallback provider.
- Zero-spend defaults: Gemini free tier as default provider, Ollama at http://localhost:11434.
- Products unique on (site_id, hash), saved with upsert. Never bypass logins, CAPTCHAs or bot
  challenges; respect robots.txt and mark such targets "blocked".

DESIGN SYSTEM (one visual language for /admin and /app)
- Tokens once with Tailwind 4 @theme: neutral zinc base, one brand accent, semantic success /
  warning / danger / info, surfaces, borders. Light and dark mode.
- shadcn/ui themed with these tokens; custom Filament theme (php artisan make:filament-theme)
  using the same tokens.
- Inter (or Geist) for UI, JetBrains Mono for URLs, keys and code. Scale 12/14/16/20/24/30.
- 4px grid, radius 8 for controls and 12 for cards, 1px borders over heavy shadows.
- Icons: lucide-react in /app, Heroicons in Filament.
- Before each new screen write a 5-line design brief: user, main task, key information, primary
  action, states. Design every state: empty, loading, partial, error, blocked, success.
- Responsive 375px to 1440px, WCAG AA contrast, visible focus rings, motion under 200ms, realistic
  Bangladeshi product names and ৳ prices (no lorem ipsum). Clarity and density over decoration.

============================================================
PHASE 1: FOUNDATION & AI CONFIGURATION
- Project setup: Laravel 13 React starter kit, Filament 5, laravel/ai, Reverb, Pest, MySQL.
- Design system: @theme tokens, fonts, dark mode, Filament custom theme, base layout for /app
  (top bar, user menu, nav: Extract, History, Exports).
- Migrations + models: ai_providers, ai_models, ai_agents, agent_prompts, agent_examples,
  agent_test_cases, agent_runs, settings; users.is_admin.
- Enums: AgentRole (discovery, extractor, fallback_extractor, normalizer, validator), AiDriver.
- Observers: one default agent per role; the default cannot be deactivated; one live prompt per agent.
- Services: ProviderFactory, AgentResolver (site override, role default, fallback model,
  rate-limit fallback, free-only mode), ConfigSnapshot, ModelSync, ProviderHealthCheck.
- Filament: AI Providers page (Global Settings card + 2-column provider cards with Active /
  Configured / Free badges, masked key with show/copy, model select with "(Free)", Get API key link,
  Test connection with latency), AI Models resource, AI Agents resource grouped by role,
  Settings page.
- Seeders: admin user, 6 providers with free flags, 5 default agents with starter prompts.
ACCEPT: admin can add providers and agents, toggle active/default; resolver returns the right agent
per role; inactive agents never returned; free-only mode works; Test connection works with Ollama.

============================================================
PHASE 2: EXTRACTION CORE (NO AI)
- Migrations + models: sites, scrape_jobs, scrape_targets, categories, products,
  product_scrape_target, product_price_history, exports.
- Extract page: URL chip input (favicon, valid / invalid / duplicate states, limit from settings),
  sample URLs, submit -> StartScrapeJob.
- UrlNormalizer, PageFetcher (timeouts, retries, user agent, robots.txt via spatie/robots-txt).
- Strategies in ladder order: RecipeStrategy, JsonLdStrategy, PlatformApiStrategy (WooCommerce
  Store API, Shopify products.json), EmbeddedStateStrategy (__NEXT_DATA__, __NUXT__,
  __INITIAL_STATE__ via JsonProductFinder), HeadlessStrategy (Browsershot rendered HTML +
  triggered JSON requests). Stop at first strategy with >= 3 products with name and price; save the
  winning strategy on the site.
- ProcessPageJob with pagination (recipe, rel=next, ?page=N, platform page param), stop on no new
  hashes or max_pages; WithoutOverlapping per domain; 1-3 s delay between pages.
- ProductNormalizer (Bengali digits, ৳/Tk/BDT, sale vs regular price, lazy images, absolute URLs,
  category from heading/breadcrumb/URL, hash), ProductUpserter (batches of 50, pivot, counters).
- Preview page (static load from DB) with site tabs and product table.
ACCEPT: chaldal.com/popular and one WooCommerce shop return products with name, price and image;
re-running updates rows without duplicates; blocked sites are marked, not bypassed.

============================================================
PHASE 3: AI IN THE LOOP
- app/Ai/Agents: ExtractorAgent, FallbackExtractorAgent, DiscoveryAgent, NormalizerAgent,
  ValidatorAgent, all using laravel/ai structured output.
- HtmlCleaner (strip scripts, styles, SVG, header, footer; trim to model budget), PromptBuilder
  (live prompt + JSON schema + site notes + up to 3 active examples + page content).
- AiRecipeStrategy (selectors -> RecipeValidator -> save to sites.selectors), AiDirectStrategy.
- Self-healing: recipe returning zero products is marked invalid and regenerated.
- agent_runs logging: provider, model, tokens, cost, was_free, latency, status.
- DiscoverDomainJob for bare domains (sitemap.xml, robots.txt Sitemap, nav links, URL patterns,
  DiscoveryAgent only for ambiguous links) + category picker step on the Extract page.
ACCEPT: an unknown custom site gets a recipe on the first run; the second run makes zero AI calls
and returns the same products; all AI calls appear in agent_runs.

============================================================
PHASE 4: LIVE PREVIEW & EXPORT
- Events ProductsFound, TargetStatusChanged, ExportReady on private channel scrape-job.{id}.
- Live Preview: sticky header with overall progress; site tabs with status dot and count; rows
  fade in live; skeleton rows; toolbar with search, category filter, price range, column picker;
  row selection; designed empty / error / blocked states.
- Export dialog: segmented scope (this site, selected rows, all sites) and format (CSV, XLSX, ZIP),
  column checklist, XLSX layout (sheet per site or one sheet + source column), live file-name preview.
- ExportProductsJob with maatwebsite/excel (FromQuery, chunked, multiple sheets), signed download
  URL valid 24 hours; small exports (<= 2,000 rows) download directly.
- History page (re-run, re-export, delete) and Exports page.
ACCEPT: user submits 3 sites, sees rows appear live per tab, downloads one site as CSV and all sites
as one XLSX with a sheet per site.

============================================================
PHASE 5: AI TRAINING, DASHBOARD & HARDENING
- AI Training page: left rail (agent, prompt versions with live badge), tabs: Instructions (prompt +
  JSON schema editor, save = new draft), Examples, Playground (input and output side by side with
  tokens, cost, time; Save as example; Save as site recipe), Test Set (run draft vs live: products
  found, field fill rate, cost; Make live), Logs (filters + daily cost chart). Rollback any version.
- Filament resources: Sites, Scrape Jobs (read-only, retry).
- Admin dashboard widgets: jobs today, products extracted, AI cost today, free vs paid calls,
  failing sites, recent jobs.
- Per-user rate limits, policies on every resource, error pages styled with the design system.
- Full test suite green on MySQL; verify migrations and tests on SQL Server.
ACCEPT: admin drafts a prompt, compares it with live on the test set, makes it live and rolls
back; dashboard shows real numbers; all tests pass.

============================================================
CODE RULES
- Services in app/Services, AI agents in app/Ai/Agents, one class per strategy, constructor injection.
- Backed enums for roles, drivers and statuses. Form Requests for validation. Policies for auth.
- Every job sets timeout, tries and backoff.
- Reusable UI components in resources/js/components; extract components or cva variants instead of
  repeating long Tailwind class lists.
- Pest feature tests for every resolver rule, free-only mode, each extraction strategy (saved HTML
  fixtures), upsert dedupe and exports. No live HTTP or AI in tests: Http::fake() and AI SDK fakes.

OUTPUT FORMAT (repeat for every phase)
1. Phase title.
2. Design brief for each new screen.
3. List of files to create or change.
4. Full code for every file. No placeholders, no "...", no "rest stays the same".
5. Commands to run.
6. Accept check with a UI checklist, and fixes for anything that failed.

If you reach your output limit, stop at the end of a complete file and write
"CONTINUE FROM: <phase> / <next file>". When I reply "continue", resume exactly there.

FINAL DELIVERY (after Phase 5)
- README.md: requirements, install steps for Windows (Laragon/Herd) and Linux, .env reference,
  running queue workers, Reverb and Ollama, deployment notes (Supervisor or NSSM).
- Seeded demo: admin login, providers, agents, and one sample extraction job.
- Final checklist: every Accept criterion from Phases 1-5 with pass/fail.

Start now with Phase 1 and continue through Phase 5.
```

Run each phase's Accept check yourself as the code arrives. When the AI hits its output limit, reply "continue" and it resumes from the file it names. If the AI drifts (adds Python, hardcodes a model, uses MySQL-only SQL), point it back to the constraint by name.

## Sources

- [Laravel versions: latest release and support status](https://benjamincrozat.com/laravel-versions) (Laravel 13, PHP 8.3–8.5)
- [Filament v5 upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide) (requirements)
- [Laravel AI SDK docs](https://laravel.com/docs/ai-sdk.md) (supported providers)
- [Free LLM APIs compared, OpenRouter blog](https://openrouter.ai/blog/tutorials/free-llm-apis-compared/)
- [Best LLM API with a free tier in 2026, CostBench](https://www.costbench.com/best/best-llm-api-with-free-tier/) (Gemini, Groq, OpenRouter free limits)
