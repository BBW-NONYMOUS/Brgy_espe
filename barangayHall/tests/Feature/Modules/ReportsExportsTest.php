<?php

use App\Enums\AuditAction;
use App\Enums\ConsentType;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Referral;
use App\Models\User;
use App\Services\FamilyHealthSummary;
use App\Services\ModuleReportService;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    User::query()->update(['must_change_password' => false]); // seeded accounts start with a forced reset
    $this->midwife = User::query()->where('username', 'mw.cristina')->sole();
    $this->official = User::query()->where('username', 'kgd.ronaldo')->sole();
    $this->bhw = User::query()->where('username', 'bhw.analiza')->sole();
    $this->period = ['date_from' => today()->subYear()->toDateString(), 'date_to' => today()->toDateString()];
});

test('every module report builds; officials never receive name tables', function (string $report) {
    $service = app(ModuleReportService::class);

    $forStaff = $service->build($report, $this->period, $this->midwife);
    $forOfficial = $service->build($report, $this->period, $this->official);

    expect($forStaff['totals'])->not->toBeEmpty()
        ->and($forStaff['tables'])->not->toBeEmpty()
        ->and(collect($forOfficial['tables'])->contains('named', true))->toBeFalse()
        ->and(json_encode($forOfficial))->not->toContain('KII-');
})->with(array_keys(ModuleReportService::REPORTS));

test('the reports page serves the selected module report as a deferred prop', function () {
    $this->actingAs($this->official)
        ->get(route('reports.index', ['report' => 'diseases', ...$this->period]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.report', 'diseases')
            ->where('summary', null)
            ->where('lists', null)
            ->loadDeferredProps('summary', fn (Assert $reload) => $reload
                ->where('moduleReport.key', 'diseases')
                ->where('moduleReport.totals.0.value', 8)));

    $this->actingAs($this->official)->get(route('reports.index', ['report' => 'nope']))->assertSessionHasErrors('report');
});

test('module reports export as PDF, Excel and CSV and every export is audited', function (string $format, string $type) {
    $response = $this->actingAs($this->midwife)->get(route('reports.export', ['report' => 'inventory', 'format' => $format, ...$this->period]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($type)
        ->and(AuditLog::query()->where('action', AuditAction::Exported)->where('description', 'like', 'Exported medicine stock report ('.strtoupper($format).')%')->exists())->toBeTrue();
})->with([
    'pdf' => ['pdf', 'application/pdf'],
    'xlsx' => ['xlsx', 'spreadsheetml'],
    'csv' => ['csv', 'text/csv'],
]);

test('officials export module reports as aggregates only', function () {
    $this->actingAs($this->official)->get(route('reports.export', ['report' => 'maternal_child', 'format' => 'csv']))->assertOk();

    expect(AuditLog::query()->latest('id')->first()->description)->toEndWith('aggregates only');
});

test('referral slips are printable by health roles only and each print is logged', function () {
    $referral = Referral::query()->where('status', 'sent')->firstOrFail();

    $this->actingAs($this->official)->get(route('referrals.slip', $referral))->assertForbidden();

    $response = $this->actingAs($this->bhw)->get(route('referrals.slip', $referral));
    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(AuditLog::query()->where('action', AuditAction::Exported)->where('description', "Printed referral slip {$referral->referral_no}")->exists())->toBeTrue();
});

test('the family health summary is a logged PDF for health roles; FP methods need the member\'s consent', function () {
    $household = Household::query()->where('household_no', 'HH-0003')->sole(); // Normina uses DMPA with an FP consent

    $this->actingAs($this->official)->get(route('households.summary', $household))->assertForbidden();
    $this->actingAs($this->midwife)->get(route('households.summary', $household))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(AuditLog::query()->where('description', 'Printed family health summary of HH-0003')->exists())->toBeTrue();

    $fpRows = fn () => app(FamilyHealthSummary::class)->build($household->fresh())['members']
        ->flatMap(fn (array $m) => collect($m['details'])->filter(fn (array $d) => $d[0] === 'Family planning'));

    expect($fpRows())->toHaveCount(1);

    $household->members()->where('first_name', 'Normina')->sole()
        ->consents()->where('consent_type', ConsentType::FamilyPlanning)->update(['revoked_at' => today()]);

    expect($fpRows())->toHaveCount(0);
});
