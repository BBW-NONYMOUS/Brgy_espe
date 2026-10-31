import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export type QueryValue = string | number | boolean | null | undefined;
export type QueryState = Record<string, QueryValue>;

type Options = {
    /** Only reload these props (Inertia partial reload). */
    only?: string[];
    /** Debounce for text search, in ms. */
    debounce?: number;
};

type VisitOptions = {
    /** Wait before visiting (used for typing in the search box). */
    debounced?: boolean;
    /** Go back to page 1 (true for every filter change). */
    resetPage?: boolean;
};

/**
 * Keeps table filters, sort and page in the URL query string and reloads
 * the page props from the server (preserveState + preserveScroll), so a
 * search can be bookmarked, refreshed or shared.
 *
 * Empty values ('', null, false) are dropped from the URL.
 */
export function useTableQuery<T extends QueryState>(
    initial: T,
    { only, debounce = 300 }: Options = {},
) {
    const page = usePage();
    const [query, setQuery] = useState<T>(initial);
    const [processing, setProcessing] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    // Latest query, read synchronously so rapid changes never use stale values.
    const latest = useRef<T>(initial);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const visit = useCallback(
        (next: Record<string, QueryValue>) => {
            const path = page.url.split('?')[0];
            const params = Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) =>
                        value !== '' &&
                        value !== null &&
                        value !== undefined &&
                        value !== false,
                ),
            ) as Record<string, string | number | boolean>;

            router.get(path, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            });
        },
        [page.url, only],
    );

    const update = useCallback(
        (
            changes: Partial<T> & { page?: number },
            { debounced = false, resetPage = true }: VisitOptions = {},
        ) => {
            const next = {
                ...latest.current,
                ...changes,
                ...(resetPage && !('page' in changes) ? { page: null } : {}),
            } as T;

            latest.current = next;
            setQuery(next);

            if (timer.current) {
                clearTimeout(timer.current);
            }

            if (debounced) {
                timer.current = setTimeout(() => visit(next), debounce);
            } else {
                visit(next);
            }
        },
        [visit, debounce],
    );

    const setSort = useCallback(
        (column: string) => {
            const same = query.sort === column;
            const direction =
                same && query.direction === 'asc' ? 'desc' : 'asc';

            update({ sort: column, direction } as unknown as Partial<T>);
        },
        [query.sort, query.direction, update],
    );

    const setPage = useCallback(
        (pageNumber: number) =>
            update({ page: pageNumber } as Partial<T> & { page: number }, {
                resetPage: false,
            }),
        [update],
    );

    const reset = useCallback(
        (defaults: Partial<T>) => update(defaults),
        [update],
    );

    return { query, update, setSort, setPage, reset, processing };
}
