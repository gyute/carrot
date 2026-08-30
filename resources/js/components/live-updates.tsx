import { router, usePage, usePoll } from '@inertiajs/react';
import {
    echoIsConfigured,
    useEcho,
    useEchoNotification,
} from '@laravel/echo-react';

type Props = {
    /** The page props to reload when something happens. */
    only: string[];
    pollMs?: number;
};

function Socket({ userId, only }: { userId: number; only: string[] }) {
    const channel = `App.Models.User.${userId}`;
    const key = only.join(',');
    const reload = () => router.reload({ only: key.split(',') });

    useEchoNotification(channel, reload, undefined, [key]);
    useEcho(channel, ['MessageReceived', 'ToolRunUpdated'], reload, [key]);

    return null;
}

/**
 * Keeps the given props fresh for the signed-in user. Reverb pushes an event
 * the moment something lands in their inbox; a slow poll backs that up for
 * tabs whose socket dropped, and carries dev boxes without Reverb at all.
 * Renders nothing. The socket lives in a child so its hooks only mount when
 * Echo is configured - they throw otherwise.
 */
export default function LiveUpdates({ only, pollMs = 60_000 }: Props) {
    const { auth } = usePage().props;
    const userId = auth?.user?.id;

    usePoll(pollMs, { only }, { keepAlive: true });

    return echoIsConfigured() && userId ? (
        <Socket userId={userId} only={only} />
    ) : null;
}
