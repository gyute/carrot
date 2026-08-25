import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    tone?: 'light' | 'dark';
};

/**
 * The wordmark for the sample portal.
 */
export default function PortalLogo({ className, tone = 'light' }: Props) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            <PortalLogoMark
                className={cn(
                    'size-9 shrink-0',
                    tone === 'light' ? 'text-white' : 'text-sky-700',
                )}
            />
            <span
                className={cn(
                    'text-2xl font-extrabold tracking-[0.14em]',
                    tone === 'light' ? 'text-white' : 'text-slate-800',
                )}
            >
                CARROT
            </span>
        </div>
    );
}

/**
 * An original mark drawn for this project: a carrot in a rounded tile.
 */
export function PortalLogoMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            aria-hidden="true"
            className={className}
        >
            <rect
                x="2"
                y="2"
                width="28"
                height="28"
                rx="7"
                className="fill-current opacity-20"
            />
            <path
                d="M10.6 13.4h10.8l-4.3 12.1a1.2 1.2 0 0 1-2.2 0l-4.3-12.1Z"
                className="fill-current"
            />
            <path
                d="M16 13.4 12.6 9.2M16 13.4l3.4-4.2"
                className="stroke-current"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </svg>
    );
}
