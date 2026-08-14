<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelemetryReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public float $temperature;
    public float $humidity;
    public string $timestamp;
    public bool $isDeviceOnline;

    public function __construct(float $temperature, float $humidity, ?string $timestamp = null, bool $isDeviceOnline = true)
    {
        $this->temperature = $temperature;
        $this->humidity = $humidity;
        $this->timestamp = $timestamp ?? now()->format('H:i:s');
        $this->isDeviceOnline = $isDeviceOnline;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('maggot-telemetry'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'telemetry.received';
    }
}
