<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\AppointmentType;
use App\Models\Condition;
use App\Models\Disease;
use App\Models\Facility;
use App\Models\Household;
use App\Models\ProgramType;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\RiskFactor;
use App\Models\ToiletType;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\VisitType;
use App\Models\WaterSource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Small {value, label} lists for Select / Combobox components.
 */
class LookupOptions
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function puroks(): array
    {
        return Purok::query()->orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Purok $p) => ['value' => $p->id, 'label' => $p->name])->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function visitTypes(): array
    {
        return VisitType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (VisitType $v) => ['value' => $v->id, 'label' => $v->name])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, code: string, doses: int}>
     */
    public function vaccines(): array
    {
        return Vaccine::query()->orderBy('code')->get()
            ->map(fn (Vaccine $v) => ['value' => $v->id, 'label' => $v->name, 'code' => $v->code, 'doses' => $v->doses_required])->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function conditions(): array
    {
        return Condition::query()->orderBy('name')->get()
            ->map(fn (Condition $c) => ['value' => $c->id, 'label' => $c->icd10_code ? "{$c->name} ({$c->icd10_code})" : $c->name])->all();
    }

    /**
     * Household Combobox search (household no., address or head's name).
     *
     * @return array<int, array{value: int, label: string, purok: string, address: string}>
     */
    public function households(?string $term, ?int $includeId = null): array
    {
        $term = trim((string) $term);

        return Household::query()
            ->with(['purok', 'head'])
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('household_no', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%")
                ->orWhereHas('head', fn (Builder $h) => $h->where('last_name', 'like', "{$term}%"))))
            ->when($term === '' && $includeId === null, fn (Builder $q) => $q->latest('id'))
            ->when($includeId, fn (Builder $q, int $id) => $q->orderByRaw('id = ? desc', [$id]))
            ->limit(15)
            ->get()
            ->map(fn (Household $h) => [
                'value' => $h->id,
                'label' => $h->household_no.($h->head ? ' — '.$h->head->full_name : ''),
                'purok' => $h->purok->name,
                'address' => $h->address,
            ])
            ->all();
    }

    /**
     * Resident Combobox search (name or resident code), active residents only.
     *
     * @return array<int, array{value: int, label: string, code: string, age: int, purok: string}>
     */
    public function residents(?string $term, ?int $includeId = null): array
    {
        $term = trim((string) $term);

        if ($term === '' && $includeId === null) {
            return [];
        }

        return Resident::query()
            ->with('household.purok')
            ->where(fn (Builder $q) => $q
                ->when($term !== '', fn (Builder $w) => $w
                    ->where('resident_code', 'like', "{$term}%")
                    ->orWhere('last_name', 'like', "{$term}%")
                    ->orWhere('first_name', 'like', "{$term}%"))
                ->when($includeId, fn (Builder $w, int $id) => $w->orWhereKey($id)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(15)
            ->get()
            ->map(fn (Resident $r) => [
                'value' => $r->id,
                'label' => $r->full_name,
                'code' => $r->resident_code,
                'age' => $r->age,
                'purok' => $r->household->purok->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string, category: string}>
     */
    public function diseases(bool $activeOnly = true): array
    {
        return Disease::query()->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))->orderBy('name')->get()
            ->map(fn (Disease $d) => ['value' => $d->id, 'label' => $d->name, 'category' => $d->pidsr_category->label()])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, type: string}>
     */
    public function facilities(): array
    {
        return Facility::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Facility $f) => ['value' => $f->id, 'label' => $f->name, 'type' => $f->type->label()])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, code: string, color: string}>
     */
    public function appointmentTypes(): array
    {
        return AppointmentType::query()->orderBy('name')->get()
            ->map(fn (AppointmentType $t) => ['value' => $t->id, 'label' => $t->name, 'code' => $t->code, 'color' => $t->color])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, color: string}>
     */
    public function programTypes(): array
    {
        return ProgramType::query()->orderBy('name')->get()
            ->map(fn (ProgramType $t) => ['value' => $t->id, 'label' => $t->name, 'color' => $t->color])->all();
    }

    /**
     * Health workers who can be assigned appointments or programs.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function staff(): array
    {
        return User::query()->withPermission(PermissionName::AppointmentsManage)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function riskFactors(): array
    {
        return RiskFactor::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (RiskFactor $f) => ['value' => $f->id, 'label' => $f->name])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, safe: bool}>
     */
    public function waterSources(): array
    {
        return WaterSource::query()->orderBy('sort_order')->get()
            ->map(fn (WaterSource $w) => ['value' => $w->id, 'label' => $w->name, 'safe' => $w->is_safe])->all();
    }

    /**
     * @return array<int, array{value: int, label: string, sanitary: bool}>
     */
    public function toiletTypes(): array
    {
        return ToiletType::query()->orderBy('sort_order')->get()
            ->map(fn (ToiletType $t) => ['value' => $t->id, 'label' => $t->name, 'sanitary' => $t->is_sanitary])->all();
    }
}
