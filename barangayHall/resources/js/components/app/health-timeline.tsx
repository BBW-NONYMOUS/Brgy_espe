import { Lock, Stethoscope } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { StatusBadge } from '@/components/app/status-badge';
import { formatDate } from '@/lib/format';
import type { HealthVisit, VitalSigns } from '@/types';

type Props = {
    visits: HealthVisit[];
    /** Edit / remove buttons per visit (only for roles that may record health). */
    renderActions?: (visit: HealthVisit) => ReactNode;
    /** Shown in the empty state, e.g. an "Add visit" button. */
    emptyAction?: ReactNode;
};

/**
 * Chronological health history (newest first), grouped by year.
 * Clinical text the viewer may not see arrives already removed by the
 * server; we show a lock note instead.
 */
export function HealthTimeline({ visits, renderActions, emptyAction }: Props) {
    if (visits.length === 0) {
        return (
            <EmptyState
                icon={Stethoscope}
                title="No health visits yet"
                description="Consultations, check-ups and vital signs recorded at the health center will appear here."
                action={emptyAction}
            />
        );
    }

    const byYear = visits.reduce<Record<string, HealthVisit[]>>(
        (groups, visit) => {
            const year = visit.visit_date.slice(0, 4);
            (groups[year] ??= []).push(visit);

            return groups;
        },
        {},
    );

    return (
        <div className="space-y-6">
            {Object.entries(byYear)
                .sort(([a], [b]) => Number(b) - Number(a))
                .map(([year, items]) => (
                    <section key={year} aria-labelledby={`timeline-${year}`}>
                        <h3
                            id={`timeline-${year}`}
                            className="mb-3 text-sm font-bold text-muted-foreground"
                        >
                            {year}
                        </h3>
                        <ol className="relative space-y-4 border-l-2 border-border pl-6">
                            {items.map((visit) => (
                                <li key={visit.id} className="relative">
                                    <span
                                        className="absolute top-4 -left-[calc(1.5rem+5px)] size-3 rounded-full border-2 border-card bg-primary"
                                        aria-hidden
                                    />
                                    <TimelineItem
                                        visit={visit}
                                        actions={renderActions?.(visit)}
                                    />
                                </li>
                            ))}
                        </ol>
                    </section>
                ))}
        </div>
    );
}

function TimelineItem({
    visit,
    actions,
}: {
    visit: HealthVisit;
    actions?: ReactNode;
}) {
    return (
        <article className="rounded-xl border bg-card p-4">
            <header className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <time dateTime={visit.visit_date} className="font-bold">
                            {formatDate(visit.visit_date, 'long')}
                        </time>
                        {visit.visit_type && (
                            <StatusBadge tone="primary">
                                {visit.visit_type.name}
                            </StatusBadge>
                        )}
                        {visit.maternal && (
                            <StatusBadge tone="success">
                                {visit.maternal.kind.label}
                            </StatusBadge>
                        )}
                    </div>
                    {visit.attended_by && (
                        <p className="text-sm text-muted-foreground">
                            Attended by {visit.attended_by}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex gap-1" data-print="hide">
                        {actions}
                    </div>
                )}
            </header>

            {visit.restricted ? (
                <p className="mt-3 flex items-center gap-2 text-sm text-muted-foreground">
                    <Lock className="size-4" aria-hidden />
                    Clinical notes are hidden for your role.
                </p>
            ) : (
                <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-[9rem_1fr]">
                    {visit.chief_complaint && (
                        <Detail term="Complaint">
                            {visit.chief_complaint}
                        </Detail>
                    )}
                    {visit.diagnosis && (
                        <Detail term="Diagnosis">{visit.diagnosis}</Detail>
                    )}
                    {visit.notes && <Detail term="Notes">{visit.notes}</Detail>}
                    {visit.maternal?.remarks && (
                        <Detail term="Maternal remarks">
                            {visit.maternal.remarks}
                        </Detail>
                    )}
                </dl>
            )}

            {(visit.vitals || visit.maternal) && (
                <ul
                    className="mt-3 flex flex-wrap gap-2"
                    aria-label="Vital signs"
                >
                    {visit.vitals && <VitalChips vitals={visit.vitals} />}
                    {visit.maternal?.fetal_heart_rate && (
                        <Chip
                            label="FHR"
                            value={`${visit.maternal.fetal_heart_rate} bpm`}
                        />
                    )}
                    {visit.maternal?.fundal_height_cm && (
                        <Chip
                            label="Fundal ht"
                            value={`${visit.maternal.fundal_height_cm} cm`}
                        />
                    )}
                </ul>
            )}
        </article>
    );
}

function Detail({ term, children }: { term: string; children: ReactNode }) {
    return (
        <>
            <dt className="font-bold text-muted-foreground">{term}</dt>
            <dd className="whitespace-pre-line">{children}</dd>
        </>
    );
}

/**
 * Vital signs as compact chips. High blood pressure (≥140/90) and fever
 * (≥37.5 °C) are flagged with text, not only color.
 */
export function VitalChips({ vitals }: { vitals: VitalSigns }) {
    const highBp =
        (vitals.bp_systolic ?? 0) >= 140 || (vitals.bp_diastolic ?? 0) >= 90;
    const fever = (vitals.temperature_c ?? 0) >= 37.5;

    return (
        <>
            {vitals.bp_systolic && vitals.bp_diastolic && (
                <Chip
                    label="BP"
                    value={`${vitals.bp_systolic}/${vitals.bp_diastolic} mmHg`}
                    alert={highBp ? 'High' : undefined}
                />
            )}
            {vitals.temperature_c && (
                <Chip
                    label="Temp"
                    value={`${vitals.temperature_c} °C`}
                    alert={fever ? 'Fever' : undefined}
                />
            )}
            {vitals.weight_kg && (
                <Chip label="Wt" value={`${vitals.weight_kg} kg`} />
            )}
            {vitals.height_cm && (
                <Chip label="Ht" value={`${vitals.height_cm} cm`} />
            )}
            {vitals.bmi && <Chip label="BMI" value={String(vitals.bmi)} />}
            {vitals.pulse_rate && (
                <Chip label="PR" value={`${vitals.pulse_rate}/min`} />
            )}
            {vitals.respiratory_rate && (
                <Chip label="RR" value={`${vitals.respiratory_rate}/min`} />
            )}
        </>
    );
}

function Chip({
    label,
    value,
    alert,
}: {
    label: string;
    value: string;
    alert?: string;
}) {
    return (
        <li
            className={
                alert
                    ? 'rounded-lg border border-destructive/30 bg-danger-soft px-2.5 py-1 text-sm text-danger-soft-foreground'
                    : 'rounded-lg bg-muted px-2.5 py-1 text-sm'
            }
        >
            <span className="font-bold">{label}</span> {value}
            {alert && <span className="ml-1 font-bold">· {alert}</span>}
        </li>
    );
}
