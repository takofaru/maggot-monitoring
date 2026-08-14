<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $notification;

    public function __construct(ActivityLog $activityLog)
    {
        $this->notification = [
            'id'          => $activityLog->id,
            'type'        => $activityLog->type,
            'title'       => $activityLog->title,
            'description' => $activityLog->description,
            'user_name'   => $activityLog->user_name,
            'metadata'    => $activityLog->metadata,
            'is_read'     => $activityLog->is_read,
            'created_at'  => $activityLog->created_at ? $activityLog->created_at->toIso8601String() : now()->toIso8601String(),
            'time'        => $activityLog->created_at ? $activityLog->created_at->diffForHumans() : 'Baru saja',
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('maggot-notifications'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
