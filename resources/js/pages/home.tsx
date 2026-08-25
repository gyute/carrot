import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    FileSignature,
    FolderClosed,
    LogOut,
    Mail,
    MessageSquareText,
    UserRound,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import PortalLogo from '@/components/portal-logo';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

type Module = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    className: string;
};

const MODULES: Module[] = [
    { label: 'メール', icon: Mail, className: 'from-sky-400 to-sky-600' },
    {
        label: 'ワークフロー',
        icon: FileSignature,
        className: 'from-sky-500 to-blue-600',
    },
    {
        label: '掲示板',
        icon: MessageSquareText,
        className: 'from-blue-500 to-indigo-600',
    },
    {
        label: 'スケジュール',
        icon: CalendarDays,
        className: 'from-indigo-500 to-indigo-700',
    },
    {
        label: '勤怠管理',
        icon: ClipboardList,
        className: 'from-indigo-500 to-violet-600',
    },
    {
        label: 'プロジェクト',
        icon: BarChart3,
        className: 'from-violet-500 to-purple-700',
    },
    {
        label: 'ファイル',
        icon: FolderClosed,
        className: 'from-cyan-500 to-sky-600',
    },
    {
        label: 'アドレス帳',
        icon: Users,
        className: 'from-teal-400 to-cyan-600',
    },
];

export default function Home() {
    const { auth } = usePage().props;

    return (
        <div className="portal-surface min-h-svh bg-slate-100">
            <Head title="ポータルホーム" />

            <header className="bg-linear-to-r from-sky-700 to-blue-800">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-6 py-4">
                    <PortalLogo />

                    <div className="ml-auto flex items-center gap-3 text-sm text-white">
                        <span className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-full bg-white/20">
                                <UserRound className="size-4" />
                            </span>
                            <span>
                                {auth.user.name}（{auth.user.username}）さん
                            </span>
                        </span>

                        <Form {...logout.form()}>
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                className="text-white hover:bg-white/15 hover:text-white"
                                data-test="logout-button"
                            >
                                <LogOut className="size-4" />
                                ログアウト
                            </Button>
                        </Form>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-10">
                <h1 className="text-xl font-bold text-slate-800">
                    こんにちは、{auth.user.name} さん
                </h1>
                <p className="mt-1 text-sm text-slate-500">
                    CARROT にログインしました。
                </p>

                <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
                    {MODULES.map(({ label, icon: Icon, className }) => (
                        <div
                            key={label}
                            className={`flex aspect-square flex-col items-center justify-center gap-3 rounded-md bg-linear-to-b ${className} text-white shadow-md`}
                        >
                            <Icon className="size-8" />
                            <span className="text-sm font-bold">{label}</span>
                        </div>
                    ))}
                </div>

                <div className="mt-8 rounded-md border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p className="text-sm font-medium text-slate-600">
                        業務モジュールは準備中です。
                    </p>
                    <p className="mt-2 text-sm text-slate-400">
                        現在はログイン・新規登録と{' '}
                        <Link
                            href={editProfile()}
                            className="font-medium text-sky-700 underline decoration-sky-300 underline-offset-4"
                        >
                            アカウント設定
                        </Link>
                        のみ利用できます。
                    </p>
                </div>
            </main>
        </div>
    );
}
