<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Kept deliberately small: these props are visible in the page source.
     * Never pass the raw User model — only the fields the layout needs.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'healthCenter' => [
                'barangay' => config('barangay.barangay'),
                'municipality' => config('barangay.municipality'),
                'province' => config('barangay.province'),
            ],
            'auth' => [
                'user' => $user instanceof User ? $this->sharedUser($user) : null,
            ],
            'can' => $user instanceof User ? $this->permissionFlags($user) : (object) [],
            // Bell badge on every page; the list itself is only sent when the
            // bell asks for it (partial reload of `notificationsLatest`).
            'unreadNotifications' => $user instanceof User ? fn () => $user->unreadNotifications()->count() : 0,
            'notificationsLatest' => Inertia::optional(fn () => $user instanceof User
                ? $user->notifications()->latest()->limit(8)->get()->map(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    ...$n->data,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                ])->all()
                : []),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => ['name' => $user->role->name, 'label' => $user->role->label],
            'must_change_password' => $user->must_change_password,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
        ];
    }

    /**
     * UI hints only — every action is authorized again on the server.
     *
     * @return array<string, bool>
     */
    private function permissionFlags(User $user): array
    {
        $has = fn (PermissionName $permission) => $user->hasPermission($permission);

        return [
            'viewResidents' => $has(PermissionName::ResidentsView),
            'createResidents' => $has(PermissionName::ResidentsCreate),
            'updateResidents' => $has(PermissionName::ResidentsUpdate),
            'archiveResidents' => $has(PermissionName::ResidentsArchive),
            'forceDeleteResidents' => $has(PermissionName::ResidentsForceDelete),
            'manageHouseholds' => $has(PermissionName::HouseholdsManage),
            'recordConsents' => $has(PermissionName::ConsentsRecord),
            'viewHealthRecords' => $has(PermissionName::HealthViewSensitive),
            'recordHealth' => $has(PermissionName::HealthRecord),
            'viewReports' => $has(PermissionName::ReportsView),
            'exportReports' => $has(PermissionName::ReportsExport),
            'manageUsers' => $has(PermissionName::UsersManage),
            'viewAuditLogs' => $has(PermissionName::AuditView),
            'manageBackups' => $has(PermissionName::BackupsManage),
            'manageSettings' => $has(PermissionName::LookupsManage),
            'geotagHouseholds' => $has(PermissionName::HouseholdsGeotag),
            'viewHouseholdMap' => $has(PermissionName::MapViewExact),
            'viewAggregateMap' => $has(PermissionName::MapViewAggregate),
            'viewDiseases' => $has(PermissionName::DiseasesView),
            'recordDiseases' => $has(PermissionName::DiseasesRecord),
            'classifyDiseases' => $has(PermissionName::DiseasesClassify),
            'viewOutbreaks' => $has(PermissionName::OutbreaksViewSummary),
            'manageOutbreaks' => $has(PermissionName::OutbreaksManage),
            'manageMaternal' => $has(PermissionName::MaternalManage),
            'manageReferrals' => $has(PermissionName::ReferralsManage),
            'viewInventory' => $has(PermissionName::InventoryView),
            'dispenseMedicine' => $has(PermissionName::InventoryDispense),
            'manageInventory' => $has(PermissionName::InventoryManage),
            'manageAppointments' => $has(PermissionName::AppointmentsManage),
            'viewPrograms' => $has(PermissionName::ProgramsView),
            'managePrograms' => $has(PermissionName::ProgramsManage),
            'manageSchedules' => $has(PermissionName::SchedulesManage),
        ];
    }
}
