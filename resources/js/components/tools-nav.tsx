import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { index as tools } from '@/routes/tools';

type Tab = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
};

/**
 * The menu shared by every screen in the tool module. A tab is active when
 * the URL starts with its path; the catalog tab is the fallback so tool pages
 * light it up. Longer prefixes are checked first, since every tool path
 * starts with the catalog's.
 *
 * `actions` sits at the right end of the same row, for controls that belong to
 * the tab bar rather than to the page body.
 */
export default function ToolsNav({ actions }: { actions?: ReactNode }) {
    const { url } = usePage();

    const tabs: Tab[] = [
        { label: 'ツール一覧', href: tools().url, icon: LayoutGrid },
    ];

    const activeHref =
        [...tabs]
            .sort((a, b) => b.href.length - a.href.length)
            .find((tab) => url.startsWith(tab.href))?.href ?? tools().url;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
                {tabs.map(({ label, href, icon: Icon }) => {
                    const active = href === activeHref;

                    return (
                        <Link
                            key={href}
                            href={href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-sm font-medium transition',
                                active
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800',
                            )}
                        >
                            <Icon className="size-4" />
                            {label}
                        </Link>
                    );
                })}
            </nav>

            {actions && <div className="ml-auto">{actions}</div>}
        </div>
    );
}
