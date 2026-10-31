<?php

use App\Http\Controllers\Admin\HealthSettingsController;
use App\Http\Controllers\GeotagController;
use App\Http\Controllers\Health\ChildHealthController;
use App\Http\Controllers\Health\ChildMeasurementController;
use App\Http\Controllers\Health\DeliveryController;
use App\Http\Controllers\Health\EmergencyContactController;
use App\Http\Controllers\Health\MedicalAlertController;
use App\Http\Controllers\Health\PregnancyController;
use App\Http\Controllers\Health\ReferralController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\Inventory\MedicineController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Scheduling\AppointmentController;
use App\Http\Controllers\Scheduling\CalendarEventsController;
use App\Http\Controllers\Scheduling\HealthProgramController;
use App\Http\Controllers\Scheduling\ProgramAttendanceController;
use App\Http\Controllers\Surveillance\DiseaseCaseController;
use App\Http\Controllers\Surveillance\OutbreakAlertController;
use Illuminate\Support\Facades\Route;

/*
| Additional modules 9–17 (featured.md). Loaded from web.php inside the `auth`
| group. As in the core: `can:` middleware is the second line; every action
| authorizes again with a Policy/Gate or Form Request.
*/

// 9. Geo-tagging & household map (Officials: purok-level view only)
Route::get('map', [MapController::class, 'index'])->name('map.index');
Route::middleware('can:households.geotag')->group(function () {
    Route::put('households/{household}/geotag', [GeotagController::class, 'update'])->name('households.geotag.update');
    Route::delete('households/{household}/geotag', [GeotagController::class, 'destroy'])->name('households.geotag.destroy');
});

// 10. Disease surveillance
Route::middleware('can:diseases.view')->group(function () {
    Route::get('diseases', [DiseaseCaseController::class, 'index'])->name('disease-cases.index');
    Route::post('diseases', [DiseaseCaseController::class, 'store'])->middleware('can:diseases.record')->name('disease-cases.store');
    Route::put('diseases/{case}', [DiseaseCaseController::class, 'update'])->middleware('can:diseases.classify')->name('disease-cases.update');
    Route::delete('diseases/{case}', [DiseaseCaseController::class, 'destroy'])->middleware('can:diseases.classify')->name('disease-cases.destroy');
});
Route::get('outbreak-alerts', [OutbreakAlertController::class, 'index'])->middleware('can:outbreaks.view_summary')->name('outbreak-alerts.index');
Route::middleware('can:outbreaks.manage')->group(function () {
    Route::post('outbreak-alerts/{alert}/acknowledge', [OutbreakAlertController::class, 'acknowledge'])->name('outbreak-alerts.acknowledge');
    Route::post('outbreak-alerts/{alert}/monitor', [OutbreakAlertController::class, 'monitor'])->name('outbreak-alerts.monitor');
    Route::post('outbreak-alerts/{alert}/close', [OutbreakAlertController::class, 'close'])->name('outbreak-alerts.close');
});

// 11. Maternal & child health (viewing: health access; changes: see policies)
Route::middleware('can:health.view_sensitive')->group(function () {
    Route::get('maternal', [PregnancyController::class, 'index'])->name('pregnancies.index');
    Route::get('maternal/{pregnancy}', [PregnancyController::class, 'show'])->name('pregnancies.show');
    Route::post('pregnancies/{pregnancy}/delivery', [DeliveryController::class, 'store'])->middleware('can:maternal.manage')->name('pregnancies.delivery.store');

    Route::get('child-health', [ChildHealthController::class, 'index'])->name('child-health.index');
    Route::get('child-health/{resident}', [ChildHealthController::class, 'show'])->name('child-health.show');
});
Route::middleware('can:health.record')->group(function () {
    Route::post('residents/{resident}/growth', [ChildMeasurementController::class, 'storeGrowth'])->name('residents.growth.store');
    Route::delete('growth/{measurement}', [ChildMeasurementController::class, 'destroyGrowth'])->name('growth.destroy');
    Route::post('residents/{resident}/micronutrients', [ChildMeasurementController::class, 'storeMicronutrient'])->name('residents.micronutrients.store');
    Route::delete('micronutrients/{record}', [ChildMeasurementController::class, 'destroyMicronutrient'])->name('micronutrients.destroy');

    // 15. Emergency contacts & medical alerts
    Route::post('residents/{resident}/emergency-contacts', [EmergencyContactController::class, 'store'])->name('residents.emergency-contacts.store');
    Route::put('emergency-contacts/{contact}', [EmergencyContactController::class, 'update'])->name('emergency-contacts.update');
    Route::delete('emergency-contacts/{contact}', [EmergencyContactController::class, 'destroy'])->name('emergency-contacts.destroy');
    Route::post('residents/{resident}/medical-alerts', [MedicalAlertController::class, 'store'])->name('residents.medical-alerts.store');
    Route::put('medical-alerts/{alert}', [MedicalAlertController::class, 'update'])->name('medical-alerts.update');
    Route::delete('medical-alerts/{alert}', [MedicalAlertController::class, 'destroy'])->name('medical-alerts.destroy');
});

