import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Pencil, Undo2 } from 'lucide-react';
import StatusPill from '@/components/status-pill';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatTimestamp } from '@/lib/format';
import {
    REQUEST_PRIORITY_STYLES,
    REQUEST_STATUS_STYLES,
} from '@/lib/tool-presets';
import { show as adminShow } from '@/routes/admin/requests';
import { show as showTool } from '@/routes/tools';
import { destroy, edit, index, show } from '@/routes/tools/requests';
import type { ToolRequestDetail } from '@/types/tools';

type Props = {
    toolRequest: ToolRequestDetail;
    can: { update: boolean; withdraw: boolean; triage: boolean };
};

export default function RequestShow({ toolRequest, can }: Props) {
    const withdraw = () => {
        if (window.confirm('このリクエストを取り下げますか？')) {
            router.delete(destroy(toolRequest.ulid).url);
        }
    };

    return (
        <>
            <Head title={`リクエスト: ${toolRequest.title}`} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                リクエスト一覧へ
            </Link>

            <div className="mt-2 flex flex-wrap items-center gap-3">
                <h1 className="text-xl font-bold text-slate-800">
                    {toolRequest.title}
                </h1>
                <StatusPill
                    value={toolRequest.status}
                    label={toolRequest.statusLabel}
                    styles={REQUEST_STATUS_STYLES}
                />
                {toolRequest.priority && (
                    <StatusPill
                        value={toolRequest.priority}
                        label={`優先度 ${toolRequest.priorityLabel}`}
                        styles={REQUEST_PRIORITY_STYLES}
                    />
                )}

                <div className="ml-auto flex items-center gap-2">
                    {can.triage && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={adminShow(toolRequest.ulid)}>
                                <ClipboardCheck className="size-4" />
                                開発チームとして対応
                            </Link>
                        </Button>
                    )}
                    {can.update && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(toolRequest.ulid)}>
                                <Pencil className="size-4" />
                                編集
                            </Link>
                        </Button>
                    )}
                    {can.withdraw && (
                        <Button variant="ghost" size="sm" onClick={withdraw}>
                            <Undo2 className="size-4" />
                            取り下げ
                        </Button>
                    )}
                </div>
            </div>

            <dl className="mt-4 grid gap-x-6 gap-y-1 text-xs text-slate-500 sm:grid-cols-[auto_auto_auto_auto]">
                <div>
                    依頼者{' '}
                    <span className="font-medium text-slate-700">
                        {toolRequest.requester}
                    </span>
                </div>
                <div>
                    所属{' '}
                    <span className="font-medium text-slate-700">
                        {toolRequest.department ?? '—'}
                    </span>
                </div>
                <div>
                    依頼日時{' '}
                    <span className="font-medium text-slate-700 tabular-nums">
                        {formatTimestamp(toolRequest.createdAt)}
                    </span>
                </div>
                {toolRequest.neededBy && (
                    <div>
                        希望時期{' '}
                        <span className="font-medium text-slate-700 tabular-nums">
                            {toolRequest.neededBy}
                        </span>
                    </div>
                )}
                {toolRequest.desiredKindLabel && (
                    <div>
                        希望する形式{' '}
                        <span className="font-medium text-slate-700">
                            {toolRequest.desiredKindLabel}
                        </span>
                    </div>
                )}
                {toolRequest.assignee && (
                    <div>
                        担当{' '}
                        <span className="font-medium text-slate-700">
                            {toolRequest.assignee}
                        </span>
                    </div>
                )}
                {toolRequest.decider && (
                    <div>
                        対応{' '}
                        <span className="font-medium text-slate-700">
                            {toolRequest.decider}
                        </span>{' '}
                        <span className="tabular-nums">
                            ({formatDateTime(toolRequest.decidedAt)})
                        </span>
                    </div>
                )}
            </dl>

            {toolRequest.categories.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {toolRequest.categories.map((category) => (
                        <span
                            key={category}
                            className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                        >
                            {category}
                        </span>
                    ))}
                </div>
            )}

            {toolRequest.tool && (
                <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p className="text-xs font-semibold">
                        このリクエストに対して公開されたツール
                    </p>
                    <Link
                        href={showTool(toolRequest.tool.ulid)}
                        className="mt-1 inline-block font-medium hover:underline"
                    >
                        {toolRequest.tool.name}
                    </Link>
                </div>
            )}

            {toolRequest.duplicateOf && (
                <div className="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <p className="text-xs font-semibold text-slate-500">
                        重複としてまとめられました
                    </p>
                    <Link
                        href={show(toolRequest.duplicateOf.ulid)}
                        className="mt-1 inline-block font-medium text-sky-700 hover:underline"
                    >
                        {toolRequest.duplicateOf.title}
                    </Link>
                </div>
            )}

            {toolRequest.decisionComment && (
                <div className="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <p className="text-xs font-semibold">
                        開発チーム {toolRequest.decider} からのコメント
                    </p>
                    <p className="mt-1 whitespace-pre-wrap">
                        {toolRequest.decisionComment}
                    </p>
                </div>
            )}

            <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-bold text-slate-700">内容</h2>
                <p className="mt-2 text-sm whitespace-pre-wrap text-slate-700">
                    {toolRequest.body}
                </p>
            </section>
        </>
    );
}
