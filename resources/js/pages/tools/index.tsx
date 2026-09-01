import { Head, Link, useHttp } from '@inertiajs/react';
import { ArrowUpRight, SearchX, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import ToolIcon from '@/components/tool-icon';
import type { TagGroup } from '@/components/tool-tag-filter';
import ToolTagFilter from '@/components/tool-tag-filter';
import ToolsNav from '@/components/tools-nav';
import { STATUS_STYLES, toolAccent } from '@/lib/tool-presets';
import type { ToolStatus } from '@/lib/tool-presets';
import { cn } from '@/lib/utils';
import { save as saveFilters } from '@/routes/tools/filters';

type CatalogTool = {
    ulid: string;
    slug: string;
    kind: 'link' | 'embed' | 'script';
    name: string;
    summary: string;
    icon: string;
    accent: string;
    status: ToolStatus;
    statusLabel: string;
    /** Null when the card leads nowhere: deprecated, or a kind with no screen yet. */
    href: string | null;
    /** Tag values per filter group; a group the tool has no value for is empty. */
    tags: Record<string, string[]>;
};

type Props = {
    tools: CatalogTool[];
    tagGroups: TagGroup[];
    /** What this person keeps as their default, or null if they never saved one. */
    savedFilters: Record<string, string[]> | null;
};

/** Long enough that ticking several boxes in a row is one request. */
const SAVE_DELAY_MS = 600;

/** Two selections are the same filter whatever order the boxes were ticked in. */
function fingerprint(selected: Record<string, string[]>): string {
    return JSON.stringify(
        Object.entries(selected)
            .map(([key, values]) => [key, [...values].sort()] as const)
            .filter(([, values]) => values.length > 0)
            .sort(([a], [b]) => a.localeCompare(b)),
    );
}

export default function ToolsIndex({ tools, tagGroups, savedFilters }: Props) {
    const GROUP_LABELS = new Map(
        tagGroups.map(({ key, label }) => [key, label] as const),
    );
    const OPTION_LABELS = new Map(
        tagGroups.flatMap(({ key, options }) =>
            options.map(
                (option) => [`${key}/${option.value}`, option.label] as const,
            ),
        ),
    );

    // Deprecated tools stay in the catalog for reference, so the status filter
    // opens with every other status ticked rather than hiding them by a rule
    // the screen never shows. What is ticked is the whole truth.
    const builtInDefault = useMemo(
        () => ({
            status: (
                tagGroups.find(({ key }) => key === 'status')?.options ?? []
            )
                .map(({ value }) => value)
                .filter((value) => value !== 'deprecated'),
        }),
        [tagGroups],
    );

    // The request's own data is the selection, so a save always sends what is
    // on screen - no second copy of the state to keep in step.
    const saver = useHttp<{ filters: Record<string, string[]> }>({
        filters: savedFilters ?? builtInDefault,
    });
    const selected = saver.data.filters;

    const setSelected = (
        update: (current: Record<string, string[]>) => Record<string, string[]>,
    ) => saver.setData('filters', update(saver.data.filters));

    // Saved on a delay so ticking three boxes is one request, and never on the
    // first render: arriving at the page is not a choice to save anything.
    const settled = fingerprint(selected);
    const lastSaved = useRef(fingerprint(savedFilters ?? builtInDefault));
    const flush = useRef(() => {});
    const [saveFailed, setSaveFailed] = useState(false);

    useEffect(() => {
        flush.current = () => {
            if (settled === lastSaved.current) {
                return;
            }

            lastSaved.current = settled;

            // A save that works says nothing: the boxes already show what the
            // catalog will open with. Only a broken one is worth a line.
            //
            // Every failure has to go through a callback. A 422 does not
            // reject - useHttp fills in `errors` and resolves - so a promise
            // chain alone would report a rejected filter as saved.
            const failed = () => {
                setSaveFailed(true);
                // Nothing is stored, so let the next change try again even if
                // it lands back on the selection that just failed.
                lastSaved.current = '';
            };

            saver
                .put(saveFilters().url, {
                    onSuccess: () => setSaveFailed(false),
                    onError: failed,
                    onHttpException: failed,
                    onNetworkError: failed,
                })
                // Those last two report and then rethrow; they have been
                // handled, so keep the rejection from going unhandled.
                .catch(() => {});
        };
    });

    useEffect(() => {
        const timer = window.setTimeout(() => flush.current(), SAVE_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [settled]);

    // Ticking a box and opening a tool straight after is well inside the delay,
    // and that tick must not be the one that gets lost.
    useEffect(() => () => flush.current(), []);

    const toggleTag = (groupKey: string, value: string) => {
        setSelected((current) => {
            const values = current[groupKey] ?? [];
            const next = values.includes(value)
                ? values.filter((entry) => entry !== value)
                : [...values, value];

            return { ...current, [groupKey]: next };
        });
    };

    const clearTags = () => setSelected(() => ({}));

    // A group with nothing ticked is ignored; within a group the ticks are OR,
    // and a tool has to clear every filtered group.
    const visibleTools = tools.filter((tool) =>
        tagGroups.every(({ key }) => {
            const values = selected[key] ?? [];

            return (
                values.length === 0 ||
                (tool.tags[key] ?? []).some((value) => values.includes(value))
            );
        }),
    );

    const activeTags = tagGroups.flatMap(({ key }) =>
        (selected[key] ?? []).map((value) => ({
            groupKey: key,
            value,
            label: OPTION_LABELS.get(`${key}/${value}`) ?? value,
        })),
    );

    return (
        <>
            <Head title="ツール" />

            {/*
             * Finding a tool is what this screen is for, so the filter is the
             * only action on it. Registering one lives on the 登録 tab, next
             * to the requests it produces.
             */}
            <ToolsNav
                actions={
                    <ToolTagFilter
                        groups={tagGroups}
                        selected={selected}
                        onToggle={toggleTag}
                        onClear={clearTags}
                        saveFailed={saveFailed}
                    />
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
                        : `${tools.length} 件`}
                </span>
            </div>

            {activeTags.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    {activeTags.map(({ groupKey, value, label }) => (
                        <button
                            key={`${groupKey}/${value}`}
                            type="button"
                            onClick={() => toggleTag(groupKey, value)}
                            title={`${GROUP_LABELS.get(groupKey)}「${label}」を解除`}
                            className="inline-flex items-center gap-1 rounded-full bg-sky-50 py-0.5 pr-1.5 pl-2.5 text-xs font-medium text-sky-800 ring-1 ring-sky-200 transition hover:bg-sky-100"
                        >
                            {label}
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
                                        {tool.statusLabel}
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
