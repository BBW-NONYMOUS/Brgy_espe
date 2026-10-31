<?php

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\RoleName;
use App\Models\Backup;
use App\Services\Backup\BackupFailedException;
use App\Services\Backup\BackupService;
use App\Services\Backup\DatabaseDumper;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('local');

    $this->app->instance(DatabaseDumper::class, new class implements DatabaseDumper
    {
        public function dumpTo(string $path): void
        {
            file_put_contents($path, "-- test dump\nINSERT INTO residents VALUES (1, 'Dela Cruz');\n");
        }
    });
});

test('"Back up now" creates an encrypted, compressed backup that can be decrypted', function () {
    $admin = actingAsRole(RoleName::Admin);

    $this->post(route('backups.store'))->assertRedirect(route('backups.index'));

    $backup = Backup::query()->sole();
    $stored = Storage::disk('local')->get('backups/'.$backup->file_name);

    expect($backup->status)->toBe(BackupStatus::Success)
        ->and($backup->type)->toBe(BackupType::Manual)
        ->and($backup->created_by)->toBe($admin->id)
        ->and($stored)->not->toContain('Dela Cruz')
        ->and(app(BackupService::class)->decrypt($stored))->toContain("INSERT INTO residents VALUES (1, 'Dela Cruz')");
});

test('a failed dump is recorded as failed, not as success', function () {
    $this->app->instance(DatabaseDumper::class, new class implements DatabaseDumper
    {
        public function dumpTo(string $path): void
        {
            throw new BackupFailedException('mysqldump: Access denied');
        }
    });

    $this->artisan('backup:run')->assertFailed();

    expect(Backup::query()->sole()->status)->toBe(BackupStatus::Failed)
        ->and(Backup::query()->sole()->error)->toContain('Access denied');
});

test('admins can download the encrypted file; other roles cannot', function () {
    actingAsRole(RoleName::Admin);
    $backup = app(BackupService::class)->run(BackupType::Manual);

    $this->get(route('backups.download', $backup))->assertOk()->assertDownload($backup->file_name);

    actingAsRole(RoleName::Bhw);
    $this->get(route('backups.download', $backup))->assertForbidden();
    $this->post(route('backups.store'))->assertForbidden();
});

test('rotation keeps recent daily backups, Sunday weeklies and the latest manual backups', function () {
    $service = app(BackupService::class);

    // 30 nightly backups.
    foreach (range(30, 1) as $daysAgo) {
        $this->travelTo(now()->startOfDay()->subDays($daysAgo)->setTime(23, 0));
        $service->run(BackupType::Scheduled);
        $this->travelBack();
    }

    $service->prune();

    $kept = Backup::query()->where('status', BackupStatus::Success)->get();
    $old = $kept->filter(fn (Backup $b) => $b->created_at->lt(now()->subDays(14)));

    expect($kept->count())->toBeLessThan(30)
        ->and($old->every(fn (Backup $b) => $b->created_at->isSunday()))->toBeTrue()
        ->and(Backup::query()->where('status', BackupStatus::Pruned)->count())->toBe(30 - $kept->count())
        ->and(Storage::disk('local')->files('backups'))->toHaveCount($kept->count());
});

test('the scheduler runs the nightly backup', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('backup:run')->assertSuccessful();
});