// 12. Family health summary (printable PDF)
Route::get('households/{household}/summary', [HouseholdController::class, 'summary'])
    ->middleware(['can:residents.view', 'can:health.view_sensitive'])
    ->name('households.summary');

// 13. Referrals
Route::middleware('can:health.view_sensitive')->group(function () {
    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
    Route::get('referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');
    Route::get('referrals/{referral}/slip', [ReferralController::class, 'slip'])->name('referrals.slip');
    Route::post('referrals', [ReferralController::class, 'store'])->middleware('can:referrals.manage')->name('referrals.store');
    Route::patch('referrals/{referral}/status', [ReferralController::class, 'updateStatus'])->middleware('can:referrals.manage')->name('referrals.status.update');
});

// 14. Medicine inventory
Route::middleware('can:inventory.view')->group(function () {
    Route::get('inventory', [MedicineController::class, 'index'])->name('medicines.index');
    Route::get('inventory/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');
    Route::post('inventory/{medicine}/dispense', [StockController::class, 'dispense'])->middleware('can:inventory.dispense')->name('medicines.dispense');

    Route::middleware('can:inventory.manage')->group(function () {
        Route::post('inventory', [MedicineController::class, 'store'])->name('medicines.store');
        Route::put('inventory/{medicine}', [MedicineController::class, 'update'])->name('medicines.update');
        Route::post('inventory/{medicine}/batches', [StockController::class, 'receive'])->name('medicines.receive');
        Route::post('batches/{batch}/adjust', [StockController::class, 'adjust'])->name('batches.adjust');
        Route::post('batches/{batch}/transfer-out', [StockController::class, 'transferOut'])->name('batches.transfer-out');
        Route::post('batches/{batch}/write-off', [StockController::class, 'writeOff'])->name('batches.write-off');
    });
});

// 16. Appointments + shared calendar feed (JSON: the visible range only)
Route::get('calendar/events', CalendarEventsController::class)->middleware('throttle:120,1')->name('calendar.events');
Route::middleware('can:appointments.manage')->group(function () {
    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
});

// 17. Community health programs (Officials: schedule only)
Route::middleware('can:programs.view')->group(function () {
    Route::get('programs', [HealthProgramController::class, 'index'])->name('programs.index');
    Route::get('programs/{program}', [HealthProgramController::class, 'show'])->name('programs.show');

    Route::middleware('can:programs.manage')->group(function () {
        Route::post('programs', [HealthProgramController::class, 'store'])->name('programs.store');
        Route::put('programs/{program}', [HealthProgramController::class, 'update'])->name('programs.update');
        Route::delete('programs/{program}', [HealthProgramController::class, 'destroy'])->name('programs.destroy');
        Route::post('programs/{program}/attendance', [ProgramAttendanceController::class, 'store'])->name('programs.attendance.store');
        Route::delete('attendance/{attendance}', [ProgramAttendanceController::class, 'destroy'])->name('programs.attendance.destroy');
    });
});

// Notifications (each user's own)
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

// Health settings (thresholds, diseases, facilities, purok centres: Admin; schedules: Midwife/Admin)
Route::get('health-settings', [HealthSettingsController::class, 'index'])->name('health-settings.index');
Route::middleware('can:lookups.manage')->prefix('health-settings')->name('health-settings.')->group(function () {
    Route::post('thresholds', [HealthSettingsController::class, 'storeThreshold'])->name('thresholds.store');
    Route::put('thresholds/{threshold}', [HealthSettingsController::class, 'updateThreshold'])->name('thresholds.update');
    Route::delete('thresholds/{threshold}', [HealthSettingsController::class, 'destroyThreshold'])->name('thresholds.destroy');
    Route::post('diseases', [HealthSettingsController::class, 'storeDisease'])->name('diseases.store');
    Route::put('diseases/{disease}', [HealthSettingsController::class, 'updateDisease'])->name('diseases.update');
    Route::post('facilities', [HealthSettingsController::class, 'storeFacility'])->name('facilities.store');
    Route::put('facilities/{facility}', [HealthSettingsController::class, 'updateFacility'])->name('facilities.update');
    Route::put('puroks/{purok}/center', [HealthSettingsController::class, 'updatePurokCenter'])->name('puroks.center');
});
Route::middleware('can:schedules.manage')->prefix('health-settings')->name('health-settings.')->group(function () {
    Route::post('maternal-rules', [HealthSettingsController::class, 'storeMaternalRule'])->name('maternal-rules.store');
    Route::put('maternal-rules/{rule}', [HealthSettingsController::class, 'updateMaternalRule'])->name('maternal-rules.update');
    Route::post('vaccine-rules', [HealthSettingsController::class, 'storeVaccineRule'])->name('vaccine-rules.store');
    Route::put('vaccine-rules/{vaccineRule}', [HealthSettingsController::class, 'updateVaccineRule'])->name('vaccine-rules.update');
});
