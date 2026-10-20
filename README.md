# Catalogix

Type a shop's domain, tick the routes that list products, and get every product — name, price, sale price, category, stock, link and a locally stored image — in a live listing you can export as Excel (with pictures in the cells), CSV or an images ZIP.

The source of truth for scope is [docs/Catalogix — Domain Discovery, Local Images & Excel Export.md](docs/Catalogix%20—%20Domain%20Discovery,%20Local%20Images%20&%20Excel%20Export.md). There is no AI and no paid service; one Laravel app does everything, with a small Python + Playwright helper for JavaScript-heavy pages.

## How it works

1. **Discover** (`DiscoverDomainJob`) — reads `robots.txt` sitemaps and `sitemap.xml`, the WooCommerce / Shopify category list, and the site menu two levels deep; filters by URL pattern; probes each candidate and scores it (0–100) with an estimated product count and three sample images. High-score routes are pre-ticked.
2. **Extract** (`ProcessPageJob`, one page at a time per domain, 1–3 s apart) — runs the no-AI ladder and stops at the first strategy with ≥ 3 priced products:
   saved recipe → JSON-LD / microdata → platform API → embedded state JSON → captured network JSON (Python helper) → automatic card detection.
   The winner is remembered on the site; card detection also saves its selectors as a recipe, so the next run starts at step 1. Pagination follows the recipe's Next, `rel=next`, common Next links, `?page=N` or the platform API's page, until a page adds nothing new or `CATALOGIX_MAX_PAGES` is reached.
3. **Images** (`DownloadImageJob`, at most 4 at a time per site) — validated, deduplicated by URL, stored as `storage/app/public/products/{domain}/{category}/{product}.webp` plus a 200 px `_thumb.jpg`.
4. **Export** (`ExportProductsJob`) — CSV, XLSX, XLSX with thumbnails anchored in column A, or an images ZIP; above 3,000 rows Excel-with-images splits into files per site / category / part inside a ZIP. Links are signed for 24 hours.
5. **Visual recipe picker** (admins) — for layouts the ladder can't read: click a name, price, image and Next on the real page, preview every match, save.

Live progress uses Reverb (`RoutesDiscovered`, `ProductsFound`, `ScrapeProgress`, `ImageDownloaded`, `ExportReady`); API clients poll.

Pages behind a login, CAPTCHA or bot challenge, and anything `robots.txt` disallows, are marked **blocked** and never bypassed.

## Requirements

- PHP 8.3+ (8.4 recommended) with `pdo_mysql`, `gd` (WebP support), `zip`, `intl`, `mbstring`, `curl`, `fileinfo`
- Composer 2, Node 20+ (22 used in development)
- MySQL 8
- Optional, for JavaScript-heavy sites: Python 3.10+ (3.12 recommended) with Playwright

## Install

### Windows (Laragon or Herd)

```powershell
composer install
copy .env.example .env
php artisan key:generate
# create the database "catalogix" (Laragon: Menu › MySQL › Create database)
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

### Linux

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -e "CREATE DATABASE catalogix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
```

Seeded logins (change them outside local development):

| Email | Password | Role |
| --- | --- | --- |
| admin@catalogix.test | password | Admin — Sites & recipes, visual recipe picker |
| user@catalogix.test | password | Regular user |

### Python helper (optional)

Only needed for pages that build their product list with JavaScript, for JavaScript menus during discovery, and for infinite scroll. Without it Catalogix still works on server-rendered sites and reports a clear hint on the rest.

```bash
pip install -r scraper/requirements.txt
playwright install chromium
```

Set `PYTHON_BIN` in `.env` (`python` on Windows, `python3` on Linux). Laravel calls `scraper/render.py` through the `Process` facade; there is no separate Python server.

## Run locally

Four processes:

```bash
php artisan serve                     # http://localhost:8000
npm run dev                           # Vite (or rely on npm run build)
php artisan reverb:start              # live updates on :8080
php artisan queue:work --queue=discovery,pages,images,exports,default --timeout=960
```

Run 2–4 workers to extract several domains in parallel; jobs for the same domain still go one at a time.
`composer dev` starts the server, a queue listener, logs and Vite together (add Reverb separately).

## Configuration

`.env` keys added by Catalogix (defaults in [config/catalogix.php](config/catalogix.php)):

