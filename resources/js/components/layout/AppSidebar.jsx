import { useShellProps } from '@/context/ShellPropsContext';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    BookOpen,
    ChevronRight,
    ClipboardCheck,
    Database,
    DoorOpen,
    FileBarChart,
    Home,
    Library,
    Shield,
} from 'lucide-react';
import {
    adminNavigation,
    filterNavigation,
    isNavGroupActive,
    isNavItemActive,
} from '@/config/adminNavigation';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

const iconMap = {
    Home,
    ClipboardCheck,
    Database,
    BookOpen,
    Library,
    Shield,
    DoorOpen,
    FileBarChart,
};

const activeItemClass =
    'data-[active=true]:border-l-[3px] data-[active=true]:border-sidebar-primary data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[active=true]:shadow-sm';

const navButtonClass =
    'rounded-md transition-[color,background-color,box-shadow,border-color] duration-150';

function NavIcon({ name, active = false }) {
    const Icon = iconMap[name] ?? Home;

    return (
        <Icon
            className={cn(
                'size-4 shrink-0 transition-opacity',
                active ? 'opacity-100' : 'opacity-85',
            )}
        />
    );
}

function closeMobileNav(isMobile, setOpenMobile) {
    if (isMobile) {
        setOpenMobile(false);
    }
}

function SidebarBrand() {
    const { branding } = useShellProps();
    const assets = branding?.assets ?? {};
    const schoolName = branding?.schoolName ?? 'Library';
    const libraryName = branding?.libraryName ?? 'Library';
    const systemName = branding?.systemName ?? 'PANTAS';
    const portalSubtitle = branding?.portalSubtitle ?? 'Staff portal';

    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <SidebarHeader className="relative overflow-hidden border-b border-sidebar-border/50 px-2 pb-3 pt-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        asChild
                        className={cn(
                            navButtonClass,
                            'rounded-xl hover:bg-sidebar-accent/25 group-data-[collapsible=icon]:rounded-lg',
                        )}
                    >
                        <a
                            href="/book"
                            onClick={() => closeMobileNav(isMobile, setOpenMobile)}
                        >
                            <div className="flex aspect-square size-9 shrink-0 items-center justify-center rounded-full bg-white p-0.5 shadow-md ring-1 ring-sidebar-primary/40">
                                <img
                                    src={assets.logo || '/images/branding/logo.png'}
                                    alt={schoolName}
                                    className="size-full rounded-full object-contain"
                                />
                            </div>
                            <div className="grid min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span className="truncate text-sm font-semibold tracking-tight">
                                    {systemName}
                                </span>
                                <span className="truncate text-[11px] text-sidebar-foreground/70">
                                    {libraryName}
                                </span>
                                <span className="sidebar-portal-text truncate text-[10px] font-medium uppercase tracking-[0.12em]">
                                    {portalSubtitle}
                                </span>
                            </div>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
    );
}

function SidebarUserPanel() {
    const { auth, branding } = useShellProps();
    const user = auth?.user;
    const systemName = branding?.systemName ?? 'PANTAS';
    const schoolName = branding?.schoolName ?? '';

    if (!user) {
        return null;
    }

    const roleLabel = user.isAdmin ? 'Administrator' : 'Staff';

    return (
        <SidebarFooter className="gap-2 border-t border-sidebar-border/50 p-2">
            <a
                href="/account"
                className="flex items-center gap-2.5 rounded-lg px-2 py-2 text-sidebar-foreground no-underline transition-colors hover:bg-sidebar-accent/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sidebar-ring"
            >
                <Avatar className="size-9 shrink-0 ring-2 ring-sidebar-primary/30">
                    {user.avatarUrl ? (
                        <AvatarImage src={user.avatarUrl} alt={user.name} />
                    ) : null}
                    <AvatarFallback className="bg-sidebar-accent text-xs font-semibold text-sidebar-accent-foreground">
                        {user.initials}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                    <p className="truncate text-sm font-medium leading-tight">{user.name}</p>
                    <div className="mt-1 flex items-center gap-1.5">
                        <Badge
                            variant="outline"
                            className="sidebar-role-badge h-5 px-1.5 text-[10px] font-semibold uppercase tracking-wide"
                        >
                            {roleLabel}
                        </Badge>
                    </div>
                </div>
            </a>
            <p className="px-2 text-center text-[10px] text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden">
                {systemName} © {new Date().getFullYear()}
                {schoolName ? ` · ${schoolName}` : ''}
            </p>
        </SidebarFooter>
    );
}

