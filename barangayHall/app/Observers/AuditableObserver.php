<?php

namespace App\Observers;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes create / update / archive / restore / permanent delete events for
 * any model that declares #[ObservedBy(AuditableObserver::class)].
 */
class AuditableObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->log(
            AuditAction::Created,
            $model,
            'Created '.$this->label($model),
            newValues: AuditLogger::sanitize($model, $model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changes = AuditLogger::sanitize($model, $model->getChanges());

        // Ignore saves that only touched timestamps or the deleted_at column
        // (archive/restore are logged by their own events).
        unset($changes['deleted_at'], $changes['updated_by']);

        if (method_exists($model, 'auditExcludedAttributes')) {
            $changes = array_diff_key($changes, array_flip($model->auditExcludedAttributes()));
        }

        if ($changes === []) {
            return;
        }

        $original = AuditLogger::sanitize($model, array_intersect_key($model->getRawOriginal(), $changes));

        $this->audit->log(
            AuditAction::Updated,
            $model,
            'Updated '.$this->label($model).': '.implode(', ', array_keys($changes)),
            oldValues: $original,
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $permanent = ! method_exists($model, 'isForceDeleting') || $model->isForceDeleting();

        $this->audit->log(
            $permanent ? AuditAction::ForceDeleted : AuditAction::Deleted,
            $model,
            ($permanent ? 'Permanently deleted ' : 'Archived ').$this->label($model),
            oldValues: $permanent ? AuditLogger::sanitize($model, $model->getRawOriginal()) : null,
        );
    }

    public function restored(Model $model): void
    {
        $this->audit->log(AuditAction::Restored, $model, 'Restored '.$this->label($model));
    }

    private function label(Model $model): string
    {
        $name = Str::of(class_basename($model))->snake(' ')->toString();
        $reference = $model->getAttribute('resident_code')
            ?? $model->getAttribute('household_no')
            ?? $model->getAttribute('username')
            ?? '#'.$model->getKey();

        return "{$name} {$reference}";
    }
}
