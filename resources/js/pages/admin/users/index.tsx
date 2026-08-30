import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Pencil, Search, Users, X } from 'lucide-react';
import { useState } from 'react';
import AdminNav from '@/components/admin-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatTimestamp } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/users';

type AdminUser = {
    ulid: string;
    name: string;
    username: string;
    email: string;
    role: string;
    roleLabel: string;
    department: string | null;
    createdAt: string;
};

type Props = {
    users: {
        data: AdminUser[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: { q: string; role: string | null };
    roles: { value: string; label: string }[];
    departments: string[];
};

const ROLE_STYLES: Record<string, string> = {
    member: 'bg-slate-100 text-slate-600 ring-slate-200',
    manager: 'bg-sky-50 text-sky-700 ring-sky-200',
    admin: 'bg-amber-50 text-amber-700 ring-amber-200',
};

function RoleRow({
    user,
    roles,
    departments,
    onDone,
}: {
    user: AdminUser;
    roles: { value: string; label: string }[];
    departments: string[];
    onDone: () => void;
}) {
    const form = useForm({
        role: user.role,
        department: user.department ?? '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update(user.ulid).url, { onSuccess: onDone });
            }}
            className="flex flex-wrap items-start gap-2"
        >
            <div>
                <select
                    value={form.data.role}
                    onChange={(event) =>
                        form.setData('role', event.target.value)
                    }
                    className="h-9 rounded-md border border-slate-200 bg-white px-2 text-sm shadow-xs"
                >
                    {roles.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.role} />
            </div>

            <div>
                <Input
                    list="admin-departments"
                    value={form.data.department}
                    onChange={(event) =>
                        form.setData('department', event.target.value)
                    }
                    placeholder="所属"
                    className="h-9 w-40 text-sm"
                />
                <InputError message={form.errors.department} />
            </div>

            <datalist id="admin-departments">
                {departments.map((department) => (
                    <option key={department} value={department} />
                ))}
            </datalist>

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

export default function AdminUsers({
    users,
    filters,
    roles,
    departments,
}: Props) {
    const [search, setSearch] = useState(filters.q);
    const [editing, setEditing] = useState<string | null>(null);

    const go = (params: { q?: string; role?: string | null; page?: number }) =>
        router.get(
            index().url,
            {
                q: params.q ?? search,
                role: params.role === undefined ? filters.role : params.role,
                page: params.page,
            },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="ユーザー" />

            <AdminNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <Users className="size-5 text-slate-400" />
                    ユーザー
                </h1>
                <p className="text-sm text-slate-500">
                    権限と所属をここで変更します。`carrot:promote` と同じ 2
                    列を書きます。
                </p>
                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                    {users.total} 名
                </span>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        go({ page: 1 });
                    }}
                    className="relative"
                >
                    <Search className="absolute top-2.5 left-2.5 size-4 text-slate-400" />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="名前・ログインID・メール・所属"
                        className="h-9 w-72 pl-8 text-sm"
                    />
                </form>

                <div className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
                    {[{ value: null, label: 'すべて' }, ...roles].map(
                        (role) => (
                            <button
                                key={role.value ?? 'all'}
                                type="button"
                                onClick={() =>
                                    go({ role: role.value, page: 1 })
                                }
                                className={cn(
                                    'rounded-md px-3 py-1 text-xs font-medium transition',
                                    (filters.role ?? null) === role.value
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800',
                                )}
                            >
                                {role.label}
                            </button>
                        ),
                    )}
                </div>
            </div>

            <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs text-slate-500">
                        <tr>
                            <th className="px-4 py-2 font-semibold">利用者</th>
                            <th className="px-4 py-2 font-semibold">権限</th>
                            <th className="px-4 py-2 font-semibold">所属</th>
                            <th className="px-4 py-2 font-semibold">登録</th>
                            <th className="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {users.data.map((user) => (
                            <tr key={user.ulid} className="align-top">
                                <td className="px-4 py-3">
                                    <span className="font-medium text-slate-800">
                                        {user.name}
                                    </span>
                                    <span className="ml-2 font-mono text-xs text-slate-500">
                                        {user.username}
                                    </span>
                                    <div className="text-xs text-slate-400">
                                        {user.email}
                                    </div>
                                </td>

                                {editing === user.ulid ? (
                                    <td className="px-4 py-3" colSpan={4}>
                                        <RoleRow
                                            user={user}
                                            roles={roles}
                                            departments={departments}
                                            onDone={() => setEditing(null)}
                                        />
                                    </td>
                                ) : (
                                    <>
                                        <td className="px-4 py-3">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                                                    ROLE_STYLES[user.role],
                                                )}
                                            >
                                                {user.roleLabel}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {user.department ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-slate-500 tabular-nums">
                                            {formatTimestamp(user.createdAt)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditing(user.ulid)
                                                }
                                            >
                                                <Pencil className="size-4" />
                                                変更
                                            </Button>
                                        </td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {users.lastPage > 1 && (
                <div className="mt-4 flex items-center justify-center gap-3 text-sm">
                    <Link
                        href={index({
                            query: {
                                q: filters.q,
                                role: filters.role,
                                page: users.currentPage - 1,
                            },
                        })}
                        preserveState
                        className={cn(
                            'rounded-md px-3 py-1',
                            users.currentPage === 1
                                ? 'pointer-events-none text-slate-300'
                                : 'text-sky-700 hover:underline',
                        )}
                    >
                        前へ
                    </Link>
                    <span className="text-xs text-slate-500 tabular-nums">
                        {users.currentPage} / {users.lastPage}
                    </span>
                    <Link
                        href={index({
                            query: {
                                q: filters.q,
                                role: filters.role,
                                page: users.currentPage + 1,
                            },
                        })}
                        preserveState
                        className={cn(
                            'rounded-md px-3 py-1',
                            users.currentPage === users.lastPage
                                ? 'pointer-events-none text-slate-300'
                                : 'text-sky-700 hover:underline',
                        )}
                    >
                        次へ
                    </Link>
                </div>
            )}
        </>
    );
}
