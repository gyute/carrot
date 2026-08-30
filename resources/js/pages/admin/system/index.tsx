import { Head, Link, usePoll } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    CircleOff,
    Loader2,
} from 'lucide-react';
import AdminNav from '@/components/admin-nav';
import StatusPill from '@/components/status-pill';
import { formatTimestamp } from '@/lib/format';
import { cn } from '@/lib/utils';
import { RUN_STATUS_STYLES } from '@/pages/tools/runs/show';
import { show as showRun } from '@/routes/tools/runs';

type Queue = {
    name: string;
    pending: number;
    reserved: number;
    oldestPendingSeconds: number | null;
    heartbeatAt: string | null;
    alive: boolean;
};

type Status = {
    queues: Queue[];
    failedJobs: {
        count: number;
        recent: {
            id: number;
            queue: string;
            job: string;
            failedAt: string;
            exception: string;
        }[];
    };
    sandbox: {
        driver: string;
        ready: boolean;
        message: string | null;
        runtimes: Record<string, string>;
        requireRootless: boolean;
    };
    reverb: {
        connection: string;
        host: string | null;
        port: number | null;
        up: boolean | null;
    };
    runs: {
        running: number;
        last24h: Record<string, number>;
        recent: {
            ulid: string;
            tool: string | null;
            toolUlid: string | null;
            status: keyof typeof RUN_STATUS_STYLES;
            statusLabel: string;
            user: string;
            durationMs: number | null;
            createdAt: string;
        }[];
    };
    log: { path: string | null; lines: string[] };
    checkedAt: string;
};

function Light({ ok, label }: { ok: boolean | null; label: string }) {
    const Icon = ok === null ? CircleOff : ok ? CheckCircle2 : AlertTriangle;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1',
                ok === null
                    ? 'bg-slate-100 text-slate-500 ring-slate-200'
                    : ok
                      ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                      : 'bg-rose-50 text-rose-700 ring-rose-200',
            )}
        >
            <Icon className="size-3.5" />
            {label}
        </span>
    );
}

function Panel({
    title,
    right,
    children,
}: {
    title: string;
    right?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
            <header className="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
                <h2 className="text-sm font-bold text-slate-700">{title}</h2>
                <div className="ml-auto flex items-center gap-2">{right}</div>
            </header>
            <div className="px-5 py-4">{children}</div>
        </section>
    );
}

const LOG_LEVEL_STYLES: [RegExp, string][] = [
    [/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/, 'text-rose-300'],
    [/\.WARNING:/, 'text-amber-300'],
    [/\.(INFO|NOTICE):/, 'text-sky-300'],
];

function logLineClass(line: string): string {
    return (
        LOG_LEVEL_STYLES.find(([pattern]) => pattern.test(line))?.[1] ??
        'text-slate-400'
    );
}

/**
 * The admin's view of the moving parts. State rather than process output, so
 * it reads the same on a dev box and in production where the workers run
 * under a supervisor on another host.
 */
