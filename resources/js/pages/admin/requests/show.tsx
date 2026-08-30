import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Ban, Check, Copy, PackageCheck, Play } from 'lucide-react';
import { useState } from 'react';
import AdminNav from '@/components/admin-nav';
import StatusPill from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, formatTimestamp } from '@/lib/format';
import {
    REQUEST_PRIORITY_STYLES,
    REQUEST_STATUS_STYLES,
} from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import {
    accept,
    decline,
    deliver,
    duplicate,
    index,
    start,
} from '@/routes/admin/requests';
import { show as showTool } from '@/routes/tools';
import { show as showRequest } from '@/routes/tools/requests';
import { create as createSubmission } from '@/routes/tools/submissions';
import type { ToolRequestDetail } from '@/types/tools';

type Props = {
    toolRequest: ToolRequestDetail;
    can: { triage: boolean; deliver: boolean };
    candidates: { ulid: string; name: string }[];
    assignees: { ulid: string; name: string }[];
};

type Action = 'accept' | 'start' | 'decline' | 'duplicate' | 'deliver';

export default function AdminRequestShow({
    toolRequest,
    can,
    candidates,
    assignees,
}: Props) {
    const form = useForm({
        comment: '',
        priority: toolRequest.priority ?? 'normal',
        assignee: '',
        duplicate_of: '',
        tool: '',
    });
    const [pending, setPending] = useState<Action | null>(null);

    const routes = { accept, start, decline, duplicate, deliver };

    const decide = (action: Action) => {
        setPending(action);
        form.post(routes[action](toolRequest.ulid).url, {
            onFinish: () => setPending(null),
        });
    };

    const busy = (action: Action) => (pending === action ? '処理中…' : null);

    return (
        <>
            <Head title={`リクエスト: ${toolRequest.title}`} />

            <AdminNav />

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
                <Link
                    href={showRequest(toolRequest.ulid)}
                    className="ml-auto text-xs font-medium text-slate-500 hover:text-slate-800"
                >
                    依頼者に見えている画面
                </Link>
            </div>

            <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500">
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
                <div className="tabular-nums">
                    依頼日時 {formatTimestamp(toolRequest.createdAt)}
                </div>
                {toolRequest.neededBy && (
                    <div className="tabular-nums">
                        希望時期 {toolRequest.neededBy}
                    </div>
                )}
                {toolRequest.desiredKindLabel && (
                    <div>希望する形式 {toolRequest.desiredKindLabel}</div>
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
                        最終対応{' '}
                        <span className="font-medium text-slate-700">
                            {toolRequest.decider}
                        </span>{' '}
                        <span className="tabular-nums">
                            ({formatDateTime(toolRequest.decidedAt)})
                        </span>
                    </div>
                )}
            </dl>

            <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-bold text-slate-700">内容</h2>
                <p className="mt-2 text-sm whitespace-pre-wrap text-slate-700">
                    {toolRequest.body}
                </p>
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
            </section>

            {toolRequest.tool && (
                <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p className="text-xs font-semibold">公開したツール</p>
                    <Link
                        href={showTool(toolRequest.tool.ulid)}
                        className="mt-1 inline-block font-medium hover:underline"
                    >
                        {toolRequest.tool.name}
                    </Link>
                </div>
            )}

            {can.triage && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">
                        対応
                        <span className="ml-2 text-xs font-normal text-slate-500">
                            判断はここだけで完結します。承認の二段階はありません。
                        </span>
                    </h2>

                    <textarea
                        rows={3}
                        value={form.data.comment}
                        onChange={(e) =>
                            form.setData('comment', e.target.value)
                        }
                        placeholder="依頼者に伝えるコメント（見送る場合は必須）"
                        className={cn(
                            'mt-3 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none',
                            form.errors.comment
                                ? 'border-rose-400'
                                : 'border-slate-200',
                        )}
                    />
                    {form.errors.comment && (
                        <p className="mt-1 text-xs text-rose-600">
                            {form.errors.comment}
                        </p>
                    )}

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="priority">優先度</Label>
                            <select
                                id="priority"
                                value={form.data.priority}
                                onChange={(e) =>
                                    form.setData(
                                        'priority',
                                        e.target
                                            .value as typeof form.data.priority,
                                    )
                                }
                                className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="low">低</option>
                                <option value="normal">中</option>
                                <option value="high">高</option>
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="assignee">担当者</Label>
                            <select
                                id="assignee"
                                value={form.data.assignee}
                                onChange={(e) =>
                                    form.setData('assignee', e.target.value)
                                }
                                className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="">未割り当てのまま</option>
                                {assignees.map((user) => (
                                    <option key={user.ulid} value={user.ulid}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.assignee && (
                                <p className="text-xs text-rose-600">
                                    {form.errors.assignee}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => decide('decline')}
                            className="border-rose-200 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                        >
                            <Ban className="size-4" />
                            {busy('decline') ?? '見送る'}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => decide('start')}
                        >
                            <Play className="size-4" />
                            {busy('start') ?? '対応中にする'}
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() => decide('accept')}
                            className="bg-sky-700 text-white hover:bg-sky-800"
                        >
                            <Check className="size-4" />
                            {busy('accept') ?? '対応予定にする'}
                        </Button>
                    </div>

                    <div className="mt-6 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="duplicate_of">
                                重複としてまとめる
                            </Label>
                            <Input
                                id="duplicate_of"
                                value={form.data.duplicate_of}
                                placeholder="まとめ先リクエストのULID"
                                onChange={(e) =>
                                    form.setData('duplicate_of', e.target.value)
                                }
                                className={cn(
                                    'bg-white',
                                    form.errors.duplicate_of &&
                                        'border-rose-400',
                                )}
                            />
                            {form.errors.duplicate_of && (
                                <p className="text-xs text-rose-600">
                                    {form.errors.duplicate_of}
                                </p>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={
                                    form.processing || !form.data.duplicate_of
                                }
                                onClick={() => decide('duplicate')}
                            >
                                <Copy className="size-4" />
                                {busy('duplicate') ?? 'まとめる'}
                            </Button>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="tool">
                                公開したツールを紐づける
                            </Label>
                            <select
                                id="tool"
                                value={form.data.tool}
                                onChange={(e) =>
                                    form.setData('tool', e.target.value)
                                }
                                disabled={!can.deliver}
                                className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-xs disabled:bg-slate-50"
                            >
                                <option value="">選択してください</option>
                                {candidates.map((tool) => (
                                    <option key={tool.ulid} value={tool.ulid}>
                                        {tool.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.tool && (
                                <p className="text-xs text-rose-600">
                                    {form.errors.tool}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        form.processing || !form.data.tool
                                    }
                                    onClick={() => decide('deliver')}
                                >
                                    <PackageCheck className="size-4" />
                                    {busy('deliver') ?? '公開済みにする'}
                                </Button>
                                {can.deliver && (
                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={
                                                createSubmission({
                                                    query: {
                                                        request:
                                                            toolRequest.ulid,
                                                    },
                                                }).url
                                            }
                                        >
                                            このリクエストからツールを登録
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            )}
        </>
    );
}
