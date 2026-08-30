export type MessageSummary = {
    ulid: string;
    kind: string;
    subject: string;
    body: string;
    sender: string | null;
    actionUrl: string | null;
    actionLabel: string | null;
    read: boolean;
    createdAt: string;
};

export type NotificationItem = {
    id: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    createdAt: string | null;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
