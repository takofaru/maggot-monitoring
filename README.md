# Maggot Monitoring System

Maggot Monitoring adalah sistem informasi dan pemantauan budidaya maggot Black Soldier Fly (BSF) berbasis Internet of Things (IoT). Aplikasi ini dirancang untuk memantau kondisi lingkungan kandang secara real-time, mencatat aktivitas observasi harian, mengelola siklus dan fase budidaya, menganalisis performa pertumbuhan maggot, serta mendistribusikan notifikasi anomali secara instan ke seluruh antarmuka pengguna.

---

## Fitur Utama

- **Monitoring Lingkungan Real-Time**: Pemantauan telemetri suhu dan kelembapan secara langsung menggunakan sensor IoT berbasis protokol MQTT.
- **Manajemen Siklus & Fase Budidaya**: Pengaturan alur hidup maggot dari fase Penetasan, Pembesaran, hingga Prepupa (Panen) lengkap dengan batas ideal suhu dan kelembapan yang dapat disesuaikan per fase.
- **Pencatatan Observasi Harian**: Pencatatan data pakan harian, penambahan bobot maggot, dan sinkronisasi otomatis dengan data lingkungan saat observasi dilakukan.
- **Laporan & Analisis**: Evaluasi pertumbuhan, Feed Conversion Ratio (FCR), pertambahan biomassa bersih, ringkasan performa per fase, serta ekspor data ke format CSV dan cetak laporan.
- **Sistem Notifikasi Real-Time (WebSockets)**: Pemberitahuan instan via push notification (pop-up toast) dan indikator lonceng ketika terjadi penambahan catatan observasi, anomali batas suhu/kelembapan, serta perubahan status koneksi perangkat IoT (online/offline).
- **Manajemen Akun & Hak Akses**: Manajemen pengguna dengan pembagian peran Admin dan Petugas (User), pembaruan profil, dan pengaturan kata sandi.

---

## Teknologi yang Digunakan

- **Backend**: Laravel 11/12, PHP 8.3
- **Frontend & Reaktivitas**: Livewire 3 (Single File Component), Alpine.js, Tailwind CSS, Chart.js
- **Komunikasi Real-Time**: Laravel Reverb (WebSockets Server), Laravel Echo, Pusher-JS
- **Protokol IoT**: MQTT (php-mqtt/client, Eclipse Mosquitto Broker)
- **Database**: MySQL 8.0 / MariaDB
- **Containerization**: Podman Compose / Docker Compose, Nginx, Supervisor

---

## Petunjuk Menjalankan di Lingkungan Pengembangan (Development)

### Prasyarat Sistem

- PHP >= 8.3 (dengan ekstensi: `pdo_mysql`, `bcmath`, `sockets`, `pcntl`, `gd`, `zip`)
- Composer >= 2.x
- Node.js >= 20.x dan NPM
- Database MySQL / MariaDB
- Broker MQTT (Eclipse Mosquitto)

### Langkah-langkah Instalasi

1. **Clone Repository**
   ```bash
   git clone <URL_REPOSITORY>
   cd maggot-monitoring
   ```

2. **Instal Dependensi PHP**
   ```bash
   composer install
   ```

3. **Instal Dependensi Frontend**
   ```bash
   npm install
   ```

4. **Konfigurasi Environment**
   Salin file konfigurasi environment dan sesuaikan parameter database, MQTT, dan Reverb:
   ```bash
   cp .env.example .env
   ```

   Pastikan konfigurasi utama pada file `.env` telah disesuaikan:
   ```env
   APP_NAME="Maggot Monitoring"
   APP_ENV=local
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=maggot-db
   DB_USERNAME=root
   DB_PASSWORD=root

   MQTT_HOST=127.0.0.1
   MQTT_PORT=1883

   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=344853
   REVERB_APP_KEY=esqntyvtebpbanvf1ihg
   REVERB_APP_SECRET=j4w7qerwwniknhi1v7yt
   REVERB_HOST="localhost"
   REVERB_PORT=8085
   REVERB_SERVER_PORT=8085
   REVERB_SCHEME=http

   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"
   ```

5. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

6. **Migrasi dan Seeding Database**
   ```bash
   php artisan migrate --seed
   ```
   *Seeder akan menginisialisasi data inti deployment:*
   - **User Admin**: Username: `admin` | Password: `password`
   - **Phase Settings**: Batas ideal untuk fase Penetasan, Pembesaran, dan Prepupa.
   - **First Cycle (Siklus 1)**: Siklus pertama dalam fase penetasan (start_date otomatis diset jika alat online, atau dimulai saat alat pertama kali terhubung).

7. **Kompilasi Aset Frontend**
   ```bash
   npm run build
   ```

### Menjalankan Aplikasi

#### Opsi 1: Menjalankan Seluruh Service Sekaligus (Direkomendasikan)
Jalankan perintah concurrently bawaan:
```bash
composer run dev
```
Perintah ini akan menjalankan secara bersamaan:
- HTTP Web Server (`php artisan serve`)
- WebSocket Server (`php artisan reverb:start --port=8085`)
- Queue Worker (`php artisan queue:listen`)
- Vite Dev Server (`npm run dev`)

#### Opsi 2: Menjalankan Service Secara Terpisah
Buka terminal terpisah untuk setiap layanan berikut:

