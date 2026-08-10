#!/usr/bin/env python3
"""
Simulator Mikrokontroler untuk Sistem Monitoring Budidaya Maggot (ESP32 / Arduino)
----------------------------------------------------------------------------------
Fungsi Script:
  1. Mengirim data telemetri suhu & kelembapan secara sekuensial (berurutan) ke broker MQTT.
  2. Hanya mempublikasikan data ke topik 'environmentData'.
  3. Berlangganan (subscribe) ke topik 'environmentLimit' dan 'totalDay'.
  4. Mendeteksi dan mencatat setiap perubahan data (data changes) pada topik yang di-subscribe.
"""

import argparse
import datetime
import json
import logging
import math
import os
import random
import signal
import sys
import time

# Warna ANSI untuk tampilan terminal yang jelas dan informatif
class Colors:
    HEADER = "\033[95m"
    OKBLUE = "\033[94m"
    OKCYAN = "\033[96m"
    OKGREEN = "\033[92m"
    WARNING = "\033[93m"
    FAIL = "\033[91m"
    ENDC = "\033[0m"
    BOLD = "\033[1m"
    DIM = "\033[2m"
    CHANGE = "\033[44;97m"  # Background biru untuk highlight perubahan data

# Setup Logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("MicrocontrollerSim")

try:
    import paho.mqtt.client as mqtt
    PAHO_AVAILABLE = True
except ImportError:
    PAHO_AVAILABLE = False


