<?php

namespace App\Services;

use App\Models\Resident;
use App\Support\MysqlSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Server-side search, filter, sort and pagination for the residents table.
 * All filters come from the query string (validated by the controller).
 */
class ResidentSearch
{
    /** Columns the UI may sort by (whitelist — never interpolate user input). */
    public const SORTABLE = ['resident_code', 'last_name', 'birthdate', 'created_at'];

    public const AGE_GROUPS = [
        'infant' => [0, 0],
        'child' => [1, 9],
        'adolescent' => [10, 19],
        'adult' => [20, 59],
        'senior' => [60, null],
    ];

    /**
     * @param  array{search?: string|null, purok_id?: int|string|null, sex?: string|null, age_group?: string|null, status?: string|null, archived?: bool|string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Resident>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'last_name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 5), 100);

        $query = Resident::query()
            ->select('residents.*')
            ->with(['household.purok', 'pregnancies' => fn ($q) => $q->select('id', 'resident_id', 'status', 'edd')]);

        if (filter_var($filters['archived'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->onlyTrashed();
        }

        $this->applySearch($query, trim((string) ($filters['search'] ?? '')));

        $query
            ->when($filters['purok_id'] ?? null, fn (Builder $q, $purokId) => $q->whereHas(
                'household',
                fn (Builder $h) => $h->where('purok_id', $purokId),
            ))
            ->when($filters['sex'] ?? null, fn (Builder $q, $sex) => $q->where('sex', $sex))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));

        $ageGroup = $filters['age_group'] ?? null;

        if ($ageGroup !== null && array_key_exists($ageGroup, self::AGE_GROUPS)) {
            [$min, $max] = self::AGE_GROUPS[$ageGroup];
            $query->agedBetween($min, $max);
        }

        $query->orderBy($sort, $direction);

        if ($sort === 'last_name') {
            $query->orderBy('first_name', $direction);
        }

        return $query->orderBy('residents.id')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  Builder<Resident>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        // Exact code lookups: resident code or household number.
        if (preg_match('/^[A-Z]{2,4}-\d{4}-\d{1,5}$/i', $term)) {
            $query->where('resident_code', Str::upper($term));

            return;
        }

        if (preg_match('/^HH-\d+$/i', $term)) {
            $query->whereHas('household', fn (Builder $h) => $h->where('household_no', Str::upper($term)));

            return;
        }

        // Birthdate typed as YYYY-MM-DD.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $term)) {
            $query->whereDate('birthdate', $term);

            return;
        }

        // "Dela Cruz, Juan" → last name + first name prefix.
        if (str_contains($term, ',')) {
            [$last, $first] = array_map('trim', explode(',', $term, 2));
            $query->where('last_name', 'like', $this->escape($last).'%')
                ->when($first !== '', fn (Builder $q) => $q->where('first_name', 'like', $this->escape($first).'%'));

            return;
        }

        $prefix = $this->escape($term).'%';

        $query->where(function (Builder $q) use ($term, $prefix) {
            $q->where('last_name', 'like', $prefix)
                ->orWhere('first_name', 'like', $prefix);

            if (MysqlSchema::supported()) {
                // Word match anywhere in the name ("cruz" → "Dela Cruz").
                $words = collect(preg_split('/\s+/', $term) ?: [])
                    ->map(fn (string $w) => preg_replace('/[^\pL\pN]/u', '', $w))
                    ->filter(fn (?string $w) => $w !== null && mb_strlen($w) >= 2)
                    ->map(fn (string $w) => '+'.$w.'*')
                    ->implode(' ');

                if ($words !== '') {
                    $q->orWhereFullText(['last_name', 'first_name', 'middle_name'], $words, ['mode' => 'boolean']);
                }
            } else {
                $q->orWhere('middle_name', 'like', $prefix)
                    ->orWhere('last_name', 'like', '% '.$prefix);
            }
        });
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
