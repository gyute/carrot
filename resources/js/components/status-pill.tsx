import { cn } from '@/lib/utils';

/**
 * The small ring-bordered status chip used on catalog cards, request lists
 * and detail headers. `styles` picks the colour set; `value` is shown as-is.
 */
export default function StatusPill({
    value,
    label,
    styles,
    className,
}: {
    value: string;
    label?: string;
    styles: Record<string, string>;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                styles[value] ?? 'bg-slate-100 text-slate-600 ring-slate-200',
                className,
            )}
        >
            {label ?? value}
        </span>
    );
}