export default function SystemIndex({ status }: { status: Status }) {
    usePoll(5000, { only: ['status'] });

    const workersOk = status.queues.every((queue) => queue.alive);

    return (
        <>
            <Head title="システム" />

            <AdminNav />

            <div className="flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <Activity className="size-5 text-slate-400" />
                    システム
                </h1>
                <p className="text-sm text-slate-500">
                    CARROT
                    全体のワーカー・サンドボックス・Reverb・ログの現在の状態。5
                    秒ごとに更新します。
                </p>
                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                    {formatTimestamp(status.checkedAt)}
                </span>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <Light
                    ok={workersOk}
                    label={workersOk ? 'ワーカー稼働中' : 'ワーカー停止の疑い'}
                />
                <Light
                    ok={status.sandbox.ready}
                    label={`サンドボックス: ${status.sandbox.driver}`}
                />
                <Light
                    ok={status.reverb.up}
                    label={
                        status.reverb.up === null
                            ? `Reverb 未使用 (${status.reverb.connection})`
                            : status.reverb.up
                              ? 'Reverb 接続可'
                              : 'Reverb 応答なし'
                    }
                />
                <Light
                    ok={status.failedJobs.count === 0}
                    label={`失敗ジョブ ${status.failedJobs.count}`}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Panel title="キュー">
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-slate-500">
                            <tr>
                                <th className="pb-2 font-semibold">キュー</th>
                                <th className="pb-2 font-semibold">待機</th>
                                <th className="pb-2 font-semibold">処理中</th>
                                <th className="pb-2 font-semibold">
                                    最古の待機
                                </th>
                                <th className="pb-2 font-semibold">ワーカー</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {status.queues.map((queue) => (
                                <tr key={queue.name}>
                                    <td className="py-2 font-mono text-xs">
                                        {queue.name}
                                    </td>
                                    <td className="py-2 tabular-nums">
                                        {queue.pending}
                                    </td>
                                    <td className="py-2 tabular-nums">
                                        {queue.reserved}
                                    </td>
                                    <td
                                        className={cn(
                                            'py-2 tabular-nums',
                                            (queue.oldestPendingSeconds ?? 0) >
                                                60 && 'text-rose-600',
                                        )}
                                    >
                                        {queue.oldestPendingSeconds === null
                                            ? '—'
                                            : `${queue.oldestPendingSeconds}s`}
                                    </td>
                                    <td className="py-2">
                                        <Light
                                            ok={queue.alive}
                                            label={
                                                queue.heartbeatAt
                                                    ? formatTimestamp(
                                                          queue.heartbeatAt,
                                                      )
                                                    : '未確認'
                                            }
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-3 text-xs text-slate-400">
                        ワーカーはループごとに合図を残します。90
                        秒以上途絶えると停止扱いです。
                    </p>
                </Panel>

                <Panel title="サンドボックス">
                    <dl className="grid gap-2 text-sm">
                        <div className="flex gap-3">
                            <dt className="w-28 shrink-0 text-xs font-semibold text-slate-500">
                                ドライバ
                            </dt>
                            <dd className="font-mono text-xs">
                                {status.sandbox.driver}
                                {status.sandbox.requireRootless &&
                                    status.sandbox.driver === 'docker' &&
                                    ' (rootless 必須)'}
                            </dd>
                        </div>
                        {status.sandbox.message && (
                            <div className="rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                {status.sandbox.message}
                            </div>
                        )}
                        {Object.entries(status.sandbox.runtimes).map(
                            ([runtime, label]) => (
                                <div key={runtime} className="flex gap-3">
                                    <dt className="w-28 shrink-0 text-xs font-semibold text-slate-500">
                                        {runtime}
                                    </dt>
                                    <dd className="text-xs text-slate-700">
                                        {label}
                                    </dd>
                                </div>
                            ),
                        )}
                        <div className="flex gap-3">
                            <dt className="w-28 shrink-0 text-xs font-semibold text-slate-500">
                                直近 24h
                            </dt>
                            <dd className="flex flex-wrap gap-1.5">
                                {Object.entries(status.runs.last24h).map(
                                    ([key, count]) => (
                                        <StatusPill
                                            key={key}
                                            value={key}
                                            label={`${key} ${count}`}
                                            styles={RUN_STATUS_STYLES}
                                        />
                                    ),
                                )}
                            </dd>
                        </div>
                    </dl>
                </Panel>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Panel
                    title="最近の実行"
                    right={
                        status.runs.running > 0 && (
                            <span className="inline-flex items-center gap-1 text-xs text-sky-700">
                                <Loader2 className="size-3 animate-spin" />
                                {status.runs.running} 件 実行中
                            </span>
                        )
                    }
                >
                    {status.runs.recent.length === 0 ? (
                        <p className="text-xs text-slate-400">
                            まだ実行はありません。
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {status.runs.recent.map((run) => (
                                <li
                                    key={run.ulid}
                                    className="flex flex-wrap items-center gap-x-3 gap-y-1 py-2"
                                >
                                    <StatusPill
                                        value={run.status}
                                        label={run.statusLabel}
                                        styles={RUN_STATUS_STYLES}
                                    />
                                    {run.toolUlid ? (
                                        <Link
                                            href={showRun([
                                                run.toolUlid,
                                                run.ulid,
                                            ])}
                                            className="font-medium text-sky-700 hover:underline"
                                        >
                                            {run.tool}
                                        </Link>
                                    ) : (
                                        <span className="text-slate-600">
                                            テスト実行
                                        </span>
                                    )}
                                    <span className="text-xs text-slate-500">
                                        {run.user}
                                    </span>
                                    <span className="ml-auto text-xs text-slate-400 tabular-nums">
                                        {formatTimestamp(run.createdAt)}
                                        {run.durationMs !== null &&
                                            ` · ${run.durationMs} ms`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel title="失敗したジョブ">
                    {status.failedJobs.recent.length === 0 ? (
                        <p className="text-xs text-slate-400">
                            失敗したジョブはありません。
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100 text-sm">
                            {status.failedJobs.recent.map((job) => (
                                <li key={job.id} className="py-2">
                                    <div className="flex flex-wrap items-center gap-x-3">
                                        <span className="font-mono text-xs text-slate-700">
                                            {job.job}
                                        </span>
                                        <span className="rounded bg-slate-100 px-1.5 text-[11px] text-slate-500">
                                            {job.queue}
                                        </span>
                                        <span className="ml-auto text-xs text-slate-400 tabular-nums">
                                            {formatTimestamp(job.failedAt)}
                                        </span>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-rose-700">
                                        {job.exception}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-3 text-xs text-slate-400">
                        再試行は <code>php artisan queue:retry all</code>。
                    </p>
                </Panel>
            </div>

            <div className="mt-6">
                <Panel
                    title="アプリケーションログ"
                    right={
                        status.log.path && (
                            <span className="font-mono text-[11px] text-slate-400">
                                {status.log.path}
                            </span>
                        )
                    }
                >
                    <pre className="max-h-[28rem] overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-[11px] leading-relaxed">
                        {status.log.lines.length === 0 ? (
                            <span className="text-slate-500">
                                ログはまだありません。
                            </span>
                        ) : (
                            status.log.lines.map((line, i) => (
                                <span
                                    key={i}
                                    className={cn('block', logLineClass(line))}
                                >
                                    {line}
                                </span>
                            ))
                        )}
                    </pre>
                </Panel>
            </div>
        </>
    );
}
