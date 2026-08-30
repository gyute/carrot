import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Send, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import ToolIcon from '@/components/tool-icon';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { KIND_LABELS, NETWORK_LABELS, toolAccent } from '@/lib/tool-presets';
import type { ToolKind } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { show as showTool } from '@/routes/tools';
import { store as storeChange } from '@/routes/tools/change';
import { index, store, update } from '@/routes/tools/submissions';
import type {
    FormLimits,
    SubmissionPayload,
    SubmissionSummary,
    ToolInput,
} from '@/types/tools';

type Props = {
    /** Set when editing an existing draft. */
    submission: SubmissionSummary | null;
    /** Set when the request changes a published tool. */
    tool: { ulid: string; name: string; kind: ToolKind } | null;
    initial: SubmissionPayload | null;
    limits: FormLimits;
    /** Set when the form was opened from a request, which approving closes. */
    answers?: { ulid: string; title: string } | null;
};

type FormData = {
    kind: ToolKind;
    name: string;
    summary: string;
    description: string;
    icon: string;
    accent: string;
    department: string;
    categories: string[];
    config: {
        url: string;
        runtime: 'php' | 'shell';
        timeout_sec: number;
        memory_mb: number;
        network: 'none' | 'internet';
        inputs: ToolInput[];
    };
    source: string;
    note: string;
    tool_request: string;
    submit: boolean;
};

const KIND_HELP: Record<ToolKind, string> = {
    link: 'クリックすると URL に移動します。ポータル内のパスも指定できます。',
    embed: '外部の https ページをこのツールの画面内に埋め込んで表示します。',
    script: 'PHP または Shell スクリプトを隔離環境（サンドボックス）で実行します。',
};

const SCRIPT_TEMPLATES: Record<'php' | 'shell', string> = {
    php: `<?php
// 入力値は $_SERVER['TOOL_INPUTS'] のファイルに JSON で渡されます。
$inputs = json_decode(file_get_contents(getenv('TOOL_INPUTS')), true) ?? [];

echo "hello\\n";
`,
    shell: `#!/bin/sh
# 入力値は $TOOL_INPUTS のファイルに JSON で渡されます（jq が使えます）。
echo "hello"
`,
};

function fieldClass(invalid?: string) {
    return cn(
        'border-slate-200 bg-white',
        invalid && 'border-rose-400 focus-visible:ring-rose-200',
    );
}

