import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Send, Undo2 } from 'lucide-react';
import StatusPill from '@/components/status-pill';
import SubmissionPayloadView from '@/components/submission-payload';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatTimestamp } from '@/lib/format';
import { SUBMISSION_STATUS_STYLES } from '@/lib/tool-presets';
import { show as showTool } from '@/routes/tools';
import { destroy, edit, index, submit } from '@/routes/tools/submissions';
import type { SubmissionDetail } from '@/types/tools';

type Props = {
    submission: SubmissionDetail;
    can: { update: boolean; withdraw: boolean };
};

export default function SubmissionShow({ submission, can }: Props) {
    const withdraw = () => {
        const message =
            submission.status === 'draft'
                ? 'この下書きを削除しますか？'
                : 'この申請を取り下げますか？';

        if (window.confirm(message)) {
            router.delete(destroy(submission.ulid).url);
        }
    };

    return (
        <>
            <Head title={`申請: ${submission.name}`} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                申請一覧へ
            </Link>

            <div className="mt-2 flex flex-wrap items-center gap-3">
                <h1 className="text-xl font-bold text-slate-800">
                    {submission.name}
                </h1>
                <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                    {submission.actionLabel}
                </span>
                <StatusPill
                    value={submission.status}
                    label={submission.statusLabel}
                    styles={SUBMISSION_STATUS_STYLES}
                />

                <div className="ml-auto flex items-center gap-2">
                    {can.update && (
                        <>
                            <Button asChild variant="outline" size="sm">
                                <Link href={edit(submission.ulid)}>
                                    <Pencil className="size-4" />
                                    編集
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                className="bg-sky-700 text-white hover:bg-sky-800"
                                onClick={() =>
                                    router.post(submit(submission.ulid).url)
                                }
                            >
                                <Send className="size-4" />
                                申請する
                            </Button>
                        </>
                    )}
                    {can.withdraw && (
                        <Button variant="ghost" size="sm" onClick={withdraw}>
                            <Undo2 className="size-4" />
                            {submission.status === 'draft'
                                ? '削除'
                                : '取り下げ'}
                        </Button>
                    )}
                </div>
            </div>

            <dl className="mt-4 grid gap-x-6 gap-y-1 text-xs text-slate-500 sm:grid-cols-[auto_auto_auto_auto]">
                <div>
                    申請者{' '}
                    <span className="font-medium text-slate-700">
                        {submission.requester}
                    </span>
                </div>
                <div>
                    申請日時{' '}
                    <span className="font-medium text-slate-700 tabular-nums">
                        {formatTimestamp(submission.submittedAt)}
                    </span>
                </div>
                {submission.endorser && (
                    <div>
                        部署承認{' '}
                        <span className="font-medium text-slate-700">
                            {submission.endorser}
                        </span>{' '}
                        <span className="tabular-nums">
                            ({formatTimestamp(submission.endorsedAt)})
                        </span>
                    </div>
                )}
                {submission.reviewer && (
                    <div>
                        システム承認{' '}
                        <span className="font-medium text-slate-700">
                            {submission.reviewer}
                        </span>{' '}
                        <span className="tabular-nums">
                            ({formatTimestamp(submission.reviewedAt)})
                        </span>
                    </div>
                )}
                {submission.tool && (
                    <div>
                        対象ツール{' '}
                        <Link
                            href={showTool(submission.tool.ulid)}
                            className="font-medium text-sky-700 hover:underline"
                        >
                            {submission.tool.name}
                        </Link>
                    </div>
                )}
            </dl>

            {submission.reviewComment && (
                <div
                    className={
                        submission.status === 'rejected'
                            ? 'mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800'
                            : 'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800'
                    }
                >
                    <p className="text-xs font-semibold">
                        {submission.reviewer} からのコメント
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">
                        {submission.reviewComment}
                    </p>
                </div>
            )}

            {submission.note && (
                <div className="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <p className="text-xs font-semibold text-slate-500">
                        申請メモ
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">
                        {submission.note}
                    </p>
                </div>
            )}

            {submission.action !== 'deprecate' && (
                <div className="mt-6">
                    <SubmissionPayloadView
                        payload={submission.payload}
                        current={submission.current}
                        runtimes={submission.runtimes}
                        behaviourOnly={submission.action === 'update'}
                    />
                </div>
            )}
        </>
    );
}
