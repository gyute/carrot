import ToolIcon from '@/components/tool-icon';
import { KIND_LABELS, NETWORK_LABELS, toolAccent } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import type { SubmissionPayload } from '@/types/tools';

type Props = {
    payload: SubmissionPayload;
    /** When given, fields that differ from it are highlighted. */
    current?: SubmissionPayload | null;
    /** A change request only carries behaviour fields; hide the display rows. */
    behaviourOnly?: boolean;
    /** Driver-reported runtime descriptions, keyed by runtime. */
    runtimes?: Record<string, string>;
};

function same(a: unknown, b: unknown): boolean {
    return JSON.stringify(a ?? null) === JSON.stringify(b ?? null);
}

function Row({
    label,
    changed,
    children,
}: {
    label: string;
    changed?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid gap-1 px-4 py-3 sm:grid-cols-[9rem_1fr] sm:gap-4',
                changed && 'bg-amber-50/70',
            )}
        >
            <dt className="flex items-center gap-2 text-xs font-semibold text-slate-500">
                {label}
                {changed && (
                    <span className="rounded bg-amber-200 px-1 text-[10px] font-bold text-amber-800">
                        変更
                    </span>
                )}
            </dt>
            <dd className="text-sm text-slate-800">{children}</dd>
        </div>
    );
}

/**
 * The content of a request, laid out as a definition list. Used by the
 * requester's detail page and the admin's approval page so both read the
 * same thing.
 */
export default function SubmissionPayloadView({
    payload,
    current,
    behaviourOnly = false,
    runtimes = {},
}: Props) {
    const kind = payload.kind ?? current?.kind;
    const config = payload.config ?? {};
    const diff = (key: keyof SubmissionPayload) =>
        current !== null &&
        current !== undefined &&
        !same(payload[key], current[key]);

    return (
        <dl className="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            {!behaviourOnly && (
                <>
                    <Row label="ツール">
                        <span className="flex items-center gap-3">
                            <span
                                className={`flex size-9 items-center justify-center rounded-lg bg-linear-to-br ${toolAccent(payload.accent ?? 'slate')} text-white`}
                            >
                                <ToolIcon
                                    name={
                                        payload.icon ??
                                        current?.icon ??
                                        'wrench'
                                    }
                                    className="size-4"
                                />
                            </span>
                            <span>
                                <span className="font-bold">
                                    {payload.name}
                                </span>
                                <span className="block text-xs text-slate-500">
                                    {payload.summary}
                                </span>
                            </span>
                        </span>
                    </Row>
                    {payload.description && (
                        <Row label="説明">
                            <p className="whitespace-pre-wrap">
                                {payload.description}
                            </p>
                        </Row>
                    )}
                    <Row label="所属 / カテゴリ">
                        <span className="flex flex-wrap items-center gap-2">
                            {payload.department && (
                                <span className="text-slate-600">
                                    {payload.department}
                                </span>
                            )}
                            {(payload.categories ?? []).map((category) => (
                                <span
                                    key={category}
                                    className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
                                >
                                    {category}
                                </span>
                            ))}
                        </span>
                    </Row>
                </>
            )}

            <Row label="種類">{kind ? KIND_LABELS[kind] : '—'}</Row>

            {(kind === 'link' || kind === 'embed') && (
                <Row label="URL" changed={diff('config')}>
                    <code className="rounded bg-slate-50 px-1.5 py-0.5 font-mono text-xs break-all">
                        {config.url}
                    </code>
                    {diff('config') && current?.config?.url && (
                        <span className="mt-1 block text-xs text-slate-400 line-through">
                            {current.config.url}
                        </span>
                    )}
                </Row>
            )}

            {kind === 'script' && (
                <>
                    <Row label="実行環境" changed={diff('config')}>
                        {runtimes[config.runtime ?? 'php'] ??
                            (config.runtime === 'php' ? 'PHP' : 'Shell (sh)')}
                        <span className="ml-3 text-xs text-slate-500">
                            タイムアウト {config.timeout_sec}s · メモリ{' '}
                            {config.memory_mb}MB
                        </span>
                    </Row>
                    <Row label="ネットワーク" changed={diff('config')}>
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                                config.network === 'internet'
                                    ? 'bg-rose-50 text-rose-700 ring-rose-200'
                                    : 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            )}
                        >
                            {NETWORK_LABELS[config.network ?? 'none']}
                        </span>
                        {config.network === 'internet' && (
                            <span className="ml-2 text-xs text-rose-700">
                                このスクリプトは外部と通信できます。承認前に用途を確認してください。
                            </span>
                        )}
                    </Row>
                    <Row label="入力項目">
                        {(config.inputs ?? []).length === 0 ? (
                            <span className="text-slate-400">なし</span>
                        ) : (
                            <ul className="space-y-1">
                                {(config.inputs ?? []).map((input) => (
                                    <li key={input.key} className="text-xs">
                                        <code className="rounded bg-slate-50 px-1 font-mono">
                                            {input.key}
                                        </code>{' '}
                                        {input.label} ({input.type}
                                        {input.required ? ', 必須' : ''})
                                        {input.options?.length ? (
                                            <span className="text-slate-500">
                                                {' '}
                                                : {input.options.join(' / ')}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Row>
                    <Row label="ソース" changed={diff('source')}>
                        <pre className="max-h-96 overflow-auto rounded-lg bg-slate-900 p-4 font-mono text-xs leading-relaxed text-slate-100 scheme-dark">
                            {payload.source}
                        </pre>
                    </Row>
                </>
            )}
        </dl>
    );
}
