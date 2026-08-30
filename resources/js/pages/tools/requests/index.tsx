import { Head, Link, usePage } from '@inertiajs/react';
import { MessageSquarePlus, Plus } from 'lucide-react';
import { useState } from 'react';
import StatusPill from '@/components/status-pill';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { REQUEST_STATUS_STYLES } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { create, show } from '@/routes/tools/requests';
import type { ToolRequestSummary } from '@/types/tools';

export default function RequestsIndex({
    requests,
}: {
    requests: ToolRequestSummary[];
}) {
    const { auth } = usePage().props;
    const [mineOnly, setMineOnly] = useState(false);

    const shown = mineOnly
        ? requests.filter((item) => item.requester === auth.user.name)
        : requests;

    return (
        <>
            <Head title="依頼" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="text-xl font-bold text-slate-800">依頼</h1>
                <p className="text-sm text-slate-500">
                    ほしいツールを開発チームに依頼できます。同じ所属のメンバーにも表示されます。
                </p>
                <Button
                    asChild
                    size="sm"
                    className="ml-auto bg-sky-700 text-white hover:bg-sky-800"
                >
                    <Link href={create()}>
                        <Plus className="size-4" />
                        依頼する
                    </Link>
                </Button>
            </div>

            {requests.length > 0 && (
                <div className="mt-4 inline-flex gap-1 rounded-lg bg-slate-200/60 p-1 text-sm">
                    {[
                        { label: 'すべて', value: false },
                        { label: '自分の依頼', value: true },
                    ].map(({ label, value }) => (
                        <button
                            key={label}
                            type="button"
                            aria-pressed={mineOnly === value}
                            onClick={() => setMineOnly(value)}
                            className={cn(
                                'rounded-md px-3 py-1 font-medium transition',
                                mineOnly === value
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}

            {shown.length === 0 ? (
                <div className="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center">
                    <MessageSquarePlus className="size-8 text-slate-300" />
                    <p className="mt-3 text-sm font-medium text-slate-600">
                        まだ依頼はありません
                    </p>
                    <p className="mt-1 text-xs text-slate-400">
                        毎回手作業でやっていることがあれば、そのまま書いて送ってください。
                    </p>
                </div>
            ) : (
                <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-2 font-semibold">
                                    タイトル
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    依頼者
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    状態
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    依頼日時
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    公開されたツール
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {shown.map((item) => (
                                <tr
                                    key={item.ulid}
                                    className="hover:bg-slate-50"
                                >
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
                                    <td className="px-4 py-2">
                                        <StatusPill
                                            value={item.status}
                                            label={item.statusLabel}
                                            styles={REQUEST_STATUS_STYLES}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-slate-500 tabular-nums">
                                        {formatDateTime(item.createdAt)}
                                    </td>
                                    <td className="px-4 py-2 text-slate-600">
                                        {item.tool?.name ?? '—'}
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
