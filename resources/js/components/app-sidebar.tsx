import { Link, usePage } from '@inertiajs/react';
import { FileDown, Globe, Wand2, Package, Tags, History, Settings } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { discover } from '@/routes';
import { index as exportsIndex } from '@/routes/exports';
import { index as sitesIndex } from '@/routes/sites';
import { index as productsIndex } from '@/routes/products';
import { index as categoriesIndex } from '@/routes/categories';
import { index as jobsIndex } from '@/routes/jobs';
import { edit as profileEdit } from '@/routes/profile';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Products', href: productsIndex(), icon: Package },
    { title: 'Websites', href: discover(), icon: Globe },
    { title: 'Categories', href: categoriesIndex(), icon: Tags },
    { title: 'Collections', href: jobsIndex(), icon: History },
    { title: 'Downloads', href: exportsIndex(), icon: FileDown },
];

const adminNavItems: NavItem[] = [
    { title: 'Site recipes', href: sitesIndex(), icon: Wand2 },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={discover()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Catalogue" />
                <NavMain items={[{ title: 'Settings', href: profileEdit(), icon: Settings }]} label="Workspace" />
                {auth.user.is_admin === true && (
                    <NavMain items={adminNavItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
