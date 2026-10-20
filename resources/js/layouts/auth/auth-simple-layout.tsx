import { Link } from '@inertiajs/react';
import { ArrowLeft, Check, Layers, Sparkles } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CatalogPreview } from '@/components/marketing/catalog-preview';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return <div className="grid min-h-svh bg-[#faf9fd] text-foreground dark:bg-background lg:grid-cols-[1.05fr_1fr]">
        <aside className="relative hidden flex-col overflow-hidden bg-[#1c1232] p-10 text-white lg:flex xl:p-14">
            <div aria-hidden className="pointer-events-none absolute -top-32 -left-32 size-[35rem] rounded-full bg-violet-500/15 blur-3xl" />
            <div aria-hidden className="pointer-events-none absolute -right-48 bottom-0 size-[36rem] rounded-full border border-white/10" />
            <Link href={home()} className="relative flex w-fit items-center gap-2.5 text-xl font-semibold tracking-tight"><span className="flex size-10 items-center justify-center rounded-xl bg-violet-500"><AppLogoIcon className="size-6 fill-current" /></span>Catalogix<span className="-ml-2 text-violet-400">.</span></Link>
            <div className="relative mx-auto flex w-full max-w-lg flex-1 flex-col justify-center gap-8 py-12">
                <div className="space-y-4"><span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs text-violet-200"><Sparkles className="size-3" /> A calmer way to collect</span><h2 className="text-4xl leading-[1.12] font-semibold tracking-tight xl:text-5xl">Good products.<br /><span className="text-violet-300">Great organization.</span></h2><p className="max-w-sm text-sm leading-relaxed text-white/55">Bring websites, products, pictures and spreadsheets together in one thoughtful workspace.</p></div>
                <CatalogPreview compact className="-rotate-1 shadow-black/25" />
                <div className="flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-white/65">{['Collect automatically', 'Keep every picture', 'Export your selection'].map((text) => <span key={text} className="flex items-center gap-1.5"><Check className="size-3 text-violet-300" />{text}</span>)}</div>
            </div>
            <p className="relative flex items-center gap-2 text-xs text-white/35"><Layers className="size-3.5" /> Your next catalogue starts here.</p>
        </aside>
        <main className="relative flex min-h-svh flex-col px-6 py-7 sm:px-10">
            <Link href={home()} className="flex w-fit items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"><ArrowLeft className="size-3.5" /> Back to Catalogix</Link>
            <div className="flex flex-1 items-center justify-center py-10">
                <div className="w-full max-w-[25rem]">
                    <Link href={home()} className="mb-8 flex items-center gap-2 text-lg font-semibold lg:hidden"><span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground"><AppLogoIcon className="size-5 fill-current" /></span>Catalogix</Link>
                    <div className="mb-7 space-y-2"><p className="text-[10px] font-semibold tracking-[0.2em] text-primary uppercase">Your product workspace</p><h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h1><p className="text-sm leading-relaxed text-muted-foreground">{description}</p></div>
                    <div className="rounded-2xl border bg-card p-6 shadow-sm shadow-violet-950/5 sm:p-7">{children}</div>
                    <p className="mt-6 text-center text-xs text-muted-foreground">Collect thoughtfully. Organize beautifully.</p>
                </div>
            </div>
            <p className="text-center text-[11px] text-muted-foreground/60">Catalogix ? Your catalogue, connected.</p>
        </main>
    </div>;
}
