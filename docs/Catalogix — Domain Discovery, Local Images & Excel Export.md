# Catalogix — Domain Discovery, Local Images & Excel Export

Oct 20, 2026 · @M.A Fayez

The user enters only a domain; Catalogix suggests every route that lists products, extracts the ticked routes, downloads product images to local storage, and exports Excel files with the images embedded in the cells. Everything runs in one Laravel application on MySQL, with no AI and no paid service; a small Python helper inside the same project handles JavaScript-heavy pages.

## How it fits together

&#91;embedded content: domain-to-export flow · one Laravel app with a Python helper\]

Every step runs as a Laravel queue job and stores its results in MySQL; Laravel calls the Python helper only when discovery or extraction meets a page that needs JavaScript.

## Stack

Laravel does almost everything; Python is used only to drive a real browser on JavaScript-heavy pages, because Playwright can read the JSON a page loads, which PHP tools cannot. Every piece is free and open source.

| Part | Pick | Role |
| --- | --- | --- |
| App + REST API | **Laravel 13** (PHP 8.3+), Sanctum | Web UI, REST API, auth, orchestration |
| Database | **MySQL 8** | Domains, routes, products, images, exports |
| Queue | Laravel queue (database driver; Redis optional) | Discovery, extraction, image and export jobs |
| Live progress | Laravel Reverb + Echo | Routes and products appear as they are found |
| Static HTML | Laravel HTTP client + **Symfony DomCrawler** | Sitemaps, menus, JSON-LD, product cards |
| JS-heavy pages | **Python 3.12 + Playwright** helper script | Render page, scroll, capture product JSON from network |
| Image resize | **Intervention Image** (GD or Imagick) | WebP originals + 200 px thumbnails |
| Excel with images | **PhpSpreadsheet** via Laravel Excel (`WithDrawings`) | Thumbnails embedded in cells |
| ZIP | PHP `ZipArchive` | Image packs per site and category |
| Frontend | Inertia + React + Tailwind CSS + shadcn/ui (inside the same Laravel app) | Discovery checklist, product listing, export dialog |

## Domain → suggested product routes

The user types only `chaldal.com`; `DiscoverDomainJob` finds every page that lists products and returns them as a scored checklist.

1. **Read the site map.** Fetch `robots.txt`, follow its `Sitemap:` lines, then `sitemap.xml` and `sitemap_index.xml`. WooCommerce and Shopify sites often publish separate category and collection sitemaps.
2. **Detect the platform** (generator meta, `wp-content`, `cdn.shopify.com`) and read its catalogue endpoint:
   - WooCommerce: `/wp-json/wc/store/v1/products/categories`
   - Shopify: `/collections.json`
   - These return the exact category list with product counts.
3. **Crawl the menu.** Open the homepage, follow the main navigation 2 levels deep, collect internal links. If the menu is built by JavaScript, the Python helper renders it first.
4. **Filter by URL pattern.** Keep `/category/`, `/product-category/`, `/collections/`, `/shop`, `/c/`, `?cat=`. Group product-detail links (`/product/`, `/products/`, `/p/`) under their parent category.
5. **Probe each candidate.** Fetch it and count product-like content: JSON-LD `Product` / `ItemList`, or repeated cards that each have an image, a link and a price (৳, Tk, BDT, digits). Each route gets a confidence score, an estimated product count and 3 sample thumbnails.

**Discovery result screen**

| ☑ | Route | Category | Est. products | Score | Preview |
| --- | --- | --- | --- | --- | --- |
| ☑ | /popular | Popular | 48 | High | 3 thumbnails |
| ☑ | /fresh-vegetables | Vegetables | 120 | High | 3 thumbnails |
| ☐ | /blog | Not a product page | 0 | None | none |

High-score routes are ticked by default. The user adjusts the ticks and clicks **Extract**.

## Extraction

Each ticked route runs through a no-AI ladder and stops at the first strategy that returns at least 3 products with a name and a price. The winning strategy is saved on the site, so the next run starts there.

1. **Saved recipe:** CSS selectors already stored for this site.
2. **JSON-LD / microdata:** `Product` and `ItemList` blocks in the page.
3. **Platform API:** WooCommerce Store API, Shopify `products.json`.
4. **Embedded state JSON:** `__NEXT_DATA__`, `window.__NUXT__`, `window.__INITIAL_STATE__`.
5. **Captured network JSON:** the Python helper loads the page and returns the product JSON the site fetched for itself.
6. **Automatic card detection:** find the largest group of repeated elements that each contain an image, a link and a price; map name, price and image inside each.
7. **Visual recipe picker:** the admin opens the page in the app, clicks one name, one price, one image and the Next button; the app generates selectors, previews all matches, and saves the recipe (2–5 minutes, once per site).

**Pagination:** follow the recipe's Next selector, `rel=next`, `?page=N` or the platform API's page parameter, until a page adds no new products or the page limit is reached. Infinite-scroll pages are scrolled by the Python helper.

