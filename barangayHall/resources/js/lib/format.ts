/**
 * Display formatting. All dates are shown in Philippine time and in a
 * format BHWs read easily ("Oct 30, 2026").
 */

const TIME_ZONE = 'Asia/Manila';
const LOCALE = 'en-PH';

/** Parse "YYYY-MM-DD" as a calendar date (no timezone shift). */
export function parseDateOnly(value: string): Date {
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);

    return new Date(y, m - 1, d);
}

/** "YYYY-MM-DD" for a local Date. */
export function toDateOnly(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

export function formatDate(
    value: string | null | undefined,
    style: 'medium' | 'long' = 'medium',
): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(LOCALE, {
        year: 'numeric',
        month: style === 'long' ? 'long' : 'short',
        day: 'numeric',
    }).format(parseDateOnly(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(LOCALE, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: TIME_ZONE,
    }).format(new Date(value));
}

/** "3 hours ago", "yesterday", "in 2 days". */
export function formatRelative(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const diffSeconds = (new Date(value).getTime() - Date.now()) / 1000;
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

    for (const [unit, seconds] of units) {
        if (Math.abs(diffSeconds) >= seconds) {
            return rtf.format(Math.round(diffSeconds / seconds), unit);
        }
    }

    return 'just now';
}

/** Whole months between a birthdate and today. */
export function ageInMonths(birthdate: string): number {
    const born = parseDateOnly(birthdate);
    const now = new Date();
    let months =
        (now.getFullYear() - born.getFullYear()) * 12 +
        (now.getMonth() - born.getMonth());

    if (now.getDate() < born.getDate()) {
        months--;
    }

    return Math.max(months, 0);
}

/** Babies are described in weeks/months, everyone else in years. */
export function formatAge(age: number, birthdate?: string): string {
    if (birthdate && age < 2) {
        const months = ageInMonths(birthdate);

        if (months < 1) {
            const days = Math.floor(
                (Date.now() - parseDateOnly(birthdate).getTime()) / 86_400_000,
            );

            return `${Math.max(Math.floor(days / 7), 0)} wk`;
        }

        return `${months} mo`;
    }

    return `${age} yr${age === 1 ? '' : 's'}`;
}

export function formatNumber(value: number | null | undefined): string {
    return value === null || value === undefined
        ? '—'
        : new Intl.NumberFormat(LOCALE).format(value);
}

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

/** Old/new values in the audit log: readable for any JSON value. */
export function formatAuditValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value as string | number);
}
