<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\EnvironmentLog;
use App\Models\Cycle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MqttListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen {--topic=environmentData : The MQTT topic to subscribe to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT topic environmentData and store telemetry sensor data into database and cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = config('services.mqtt.host', env('MQTT_HOST', '127.0.0.1'));
        $port = (int) config('services.mqtt.port', env('MQTT_PORT', 1883));
        $topic = $this->option('topic') ?: 'environmentData';
        $clientId = 'maggot-listener-' . uniqid();

        $this->info("=================================================");
        $this->info("   MAGGOT MONITORING - MQTT SUBSCRIBER DAEMON   ");
        $this->info("=================================================");
        $this->info(" • Broker Target : {$host}:{$port}");
        $this->info(" • Topik Dengar  : {$topic}");
        $this->info(" • Status        : Menghubungkan ke broker...");

        try {
            $mqtt = new MqttClient($host, $port, $clientId);

            $connectionSettings = (new ConnectionSettings)
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(5)
                ->setSocketTimeout(5)
                ->setResendTimeout(2000);

            $mqtt->connect($connectionSettings, true);
            $this->info("✔ Sukses terhubung ke broker MQTT.");
            $this->info("📡 Mulai mendengarkan topik '{$topic}' (Tekan Ctrl+C untuk berhenti)...\n");

            $mqtt->subscribe($topic, function (string $topic, string $message) {
                try {
                    $data = json_decode($message, true);
                    if (!is_array($data)) {
                        return;
                    }

                    $temp = $data['temperature'] ?? $data['temp'] ?? null;
                    $humid = $data['humidity'] ?? $data['humid'] ?? null;

                    if ($temp === null || $humid === null) {
                        return;
                    }

                    $now = now();
                    $activeCycle = Cycle::where('is_active', true)->first() ?? Cycle::latest('id')->first();

                    // Jika siklus aktif belum memiliki start_date (karena sebelumnya alat offline), mulai start_date hari ini
                    if ($activeCycle && $activeCycle->start_date === null) {
                        $activeCycle->update(['start_date' => $now->toDateString()]);
                    }

                    $log = EnvironmentLog::create([
                        'cycle_id'    => $activeCycle?->id,
                        'temperature' => (float) $temp,
                        'humidity'    => (float) $humid,
                        'timestamp'   => $now,
                    ]);

                    // Simpan timestamp terakhir di cache untuk deteksi status instan
                    Cache::put('device_last_seen', $now->toIso8601String(), 120);

                    // Evaluasi anomali suhu & kelembapan serta status perangkat
                    \App\Services\NotificationService::evaluateEnvironmentTelemetry((float) $temp, (float) $humid, $activeCycle);
                    \App\Services\NotificationService::evaluateDeviceStatus(true);

                    $this->line(sprintf(
                        "<info>[%s]</info> 📥 <comment>[%s]</comment> Suhu: <bold>%.2f°C</bold> | Humid: <bold>%.2f%%</bold> -> Tersimpan ke DB (ID: %d, Siklus: %s)",
                        $now->format('H:i:s'),
                        $topic,
                        (float) $temp,
                        (float) $humid,
                        $log->id,
                        $activeCycle ? "Siklus {$activeCycle->id}" : '-'
                    ));
                } catch (\Throwable $e) {
                    $this->error("Gagal memproses pesan: " . $e->getMessage());
                    Log::error("MQTT Process Error: " . $e->getMessage());
                }
            }, 0);

            $mqtt->loop(true);
        } catch (\Throwable $e) {
            $this->error("Gagal terhubung ke MQTT Broker: " . $e->getMessage());
            Log::error("MQTT Daemon Connection Failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
