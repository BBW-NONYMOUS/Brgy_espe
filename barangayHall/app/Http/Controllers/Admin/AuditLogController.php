<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only. There are no routes to edit or delete audit entries.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(PermissionName::AuditView->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'table_name' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where('description', 'like', "%{$s}%"))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $q, $action) => $q->where('action', $action))
            ->when($filters['table_name'] ?? null, fn (Builder $q, $table) => $q->where('table_name', $table))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('audit-logs/index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
                'action' => $filters['action'] ?? null,
                'table_name' => $filters['table_name'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'options' => [
                'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name]),
                'actions' => AuditAction::options(),
                'tables' => AuditLog::query()->whereNotNull('table_name')->distinct()->orderBy('table_name')->pluck('table_name')
                    ->map(fn (string $t) => ['value' => $t, 'label' => str_replace('_', ' ', $t)]),
            ],
        ]);
    }
}
