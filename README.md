# Catalogix

Paste one or more shop addresses and Catalogix does the rest: it finds the pages that list products, collects every product — name, SKU, brand, category, subcategory, price, sale price, stock, description, link — saves each picture locally, and offers a one-click Excel file with the pictures in the rows. No ticking, no settings; each website shows in plain words what is happening until it is **Ready**.

The source of truth for scope is [docs/Catalogix — Domain Discovery, Local Images & Excel Export.md](docs/Catalogix%20—%20Domain%20Discovery,%20Local%20Images%20&%20Excel%20Export.md). There is no AI and no paid service; one Laravel app does everything, with a small Python + Playwright helper for JavaScript-heavy pages.

## How it works

The **Websites** page takes one address or many (commas, spaces or new lines; full links are fine, `www.` and paths are dropped, duplicates merged, up to 20 at a time). Each website then runs through the steps below on its own and shows one of: *Waiting · Finding products · Collecting products · Saving pictures · Ready* (or *No products found · Website blocked us · Couldn't finish*, with **Try again**). Ready websites offer **View products** (with a details panel per product) and **Download Excel**. *Choose pages myself* (in the ⋯ menu) still opens the scored route checklist for people who want control.

1. **Discover** (`DiscoverDomainJob`) — reads `robots.txt` sitemaps and `sitemap.xml`, the WooCommerce / Shopify category list, and the site menu two levels deep; filters by URL pattern; probes each candidate and scores it (0–100) with an estimated product count and three sample images. Websites added from the app then extract the recommended routes automatically: every route scoring ≥ 60 except the home page, else every route ≥ 30, up to `CATALOGIX_AUTO_MAX_ROUTES`.
2. **Extract** (`ProcessPageJob`, one page at a time per domain, 1–3 s apart) — runs the no-AI ladder and stops at the first strategy with ≥ 3 priced products:
   saved recipe → JSON-LD / microdata → platform API → embedded state JSON → captured network JSON (Python helper) → automatic card detection.
   The winner is remembered on the site; card detection also saves its selectors as a recipe, so the next run starts at step 1. Pagination follows the recipe's Next, `rel=next`, common Next links, `?page=N` or the platform API's page, until a page adds nothing new or `CATALOGIX_MAX_PAGES` is reached.
3. **Images** (`DownloadImageJob`, at most 4 at a time per site) — validated, deduplicated by URL, stored as `storage/app/public/products/{domain}/{category}/{product}.webp` plus a 200 px `_thumb.jpg`.
4. **Export** (`ExportProductsJob`) — **Download Excel** is one click: Excel with the picture in column A of every row, then Name · SKU · Brand · Category · Subcategory · Price · Sale price · Discount · Stock · Description · Product link (clickable). Other formats: Excel or CSV with picture links instead of pictures (plus the local image path), or a pictures ZIP; above 3,000 rows Excel-with-images splits into files per site / category / part inside a ZIP. Links are signed for 24 hours.
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

One command starts the server, Vite, Reverb, and eight independent workers for discovery, product pages, images, exports, and default tasks:

```bash
composer dev
```

Or run the pieces yourself:

```bash
php artisan serve                     # http://localhost:8000
npm run dev                           # Vite (or rely on npm run build)
php artisan reverb:start              # live updates on :8080 (optional: pages poll without it)
php artisan catalogix:work
```

`composer dev` starts and monitors all workers automatically. Restart workers after PHP changes with `php artisan queue:restart`; the manager replaces them when active work finishes. A plain `queue:work` only listens to the default queue, so use the manager for Catalogix.

The Websites form supports an optional **Maximum products per website**. Collection stops at that many unique products, then finishes pending pictures. **Pause** and **Resume** hold or continue a website or job; an in-flight request can finish. Products remain available while paused. Resume takes up to 30 seconds.

For automatic startup after reboot, install the production service once. See [Background processing](docs/Background%20processing.md) for Linux Supervisor and the Windows NSSM installer.

## Configuration

On the products page, **View** opens product details with category, brand, website, product link and collection sources. Clicking a picture opens an in-page preview; **Download image** saves the local picture as an attachment. Source pictures can be previewed while their local copies are being saved.

Check individual products or the page checkbox to select rows across pages. **Export selected** prepares Excel with pictures for those products. **Select all matching products** includes matches on other pages, with the option to uncheck exceptions. **Export options** also supports filtered products, the whole collection, a website section, or all websites in Excel, CSV or an image ZIP. **Export all products** always exports the entire collection.

Website collections can overlap; their counts are not additive. Product lists and exports contain unique products. Product category counts reflect the current collection/search/picture filters and include **Uncategorized** when necessary. Broad listings such as "All Products" no longer overwrite specific categories. Existing broad labels can be repaired from saved source data using `php artisan catalogix:repair-categories --site=example.com` (preview with `--dry-run`).

`.env` keys added by Catalogix (defaults in [config/catalogix.php](config/catalogix.php)):

| Key | Default | Meaning |
| --- | --- | --- |
| `CATALOGIX_USER_AGENT` | `CatalogixBot/1.0 (...)` | Sent with every request; robots.txt rules for `CatalogixBot` apply |
| `CATALOGIX_MAX_PAGES` | 30 | Page limit per route |
| `CATALOGIX_PAGE_DELAY_MIN` / `_MAX` | 1 / 3 | Seconds between pages of one domain |
| `CATALOGIX_MAX_PROBES` | 40 | Candidate routes fetched and scored during manual discovery |
| `CATALOGIX_AUTO_MAX_PROBES` | 12 | Candidate routes checked during automatic collection |
| `CATALOGIX_AUTO_MENU_PAGES` | 3 | Second-level menu pages checked during automatic discovery |
| `CATALOGIX_MAX_SITEMAP_REQUESTS` | 12 | Total sitemap requests per discovery |
| `CATALOGIX_IMAGE_CONCURRENCY` | 4 | Parallel image downloads per site |
| `CATALOGIX_EXPORT_IMAGE_ROW_LIMIT` | 3000 | Rows per Excel file with pictures before splitting |
| `PYTHON_BIN` | `python` | Python executable for the helper |
| `CATALOGIX_RENDER_TIMEOUT` | 45 | Seconds the helper waits for a page |
| `DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER` | 1020 | Must exceed the longest worker timeout (960 s) |

Also required: `QUEUE_CONNECTION=database`, `BROADCAST_CONNECTION=reverb` and the `REVERB_*` / `VITE_REVERB_*` keys.

## REST API

Sanctum tokens, same features as the web app. All routes except `POST /api/tokens` and the signed download need `Authorization: Bearer <token>`.

| Method | Endpoint | Does |
| --- | --- | --- |
| POST | `/api/tokens` `{email, password, device_name}` | Issues a token |
| POST | `/api/discoveries` `{domain}` or `{domains: [...]}`, optional `auto_extract`, `max_products` | Starts discovery → `202` with the discovery (or a list). With `auto_extract: true` the recommended routes are extracted automatically, as in the web app |
| GET | `/api/discoveries/{id}` | Status + routes (score, est. products, sample images) |
| POST | `/api/discoveries/{id}/extract` `{route_ids[]}` | Starts extraction → `202` with the job |
| POST | `/api/discoveries/{id}/pause` or `/resume` | Pause or resume the website collection |
| POST | `/api/jobs/{id}/pause` or `/resume` | Pause or resume a job and its pending images |
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

Follow [Background processing](docs/Background%20processing.md). Production workers must be installed as server services once, then the system starts and restarts them automatically; website visitors never run commands.

- Linux: use [deploy/supervisor/catalogix.conf](deploy/supervisor/catalogix.conf), with dedicated capacity for each queue, automatic boot startup, restart, graceful shutdown, and rotated logs.
- Windows: run [deploy/windows/install-workers.ps1](deploy/windows/install-workers.ps1) once in Administrator PowerShell with NSSM installed.
- Run migrations, build assets, link storage, and cache configuration during deployment. Set queue retry/visibility timeouts above 960 seconds.
- Schedule `php artisan schedule:run` every minute for export/failure cleanup. Restart workers after deployments with `php artisan queue:restart`.
- Reverb is optional; collection progress also uses polling.

## Decisions beyond the spec

- **Automatic flow for the web app**: the spec has users tick routes; the Websites page now discovers *and* extracts on its own (the checklist stays one click away). The REST API keeps the tick-routes flow unless `auto_extract: true` is sent.
- **Excel with pictures drops the image URL and local path columns** (the picture itself is there); CSV and plain Excel keep them. Added SKU, Brand, Subcategory, Discount and Description columns.
- **Category and subcategory**: a nested category page (`/product-category/furniture/chairs/`) gives *Furniture › Chairs*; a one-level page uses its name as category and the product's own category (WooCommerce, Shopify `product_type`, JSON-LD) as subcategory. The home page never labels products "Home": they keep their own category, or the one a category page gave them.
- **Description** comes from what listings already carry (WooCommerce short description, Shopify `body_html`, JSON-LD, embedded state). Product detail pages are not visited, so sites whose listings show no description leave it empty.

- **Image rows**: `product_images` is unique on `(product_id, url_hash)` with an index on `url_hash`, instead of unique on `url_hash` alone, so several products can share one picture; the file is still downloaded only once and reused.
- **Links are optional for cards**: Chaldal's cards open products in a modal and have no link, so card detection needs an image and a price; links break ties.
- **Network JSON skips the browser** when the static HTML already shows product cards, so server-rendered sites never wait for Chromium.
- **Export scope "all"** means every product from all of the user's jobs.
- **Admins** are users with `is_admin = true`; there is no separate admin panel.
- **Tokens**: `POST /api/tokens` issues Sanctum tokens (the spec lists the API but not how to authenticate).
