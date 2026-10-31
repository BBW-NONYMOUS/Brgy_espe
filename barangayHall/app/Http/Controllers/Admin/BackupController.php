<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\BackupResource;
use App\Models\Backup;
use App\Services\AuditLogger;
use App\Services\Backup\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(PermissionName::BackupsManage->value);

        $latest = Backup::query()->where('status', BackupStatus::Success)->latest('id')->first();

        return Inertia::render('backups/index', [
            'backups' => BackupResource::collection(Backup::query()->with('creator:id,name')->latest('id')->paginate(15)),
            'lastSuccessfulAt' => $latest?->created_at->toIso8601String(),
            'schedule' => [
                'time' => config('barangay.backup.time'),
                'keepDaily' => config('barangay.backup.keep_daily'),
                'keepWeekly' => config('barangay.backup.keep_weekly'),
                'keepManual' => config('barangay.backup.keep_manual'),
            ],
        ]);
    }

    /**
     * "Back up now".
     */
    public function store(Request $request, BackupService $backups): RedirectResponse
    {
        Gate::authorize(PermissionName::BackupsManage->value);

        $backup = $backups->run(BackupType::Manual, $request->user());

        $backup->status === BackupStatus::Success
            ? $this->toast("Backup created: {$backup->file_name}")
            : $this->toast('Backup failed: '.($backup->error ?? 'unknown error'), 'error');

        return to_route('backups.index');
    }

    /**
     * Downloads the ENCRYPTED file. Restore with `php artisan backup:decrypt`.
     */
    public function download(Backup $backup, BackupService $backups, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize(PermissionName::BackupsManage->value);
        abort_unless($backups->exists($backup), 404, 'This backup file is no longer available.');

        $audit->log(AuditAction::Exported, $backup, "Downloaded backup {$backup->file_name}");

        return response()->streamDownload(
            function () use ($backups, $backup) {
                echo $backups->contents($backup);
            },
            $backup->file_name,
            ['Content-Type' => 'application/octet-stream'],
        );
    }
}
