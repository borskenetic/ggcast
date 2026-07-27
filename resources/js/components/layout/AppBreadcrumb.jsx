import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Fragment } from 'react';

export function AppBreadcrumb({ items = [] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Breadcrumb>
            <BreadcrumbList>
                {items.map((item, index) => {
                    const isLast = item.isCurrent ?? index === items.length - 1;
                    const isClickable = !isLast && Boolean(item.href);

                    return (
                        <Fragment key={`${item.label}-${index}`}>
                            <BreadcrumbItem>
                                {isClickable ? (
                                    <BreadcrumbLink href={item.href}>{item.label}</BreadcrumbLink>
                                ) : (
                                    <BreadcrumbPage>{item.label}</BreadcrumbPage>
                                )}
                            </BreadcrumbItem>
                            {!isLast && <BreadcrumbSeparator />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
