import { Armchair, ArrowDownToLine, Check, LampDesk, Package, Search, Table2 } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

const DEMO = [
    { name: 'Arc lounge chair', category: 'Living room', brand: 'Studio Home', price: '? 6,500', icon: Armchair, color: 'bg-[#efe7df] text-[#8d6c55]' },
    { name: 'Linea work desk', category: 'Office', brand: 'Studio Home', price: '? 12,800', icon: Table2, color: 'bg-[#e5e9ed] text-[#64768a]' },
    { name: 'Halo table lamp', category: 'Lighting', brand: 'Studio Home', price: '? 2,400', icon: LampDesk, color: 'bg-[#eee9f7] text-[#8a70ae]' },
];

export function CatalogPreview({ className, compact = false }: { className?: string; compact?: boolean }) {
    return <figure aria-label="Illustrative catalogue preview with sample products" className={cn('overflow-hidden rounded-2xl border border-black/8 bg-white text-zinc-900 shadow-2xl shadow-violet-950/10', className)}>
        <div className="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5"><span className="flex items-center gap-2 text-sm font-semibold"><span className="flex size-7 items-center justify-center rounded-lg bg-violet-600 text-white"><AppLogoIcon className="size-4 fill-current" /></span>Catalogix</span><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-medium text-emerald-700">? Catalogue ready</span></div>
        <div className={cn('space-y-4', compact ? 'p-4' : 'p-5 sm:p-6')}>
            <div className="flex items-center justify-between gap-2"><div><p className="text-[10px] tracking-widest text-zinc-400 uppercase">Your workspace</p><h3 className="mt-1 text-lg font-semibold tracking-tight">A well-collected collection.</h3></div><Package className="size-5 text-violet-500" /></div>
            {!compact && <div className="grid grid-cols-3 gap-2">{[['575', 'Products'], ['24', 'Categories'], ['575', 'Pictures']].map(([number, label]) => <div key={label} className="rounded-xl bg-zinc-50 px-3 py-3"><p className="text-xl font-semibold tracking-tight">{number}</p><p className="mt-0.5 text-[10px] text-zinc-500">{label}</p></div>)}</div>}
            <div className="flex items-center gap-2 rounded-lg border border-zinc-100 px-3 py-2 text-[11px] text-zinc-400"><Search className="size-3.5" /> Find your next product?<span className="ml-auto rounded border px-1 text-[9px]">? K</span></div>
            <div className="space-y-2">{DEMO.map((item) => <div key={item.name} className="flex items-center gap-3 rounded-xl border border-zinc-100 p-2.5"><span className="flex size-3.5 shrink-0 items-center justify-center rounded bg-violet-600 text-white"><Check className="size-2.5" /></span><span className={cn('flex size-12 shrink-0 items-center justify-center rounded-lg', item.color)}><item.icon className="size-7" strokeWidth={1.3} /></span><div className="min-w-0 flex-1"><p className="truncate text-xs font-semibold">{item.name}</p><p className="mt-1 truncate text-[10px] text-zinc-400">{item.brand} ? {item.category}</p></div><span className="text-xs font-medium whitespace-nowrap">{item.price}</span></div>)}</div>
            <div className="flex items-center justify-between gap-2 rounded-xl bg-violet-50 p-3"><span className="text-[11px] font-medium text-violet-800">3 products selected</span><span className="flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-[10px] font-semibold text-white"><ArrowDownToLine className="size-3" /> Export selected</span></div>
        </div>
        <figcaption className="border-t border-zinc-100 py-2 text-center text-[9px] tracking-wider text-zinc-400 uppercase">Catalogue preview ? Sample data</figcaption>
    </figure>;
}
