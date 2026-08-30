import type { ToolKind, ToolStatus } from '@/lib/tool-presets';

/** kind-specific settings; only the fields for the tool's kind are present. */
export type ToolConfig = {
    url?: string;
};

export type ToolDetail = {
    ulid: string;
    slug: string;
    kind: ToolKind;
    name: string;
    summary: string;
    description: string | null;
    icon: string;
    accent: string;
    status: ToolStatus;
    href: string | null;
    department: string | null;
    categories: string[];
    config: ToolConfig;
    /** For an embed tool: the URL to frame, or null when it must not be framed. */
    embedUrl: string | null;
    version: string | null;
    owner: string | null;
    requester: string | null;
    approver: string | null;
    publishedAt: string | null;
    deprecatedAt: string | null;
};
