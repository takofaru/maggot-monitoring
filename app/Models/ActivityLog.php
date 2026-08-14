<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'user_id',
        'user_name',
        'metadata',
        'is_read',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (ActivityLog $activityLog) {
            try {
                event(new \App\Events\NotificationCreated($activityLog));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to broadcast NotificationCreated: ' . $e->getMessage());
            }
        });
    }
}
