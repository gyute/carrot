import { Form, Link, usePage } from '@inertiajs/react';
import { Bell, LogOut, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import PortalLogo from '@/components/portal-logo';
import { Button } from '@/components/ui/button';
import { home, logout } from '@/routes';

/**
 * No notification source exists yet, so the bell shows no badge. Once one
 * lands, feed its unread count in here and the badge renders itself.
 */
const UNREAD_NOTIFICATIONS: number = 0;

/**
 * The signed in portal: the blue bar with the wordmark on every module screen.
 */
export default function PortalLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props;

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
                        <button
                            type="button"
                            className="relative flex size-9 items-center justify-center rounded-full text-white transition hover:bg-white/15"
                            aria-label="お知らせ"
                            data-test="notification-bell"
                        >
                            <Bell className="size-5" />
                            {UNREAD_NOTIFICATIONS > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] leading-4.5 font-bold">
                                    {UNREAD_NOTIFICATIONS > 99
                                        ? '99+'
                                        : UNREAD_NOTIFICATIONS}
                                </span>
                            )}
                        </button>

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
