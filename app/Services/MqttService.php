<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\PhaseSetting;
use App\Models\Cycle;
use App\Models\EnvironmentLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MqttService
{
    /**
     * Publish payload to an MQTT topic.
     *
     * @param string $topic
     * @param array|string $payload
     * @return bool
     */
    public static function publish(string $topic, array|string $payload): bool
    {
        $host = config('services.mqtt.host', env('MQTT_HOST', '127.0.0.1'));
        $port = (int) config('services.mqtt.port', env('MQTT_PORT', 1883));
        $clientId = 'maggot-publisher-' . uniqid();

        $message = is_array($payload) ? json_encode($payload) : (string) $payload;

        try {
            $mqtt = new MqttClient($host, $port, $clientId);

            $connectionSettings = (new ConnectionSettings)
                ->setConnectTimeout(2)
                ->setSocketTimeout(2);

            $mqtt->connect($connectionSettings, true);
            $mqtt->publish($topic, $message, 0);
            $mqtt->disconnect();

            Log::info("MQTT Published to [{$topic}]: {$message}");
            return true;
        } catch (\Throwable $e) {
            Log::warning("MQTT Publish failed to [{$topic}] on {$host}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync and publish active phase limits to 'environmentLimit' topic without redundant keys.
     *
     * @param string|null $phaseName
     * @return bool
     */
    public static function syncActivePhaseLimit(?string $phaseName = null): bool
    {
        if (!$phaseName) {
            $activeCycle = Cycle::where('is_active', true)->first();
            $phaseName = $activeCycle ? strtolower($activeCycle->current_phase) : 'penetasan';
        }

        if (!in_array($phaseName, ['penetasan', 'pembesaran', 'prepupa'])) {
            $phaseName = 'penetasan';
        }

        $setting = PhaseSetting::where('phase_name', $phaseName)->first();
        if (!$setting) {
            return false;
        }

        $payload = [
            'phase_name' => $phaseName,
            'temp_min'   => (float) $setting->temp_bottom,
            'temp_max'   => (float) $setting->temp_top,
            'humid_min'  => (float) $setting->humid_bottom,
            'humid_max'  => (float) $setting->humid_top,
        ];

        return self::publish('environmentLimit', $payload);
    }

    /**
     * Fetch retained environmentData from broker fast and store in DB/cache.
     *
     * @return array|null
     */
    public static function fetchRetainedEnvironmentData(): ?array
    {
        $host = config('services.mqtt.host', env('MQTT_HOST', '127.0.0.1'));
        $port = (int) config('services.mqtt.port', env('MQTT_PORT', 1883));

        // Cek cepat ketersediaan port broker (probe 0.05s)
        $fp = @fsockopen($host, $port, $errno, $errstr, 0.05);
        if (!$fp) {
            return null;
        }
        fclose($fp);

        $clientId = 'maggot-fetch-' . uniqid();
        $received = null;

        try {
            $mqtt = new MqttClient($host, $port, $clientId);
            $connectionSettings = (new ConnectionSettings)
                ->setConnectTimeout(1)
                ->setSocketTimeout(1);

            $mqtt->connect($connectionSettings, true);

            $mqtt->subscribe('environmentData', function (string $topic, string $message) use (&$received, $mqtt) {
                $data = json_decode($message, true);
                if (is_array($data)) {
                    $temp = $data['temperature'] ?? $data['temp'] ?? null;
                    $humid = $data['humidity'] ?? $data['humid'] ?? null;

                    if ($temp !== null && $humid !== null) {
                        $now = now();
                        $received = [
                            'temperature' => (float) $temp,
                            'humidity'    => (float) $humid,
                            'timestamp'   => $now,
                        ];

                        $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();
                        
                        $latestLog = EnvironmentLog::latest('id')->first();
                        $shouldInsert = !$latestLog ||
                            abs($now->diffInSeconds($latestLog->timestamp ?? $latestLog->created_at, false)) >= 8;

                        if ($shouldInsert) {
                            EnvironmentLog::create([
                                'cycle_id'    => $activeCycle?->id,
                                'temperature' => (float) $temp,
                                'humidity'    => (float) $humid,
                                'timestamp'   => $now,
                            ]);
                        }

                        Cache::put('device_last_seen', $now->toIso8601String(), 120);
                    }
                }
                $mqtt->interrupt();
            }, 0);

            $mqtt->loop(true, true, 1);
            $mqtt->disconnect();
        } catch (\Throwable $e) {
            // Broker timeout / unreachable
        }

        return $received;
    }
}
