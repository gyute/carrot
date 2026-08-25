import { Head, Link } from '@inertiajs/react';
import { AppWindow, ArrowRight, ScrollText } from 'lucide-react';
import type { ComponentType } from 'react';
import ToolsNav from '@/components/tools-nav';
import { studio } from '@/routes/tools';
import { create as createExport } from '@/routes/tools/exports';

type Tool = {
    name: string;
    summary: string;
    category: string;
    owner: string;
    icon: ComponentType<{ className?: string }>;
    href: string;
};

const TOOLS: Tool[] = [
    {
        name: '日次アクセスログ',
        summary: 'ポータルへのアクセス履歴を CSV で書き出します。',
        category: 'データ',
        owner: '情報システム部',
        icon: ScrollText,
        href: createExport().url,
    },
    {
        name: 'スタジオ',
        summary: '許可された外部ページをポータル内にそのまま表示します。',
        category: '外部連携',
        owner: '情報システム部',
        icon: AppWindow,
        href: studio().url,
    },
];

export default function ToolsIndex() {
    return (
        <>
            <Head title="ツール" />

            <ToolsNav />

            <h1 className="mt-6 text-xl font-bold text-slate-800">ツール</h1>
            <p className="mt-1 text-sm text-slate-500">
                社内で使われている業務ツールをここに集約します。
            </p>

            <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {TOOLS.map(
                    ({ name, summary, category, owner, icon: Icon, href }) => (
                        <Link
                            key={name}
                            href={href}
                            className="group flex flex-col rounded-md border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow-md"
                        >
                            <div className="flex items-center gap-3">
                                <span className="flex size-10 items-center justify-center rounded-md bg-linear-to-b from-amber-400 to-orange-500 text-white">
                                    <Icon className="size-5" />
                                </span>
                                <span className="font-bold text-slate-800">
                                    {name}
                                </span>
                                <ArrowRight className="ml-auto size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-600" />
                            </div>

                            <p className="mt-4 text-sm text-slate-600">
                                {summary}
                            </p>

                            <dl className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
                                <div className="flex gap-1">
                                    <dt>カテゴリ</dt>
                                    <dd className="text-slate-500">
                                        {category}
                                    </dd>
                                </div>
                                <div className="flex gap-1">
                                    <dt>担当</dt>
                                    <dd className="text-slate-500">{owner}</dd>
                                </div>
                            </dl>
                        </Link>
                    ),
                )}
            </div>
        </>
    );
}
