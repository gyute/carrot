import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    Ban,
    Pencil,
    RotateCcw,
    Save,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import EmbedFrame from '@/components/embed-frame';
import InputError from '@/components/input-error';
import StatusPill from '@/components/status-pill';
import ToolIcon from '@/components/tool-icon';
import ToolRunForm from '@/components/tool-run-form';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, formatTimestamp } from '@/lib/format';
import {
    KIND_LABELS,
    NETWORK_LABELS,
    STATUS_STYLES,
    SUBMISSION_STATUS_STYLES,
    toolAccent,
} from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { RUN_STATUS_STYLES } from '@/pages/tools/runs/show';
import {
    deprecate as adminDeprecate,
    destroy as adminDestroy,
    restore as adminRestore,
} from '@/routes/admin/tools';
import { deprecate, index, update } from '@/routes/tools';
import { create as createChange } from '@/routes/tools/change';
import { show as showRun, store as storeRun } from '@/routes/tools/runs';
import { show as showSubmission } from '@/routes/tools/submissions';
import type {
    FormLimits,
    SubmissionSummary,
    ToolDetail,
    ToolRunSummary,
} from '@/types/tools';

type Props = {
    tool: ToolDetail;
    history: SubmissionSummary[];
    runs: ToolRunSummary[];
    openChange: SubmissionSummary | null;
    limits: FormLimits;
    can: {
        updateMetadata: boolean;
        submitChange: boolean;
        run: boolean;
        manage: boolean;
        delete: boolean;
    };
};

function MetadataForm({
    tool,
    limits,
    onDone,
}: {
    tool: ToolDetail;
    limits: FormLimits;
    onDone: () => void;
}) {
    const form = useForm({
        name: tool.name,
        summary: tool.summary,
        description: tool.description ?? '',
        icon: tool.icon,
        accent: tool.accent,
        department: tool.department ?? '',
        categories: tool.categories,
    });
    const { data, setData, errors, processing } = form;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update(tool.ulid).url, { onSuccess: onDone });
            }}
            className="mt-4 grid gap-4 rounded-xl border border-sky-200 bg-sky-50/40 p-5"
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="name">ツール名</Label>
                    <Input
                        id="name"
                        value={data.name}
                        maxLength={60}
                        onChange={(e) => setData('name', e.target.value)}
                        className="bg-white"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="summary">概要</Label>
                    <Input
                        id="summary"
                        value={data.summary}
                        maxLength={120}
                        onChange={(e) => setData('summary', e.target.value)}
                        className="bg-white"
                    />
                    <InputError message={errors.summary} />
                </div>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="description">説明</Label>
                <textarea
                    id="description"
                    rows={4}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-xs"
                />
                <InputError message={errors.description} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label>アイコン</Label>
                    <div className="flex flex-wrap gap-1.5">
                        {limits.icons.map((name) => (
                            <button
                                key={name}
                                type="button"
                                title={name}
                                aria-pressed={data.icon === name}
                                onClick={() => setData('icon', name)}
                                className={cn(
                                    'flex size-9 items-center justify-center rounded-lg border bg-white transition',
                                    data.icon === name
                                        ? 'border-sky-500 text-sky-700'
                                        : 'border-slate-200 text-slate-500',
                                )}
                            >
                                <ToolIcon name={name} className="size-4" />
                            </button>
                        ))}
                    </div>
                </div>
                <div className="grid gap-1.5">
                    <Label>カラー</Label>
                    <div className="flex flex-wrap gap-1.5">
                        {limits.accents.map((name) => (
                            <button
                                key={name}
                                type="button"
                                title={name}
                                aria-pressed={data.accent === name}
                                onClick={() => setData('accent', name)}
                                className={cn(
                                    `size-9 rounded-lg bg-linear-to-br ${toolAccent(name)} ring-offset-2`,
                                    data.accent === name &&
                                        'ring-2 ring-sky-500',
                                )}
                            />
                        ))}
                    </div>
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="department">所属</Label>
                    <select
                        id="department"
                        value={data.department}
                        onChange={(e) => setData('department', e.target.value)}
                        className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm shadow-xs"
                    >
                        <option value="">未設定</option>
                        {limits.departments.map((department) => (
                            <option key={department} value={department}>
                                {department}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.department} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="categories">カテゴリ（カンマ区切り）</Label>
                    <Input
                        id="categories"
                        defaultValue={data.categories.join(', ')}
                        onBlur={(e) =>
                            setData(
                                'categories',
                                e.target.value
                                    .split(/[,、]/)
                                    .map((v) => v.trim())
                                    .filter(Boolean)
                                    .slice(0, 5),
                            )
                        }
                        className="bg-white"
                    />
                    <InputError message={errors.categories} />
                </div>
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onDone}>
                    キャンセル
                </Button>
                <Button
                    type="submit"
                    disabled={processing}
                    className="bg-sky-700 text-white hover:bg-sky-800"
                >
                    <Save className="size-4" />
                    保存
                </Button>
            </div>
        </form>
    );
}

