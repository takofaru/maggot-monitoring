<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\PhaseSetting;
use App\Models\Cycle;
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
}
