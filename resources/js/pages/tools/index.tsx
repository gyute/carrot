import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus, SearchX, X } from 'lucide-react';
import { useState } from 'react';
import ToolIcon from '@/components/tool-icon';
import type { TagGroup } from '@/components/tool-tag-filter';
import ToolTagFilter from '@/components/tool-tag-filter';
import ToolsNav from '@/components/tools-nav';
import { Button } from '@/components/ui/button';
import { STATUS_STYLES, toolAccent } from '@/lib/tool-presets';
import type { ToolStatus } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { create as createSubmission } from '@/routes/tools/submissions';

type CatalogTool = {
    ulid: string;
    slug: string;
    kind: 'link' | 'embed' | 'script';
    name: string;
    summary: string;
    icon: string;
    accent: string;
    status: ToolStatus;
    /** Null when the card leads nowhere: deprecated, or a kind with no screen yet. */
    href: string | null;
    /** Tag values per filter group; a group the tool has no value for is empty. */
    tags: Record<string, string[]>;
};

type Props = {
    tools: CatalogTool[];
    tagGroups: TagGroup[];
};

/**
 * Deprecated tools stay in the catalog for reference but are hidden until the
 * visitor asks for them by ticking the status.
 */
function isShownByDefault(tool: CatalogTool): boolean {
    return tool.status !== 'deprecated';
}

export default function ToolsIndex({ tools, tagGroups }: Props) {
    const { features } = usePage().props;
    const GROUP_LABELS = new Map(
        tagGroups.map(({ key, label }) => [key, label] as const),
    );
    const [selected, setSelected] = useState<Record<string, string[]>>({});

    const toggleTag = (groupKey: string, value: string) => {
        setSelected((current) => {
            const values = current[groupKey] ?? [];
            const next = values.includes(value)
                ? values.filter((entry) => entry !== value)
                : [...values, value];

            return { ...current, [groupKey]: next };
        });
    };

    const clearTags = () => setSelected({});

    // A group with nothing ticked is ignored; within a group the ticks are OR,
    // and a tool has to clear every filtered group. Deprecated tools only
    // appear once their status is ticked explicitly.
    const statusFiltered = (selected.status ?? []).length > 0;
    const visibleTools = tools.filter(
        (tool) =>
            (statusFiltered || isShownByDefault(tool)) &&
            tagGroups.every(({ key }) => {
                const values = selected[key] ?? [];

                return (
                    values.length === 0 ||
                    (tool.tags[key] ?? []).some((value) =>
                        values.includes(value),
                    )
                );
            }),
    );
    const defaultCount = tools.filter(isShownByDefault).length;

    const activeTags = tagGroups.flatMap(({ key }) =>
        (selected[key] ?? []).map((value) => ({ groupKey: key, value })),
    );

    return (
        <>
            <Head title="ツール" />

            <ToolsNav
                actions={
                    <div className="flex items-center gap-2">
                        <ToolTagFilter
                            groups={tagGroups}
                            selected={selected}
                            onToggle={toggleTag}
                            onClear={clearTags}
                        />
                        {features.maySubmit && (
                            <Button
                                asChild
                                size="sm"
                                className="bg-sky-700 text-white hover:bg-sky-800"
                            >
                                <Link href={createSubmission()}>
                                    <Plus className="size-4" />
                                    ツールを登録
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">ツール</h1>
                <p className="text-sm text-slate-500">
                    社内で使われている業務ツールをここに集約します。
                </p>
                <span className="ml-auto text-xs text-slate-400 tabular-nums">
                    {activeTags.length > 0
                        ? `${visibleTools.length} / ${tools.length} 件`
                        : `${defaultCount} 件`}
                </span>
            </div>

            {activeTags.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    {activeTags.map(({ groupKey, value }) => (
                        <button
                            key={`${groupKey}/${value}`}
                            type="button"
                            onClick={() => toggleTag(groupKey, value)}
                            title={`${GROUP_LABELS.get(groupKey)}「${value}」を解除`}
                            className="inline-flex items-center gap-1 rounded-full bg-sky-50 py-0.5 pr-1.5 pl-2.5 text-xs font-medium text-sky-800 ring-1 ring-sky-200 transition hover:bg-sky-100"
                        >
                            {value}
                            <X className="size-3 text-sky-500" />
                        </button>
                    ))}

                    <button
                        type="button"
                        onClick={clearTags}
                        className="text-xs font-medium text-slate-500 underline-offset-2 transition hover:text-slate-900 hover:underline"
                    >
                        すべて解除
                    </button>
                </div>
            )}

            {visibleTools.length === 0 ? (
                <div className="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center">
                    <SearchX className="size-8 text-slate-300" />
                    <p className="mt-3 text-sm font-medium text-slate-600">
                        条件に一致するツールがありません
                    </p>
                    <button
                        type="button"
                        onClick={clearTags}
                        className="mt-3 text-xs font-medium text-sky-700 underline-offset-2 transition hover:underline"
                    >
                        絞り込みを解除する
                    </button>
                </div>
            ) : (
                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {visibleTools.map((tool) => {
                        const accent = toolAccent(tool.accent);
                        const categories = tool.tags.category ?? [];
                        const department = tool.tags.department?.[0];
                        const body = (
                            <>
                                <span
                                    className={`absolute inset-x-0 top-0 h-1 bg-linear-to-r ${accent} opacity-0 transition group-hover:opacity-100`}
                                />

                                <span
                                    className={`flex size-12 items-center justify-center rounded-xl bg-linear-to-br ${accent} text-white shadow-sm transition duration-200 group-hover:scale-105`}
                                >
                                    <ToolIcon
                                        name={tool.icon}
                                        className="size-6"
                                    />
                                </span>

                                <span className="mt-5 flex items-center gap-2 text-base font-bold text-slate-800">
                                    {tool.name}
                                    {tool.href && (
                                        <ArrowUpRight className="size-4 text-slate-300 transition duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-sky-600" />
                                    )}
                                    <span
                                        className={cn(
                                            'ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                                            STATUS_STYLES[tool.status],
                                        )}
                                    >
                                        {tool.status}
                                    </span>
                                </span>

                                <span className="mt-2 grow text-sm leading-relaxed text-slate-600">
                                    {tool.summary}
                                </span>

                                <span className="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 text-xs">
                                    {categories.map((category) => (
                                        <span
                                            key={category}
                                            className="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600"
                                        >
                                            {category}
                                        </span>
                                    ))}
                                    {department && (
                                        <span className="text-slate-400">
                                            {department}
                                        </span>
                                    )}
                                </span>
                            </>
                        );
                        const cardClass =
                            'group relative flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-6 shadow-sm';

                        return tool.href ? (
                            <Link
                                key={tool.ulid}
                                href={tool.href}
                                className={cn(
                                    cardClass,
                                    'ring-sky-500/0 transition duration-200 hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:ring-2 hover:ring-sky-500/30',
                                )}
                            >
                                {body}
                            </Link>
                        ) : (
                            <div
                                key={tool.ulid}
                                aria-disabled="true"
                                className={cn(cardClass, 'opacity-70')}
                            >
                                {body}
                            </div>
                        );
                    })}
                </div>
            )}
        </>
    );
}
