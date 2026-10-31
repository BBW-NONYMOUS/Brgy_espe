<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'created_at' => $this->dateTime($log->created_at),
            'user' => $this->whenLoaded('user', fn () => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'username' => $log->user->username,
            ] : null),
            'action' => $this->enum($log->action),
            'table_name' => $log->table_name,
            'record_id' => $log->record_id,
            'description' => $log->description,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
        ];
    }
}
