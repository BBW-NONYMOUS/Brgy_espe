<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DuplicateCheckController;
use App\Http\Controllers\Health\ConsentController;
use App\Http\Controllers\Health\HealthVisitController;
use App\Http\Controllers\Health\ImmunizationController;
use App\Http\Controllers\Health\PregnancyController;
use App\Http\Controllers\Health\ResidentConditionController;
use App\Http\Controllers\Health\VisitListController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResidentController;
use Illuminate\Support\Facades\Route;

/*
| Every controller action is authorized again with a Policy/Gate; the `can:`
| middleware on each group is a second line of defence (least privilege).
*/

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    // Forced password change (first login / after admin reset).
    Route::get('password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::put('password/change', [PasswordChangeController::class, 'store'])->name('password.change.store');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Residents & households
    Route::middleware('can:residents.view')->group(function () {
        Route::get('residents/duplicate-check', DuplicateCheckController::class)
            ->middleware('throttle:60,1')
            ->name('residents.duplicate-check');

        Route::resource('residents', ResidentController::class)->withTrashed(['show']);
        Route::post('residents/{resident}/restore', [ResidentController::class, 'restore'])->withTrashed()->name('residents.restore');
        Route::delete('residents/{resident}/permanent', [ResidentController::class, 'forceDestroy'])->withTrashed()->name('residents.force-destroy');

        Route::resource('households', HouseholdController::class)->except(['create', 'edit']);

        Route::post('residents/{resident}/consents', [ConsentController::class, 'store'])->name('residents.consents.store');
        Route::patch('consents/{consent}/revoke', [ConsentController::class, 'revoke'])->name('consents.revoke');
    });

    // Health records (quick entry from the resident profile)
    Route::middleware('can:health.record')->group(function () {
        Route::post('residents/{resident}/visits', [HealthVisitController::class, 'store'])->name('residents.visits.store');
        Route::put('visits/{visit}', [HealthVisitController::class, 'update'])->name('visits.update');
        Route::delete('visits/{visit}', [HealthVisitController::class, 'destroy'])->name('visits.destroy');

        Route::post('residents/{resident}/immunizations', [ImmunizationController::class, 'store'])->name('residents.immunizations.store');
        Route::delete('immunizations/{immunization}', [ImmunizationController::class, 'destroy'])->name('immunizations.destroy');

        Route::post('residents/{resident}/conditions', [ResidentConditionController::class, 'store'])->name('residents.conditions.store');
        Route::put('conditions/{condition}', [ResidentConditionController::class, 'update'])->name('conditions.update');
        Route::delete('conditions/{condition}', [ResidentConditionController::class, 'destroy'])->name('conditions.destroy');

        Route::post('residents/{resident}/pregnancies', [PregnancyController::class, 'store'])->name('residents.pregnancies.store');
        Route::put('pregnancies/{pregnancy}', [PregnancyController::class, 'update'])->name('pregnancies.update');
    });

    // Health visits across residents (list only; clinical text stays on the profile).
    Route::get('visits', VisitListController::class)->middleware('can:health.view_sensitive')->name('visits.index');

    // Reports (Barangay Officials: aggregates only)
    Route::get('reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('can:reports.export')->name('reports.export');

    // Administration
    Route::middleware('can:users.manage')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit-logs.index');

    Route::middleware('can:backups.manage')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    });

    // Additional modules 9–17: map, surveillance, maternal & child, referrals,
    // inventory, appointments, programs, notifications, health settings.
    require __DIR__.'/modules.php';

    // Design-system gallery for developers; never registered in production.
    if (app()->environment('local')) {
        Route::inertia('dev/components', 'dev/components');
        Route::inertia('dev/modules', 'dev/modules');
    }
});

require __DIR__.'/settings.php';
