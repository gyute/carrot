import { Head, Link, usePoll } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import LiveUpdates from '@/components/live-updates';
import StatusPill from '@/components/status-pill';
import ToolIcon from '@/components/tool-icon';
import ToolsNav from '@/components/tools-nav';
import { formatTimestamp } from '@/lib/format';
import { toolAccent } from '@/lib/tool-presets';
import { show as showTool } from '@/routes/tools';
import type { ToolRunSummary } from '@/types/tools';

type Props = {
    tool: { ulid: string; name: string; icon: string; accent: string };
    run: ToolRunSummary;
};

export const RUN_STATUS_STYLES: Record<ToolRunSummary['status'], string> = {
    queued: 'bg-slate-100 text-slate-600 ring-slate-200',
    running: 'bg-sky-50 text-sky-700 ring-sky-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    failed: 'bg-rose-50 text-rose-700 ring-rose-200',
    timed_out: 'bg-amber-50 text-amber-700 ring-amber-200',
};

export function RunOutput({ run }: { run: ToolRunSummary }) {
    return (
        <div className="grid gap-4">
            {run.errorMessage && (
                <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {run.errorMessage}
                </div>
            )}

            <div>
                <p className="mb-1 text-xs font-semibold text-slate-500">
                    標準出力
                </p>
                <pre className="max-h-[32rem] min-h-16 overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-slate-100">
                    {run.stdout ??
                        (run.finished ? '' : '実行が終わると表示されます…')}
                </pre>
            </div>

            {run.stderr && (
                <div>
                    <p className="mb-1 text-xs font-semibold text-rose-600">
                        標準エラー
                    </p>
                    <pre className="max-h-64 overflow-auto rounded-lg bg-rose-950 p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap text-rose-100">
                        {run.stderr}
                    </pre>
                </div>
            )}

            {run.truncated && (
                <p className="text-xs text-amber-700">
                    出力が上限を超えたため途中で切り捨てました。
                </p>
            )}
        </div>
    );
}

export default function ToolRunShow({ tool, run }: Props) {
    usePoll(2000, { only: ['run'] }, { autoStart: !run.finished });

    return (
        <>
            <Head title={`${tool.name} の実行`} />

            <ToolsNav />

            <LiveUpdates only={['run']} pollMs={30_000} />

            <Link
                href={showTool(tool.ulid)}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                {tool.name} へ戻る
            </Link>

            <div className="mt-3 flex flex-wrap items-center gap-3">
                <span
                    className={`flex size-10 items-center justify-center rounded-xl bg-linear-to-br ${toolAccent(tool.accent)} text-white`}
                >
                    <ToolIcon name={tool.icon} className="size-5" />
                </span>
                <h1 className="text-xl font-bold text-slate-800">
                    {tool.name} の実行
                </h1>
                <StatusPill
                    value={run.status}
                    label={run.statusLabel}
                    styles={RUN_STATUS_STYLES}
                />
                {!run.finished && (
                    <Loader2 className="size-4 animate-spin text-sky-600" />
                )}
            </div>

            <dl className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                <div>実行者 {run.requestedBy}</div>
                <div>環境 {run.runtimeLabel}</div>
                <div className="tabular-nums">
                    開始 {formatTimestamp(run.startedAt ?? run.createdAt)}
                </div>
                {run.finished && (
                    <div className="tabular-nums">
                        所要 {run.durationMs ?? 0} ms · 終了コード{' '}
                        {run.exitCode ?? '—'}
                    </div>
                )}
                {Object.keys(run.inputs).length > 0 && (
                    <div>
                        入力{' '}
                        {Object.entries(run.inputs).map(([key, value]) => (
                            <code
                                key={key}
                                className="mr-1 rounded bg-slate-100 px-1 font-mono"
                            >
                                {key}={String(value)}
                            </code>
                        ))}
                    </div>
                )}
            </dl>

            <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <RunOutput run={run} />
            </div>
        </>
    );
}
