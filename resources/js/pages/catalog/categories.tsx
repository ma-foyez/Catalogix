import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowUpRight, FolderOpen, Globe, Pencil, Plus, Search, Tags } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatCount } from '@/lib/format';
import type { Paginated } from '@/types/catalogix';

type Category = { id: number; site_id: number; name: string; domain: string; products_count: number };
type Site = { id: number; domain: string };
type Filters = { search: string; site: string | null };

export default function Categories({ categories, sites, filters, canManage }: { categories: Paginated<Category>; sites: Site[]; filters: Filters; canManage: boolean }) {
    const [search, setSearch] = useState(filters.search);
    const [editor, setEditor] = useState<Category | 'new' | null>(null);
    const apply = (changes: Partial<Filters>) => router.get('/categories', Object.fromEntries(Object.entries({ ...filters, search, ...changes }).filter(([, value]) => value !== null && value !== '')), { preserveState: true, preserveScroll: true, replace: true, only: ['categories', 'filters'] });
    useEffect(() => {
        if (search === filters.search) return;
        const timer = setTimeout(() => apply({ search }), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search, filters.site]);
    return <>
        <Head title="Categories" />
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 py-7 md:p-7">
            <header className="flex flex-wrap items-start justify-between gap-4"><div><p className="mb-1 text-xs font-medium tracking-widest text-primary uppercase">A place for everything</p><h1 className="text-3xl font-semibold tracking-tight">Browse by category.</h1><p className="mt-2 text-sm text-muted-foreground">Find a focused view of your products, organized by website and category.</p></div>{canManage && <Button disabled={sites.length === 0} onClick={() => setEditor('new')}><Plus /> New category</Button>}</header>
            <div className="flex flex-col gap-3 rounded-2xl border bg-card p-4 sm:flex-row"><div className="relative flex-1"><Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" /><Input aria-label="Search categories" placeholder="Find a category?" className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} /></div><Select value={filters.site ?? 'all'} onValueChange={(value) => apply({ site: value === 'all' ? null : value })}><SelectTrigger className="w-full sm:w-64" aria-label="Filter categories by website"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">All websites</SelectItem>{sites.map((site) => <SelectItem key={site.id} value={String(site.id)}>{site.domain}</SelectItem>)}</SelectContent></Select></div>
            <div className="flex items-center justify-between gap-2"><p className="text-sm text-muted-foreground"><span className="font-semibold text-foreground">{formatCount(categories.meta.total)}</span> categories in your workspace</p><Link href="/products?category=uncategorized" className="text-xs text-primary hover:underline">View uncategorized products</Link></div>
            {categories.data.length === 0 ? <EmptyState icon={Tags} title="No categories to show" description="Categories appear as websites are collected. Try another search or collect a website." /> : <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{categories.data.map((category, index) => <article key={category.id} className="group overflow-hidden rounded-2xl border bg-card transition-shadow hover:shadow-md hover:shadow-primary/5"><div className="space-y-4 p-5"><div className="flex items-start justify-between gap-3"><span className={`flex size-12 items-center justify-center rounded-2xl ${['bg-violet-500/10 text-violet-600 dark:text-violet-300', 'bg-sky-500/10 text-sky-600 dark:text-sky-300', 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'][index % 3]}`}><FolderOpen className="size-6" /></span>{canManage && <Button size="icon" variant="ghost" className="size-8" aria-label={`Edit ${category.name}`} onClick={() => setEditor(category)}><Pencil className="size-4" /></Button>}</div><div><h2 className="text-lg font-semibold tracking-tight">{category.name}</h2><p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"><Globe className="size-3" />{category.domain}</p></div><p className="text-sm text-muted-foreground"><span className="text-xl font-semibold text-foreground tabular-nums">{formatCount(category.products_count)}</span> {category.products_count === 1 ? 'product' : 'products'}</p></div><Link href={`/products?site=${category.site_id}&category=${category.id}`} className="flex items-center justify-between border-t bg-muted/20 px-5 py-3 text-sm font-medium transition-colors group-hover:bg-primary/5 group-hover:text-primary">View products<ArrowUpRight className="size-4" /></Link></article>)}</div>}
            <PaginationLinks page={categories} only={['categories', 'filters']} />
        </div>
        {editor !== null && <CategoryEditor key={editor === 'new' ? 'new' : editor.id} category={editor === 'new' ? null : editor} sites={sites} onClose={() => setEditor(null)} />}
    </>;
}

function CategoryEditor({ category, sites, onClose }: { category: Category | null; sites: Site[]; onClose: () => void }) {
    const form = useForm({ name: category?.name ?? '', slug: '', site_id: category ? String(category.site_id) : String(sites[0]?.id ?? '') });
    return <Dialog open onOpenChange={(open) => !open && onClose()}><DialogContent><DialogHeader><DialogTitle>{category ? 'Edit category' : 'Create a category'}</DialogTitle><DialogDescription>{category ? 'Rename this category across its products. Saved pictures stay available.' : 'Create a category for a website, then assign products from the Products page.'}</DialogDescription></DialogHeader><form className="space-y-5" onSubmit={(event) => { event.preventDefault(); const options = { preserveScroll: true, onSuccess: onClose }; if (category) form.patch(`/categories/${category.id}`, options); else form.post('/categories', options); }}><div className="space-y-2"><Label htmlFor="category-name">Category name</Label><Input id="category-name" required autoFocus maxLength={100} placeholder="e.g. Office furniture" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><InputError message={form.errors.name} /><InputError message={form.errors.slug} /></div>{!category && <div className="space-y-2"><Label>Website</Label><Select value={form.data.site_id} onValueChange={(value) => form.setData('site_id', value)}><SelectTrigger className="w-full" aria-label="Category website"><SelectValue /></SelectTrigger><SelectContent>{sites.map((site) => <SelectItem key={site.id} value={String(site.id)}>{site.domain}</SelectItem>)}</SelectContent></Select><InputError message={form.errors.site_id} /></div>}<div className="flex justify-end gap-2"><Button variant="outline" type="button" onClick={onClose}>Cancel</Button><Button disabled={form.processing}>{form.processing && <Spinner />}{category ? 'Save category' : 'Create category'}</Button></div></form></DialogContent></Dialog>;
}
Categories.layout = { breadcrumbs: [{ title: 'Categories', href: '/categories' }] };
