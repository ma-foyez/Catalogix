import { Link, usePage } from '@inertiajs/react';
import { FileDown, Globe, History, Radar } from 'lucide-react';
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
import { index as jobsIndex } from '@/routes/jobs';
import { index as sitesIndex } from '@/routes/sites';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Discover', href: discover(), icon: Radar },
    { title: 'History', href: jobsIndex(), icon: History },
    { title: 'Exports', href: exportsIndex(), icon: FileDown },
];

const adminNavItems: NavItem[] = [
    { title: 'Sites & recipes', href: sitesIndex(), icon: Globe },
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
