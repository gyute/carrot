import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { Download, KeyRound, RefreshCw } from 'lucide-react';
import InputError from '@/components/input-error';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    queued: 'bg-slate-100 text-slate-600',
    running: 'bg-sky-100 text-sky-700',
    completed: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-rose-100 text-rose-700',
};

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('ja-JP') : '—';
}

export default function ExportJobs({ jobs, unlockedJobs, issuedCode }: Props) {
    const running = jobs.some(
        (job) => job.status === 'queued' || job.status === 'running',
    );

    // Batches finish in the background, so keep the list fresh while any run.
    usePoll(3000, { only: ['jobs', 'unlockedJobs'] }, { autoStart: running });

    return (
        <>
            <Head title="バッチ一覧" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">バッチ一覧</h1>
                {running && (
                    <span className="inline-flex items-center gap-1 text-sm text-sky-700">
                        <RefreshCw className="size-3.5 animate-spin" />
                        実行中のバッチがあります
                    </span>
                )}
                <Link
                    href={createExport()}
                    className="ml-auto text-sm font-medium text-sky-700 underline decoration-sky-300 underline-offset-4"
                >
                    新しいエクスポート
                </Link>
            </div>

            {issuedCode && (
                <div className="mt-6 rounded-md border border-sky-200 bg-sky-50 px-5 py-4">
                    <p className="text-sm font-bold text-sky-900">
                        エクスポートを受け付けました。
                    </p>
                    <p className="mt-1 text-sm text-sky-800">
                        ダウンロードコード
                        <span className="mx-2 rounded-sm bg-white px-2 py-1 font-mono text-base font-bold tracking-widest text-sky-900">
                            {issuedCode}
                        </span>
                        完了後、このコードでダウンロードできます。
                    </p>
                </div>
            )}

            <Form
                {...lookup.form()}
                className="mt-6 rounded-md border border-slate-200 bg-white p-5 shadow-sm"
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <label
                            htmlFor="code"
                            className="text-sm font-bold text-slate-700"
                        >
                            ダウンロードコード
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
            <p className="mt-3 rounded-md border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-400">
                バッチはまだありません。
            </p>
        );
    }

    return (
        <div className="mt-3 overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-3xl text-left text-sm">
                <thead className="border-b border-slate-200 text-xs text-slate-500">
                    <tr>
                        <th className="px-4 py-3 font-medium">内容</th>
                        <th className="px-4 py-3 font-medium">状態</th>
                        <th className="px-4 py-3 font-medium">件数</th>
                        <th className="px-4 py-3 font-medium">コード</th>
                        <th className="px-4 py-3 font-medium">実行者</th>
                        <th className="px-4 py-3 font-medium">受付</th>
                        <th className="px-4 py-3 font-medium">保存期限</th>
                        <th className="px-4 py-3" />
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {jobs.map((job) => (
                        <tr key={job.ulid}>
                            <td className="px-4 py-3 font-medium text-slate-800">
                                {job.label}
                                {job.errorMessage && (
                                    <span className="mt-1 block text-xs font-normal text-rose-600">
                                        {job.errorMessage}
                                    </span>
                                )}
                            </td>
                            <td className="px-4 py-3">
                                <span
                                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-bold ${STATUS_STYLES[job.status]}`}
                                >
                                    {job.statusLabel}
                                </span>
                            </td>
                            <td className="px-4 py-3 text-slate-600">
                                {job.rowCount === null
                                    ? '—'
                                    : `${job.rowCount.toLocaleString('ja-JP')} 件`}
                            </td>
                            <td className="px-4 py-3 font-mono text-xs tracking-wider text-slate-500">
                                {job.downloadCode}
                            </td>
                            <td className="px-4 py-3 text-slate-600">
                                {job.requestedBy}
                            </td>
                            <td className="px-4 py-3 text-slate-500">
                                {formatDateTime(job.createdAt)}
                            </td>
                            <td className="px-4 py-3 text-slate-500">
                                {formatDateTime(job.expiresAt)}
                            </td>
                            <td className="px-4 py-3 text-right">
                                {job.downloadable && (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="secondary"
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
