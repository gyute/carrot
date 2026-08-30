import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ToolKind } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/tools/requests';
import type { ToolRequestDetail, ToolRequestLimits } from '@/types/tools';

type Props = {
    toolRequest: ToolRequestDetail | null;
    limits: ToolRequestLimits;
};

const BODY_PLACEHOLDER = `例）
・今どうやっていますか: 毎月末に請求書を1件ずつ開いて消費税を電卓で計算しています
・何が大変ですか: 100件あると半日かかり、写し間違いも起きます
・どうなれば嬉しいですか: 金額を貼り付けたら税込の一覧が出てほしいです`;

export default function RequestForm({ toolRequest, limits }: Props) {
    const form = useForm({
        title: toolRequest?.title ?? '',
        body: toolRequest?.body ?? '',
        categories: toolRequest?.categories ?? [],
        desired_kind: toolRequest?.desiredKind ?? '',
        needed_by: toolRequest?.neededBy ?? '',
    });
    const { data, setData, errors, processing } = form;

    const send = () => {
        if (toolRequest) {
            form.patch(update(toolRequest.ulid).url);
        } else {
            form.post(store().url);
        }
    };

    const title = toolRequest ? 'リクエストを編集' : 'ツールをリクエスト';

    return (
        <>
            <Head title={title} />

            <ToolsNav />

            <Link
                href={index()}
                className="mt-6 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                リクエスト一覧へ
            </Link>

            <h1 className="mt-2 text-xl font-bold text-slate-800">{title}</h1>
            <p className="mt-1 text-sm text-slate-500">
                作り方は分からなくて構いません。困っていることをそのまま書いてください。開発チームが読んで判断します。
            </p>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    send();
                }}
                className="mt-6 grid gap-6"
            >
                <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-1.5">
                        <Label htmlFor="title">タイトル</Label>
                        <Input
                            id="title"
                            value={data.title}
                            maxLength={80}
                            placeholder="ひとことで言うと何がしたいか"
                            onChange={(e) => setData('title', e.target.value)}
                            className={cn(
                                'bg-white',
                                errors.title && 'border-rose-400',
                            )}
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="mt-4 grid gap-1.5">
                        <Label htmlFor="body">内容</Label>
                        <textarea
                            id="body"
                            rows={10}
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder={BODY_PLACEHOLDER}
                            className={cn(
                                'rounded-md border bg-white px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none',
                                errors.body
                                    ? 'border-rose-400'
                                    : 'border-slate-200',
                            )}
                        />
                        <InputError message={errors.body} />
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="needed_by">希望時期（任意）</Label>
                            <Input
                                id="needed_by"
                                type="date"
                                value={data.needed_by ?? ''}
                                onChange={(e) =>
                                    setData('needed_by', e.target.value)
                                }
                                className={cn(
                                    'bg-white',
                                    errors.needed_by && 'border-rose-400',
                                )}
                            />
                            <InputError message={errors.needed_by} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="desired_kind">
                                希望する形式（任意）
                            </Label>
                            <select
                                id="desired_kind"
                                value={data.desired_kind ?? ''}
                                onChange={(e) =>
                                    setData(
                                        'desired_kind',
                                        e.target.value as ToolKind | '',
                                    )
                                }
                                className="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-sky-500/30 focus-visible:outline-none"
                            >
                                <option value="">わからない / おまかせ</option>
                                {limits.kinds.map((kind) => (
                                    <option key={kind.value} value={kind.value}>
                                        {kind.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.desired_kind} />
                        </div>
                    </div>

                    <div className="mt-4 grid gap-1.5">
                        <Label htmlFor="categories">カテゴリ（任意）</Label>
                        <Input
                            id="categories"
                            value={data.categories.join('、')}
                            placeholder="読点かカンマで区切って、最大5つ"
                            onChange={(e) =>
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

                    <p className="mt-4 text-xs text-slate-500">
                        {limits.department
                            ? `所属「${limits.department}」のリクエストとして登録され、同じ所属のメンバーと開発チームが読めます。`
                            : '所属が未設定のため、このリクエストはあなたと開発チームだけに表示されます。所属はシステム管理者が設定します。'}
                    </p>
                </section>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-sky-700 text-white hover:bg-sky-800"
                    >
                        <Send className="size-4" />
                        {toolRequest ? '保存' : 'リクエストを送る'}
                    </Button>
                </div>
            </form>
        </>
    );
}