class MaggotMicrocontrollerSimulator:
    def __init__(self, host="localhost", port=1883, keepalive=60, client_id=None, interval=5.0):
        self.host = host
        self.port = port
        self.keepalive = keepalive
        self.interval = interval
        self.client_id = client_id or f"ESP32-Maggot-{random.randint(1000, 9999)}"
        
        # Konfigurasi Topik MQTT
        self.TOPIC_PUB_ENV_DATA = "environmentData"      # HANYA PUBLISH DI SINI
        self.TOPIC_SUB_ENV_LIMIT = "environmentLimit"    # HANYA SUBSCRIBE
        self.TOPIC_SUB_TOTAL_DAY = "totalDay"            # HANYA SUBSCRIBE
        
        # Status Operasional & Urutan Sekuensial
        self.running = False
        self.connected = False
        self.sequence_number = 0  # Nomor urut pengiriman data (sequential counter)
        
        # Data Terlanggan (Subscribed Data) & State Sebelumnya (Untuk deteksi perubahan)
        self.current_day = 1
        self.previous_day = None
        
        self.limits = {
            "TempTop": 35.0,
            "TempBottom": 25.0,
            "HumidTop": 85.0,
            "HumidBottom": 60.0,
        }
        self.previous_limits = self.limits.copy()
        
        # Status Sensor & Dinamika Lingkungan
        self.current_temp = 29.5
        self.current_humid = 72.0
        self.sim_tick = 0

        # Inisialisasi Klien MQTT Paho
        self.client = None
        if PAHO_AVAILABLE:
            self._init_paho_client()

    def _init_paho_client(self):
        # Mendukung paho-mqtt v2.0+ (CallbackAPIVersion) maupun v1.x
        if hasattr(mqtt, "CallbackAPIVersion"):
            self.client = mqtt.Client(
                callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
                client_id=self.client_id,
            )
        else:
            self.client = mqtt.Client(client_id=self.client_id)

        self.client.on_connect = self._on_connect
        self.client.on_disconnect = self._on_disconnect
        self.client.on_message = self._on_message

    def _on_connect(self, client, userdata, flags, rc, properties=None):
        rc_code = rc if isinstance(rc, int) else getattr(rc, "value", 0)
        if rc_code == 0:
            self.connected = True
            logger.info(f"{Colors.OKGREEN}✔ Terhubung ke Broker MQTT di {self.host}:{self.port} (Client ID: {self.client_id}){Colors.ENDC}")
            
            # Subscribe ke topik sesuai instruksi
            logger.info(f"{Colors.OKCYAN}📡 Mengaktifkan Subscription Topik:{Colors.ENDC}")
            logger.info(f"   [SUB] -> {Colors.BOLD}{self.TOPIC_SUB_ENV_LIMIT}{Colors.ENDC} (Batas: TempTop, TempBottom, HumidTop, HumidBottom)")
            logger.info(f"   [SUB] -> {Colors.BOLD}{self.TOPIC_SUB_TOTAL_DAY}{Colors.ENDC} (Hari Siklus Budidaya)")
            
            client.subscribe(self.TOPIC_SUB_ENV_LIMIT, qos=1)
            client.subscribe(self.TOPIC_SUB_TOTAL_DAY, qos=1)
        else:
            self.connected = False
            logger.error(f"{Colors.FAIL}✖ Gagal terhubung ke broker, return code: {rc_code}{Colors.ENDC}")

    def _on_disconnect(self, client, userdata, flags=None, rc=None, properties=None):
        self.connected = False
        logger.warning(f"{Colors.WARNING}⚠ Terputus dari Broker MQTT (rc={rc}){Colors.ENDC}")

    def _on_message(self, client, userdata, msg):
        topic = msg.topic
        payload_raw = msg.payload.decode("utf-8", errors="ignore").strip()
        logger.info(f"{Colors.OKBLUE}[PESAN MASUK]{Colors.ENDC} Topik: {Colors.BOLD}{topic}{Colors.ENDC} | Payload: {payload_raw}")
        
        if topic == self.TOPIC_SUB_ENV_LIMIT:
            self._handle_environment_limit(payload_raw)
        elif topic == self.TOPIC_SUB_TOTAL_DAY:
            self._handle_total_day(payload_raw)
        else:
            logger.warning(f"Menerima pesan dari topik tak dikenal: {topic}")

    def _handle_environment_limit(self, payload_str):
        """Mendeteksi dan memperbarui batas ambang lingkungan (environmentLimit)."""
        try:
            data = {}
            if payload_str.startswith("{") and payload_str.endswith("}"):
                data = json.loads(payload_str)
            else:
                for item in payload_str.replace(";", ",").split(","):
                    if "=" in item:
                        k, v = item.split("=", 1)
                        data[k.strip()] = float(v.strip())
                    elif ":" in item:
                        k, v = item.split(":", 1)
                        data[k.strip()] = float(v.strip())

            # Alias mapping
            mapping = {
                "TempTop": ["TempTop", "temp_top", "temptop", "tempTop", "max_temp"],
                "TempBottom": ["TempBottom", "temp_bottom", "tempbottom", "tempBottom", "min_temp"],
                "HumidTop": ["HumidTop", "humid_top", "humidtop", "humidTop", "max_humid"],
                "HumidBottom": ["HumidBottom", "humid_bottom", "humidbottom", "humidBottom", "min_humid"],
            }

            changes_detected = []
            new_limits = self.limits.copy()

            for target_key, aliases in mapping.items():
                for alias in aliases:
                    if alias in data:
                        try:
                            val = float(data[alias])
                            old_val = self.limits[target_key]
                            if old_val != val:
                                changes_detected.append(f"{target_key}: {old_val} -> {Colors.BOLD}{val}{Colors.ENDC}")
                            new_limits[target_key] = val
                            break
                        except (ValueError, TypeError):
                            pass

            if changes_detected:
                self.previous_limits = self.limits.copy()
                self.limits = new_limits
                print("\n" + "=" * 65)
                print(f" {Colors.CHANGE} 🔔 DETEKSI PERUBAHAN DATA: environmentLimit {Colors.ENDC}")
                for change in changes_detected:
                    print(f"  • {change}")
                print(f"  Rentang Suhu Baru      : [{self.limits['TempBottom']}°C s/d {self.limits['TempTop']}°C]")
                print(f"  Rentang Kelembapan Baru: [{self.limits['HumidBottom']}% s/d {self.limits['HumidTop']}%]")
                print("=" * 65 + "\n")
            else:
                logger.info(f"{Colors.DIM}Batas ambang diterima sama dengan data sebelumnya (tidak ada perubahan).{Colors.ENDC}")

        except Exception as e:
            logger.error(f"Gagal memproses payload environmentLimit '{payload_str}': {e}")

    def _handle_total_day(self, payload_str):
        """Mendeteksi dan memperbarui hari siklus budidaya (totalDay)."""
        try:
            day_val = None
            if payload_str.startswith("{") and payload_str.endswith("}"):
                data = json.loads(payload_str)
                for k in ["day", "totalDay", "total_day", "Day", "TotalDay", "current_day"]:
                    if k in data:
                        day_val = int(data[k])
                        break
            else:
                digits = "".join([c for c in payload_str if c.isdigit()])
                if digits:
                    day_val = int(digits)

            if day_val is not None and day_val >= 0:
                if self.current_day != day_val:
                    old_day = self.current_day
                    self.previous_day = old_day
                    self.current_day = day_val
                    print("\n" + "=" * 65)
                    print(f" {Colors.CHANGE} 🔔 DETEKSI PERUBAHAN DATA: totalDay {Colors.ENDC}")
                    print(f"  • Hari Siklus Berubah: Hari ke-{old_day} -> {Colors.BOLD}Hari ke-{self.current_day}{Colors.ENDC}")
                    print("=" * 65 + "\n")
                else:
                    logger.info(f"{Colors.DIM}Hari siklus diterima sama dengan data sebelumnya (Hari ke-{self.current_day}).{Colors.ENDC}")
            else:
                logger.warning(f"Format data totalDay tidak valid: {payload_str}")

        except Exception as e:
            logger.error(f"Gagal memproses payload totalDay '{payload_str}': {e}")

    def _generate_sequential_readings(self):
        """Menghasilkan pembacaan sensor suhu & kelembapan yang realistis dan berurutan."""
        self.sim_tick += 1
        
        # Dinamika gelombang sinusoidal + variasi acak halus (random walk)
        time_factor = math.sin(self.sim_tick * 0.15) * 1.2
        noise_temp = random.uniform(-0.20, 0.20)
        noise_humid = random.uniform(-0.50, 0.50)
        
        # Target nilai tengah dari limit aktif
        target_mid_temp = (self.limits["TempTop"] + self.limits["TempBottom"]) / 2.0
        target_mid_humid = (self.limits["HumidTop"] + self.limits["HumidBottom"]) / 2.0
        
        # Penyesuaian bertahap menuju rentang optimal
        self.current_temp += (target_mid_temp - self.current_temp) * 0.06 + time_factor * 0.05 + noise_temp
        self.current_humid += (target_mid_humid - self.current_humid) * 0.06 - (time_factor * 0.10) + noise_humid
        
        temp = round(max(15.0, min(50.0, self.current_temp)), 2)
        humid = round(max(20.0, min(99.0, self.current_humid)), 2)
        
        # Evaluasi status terhadap limit
        status = "NORMAL"
        warnings = []
        if temp > self.limits["TempTop"]:
            warnings.append("SUHU_TINGGI")
        elif temp < self.limits["TempBottom"]:
            warnings.append("SUHU_RENDAH")
            
        if humid > self.limits["HumidTop"]:
            warnings.append("LEMBAP_TINGGI")
        elif humid < self.limits["HumidBottom"]:
            warnings.append("LEMBAP_RENDAH")
            
        if warnings:
            status = "_".join(warnings)
            
        return temp, humid, status

    def publish_environment_data(self):
        """Mempublikasikan data telemetri secara sekuensial HANYA ke topik environmentData."""
        if not self.client or not self.connected:
            return

        self.sequence_number += 1
        temp, humid, status = self._generate_sequential_readings()
        now_iso = datetime.datetime.now().astimezone().isoformat()
        
        # Format payload lengkap & ramah kompatibilitas
        payload = {
            "seq": self.sequence_number,
            "device_id": self.client_id,
            "temperature": temp,
            "humidity": humid,
            "Temp": temp,
            "Humid": humid,
            "day": self.current_day,
            "status": status,
            "timestamp": now_iso,
            "active_limits": self.limits,
        }
        
        payload_json = json.dumps(payload)
        
        # PUBLISH HANYA KE TOPIK environmentData
        self.client.publish(self.TOPIC_PUB_ENV_DATA, payload_json, qos=1)
        
        # Indikator warna status
        status_color = Colors.OKGREEN if status == "NORMAL" else Colors.WARNING
        logger.info(
            f"{Colors.HEADER}[PUB -> {self.TOPIC_PUB_ENV_DATA}] Urutan #{self.sequence_number:04d}{Colors.ENDC} | "
            f"Suhu: {Colors.BOLD}{temp}°C{Colors.ENDC} | "
            f"Kelembapan: {Colors.BOLD}{humid}%{Colors.ENDC} | "
            f"Hari: {self.current_day} | "
            f"Status: {status_color}{status}{Colors.ENDC}"
        )

    def run(self):
        """Memulai loop utama simulator mikrokontroler."""
        if not PAHO_AVAILABLE:
            self._show_paho_missing()
            return

        self.running = True
        print("\n" + "=" * 65)
        print(f"{Colors.BOLD}   SIMULATOR MIKROKONTROLER MONITORING BUDIDAYA MAGGOT (ESP32){Colors.ENDC}")
        print("=" * 65)
        print(f"  • Broker Target   : {self.host}:{self.port}")
        print(f"  • Interval Kirim  : {self.interval} detik")
        print(f"  • Topik Publish   : {Colors.BOLD}{self.TOPIC_PUB_ENV_DATA}{Colors.ENDC}")
        print(f"  • Topik Subscribe : {Colors.BOLD}{self.TOPIC_SUB_ENV_LIMIT}{Colors.ENDC}, {Colors.BOLD}{self.TOPIC_SUB_TOTAL_DAY}{Colors.ENDC}")
        print("=" * 65 + "\n")
        
        try:
            self.client.connect(self.host, self.port, self.keepalive)
            self.client.loop_start()
        except Exception as e:
            logger.error(f"{Colors.FAIL}Gagal menghubungkan ke broker MQTT: {e}{Colors.ENDC}")
            logger.info("Pastikan container Mosquitto sedang berjalan (misal: docker compose up -d atau podman compose up -d)")
            return

        try:
            while self.running:
                if self.connected:
                    self.publish_environment_data()
                time.sleep(self.interval)
        except KeyboardInterrupt:
            logger.info("Menerima sinyal keyboard interrupt (Ctrl+C)...")
        finally:
            self.stop()

    def stop(self):
        """Menghentikan simulator dan memutus koneksi secara bersih."""
        self.running = False
        logger.info(f"{Colors.WARNING}Menghentikan simulator mikrokontroler...{Colors.ENDC}")
        if self.client:
            try:
                self.client.loop_stop()
                self.client.disconnect()
            except Exception:
                pass
        logger.info(f"{Colors.OKGREEN}Simulator selesai. Total frame terkirim berurutan: {self.sequence_number}{Colors.ENDC}")

    def _show_paho_missing(self):
        print(f"\n{Colors.FAIL}Error: Library 'paho-mqtt' belum terinstall.{Colors.ENDC}")
        print("Silakan install dependensi terlebih dahulu dengan perintah:")
        print(f"  {Colors.BOLD}pip install -r requirements.txt{Colors.ENDC}")
        print("  atau")
        print(f"  {Colors.BOLD}pip install paho-mqtt{Colors.ENDC}\n")


