import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Inbox, MailOpen } from 'lucide-react';
import LiveUpdates from '@/components/live-updates';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, readAll, show } from '@/routes/inbox';
import type { MessageSummary, Paginated } from '@/types/inbox';

type Props = {
    messages: Paginated<MessageSummary>;
    unreadOnly: boolean;
    unreadCount: number;
};

export default function InboxIndex({
    messages,
    unreadOnly,
    unreadCount,
}: Props) {
    return (
        <>
            <LiveUpdates only={['messages', 'unreadCount', 'notifications']} />
            <Head title="メッセージ" />

            <div className="flex flex-wrap items-end gap-x-4 gap-y-2">
                <h1 className="flex items-center gap-2 text-xl font-bold text-slate-800">
                    <Inbox className="size-5 text-slate-400" />
                    メッセージ
                </h1>
                <span className="text-xs text-slate-500 tabular-nums">
                    未読 {unreadCount} 件
                </span>

                <div className="ml-auto flex items-center gap-2">
                    <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1 text-sm">
                        {[
                            {
                                label: 'すべて',
                                href: index().url,
                                active: !unreadOnly,
                            },
                            {
                                label: '未読',
                                href: index({ query: { unread: 1 } }).url,
                                active: unreadOnly,
                            },
                        ].map((tab) => (
                            <Link
                                key={tab.label}
                                href={tab.href}
                                className={cn(
                                    'rounded-md px-3 py-1 font-medium transition',
                                    tab.active
                                        ? 'bg-white text-slate-900 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800',
                                )}
                            >
                                {tab.label}
                            </Link>
                        ))}
                    </nav>
                    <button
                        type="button"
                        disabled={unreadCount === 0}
                        onClick={() => router.post(readAll().url)}
                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:text-slate-900 disabled:opacity-40"
                    >
                        <CheckCheck className="size-3.5" />
                        すべて既読
                    </button>
                </div>
            </div>

            {messages.data.length === 0 ? (
                <div className="mt-6 flex flex-col items-center rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center">
                    <MailOpen className="size-8 text-slate-300" />
                    <p className="mt-3 text-sm font-medium text-slate-600">
                        {unreadOnly
                            ? '未読のメッセージはありません'
                            : 'メッセージはありません'}
                    </p>
                </div>
            ) : (
                <ul className="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm">
                    {messages.data.map((message) => (
                        <li key={message.ulid}>
                            <Link
                                href={show(message.ulid)}
                                className={cn(
                                    'flex gap-4 px-5 py-3.5 transition hover:bg-slate-50',
                                    !message.read && 'bg-sky-50/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'mt-2 size-2 shrink-0 rounded-full',
                                        message.read
                                            ? 'bg-transparent'
                                            : 'bg-sky-500',
                                    )}
                                />
                                <span className="min-w-0 grow">
                                    <span
                                        className={cn(
                                            'block truncate text-sm',
                                            message.read
                                                ? 'text-slate-700'
                                                : 'font-semibold text-slate-900',
                                        )}
                                    >
                                        {message.subject}
                                    </span>
                                    <span className="mt-0.5 line-clamp-1 block text-xs text-slate-500">
                                        {message.body}
                                    </span>
                                </span>
                                <span className="shrink-0 text-right text-xs text-slate-400">
                                    <span className="block">
                                        {message.sender ?? 'システム'}
                                    </span>
                                    <span className="block tabular-nums">
                                        {formatDateTime(message.createdAt)}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {(messages.prev_page_url || messages.next_page_url) && (
                <div className="mt-4 flex justify-between text-sm">
                    {messages.prev_page_url ? (
                        <Link
                            href={messages.prev_page_url}
                            className="text-sky-700 hover:underline"
                        >
                            ← 新しいメッセージ
                        </Link>
                    ) : (
                        <span />
                    )}
                    {messages.next_page_url && (
                        <Link
                            href={messages.next_page_url}
                            className="text-sky-700 hover:underline"
                        >
                            古いメッセージ →
                        </Link>
                    )}
                </div>
            )}
        </>
    );
}
