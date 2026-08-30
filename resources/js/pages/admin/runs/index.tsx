import { Head, Link, router } from '@inertiajs/react';
import { Eraser, Play, Trash2 } from 'lucide-react';
import AdminNav from '@/components/admin-nav';
import StatusPill from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { formatTimestamp } from '@/lib/format';
import { cn } from '@/lib/utils';
import { RUN_STATUS_STYLES } from '@/pages/tools/runs/show';
import { show as showApproval } from '@/routes/admin/approvals';
import { show as showTool } from '@/routes/tools';
import type { ToolRunSummary } from '@/types/tools';
import { destroy, index, prune } from '@/routes/admin/runs';

type AdminRun = ToolRunSummary & {
    tool: { ulid: string; name: string } | null;
    submission: { ulid: string } | null;
};

type Props = {
    runs: {
        data: AdminRun[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: { status: string | null };
    statuses: { value: string; label: string }[];
    retentionDays: number;
};

export default function AdminRuns({
    runs,
    filters,
    statuses,
    retentionDays,
}: Props) {
    const go = (status: string | null) =>
        router.get(
            index().url,
            { status },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="実行履歴" />

            <AdminNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <Play className="size-5 text-slate-400" />
                    実行履歴
                </h1>
                <p className="text-sm text-slate-500">
                    サンドボックスで走った全ての実行です。{retentionDays}{' '}
                    日で自動削除されます。
                </p>
                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                    {runs.total} 件
                </span>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <div className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
                    {[{ value: null, label: 'すべて' }, ...statuses].map(
                        (status) => (
                            <button
                                key={status.value ?? 'all'}
                                type="button"
                                onClick={() => go(status.value)}
                                className={cn(
                                    'rounded-md px-3 py-1 text-xs font-medium transition',
                                    (filters.status ?? null) === status.value
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800',
                                )}
                            >
                                {status.label}
                            </button>
                        ),
                    )}
                </div>

                <Button
                    size="sm"
                    variant="outline"
                    className="ml-auto"
                    onClick={() => {
                        if (
                            window.confirm(
                                `${retentionDays} 日より古い終了済みの実行を削除します。`,
                            )
                        ) {
                            router.post(prune().url);
                        }
                    }}
                >
                    <Eraser className="size-4" />
                    古い履歴を今すぐ削除
                </Button>
            </div>

            {runs.data.length === 0 ? (
                <p className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white/60 px-4 py-10 text-center text-sm text-slate-500">
                    実行履歴はありません。
                </p>
            ) : (
                <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-2 font-semibold">
                                    実行日時
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    対象
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    実行者
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    状態
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    所要
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {runs.data.map((run) => (
                                <tr key={run.ulid}>
                                    <td className="px-4 py-3 text-slate-500 tabular-nums">
                                        {formatTimestamp(run.createdAt)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {run.tool ? (
                                            <Link
                                                href={showTool(run.tool.ulid)}
                                                className="font-medium text-sky-700 hover:underline"
                                            >
                                                {run.tool.name}
                                            </Link>
                                        ) : run.submission ? (
                                            <Link
                                                href={showApproval(
                                                    run.submission.ulid,
                                                )}
                                                className="font-medium text-sky-700 hover:underline"
                                            >
                                                申請のテスト実行
                                            </Link>
                                        ) : (
                                            <span className="text-slate-400">
                                                —
                                            </span>
                                        )}
                                        <div className="text-xs text-slate-400">
                                            {run.runtimeLabel}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {run.requestedBy}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill
                                            value={run.status}
                                            label={run.statusLabel}
                                            styles={RUN_STATUS_STYLES}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-xs text-slate-500 tabular-nums">
                                        {run.durationMs === null
                                            ? '—'
                                            : `${run.durationMs} ms`}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        'この実行履歴を削除しますか？',
                                                    )
                                                ) {
                                                    router.delete(
                                                        destroy(run.ulid).url,
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {runs.lastPage > 1 && (
                <div className="mt-4 flex items-center justify-center gap-3 text-sm">
                    <Link
                        href={index({
                            query: {
                                status: filters.status,
                                page: runs.currentPage - 1,
                            },
                        })}
                        preserveState
                        className={cn(
                            'rounded-md px-3 py-1',
                            runs.currentPage === 1
                                ? 'pointer-events-none text-slate-300'
                                : 'text-sky-700 hover:underline',
                        )}
                    >
                        前へ
                    </Link>
                    <span className="text-xs text-slate-500 tabular-nums">
                        {runs.currentPage} / {runs.lastPage}
                    </span>
                    <Link
                        href={index({
                            query: {
                                status: filters.status,
                                page: runs.currentPage + 1,
                            },
                        })}
                        preserveState
                        className={cn(
                            'rounded-md px-3 py-1',
                            runs.currentPage === runs.lastPage
                                ? 'pointer-events-none text-slate-300'
                                : 'text-sky-700 hover:underline',
                        )}
                    >
                        次へ
                    </Link>
                </div>
            )}
        </>
    );
}