export default function SubmissionForm({
    submission,
    tool,
    initial,
    limits,
    answers = null,
}: Props) {
    const changeRequest = tool !== null;
    const form = useForm<FormData>({
        kind: initial?.kind ?? tool?.kind ?? 'link',
        name: initial?.name ?? '',
        summary: initial?.summary ?? '',
        description: initial?.description ?? '',
        icon: initial?.icon ?? 'wrench',
        accent: initial?.accent ?? 'sky',
        department: initial?.department ?? '',
        categories: initial?.categories ?? [],
        config: {
            url: initial?.config?.url ?? '',
            runtime: initial?.config?.runtime ?? 'php',
            timeout_sec: initial?.config?.timeout_sec ?? 30,
            memory_mb: initial?.config?.memory_mb ?? 128,
            network: initial?.config?.network ?? 'none',
            inputs: initial?.config?.inputs ?? [],
        },
        source: initial?.source ?? '',
        note: '',
        tool_request: answers?.ulid ?? '',
        submit: false,
    });
    const { data, setData, errors, processing } = form;
    const error = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    const send = (submitNow: boolean) => {
        form.transform((current) => ({ ...current, submit: submitNow }));

        if (submission) {
            form.patch(update(submission.ulid).url);
        } else if (tool) {
            form.post(storeChange(tool.ulid).url);
        } else {
            form.post(store().url);
        }
    };

    const setConfig = <K extends keyof FormData['config']>(
        key: K,
        value: FormData['config'][K],
    ) => setData('config', { ...data.config, [key]: value });

    const setInput = (i: number, patch: Partial<ToolInput>) =>
        setConfig(
            'inputs',
            data.config.inputs.map((input, j) =>
                j === i ? { ...input, ...patch } : input,
            ),
        );

    const title = submission
        ? '申請を編集'
        : changeRequest
          ? `${tool.name} の変更を申請`
          : 'ツールを登録';

    return (
        <>
            <Head title={title} />

            <ToolsNav />

            <Link
                href={changeRequest ? showTool(tool.ulid) : index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                {changeRequest ? 'ツールへ戻る' : '申請一覧へ'}
            </Link>

            <h1 className="mt-2 text-xl font-bold text-slate-800">{title}</h1>

            {answers && (
                <p className="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-900">
                    リクエスト「{answers.title}
                    」への回答として登録します。承認されるとそのリクエストは公開済みになります。
                </p>
            )}
            <p className="mt-1 text-sm text-slate-500">
                {changeRequest
                    ? '動作に関わる設定（URL・スクリプト）の変更は承認が必要です。承認されるまでは現在の内容のまま稼働します。'
                    : '下書きはいつでも保存できます。「申請する」で管理者に届き、承認されると全社に公開されます。'}
            </p>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    send(false);
                }}
                className="mt-6 grid gap-6"
            >
                {!changeRequest && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-sm font-bold text-slate-700">
                            表示内容
                        </h2>
                        <p className="mt-1 text-xs text-slate-500">
                            ここは承認後も所有者が自由に変更できます。
                        </p>

                        <div className="mt-4 grid gap-4 sm:grid-cols-[auto_1fr]">
                            <div className="flex flex-col items-center gap-2">
                                <span
                                    className={`flex size-16 items-center justify-center rounded-2xl bg-linear-to-br ${toolAccent(data.accent)} text-white shadow-sm`}
                                >
                                    <ToolIcon
                                        name={data.icon}
                                        className="size-8"
                                    />
                                </span>
                                <span className="text-[11px] text-slate-400">
                                    プレビュー
                                </span>
                            </div>

                            <div className="grid gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">ツール名</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        maxLength={60}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        className={fieldClass(error('name'))}
                                    />
                                    <InputError message={error('name')} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="summary">概要</Label>
                                    <Input
                                        id="summary"
                                        value={data.summary}
                                        maxLength={120}
                                        placeholder="カードに表示される一行の説明"
                                        onChange={(e) =>
                                            setData('summary', e.target.value)
                                        }
                                        className={fieldClass(error('summary'))}
                                    />
                                    <InputError message={error('summary')} />
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-1.5">
                            <Label htmlFor="description">説明（任意）</Label>
                            <textarea
                                id="description"
                                rows={4}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="使い方や注意点など。ツールの詳細画面に表示されます。"
                                className={cn(
                                    'rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none',
                                    fieldClass(error('description')),
                                )}
                            />
                            <InputError message={error('description')} />
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label>アイコン</Label>
                                <div className="flex flex-wrap gap-1.5">
                                    {limits.icons.map((name) => (
                                        <button
                                            key={name}
                                            type="button"
                                            title={name}
                                            aria-pressed={data.icon === name}
                                            onClick={() =>
                                                setData('icon', name)
                                            }
                                            className={cn(
                                                'flex size-9 items-center justify-center rounded-lg border transition',
                                                data.icon === name
                                                    ? 'border-sky-500 bg-sky-50 text-sky-700'
                                                    : 'border-slate-200 text-slate-500 hover:border-slate-300',
                                            )}
                                        >
                                            <ToolIcon
                                                name={name}
                                                className="size-4"
                                            />
                                        </button>
                                    ))}
                                </div>
                                <InputError message={error('icon')} />
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
                                            onClick={() =>
                                                setData('accent', name)
                                            }
                                            className={cn(
                                                `size-9 rounded-lg bg-linear-to-br ${toolAccent(name)} ring-offset-2 transition`,
                                                data.accent === name &&
                                                    'ring-2 ring-sky-500',
                                            )}
                                        />
                                    ))}
                                </div>
                                <InputError message={error('accent')} />
                            </div>
                        </div>

                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="department">所属</Label>
                                <select
                                    id="department"
                                    value={data.department}
                                    onChange={(e) =>
                                        setData('department', e.target.value)
                                    }
                                    className={cn(
                                        'h-9 rounded-md border px-3 text-sm shadow-xs',
                                        fieldClass(error('department')),
                                    )}
                                >
                                    <option value="">未設定</option>
                                    {limits.departments.map((department) => (
                                        <option
                                            key={department}
                                            value={department}
                                        >
                                            {department}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={error('department')} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="categories">
                                    カテゴリ（カンマ区切り、5つまで）
                                </Label>
                                <Input
                                    id="categories"
                                    defaultValue={data.categories.join(', ')}
                                    placeholder="例: データ, 勤怠"
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
                                    className={fieldClass(error('categories'))}
                                />
                                <InputError
                                    message={
                                        error('categories') ??
                                        error('categories.0')
                                    }
                                />
                            </div>
                        </div>
                    </section>
                )}

                <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-bold text-slate-700">動作</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        ここを変更するには承認が必要です。
                    </p>

                    {!changeRequest && (
                        <div className="mt-4 grid gap-2 sm:grid-cols-3">
                            {(Object.keys(KIND_LABELS) as ToolKind[]).map(
                                (kind) => (
                                    <label
                                        key={kind}
                                        className={cn(
                                            'flex cursor-pointer flex-col gap-1 rounded-lg border p-3 transition',
                                            data.kind === kind
                                                ? 'border-sky-500 bg-sky-50/60'
                                                : 'border-slate-200 hover:border-slate-300',
                                        )}
                                    >
                                        <span className="flex items-center gap-2 text-sm font-medium text-slate-800">
                                            <input
                                                type="radio"
                                                name="kind"
                                                value={kind}
                                                checked={data.kind === kind}
                                                onChange={() => {
                                                    setData('kind', kind);

                                                    if (
                                                        kind === 'script' &&
                                                        data.source === ''
                                                    ) {
                                                        setData(
                                                            'source',
                                                            SCRIPT_TEMPLATES[
                                                                data.config
                                                                    .runtime
                                                            ],
                                                        );
                                                    }
                                                }}
                                                className="accent-sky-600"
                                            />
                                            {KIND_LABELS[kind]}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {KIND_HELP[kind]}
                                        </span>
                                    </label>
                                ),
                            )}
                        </div>
                    )}

                    {(data.kind === 'link' || data.kind === 'embed') && (
                        <div className="mt-4 grid gap-1.5">
                            <Label htmlFor="url">URL</Label>
                            <Input
                                id="url"
                                value={data.config.url}
                                placeholder={
                                    data.kind === 'embed'
                                        ? 'https://…（外部サイトのみ）'
                                        : 'https://… または /tools/…'
                                }
                                onChange={(e) =>
                                    setConfig('url', e.target.value)
                                }
                                className={cn(
                                    'font-mono text-sm',
                                    fieldClass(error('config.url')),
                                )}
                            />
                            <InputError message={error('config.url')} />
                        </div>
                    )}

                    {data.kind === 'script' && (
                        <div className="mt-4 grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="runtime">ランタイム</Label>
                                    <select
                                        id="runtime"
                                        value={data.config.runtime}
                                        onChange={(e) => {
                                            const runtime = e.target.value as
                                                'php' | 'shell';
                                            const untouched =
                                                data.source === '' ||
                                                data.source ===
                                                    SCRIPT_TEMPLATES[
                                                        data.config.runtime
                                                    ];

                                            setConfig('runtime', runtime);

                                            if (untouched) {
                                                setData(
                                                    'source',
                                                    SCRIPT_TEMPLATES[runtime],
                                                );
                                            }
                                        }}
                                        className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm shadow-xs"
                                    >
                                        <option value="php">
                                            {limits.runtimes.php ?? 'PHP'}
                                        </option>
                                        <option value="shell">
                                            {limits.runtimes.shell ??
                                                'Shell (sh)'}
                                        </option>
                                    </select>
                                    <InputError
                                        message={error('config.runtime')}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="timeout">
                                        タイムアウト（秒、最大{' '}
                                        {limits.timeoutMax}）
                                    </Label>
                                    <Input
                                        id="timeout"
                                        type="number"
                                        min={1}
                                        max={limits.timeoutMax}
                                        value={data.config.timeout_sec}
                                        onChange={(e) =>
                                            setConfig(
                                                'timeout_sec',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={fieldClass(
                                            error('config.timeout_sec'),
                                        )}
                                    />
                                    <InputError
                                        message={error('config.timeout_sec')}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="memory">
                                        メモリ上限（MB、最大 {limits.memoryMax}
                                        ）
                                    </Label>
                                    <Input
                                        id="memory"
                                        type="number"
                                        min={32}
                                        max={limits.memoryMax}
                                        step={32}
                                        value={data.config.memory_mb}
                                        onChange={(e) =>
                                            setConfig(
                                                'memory_mb',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={fieldClass(
                                            error('config.memory_mb'),
                                        )}
                                    />
                                    <InputError
                                        message={error('config.memory_mb')}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="network">ネットワーク</Label>
                                <select
                                    id="network"
                                    value={data.config.network}
                                    onChange={(e) =>
                                        setConfig(
                                            'network',
                                            e.target.value as
                                                'none' | 'internet',
                                        )
                                    }
                                    className="h-9 max-w-xs rounded-md border border-slate-200 bg-white px-3 text-sm shadow-xs"
                                >
                                    <option value="none">
                                        {NETWORK_LABELS.none}
                                    </option>
                                    <option value="internet">
                                        {NETWORK_LABELS.internet}
                                    </option>
                                </select>
                                <p className="text-xs text-slate-500">
                                    既定は完全遮断です。外部 API
                                    を呼ぶ必要がある場合のみ「インターネットあり」を選んでください。承認者が用途を確認します。
                                </p>
                                <InputError message={error('config.network')} />
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-center justify-between">
                                    <Label>入力項目</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            data.config.inputs.length >= 10
                                        }
                                        onClick={() =>
                                            setConfig('inputs', [
                                                ...data.config.inputs,
                                                {
                                                    key: '',
                                                    label: '',
                                                    type: 'text',
                                                    required: false,
                                                    options: [],
                                                },
                                            ])
                                        }
                                    >
                                        <Plus className="size-4" />
                                        追加
                                    </Button>
                                </div>
                                <p className="text-xs text-slate-500">
                                    実行時に利用者へ尋ねる値です。スクリプトには
                                    JSON ファイルとして渡されます。
                                </p>
                                <InputError message={error('config.inputs')} />

                                {data.config.inputs.map((input, i) => (
                                    <div
                                        key={i}
                                        className="grid gap-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3 sm:grid-cols-[1fr_1fr_8rem_auto_auto]"
                                    >
                                        <div className="grid gap-1">
                                            <Input
                                                value={input.key}
                                                placeholder="key（英小文字）"
                                                onChange={(e) =>
                                                    setInput(i, {
                                                        key: e.target.value,
                                                    })
                                                }
                                                className={cn(
                                                    'h-8 font-mono text-xs',
                                                    fieldClass(
                                                        error(
                                                            `config.inputs.${i}.key`,
                                                        ),
                                                    ),
                                                )}
                                            />
                                            <InputError
                                                className="text-xs"
                                                message={error(
                                                    `config.inputs.${i}.key`,
                                                )}
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Input
                                                value={input.label}
                                                placeholder="ラベル"
                                                onChange={(e) =>
                                                    setInput(i, {
                                                        label: e.target.value,
                                                    })
                                                }
                                                className={cn(
                                                    'h-8 text-xs',
                                                    fieldClass(
                                                        error(
                                                            `config.inputs.${i}.label`,
                                                        ),
                                                    ),
                                                )}
                                            />
                                            <InputError
                                                className="text-xs"
                                                message={error(
                                                    `config.inputs.${i}.label`,
                                                )}
                                            />
                                        </div>
                                        <select
                                            value={input.type}
                                            onChange={(e) =>
                                                setInput(i, {
                                                    type: e.target
                                                        .value as ToolInput['type'],
                                                })
                                            }
                                            className="h-8 rounded-md border border-slate-200 bg-white px-2 text-xs"
                                        >
                                            <option value="text">文字</option>
                                            <option value="number">数値</option>
                                            <option value="select">選択</option>
                                        </select>
                                        <label className="flex items-center gap-1.5 text-xs text-slate-600">
                                            <Checkbox
                                                checked={input.required}
                                                onCheckedChange={(checked) =>
                                                    setInput(i, {
                                                        required:
                                                            checked === true,
                                                    })
                                                }
                                            />
                                            必須
                                        </label>
                                        <button
                                            type="button"
                                            aria-label="削除"
                                            onClick={() =>
                                                setConfig(
                                                    'inputs',
                                                    data.config.inputs.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                            className="text-slate-400 hover:text-rose-600"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                        {input.type === 'select' && (
                                            <div className="grid gap-1 sm:col-span-5">
                                                <Input
                                                    defaultValue={(
                                                        input.options ?? []
                                                    ).join(', ')}
                                                    placeholder="選択肢（カンマ区切り）"
                                                    onBlur={(e) =>
                                                        setInput(i, {
                                                            options:
                                                                e.target.value
                                                                    .split(
                                                                        /[,、]/,
                                                                    )
                                                                    .map((v) =>
                                                                        v.trim(),
                                                                    )
                                                                    .filter(
                                                                        Boolean,
                                                                    ),
                                                        })
                                                    }
                                                    className="h-8 text-xs"
                                                />
                                                <InputError
                                                    className="text-xs"
                                                    message={error(
                                                        `config.inputs.${i}.options`,
                                                    )}
                                                />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-baseline justify-between">
                                    <Label htmlFor="source">ソースコード</Label>
                                    <span className="text-xs text-slate-400 tabular-nums">
                                        {
                                            new TextEncoder().encode(
                                                data.source,
                                            ).length
                                        }{' '}
                                        / {limits.sourceBytes} bytes
                                    </span>
                                </div>
                                <textarea
                                    id="source"
                                    rows={18}
                                    spellCheck={false}
                                    value={data.source}
                                    onChange={(e) =>
                                        setData('source', e.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key !== 'Tab') {
                                            return;
                                        }

                                        e.preventDefault();
                                        const el = e.currentTarget;
                                        const { selectionStart, selectionEnd } =
                                            el;
                                        const next =
                                            data.source.slice(
                                                0,
                                                selectionStart,
                                            ) +
                                            '    ' +
                                            data.source.slice(selectionEnd);

                                        setData('source', next);
                                        requestAnimationFrame(() => {
                                            el.selectionStart =
                                                el.selectionEnd =
                                                    selectionStart + 4;
                                        });
                                    }}
                                    className={cn(
                                        'rounded-lg border bg-slate-900 p-4 font-mono text-xs leading-relaxed text-slate-100 shadow-inner focus-visible:ring-2 focus-visible:ring-sky-500/40 focus-visible:outline-none',
                                        error('source')
                                            ? 'border-rose-400'
                                            : 'border-slate-700',
                                    )}
                                />
                                <InputError message={error('source')} />
                                <p className="text-xs text-slate-500">
                                    ネットワークなし・書き込み不可・非 root
                                    で実行されます。結果は標準出力のみ返ります。
                                </p>
                            </div>
                        </div>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <Label htmlFor="note">申請メモ（任意）</Label>
                    <textarea
                        id="note"
                        rows={3}
                        value={data.note}
                        onChange={(e) => setData('note', e.target.value)}
                        placeholder="承認者への補足があれば書いてください。"
                        className={cn(
                            'mt-1.5 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none',
                            fieldClass(error('note')),
                        )}
                    />
                    <InputError message={error('note')} />
                </section>

                <div className="flex flex-wrap items-center justify-end gap-2">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing}
                    >
                        <Save className="size-4" />
                        下書きを保存
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={() => send(true)}
                        className="bg-sky-700 text-white hover:bg-sky-800"
                    >
                        <Send className="size-4" />
                        申請する
                    </Button>
                </div>
            </form>
        </>
    );
}
