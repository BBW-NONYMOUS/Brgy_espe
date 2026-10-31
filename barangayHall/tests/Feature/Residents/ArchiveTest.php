<?php

use App\Enums\AuditAction;
use App\Enums\ResidentStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\HealthVisit;
use App\Models\Resident;

beforeEach(function () {
    seedReferenceData();
    $this->resident = Resident::factory()->create();
});

test('archiving needs a reason and is a soft delete', function () {
    actingAsRole(RoleName::Bhw);

    $this->delete(route('residents.destroy', $this->resident), ['status' => 'moved'])->assertSessionHasErrors('archive_reason');

    $this->delete(route('residents.destroy', $this->resident), ['status' => 'moved', 'archive_reason' => 'Moved to Tacurong City'])
        ->assertRedirect(route('residents.index'));

    $archived = Resident::withTrashed()->find($this->resident->id);

    expect($archived->trashed())->toBeTrue()
        ->and($archived->status)->toBe(ResidentStatus::Moved)
        ->and(AuditLog::query()->where('action', AuditAction::Deleted)->where('record_id', $this->resident->id)->exists())->toBeTrue();
});

test('archived residents can be restored', function () {
    actingAsRole(RoleName::Bhw);
    $this->resident->update(['status' => ResidentStatus::Moved, 'archive_reason' => 'Moved']);
    $this->resident->delete();

    $this->post(route('residents.restore', $this->resident))->assertRedirect(route('residents.show', $this->resident));

    expect($this->resident->fresh()->trashed())->toBeFalse()
        ->and($this->resident->fresh()->status)->toBe(ResidentStatus::Active);
});

test('permanent deletion is admin-only and only after the retention period', function () {
    HealthVisit::factory()->for($this->resident)->create();
    $this->resident->delete();

    actingAsRole(RoleName::Bhw);
    $this->delete(route('residents.force-destroy', $this->resident))->assertForbidden();

    actingAsRole(RoleName::Admin);
    $this->delete(route('residents.force-destroy', $this->resident))->assertForbidden();

    $this->travel(5)->years();
    $this->travel(1)->day();

    $this->delete(route('residents.force-destroy', $this->resident))->assertRedirect();

    expect(Resident::withTrashed()->find($this->resident->id))->toBeNull()
        ->and(HealthVisit::withTrashed()->where('resident_id', $this->resident->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::ForceDeleted)->where('table_name', 'residents')->exists())->toBeTrue();
});

test('active residents cannot be permanently deleted', function () {
    actingAsRole(RoleName::Admin);

    $this->delete(route('residents.force-destroy', $this->resident))->assertForbidden();
});
