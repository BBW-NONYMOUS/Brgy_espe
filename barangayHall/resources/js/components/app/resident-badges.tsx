import {
    Accessibility,
    Archive,
    Baby,
    HandCoins,
    HeartPulse,
} from 'lucide-react';
import { StatusBadge } from '@/components/app/status-badge';
import type { Resident } from '@/types';

type Props = {
    resident: Pick<
        Resident,
        | 'is_senior'
        | 'is_pwd'
        | 'is_4ps'
        | 'is_pregnant'
        | 'status'
        | 'archived_at'
        | 'age'
    >;
    /** Hide the status badge for active residents (lists). */
    showActiveStatus?: boolean;
    className?: string;
};

/**
 * Flags shown next to a resident's name: Senior, PWD, 4Ps, Pregnant,
 * Infant and the record status (Moved, Deceased, Archived).
 */
export function ResidentBadges({
    resident,
    showActiveStatus = false,
    className,
}: Props) {
    const status = resident.status.value;

    return (
        <span className={className ?? 'flex flex-wrap gap-1'}>
            {resident.archived_at && (
                <StatusBadge tone="neutral" icon={<Archive aria-hidden />}>
                    Archived
                </StatusBadge>
            )}
            {status === 'deceased' && (
                <StatusBadge tone="neutral">Deceased</StatusBadge>
            )}
            {status === 'moved' && (
                <StatusBadge tone="warning">Moved out</StatusBadge>
            )}
            {status === 'active' &&
                showActiveStatus &&
                !resident.archived_at && (
                    <StatusBadge tone="success">Active</StatusBadge>
                )}
            {resident.is_senior && (
                <StatusBadge tone="info" title="Senior citizen (60+, RA 9994)">
                    Senior
                </StatusBadge>
            )}
            {resident.age < 1 && (
                <StatusBadge tone="primary" icon={<Baby aria-hidden />}>
                    Infant
                </StatusBadge>
            )}
            {resident.is_pwd && (
                <StatusBadge
                    tone="primary"
                    icon={<Accessibility aria-hidden />}
                    title="Person with disability"
                >
                    PWD
                </StatusBadge>
            )}
            {resident.is_4ps && (
                <StatusBadge
                    tone="warning"
                    icon={<HandCoins aria-hidden />}
                    title="Pantawid Pamilyang Pilipino Program member"
                >
                    4Ps
                </StatusBadge>
            )}
            {resident.is_pregnant && (
                <StatusBadge tone="success" icon={<HeartPulse aria-hidden />}>
                    Pregnant
                </StatusBadge>
            )}
        </span>
    );
}
