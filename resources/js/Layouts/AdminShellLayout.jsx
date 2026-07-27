import { AppBreadcrumb } from '@/components/layout/AppBreadcrumb';
import { AppHeader } from '@/components/layout/AppHeader';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { resolveBreadcrumbs } from '@/config/adminNavigation';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';

export function AdminShellLayout({
    routeName,
    breadcrumbOverride,
    contentRef,
    children,
}) {
    const breadcrumbs = resolveBreadcrumbs(routeName, breadcrumbOverride);

    return (
        <TooltipProvider>
            <SidebarProvider className="admin-shell min-w-0 w-full">
                <AppSidebar />
                <SidebarInset>
                    <AppHeader />
                    <div
                        data-slot="admin-main"
                        className="flex flex-1 flex-col gap-3 p-3 sm:gap-4 sm:p-4 md:p-6"
                    >
                        <AppBreadcrumb items={breadcrumbs} />
                        {children}
                        {contentRef ? (
                            <div
                                ref={contentRef}
                                className="admin-blade-slot min-w-0 [&_.container]:max-w-full"
                            />
                        ) : null}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    );
}
