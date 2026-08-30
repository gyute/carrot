import { Head, Link } from '@inertiajs/react';
import { AppWindow, ArrowUpRight } from 'lucide-react';
import type { ComponentType } from 'react';
import ToolsNav from '@/components/tools-nav';
import { studio } from '@/routes/tools';

type Tool = {
    name: string;
    summary: string;
    category: string;
    owner: string;
    icon: ComponentType<{ className?: string }>;
    accent: string;
    href: string;
};

const TOOLS: Tool[] = [
    {
        name: 'スタジオ',
        summary: '許可された外部ページをポータル内にそのまま表示します。',
        category: '外部連携',
        owner: '情報システム部',
        icon: AppWindow,
        accent: 'from-sky-400 to-blue-600',
        href: studio().url,
    },
];

export default function ToolsIndex() {
    return (
        <>
            <Head title="ツール" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">ツール</h1>
                <p className="text-sm text-slate-500">
                    社内で使われている業務ツールをここに集約します。
                </p>
                <span className="ml-auto text-xs text-slate-400">
                    {TOOLS.length} 件
                </span>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {TOOLS.map(
                    ({
                        name,
                        summary,
                        category,
                        owner,
                        icon: Icon,
                        accent,
                        href,
                    }) => (
                        <Link
                            key={name}
                            href={href}
                            className="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm ring-sky-500/0 transition duration-200 hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:ring-2 hover:ring-sky-500/30"
                        >
                            <span
                                className={`absolute inset-x-0 top-0 h-1 bg-linear-to-r ${accent} opacity-0 transition group-hover:opacity-100`}
                            />

                            <span
                                className={`flex size-12 items-center justify-center rounded-xl bg-linear-to-br ${accent} text-white shadow-sm transition duration-200 group-hover:scale-105`}
                            >
                                <Icon className="size-6" />
                            </span>

                            <span className="mt-5 flex items-center gap-2 text-base font-bold text-slate-800">
                                {name}
                                <ArrowUpRight className="size-4 text-slate-300 transition duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-sky-600" />
                            </span>

                            <span className="mt-2 grow text-sm leading-relaxed text-slate-600">
                                {summary}
                            </span>

                            <span className="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4 text-xs">
                                <span className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">
                                    {category}
                                </span>
                                <span className="text-slate-400">{owner}</span>
                            </span>
                        </Link>
                    ),
                )}
            </div>
        </>
    );
}
