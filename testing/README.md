# Panduan Pengujian & Simulasi IoT Monitoring Budidaya Maggot

Dokumen ini berisi panduan lengkap untuk menjalankan lingkungan pengujian (*testing environment*) monitoring budidaya maggot, yang mencakup layanan database **MariaDB (`maggot-db`)**, **phpMyAdmin**, **Eclipse Mosquitto (MQTT Broker)**, serta script simulator mikrokontroler berbasis Python.

---

## 📁 Struktur Direktori Pengujian

```
testing/
├── docker-compose.yml           # Konfigurasi container MariaDB, phpMyAdmin, dan Mosquitto
├── mosquitto/
│   └── config/
│       └── mosquitto.conf      # Konfigurasi broker Mosquitto (port 1883 & 9001)
├── requirements.txt            # Dependensi Python (paho-mqtt)
├── simulator.py                # Simulator mikrokontroler (ESP32) dengan pengiriman sekuensial
├── test_publisher.py           # Skrip pengujian perubahan data (environmentLimit & totalDay)
└── README.md                   # Panduan lengkap dalam Bahasa Indonesia
```

---

## 🚀 1. Menjalankan Layanan (Docker / Podman)

Anda dapat memilih menggunakan **Docker** atau alternatifnya seperti **Podman**.

### 🔹 Opsi A: Menggunakan Docker

Pastikan Docker Engine dan Docker Compose sudah terpasang, lalu jalankan:

```bash
cd testing

# Menjalankan seluruh container di latar belakang (background)
docker compose up -d

# Memeriksa status container
docker compose ps

# Melihat log container
docker compose logs -f
```

---

### 🔹 Opsi B: Menggunakan Podman (Alternatif)

Jika Anda menggunakan Podman, Anda dapat menggunakan perintah berikut:

#### Menggunakan `podman compose`:
```bash
cd testing

# Menjalankan container dengan podman compose
podman compose up -d

# Memeriksa status container
podman compose ps
```

#### Atau menggunakan `podman-compose`:
```bash
podman-compose up -d
```

#### Atau menjalankan manual via Podman Pod (Tanpa compose):
```bash
# 1. Buat network atau pod bersama
podman network create maggot-net

# 2. Jalankan MariaDB
podman run -d --name maggot_mariadb \
  --network maggot-net \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=maggot-db \
  -e MYSQL_USER=maggot \
  -e MYSQL_PASSWORD=maggot \
  -p 3306:3306 \
  -v maggot_mariadb_data:/var/lib/mysql \
  mariadb:10.11

# 3. Jalankan phpMyAdmin
podman run -d --name maggot_phpmyadmin \
  --network maggot-net \
  -e PMA_HOST=maggot_mariadb \
  -e PMA_PORT=3306 \
  -p 8080:80 \
  phpmyadmin/phpmyadmin:latest

# 4. Jalankan Mosquitto
podman run -d --name maggot_mosquitto \
  --network maggot-net \
  -p 1883:1883 -p 9001:9001 \
  -v $(pwd)/mosquitto/config/mosquitto.conf:/mosquitto/config/mosquitto.conf:ro,Z \
  eclipse-mosquitto:2
```

---

## 📊 2. Informasi Layanan & Kredensial

