import { Link, usePage } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { useEffect } from 'react';
import type { PropsWithChildren } from 'react';
import { toast } from 'sonner';
import NotificationBell from '@/components/notification-bell';
import PortalLogo from '@/components/portal-logo';
import PortalUserMenu from '@/components/portal-user-menu';
import { home } from '@/routes';
import { index as system } from '@/routes/admin/system';

/**
 * The signed in portal: the blue bar with the wordmark on every module screen.
 */
export default function PortalLayout({ children }: PropsWithChildren) {
    const { auth, flash } = usePage().props;

    // Redirect-with-status messages surface as a toast, once per response.
    useEffect(() => {
        if (flash?.status) {
            toast.success(flash.status);
        }
    }, [flash]);

    return (
        <div className="portal-surface min-h-svh bg-slate-100">
            <header className="bg-linear-to-r from-sky-700 to-blue-800">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-6 py-4">
                    <Link
                        href={home()}
                        className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    >
                        <PortalLogo />
                    </Link>

                    <div className="ml-auto flex items-center gap-3 text-sm text-white">
                        {auth.user.role === 'admin' && (
                            <Link
                                href={system()}
                                title="システム状態（管理者）"
                                className="flex size-9 items-center justify-center rounded-full text-white transition hover:bg-white/15"
                            >
                                <Activity className="size-5" />
                            </Link>
                        )}
                        <NotificationBell />

                        <PortalUserMenu user={auth.user} />
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>
        </div>
    );
}
