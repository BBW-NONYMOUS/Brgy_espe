<?php

namespace App\Http\Controllers\Surveillance;

use App\Enums\OutbreakAlertStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OutbreakAlertResource;
use App\Models\OutbreakAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outbreak alert summary (Officials included — no names) and the
 * acknowledge → monitoring → closed workflow (Midwife/Admin).
 */
class OutbreakAlertController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', OutbreakAlert::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OutbreakAlertStatus::class)],
        ]);

        $alerts = OutbreakAlert::query()
            ->with(['disease', 'purok', 'acknowledgedBy:id,name', 'closedBy:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'monitoring' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('diseases/alerts', [
            'alerts' => OutbreakAlertResource::collection($alerts),
            'filters' => ['status' => $filters['status'] ?? null],
            'options' => ['statuses' => OutbreakAlertStatus::options()],
            'canManage' => Gate::allows('update', new OutbreakAlert),
        ]);
    }

    public function acknowledge(Request $request, OutbreakAlert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);
        $this->ensureOpen($alert);

        $alert->update(['acknowledged_by' => $request->user()?->id, 'acknowledged_at' => now()]);

        $this->toast('Alert acknowledged.');

        return back();
    }

    /**
     * Response under way; keep watching for new cases.
     */
    public function monitor(Request $request, OutbreakAlert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);
        $this->ensureOpen($alert);

        $alert->update([
            'status' => OutbreakAlertStatus::Monitoring,
            'acknowledged_by' => $alert->acknowledged_by ?? $request->user()?->id,
            'acknowledged_at' => $alert->acknowledged_at ?? now(),
        ]);

        $this->toast('Alert moved to monitoring.');

        return back();
    }

    public function close(Request $request, OutbreakAlert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);
        $this->ensureOpen($alert);

        $data = $request->validate(['closing_note' => ['required', 'string', 'max:255']]);

        $alert->update([
            'status' => OutbreakAlertStatus::Closed,
            'closed_by' => $request->user()?->id,
            'closed_at' => now(),
            'closing_note' => $data['closing_note'],
        ]);

        $this->toast('Alert closed.');

        return back();
    }

    private function ensureOpen(OutbreakAlert $alert): void
    {
        if ($alert->status === OutbreakAlertStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'This alert is already closed. A new alert is raised if the threshold is crossed again.']);
        }
    }
}
