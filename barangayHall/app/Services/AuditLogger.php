<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Single entry point for writing to the append-only audit log.
 */
class AuditLogger
{
    public const REDACTED = '[encrypted]';

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        AuditAction $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
    ): AuditLog {
        $userId = $user !== null ? $user->id : Auth::id();

        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $subject?->getTable(),
            'record_id' => $subject?->getKey(),
            'description' => $description !== null ? Str::limit($description, 250) : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ]);
    }

    /**
     * Strip hidden attributes and replace encrypted ones so plaintext
     * sensitive data never lands in the log.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function sanitize(Model $model, array $attributes): array
    {
        $hidden = array_merge($model->getHidden(), ['updated_at', 'created_at']);
        $casts = $model->getCasts();

        $clean = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $hidden, true)) {
                continue;
            }

            $isEncrypted = isset($casts[$key]) && str_starts_with((string) $casts[$key], 'encrypted');
            $clean[$key] = $isEncrypted && $value !== null ? self::REDACTED : $value;
        }

        return $clean;
    }
}