def parse_args():
    parser = argparse.ArgumentParser(
        description="Simulator Mikrokontroler Pengujian Monitoring Maggot (MQTT)"
    )
    parser.add_argument(
        "--host",
        default=os.getenv("MQTT_HOST", "broker.hivemq.com"),
        help="Alamat host broker MQTT (default: localhost)",
    )
    parser.add_argument(
        "--port",
        type=int,
        default=int(os.getenv("MQTT_PORT", "1883")),
        help="Port broker MQTT (default: 1883)",
    )
    parser.add_argument(
        "--interval",
        "-i",
        type=float,
        default=float(os.getenv("SIM_INTERVAL", "5.0")),
        help="Interval pengiriman data sekuensial dalam detik (default: 5.0)",
    )
    parser.add_argument(
        "--client-id",
        default=os.getenv("MQTT_CLIENT_ID", None),
        help="Client ID MQTT (default: ESP32-Maggot-XXXX)",
    )
    parser.add_argument(
        "--initial-day",
        type=int,
        default=1,
        help="Hari awal siklus budidaya (default: 1)",
    )
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()
    sim = MaggotMicrocontrollerSimulator(
        host=args.host,
        port=args.port,
        interval=args.interval,
        client_id=args.client_id,
    )
    sim.current_day = args.initial_day

    def sig_handler(signum, frame):
        sim.stop()
        sys.exit(0)

    signal.signal(signal.SIGINT, sig_handler)
    signal.signal(signal.SIGTERM, sig_handler)

    sim.run()
