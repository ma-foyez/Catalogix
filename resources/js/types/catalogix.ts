export type RunStatus = 'queued' | 'running' | 'done' | 'failed';

export type TargetStatus =
    | 'queued'
    | 'crawling'
    | 'done'
    | 'blocked'
    | 'failed';

export type ImageStatus = 'pending' | 'downloaded' | 'failed';

export type DiscoveryStage =
    | 'waiting'
    | 'home'
    | 'sitemaps'
    | 'catalogue'
    | 'menu'
    | 'probing'
    | 'rendering'
    | 'finished';

export type QueueStall = {
    queues: string[];
    waiting: number;
    command: string;
};

export type ScoreLabel = 'high' | 'medium' | 'low' | 'none';

export type DiscoveredRoute = {
    id: number;
    url: string;
    path: string;
    category_name: string | null;
    source: 'sitemap' | 'platform_api' | 'menu' | 'pattern';
    score: number;
    score_label: ScoreLabel;
    est_products: number;
    sample_images: string[];
    is_selected: boolean;
};

export type Discovery = {
    id: number;
    domain: string;
    site?: {
        id: number;
        domain: string;
        platform: string;
        needs_js: boolean;
        is_blocked: boolean;
        blocked_reason: string | null;
    };
    status: RunStatus;
    stage: DiscoveryStage;
    stage_label: string;
    candidates: number;
    probed: number;
    to_probe: number;
    routes_found: number;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    routes?: DiscoveredRoute[];
};

export type ScrapeTarget = {
    id: number;
    url: string;
    category: string | null;
    status: TargetStatus;
    strategy: string | null;
    strategy_label: string | null;
    pages_crawled: number;
    products_found: number;
    error: string | null;
};

export type ScrapeJob = {
    id: number;
    discovery_id: number | null;
    domain: string | null;
    site_id: number | null;
    status: RunStatus;
    total_routes: number;
    total_products: number;
    total_images: number;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    routes?: ScrapeTarget[];
};

export type ProductImage = {
    id: number;
    status: ImageStatus;
    url: string | null;
    thumb_url: string | null;
    source_url: string;
    local_path: string | null;
    width: number | null;
    height: number | null;
    bytes: number | null;
    error: string | null;
};

export type Product = {
    id: number;
    name: string;
    price: number | null;
    sale_price: number | null;
    currency: string;
    category: string | null;
    site: string;
    in_stock: boolean | null;
    product_url: string | null;
    sku: string | null;
    brand: string | null;
    image: ProductImage | null;
    last_seen_at: string | null;
};

export type ExportFormat = 'xlsx_images' | 'xlsx' | 'csv' | 'zip_images';

export type ExportLayout = 'per_site' | 'single';

export type ExportScope = 'route' | 'site' | 'all';

export type ExportFile = {
    id: number;
    scrape_job_id: number | null;
    scope: {
        type: ExportScope;
        scrape_job_id?: number;
        scrape_target_id?: number;
    };
    format: ExportFormat;
    format_label: string;
    layout: ExportLayout;
    status: 'pending' | 'processing' | 'ready' | 'failed';
    file_name: string | null;
    bytes: number | null;
    row_count: number;
    error: string | null;
    expires_at: string | null;
    created_at: string | null;
    download_url: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: PaginationLink[];
    };
};
