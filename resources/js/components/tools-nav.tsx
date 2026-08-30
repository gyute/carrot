import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import { cn } from '@/lib/utils';
import { index as tools } from '@/routes/tools';

const LINKS = [{ label: 'ツール一覧', href: tools().url, icon: LayoutGrid }];

/**
 * The menu shared by every screen in the tool module.
 */
export default function ToolsNav() {
    const { url } = usePage();

    return (
        <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
            {LINKS.map(({ label, href, icon: Icon }) => {
                const active = url.startsWith(href);

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
