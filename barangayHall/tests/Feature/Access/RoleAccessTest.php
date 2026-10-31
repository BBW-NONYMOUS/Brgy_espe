<?php

use App\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedReferenceData());

/*
| Least privilege: every page is checked on the server for every role.
*/

dataset('pages', [
    'residents' => ['residents.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'register resident' => ['residents.create', [RoleName::Admin, RoleName::Bhw]],
    'households' => ['households.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife]],
    'reports' => ['reports.index', [RoleName::Admin, RoleName::Bhw, RoleName::Midwife, RoleName::Official]],
    'users' => ['users.index', [RoleName::Admin]],
    'audit logs' => ['audit-logs.index', [RoleName::Admin]],
    'backups' => ['backups.index', [RoleName::Admin]],
]);

test('each role can only open the pages it is allowed to', function (string $route, array $allowed) {
    foreach (RoleName::cases() as $role) {
        actingAsRole($role);

        $response = $this->get(route($route));

        in_array($role, $allowed, true)
            ? $response->assertOk()
            : $response->assertForbidden();
    }
})->with('pages');

test('every role sees the dashboard', function (RoleName $role) {
    actingAsRole($role);

    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard'));
})->with(RoleName::cases());

test('shared props expose permission flags matching the role', function () {
    actingAsRole(RoleName::Official);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.role.name', 'official')
        ->where('can.viewReports', true)
        ->where('can.viewResidents', false)
        ->where('can.viewHealthRecords', false)
        ->where('can.manageUsers', false)
        ->missing('auth.user.password'));
});

test('the dashboard never sends individual activity to barangay officials', function () {
    actingAsRole(RoleName::Official);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('recentActivity', null)
        ->loadDeferredProps('stats', fn (Assert $reload) => $reload->has('stats.residents')));
});

test('guests are sent to the login page', function () {
    $this->get(route('residents.index'))->assertRedirect(route('login'));
});
