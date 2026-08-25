import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    FileSignature,
    FolderClosed,
    Mail,
    MessageSquareText,
    MessagesSquare,
    Settings,
    Users,
    Video,
    Wrench,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { index as tools } from '@/routes/tools';

type Module = {
    label: string;
    icon: ComponentType<{ className?: string }>;
    className: string;
    /** Modules without a screen of their own stay as plain tiles. */
    href?: string;
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
        href: tools().url,
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

const TILE =
    'flex aspect-square flex-col items-center justify-center gap-3 rounded-md bg-linear-to-b text-white shadow-md';

export default function Home() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="ポータルホーム" />

            <h1 className="text-xl font-bold text-slate-800">
                こんにちは、{auth.user.name} さん
            </h1>
            <p className="mt-1 text-sm text-slate-500">
                CARROT にログインしました。
            </p>

            <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                {MODULES.map(({ label, icon: Icon, className, href }) => {
                    const tile = (
                        <>
                            <Icon className="size-8" />
                            <span className="text-sm font-bold">{label}</span>
                        </>
                    );

                    return href ? (
                        <Link
                            key={label}
                            href={href}
                            className={`${TILE} ${className} transition hover:brightness-110`}
                        >
                            {tile}
                        </Link>
                    ) : (
                        <div key={label} className={`${TILE} ${className}`}>
                            {tile}
                        </div>
                    );
                })}
            </div>
        </>
    );
}
