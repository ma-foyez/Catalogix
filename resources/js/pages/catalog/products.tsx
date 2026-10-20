import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink, Eye, Globe, ImageIcon, Package, Pencil, Search, Tags, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { ProductDetails, StockBadge } from '@/components/product-details';
import { ProductImagePreview } from '@/components/product-image-preview';
import { ProductThumb } from '@/components/product-thumb';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCount, formatTaka } from '@/lib/format';
import type { Paginated, Product } from '@/types/catalogix';

type Filters = { search: string; site: string | null; category: string | null; image: string | null };
type CategoryOption = { id: number; site_id: number; name: string; domain: string };
type Props = { products: Paginated<Product>; filters: Filters; sites: { id: number; domain: string }[]; categories: CategoryOption[]; stats: { products: number; websites: number; categories: number; pictures: number }; canManage: boolean };

export default function Products({ products, filters, sites, categories, stats, canManage }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [details, setDetails] = useState<Product | null>(null);
    const [preview, setPreview] = useState<Product | null>(null);
    const [editing, setEditing] = useState<Product | null>(null);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [exporting, setExporting] = useState(false);
    const apply = (changes: Partial<Filters>) => router.get('/products', Object.fromEntries(Object.entries({ ...filters, search, ...changes }).filter(([, value]) => value !== null && value !== '')), { preserveState: true, preserveScroll: true, replace: true, only: ['products', 'filters'] });
    useEffect(() => {
        if (search === filters.search) return;
        const timer = setTimeout(() => apply({ search }), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search, filters.site, filters.category, filters.image]);
    const toggle = (id: number, checked: boolean) => setSelected((old) => { const next = new Set(old); if (checked) next.add(id); else next.delete(id); return next; });
    const checkedPage = products.data.filter((product) => selected.has(product.id)).length;
    const exportProducts = (checked: boolean) => {
        setExporting(true);
        router.post('/exports', { scope: 'all', format: 'xlsx_images', layout: 'per_site', ...(checked ? { product_ids: [...selected] } : {}) }, { preserveScroll: true, onFinish: () => setExporting(false) });
    };
    return <>
        <Head title="Products" />
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 py-7 md:p-7">
            <header className="flex flex-wrap items-start justify-between gap-4"><div><p className="mb-1 text-xs font-medium tracking-widest text-primary uppercase">Your catalogue</p><h1 className="text-3xl font-semibold tracking-tight">Every product. One workspace.</h1><p className="mt-2 text-sm text-muted-foreground">Explore and organize products collected from all your websites.</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="outline"><Link href="/discover"><Globe /> Add a website</Link></Button><Button disabled={stats.products === 0 || exporting} onClick={() => exportProducts(false)}>{exporting ? <Spinner /> : <Download />} Export catalogue</Button></div></header>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{([
                ['products', 'Unique products', Package], ['websites', 'Source websites', Globe], ['categories', 'Product categories', Tags], ['pictures', 'Pictures saved', ImageIcon],
            ] as const).map(([key, label, Icon]) => <div key={key} className="flex items-center gap-3 rounded-2xl border bg-card p-4"><span className="flex size-10 items-center justify-center rounded-xl bg-primary/8 text-primary"><Icon className="size-5" /></span><div><p className="text-2xl font-semibold tracking-tight tabular-nums">{formatCount(stats[key])}</p><p className="text-xs text-muted-foreground">{label}</p></div></div>)}</div>
            <section className="space-y-3 rounded-2xl border bg-card p-4" aria-label="Catalog filters"><div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr]">
                <div className="relative"><Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" /><Input aria-label="Search products" placeholder="Search name, brand or SKU" value={search} onChange={(event) => setSearch(event.target.value)} className="pl-9" /></div>
                <Select value={filters.site ?? 'all'} onValueChange={(value) => apply({ site: value === 'all' ? null : value, category: null })}><SelectTrigger className="w-full" aria-label="Website"><SelectValue placeholder="All websites" /></SelectTrigger><SelectContent><SelectItem value="all">All websites</SelectItem>{sites.map((site) => <SelectItem key={site.id} value={String(site.id)}>{site.domain}</SelectItem>)}</SelectContent></Select>
                <Select value={filters.category ?? 'all'} onValueChange={(value) => apply({ category: value === 'all' ? null : value })}><SelectTrigger className="w-full" aria-label="Category"><SelectValue placeholder="All categories" /></SelectTrigger><SelectContent><SelectItem value="all">All categories</SelectItem><SelectItem value="uncategorized">Uncategorized</SelectItem>{categories.filter((category) => !filters.site || category.site_id === Number(filters.site)).map((category) => <SelectItem key={category.id} value={String(category.id)}>{category.name}{sites.length > 1 && !filters.site ? ` ? ${category.domain}` : ''}</SelectItem>)}</SelectContent></Select>
                <Select value={filters.image ?? 'all'} onValueChange={(value) => apply({ image: value === 'all' ? null : value })}><SelectTrigger className="w-full" aria-label="Picture status"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">All pictures</SelectItem><SelectItem value="downloaded">Pictures saved</SelectItem><SelectItem value="pending">Still saving</SelectItem><SelectItem value="failed">Download failed</SelectItem></SelectContent></Select>
            </div><div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"><span>{formatCount(products.meta.total)} matching products ? Shared website collections appear once.</span>{(filters.search || filters.site || filters.category || filters.image) && <button className="flex items-center gap-1 hover:text-foreground" type="button" onClick={() => { setSearch(''); apply({ search: '', site: null, category: null, image: null }); }}><X className="size-3" /> Clear filters</button>}</div></section>
            {selected.size > 0 && <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3"><p className="text-sm font-medium">{formatCount(selected.size)} selected across pages</p><div className="flex gap-2"><Button variant="ghost" size="sm" onClick={() => setSelected(new Set())}>Clear</Button><Button size="sm" disabled={exporting} onClick={() => exportProducts(true)}><Download /> Export selected</Button></div></div>}
            {products.data.length === 0 ? <EmptyState icon={Package} title="No products here yet" description="Collect a website or adjust your filters to find the products you need." /> : <div className="overflow-hidden rounded-2xl border bg-card"><Table><TableHeader><TableRow><TableHead className="w-10 pl-4"><Checkbox aria-label="Select this page" checked={checkedPage === products.data.length ? true : checkedPage > 0 ? 'indeterminate' : false} onCheckedChange={(value) => setSelected((old) => { const next = new Set(old); for (const product of products.data) { if (value === true) next.add(product.id); else next.delete(product.id); } return next; })} /></TableHead><TableHead>Product</TableHead><TableHead>Category</TableHead><TableHead>Website</TableHead><TableHead className="text-right">Price</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader><TableBody>{products.data.map((product) => <TableRow key={product.id} data-state={selected.has(product.id) ? 'selected' : undefined}><TableCell className="pl-4"><Checkbox aria-label={`Select ${product.name}`} checked={selected.has(product.id)} onCheckedChange={(value) => toggle(product.id, value === true)} /></TableCell><TableCell><div className="flex items-center gap-3"><ProductThumb image={product.image} name={product.name} onPreview={() => setPreview(product)} /><div className="max-w-80"><button className="line-clamp-2 text-left text-sm font-medium hover:text-primary" type="button" onClick={() => setDetails(product)}>{product.name}</button><p className="mt-1 text-xs text-muted-foreground">{product.brand ?? 'Brand not specified'}{product.sku ? ` ? ${product.sku}` : ''}</p></div></div></TableCell><TableCell className="text-sm">{product.category ?? 'Uncategorized'}</TableCell><TableCell><a href={product.website_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-primary">{product.site}<ExternalLink className="size-3" /></a></TableCell><TableCell className="text-right"><p className="font-mono text-sm font-medium">{formatTaka(product.sale_price ?? product.price)}</p><StockBadge inStock={product.in_stock} /></TableCell><TableCell><div className="flex justify-end gap-1"><Button variant="ghost" size="sm" onClick={() => setDetails(product)}><Eye /> View</Button>{canManage && <Button variant="ghost" size="icon" className="size-8" aria-label={`Edit ${product.name}`} onClick={() => setEditing(product)}><Pencil className="size-4" /></Button>}</div></TableCell></TableRow>)}</TableBody></Table></div>}
            <PaginationLinks page={products} only={['products', 'filters']} />
            <p className="text-xs text-muted-foreground">Exports appear in <Link href="/exports" className="text-primary hover:underline">Downloads</Link>. Product details are shared between collections; administrators can edit them.</p>
        </div>
        <ProductDetails product={products.data.find((product) => product.id === details?.id) ?? details} onOpenChange={(open) => !open && setDetails(null)} onPreview={() => setPreview(details)} />
        <ProductImagePreview product={preview} onOpenChange={(open) => !open && setPreview(null)} />
        {editing && <ProductEditor key={editing.id} product={editing} categories={categories} onClose={() => setEditing(null)} />}
    </>;
}

function ProductEditor({ product, categories, onClose }: { product: Product; categories: CategoryOption[]; onClose: () => void }) {
    const form = useForm({ name: product.name, brand: product.brand ?? '', sku: product.sku ?? '', description: product.description ?? '', category_id: product.category_id === null ? 'none' : String(product.category_id), price: product.price === null ? '' : String(product.price), sale_price: product.sale_price === null ? '' : String(product.sale_price), in_stock: product.in_stock === null ? 'unknown' : product.in_stock ? 'yes' : 'no' });
    return <Dialog open onOpenChange={(open) => !open && onClose()}><DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl"><DialogHeader><DialogTitle>Edit product</DialogTitle><DialogDescription>Update catalogue details for {product.site}. A future collection may refresh source information.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); form.transform((data) => ({ ...data, category_id: data.category_id === 'none' ? null : Number(data.category_id), price: data.price === '' ? null : Number(data.price), sale_price: data.sale_price === '' ? null : Number(data.sale_price), in_stock: data.in_stock === 'unknown' ? null : data.in_stock === 'yes' })); form.patch(`/products/${product.id}`, { preserveScroll: true, onSuccess: onClose }); }}>
        <div className="space-y-2"><Label htmlFor="edit-product-name">Product name</Label><Input id="edit-product-name" required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /><InputError message={form.errors.name} /></div>
        <div className="grid gap-4 sm:grid-cols-2">{(['brand', 'sku', 'price', 'sale_price'] as const).map((key) => <div key={key} className="space-y-2"><Label htmlFor={`edit-${key}`}>{({ brand: 'Brand', sku: 'SKU', price: 'Regular price', sale_price: 'Sale price' })[key]}</Label><Input id={`edit-${key}`} type={key.includes('price') ? 'number' : 'text'} min={0} step={key.includes('price') ? '0.01' : undefined} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} /><InputError message={form.errors[key]} /></div>)}</div>
        <div className="grid gap-4 sm:grid-cols-2"><div className="space-y-2"><Label>Category</Label><Select value={form.data.category_id} onValueChange={(value) => form.setData('category_id', value)}><SelectTrigger className="w-full" aria-label="Edit category"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="none">Uncategorized</SelectItem>{categories.filter((category) => category.site_id === product.site_id).map((category) => <SelectItem key={category.id} value={String(category.id)}>{category.name}</SelectItem>)}</SelectContent></Select><InputError message={form.errors.category_id} /></div><div className="space-y-2"><Label>Availability</Label><Select value={form.data.in_stock} onValueChange={(value) => form.setData('in_stock', value)}><SelectTrigger className="w-full" aria-label="Edit stock"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="unknown">Not specified</SelectItem><SelectItem value="yes">In stock</SelectItem><SelectItem value="no">Out of stock</SelectItem></SelectContent></Select></div></div>
        <div className="space-y-2"><Label htmlFor="edit-description">Description</Label><textarea id="edit-description" className="min-h-28 w-full rounded-md border bg-background p-3 text-sm" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /><InputError message={form.errors.description} /></div>
        <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button disabled={form.processing}>{form.processing && <Spinner />} Save changes</Button></div>
    </form></DialogContent></Dialog>;
}

Products.layout = { breadcrumbs: [{ title: 'Products', href: '/products' }] };
