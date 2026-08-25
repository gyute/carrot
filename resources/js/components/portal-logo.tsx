import type { SVGAttributes } from 'react';
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
            <PortalLogoMark className="size-9 shrink-0" />
            {/*
             * Nudged down so the cap height centres on the carrot's body
             * rather than on the whole glyph: the leaves sit above the body
             * and pull the optical centre up.
             */}
            <span
                className={cn(
                    'translate-y-[2px] text-2xl font-extrabold tracking-[0.14em]',
                    tone === 'light' ? 'text-white' : 'text-slate-800',
                )}
            >
                CARROT
            </span>
        </div>
    );
}

/**
 * The carrot glyph, drawn to fit a 32x32 box around its optical center.
 */
function PortalCarrot() {
    return (
        <g transform="translate(16 16) scale(1.789) translate(-16 -17.72)">
            <path
                d="M16 14.6C19.9 12.7 22.84 13.95 22.3 15.67L16.48 26.12A0.55 0.55 0 0 1 15.52 26.12L9.7 15.67C9.16 13.95 12.1 12.7 16 14.6Z"
                fill="#fb923c"
            />
            <path
                d="M16 14.6C19.9 12.7 22.84 13.95 22.3 15.67L16.48 26.12A0.55 0.55 0 0 1 16 26.4Z"
                fill="#f97316"
            />
            <path
                d="M16 14.6 12.6 10.4M16 14.6l3.4-4.2"
                stroke="#4ade80"
                strokeWidth="2.7"
                strokeLinecap="round"
            />
        </g>
    );
}

/**
 * An original mark drawn for this project, shared with public/favicon.svg.
 */
export function PortalLogoMark(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
        >
            <PortalCarrot />
        </svg>
    );
}
