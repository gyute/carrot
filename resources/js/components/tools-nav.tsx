import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ListChecks } from 'lucide-react';
import { cn } from '@/lib/utils';
import { index as tools } from '@/routes/tools';
import { jobs } from '@/routes/tools/exports';

const JOBS_HREF = jobs().url;

const LINKS = [
    { label: 'ツール一覧', href: tools().url, icon: LayoutGrid },
    { label: 'バッチ一覧', href: JOBS_HREF, icon: ListChecks },
];

/**
 * The menu shared by every screen in the tool module. Every screen that is not
 * the batch list belongs to the catalog, so the two tabs split on that.
 */
export default function ToolsNav() {
    const { url } = usePage();
    const onJobs = url.startsWith(JOBS_HREF);

    return (
        <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
            {LINKS.map(({ label, href, icon: Icon }) => {
                const active = href === JOBS_HREF ? onJobs : !onJobs;

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
    );
}
