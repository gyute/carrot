import type {
    SubmissionStatus,
    ToolKind,
    ToolRequestPriority,
    ToolRequestStatus,
    ToolStatus,
} from '@/lib/tool-presets';

export type ToolInput = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select';
    required: boolean;
    options?: string[] | null;
};

/** kind-specific settings; only the fields for the tool's kind are present. */
export type ToolConfig = {
    url?: string;
    runtime?: 'php' | 'shell';
    timeout_sec?: number;
    memory_mb?: number;
    /** Script only: whether the sandbox gets an internet-facing network. */
    network?: 'none' | 'internet';
    inputs?: ToolInput[];
};

/** What a submission asks a tool to become. A change request carries config and source only. */
export type SubmissionPayload = {
    kind?: ToolKind;
    name?: string;
    summary?: string;
    description?: string | null;
    icon?: string;
    accent?: string;
    department?: string | null;
    categories?: string[];
    config?: ToolConfig;
    source?: string | null;
};

export type SubmissionAction = 'create' | 'update' | 'deprecate';

export type SubmissionSummary = {
    ulid: string;
    action: SubmissionAction;
    actionLabel: string;
    status: SubmissionStatus;
    statusLabel: string;
    name: string;
    kind: ToolKind | null;
    requester: string;
    department: string | null;
    tool: { ulid: string; name: string; slug: string } | null;
    note: string | null;
    endorser: string | null;
    endorseComment: string | null;
    endorsedAt: string | null;
    reviewer: string | null;
    reviewComment: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    createdAt: string;
};

export type SubmissionDetail = SubmissionSummary & {
    payload: SubmissionPayload;
    /** The tool as it is now, for comparing a change request against. */
    current: SubmissionPayload | null;
    runtimes: Record<string, string>;
};

export type FormLimits = {
    icons: string[];
    accents: string[];
    departments: string[];
    /** What each runtime actually is on this deployment, e.g. "PHP 8.3 (php:8.3-cli-alpine)". */
    runtimes: Record<string, string>;
    timeoutMax: number;
    memoryMax: number;
    sourceBytes: number;
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
    source: string | null;
    version: string | null;
    owner: string | null;
    requester: string | null;
    endorser: string | null;
    approver: string | null;
    publishedAt: string | null;
    deprecatedAt: string | null;
    pendingChange: boolean;
};

export type ToolRunSummary = {
    ulid: string;
    status: 'queued' | 'running' | 'completed' | 'failed' | 'timed_out';
    statusLabel: string;
    finished: boolean;
    runtime: string;
    runtimeLabel: string;
    inputs: Record<string, unknown>;
    exitCode: number | null;
    stdout: string | null;
    stderr: string | null;
    truncated: boolean;
    durationMs: number | null;
    errorMessage: string | null;
    requestedBy: string;
    createdAt: string;
    startedAt: string | null;
    finishedAt: string | null;
};

export type ToolRequestSummary = {
    ulid: string;
    status: ToolRequestStatus;
    statusLabel: string;
    title: string;
    requester: string;
    department: string | null;
    categories: string[];
    desiredKind: ToolKind | null;
    desiredKindLabel: string | null;
    neededBy: string | null;
    priority: ToolRequestPriority | null;
    priorityLabel: string | null;
    assignee: string | null;
    decider: string | null;
    decisionComment: string | null;
    decidedAt: string | null;
    createdAt: string;
    tool: { ulid: string; name: string } | null;
    duplicateOf: { ulid: string; title: string } | null;
};

export type ToolRequestDetail = ToolRequestSummary & {
    body: string;
};

export type ToolRequestLimits = {
    /** The requester's own department, which the request is stamped with. */
    department: string | null;
    departments: string[];
    kinds: { value: ToolKind; label: string }[];
};