1. **Web Server**:
   ```bash
   php artisan serve
   ```
2. **WebSocket Server Reverb**:
   ```bash
   php artisan reverb:start --port=8085
   ```
3. **Daemon Subscriber MQTT (IoT Ingestion)**:
   ```bash
   php artisan mqtt:listen --topic=environmentData
   ```
4. **Vite Assets Server**:
   ```bash
   npm run dev
   ```

Aplikasi dapat diakses melalui browser pada alamat: `http://localhost:8000`.

---

## Petunjuk Deployment ke Server (Production)

Deployment ke server dilakukan menggunakan container stack berbasis `podman-compose` atau `docker compose` yang dikelola secara terpisah pada repositori **`maggot-monitoring-container`**.

### Arsitektur Container

Container stack terdiri atas 3 layanan utama:
- **`app`**: PHP 8.3-FPM, Nginx Web Server, Laravel Reverb (port 8085), MQTT Listener Daemon, Queue Worker, dan Scheduler yang dikelola oleh Supervisor.
- **`mysql`**: MySQL 8.0 dengan volume persisten data.
- **`mosquitto`**: Eclipse Mosquitto MQTT Broker (port 1883) untuk komunikasi perangkat keras IoT.

### Langkah-langkah Deployment

1. **Masuk ke Direktori Container Stack**
   ```bash
   cd ../maggot-monitoring-container
   ```

2. **Jalankan Container Stack**
   Gunakan Podman Compose:
   ```bash
   podman-compose up -d --build
   ```
   Atau Docker Compose:
   ```bash
   docker compose up -d --build
   ```

   Saat inisialisasi awal, container `app` akan secara otomatis:
   - Menunggu koneksi MySQL siap.
   - Menjalankan migrasi database (`php artisan migrate --force`).
   - Membuat symlink storage (`php artisan storage:link`).
   - Mengoptimalkan cache konfigurasi, rute, dan tampilan Blade.
   - Memulai Supervisor untuk menjalankan Nginx, PHP-FPM, Reverb, dan MQTT Listener.

3. **Inisialisasi Data Awal (Seeding)**
   Jalankan seeder akun dan konfigurasi awal pada container aplikasi:
   ```bash
   podman exec -it maggot_app php artisan db:seed --force
   ```
   *(Gunakan `docker exec -it maggot_app ...` jika menggunakan Docker)*

### Manajemen dan Monitoring Server

- **Melihat status container**:
  ```bash
  podman-compose ps
  ```
- **Melihat status proses Supervisor di dalam container**:
  ```bash
  podman exec -it maggot_app supervisorctl status
  ```
- **Melihat log aplikasi**:
  ```bash
  podman logs -f maggot_app
  ```
- **Melihat log broker MQTT**:
  ```bash
  podman logs -f maggot_mosquitto
  ```
- **Menghentikan seluruh layanan**:
  ```bash
  podman-compose down
  ```
- **Pembaruan Aplikasi dari Git**:
  ```bash
  # 1. Update source code
  cd ../maggot-monitoring
  git pull origin main

  # 2. Re-build dan restart container
  cd ../maggot-monitoring-container
  podman-compose up -d --build
  ```

---

## Integrasi Hardware IoT (ESP32)

Konfigurasikan firmware mikrokontroler (ESP32) untuk mengirim data telemetri ke server:

- **Broker Host**: `<IP_ADDRESS_SERVER>`
- **Broker Port**: `1883`
- **Topic Publikasi Data (ESP32 -> Server)**: `environmentData`
  Format JSON payload:
  ```json
  {
    "temperature": 28.5,
    "humidity": 70.2
  }
  ```
- **Topic Batas Fase (Server -> ESP32)**: `environmentLimit`
  Format JSON payload:
  ```json
  {
    "phase_name": "penetasan",
    "temp_min": 26.5,
    "temp_max": 32.0,
    "humid_min": 60.0,
    "humid_max": 85.0
  }
  ```

---

## Struktur Direktori Utama

```
maggot-monitoring/
├── app/
│   ├── Console/Commands/       # Perintah Artisan (termasuk MqttListenCommand)
│   ├── Events/                 # Event broadcast WebSockets (NotificationCreated, TelemetryReceived)
│   ├── Models/                 # Model Eloquent (User, Cycle, ObservationLog, EnvironmentLog, dll.)
│   └── Services/               # Layanan bisnis (NotificationService, MqttService)
├── database/
│   ├── migrations/             # Skema tabel database
│   └── seeders/                # Data awal sistem dan akun pengguna
├── resources/
│   ├── css/                    # Desain styling aplikasi
│   ├── js/                     # Skrip frontend dan konfigurasi Laravel Echo
│   └── views/
│       ├── components/         # Komponen UI Livewire (Sidebar, Navbar, Modal, Notification Bell)
│       ├── layouts/            # Layout utama aplikasi
│       └── pages/              # Halaman fungsional (Dashboard, Observasi, Laporan, Pengaturan, Akun)
└── routes/                     # Definisi rute web, console, dan channel broadcast
```

---

## Lisensi

Proyek ini dikembangkan untuk sistem pemantauan budidaya maggot dan didistribusikan di bawah lisensi MIT.
