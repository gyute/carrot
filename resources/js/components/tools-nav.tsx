import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardCheck,
    FilePen,
    LayoutGrid,
    MessageSquarePlus,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { index as approvals } from '@/routes/admin/approvals';
import { index as tools } from '@/routes/tools';
import { index as requests } from '@/routes/tools/requests';
import { index as submissions } from '@/routes/tools/submissions';

type Tab = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    badge?: number;
};

/**
 * The menu shared by every screen in the tool module. Longer prefixes are
 * matched first, since every tool path starts with the catalog's; the catalog
 * is the fallback, so a tool page lights it up. `actions` sits at the right
 * end of the same row.
 *
 * A tab this deployment does not run is not shown: its routes answer 404.
 * The submission tab stays up for everyone the screens exist for, even when
 * only the development team may file - people keep reaching their history.
 */
export default function ToolsNav({ actions }: { actions?: ReactNode }) {
    const { url, props } = usePage();
    const isReviewer = ['admin', 'manager'].includes(props.auth.user.role);

    const { features } = props;

    const tabs: Tab[] = [
        { label: 'ツール一覧', href: tools().url, icon: LayoutGrid },
        ...(features.requests
            ? [
                  {
                      label: '依頼',
                      href: requests().url,
                      icon: MessageSquarePlus,
                  },
              ]
            : []),
        ...(features.submissions
            ? [{ label: '申請', href: submissions().url, icon: FilePen }]
            : []),
        ...(features.submissions && isReviewer
            ? [
                  {
                      label: '承認',
                      href: approvals().url,
                      icon: ClipboardCheck,
                      badge: props.pendingApprovals,
                  },
              ]
            : []),
    ];

    const activeHref =
        [...tabs]
            .sort((a, b) => b.href.length - a.href.length)
            .find((tab) => url.startsWith(tab.href))?.href ?? tools().url;

    return (
        <div className="flex flex-wrap items-center gap-3">
            <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
                {tabs.map(({ label, href, icon: Icon, badge }) => {
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
                            {badge !== undefined && badge > 0 && (
                                <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white tabular-nums">
                                    {badge}
                                </span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            {actions && <div className="ml-auto">{actions}</div>}
        </div>
    );
}
