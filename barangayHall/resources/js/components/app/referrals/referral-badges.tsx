import {
    CheckCircle2,
    CircleDashed,
    Inbox,
    Send,
    Siren,
    TriangleAlert,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/app/status-badge';
import type { BadgeTone } from '@/components/app/status-badge';
import type { EnumValue } from '@/types/models';
import type { ReferralStatus, ReferralUrgency } from '@/types/modules';

const statusStyle: Record<
    ReferralStatus,
    { tone: BadgeTone; icon: ReactNode }
> = {
    pending: { tone: 'warning', icon: <CircleDashed aria-hidden /> },
    sent: { tone: 'info', icon: <Send aria-hidden /> },
    received: { tone: 'primary', icon: <Inbox aria-hidden /> },
    completed: { tone: 'success', icon: <CheckCircle2 aria-hidden /> },
    cancelled: { tone: 'neutral', icon: <XCircle aria-hidden /> },
};

export function ReferralStatusBadge({
    status,
}: {
    status: EnumValue<ReferralStatus>;
}) {
    const style = statusStyle[status.value];

    return (
        <StatusBadge tone={style.tone} icon={style.icon}>
            {status.label}
        </StatusBadge>
    );
}

export function UrgencyBadge({
    urgency,
}: {
    urgency: EnumValue<ReferralUrgency>;
}) {
    if (urgency.value === 'routine') {
        return <StatusBadge tone="neutral">{urgency.label}</StatusBadge>;
    }

    return (
        <StatusBadge
            tone={urgency.value === 'emergency' ? 'danger' : 'caution'}
            icon={
                urgency.value === 'emergency' ? (
                    <Siren aria-hidden />
                ) : (
                    <TriangleAlert aria-hidden />
                )
            }
        >
            {urgency.label}
        </StatusBadge>
    );
}
