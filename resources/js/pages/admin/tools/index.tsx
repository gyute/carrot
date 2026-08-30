import { Head, Link, router } from '@inertiajs/react';
import { LayoutGrid, RotateCcw, Search, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';
import AdminNav from '@/components/admin-nav';
import StatusPill from '@/components/status-pill';
import ToolIcon from '@/components/tool-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatTimestamp } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, purge, restore, untrash } from '@/routes/admin/tools';
import { show as showTool } from '@/routes/tools';

type AdminTool = {
    ulid: string;
    slug: string;
    name: string;
    icon: string;
    kind: string;
    kindLabel: string;
    status: 'running' | 'deprecated' | 'deleted';
    department: string | null;
    owner: string | null;
    version: string | null;
    categories: string[];
    publishedAt: string | null;
    deprecatedAt: string | null;
    deletedAt: string | null;
};

type Props = {
    tools: AdminTool[];
    filters: { q: string; state: string };
    counts: { running: number; deprecated: number; deleted: number };
};

const STATE_STYLES: Record<string, string> = {
    running: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    deprecated: 'bg-slate-100 text-slate-500 ring-slate-200',
    deleted: 'bg-rose-50 text-rose-700 ring-rose-200',
};

export default function AdminTools({ tools, filters, counts }: Props) {
    const [search, setSearch] = useState(filters.q);

    const go = (params: { q?: string; state?: string }) =>
        router.get(
            index().url,
            {
                q: params.q ?? search,
                state:
                    params.state === undefined ? filters.state : params.state,
            },
            { preserveState: true, replace: true },
        );

    const confirmThen = (message: string, run: () => void) => () => {
        if (window.confirm(message)) {
            run();
        }
    };

    const states = [
        { value: '', label: 'すべて' },
        { value: 'running', label: `稼働中 ${counts.running}` },
        { value: 'deprecated', label: `非推奨 ${counts.deprecated}` },
        { value: 'deleted', label: `削除済み ${counts.deleted}` },
    ];

    return (
        <>
            <Head title="ツール管理" />

            <AdminNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <LayoutGrid className="size-5 text-slate-400" />
                    ツール管理
                </h1>
                <p className="text-sm text-slate-500">
                    カタログに出ない行もここにあります。削除は論理削除なので元に戻せます。
                </p>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        go({});
                    }}
                    className="relative"
                >
                    <Search className="absolute top-2.5 left-2.5 size-4 text-slate-400" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="名前・スラッグ・所属"
                        className="h-9 w-64 pl-8 text-sm"
                    />
                </form>

                <div className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
                    {states.map((state) => (
                        <button
                            key={state.value}
                            type="button"
                            onClick={() => go({ state: state.value })}
                            className={cn(
                                'rounded-md px-3 py-1 text-xs font-medium transition',
                                filters.state === state.value
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800',
                            )}
                        >
                            {state.label}
                        </button>
                    ))}
                </div>
            </div>

            {tools.length === 0 ? (
                <p className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white/60 px-4 py-10 text-center text-sm text-slate-500">
                    条件に一致するツールがありません。
                </p>
            ) : (
                <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-2 font-semibold">
                                    ツール
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    種類 / 所属
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    バージョン
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    状態
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tools.map((tool) => (
                                <tr key={tool.ulid} className="align-top">
                                    <td className="px-4 py-3">
                                        <span className="flex items-center gap-2 font-medium text-slate-800">
                                            <ToolIcon
                                                name={tool.icon}
                                                className="size-4 text-slate-400"
                                            />
                                            {tool.status === 'deleted' ? (
                                                tool.name
                                            ) : (
                                                <Link
                                                    href={showTool(tool.ulid)}
                                                    className="text-sky-700 hover:underline"
                                                >
                                                    {tool.name}
                                                </Link>
                                            )}
                                        </span>
                                        <span className="font-mono text-xs text-slate-400">
                                            {tool.slug}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-slate-500">
                                        {tool.kindLabel}
                                        {tool.department && (
                                            <div>{tool.department}</div>
                                        )}
                                        {tool.owner && (
                                            <div className="text-slate-400">
                                                {tool.owner}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                        {tool.version
                                            ? `v${tool.version}`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill
                                            value={tool.status}
                                            styles={STATE_STYLES}
                                        />
                                        <div className="mt-1 text-[11px] text-slate-400 tabular-nums">
                                            {formatTimestamp(
                                                tool.deletedAt ??
                                                    tool.deprecatedAt ??
                                                    tool.publishedAt,
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {tool.status === 'deleted' ? (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={confirmThen(
                                                        `「${tool.name}」をカタログに戻しますか？`,
                                                        () =>
                                                            router.post(
                                                                untrash(
                                                                    tool.ulid,
                                                                ).url,
                                                            ),
                                                    )}
                                                >
                                                    <Undo2 className="size-4" />
                                                    元に戻す
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="ml-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                                    onClick={confirmThen(
                                                        `「${tool.name}」を完全に削除します。申請と実行履歴も消え、元に戻せません。`,
                                                        () =>
                                                            router.delete(
                                                                purge(tool.ulid)
                                                                    .url,
                                                            ),
                                                    )}
                                                >
                                                    <Trash2 className="size-4" />
                                                    完全削除
                                                </Button>
                                            </>
                                        ) : (
                                            tool.status === 'deprecated' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={confirmThen(
                                                        '稼働中に戻しますか？',
                                                        () =>
                                                            router.post(
                                                                restore(
                                                                    tool.ulid,
                                                                ).url,
                                                            ),
                                                    )}
                                                >
                                                    <RotateCcw className="size-4" />
                                                    稼働中に戻す
                                                </Button>
                                            )
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
