import {
    AppWindow,
    BookOpen,
    Database,
    FileText,
    Link as LinkIcon,
    ScrollText,
    Terminal,
    Wrench,
} from 'lucide-react';
import type { ComponentType } from 'react';

type Icon = ComponentType<{ className?: string }>;

/**
 * Icons a tool may pick, keyed by the name stored on the row. The set is a
 * fixed allowlist rather than every lucide icon so the bundle stays small and
 * a typo on a row can never break the catalog.
 */
export const TOOL_ICONS: Record<string, Icon> = {
    'app-window': AppWindow,
    'book-open': BookOpen,
    database: Database,
    'file-text': FileText,
    link: LinkIcon,
    'scroll-text': ScrollText,
    terminal: Terminal,
    wrench: Wrench,
};

export function toolIcon(name: string): Icon {
    return TOOL_ICONS[name] ?? Wrench;
}

/** Gradient presets for the card badge, keyed by the accent stored on the row. */
export const TOOL_ACCENTS: Record<string, string> = {
    amber: 'from-amber-400 to-orange-500',
    sky: 'from-sky-400 to-blue-600',
    emerald: 'from-emerald-400 to-teal-600',
    violet: 'from-violet-400 to-purple-600',
    rose: 'from-rose-400 to-pink-600',
    slate: 'from-slate-400 to-slate-600',
};

export function toolAccent(name: string): string {
    return TOOL_ACCENTS[name] ?? TOOL_ACCENTS.slate;
}

export type ToolStatus = 'running' | 'pending' | 'deprecated';

export type ToolKind = 'link' | 'embed' | 'script';

export const KIND_LABELS: Record<ToolKind, string> = {
    link: 'リンク',
    embed: '埋め込み',
    script: 'スクリプト',
};

/** Status pill styling. Values are shown in English, as stored. */
export const STATUS_STYLES: Record<ToolStatus, string> = {
    running: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    deprecated: 'bg-slate-100 text-slate-500 ring-slate-200',
};

export type SubmissionStatus =
    'draft' | 'pending' | 'endorsed' | 'approved' | 'rejected' | 'withdrawn';

export const SUBMISSION_STATUS_STYLES: Record<SubmissionStatus, string> = {
    draft: 'bg-slate-100 text-slate-600 ring-slate-200',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    endorsed: 'bg-sky-50 text-sky-700 ring-sky-200',
    approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
    withdrawn: 'bg-slate-100 text-slate-500 ring-slate-200',
};

export const NETWORK_LABELS: Record<'none' | 'internet', string> = {
    none: 'なし（完全遮断）',
    internet: 'インターネットあり',
};
