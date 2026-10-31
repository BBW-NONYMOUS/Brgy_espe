<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per digest/alert sent; lets scheduled checks re-run without
 * notifying anyone twice.
 *
 * @property int $id
 * @property string $dedupe_key
 * @property string $notification_type
 * @property int $recipients_count
 * @property CarbonImmutable $created_at
 */
#[Fillable(['dedupe_key', 'notification_type', 'recipients_count'])]
class NotificationDispatch extends Model
{
    public const UPDATED_AT = null;
}
