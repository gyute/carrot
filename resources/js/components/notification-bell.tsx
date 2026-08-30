import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, Inbox } from 'lucide-react';
import LiveUpdates from '@/components/live-updates';
import { usePopover } from '@/hooks/use-popover';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as inbox } from '@/routes/inbox';
import { read, readAll } from '@/routes/notifications';

/**
 * The bell in the portal header. Clicking it drops a panel of recent
 * notifications; picking one marks it read and goes where it points, which
 * for a request is the message that links to the approval screen.
 */
export default function NotificationBell() {
    const { notifications } = usePage().props;
    const { ref, open, toggle, setOpen } = usePopover<HTMLDivElement>();

    const openNotification = (id: string, url: string | null) => {
        setOpen(false);
        router.patch(
            read(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (url) {
                        router.visit(url);
                    }
                },
            },
        );
    };

    return (
        <div ref={ref} className="relative">
            <LiveUpdates only={['notifications', 'pendingApprovals']} />
            <button
                type="button"
                onClick={toggle}
                aria-expanded={open}
                aria-haspopup="dialog"
                aria-label={`お知らせ（未読 ${notifications.unread} 件）`}
                data-test="notification-bell"
                className={cn(
                    'relative flex size-9 items-center justify-center rounded-full text-white transition hover:bg-white/15',
                    open && 'bg-white/15',
                )}
            >
                <Bell className="size-5" />
                {notifications.unread > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[11px] leading-4.5 font-bold">
                        {notifications.unread > 99
                            ? '99+'
                            : notifications.unread}
                    </span>
                )}
            </button>

            {open && (
                <div
                    role="dialog"
                    aria-label="お知らせ"
                    className="absolute right-0 z-30 mt-2 w-80 origin-top-right rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl sm:w-96"
                >
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                        <span className="text-sm font-bold">お知らせ</span>
                        <button
                            type="button"
                            disabled={notifications.unread === 0}
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 transition hover:text-slate-900 disabled:pointer-events-none disabled:opacity-40"
                        >
                            <CheckCheck className="size-3.5" />
                            すべて既読
                        </button>
                    </div>

                    <ul className="max-h-96 overflow-y-auto">
                        {notifications.recent.length === 0 ? (
                            <li className="px-4 py-10 text-center text-xs text-slate-400">
                                お知らせはありません
                            </li>
                        ) : (
                            notifications.recent.map((item) => (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            openNotification(item.id, item.url)
                                        }
                                        className={cn(
                                            'flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50',
                                            !item.read && 'bg-sky-50/60',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                item.read
                                                    ? 'bg-transparent'
                                                    : 'bg-sky-500',
                                            )}
                                        />
                                        <span className="min-w-0 grow">
                                            <span
                                                className={cn(
                                                    'block text-sm',
                                                    item.read
                                                        ? 'text-slate-700'
                                                        : 'font-semibold text-slate-900',
                                                )}
                                            >
                                                {item.title}
                                            </span>
                                            <span className="mt-0.5 line-clamp-2 block text-xs text-slate-500">
                                                {item.body}
                                            </span>
                                            <span className="mt-1 block text-[11px] text-slate-400 tabular-nums">
                                                {formatDateTime(item.createdAt)}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))
                        )}
                    </ul>

                    <div className="border-t border-slate-100 px-4 py-2.5">
                        <Link
                            href={inbox()}
                            onClick={() => setOpen(false)}
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-sky-700 hover:underline"
                        >
                            <Inbox className="size-3.5" />
                            すべてのメッセージを見る
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