**Normalization:** Bengali digits to 0–9, strip ৳ / Tk / BDT and commas, lower of two prices is the sale price, images from `src` / `data-src` / `srcset`, all URLs made absolute, category from the route's name in discovery.

## Images downloaded locally

Every product image is saved to local storage, and the listing shows the local copy, so the preview stays fast and works even if the source site goes offline.

- **One `DownloadImageJob` per image**, at most 3–5 running per site at a time, with a short delay, so the source site isn't hammered.
- **Deduplicated by URL hash:** the same image is never downloaded twice, even across routes.
- **Validated:** content type must be an image, and size above a small minimum (skips 1×1 tracking pixels and placeholders).
- **Stored as** `storage/app/public/products/{domain}/{category}/{product-slug}.webp`, plus a 200 px thumbnail `{product-slug}_thumb.jpg`, both made with Intervention Image.
- **Listing:** the product table shows the local thumbnail; clicking opens the full local image.
- **Download images:** a ZIP with folders per site and category, built with `ZipArchive` in a queued job.
- **Status per image:** pending, downloaded, failed (with the reason), so failed images can be retried.

## Excel with real images in cells

The exported sheet carries the picture itself, not just the URL: each row has the product thumbnail anchored to its first cell, so the file reads like a catalogue and works offline.

**Columns:** Image · Name · Price (৳) · Sale price · Category · Stock · Product URL · Image URL · Local image path.

**How:** Laravel Excel export with an `AfterSheet` event that places one PhpSpreadsheet `Drawing` per row.

```php
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

public function registerEvents(): array
{
    return [AfterSheet::class => function (AfterSheet $event) {
        $sheet = $event->sheet->getDelegate();
        $sheet->getColumnDimension('A')->setWidth(14);
        $row = 2;

        foreach ($this->query()->with('image')->cursor() as $product) {
            $sheet->getRowDimension($row)->setRowHeight(60);

            if ($thumb = $product->image?->thumb_path) {
                $drawing = new Drawing();
                $drawing->setPath(Storage::disk('public')->path($thumb));
                $drawing->setHeight(70);
                $drawing->setCoordinates("A{$row}");
                $drawing->setOffsetX(6);
                $drawing->setOffsetY(6);
                $drawing->setWorksheet($sheet);
            }
            $row++;
        }
    }];
}
```

**Size rules**

- Embed the **200 px JPEG thumbnail**, never the full image. JPEG and PNG embed most reliably, which is why thumbnails are saved as `.jpg`.
- A few thousand products with thumbnails produce a file of tens of MB, and PhpSpreadsheet holds every drawing in memory while writing.
- Set a limit (for example 3,000 rows per file with images). Above it, the export splits into one file per site or category; the user can also choose "Excel without images" or CSV for the full set.

**Export options in the dialog:** scope (route, site, all), format (XLSX with images, XLSX without images, CSV, images ZIP), and layout (one sheet per site, or one sheet with a Source column).

## Python helper

The helper is one script inside the Laravel project (`scraper/render.py`), called by Laravel queue jobs through the `Process` facade; there is no separate Python server, queue or database. It runs only when a page needs JavaScript: menus built by JS during discovery, ladder step 5 (captured network JSON), and infinite scroll.

**Contract**

| Direction | Content |
| --- | --- |
| Input (CLI args) | `--url`, `--scroll` (number of scroll steps), `--timeout` (seconds), `--out` (JSON file path) |
| Output (JSON file) | `final_url`, rendered `html`, all `links`, up to 50 captured `json_responses` (`url` + parsed `body`) |
| Errors | Non-zero exit code + message on stderr; Laravel marks the target failed or blocked |

**render.py**

```python
import argparse, json
from playwright.sync_api import sync_playwright

p = argparse.ArgumentParser()
p.add_argument("--url", required=True)
p.add_argument("--scroll", type=int, default=0)
p.add_argument("--timeout", type=int, default=45)
p.add_argument("--out", required=True)
args = p.parse_args()

captured = []

def on_response(resp):
    if resp.request.resource_type in ("xhr", "fetch") and "json" in resp.headers.get("content-type", ""):
        try:
            captured.append({"url": resp.url, "body": resp.json()})
        except Exception:
            pass

with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True)
    page = browser.new_page(user_agent="CatalogixBot/1.0")
    page.on("response", on_response)
    page.goto(args.url, wait_until="networkidle", timeout=args.timeout * 1000)
    for _ in range(args.scroll):
        page.mouse.wheel(0, 4000)
        page.wait_for_timeout(1200)
    result = {
        "final_url": page.url,
        "html": page.content(),
        "links": page.eval_on_selector_all("a[href]", "els => els.map(e => e.href)"),
        "json_responses": captured[:50],
    }
    browser.close()

with open(args.out, "w", encoding="utf-8") as f:
    json.dump(result, f, ensure_ascii=False)
```

