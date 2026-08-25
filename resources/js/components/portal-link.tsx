import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

/**
 * Inline text link styled for the light cards used across the portal gate.
 */
export default function PortalLink({ className, children, ...props }: Props) {
    return (
        <Link
            className={cn(
                'font-medium text-sky-700 underline decoration-sky-300 underline-offset-4 transition-colors hover:text-sky-900 hover:decoration-sky-600',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
