import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Braces,
    Check,
    Code2,
    FileSpreadsheet,
    Globe,
    ImageDown,
    Layers,
    MousePointerClick,
    Radar,
    Radio,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { discover, login, register } from '@/routes';

const STEPS = [
    {
        icon: Globe,
        title: 'Paste websites',
        text: 'One shop or twenty — plain addresses or full links, Catalogix reads them all.',
    },
    {
        icon: Radar,
        title: 'It finds the products',
        text: 'Sitemaps, the store catalogue and the menu point to every page that lists products.',
    },
    {
        icon: Layers,
        title: 'Collects and saves pictures',
        text: 'Name, price, sale price, SKU, category and stock — with every picture saved.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Download Excel',
        text: 'One click: an Excel file with the product pictures right in the rows.',
    },
];

const LADDER = [
    ['Saved recipe', 'Selectors learned on an earlier run'],
    ['JSON-LD & microdata', 'Structured data the shop already publishes'],
    ['Platform API', 'WooCommerce Store API, Shopify products.json'],
    ['Embedded state', '__NEXT_DATA__, __NUXT__ and friends'],
    ['Network JSON', 'Responses captured while a real browser loads'],
    ['Card detection', 'Repeated blocks with an image and a price'],
];

const SAMPLE_ROWS = [
    { name: 'Tata Tea Premium 400 gm', price: '৳ 230', sale: '৳ 149', hue: 28 },
    { name: 'Fresh Toned Milk 1 ltr', price: '৳ 95', sale: null, hue: 210 },
    { name: 'Teer Soybean Oil 2 ltr', price: '৳ 375', sale: '৳ 360', hue: 48 },
    { name: 'Lal Teer Hybrid Seeds', price: '৳ 60', sale: null, hue: 140 },
];

/*
 * Design brief
 * User: someone landing on Catalogix for the first time (or a returning user heading to the app).
 * Main task: understand what Catalogix does and how, then sign in or start.
 * Key information: domain → routes → products → export, the no-AI extraction ladder,
 * local images and Excel with pictures, the API, the politeness rules.
 * Primary action: "Start with a domain" (Log in / Get started for guests).
 */
