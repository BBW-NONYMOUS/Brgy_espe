import { cn } from '@/lib/utils';
import type { MapFlag } from '@/types';

/*
 * Marker meanings, shared by the map (lazy-loaded with Leaflet) and the
 * legend/list views (light, no Leaflet).
 */

/** Highest priority first: the marker takes the colour of its first flag. */
export const MARKER_FLAGS: {
    flag: MapFlag | 'household';
    label: string;
    cssVar: string;
}[] = [
    {
        flag: 'active_case',
        label: 'Active disease case',
        cssVar: '--marker-case',
    },
    { flag: 'pregnant', label: 'Pregnant member', cssVar: '--marker-pregnant' },
    { flag: 'senior', label: 'Senior citizen', cssVar: '--marker-senior' },
    { flag: 'pwd', label: 'Person with disability', cssVar: '--marker-pwd' },
    { flag: 'four_ps', label: '4Ps household', cssVar: '--marker-four-ps' },
    {
        flag: 'household',
        label: 'Other household',
        cssVar: '--marker-household',
    },
];

export const FLAG_LABEL: Record<MapFlag, string> = {
    active_case: 'Active disease case',
    pregnant: 'Pregnant member',
    senior: 'Senior citizen',
    pwd: 'PWD',
    four_ps: '4Ps',
};

/**
 * Colour legend with text for every marker kind (shown beside the map).
 */
export function MapLegend({
    mode = 'households',
    className,
}: {
    mode?: 'households' | 'puroks';
    className?: string;
}) {
    const items =
        mode === 'households'
            ? MARKER_FLAGS
            : [
                  {
                      flag: 'active_case',
                      label: 'Purok with disease cases (size = number of cases)',
                      cssVar: '--marker-case',
                  },
                  {
                      flag: 'household',
                      label: 'No recent cases',
                      cssVar: '--marker-household',
                  },
              ];

    return (
        <ul
            className={cn('flex flex-wrap gap-x-4 gap-y-1 text-sm', className)}
            aria-label="Map legend"
        >
            {items.map((item) => (
                <li key={item.flag} className="flex items-center gap-2">
                    <span
                        aria-hidden
                        className="size-3.5 rounded-full border-2 border-white shadow-[0_0_0_1px_var(--border)]"
                        style={{ background: `var(${item.cssVar})` }}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}
