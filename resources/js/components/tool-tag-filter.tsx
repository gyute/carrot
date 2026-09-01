import { Filter, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type TagOption = {
    value: string;
    /** What the box says. Status values are stored in English. */
    label: string;
    count: number;
};

export type TagGroup = {
    key: string;
    label: string;
    options: TagOption[];
};

type Props = {
    groups: TagGroup[];
    /** Checked values per group key. A group missing from the map is unfiltered. */
    selected: Record<string, string[]>;
    onToggle: (groupKey: string, value: string) => void;
    onClear: () => void;
};

/**
 * Search-and-check filter for the tool catalog. The panel is hand-rolled rather
 * than built on a menu primitive because the search box has to keep focus while
 * boxes are ticked, which menu typeahead would fight over.
 */
export default function ToolTagFilter({
    groups,
    selected,
    onToggle,
    onClear,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const selectedCount = Object.values(selected).reduce(
        (total, values) => total + values.length,
        0,
    );

    const needle = query.trim().toLowerCase();
    const matches = groups
        .map((group) => ({
            ...group,
            options: group.options.filter((option) =>
                option.label.toLowerCase().includes(needle),
            ),
        }))
        .filter((group) => group.options.length > 0);

    useEffect(() => {
        if (!open) {
            return;
        }

        searchRef.current?.focus();

        const onPointerDown = (event: PointerEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-haspopup="dialog"
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium shadow-sm transition',
                    open || selectedCount > 0
                        ? 'border-sky-300 bg-sky-50 text-sky-800'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900',
                )}
            >
                <Filter className="size-4" />
                タグで絞り込み
                {selectedCount > 0 && (
                    <span className="ml-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-sky-600 px-1.5 text-xs font-bold text-white tabular-nums">
                        {selectedCount}
                    </span>
                )}
            </button>

            {open && (
                <div
                    role="dialog"
                    aria-label="タグで絞り込み"
                    className="absolute right-0 z-20 mt-2 w-64 origin-top-right rounded-xl border border-slate-200 bg-white shadow-xl"
                >
                    <div className="relative border-b border-slate-100 p-2">
                        <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            ref={searchRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="タグを検索"
                            aria-label="タグを検索"
                            className="h-8 border-slate-200 pl-8 text-sm"
                        />
                    </div>

                    <div className="max-h-64 overflow-y-auto p-2">
                        {matches.length === 0 ? (
                            <p className="px-1 py-6 text-center text-xs text-slate-400">
                                該当するタグがありません
                            </p>
                        ) : (
                            matches.map((group) => (
                                <div key={group.key} className="mb-2 last:mb-0">
                                    <p className="px-1 py-1 text-xs font-semibold text-slate-400">
                                        {group.label}
                                    </p>

                                    {group.options.map((option) => {
                                        const checked = (
                                            selected[group.key] ?? []
                                        ).includes(option.value);

                                        return (
                                            <label
                                                key={option.value}
                                                className="flex cursor-pointer items-center gap-2 rounded-md px-1 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50"
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    onCheckedChange={() =>
                                                        onToggle(
                                                            group.key,
                                                            option.value,
                                                        )
                                                    }
                                                />
                                                <span className="grow truncate">
                                                    {option.label}
                                                </span>
                                                <span className="text-xs text-slate-400 tabular-nums">
                                                    {option.count}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            ))
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t border-slate-100 px-3 py-2 text-xs">
                        <span className="text-slate-500">
                            {selectedCount > 0
                                ? `${selectedCount} 件選択中`
                                : '未選択'}
                        </span>
                        <button
                            type="button"
                            onClick={onClear}
                            disabled={selectedCount === 0}
                            className="inline-flex items-center gap-1 font-medium text-slate-500 transition hover:text-slate-900 disabled:pointer-events-none disabled:opacity-40"
                        >
                            <X className="size-3" />
                            クリア
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
