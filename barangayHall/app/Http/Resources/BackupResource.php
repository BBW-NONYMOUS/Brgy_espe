<?php

namespace App\Http\Resources;

use App\Enums\BackupStatus;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Backup
 */
class BackupResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Backup $backup */
        $backup = $this->resource;

        return [
            'id' => $backup->id,
            'file_name' => $backup->file_name,
            'size_bytes' => $backup->size_bytes,
            'type' => $this->enum($backup->type),
            'status' => $this->enum($backup->status),
            'error' => $backup->error,
            'created_by' => $this->whenLoaded('creator', fn () => $backup->creator?->name),
            'created_at' => $this->dateTime($backup->created_at),
            'completed_at' => $this->dateTime($backup->completed_at),
            'downloadable' => $backup->status === BackupStatus::Success,
        ];
    }
}
