import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Undo2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import StatusPill from '@/components/status-pill';
import SubmissionPayloadView from '@/components/submission-payload';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { SUBMISSION_STATUS_STYLES } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { approve, index, reject } from '@/routes/admin/approvals';
import { show as showTool } from '@/routes/tools';
import type { SubmissionDetail } from '@/types/tools';

type Props = {
    submission: SubmissionDetail;
    can: { review: boolean; finalize: boolean };
};

export default function ApprovalShow({ submission, can }: Props) {
    const form = useForm({ comment: '' });
    const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);

    const decide = (action: 'approve' | 'reject') => {
        setDecision(action);
        form.post(
            (action === 'approve' ? approve : reject)(submission.ulid).url,
            { onFinish: () => setDecision(null) },
        );
    };

    return (
        <>
            <Head title={`承認: ${submission.name}`} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                承認一覧へ
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
            </div>

            <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500">
                <div>
                    申請者{' '}
                    <span className="font-medium text-slate-700">
                        {submission.requester}
                    </span>
                </div>
                <div className="tabular-nums">
                    申請日時 {formatDateTime(submission.submittedAt)}
                </div>
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
                {submission.reviewer && (
                    <div>
                        処理{' '}
                        <span className="font-medium text-slate-700">
                            {submission.reviewer}
                        </span>{' '}
                        <span className="tabular-nums">
                            ({formatDateTime(submission.reviewedAt)})
                        </span>
                    </div>
                )}
            </dl>

            {submission.note && (
                <div className="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <p className="text-xs font-semibold text-slate-500">
                        申請者からのメモ
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">
                        {submission.note}
                    </p>
                </div>
            )}

            {submission.endorseComment && (
                <div className="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <p className="text-xs font-semibold">
                        部署管理者 {submission.endorser} のコメント
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">
                        {submission.endorseComment}
                    </p>
                </div>
            )}

            <div className="mt-6">
                {submission.action === 'deprecate' ? (
                    <p className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-600">
                        承認すると{' '}
                        <span className="font-medium">
                            {submission.tool?.name}
                        </span>{' '}
                        は非推奨になり、カタログの既定表示から外れます。
                    </p>
                ) : (
                    <SubmissionPayloadView
                        payload={submission.payload}
                        current={submission.current}
                        runtimes={submission.runtimes}
                        behaviourOnly={submission.action === 'update'}
                    />
                )}
            </div>

            {can.review ? (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">
                        判定
                        <span className="ml-2 text-xs font-normal text-slate-500">
                            {can.finalize
                                ? 'システム管理者として承認すると公開されます。'
                                : '部署として承認すると、システム管理者の確認に進みます。'}
                        </span>
                    </h2>
                    <textarea
                        rows={3}
                        value={form.data.comment}
                        onChange={(e) =>
                            form.setData('comment', e.target.value)
                        }
                        placeholder="コメント（差し戻す場合は必須）"
                        className={cn(
                            'mt-3 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none',
                            form.errors.comment
                                ? 'border-rose-400'
                                : 'border-slate-200',
                        )}
                    />
                    <InputError message={form.errors.comment} />
                    <div className="mt-3 flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => decide('reject')}
                            className="border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                        >
                            <Undo2 className="size-4" />
                            {decision === 'reject' ? '処理中…' : '差し戻す'}
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() => decide('approve')}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            <Check className="size-4" />
                            {decision === 'approve'
                                ? '処理中…'
                                : can.finalize
                                  ? '承認して公開'
                                  : '部署として承認'}
                        </Button>
                    </div>
                </section>
            ) : (
                submission.reviewComment && (
                    <div className="mt-6 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                        <p className="text-xs font-semibold text-slate-500">
                            コメント
                        </p>
                        <p className="mt-1 whitespace-pre-wrap">
                            {submission.reviewComment}
                        </p>
                    </div>
                )
            )}
        </>
    );
}