**Laravel side (`PageRenderer` service)**

```php
$out = storage_path('app/tmp/render-' . Str::uuid() . '.json');

$result = Process::timeout(90)->run([
    config('catalogix.python_bin'), base_path('scraper/render.py'),
    '--url', $url, '--scroll', (string) $scroll, '--out', $out,
]);

if ($result->failed()) {
    throw new RenderFailedException($result->errorOutput());
}

$data = json_decode(file_get_contents($out), true);
@unlink($out);
```

The captured `json_responses` go to `JsonProductFinder`, which looks for arrays of objects with name-, price- and image-like keys.

**Setup (once):** `pip install playwright`, then `playwright install chromium`, and set `PYTHON_BIN` in `.env` (for example `python` on Windows, `python3` on Linux).

## REST API

The same Laravel app serves the web UI and a REST API in `routes/api.php`, protected by Sanctum tokens, so a mobile app or another system can use the same features.

| Method | Endpoint | Does |
| --- | --- | --- |
| POST | `/api/discoveries` `{ domain }` | Starts route discovery; returns a discovery id |
| GET | `/api/discoveries/{id}` | Status + suggested routes with score, estimated count, sample thumbnails |
| POST | `/api/discoveries/{id}/extract` `{ route_ids[] }` | Starts extraction for the ticked routes; returns a job id |
| GET | `/api/jobs/{id}` | Job status, per-route progress, product and image counts |
| GET | `/api/jobs/{id}/products?route=&category=&search=&page=` | Products with local image and thumbnail URLs (paginated) |
| POST | `/api/jobs/{id}/images/retry` | Retries failed image downloads |
| POST | `/api/exports` `{ job_id, scope, format, embed_images, layout }` | Builds XLSX (with or without images), CSV or images ZIP |
| GET | `/api/exports/{id}` | Export status and file size |
| GET | `/api/exports/{id}/download` | Downloads the file (signed URL, 24-hour expiry) |

Live progress in the web UI comes from Reverb events (`RoutesDiscovered`, `ProductsFound`, `ImageDownloaded`, `ExportReady`); API clients poll the status endpoints.

## MySQL tables

MySQL 8, utf8mb4. Every table has `id` and timestamps unless noted.

| Table | Columns | Indexes / rules |
| --- | --- | --- |
| `sites` | domain, name, platform (woocommerce/shopify/custom/unknown), needs\_js, strategy, selectors (json), pagination (json), recipe\_valid, is\_blocked, last\_success\_at | unique domain |
| `discoveries` | user\_id, site\_id, status (queued/running/done/failed), routes\_found, started\_at, finished\_at | index (user\_id) |
| `discovered_routes` | discovery\_id, url (text), url\_hash (char 40), path, category\_name, source (sitemap/platform\_api/menu/pattern), score (tinyint 0–100), est\_products, sample\_images (json), is\_selected | unique (discovery\_id, url\_hash) |
| `scrape_jobs` | user\_id, discovery\_id, status, total\_routes, total\_products, total\_images, started\_at, finished\_at | index (user\_id) |
| `scrape_targets` | scrape\_job\_id, discovered\_route\_id, url (text), status, strategy\_used, pages\_crawled, products\_found, error | index (scrape\_job\_id) |
| `categories` | site\_id, name, slug, source\_url | unique (site\_id, slug) |
| `products` | site\_id, category\_id, hash (char 40), name, price, sale\_price (decimal 12,2), currency, product\_url (text), sku, brand, in\_stock, raw (json), first\_seen\_at, last\_seen\_at | **unique (site\_id, hash)**; saved with `upsert()` |
| `product_scrape_target` | product\_id, scrape\_target\_id | primary (product\_id, scrape\_target\_id) |
| `product_images` | product\_id, source\_url (text), url\_hash (char 40), local\_path, thumb\_path, width, height, bytes, status (pending/downloaded/failed), error | unique url\_hash; index (product\_id) |
| `exports` | user\_id, scrape\_job\_id, scope (json), format (xlsx\_images/xlsx/csv/zip\_images), layout, file\_path, bytes, row\_count, status, expires\_at | index (user\_id) |

## Notes

- **Cost:** zero for software and AI; the only cost is a server when you deploy publicly. On your own PC it is free.
- **Blocked sites:** pages behind logins, CAPTCHAs or bot challenges are marked `blocked`, never bypassed; `robots.txt` is respected.
- **Politeness:** one page at a time per domain with a 1–3 second delay; 3–5 image downloads at a time per domain.
- **Recipes break when sites change layout:** a saved recipe that suddenly returns zero products is flagged, and the admin re-picks it in the visual recipe picker.
- **Image rights:** downloaded product images belong to the source sites. Internal research, or suppliers who have agreed, is fine; don't republish them commercially without permission.
