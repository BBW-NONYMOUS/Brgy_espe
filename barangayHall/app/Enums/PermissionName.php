<?php

namespace App\Enums;

enum PermissionName: string
{
    case UsersManage = 'users.manage';
    case LookupsManage = 'lookups.manage';
    case ResidentsView = 'residents.view';
    case ResidentsCreate = 'residents.create';
    case ResidentsUpdate = 'residents.update';
    case ResidentsArchive = 'residents.archive';
    case ResidentsForceDelete = 'residents.force_delete';
    case HouseholdsManage = 'households.manage';
    case ConsentsRecord = 'consents.record';
    case HealthViewSensitive = 'health.view_sensitive';
    case HealthRecord = 'health.record';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case AuditView = 'audit.view';
    case BackupsManage = 'backups.manage';
    case HouseholdsGeotag = 'households.geotag';
    case MapViewExact = 'map.view_exact';
    case MapViewAggregate = 'map.view_aggregate';
    case DiseasesView = 'diseases.view';
    case DiseasesRecord = 'diseases.record';
    case DiseasesClassify = 'diseases.classify';
    case OutbreaksViewSummary = 'outbreaks.view_summary';
    case OutbreaksManage = 'outbreaks.manage';
    case MaternalManage = 'maternal.manage';
    case ReferralsManage = 'referrals.manage';
    case InventoryView = 'inventory.view';
    case InventoryDispense = 'inventory.dispense';
    case InventoryManage = 'inventory.manage';
    case AppointmentsManage = 'appointments.manage';
    case ProgramsView = 'programs.view';
    case ProgramsManage = 'programs.manage';
    case SchedulesManage = 'schedules.manage';

    public function label(): string
    {
        return match ($this) {
            self::UsersManage => 'Manage user accounts and roles',
            self::LookupsManage => 'Manage lookups and health settings (puroks, diseases, outbreak thresholds, facilities)',
            self::ResidentsView => 'Search and view resident profiles',
            self::ResidentsCreate => 'Register residents',
            self::ResidentsUpdate => 'Update resident profiles',
            self::ResidentsArchive => 'Archive and restore residents',
            self::ResidentsForceDelete => 'Permanently delete archived residents',
            self::HouseholdsManage => 'Create and update households',
            self::ConsentsRecord => 'Record resident consent',
            self::HealthViewSensitive => 'View diagnosis, notes and PhilHealth number',
            self::HealthRecord => 'Record visits, vitals, immunizations, growth, micronutrients, contacts and medical alerts',
            self::ReportsView => 'View summary reports',
            self::ReportsExport => 'Export and print summary reports',
            self::AuditView => 'View audit logs',
            self::BackupsManage => 'Run and download database backups',
            self::HouseholdsGeotag => 'Geo-tag households',
            self::MapViewExact => 'View exact household locations on the map',
            self::MapViewAggregate => 'View purok-level health maps',
            self::DiseasesView => 'View disease case lists',
            self::DiseasesRecord => 'Report disease cases',
            self::DiseasesClassify => 'Confirm or rule out disease cases and set outcomes',
            self::OutbreaksViewSummary => 'View outbreak alert summaries',
            self::OutbreaksManage => 'Acknowledge, monitor and close outbreak alerts',
            self::MaternalManage => 'Manage pregnancies, deliveries and prenatal/postnatal care',
            self::ReferralsManage => 'Create referrals and update their status',
            self::InventoryView => 'View medicine inventory',
            self::InventoryDispense => 'Dispense medicines to residents',
            self::InventoryManage => 'Receive, adjust and write off stock; set reorder levels',
            self::AppointmentsManage => 'Book and update appointments',
            self::ProgramsView => 'View community health programs',
            self::ProgramsManage => 'Schedule programs and record attendance',
            self::SchedulesManage => 'Configure prenatal/postnatal and immunization schedules',
        };
    }

    /**
     * Default permission matrix (docs/phase-1-architecture.md §2.2 and
     * docs/additional/phase-1-architecture.md §2.2).
     *
     * @return list<self>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::Admin => self::cases(),
            RoleName::Bhw => [
                self::ResidentsView, self::ResidentsCreate, self::ResidentsUpdate, self::ResidentsArchive,
                self::HouseholdsManage, self::ConsentsRecord, self::HealthViewSensitive, self::HealthRecord,
                self::ReportsView, self::ReportsExport,
                self::HouseholdsGeotag, self::MapViewExact, self::MapViewAggregate,
                self::DiseasesView, self::DiseasesRecord, self::OutbreaksViewSummary,
                self::InventoryView, self::InventoryDispense,
                self::AppointmentsManage, self::ProgramsView, self::ProgramsManage,
            ],
            RoleName::Midwife => [
                self::ResidentsView, self::ResidentsUpdate, self::ConsentsRecord,
                self::HealthViewSensitive, self::HealthRecord, self::ReportsView, self::ReportsExport,
                self::HouseholdsGeotag, self::MapViewExact, self::MapViewAggregate,
                self::DiseasesView, self::DiseasesRecord, self::DiseasesClassify,
                self::OutbreaksViewSummary, self::OutbreaksManage,
                self::MaternalManage, self::ReferralsManage,
                self::InventoryView, self::InventoryDispense, self::InventoryManage,
                self::AppointmentsManage, self::ProgramsView, self::ProgramsManage, self::SchedulesManage,
            ],
            RoleName::Official => [
                self::ReportsView, self::ReportsExport,
                self::MapViewAggregate, self::OutbreaksViewSummary, self::ProgramsView,
            ],
        };
    }
}