| Key | Default | Meaning |
| --- | --- | --- |
| `CATALOGIX_USER_AGENT` | `CatalogixBot/1.0 (...)` | Sent with every request; robots.txt rules for `CatalogixBot` apply |
| `CATALOGIX_MAX_PAGES` | 30 | Page limit per route |
| `CATALOGIX_PAGE_DELAY_MIN` / `_MAX` | 1 / 3 | Seconds between pages of one domain |
| `CATALOGIX_MAX_PROBES` | 40 | Candidate routes fetched and scored per discovery |
| `CATALOGIX_IMAGE_CONCURRENCY` | 4 | Parallel image downloads per site |
| `CATALOGIX_EXPORT_IMAGE_ROW_LIMIT` | 3000 | Rows per Excel file with pictures before splitting |
| `PYTHON_BIN` | `python` | Python executable for the helper |
| `CATALOGIX_RENDER_TIMEOUT` | 45 | Seconds the helper waits for a page |
| `DB_QUEUE_RETRY_AFTER` | 1000 | Must stay above the longest job (discovery, 900 s) |

Also required: `QUEUE_CONNECTION=database`, `BROADCAST_CONNECTION=reverb` and the `REVERB_*` / `VITE_REVERB_*` keys.

## REST API

Sanctum tokens, same features as the web app. All routes except `POST /api/tokens` and the signed download need `Authorization: Bearer <token>`.

| Method | Endpoint | Does |
| --- | --- | --- |
| POST | `/api/tokens` `{email, password, device_name}` | Issues a token |
| POST | `/api/discoveries` `{domain}` | Starts discovery → `202` with the discovery |
| GET | `/api/discoveries/{id}` | Status + routes (score, est. products, sample images) |
| POST | `/api/discoveries/{id}/extract` `{route_ids[]}` | Starts extraction → `202` with the job |
| GET | `/api/jobs/{id}` | Status, per-route progress, product counts, image counts |
| GET | `/api/jobs/{id}/products?route=&category=&search=&page=&per_page=` | Products with local image and thumbnail URLs |
| POST | `/api/jobs/{id}/images/retry` | Requeues failed image downloads |
| POST | `/api/exports` `{job_id, scope, format, embed_images, layout}` | Builds an export → `202` |
| GET | `/api/exports/{id}` | Status, size and, when ready, `download_url` |
| GET | `/api/exports/{id}/download?signature=…` | The file (signed, 24 h) |

`scope`: `route` (with `route_id`), `site` (with `job_id`), `all`. `format`: `xlsx_images`, `xlsx`, `csv`, `zip_images` (or `xlsx` + `embed_images: true`). `layout`: `per_site` or `single`.

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/tokens -H 'Accept: application/json' \
  -d email=user@catalogix.test -d password=password -d device_name=cli | jq -r .token)
curl -s -X POST localhost:8000/api/discoveries -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -d domain=chaldal.com
```

Limits: 10 discoveries and 20 exports per minute per user, 120 API calls per minute.

## Tests

```bash
php artisan test        # Pest, against the MySQL database "catalogix_test" (create it first)
composer test           # Pint + Larastan + tests
npm run check && npm run types:check
```

No test touches a real site or the Python helper: HTTP is faked with saved HTML/JSON fixtures in `tests/Fixtures`, and `Http::preventStrayRequests()` / `Process::preventStrayProcesses()` guard every test.

## Deployment

- `php artisan config:cache route:cache view:cache` and `npm run build`.
- Keep workers and Reverb alive with **Supervisor** (Linux) or **NSSM** (Windows):

  ```ini
  [program:catalogix-worker]
  command=php /var/www/catalogix/artisan queue:work --queue=discovery,pages,images,exports,default --timeout=960 --sleep=1
  numprocs=3
  process_name=%(program_name)s_%(process_num)02d
  autorestart=true
  stopwaitsecs=1000

  [program:catalogix-reverb]
  command=php /var/www/catalogix/artisan reverb:start
  autorestart=true
  ```

  On Windows: `nssm install CatalogixWorker C:\laragon\bin\php\php-8.4.24\php.exe "artisan queue:work --queue=discovery,pages,images,exports,default --timeout=960"` with the app folder as the startup directory; repeat for `reverb:start`.
- Schedule `php artisan schedule:run` every minute (cron or Windows Task Scheduler): it removes expired export files hourly and prunes week-old failed jobs.
- Product images belong to the source sites: fine for internal research or with the supplier's agreement, not for commercial republishing without permission.

## Decisions beyond the spec

- **Image rows**: `product_images` is unique on `(product_id, url_hash)` with an index on `url_hash`, instead of unique on `url_hash` alone, so several products can share one picture; the file is still downloaded only once and reused.
- **Links are optional for cards**: Chaldal's cards open products in a modal and have no link, so card detection needs an image and a price; links break ties.
- **Network JSON skips the browser** when the static HTML already shows product cards, so server-rendered sites never wait for Chromium.
- **Export scope "all"** means every product from all of the user's jobs.
- **Admins** are users with `is_admin = true`; there is no separate admin panel.
- **Tokens**: `POST /api/tokens` issues Sanctum tokens (the spec lists the API but not how to authenticate).