export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth, name } = usePage().props;
    const primaryHref = auth.user ? discover() : register();

    return (
        <>
            <Head title="Domain to catalogue">
                <meta
                    name="description"
                    content="Catalogix turns a shop's domain into a product catalogue with local images and Excel exports."
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <SiteHeader
                    name={String(name)}
                    signedIn={Boolean(auth.user)}
                    canRegister={canRegister}
                />

                <main>
                    {/* Hero */}
                    <section className="relative overflow-hidden">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] mask-[radial-gradient(ellipse_70%_60%_at_50%_0%,black_30%,transparent_100%)] bg-size-[40px_40px] opacity-70"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-40 left-1/2 size-176 -translate-x-1/2 rounded-full bg-primary/15 blur-3xl"
                        />

                        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pt-16 pb-20 md:px-6 lg:grid-cols-[1.05fr_1fr] lg:pt-24 lg:pb-28">
                            <div className="animate-in space-y-7 duration-500 fade-in slide-in-from-bottom-3">
                                <span className="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur">
                                    <Sparkles className="size-3.5 text-primary" />
                                    No AI · no paid services · one Laravel app
                                </span>

                                <h1 className="text-4xl font-semibold tracking-tight text-balance md:text-5xl lg:text-6xl">
                                    Turn any shop's domain into a{' '}
                                    <span className="bg-linear-to-r from-primary to-fuchsia-500 bg-clip-text text-transparent">
                                        clean product catalogue
                                    </span>
                                </h1>

                                <p className="max-w-xl text-base text-muted-foreground md:text-lg">
                                    Catalogix finds the pages that list
                                    products, extracts name, price, sale price,
                                    stock and image, stores every picture
                                    locally, and exports Excel with the photos
                                    right in the cells.
                                </p>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Link
                                        href={primaryHref}
                                        className="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground shadow-lg shadow-primary/25 transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        {auth.user
                                            ? 'Open Catalogix'
                                            : 'Start with a domain'}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                    <a
                                        href="#how-it-works"
                                        className="inline-flex h-11 items-center gap-2 rounded-lg border bg-background/80 px-5 text-sm font-medium backdrop-blur transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        See how it works
                                    </a>
                                </div>

                                <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                                    {[
                                        'WooCommerce, Shopify & custom shops',
                                        'Bengali digits & ৳ prices',
                                        'robots.txt respected',
                                    ].map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-center gap-1.5"
                                        >
                                            <Check className="size-4 text-success" />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <ProductWindow />
                        </div>
                    </section>

                    {/* How it works */}
                    <section
                        id="how-it-works"
                        className="scroll-mt-20 border-y bg-muted/30"
                    >
                        <div className="mx-auto max-w-6xl px-4 py-20 md:px-6">
                            <SectionHeading
                                eyebrow="How it works"
                                title="Paste a website. Get an Excel file."
                                text="No settings and no technical steps — Catalogix does the work and tells you in plain words what it is doing."
                            />

                            <ol className="relative mt-12 grid gap-6 md:grid-cols-4">
                                <div
                                    aria-hidden
                                    className="absolute top-6 right-[12%] left-[12%] hidden h-px bg-linear-to-r from-transparent via-border to-transparent md:block"
                                />
                                {STEPS.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="relative space-y-3 text-center md:text-left"
                                    >
                                        <span className="relative mx-auto flex size-12 items-center justify-center rounded-2xl border bg-background shadow-sm md:mx-0">
                                            <step.icon className="size-5 text-primary" />
                                            <span className="absolute -top-2 -right-2 flex size-5 items-center justify-center rounded-full bg-primary font-mono text-[10px] font-semibold text-primary-foreground">
                                                {index + 1}
                                            </span>
                                        </span>
                                        <h3 className="font-medium">
                                            {step.title}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {step.text}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    {/* Extraction ladder */}
                    <section
                        id="ladder"
                        className="mx-auto grid max-w-6xl scroll-mt-20 gap-12 px-4 py-20 md:px-6 lg:grid-cols-2 lg:items-center"
                    >
                        <div className="space-y-5">
                            <SectionHeading
                                align="left"
                                eyebrow="The extraction ladder"
                                title="Six strategies, tried in order, until one works"
                                text="Each page climbs the ladder and stops at the first rung that finds at least three priced products. The winner is remembered, so the next run starts there."
                            />
                            <p className="flex items-start gap-2 rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                                <MousePointerClick className="mt-0.5 size-4 shrink-0 text-primary" />
                                <span>
                                    Odd layout? Admins open the{' '}
                                    <span className="font-medium text-foreground">
                                        visual recipe picker
                                    </span>
                                    , click a name, a price, an image and the
                                    Next button on the real page, preview every
                                    match and save.
                                </span>
                            </p>
                        </div>

                        <ol className="space-y-2">
                            {LADDER.map(([title, text], index) => (
                                <li
                                    key={title}
                                    className="group flex items-center gap-4 rounded-xl border bg-card px-4 py-3 transition hover:border-primary/40 hover:shadow-sm"
                                    style={{
                                        marginLeft: `${index * 4}%`,
                                    }}
                                >
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 font-mono text-xs font-semibold text-primary">
                                        {index + 1}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {title}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {text}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </section>

                    {/* Feature bento */}
                    <section
                        id="features"
                        className="scroll-mt-20 border-t bg-muted/30"
                    >
                        <div className="mx-auto max-w-6xl px-4 py-20 md:px-6">
                            <SectionHeading
                                eyebrow="Built for real catalogues"
                                title="Everything the spreadsheet needs, nothing it doesn't"
                            />

                            <div className="mt-12 grid gap-4 md:grid-cols-6">
                                <Feature
                                    className="md:col-span-3"
                                    icon={ImageDown}
                                    title="Images stored locally"
                                    text="Each picture is checked, saved once as WebP plus a 200 px thumbnail, and filed by site and category — three to five downloads at a time per shop."
                                >
                                    <div className="mt-5 grid grid-cols-4 gap-2">
                                        {[28, 210, 48, 140].map((hue) => (
                                            <div
                                                key={hue}
                                                className="aspect-square rounded-lg border"
                                                style={{
                                                    background: `linear-gradient(135deg, oklch(0.85 0.09 ${hue}), oklch(0.7 0.13 ${hue}))`,
                                                }}
                                            />
                                        ))}
                                    </div>
                                </Feature>

                                <Feature
                                    className="md:col-span-3"
                                    icon={FileSpreadsheet}
                                    title="Excel with pictures in the cells"
                                    text="Thumbnails sit in column A next to name, price and sale price. Big exports split into files per site and category inside one ZIP."
                                >
                                    <div className="mt-5 overflow-hidden rounded-lg border bg-background text-xs">
                                        {SAMPLE_ROWS.slice(0, 3).map((row) => (
                                            <div
                                                key={row.name}
                                                className="grid grid-cols-[2rem_1fr_auto] items-center gap-3 border-b px-2 py-1.5 last:border-b-0"
                                            >
                                                <span
                                                    className="size-6 rounded"
                                                    style={{
                                                        background: `oklch(0.8 0.1 ${row.hue})`,
                                                    }}
                                                />
                                                <span className="truncate">
                                                    {row.name}
                                                </span>
                                                <span className="font-mono">
                                                    {row.sale ?? row.price}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </Feature>

                                <Feature
                                    className="md:col-span-2"
                                    icon={Radio}
                                    title="Live progress"
                                    text="Routes, products and images appear as they are found — over Reverb, with polling as a fallback."
                                />

                                <Feature
                                    className="md:col-span-2"
                                    icon={ShieldCheck}
                                    title="Polite by default"
                                    text="One page at a time per site, 1–3 s apart. Logins, CAPTCHAs and bot checks are marked blocked, never bypassed."
                                />

                                <Feature
                                    className="md:col-span-2"
                                    icon={Braces}
                                    title="Re-runs update, never duplicate"
                                    text="Products are keyed by their link (or name, price and image when a card has none), so re-running a route doesn't add copies."
                                />

                                <Feature
                                    className="md:col-span-6"
                                    icon={Code2}
                                    title="The same features over a REST API"
                                    text="Sanctum tokens; start a discovery, extract routes, page through products and download exports from your own scripts."
                                >
                                    <pre className="mt-5 overflow-x-auto rounded-lg border bg-zinc-950 p-4 font-mono text-xs leading-relaxed text-zinc-300">
                                        <span className="text-zinc-500">
                                            # start a discovery
                                        </span>
                                        {'\n'}
                                        <span className="text-fuchsia-400">
                                            curl
                                        </span>{' '}
                                        -X POST /api/discoveries -H{' '}
                                        <span className="text-emerald-400">
                                            "Authorization: Bearer $TOKEN"
                                        </span>{' '}
                                        -d domain=chaldal.com{'\n'}
                                        <span className="text-zinc-500">
                                            # → 202 {'{'} "id": 12, "status":
                                            "queued", "stage": "waiting" {'}'}
                                        </span>
                                    </pre>
                                </Feature>
                            </div>
                        </div>
                    </section>

                    {/* CTA */}
                    <section className="mx-auto max-w-6xl px-4 py-20 md:px-6">
                        <div className="relative overflow-hidden rounded-3xl bg-primary px-6 py-14 text-center text-primary-foreground md:px-12">
                            <div
                                aria-hidden
                                className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_0%,rgba(255,255,255,0.25),transparent_45%),radial-gradient(circle_at_90%_100%,rgba(236,72,153,0.35),transparent_50%)]"
                            />
                            <div className="relative mx-auto max-w-2xl space-y-5">
                                <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                                    Start with a domain. Leave with a catalogue.
                                </h2>
                                <p className="text-primary-foreground/80">
                                    Discovery takes a minute or two for most
                                    shops; you decide which routes to extract.
                                </p>
                                <Link
                                    href={primaryHref}
                                    className="inline-flex h-11 items-center gap-2 rounded-lg bg-background px-5 text-sm font-medium text-foreground shadow-lg transition hover:bg-background/90 focus-visible:ring-2 focus-visible:ring-background focus-visible:ring-offset-2 focus-visible:ring-offset-primary focus-visible:outline-none"
                                >
                                    {auth.user
                                        ? 'Open Catalogix'
                                        : 'Create an account'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between md:px-6">
                        <span className="flex items-center gap-2">
                            <AppLogoIcon className="size-4 fill-current text-primary" />
                            {String(name)} — built with Laravel, React and
                            Reverb
                        </span>
                        <span className="text-xs">
                            Product images belong to their shops: use exports
                            for research or with the supplier's agreement.
                        </span>
                    </div>
                </footer>
            </div>
        </>
    );
}

function SiteHeader({
    name,
    signedIn,
    canRegister,
}: {
    name: string;
    signedIn: boolean;
    canRegister: boolean;
}) {
    return (
        <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur-md">
            <nav className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 md:px-6">
                <Link
                    href="/"
                    className="flex items-center gap-2 font-semibold"
                >
                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm shadow-primary/30">
                        <AppLogoIcon className="size-5 fill-current" />
                    </span>
                    {name}
                </Link>

                <div className="hidden items-center gap-5 text-sm text-muted-foreground md:flex">
                    <a href="#how-it-works" className="hover:text-foreground">
                        How it works
                    </a>
                    <a href="#ladder" className="hover:text-foreground">
                        Extraction
                    </a>
                    <a href="#features" className="hover:text-foreground">
                        Features
                    </a>
                </div>

                <div className="ml-auto flex items-center gap-2">
                    {signedIn ? (
                        <Link
                            href={discover()}
                            className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                        >
                            Open app <ArrowRight className="size-4" />
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium transition hover:bg-muted"
                            >
                                Log in
                            </Link>
                            {canRegister && (
                                <Link
                                    href={register()}
                                    className="inline-flex h-9 items-center rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                >
                                    Get started
                                </Link>
                            )}
                        </>
                    )}
                </div>
            </nav>
        </header>
    );
}

function SectionHeading({
    eyebrow,
    title,
    text,
    align = 'center',
}: {
    eyebrow: string;
    title: string;
    text?: string;
    align?: 'center' | 'left';
}) {
    return (
        <div
            className={cn(
                'max-w-2xl space-y-3',
                align === 'center' && 'mx-auto text-center',
            )}
        >
            <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                {eyebrow}
            </p>
            <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                {title}
            </h2>
            {text && <p className="text-muted-foreground">{text}</p>}
        </div>
    );
}

function Feature({
    icon: Icon,
    title,
    text,
    className,
    children,
}: {
    icon: typeof Globe;
    title: string;
    text: string;
    className?: string;
    children?: React.ReactNode;
}) {
    return (
        <article
            className={cn(
                'min-w-0 rounded-2xl border bg-card p-6 transition hover:shadow-md',
                className,
            )}
        >
            <span className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Icon className="size-5" />
            </span>
            <h3 className="mt-4 font-medium">{title}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{text}</p>
            {children}
        </article>
    );
}

/**
 * An illustrative app window: discovery steps on top, extracted products below.
 */
function ProductWindow() {
    return (
        <div className="relative animate-in duration-700 fade-in slide-in-from-bottom-6">
            <div
                aria-hidden
                className="absolute -inset-4 rounded-4xl bg-linear-to-br from-primary/25 via-fuchsia-500/10 to-transparent blur-2xl"
            />
            <figure
                aria-label="Catalogix extracting products from chaldal.com"
                className="relative overflow-hidden rounded-2xl border bg-card shadow-2xl shadow-primary/10"
            >
                <div className="flex items-center gap-2 border-b bg-muted/50 px-4 py-2.5">
                    <span className="flex gap-1.5" aria-hidden>
                        <span className="size-2.5 rounded-full bg-red-400" />
                        <span className="size-2.5 rounded-full bg-amber-400" />
                        <span className="size-2.5 rounded-full bg-emerald-400" />
                    </span>
                    <span className="mx-auto rounded-md border bg-background px-3 py-0.5 font-mono text-[11px] text-muted-foreground">
                        catalogix / chaldal.com
                    </span>
                </div>

                <div className="space-y-4 p-4">
                    <div className="flex items-center gap-3 rounded-xl border bg-primary/5 px-3 py-2.5">
                        <span className="relative flex size-8 items-center justify-center rounded-full bg-primary/15 text-primary">
                            <span className="absolute inset-0 animate-ping rounded-full bg-primary/20" />
                            <Spinner className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="text-[10px] font-semibold tracking-wide text-primary uppercase">
                                Analysing
                            </p>
                            <p className="truncate text-sm font-medium">
                                Checking pages for products · 18/40
                            </p>
                        </div>
                        <span className="font-mono text-xs text-muted-foreground">
                            0:42
                        </span>
                    </div>

                    <div className="grid grid-cols-3 gap-2 text-center">
                        {[
                            ['Routes', '12'],
                            ['Products', '1,284'],
                            ['Images', '1,190'],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="rounded-lg border bg-background px-2 py-2"
                            >
                                <p className="font-mono text-base font-semibold">
                                    {value}
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    {label}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="overflow-hidden rounded-xl border">
                        {SAMPLE_ROWS.map((row, index) => (
                            <div
                                key={row.name}
                                className="flex animate-in items-center gap-3 border-b bg-background px-3 py-2 fill-mode-both fade-in slide-in-from-left-2 last:border-b-0"
                                style={{
                                    animationDelay: `${400 + index * 180}ms`,
                                }}
                            >
                                <span
                                    className="size-9 shrink-0 rounded-md border"
                                    style={{
                                        background: `linear-gradient(135deg, oklch(0.88 0.08 ${row.hue}), oklch(0.72 0.13 ${row.hue}))`,
                                    }}
                                />
                                <span className="min-w-0 flex-1 truncate text-sm">
                                    {row.name}
                                </span>
                                <span className="text-right font-mono text-xs">
                                    {row.sale ? (
                                        <>
                                            <span className="block text-muted-foreground line-through">
                                                {row.price}
                                            </span>
                                            <span className="font-semibold text-success">
                                                {row.sale}
                                            </span>
                                        </>
                                    ) : (
                                        row.price
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </figure>
        </div>
    );
}
