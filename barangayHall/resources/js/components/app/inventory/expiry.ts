import { parseDateOnly } from '@/lib/format';
import type { ExpiryBand } from '@/types/modules';

/**
 * Same rule as InventoryAlertService::bandFor() on the server: expired → 0,
 * then the smallest of 30 / 60 / 90 days it falls within, else null.
 */
export function expiryOf(
    expiryDate: string,
    today: Date = new Date(),
): { band: ExpiryBand; days: number } {
    const start = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    const days = Math.round(
        (parseDateOnly(expiryDate).getTime() - start.getTime()) / 86_400_000,
    );

    const band: ExpiryBand =
        days < 0
            ? 0
            : days <= 30
              ? 30
              : days <= 60
                ? 60
                : days <= 90
                  ? 90
                  : null;

    return { band, days };
}
