import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { Check, Copy, Download, KeyRound, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { create as createExport } from '@/routes/tools/exports';
import { download, lookup } from '@/routes/tools/exports/jobs';

type ExportJob = {
    ulid: string;
    label: string;
    status: 'queued' | 'running' | 'completed' | 'failed';
    statusLabel: string;
    rowCount: number | null;
    downloadCode: string;
    requestedBy: string;
    createdAt: string;
    completedAt: string | null;
    expiresAt: string | null;
    errorMessage: string | null;
    downloadable: boolean;
};

type Props = {
    jobs: ExportJob[];
    unlockedJobs: ExportJob[];
    issuedCode: string | null;
};

const STATUS_STYLES: Record<ExportJob['status'], string> = {
    queued: 'bg-slate-100 text-slate-600 ring-slate-200',
    running: 'bg-sky-50 text-sky-700 ring-sky-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    failed: 'bg-rose-50 text-rose-700 ring-rose-200',
};

const DOT_STYLES: Record<ExportJob['status'], string> = {
    queued: 'bg-slate-400',
    running: 'bg-sky-500 animate-pulse',
    completed: 'bg-emerald-500',
    failed: 'bg-rose-500',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ja-JP', {
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function CodeChip({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            toast.success('ダウンロードコードをコピーしました');
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            toast.error('コピーできませんでした');
        }
    };

    return (
        <button
            type="button"
            onClick={copy}
            title="コピー"
            className="inline-flex items-center gap-1.5 rounded-md bg-slate-50 px-2 py-1 font-mono text-xs tracking-wider text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900"
        >
            {code}
            {copied ? (
                <Check className="size-3 text-emerald-600" />
            ) : (
                <Copy className="size-3 text-slate-400" />
            )}
        </button>
    );
}

function StatCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <p className="text-xs text-slate-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-slate-800 tabular-nums">
                {value}
            </p>
        </div>
    );
}

export default function ExportJobs({ jobs, unlockedJobs, issuedCode }: Props) {
    const running = jobs.filter(
        (job) => job.status === 'queued' || job.status === 'running',
    ).length;

    // Batches finish in the background, so keep the list fresh while any run.
    usePoll(
        3000,
        { only: ['jobs', 'unlockedJobs'] },
        { autoStart: running > 0 },
    );

    return (
        <>
            <Head title="バッチ一覧" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="text-xl font-bold text-slate-800">バッチ一覧</h1>
                {running > 0 && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 ring-1 ring-sky-200">
                        <Loader2 className="size-3 animate-spin" />
                        {running} 件 実行中
                    </span>
                )}
                <Button
                    asChild
                    size="sm"
                    className="ml-auto bg-sky-700 text-white hover:bg-sky-800"
                >
                    <Link href={createExport()}>
                        <Plus className="size-4" />
                        新しいエクスポート
                    </Link>
                </Button>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <StatCard label="自分のバッチ" value={jobs.length} />
                <StatCard label="実行中" value={running} />
                <StatCard
                    label="ダウンロード可能"
                    value={jobs.filter((job) => job.downloadable).length}
                />
            </div>

            {issuedCode && (
                <div className="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-sky-200 bg-linear-to-r from-sky-50 to-white px-5 py-4">
                    <div>
                        <p className="text-sm font-bold text-sky-900">
                            エクスポートを受け付けました。
                        </p>
                        <p className="mt-0.5 text-xs text-sky-800">
                            完了後、このコードでダウンロードできます。
                        </p>
                    </div>
                    <span className="ml-auto">
                        <CodeChip code={issuedCode} />
                    </span>
                </div>
            )}

            <Form
                {...lookup.form()}
                className="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <label
                            htmlFor="code"
                            className="text-sm font-bold text-slate-700"
                        >
                            ダウンロードコードで照会
                        </label>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <Input
                                id="code"
                                name="code"
                                placeholder="ABCD123456"
                                autoComplete="off"
                                maxLength={12}
                                className="max-w-xs font-mono tracking-widest uppercase"
                            />
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="lookup-button"
                            >
                                <KeyRound className="size-4" />
                                照会
                            </Button>
                        </div>
                        <InputError message={errors.code} className="mt-2" />
                        <p className="mt-2 text-xs text-slate-400">
                            自分が実行したバッチは下に表示されます。ほかの人が実行したバッチは、発行されたコードでのみ取得できます。
                        </p>
                    </>
                )}
            </Form>

            {unlockedJobs.length > 0 && (
                <section className="mt-8">
                    <h2 className="text-sm font-bold text-slate-700">
                        コードで照会したバッチ
                    </h2>
                    <JobTable jobs={unlockedJobs} />
                </section>
            )}

            <section className="mt-8">
                <h2 className="text-sm font-bold text-slate-700">
                    自分のバッチ
                </h2>
                <JobTable jobs={jobs} />
            </section>
        </>
    );
}

function JobTable({ jobs }: { jobs: ExportJob[] }) {
    if (jobs.length === 0) {
        return (
            <div className="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                <p className="text-sm text-slate-500">
                    バッチはまだありません。
                </p>
                <Button asChild variant="secondary" size="sm" className="mt-4">
                    <Link href={createExport()}>
                        <Plus className="size-4" />
                        エクスポートを実行
                    </Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-3xl text-left text-sm">
                <thead className="bg-slate-50/80 text-[11px] tracking-wider text-slate-500 uppercase">
                    <tr>
                        <th className="px-5 py-3 font-medium">内容</th>
                        <th className="px-5 py-3 font-medium">状態</th>
                        <th className="px-5 py-3 text-right font-medium">
                            件数
                        </th>
                        <th className="px-5 py-3 font-medium">コード</th>
                        <th className="px-5 py-3 font-medium">実行者</th>
                        <th className="px-5 py-3 font-medium">受付</th>
                        <th className="px-5 py-3 font-medium">保存期限</th>
                        <th className="px-5 py-3" />
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {jobs.map((job) => (
                        <tr
                            key={job.ulid}
                            className="transition hover:bg-slate-50/70"
                        >
                            <td className="px-5 py-4 font-medium text-slate-800">
                                {job.label}
                                {job.errorMessage && (
                                    <span className="mt-1 block max-w-xs truncate text-xs font-normal text-rose-600">
                                        {job.errorMessage}
                                    </span>
                                )}
                            </td>
                            <td className="px-5 py-4">
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                                        STATUS_STYLES[job.status],
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            DOT_STYLES[job.status],
                                        )}
                                    />
                                    {job.statusLabel}
                                </span>
                            </td>
                            <td className="px-5 py-4 text-right text-slate-600 tabular-nums">
                                {job.rowCount === null
                                    ? '—'
                                    : job.rowCount.toLocaleString('ja-JP')}
                            </td>
                            <td className="px-5 py-4">
                                <CodeChip code={job.downloadCode} />
                            </td>
                            <td className="px-5 py-4 text-slate-600">
                                {job.requestedBy}
                            </td>
                            <td className="px-5 py-4 text-slate-500 tabular-nums">
                                {formatDateTime(job.createdAt)}
                            </td>
                            <td className="px-5 py-4 text-slate-500 tabular-nums">
                                {formatDateTime(job.expiresAt)}
                            </td>
                            <td className="px-5 py-4 text-right">
                                {job.downloadable && (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="bg-sky-700 text-white hover:bg-sky-800"
                                    >
                                        <a href={download(job.ulid).url}>
                                            <Download className="size-4" />
                                            ダウンロード
                                        </a>
                                    </Button>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