export default function ToolShow({
    tool,
    history,
    runs,
    openChange,
    limits,
    can,
}: Props) {
    const [editing, setEditing] = useState(false);
    const isDeprecated = tool.status === 'deprecated';
    const opensElsewhere = tool.kind === 'link' && tool.href;

    const requestDeprecation = () => {
        const note = window.prompt(
            '非推奨化を申請します。理由があれば入力してください。',
            '',
        );

        if (note !== null) {
            router.post(deprecate(tool.ulid).url, { note });
        }
    };

    const confirmThen = (message: string, run: () => void) => () => {
        if (window.confirm(message)) {
            run();
        }
    };

    return (
        <>
            <Head title={tool.name} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                ツール一覧へ
            </Link>

            <div className="mt-3 flex flex-wrap items-start gap-4">
                <span
                    className={`flex size-14 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br ${toolAccent(tool.accent)} text-white shadow-sm`}
                >
                    <ToolIcon name={tool.icon} className="size-7" />
                </span>

                <div className="min-w-0 grow">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-bold text-slate-800">
                            {tool.name}
                        </h1>
                        <StatusPill
                            value={tool.status}
                            styles={STATUS_STYLES}
                        />
                        <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                            {KIND_LABELS[tool.kind]}
                        </span>
                        {tool.pendingChange && (
                            <span className="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                変更申請中
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                        {tool.summary}
                    </p>
                    <dl className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                        <div>
                            バージョン{' '}
                            <span className="font-mono font-medium text-slate-700">
                                {tool.version ? `v${tool.version}` : '—'}
                            </span>
                        </div>
                        <div>
                            申請{' '}
                            <span className="font-medium text-slate-700">
                                {tool.requester ?? '—'}
                            </span>
                        </div>
                        <div>
                            部署承認{' '}
                            <span className="font-medium text-slate-700">
                                {tool.endorser ?? '—'}
                            </span>
                        </div>
                        <div>
                            システム承認{' '}
                            <span className="font-medium text-slate-700">
                                {tool.approver ?? '—'}
                            </span>
                        </div>
                        <div>
                            所有者{' '}
                            <span className="font-medium text-slate-700">
                                {tool.owner ?? '—'}
                            </span>
                        </div>
                        {tool.department && <div>所属 {tool.department}</div>}
                        <div className="tabular-nums">
                            公開 {formatDateTime(tool.publishedAt)}
                        </div>
                    </dl>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {opensElsewhere && !isDeprecated && (
                        <Button
                            asChild
                            className="bg-sky-700 text-white hover:bg-sky-800"
                        >
                            <Link href={tool.href as string}>
                                開く
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </Button>
                    )}
                    {can.updateMetadata && !editing && (
                        <Button
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil className="size-4" />
                            表示内容を編集
                        </Button>
                    )}
                </div>
            </div>

            {isDeprecated && (
                <div className="mt-4 rounded-lg border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-700">
                    このツールは {formatDateTime(tool.deprecatedAt)}{' '}
                    に非推奨になりました。新しい利用は推奨されません。
                </div>
            )}

            {editing && (
                <MetadataForm
                    tool={tool}
                    limits={limits}
                    onDone={() => setEditing(false)}
                />
            )}

            {tool.description && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">説明</h2>
                    <p className="mt-2 text-sm leading-relaxed whitespace-pre-wrap text-slate-700">
                        {tool.description}
                    </p>
                </section>
            )}

            {tool.categories.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {tool.categories.map((category) => (
                        <span
                            key={category}
                            className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                        >
                            {category}
                        </span>
                    ))}
                </div>
            )}

            {tool.kind === 'embed' && !isDeprecated && (
                <div className="mt-6">
                    {tool.embedUrl ? (
                        <EmbedFrame url={tool.embedUrl} title={tool.name} />
                    ) : (
                        <p className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            この URL は埋め込めません。外部の https
                            ページのみ表示できます。
                        </p>
                    )}
                </div>
            )}

            {tool.kind === 'script' && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-slate-700">
                        <Wrench className="size-4" />
                        実行
                        <span className="text-xs font-normal text-slate-500">
                            {limits.runtimes[tool.config.runtime ?? 'php'] ??
                                tool.config.runtime}{' '}
                            · ネットワーク{' '}
                            {NETWORK_LABELS[tool.config.network ?? 'none']} ·
                            タイムアウト {tool.config.timeout_sec}s · メモリ{' '}
                            {tool.config.memory_mb}MB
                        </span>
                    </h2>

                    <div className="mt-4">
                        {can.run ? (
                            <ToolRunForm
                                inputs={tool.config.inputs ?? []}
                                action={storeRun(tool.ulid).url}
                            />
                        ) : (
                            <p className="text-sm text-slate-500">
                                非推奨のツールは実行できません。
                            </p>
                        )}
                    </div>

                    {runs.length > 0 && (
                        <div className="mt-6">
                            <h3 className="text-xs font-semibold text-slate-500">
                                最近の実行
                            </h3>
                            <ul className="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                                {runs.map((run) => (
                                    <li key={run.ulid}>
                                        <Link
                                            href={showRun([
                                                tool.ulid,
                                                run.ulid,
                                            ])}
                                            className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 transition hover:bg-slate-50"
                                        >
                                            <StatusPill
                                                value={run.status}
                                                label={run.statusLabel}
                                                styles={RUN_STATUS_STYLES}
                                            />
                                            <span className="text-slate-500 tabular-nums">
                                                {formatTimestamp(run.createdAt)}
                                            </span>
                                            <span className="text-xs text-slate-500">
                                                {run.requestedBy}
                                            </span>
                                            {run.durationMs !== null && (
                                                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                                                    {run.durationMs} ms
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>
            )}

            {(can.submitChange || can.manage) && (
                <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">管理</h2>

                    {openChange && (
                        <p className="mt-2 text-sm text-slate-600">
                            あなたの
                            <Link
                                href={showSubmission(openChange.ulid)}
                                className="mx-1 font-medium text-sky-700 hover:underline"
                            >
                                {openChange.actionLabel}申請
                            </Link>
                            が
                            <StatusPill
                                value={openChange.status}
                                label={openChange.statusLabel}
                                styles={SUBMISSION_STATUS_STYLES}
                                className="mx-1"
                            />
                            です。
                        </p>
                    )}

                    <div className="mt-3 flex flex-wrap gap-2">
                        {can.submitChange && !openChange && (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={createChange(tool.ulid)}>
                                        <Pencil className="size-4" />
                                        動作の変更を申請
                                    </Link>
                                </Button>
                                {!isDeprecated && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={requestDeprecation}
                                    >
                                        <Ban className="size-4" />
                                        非推奨化を申請
                                    </Button>
                                )}
                            </>
                        )}
                        {can.manage && (
                            <>
                                {isDeprecated ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={confirmThen(
                                            '稼働中に戻しますか？',
                                            () =>
                                                router.post(
                                                    adminRestore(tool.ulid).url,
                                                ),
                                        )}
                                    >
                                        <RotateCcw className="size-4" />
                                        稼働中に戻す（管理者）
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={confirmThen(
                                            '申請なしで直ちに非推奨にします。よろしいですか？',
                                            () =>
                                                router.post(
                                                    adminDeprecate(tool.ulid)
                                                        .url,
                                                ),
                                        )}
                                    >
                                        <Ban className="size-4" />
                                        非推奨にする（管理者）
                                    </Button>
                                )}
                            </>
                        )}
                        {can.delete && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                onClick={confirmThen(
                                    `「${tool.name}」を削除します。カタログから消え、元に戻せません。`,
                                    () =>
                                        router.delete(
                                            adminDestroy(tool.ulid).url,
                                        ),
                                )}
                            >
                                <Trash2 className="size-4" />
                                削除（管理者）
                            </Button>
                        )}
                    </div>
                </section>
            )}

            <section className="mt-6">
                <h2 className="text-sm font-bold text-slate-700">履歴</h2>
                {history.length === 0 ? (
                    <p className="mt-2 text-xs text-slate-400">
                        承認済みの申請はありません。
                    </p>
                ) : (
                    <ol className="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm">
                        {history.map((entry) => (
                            <li
                                key={entry.ulid}
                                className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3"
                            >
                                <span className="text-slate-500 tabular-nums">
                                    {formatTimestamp(entry.reviewedAt)}
                                </span>
                                <Link
                                    href={showSubmission(entry.ulid)}
                                    className="font-medium text-sky-700 hover:underline"
                                >
                                    {entry.actionLabel}
                                </Link>
                                <span className="text-xs text-slate-500">
                                    申請 {entry.requester} · 承認{' '}
                                    {entry.reviewer ?? '—'}
                                </span>
                                {entry.reviewComment && (
                                    <span className="text-xs text-slate-400">
                                        「{entry.reviewComment}」
                                    </span>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </>
    );
}
