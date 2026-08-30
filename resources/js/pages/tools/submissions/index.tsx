import { Head, Link, usePage } from '@inertiajs/react';
import { FilePen, Plus } from 'lucide-react';
import StatusPill from '@/components/status-pill';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { SUBMISSION_STATUS_STYLES } from '@/lib/tool-presets';
import { create, show } from '@/routes/tools/submissions';
import type { SubmissionSummary } from '@/types/tools';

export default function SubmissionsIndex({
    submissions,
}: {
    submissions: SubmissionSummary[];
}) {
    const { features } = usePage().props;

    return (
        <>
            <Head title="登録" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="text-xl font-bold text-slate-800">登録</h1>
                <p className="text-sm text-slate-500">
                    あなたが出したツールの登録・変更・非推奨化の申請と、その結果です。
                </p>
                {features.maySubmit && (
                    <Button
                        asChild
                        size="sm"
                        className="ml-auto bg-sky-700 text-white hover:bg-sky-800"
                    >
                        <Link href={create()}>
                            <Plus className="size-4" />
                            ツールを登録
                        </Link>
                    </Button>
                )}
            </div>

            {submissions.length === 0 ? (
                <div className="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center">
                    <FilePen className="size-8 text-slate-300" />
                    <p className="mt-3 text-sm font-medium text-slate-600">
                        まだ申請はありません
                    </p>
                    <p className="mt-1 text-xs text-slate-400">
                        部署のツールを登録すると、承認後に全社で使えるようになります。
                    </p>
                </div>
            ) : (
                <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-2 font-semibold">
                                    ツール
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    内容
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    状態
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    申請日時
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    承認者
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {submissions.map((submission) => (
                                <tr
                                    key={submission.ulid}
                                    className="transition hover:bg-slate-50"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(submission.ulid)}
                                            className="font-medium text-sky-700 hover:underline"
                                        >
                                            {submission.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {submission.actionLabel}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill
                                            value={submission.status}
                                            label={submission.statusLabel}
                                            styles={SUBMISSION_STATUS_STYLES}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-slate-500 tabular-nums">
                                        {formatDateTime(
                                            submission.submittedAt ??
                                                submission.createdAt,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {submission.reviewer ?? '—'}
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