function NavLink({ item, routeName, pathname }) {
    const { isMobile, setOpenMobile } = useSidebar();
    const active = isNavItemActive(item, routeName, pathname);

    return (
        <SidebarMenuButton
            asChild
            isActive={active}
            tooltip={item.label}
            className={cn(navButtonClass, activeItemClass)}
        >
            <a
                href={item.href}
                onClick={() => closeMobileNav(isMobile, setOpenMobile)}
            >
                {item.icon ? <NavIcon name={item.icon} active={active} /> : null}
                <span>{item.label}</span>
            </a>
        </SidebarMenuButton>
    );
}

function NavGroup({ item, routeName, pathname }) {
    const { isMobile, setOpenMobile } = useSidebar();
    const open = isNavGroupActive(item, routeName, pathname);
    const hasActiveChild = item.children.some((child) =>
        isNavItemActive(child, routeName, pathname),
    );

    return (
        <Collapsible asChild defaultOpen={open} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={item.label}
                        className={cn(
                            navButtonClass,
                            open && 'bg-sidebar-accent/10',
                            hasActiveChild &&
                                'border-l-[3px] border-sidebar-primary/70 bg-sidebar-accent/15 font-medium',
                        )}
                    >
                        <NavIcon name={item.icon} active={hasActiveChild} />
                        <span>{item.label}</span>
                        <ChevronRight className="ml-auto size-4 opacity-60 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent className="overflow-hidden">
                    <SidebarMenuSub className="mx-0 min-w-0 max-w-full border-l border-sidebar-primary/25 pl-2">
                        {item.children.map((child) => {
                            const active = isNavItemActive(child, routeName, pathname);

                            return (
                                <SidebarMenuSubItem key={child.routeName ?? child.href}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={active}
                                        className={cn(
                                            'min-w-0 max-w-full',
                                            navButtonClass,
                                            activeItemClass,
                                        )}
                                    >
                                        <a
                                            href={child.href}
                                            className="min-w-0 max-w-full gap-2"
                                            onClick={() =>
                                                closeMobileNav(isMobile, setOpenMobile)
                                            }
                                        >
                                            {active ? (
                                                <span
                                                    aria-hidden
                                                    className="size-1.5 shrink-0 rounded-full bg-sidebar-primary"
                                                />
                                            ) : (
                                                <span
                                                    aria-hidden
                                                    className="size-1.5 shrink-0 rounded-full bg-sidebar-foreground/25"
                                                />
                                            )}
                                            <span className="truncate">{child.label}</span>
                                        </a>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function AppSidebar({ ...props }) {
    const { auth, routeName } = useShellProps();
    const isAdmin = auth?.user?.isAdmin ?? false;
    const pathname = typeof window !== 'undefined' ? window.location.pathname : '';
    const navItems = filterNavigation(adminNavigation, isAdmin);

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarBrand />

            <SidebarContent className="gap-0 overflow-x-hidden px-1.5 py-2">
                <SidebarGroup className="p-0">
                    <SidebarGroupLabel className="mb-1 flex items-center gap-2 px-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-sidebar-foreground/55">
                        <span className="h-px flex-1 bg-sidebar-border/60" aria-hidden />
                        Menu
                        <span className="h-px flex-1 bg-sidebar-border/60" aria-hidden />
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu className="gap-0.5">
                            {navItems.map((item) =>
                                item.children ? (
                                    <NavGroup
                                        key={item.label}
                                        item={item}
                                        routeName={routeName}
                                        pathname={pathname}
                                    />
                                ) : (
                                    <SidebarMenuItem key={item.label}>
                                        <NavLink
                                            item={item}
                                            routeName={routeName}
                                            pathname={pathname}
                                        />
                                    </SidebarMenuItem>
                                ),
                            )}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarSeparator className="mx-2 bg-sidebar-border/40" />

            <SidebarUserPanel />
            <SidebarRail />
        </Sidebar>
    );
}