| Layanan | Port Host | Host Internal | Kredensial / Keterangan |
| :--- | :--- | :--- | :--- |
| **MariaDB** | `3306` | `mariadb:3306` | **Database**: `maggot-db`<br>**User**: `maggot` / **Password**: `maggot`<br>**Root Password**: `root` |
| **phpMyAdmin** | `8080` | `phpmyadmin:80` | Akses Web: [http://localhost:8080](http://localhost:8080)<br>**Server**: `mariadb`<br>**Username**: `root`<br>**Password**: `root` |
| **Mosquitto (MQTT)** | `1883` | `mosquitto:1883` | Protokol MQTT TCP standar (Akses anonim diaktifkan) |
| **Mosquitto (WS)** | `9001` | `mosquitto:9001` | Protokol MQTT WebSocket |

---

## 🐍 3. Persiapan Lingkungan Python

Pasang pustaka yang dibutuhkan (`paho-mqtt`):

```bash
cd testing
pip install -r requirements.txt
```

*Jika menggunakan Python Virtual Environment:*
```bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

---

## 📡 4. Spesifikasi Topik MQTT & Perilaku Simulator

Mikrokontroler disimulasikan melalui script [`simulator.py`](file:///home/dimas/maggot-monitoring/testing/simulator.py) dengan aturan komunikasi:

```
                            +-------------------------------+
                            |   Broker Mosquitto (MQTT)     |
                            +---------------+---------------+
                                            |
               +----------------------------+----------------------------+
               | (Kirim Sekuensial)         | (Deteksi Perubahan)        | (Deteksi Perubahan)
               v                            v                            v
       [environmentData]            [environmentLimit]              [totalDay]
               |                            |                            |
               |                            |                            |
       +-------+----------------------------+----------------------------+-------+
       |                 Simulator Mikrokontroler (ESP32)                        |
       |                    (`testing/simulator.py`)                             |
       +-------------------------------------------------------------------------+
```

### 1. Topik `environmentData` (**HANYA PUBLISH**)
- Mikrokontroler **secara sekuensial dan berurutan** mengirimkan data telemetri suhu & kelembapan setiap 5 detik (dapat diatur).
- Menyertakan nomor urut (`seq`), ID perangkat, suhu, kelembapan, status ambang, dan stempel waktu (*timestamp*).

**Contoh Payload JSON yang Dikirim:**
```json
{
  "seq": 1,
  "device_id": "ESP32-Maggot-5821",
  "temperature": 29.45,
  "humidity": 71.80,
  "Temp": 29.45,
  "Humid": 71.80,
  "day": 5,
  "status": "NORMAL",
  "timestamp": "2026-08-08T08:35:00+07:00",
  "active_limits": {
    "TempTop": 35.0,
    "TempBottom": 25.0,
    "HumidTop": 85.0,
    "HumidBottom": 60.0
  }
}
```

---

### 2. Topik `environmentLimit` (**HANYA SUBSCRIBE & DETEKSI PERUBAHAN**)
- Mikrokontroler mendengarkan pembaruan batas ambang suhu dan kelembapan.
- **Deteksi Perubahan:** Jika nilai `TempTop`, `TempBottom`, `HumidTop`, atau `HumidBottom` berbeda dari nilai sebelumnya, mikrokontroler akan mencatat highlight perubahan (`NILAI_LAMA -> NILAI_BARU`) dan menyesuaikan dinamika simulasi.

**Contoh Payload:**
```json
{
  "TempTop": 34.0,
  "TempBottom": 26.0,
  "HumidTop": 80.0,
  "HumidBottom": 65.0
}
```

---

### 3. Topik `totalDay` (**HANYA SUBSCRIBE & DETEKSI PERUBAHAN**)
- Mikrokontroler mendengarkan pembaruan hari siklus budidaya maggot.
- **Deteksi Perubahan:** Jika nilai hari berubah (misal dari Hari ke-1 menjadi Hari ke-5), simulator akan mencatat notifikasi perubahan fase siklus.

**Contoh Payload:**
```json
{
  "day": 5
}
```
*(Atau angka langsung: `5`)*

---

## 🧪 5. Cara Menjalankan Pengujian

### Langkah 1: Jalankan Simulator Mikrokontroler
Buka terminal pertama dan jalankan:
```bash
python3 simulator.py
```

*Argumen tambahan (opsional):*
```bash
# Menyesuaikan host, port, interval kirim sekuensial (contoh: 2 detik), dan hari awal
python3 simulator.py --host localhost --port 1883 -i 2.0 --initial-day 1
```

---

### Langkah 2: Uji Deteksi Perubahan Data
Buka terminal kedua dan kirimkan data perubahan ke topik yang di-subscribe mikrokontroler:

#### Menggunakan Mode Interaktif `test_publisher.py`:
```bash
python3 test_publisher.py --interactive
```
*Pilih menu 1 untuk mengubah batas suhu/kelembapan, atau menu 2 untuk mengubah hari siklus. Amati log di terminal simulator mikrokontroler saat perubahan data terdeteksi.*

#### Menggunakan Perintah Sekali Jalan (CLI):
```bash
# Mengirimkan batas baru dan hari siklus baru:
python3 test_publisher.py --temp-top 33.5 --temp-bottom 27.0 --humid-top 82.0 --humid-bottom 62.0 --day 6
```

#### Atau menggunakan `mosquitto_pub` bawaan:
```bash
# Mengirim perubahan batas ambang:
mosquitto_pub -h localhost -p 1883 -t "environmentLimit" -m '{"TempTop": 32.0, "TempBottom": 26.0, "HumidTop": 80.0, "HumidBottom": 65.0}'

# Mengirim perubahan hari siklus:
mosquitto_pub -h localhost -p 1883 -t "totalDay" -m '{"day": 8}'

# Memantau data telemetri yang dikirim mikrokontroler:
mosquitto_sub -h localhost -p 1883 -t "environmentData"
```

---

## 🛑 6. Menghentikan Layanan

### Menggunakan Docker:
```bash
docker compose down
```

### Menggunakan Podman:
```bash
podman compose down
# atau: podman-compose down
```
*(Tambahkan `-v` jika ingin menghapus volume data database dan log, contoh: `docker compose down -v` atau `podman compose down -v`)*
