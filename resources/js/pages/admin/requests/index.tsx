import { Head, Link } from '@inertiajs/react';
import { MessageSquarePlus } from 'lucide-react';
import AdminNav from '@/components/admin-nav';
import StatusPill from '@/components/status-pill';
import { formatDateTime } from '@/lib/format';
import {
    REQUEST_PRIORITY_STYLES,
    REQUEST_STATUS_STYLES,
} from '@/lib/tool-presets';
import { show } from '@/routes/admin/requests';
import type { ToolRequestSummary } from '@/types/tools';

type Props = {
    open: ToolRequestSummary[];
    working: ToolRequestSummary[];
    closed: ToolRequestSummary[];
};

function Table({
    rows,
    emptyText,
}: {
    rows: ToolRequestSummary[];
    emptyText: string;
}) {
    if (rows.length === 0) {
        return (
            <p className="mt-2 rounded-xl border border-dashed border-slate-300 bg-white/60 px-4 py-8 text-center text-sm text-slate-500">
                {emptyText}
            </p>
        );
    }

    return (
        <div className="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs text-slate-500">
                    <tr>
                        <th className="px-4 py-2 font-semibold">タイトル</th>
                        <th className="px-4 py-2 font-semibold">依頼者</th>
                        <th className="px-4 py-2 font-semibold">所属</th>
                        <th className="px-4 py-2 font-semibold">状態</th>
                        <th className="px-4 py-2 font-semibold">優先度</th>
                        <th className="px-4 py-2 font-semibold">担当</th>
                        <th className="px-4 py-2 font-semibold">依頼日時</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {rows.map((item) => (
                        <tr key={item.ulid} className="hover:bg-slate-50">
                            <td className="px-4 py-2">
                                <Link
                                    href={show(item.ulid)}
                                    className="font-medium text-sky-700 hover:underline"
                                >
                                    {item.title}
                                </Link>
                            </td>
                            <td className="px-4 py-2 text-slate-600">
                                {item.requester}
                            </td>
                            <td className="px-4 py-2 text-slate-600">
                                {item.department ?? '—'}
                            </td>
                            <td className="px-4 py-2">
                                <StatusPill
                                    value={item.status}
                                    label={item.statusLabel}
                                    styles={REQUEST_STATUS_STYLES}
                                />
                            </td>
                            <td className="px-4 py-2">
                                {item.priority ? (
                                    <StatusPill
                                        value={item.priority}
                                        label={item.priorityLabel ?? ''}
                                        styles={REQUEST_PRIORITY_STYLES}
                                    />
                                ) : (
                                    <span className="text-slate-400">—</span>
                                )}
                            </td>
                            <td className="px-4 py-2 text-slate-600">
                                {item.assignee ?? '—'}
                            </td>
                            <td className="px-4 py-2 text-slate-500 tabular-nums">
                                {formatDateTime(item.createdAt)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function AdminRequestsIndex({ open, working, closed }: Props) {
    return (
        <>
            <Head title="リクエスト対応" />

            <AdminNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="text-xl font-bold text-slate-800">
                    リクエスト対応
                </h1>
                <p className="text-sm text-slate-500">
                    現場から届いたツールの依頼です。重複はここでまとめます。
                </p>
            </div>

            <h2 className="mt-6 flex items-center gap-2 text-sm font-bold text-slate-700">
                <MessageSquarePlus className="size-4" />
                未対応
                {open.length > 0 && (
                    <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white tabular-nums">
                        {open.length}
                    </span>
                )}
            </h2>
            <Table rows={open} emptyText="未対応のリクエストはありません。" />

            <h2 className="mt-8 text-sm font-bold text-slate-700">対応中</h2>
            <Table
                rows={working}
                emptyText="対応予定・対応中のリクエストはありません。"
            />

            <h2 className="mt-8 text-sm font-bold text-slate-700">完了</h2>
            <Table rows={closed} emptyText="完了したリクエストはありません。" />
        </>
    );
}
