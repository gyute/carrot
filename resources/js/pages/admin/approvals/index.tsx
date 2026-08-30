import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import StatusPill from '@/components/status-pill';
import ToolsNav from '@/components/tools-nav';
import { formatTimestamp } from '@/lib/format';
import { KIND_LABELS, SUBMISSION_STATUS_STYLES } from '@/lib/tool-presets';
import { show } from '@/routes/admin/approvals';
import type { SubmissionSummary } from '@/types/tools';

type Props = {
    pending: SubmissionSummary[];
    decided: SubmissionSummary[];
    stage: 'manager' | 'admin';
};

function Table({
    rows,
    emptyText,
}: {
    rows: SubmissionSummary[];
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
                        <th className="px-4 py-2 font-semibold">ツール</th>
                        <th className="px-4 py-2 font-semibold">内容</th>
                        <th className="px-4 py-2 font-semibold">
                            申請者 / 所属
                        </th>
                        <th className="px-4 py-2 font-semibold">申請日時</th>
                        <th className="px-4 py-2 font-semibold">状態</th>
                        <th className="px-4 py-2 font-semibold">承認者</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {rows.map((row) => (
                        <tr
                            key={row.ulid}
                            className="transition hover:bg-slate-50"
                        >
                            <td className="px-4 py-3">
                                <Link
                                    href={show(row.ulid)}
                                    className="font-medium text-sky-700 hover:underline"
                                >
                                    {row.name}
                                </Link>
                                {row.kind && (
                                    <span className="ml-2 text-xs text-slate-400">
                                        {KIND_LABELS[row.kind]}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-slate-600">
                                {row.actionLabel}
                            </td>
                            <td className="px-4 py-3 text-slate-600">
                                {row.requester}
                                {row.department && (
                                    <span className="ml-1 text-xs text-slate-400">
                                        {row.department}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3 text-slate-500 tabular-nums">
                                {formatTimestamp(row.submittedAt)}
                            </td>
                            <td className="px-4 py-3">
                                <StatusPill
                                    value={row.status}
                                    label={row.statusLabel}
                                    styles={SUBMISSION_STATUS_STYLES}
                                />
                            </td>
                            <td className="px-4 py-3 text-slate-500">
                                {row.reviewer ?? '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ApprovalsIndex({ pending, decided, stage }: Props) {
    return (
        <>
            <Head title="承認" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <ClipboardCheck className="size-5 text-slate-400" />
                    承認
                </h1>
                <p className="text-sm text-slate-500">
                    {stage === 'admin'
                        ? '部署で承認された申請をシステム管理者として確認し、公開を決めます。第一段階の申請も直接承認できます。'
                        : 'あなたの部署からのツール申請を確認して、承認（システム管理者へ回付）または差し戻します。'}
                </p>
            </div>

            <h2 className="mt-6 text-sm font-bold text-slate-700">
                承認待ち{' '}
                <span className="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 tabular-nums">
                    {pending.length}
                </span>
            </h2>
            <Table rows={pending} emptyText="承認待ちの申請はありません。" />

            <h2 className="mt-8 text-sm font-bold text-slate-700">処理済み</h2>
            <Table rows={decided} emptyText="まだ処理した申請はありません。" />
        </>
    );
}
