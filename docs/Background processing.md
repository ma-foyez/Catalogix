# Background processing

Website visitors only click **Collect products**, **Pause**, **Resume**, or **Download Excel**. The server runs queued work continuously. A server administrator installs the service once; visitors never run PHP commands.

## Collection controls

- **Maximum products per website** is optional (1–100,000). Blank means no product cap; the configured route and pagination safety limits still apply. Each website gets its own limit. Duplicate products shared by sections count once. The database locks the budget while saving, so concurrent sections cannot exceed it. Refreshing prices uses the same cap.
- **Pause** holds discovery, extraction and pending pictures for that collection. A request already in flight may finish. Collected products remain available for viewing and export.
- **Resume** continues within 30 seconds while workers are healthy. Product pagination retains its queued URL and page number. If paused during discovery, its checks restart from the home page; already extracted products are retained.
- Waiting for pause or a domain lock does not consume the exception retry allowance. Queue attempts use a wider counter so long pauses cannot overflow the database queue's original 255-attempt limit.

## Local development

```powershell
composer dev
```

This starts the web server, Vite, Reverb, and `catalogix:work`. The worker manager runs two discovery workers, two page workers, two image workers, one export worker, and one default worker. It restarts workers when they crash, reach the memory limit, finish their hourly recycle, or receive `queue:restart`.

When Laragon already serves the app, start only the workers with `php artisan catalogix:work`. To remove the need to start them after reboot on Windows, install the service below.

Inspect or tune capacity:

```powershell
php artisan catalogix:work --dry-run
php artisan catalogix:work --discovery=2 --pages=2 --images=4 --exports=1 --default=1
```

Scale according to server memory and workload. Each process has a 256 MB recycling limit. Database queues work with MySQL; Redis can reduce queue polling overhead on larger installations. Jobs on the same domain share one discovery/page lock, while different domains can progress independently. Image concurrency retains its own per-domain limit. The crawler still respects robots.txt and page delays.

## Linux production: Supervisor

Use PHP CLI with `pcntl` enabled so worker timeouts and graceful signals work. Install Supervisor with your server's package manager. Set the production `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1020
REDIS_QUEUE_RETRY_AFTER=1020
```

Keep `retry_after` above the longest worker timeout (960 seconds). For SQS, set the queue visibility timeout above 960 seconds in AWS. Use a shared cache store when running more than one application server so domain locks apply across servers.

1. Run migrations and build assets in your deployment directory:

   ```bash
   php artisan migrate --force
   npm ci
   npm run build
   php artisan storage:link
   php artisan optimize
   ```

2. Edit `deploy/supervisor/catalogix.conf` for your project path, PHP binary, and application user. Copy it into Supervisor's configuration directory:

   ```bash
   sudo cp deploy/supervisor/catalogix.conf /etc/supervisor/conf.d/catalogix.conf
   sudo systemctl enable --now supervisor
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl status
   ```

   The configuration gives each queue dedicated workers. Supervisor starts them at boot, restarts failed processes, rotates logs, and waits for active jobs during shutdown. Do not also run `catalogix:work` on that server: Supervisor already manages each worker directly.

3. Add the scheduler to the application user's crontab (adjust the path):

   ```cron
   * * * * * cd /var/www/catalogix && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

   The scheduler removes expired exports and old failure records. It does not replace the continuous worker services.

4. After each deployment, run `php artisan queue:restart`. Supervisor starts fresh workers after active jobs finish.

Reverb is optional for collection: the UI polls while work is active. If enabled, run `php artisan reverb:start` under a separate service and configure the websocket reverse proxy.

## Windows: automatic service with NSSM

Install NSSM separately. After migrations, run the provided installer once in Administrator PowerShell:

```powershell
.\deploy\windows\install-workers.ps1 -NssmPath 'C:\tools\nssm\win64\nssm.exe' -PhpPath 'C:\laragon\bin\php\php-8.4.24\php.exe'
```

This installs **CatalogixWorkers** as an automatic Windows service, starts the worker manager without a visible terminal, and writes logs under `storage/logs`. NSSM restarts the manager and its process tree after failure. Ensure the service account can access the project, database, PHP, Python helper, and `storage` directory. PHP on Windows does not provide `pcntl` worker timeouts; Linux with `pcntl` is preferred for production crawling.

Use `Get-Service CatalogixWorkers` to inspect the service. `php artisan queue:restart` reloads worker code; use `Restart-Service CatalogixWorkers` after changing service or pool settings. Avoid a second manual pool while the service runs.

## Performance and troubleshooting

Automatic discovery checks up to 12 candidate pages and follows up to three second-level menu links; manual discovery retains the 40-probe default. Sitemap traversal has a 12-request budget shared across the sitemap sources. Fetched home/menu HTML is reused when scoring routes. Adjust `CATALOGIX_AUTO_MAX_PROBES`, `CATALOGIX_AUTO_MENU_PAGES`, and `CATALOGIX_MAX_SITEMAP_REQUESTS` for sites needing broader discovery.

Independent queues let pictures download while products are collected, exports finish without waiting behind a crawl, and multiple websites start together. Progress recounts run as database aggregates instead of loading all product IDs into PHP. These changes reduce avoidable waiting; remote response time, JavaScript rendering, page counts, and crawl delays still determine actual completion time.

If tasks stop moving, inspect service status, `storage/logs/laravel.log`, worker logs, and `php artisan queue:failed`. Fix the cause before retrying failures with `queue:retry`. The UI shows a delay notice; server commands are shown only to administrators. A busy discovery worker no longer masks a missing page, image, or export worker.
