import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/inbox';
import type { MessageSummary } from '@/types/inbox';

export default function InboxShow({ message }: { message: MessageSummary }) {
    return (
        <>
            <Head title={message.subject} />

            <Link
                href={index()}
                className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-800"
            >
                <ArrowLeft className="size-3.5" />
                メッセージ一覧へ
            </Link>

            <article className="mt-3 rounded-xl border border-slate-200 bg-white shadow-sm">
                <header className="border-b border-slate-100 px-6 py-4">
                    <h1 className="text-lg font-bold text-slate-800">
                        {message.subject}
                    </h1>
                    <p className="mt-1 text-xs text-slate-500">
                        {message.sender ?? 'システム'} ·{' '}
                        <span className="tabular-nums">
                            {formatDateTime(message.createdAt)}
                        </span>
                    </p>
                </header>

                <div className="px-6 py-5 text-sm leading-relaxed whitespace-pre-wrap text-slate-700">
                    {message.body}
                </div>

                {message.actionUrl && (
                    <footer className="border-t border-slate-100 px-6 py-4">
                        <Button
                            asChild
                            className="bg-sky-700 text-white hover:bg-sky-800"
                        >
                            <Link href={message.actionUrl}>
                                {message.actionLabel ?? '開く'}
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </Button>
                    </footer>
                )}
            </article>
        </>
    );
}
