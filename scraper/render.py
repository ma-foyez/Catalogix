"""Catalogix page renderer.

Loads one page in headless Chromium (Playwright), optionally scrolls it to trigger
infinite-scroll loading, and writes a JSON file with:

    final_url        the URL after redirects
    html             the rendered DOM
    links            every a[href] on the page (absolute)
    json_responses   up to 50 JSON XHR/fetch responses: {"url", "body"}

Laravel calls it through the Process facade (App\\Services\\Crawling\\PageRenderer).
On failure it prints a message to stderr and exits with a non-zero code.

Setup once:  pip install -r scraper/requirements.txt  &&  playwright install chromium
"""

import argparse
import json
import sys

MAX_JSON_RESPONSES = 50
MAX_JSON_BYTES = 5 * 1024 * 1024


def parse_args():
    parser = argparse.ArgumentParser(description="Render a page for Catalogix.")
    parser.add_argument("--url", required=True)
    parser.add_argument("--scroll", type=int, default=0, help="number of scroll steps")
    parser.add_argument("--timeout", type=int, default=45, help="seconds")
    parser.add_argument("--user-agent", default="CatalogixBot/1.0")
    parser.add_argument("--out", required=True, help="path of the JSON file to write")
    return parser.parse_args()


def main():
    args = parse_args()

    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import TimeoutError as PlaywrightTimeout
        from playwright.sync_api import sync_playwright
    except ImportError:
        print("Playwright is not installed: run 'pip install playwright' and 'playwright install chromium'.", file=sys.stderr)
        return 3

    captured = []

    def on_response(resp):
        if len(captured) >= MAX_JSON_RESPONSES:
            return
        try:
            if resp.request.resource_type not in ("xhr", "fetch"):
                return
            if "json" not in resp.headers.get("content-type", ""):
                return
            body = resp.body()
            if len(body) > MAX_JSON_BYTES:
                return
            captured.append({"url": resp.url, "body": json.loads(body)})
        except Exception:
            pass

    try:
        with sync_playwright() as pw:
            browser = pw.chromium.launch(headless=True)
            try:
                page = browser.new_page(user_agent=args.user_agent)
                page.on("response", on_response)

                try:
                    page.goto(args.url, wait_until="networkidle", timeout=args.timeout * 1000)
                except PlaywrightTimeout:
                    # Busy pages (analytics, chat widgets) never go idle; use what has loaded.
                    page.wait_for_load_state("domcontentloaded", timeout=5000)

                for _ in range(max(0, args.scroll)):
                    page.mouse.wheel(0, 4000)
                    page.wait_for_timeout(1200)

                result = {
                    "final_url": page.url,
                    "html": page.content(),
                    "links": page.eval_on_selector_all("a[href]", "els => els.map(e => e.href)"),
                    "json_responses": captured[:MAX_JSON_RESPONSES],
                }
            finally:
                browser.close()
    except PlaywrightError as error:
        print(f"Rendering failed: {error}", file=sys.stderr)
        return 2

    with open(args.out, "w", encoding="utf-8") as handle:
        json.dump(result, handle, ensure_ascii=False)

    return 0


if __name__ == "__main__":
    sys.exit(main())
