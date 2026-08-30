import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ClipboardCheck,
    LayoutGrid,
    MessageSquarePlus,
    Play,
    Tags,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { cn } from '@/lib/utils';
import { index as approvals } from '@/routes/admin/approvals';
import { index as adminRequests } from '@/routes/admin/requests';
import { index as runs } from '@/routes/admin/runs';
import { index as system } from '@/routes/admin/system';
import { index as tags } from '@/routes/admin/tags';
import { index as adminTools } from '@/routes/admin/tools';
import { index as users } from '@/routes/admin/users';

type Tab = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    adminOnly: boolean;
    badge?: number;
};

/**
 * The menu for the admin section. A manager only reviews, so everything that
 * writes to a table other than tool_submissions is hidden from them - the
 * routes refuse it too, this just keeps the row honest.
 */
export default function AdminNav() {
    const { url, props } = usePage();
    const isAdmin = props.auth.user.role === 'admin';

    const { features } = props;

    const tabs: Tab[] = [
        ...(features.submissions
            ? [
                  {
                      label: '承認',
                      href: approvals().url,
                      icon: ClipboardCheck,
                      adminOnly: false,
                      badge: props.pendingApprovals,
                  },
              ]
            : []),
        ...(features.requests
            ? [
                  {
                      label: 'リクエスト',
                      href: adminRequests().url,
                      icon: MessageSquarePlus,
                      adminOnly: true,
                      badge: props.openRequests,
                  },
              ]
            : []),
        { label: 'ユーザー', href: users().url, icon: Users, adminOnly: true },
        {
            label: 'ツール',
            href: adminTools().url,
            icon: LayoutGrid,
            adminOnly: true,
        },
        { label: 'タグ', href: tags().url, icon: Tags, adminOnly: true },
        { label: '実行', href: runs().url, icon: Play, adminOnly: true },
        {
            label: 'システム',
            href: system().url,
            icon: Activity,
            adminOnly: true,
        },
    ].filter((tab) => isAdmin || !tab.adminOnly);

    return (
        <nav className="inline-flex gap-1 rounded-lg bg-slate-200/60 p-1">
            {tabs.map(({ label, href, icon: Icon, badge }) => {
                const active = url.startsWith(href);

                return (
                    <Link
                        key={href}
                        href={href}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-sm font-medium transition',
                            active
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-500 hover:text-slate-800',
                        )}
                    >
                        <Icon className="size-4" />
                        {label}
                        {badge !== undefined && badge > 0 && (
                            <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-bold text-white tabular-nums">
                                {badge}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}
