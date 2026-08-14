# 🐳 Panduan Setup & Deployment Server (Podman Compose)

Folder ini berisi seluruh konfigurasi otomatisasi container untuk menjalankan sistem **Maggot Monitoring** secara lengkap di server menggunakan `podman-compose` (atau `docker compose`).

---

## 🏗️ Arsitektur Layanan Container

| Layanan | Image / Base | Port | Deskripsi |
| :--- | :--- | :--- | :--- |
| **`app`** | PHP 8.3 + Nginx + Supervisor | `80:80`, `8085:8085` | Aplikasi web Laravel, Livewire, Reverb WebSockets, MQTT listener daemon, dan queue worker. |
| **`mysql`** | `mysql:8.0` | `3306:3306` | Database server relasional dengan persistent storage. |
| **`mosquitto`** | `eclipse-mosquitto:2.0` | `1883:1883`, `9001:9001` | Broker MQTT untuk komunikasi data sensor IoT ESP32. |

---

## 🚀 Cara Menjalankan di Server

### 1. Prasyarat Server
Pastikan `podman` dan `podman-compose` telah terpasang pada sistem server:
```bash
# Debian / Ubuntu
sudo apt update
sudo apt install -y podman podman-compose
```

---

### 2. Memulai Layanan
Masuk ke direktori `setup/` lalu jalankan perintah:

```bash
cd setup

# Build image dan jalankan seluruh container di background
podman-compose up -d --build
```

> **Catatan**: Saat pertama kali berjalan, container `app` akan secara otomatis:
> - Menunggu database MySQL siap menerima koneksi.
> - Menjalankan migrasi database (`php artisan migrate --force`).
> - Membuat storage symlink (`php artisan storage:link`).
> - Mengompilasi dan mengoptimalkan cache konfigurasi serta rute.
> - Menjalankan Nginx, PHP-FPM, Reverb, MQTT Daemon, dan Queue Worker via Supervisor.

---

### 3. Mengisi Data Awal (Seeding Admin & Pengaturan)
Setelah container berjalan, Anda dapat menjalankan seeder untuk membuat akun admin default dan batas fase:

```bash
podman exec -it maggot_app php artisan db:seed --force
```

---

### 4. Perintah Operasional Penting

#### 📊 Memeriksa Status Container
```bash
podman-compose ps
```

#### 📜 Melihat Log Real-Time
```bash
# Log seluruh layanan
podman-compose logs -f

# Log aplikasi Laravel saja
podman logs -f maggot_app

# Log MQTT Broker saja
podman logs -f maggot_mosquitto
```

#### 🔍 Memeriksa Status Daemon Internal (Supervisor)
```bash
podman exec -it maggot_app supervisorctl status
```
*Akan menampilkan status:*
- `nginx` (RUNNING)
- `php-fpm` (RUNNING)
- `laravel-reverb` (RUNNING)
- `mqtt-listener` (RUNNING)
- `laravel-queue` (RUNNING)
- `laravel-scheduler` (RUNNING)

#### 🛑 Menghentikan Layanan
```bash
podman-compose down
```

#### 🔄 Memperbarui Aplikasi (Setelah Ada Update Git)
```bash
git pull origin main
cd setup
podman-compose up -d --build
```

---

## 📡 Konfigurasi Hardware IoT ESP32
Pada firmware ESP32, arahkan target MQTT ke IP Address Server Anda:
- **Broker IP / Host**: `<IP_SERVER_ANDA>`
- **Port**: `1883`
- **Topic Publikasi Data**: `environmentData`
- **Topic Batas Lingkungan**: `environmentLimit`
