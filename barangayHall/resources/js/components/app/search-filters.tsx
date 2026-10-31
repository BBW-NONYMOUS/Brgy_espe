import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** Radix Select cannot use '' as a value, so "All" is a sentinel. */
const ALL = '__all';

export type FilterOption = { value: string | number; label: string };

export type FilterDefinition = {
    key: string;
    label: string;
    options: FilterOption[];
    value: string | number | null | undefined;
    onChange: (value: string | null) => void;
    /** Label for the "no filter" choice, e.g. "All puroks". */
    allLabel: string;
};

type Props = {
    search?: {
        value: string;
        onChange: (value: string) => void;
        placeholder: string;
        /** Visually hidden label for screen readers. */
        label: string;
    };
    filters?: FilterDefinition[];
    /** Extra controls, e.g. a "Show archived" switch. */
    children?: ReactNode;
    onReset?: () => void;
    hasActiveFilters?: boolean;
};

/**
 * Search box + filter dropdowns above a DataTable. State lives in the URL
 * (see useTableQuery); this component is purely presentational.
 */
export function SearchFilters({
    search,
    filters = [],
    children,
    onReset,
    hasActiveFilters = false,
}: Props) {
    const compact = filters.length <= 1 && !children;

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-3',
                // One filter fits beside the search box; more go on their own row.
                compact && 'lg:flex-row lg:items-center',
            )}
            role="search"
            data-print="hide"
        >
            {search && (
                <div className="min-w-0 flex-1">
                    <Label htmlFor="table-search" className="sr-only">
                        {search.label}
                    </Label>
                    <InputGroup className="h-11">
                        <InputGroupAddon>
                            <Search aria-hidden />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="table-search"
                            type="search"
                            value={search.value}
                            onChange={(e) => search.onChange(e.target.value)}
                            placeholder={search.placeholder}
                            autoComplete="off"
                            className="text-base"
                        />
                    </InputGroup>
                </div>
            )}

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                {filters.length > 0 && (
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        {filters.map((filter) => (
                            <div key={filter.key}>
                                <Label
                                    htmlFor={`filter-${filter.key}`}
                                    className="sr-only"
                                >
                                    {filter.label}
                                </Label>
                                <Select
                                    value={
                                        filter.value === null ||
                                        filter.value === undefined ||
                                        filter.value === ''
                                            ? ALL
                                            : String(filter.value)
                                    }
                                    onValueChange={(v) =>
                                        filter.onChange(v === ALL ? null : v)
                                    }
                                >
                                    <SelectTrigger
                                        id={`filter-${filter.key}`}
                                        className="w-full data-[size=default]:h-11 sm:w-44"
                                    >
                                        <SelectValue
                                            placeholder={filter.label}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {filter.allLabel}
                                        </SelectItem>
                                        {filter.options.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                    </div>
                )}

                {children}

                {onReset && hasActiveFilters && (
                    <Button variant="ghost" className="h-11" onClick={onReset}>
                        <X aria-hidden />
                        Clear filters
                    </Button>
                )}
            </div>
        </div>
    );
}
