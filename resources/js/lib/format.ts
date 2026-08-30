const dateTime = new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

/** "2026/08/27 10:05" for an ISO timestamp; an em dash for none. */
export function formatDateTime(iso: string | null | undefined): string {
    return iso ? dateTime.format(new Date(iso)) : '—';
}
