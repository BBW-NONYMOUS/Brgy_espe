<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $file_name
 * @property int|null $size_bytes
 * @property BackupType $type
 * @property BackupStatus $status
 * @property string|null $error
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['file_name', 'size_bytes', 'type', 'status', 'error', 'created_by', 'completed_at'])]
class Backup extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'status' => BackupStatus::class,
            'size_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
