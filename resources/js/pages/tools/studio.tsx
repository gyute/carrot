import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import ToolsNav from '@/components/tools-nav';
import { cn } from '@/lib/utils';
import { studio } from '@/routes/tools';

type StudioPage = {
    key: string;
    label: string;
    description: string;
};

type Props = {
    pages: StudioPage[];
    current: StudioPage & { url: string };
};

export default function Studio({ pages, current }: Props) {
    return (
        <>
            <Head title="スタジオ" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">スタジオ</h1>
                <a
                    href={current.url}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="ml-auto inline-flex items-center gap-1 text-sm font-medium text-sky-700 underline decoration-sky-300 underline-offset-4"
                >
                    新しいタブで開く
                    <ExternalLink className="size-3.5" />
                </a>
            </div>
            <p className="mt-1 text-sm text-slate-500">{current.description}</p>

            {pages.length > 1 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {pages.map((page) => (
                        <Link
                            key={page.key}
                            href={studio({ page: page.key })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-sm transition',
                                page.key === current.key
                                    ? 'border-sky-500 bg-sky-50 font-medium text-sky-700'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                            )}
                        >
                            {page.label}
                        </Link>
                    ))}
                </div>
            )}

            <div className="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
                    <span className="truncate font-mono">{current.url}</span>
                </div>

                {/*
                 * The frame is locked down: no allow-top-navigation, so the
                 * embedded page cannot move the portal out from under the
                 * user, and the URL only ever comes from the allowlist in
                 * config/tools.php.
                 */}
                <iframe
                    key={current.key}
                    src={current.url}
                    title={current.label}
                    sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"
                    referrerPolicy="no-referrer"
                    loading="lazy"
                    className="h-[calc(100svh-22rem)] min-h-96 w-full bg-white"
                />
            </div>

            <p className="mt-3 text-xs text-slate-400">
                埋め込みを許可していないサイトは表示されません。その場合は「新しいタブで開く」から開いてください。
            </p>
        </>
    );
}
