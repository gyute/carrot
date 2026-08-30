import type { Auth } from '@/types/auth';
import type { NotificationItem } from '@/types/inbox';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash: { status: string | null };
            features: {
                submissions: boolean;
                maySubmit: boolean;
                requests: boolean;
            };
            pendingApprovals: number;
            openRequests: number;
            notifications: { unread: number; recent: NotificationItem[] };
            [key: string]: unknown;
        };
    }
}
