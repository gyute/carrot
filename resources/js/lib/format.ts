const dateTime = new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
});

const timestamp = new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

/** "2026/08/27 10:05" for an ISO timestamp; an em dash for none. */
export function formatDateTime(iso: string | null | undefined): string {
    return iso ? dateTime.format(new Date(iso)) : '—';
}

/**
 * "2026/08/27 10:05:42" - the same instant to the second. Operational screens
 * use this one: on an admin, approval or run page the minute is not enough to
 * tell two events apart or to line a row up with the application log.
 */
export function formatTimestamp(iso: string | null | undefined): string {
    return iso ? timestamp.format(new Date(iso)) : '—';
}
