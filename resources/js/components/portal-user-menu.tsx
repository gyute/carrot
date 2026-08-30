import { Form, Link } from '@inertiajs/react';
import { LogOut, UserRound } from 'lucide-react';
import { usePopover } from '@/hooks/use-popover';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { User } from '@/types';

/**
 * The account menu in the portal header. One way in is enough: the settings
 * screens carry their own navigation, so password and two-factor live behind
 * the profile link rather than being listed again here.
 */
export default function PortalUserMenu({ user }: { user: User }) {
    const { ref, open, toggle, setOpen } = usePopover<HTMLDivElement>();

    const item =
        'flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50';

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={toggle}
                aria-expanded={open}
                aria-haspopup="menu"
                data-test="portal-user-menu"
                className={cn(
                    'flex items-center gap-2 rounded-full py-1 pr-3 pl-1 text-white transition hover:bg-white/15',
                    open && 'bg-white/15',
                )}
            >
                <span className="flex size-8 items-center justify-center rounded-full bg-white/20">
                    <UserRound className="size-4" />
                </span>
                <span className="text-sm">
                    {user.name}（{user.username}）さん
                </span>
            </button>

            {open && (
                <div
                    role="menu"
                    aria-label="アカウント"
                    className="absolute right-0 z-30 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl"
                >
                    <Link
                        href={editProfile()}
                        onClick={() => setOpen(false)}
                        className={item}
                    >
                        <UserRound className="size-4 text-slate-400" />
                        プロフィール
                    </Link>
                    <Form
                        {...logout.form()}
                        className="border-t border-slate-100"
                    >
                        <button
                            type="submit"
                            data-test="logout-button"
                            className={item}
                        >
                            <LogOut className="size-4 text-slate-400" />
                            ログアウト
                        </button>
                    </Form>
                </div>
            )}
        </div>
    );
}
