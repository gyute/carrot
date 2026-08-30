import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';
import EmbedFrame from '@/components/embed-frame';
import StatusPill from '@/components/status-pill';
import ToolIcon from '@/components/tool-icon';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { KIND_LABELS, STATUS_STYLES, toolAccent } from '@/lib/tool-presets';
import { index } from '@/routes/tools';
import type { ToolDetail } from '@/types/tools';

type Props = {
    tool: ToolDetail;
};

/**
 * A tool's own page. A link tool is opened from here; an embed tool is framed
 * here, so an external page never gets a screen of its own.
 */
export default function ToolShow({ tool }: Props) {
    const isDeprecated = tool.status === 'deprecated';
    const opensElsewhere = tool.kind === 'link' && tool.href;

    return (
        <>
            <Head title={tool.name} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                ツール一覧へ
            </Link>

            <div className="mt-3 flex flex-wrap items-start gap-4">
                <span
                    className={`flex size-14 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br ${toolAccent(tool.accent)} text-white shadow-sm`}
                >
                    <ToolIcon name={tool.icon} className="size-7" />
                </span>

                <div className="min-w-0 grow">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-bold text-slate-800">
                            {tool.name}
                        </h1>
                        <StatusPill
                            value={tool.status}
                            styles={STATUS_STYLES}
                        />
                        <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                            {KIND_LABELS[tool.kind]}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                        {tool.summary}
                    </p>
                    <dl className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                        <div>
                            バージョン{' '}
                            <span className="font-mono font-medium text-slate-700">
                                {tool.version ? `v${tool.version}` : '—'}
                            </span>
                        </div>
                        <div>
                            所有者{' '}
                            <span className="font-medium text-slate-700">
                                {tool.owner ?? '—'}
                            </span>
                        </div>
                        {tool.department && <div>所属 {tool.department}</div>}
                        <div className="tabular-nums">
                            公開 {formatDateTime(tool.publishedAt)}
                        </div>
                    </dl>
                </div>

                {opensElsewhere && !isDeprecated && (
                    <Button
                        asChild
                        className="bg-sky-700 text-white hover:bg-sky-800"
                    >
                        <Link href={tool.href as string}>
                            開く
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </Button>
                )}
            </div>

            {isDeprecated && (
                <div className="mt-4 rounded-lg border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-700">
                    このツールは {formatDateTime(tool.deprecatedAt)}{' '}
                    に非推奨になりました。新しい利用は推奨されません。
                </div>
            )}

            {tool.description && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">説明</h2>
                    <p className="mt-2 text-sm leading-relaxed whitespace-pre-wrap text-slate-700">
                        {tool.description}
                    </p>
                </section>
            )}

            {tool.categories.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {tool.categories.map((category) => (
                        <span
                            key={category}
                            className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                        >
                            {category}
                        </span>
                    ))}
                </div>
            )}

            {tool.kind === 'embed' && !isDeprecated && (
                <div className="mt-6">
                    {tool.embedUrl ? (
                        <EmbedFrame url={tool.embedUrl} title={tool.name} />
                    ) : (
                        <p className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            この URL は埋め込めません。外部の https
                            ページのみ表示できます。
                        </p>
                    )}
                </div>
            )}
        </>
    );
}
