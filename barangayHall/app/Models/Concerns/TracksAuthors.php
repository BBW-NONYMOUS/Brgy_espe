<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by / updated_by from the logged-in user.
 */
trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function (self $model) {
            if (Auth::id() !== null) {
                $model->getAttribute('created_by') ?? $model->setAttribute('created_by', Auth::id());
                $model->getAttribute('updated_by') ?? $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::updating(function (self $model) {
            if (Auth::id() !== null) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
