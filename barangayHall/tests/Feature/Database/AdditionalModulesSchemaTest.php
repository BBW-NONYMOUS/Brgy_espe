<?php

use App\Enums\AlertSeverity;
use App\Enums\AppointmentStatus;
use App\Enums\ConsentType;
use App\Enums\MaternalStatus;
use App\Enums\OutbreakAlertStatus;
use App\Models\Appointment;
use App\Models\Consent;
use App\Models\DiseaseCase;
use App\Models\Household;
use App\Models\MedicineBatch;
use App\Models\OutbreakAlert;
use App\Models\Pregnancy;
use App\Models\Resident;
use App\Models\StockMovement;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('every batch on hand equals the sum of its stock movements', function () {
    $mismatched = MedicineBatch::query()
        ->withSum('movements', 'quantity')
        ->get()
        ->filter(fn (MedicineBatch $b) => (int) $b->movements_sum_quantity !== $b->quantity_on_hand);

    expect(MedicineBatch::query()->count())->toBeGreaterThanOrEqual(16)
        ->and($mismatched)->toBeEmpty()
        ->and(MedicineBatch::query()->where('quantity_on_hand', '<', 0)->exists())->toBeFalse();
});

test('the seeded inventory covers every expiry band', function () {
    $days = MedicineBatch::query()->where('quantity_on_hand', '>', 0)->get()
        ->map(fn (MedicineBatch $b) => (int) today()->diffInDays($b->expiry_date, false));

    expect($days->contains(fn (int $d) => $d < 0))->toBeTrue()
        ->and($days->contains(fn (int $d) => $d >= 0 && $d <= 30))->toBeTrue()
        ->and($days->contains(fn (int $d) => $d > 30 && $d <= 60))->toBeTrue()
        ->and($days->contains(fn (int $d) => $d > 60 && $d <= 90))->toBeTrue();
});

test('stock movements are append-only', function () {
    $movement = StockMovement::query()->firstOrFail();

    expect(fn () => $movement->update(['quantity' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

test('every geo-tagged household has a member with an active geo-tagging consent', function () {
    $geotagged = Household::query()->geotagged()->get();

    expect($geotagged)->toHaveCount(12)
        ->and(Household::query()->whereNull('latitude')->count())->toBe(2);

    foreach ($geotagged as $household) {
        expect(Consent::query()
            ->activeOfType(ConsentType::Geotagging)
            ->whereIn('resident_id', $household->members()->withTrashed()->pluck('id'))
            ->exists())->toBeTrue("{$household->household_no} has no geo-tagging consent");
    }
});

test('coordinates and clinical free text are stored as specified', function () {
    $household = Household::query()->geotagged()->firstOrFail();
    $rawLatitude = DB::table('households')->where('id', $household->id)->value('latitude');
    $alert = Resident::query()->whereHas('criticalAlerts')->firstOrFail()->criticalAlerts()->firstOrFail();
    $rawAlert = DB::table('medical_alerts')->where('id', $alert->id)->value('description');

    expect((float) $rawLatitude)->toEqualWithDelta((float) $household->latitude, 0.0000001)
        ->and($rawAlert)->not->toContain('allergy')
        ->and($alert->severity)->toBe(AlertSeverity::Critical);
});

test('4Ps is a household attribute inherited by members', function () {
    $member = Resident::query()->where('first_name', 'Jamil')->firstOrFail();

    expect($member->isFourPs())->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasColumn('residents', 'is_4ps'))->toBeFalse()
        ->and(Household::query()->where('is_4ps', true)->count())->toBe(4);
});

test('the seeded maternal data links newborns to mothers and deliveries', function () {
    $delivered = Pregnancy::query()->where('status', MaternalStatus::Delivered)->sole();
    $newborn = Resident::query()->where('first_name', 'Matteo')->firstOrFail();

    expect($delivered->delivery)->not->toBeNull()
        ->and($delivered->delivery->isFacilityBased())->toBeTrue()
        ->and($newborn->mother_id)->toBe($delivered->resident_id)
        ->and($delivered->postnatalVisits()->count())->toBe(3)
        ->and(Pregnancy::query()->where('status', MaternalStatus::Ongoing)->count())->toBe(3);
});

test('the seeded surveillance data has one active dengue alert backed by 3 countable cases', function () {
    $alert = OutbreakAlert::query()->open()->sole();

    $cases = DiseaseCase::query()
        ->countable()
        ->where('disease_id', $alert->disease_id)
        ->where('purok_id', $alert->purok_id)
        ->where('onset_date', '>=', today()->subDays($alert->threshold_days_window - 1)->toDateString())
        ->count();

    expect($alert->status)->toBe(OutbreakAlertStatus::Active)
        ->and($alert->disease->name)->toBe('Dengue')
        ->and($cases)->toBe(3)
        ->and(DiseaseCase::query()->where('classification', 'ruled_out')->count())->toBe(1);
});

test('auto-generated appointments have unique source keys and manual ones have none', function () {
    $auto = Appointment::query()->whereNotNull('source_type');

    expect($auto->count())->toBeGreaterThan(10)
        ->and($auto->clone()->select('source_type', 'source_id', 'source_key')->distinct()->get()->count())->toBe($auto->count())
        ->and(Appointment::query()->whereNull('source_type')->whereNotNull('source_key')->exists())->toBeFalse()
        ->and(Appointment::query()->where('status', AppointmentStatus::Missed)->exists())->toBeTrue()
        ->and(Appointment::query()->whereDate('scheduled_at', today())->count())->toBeGreaterThanOrEqual(3);
});
