import { Form, Head, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    CalendarDays,
    ClipboardList,
    FileSignature,
    FolderClosed,
    LogOut,
    Mail,
    MessageSquareText,
    MessagesSquare,
    Settings,
    UserRound,
    Users,
    Video,
    Wrench,
} from 'lucide-react';
import type { ComponentType } from 'react';
import PortalLogo from '@/components/portal-logo';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

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
    {
        label: 'メッセージ',
        icon: MessagesSquare,
        className: 'from-emerald-400 to-green-600',
    },
    {
        label: 'ツール',
        icon: Wrench,
        className: 'from-amber-400 to-orange-500',
    },
    {
        label: 'Web会議',
        icon: Video,
        className: 'from-rose-400 to-rose-600',
    },
    {
        label: '設定',
        icon: Settings,
        className: 'from-slate-400 to-slate-600',
    },
];

/**
 * No notification source exists yet, so the bell shows no badge. Once one
 * lands, feed its unread count in here and the badge renders itself.
 */
const UNREAD_NOTIFICATIONS: number = 0;

export default function Home() {
    const { auth } = usePage().props;

    return (
        <div className="portal-surface min-h-svh bg-slate-100">
            <Head title="ポータルホーム" />

            <header className="bg-linear-to-r from-sky-700 to-blue-800">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-6 py-4">
                    <PortalLogo />

                    <div className="ml-auto flex items-center gap-3 text-sm text-white">
                        <button
                            type="button"
                            className="relative flex size-9 items-center justify-center rounded-full text-white transition hover:bg-white/15"
                            aria-label="お知らせ"
                            data-test="notification-bell"
                        >
                            <Bell className="size-5" />
                            {UNREAD_NOTIFICATIONS > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] leading-4.5 font-bold">
                                    {UNREAD_NOTIFICATIONS > 99
                                        ? '99+'
                                        : UNREAD_NOTIFICATIONS}
                                </span>
                            )}
                        </button>

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

                <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
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
            </main>
        </div>
    );
}
