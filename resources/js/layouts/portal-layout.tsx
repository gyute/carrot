import { Form, Link, usePage } from '@inertiajs/react';
import { LogOut, UserRound } from 'lucide-react';
import { useEffect } from 'react';
import type { PropsWithChildren } from 'react';
import { toast } from 'sonner';
import NotificationBell from '@/components/notification-bell';
import PortalLogo from '@/components/portal-logo';
import { Button } from '@/components/ui/button';
import { home, logout } from '@/routes';

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
                        <NotificationBell />

                        <span className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-full bg-white/20">
                                <UserRound className="size-4" />
                            </span>
                            <span>
                                {auth.user.name}（{auth.user.username}）さん
                            </span>
                        </span>

                        <Form {...logout.form()}>
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                className="text-white hover:bg-white/15 hover:text-white"
                                data-test="logout-button"
                            >
                                <LogOut className="size-4" />
                                ログアウト
                            </Button>
                        </Form>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>
        </div>
    );
}
