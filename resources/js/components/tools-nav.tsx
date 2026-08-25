import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { index as tools } from '@/routes/tools';
import { jobs } from '@/routes/tools/exports';

const LINKS = [
    { label: 'ツール一覧', href: tools().url },
    { label: 'バッチ一覧', href: jobs().url },
];

/**
 * The menu shared by every screen in the tool module.
 */
export default function ToolsNav() {
    const { url } = usePage();

    return (
        <nav className="flex gap-1 border-b border-slate-200">
            {LINKS.map(({ label, href }) => (
                <Link
                    key={href}
                    href={href}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                        url.startsWith(href) &&
                            (href !== tools().url || url === href)
                            ? 'border-sky-600 text-sky-700'
                            : 'border-transparent text-slate-500 hover:text-slate-800',
                    )}
                >
                    {label}
                </Link>
            ))}
        </nav>
    );
}
