import { Link } from '@inertiajs/react';
import PortalBackdrop from '@/components/portal-backdrop';
import PortalLogo from '@/components/portal-logo';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

type Props = AuthLayoutProps & {
    /** Widen the card for the two column login form. */
    wide?: boolean;
};

/**
 * The front gate of the portal: a tiled blue curtain with a single frosted
 * card floating on top of it.
 */
export default function AuthPortalLayout({
    children,
    title,
    description,
    wide = false,
}: Props) {
    return (
        <div className="portal-surface relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-4 sm:p-6">
            <PortalBackdrop />

            <div
                className={cn(
                    'relative w-full',
                    wide ? 'max-w-3xl' : 'max-w-lg',
                )}
            >
                <Link
                    href={home()}
                    className="mb-4 inline-flex rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                >
                    <PortalLogo />
                </Link>

                <div className="rounded-sm bg-white/85 px-6 py-8 shadow-2xl shadow-blue-950/25 backdrop-blur-sm sm:px-12 sm:py-12">
                    <div className="mb-8 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <h1 className="text-4xl font-light text-sky-700">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-slate-500">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
