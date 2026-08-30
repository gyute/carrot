import { Head, router, useForm } from '@inertiajs/react';
import { Check, Pencil, Tags, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import AdminNav from '@/components/admin-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, update } from '@/routes/admin/tags';

type AdminTag = {
    id: number;
    group: string;
    value: string;
    tools: number;
};

type Props = {
    tags: AdminTag[];
};

function RenameForm({ tag, onDone }: { tag: AdminTag; onDone: () => void }) {
    const form = useForm({ value: tag.value });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update(tag.id).url, { onSuccess: onDone });
            }}
            className="flex flex-wrap items-start gap-2"
        >
            <div>
                <Input
                    value={form.data.value}
                    onChange={(event) =>
                        form.setData('value', event.target.value)
                    }
                    className="h-9 w-48 text-sm"
                    autoFocus
                />
                <InputError message={form.errors.value} />
            </div>
            <Button type="submit" size="sm" disabled={form.processing}>
                <Check className="size-4" />
                保存
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                <X className="size-4" />
            </Button>
        </form>
    );
}

export default function AdminTags({ tags }: Props) {
    const [editing, setEditing] = useState<number | null>(null);

    const remove = (tag: AdminTag) => {
        if (
            window.confirm(
                `「${tag.value}」を削除します。${tag.tools} 件のツールから外れます。`,
            )
        ) {
            router.delete(destroy(tag.id).url);
        }
    };

    return (
        <>
            <Head title="タグ" />

            <AdminNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <Tags className="size-5 text-slate-400" />
                    タグ
                </h1>
                <p className="text-sm text-slate-500">
                    申請時に入力された名前がそのまま増えます。表記ゆれはここで直し、同じ名前に変更すると統合されます。
                </p>
                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                    {tags.length} 件
                </span>
            </div>

            {tags.length === 0 ? (
                <p className="mt-4 rounded-xl border border-dashed border-slate-300 bg-white/60 px-4 py-10 text-center text-sm text-slate-500">
                    まだタグがありません。
                </p>
            ) : (
                <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-2 font-semibold">
                                    グループ
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    タグ
                                </th>
                                <th className="px-4 py-2 font-semibold">
                                    ツール数
                                </th>
                                <th className="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tags.map((tag) => (
                                <tr key={tag.id}>
                                    <td className="px-4 py-3 text-xs text-slate-500">
                                        {tag.group}
                                    </td>
                                    <td
                                        className="px-4 py-3"
                                        colSpan={editing === tag.id ? 3 : 1}
                                    >
                                        {editing === tag.id ? (
                                            <RenameForm
                                                tag={tag}
                                                onDone={() => setEditing(null)}
                                            />
                                        ) : (
                                            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                                {tag.value}
                                            </span>
                                        )}
                                    </td>
                                    {editing !== tag.id && (
                                        <>
                                            <td className="px-4 py-3 text-slate-600 tabular-nums">
                                                {tag.tools}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setEditing(tag.id)
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                    名前を変更
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="ml-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                                    onClick={() => remove(tag)}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </td>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
