/**
 * Staff/admin sidebar navigation — mirrors layouts/sec.blade.php top nav.
 * Single source for sidebar links and breadcrumb resolution.
 */
export const adminNavigation = [
    {
        label: 'Home',
        href: '/book',
        routeName: 'book.index',
        icon: 'Home',
    },
    {
        label: 'Attendance',
        icon: 'ClipboardCheck',
        children: [
            { label: 'Attendance', href: '/attendance', routeName: 'attendance.scan' },
            { label: 'Attendance Logs', href: '/attendance-logs', routeName: 'attendance_logs.index', routePrefix: 'attendance_logs.' },
            { label: 'Change Video', href: '/attendance/change-video', routeName: 'attendance.changeVideo' },
            { label: 'Logout Feedback', href: '/attendance/logout-feedback', routeName: 'attendance.feedback.settings' },
            { label: 'View Feedback Responses', href: '/admin/attendance-feedbacks', routeName: 'admin.attendance.feedbacks' },
        ],
    },
    {
        label: 'Data',
        icon: 'Database',
        children: [
            { label: 'Student Data', href: '/students', routeName: 'students.index', routePrefix: 'students.' },
            { label: 'Faculty & Staff Data', href: '/employees', routeName: 'employees.index', routePrefix: 'employees.' },
        ],
    },
    {
        label: 'OPAC',
        href: '/opac',
        routeName: 'landing',
        icon: 'BookOpen',
    },
    {
        label: 'Circulation',
        icon: 'Library',
        children: [
            { label: 'Circulation', href: '/logs', routeName: 'logs.index' },
            { label: 'Outstanding Fines', href: '/admin/fines/outstanding', routeName: 'fines.outstanding', adminOnly: true },
            { label: 'Copy Cataloging', href: '/catalog/copy/openlibrary', routeName: 'catalog.copy.openlibrary.form', routePrefix: 'catalog.copy.' },
            { label: 'Library Holdings Report', href: '/reports/library-holdings', routeName: 'reports.library_holdings.create', routePrefix: 'reports.library_holdings.' },
            { label: 'Download Book Report (PDF)', href: '/download-book-report', routeName: 'book.report.download' },
            { label: 'Fines and Due Dates', href: '/admin/fines', routeName: 'fines.edit', adminOnly: true },
            { label: 'Borrow Limits', href: '/admin/circulation/borrow-limits', routeName: 'circulation.borrow_limits.edit', adminOnly: true },
        ],
    },
    {
        label: 'Admin',
        icon: 'Shield',
        children: [
            { label: 'Student Feedback', href: '/feedbacks', routeName: 'feedback.index', routePrefix: 'feedback.' },
            { label: 'Repository', href: '/files', routeName: 'files.index', routePrefix: 'files.' },
            { label: 'Prospectus Manager', href: '/prospectus', routeName: 'prospectus.index', routePrefix: 'prospectus.' },
            { label: 'View Pantas Users', href: '/view-users', routeName: 'users.index', routePrefix: 'users.', adminOnly: true },
            { label: 'MARC catalog frameworks', href: '/admin/catalog-frameworks', routeName: 'admin.catalog_frameworks.index', routePrefix: 'admin.catalog_frameworks.', adminOnly: true },
            { label: 'Catalog dropdown options', href: '/admin/catalog-select-options', routeName: 'admin.catalog_select_options.index', routePrefix: 'admin.catalog_select_options.', adminOnly: true },
            { label: 'Hybrid devices', href: '/admin/hybrid-devices', routeName: 'hybrid_devices.index', routePrefix: 'hybrid_devices.', adminOnly: true },
        ],
    },
    {
        label: 'Room Reservations',
        icon: 'DoorOpen',
        children: [
            { label: 'Manage Rooms', href: '/rooms', routeName: 'rooms.index' },
            { label: 'Book a Room', href: '/rooms/book', routeName: 'rooms.book' },
            { label: 'View Schedule', href: '/rooms/schedule', routeName: 'rooms.schedule' },
            { label: 'Pending Reservations', href: '/rooms/pending', routeName: 'rooms.pending' },
            { label: 'Reservation Logs', href: '/rooms/logs', routeName: 'rooms.logs' },
            { label: 'SMS Blast', href: '/sms-blast', routeName: 'sms.page', adminOnly: true },
        ],
    },
];

export function filterNavigation(items, isAdmin) {
    return items
        .map((item) => {
            if (item.adminOnly && !isAdmin) {
                return null;
            }

            if (item.children) {
                const children = item.children.filter(
                    (child) => !child.adminOnly || isAdmin,
                );

                if (children.length === 0) {
                    return null;
                }

                return { ...item, children };
            }

            return item;
        })
        .filter(Boolean);
}

function findTrail(items, routeName, parents = []) {
    for (const item of items) {
        if (item.routeName === routeName) {
            return [...parents, item];
        }

        if (item.routePrefix && routeName?.startsWith(item.routePrefix)) {
            return [...parents, item];
        }

        if (item.children) {
            const trail = findTrail(item.children, routeName, [...parents, item]);

            if (trail) {
                return trail;
            }
        }
    }

    return null;
}

export function resolveBreadcrumbs(routeName, override) {
    if (override?.length) {
        return override;
    }

    if (!routeName) {
        return [{ label: 'Home', href: '/book', isCurrent: true }];
    }

    const trail = findTrail(adminNavigation, routeName);

    if (!trail) {
        return [{ label: 'Home', href: '/book', isCurrent: true }];
    }

    return trail.map((item, index) => {
        const isLast = index === trail.length - 1;
        const href =
            item.href ??
            item.children?.find((child) => child.href)?.href ??
            null;

        return {
            label: item.label,
            href: isLast ? null : href,
            isCurrent: isLast,
        };
    });
}

export function isNavItemActive(item, routeName, pathname) {
    if (item.routePrefix && routeName?.startsWith(item.routePrefix)) {
        return true;
    }

    const routeNames = item.routeNames ?? (item.routeName ? [item.routeName] : []);

    if (routeName && routeNames.includes(routeName)) {
        return true;
    }

    // Prefer server route name on Blade pages — avoids false positives
    // (e.g. /rooms/logs also matching the /rooms parent href).
    if (routeName) {
        return false;
    }

    if (item.href && pathname) {
        if (pathname === item.href) {
            return true;
        }

        if (item.href !== '/book' && pathname.startsWith(`${item.href}/`)) {
            return true;
        }
    }

    return false;
}

export function isNavGroupActive(item, routeName, pathname) {
    if (item.children) {
        return item.children.some((child) => isNavItemActive(child, routeName, pathname));
    }

    return isNavItemActive(item, routeName, pathname);
}
