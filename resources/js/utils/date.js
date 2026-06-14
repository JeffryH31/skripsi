const SHORT_OPTIONS = { day: 'numeric', month: 'short', year: 'numeric' };
const LONG_OPTIONS = { day: '2-digit', month: 'short', year: 'numeric' };
const DATETIME_OPTIONS = {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
};

export function formatDate(value, locale = 'en-US') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleDateString(locale, SHORT_OPTIONS);
}

export function formatDateLong(value, locale = 'en-GB') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleDateString(locale, LONG_OPTIONS);
}

/**
 * Convert any date value (ISO timestamp or date string) to a LOCAL
 * "YYYY-MM-DD" string suitable for <input type="date"> and for sending
 * date-only values to the backend.
 */
export function toLocalDateInput(value) {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export function formatDateTime(value, locale = 'id-ID') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString(locale, DATETIME_OPTIONS);
}
