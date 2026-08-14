#!/usr/bin/env python3
"""
Microcontroller Simulator (ESP32) - Maggot Monitoring System
============================================================
Fungsi:
  1. Terhubung ke Broker MQTT di localhost:1883.
  2. Subscribe ke topik 'environmentLimit':
     - Menerima batas suhu (temp_min, temp_max) & kelembapan (humid_min, humid_max).
     - Menampilkan log pembaruan batas lingkungan.
  3. Publish ke topik 'environmentData' secara periodik (setiap 10 detik):
     - Mengirimkan hanya 'temperature' dan 'humidity' dengan retain=True agar data selalu tersimpan di broker.
  4. Kontrol Keyboard:
     - [Enter] / [S] : Jeda (Pause) atau Lanjutkan (Resume) pengiriman data.
     - [N]           : Kirim 1 data telemetri sekarang (Send Now).
     - [Q]           : Keluar dari simulator.
"""

import os
import sys
import json
import time
import random
import threading
from datetime import datetime

# -----------------------------------------------------------------------------
# Auto-detect paho-mqtt
# -----------------------------------------------------------------------------
try:
    import paho.mqtt.client as mqtt
except ImportError:
    script_dir = os.path.dirname(os.path.abspath(__file__))
    venv_lib = os.path.join(script_dir, ".venv", "lib")
    if os.path.exists(venv_lib):
        for p in os.listdir(venv_lib):
            site_p = os.path.join(venv_lib, p, "site-packages")
            if os.path.exists(site_p) and site_p not in sys.path:
                sys.path.insert(0, site_p)
    try:
        import paho.mqtt.client as mqtt
    except ImportError:
        print("[ERROR] Library 'paho-mqtt' tidak ditemukan. Jalankan: pip install paho-mqtt", flush=True)
        sys.exit(1)

# Konfigurasi MQTT
BROKER_HOST = os.getenv("MQTT_HOST", "localhost")
BROKER_PORT = int(os.getenv("MQTT_PORT", 1883))
TOPIC_PUB = "environmentData"
TOPIC_SUB = "environmentLimit"
DEFAULT_INTERVAL = 10.0

# Kode Warna Terminal ANSI
C_RESET = "\033[0m"
C_BOLD = "\033[1m"
C_GREEN = "\033[92m"
C_YELLOW = "\033[93m"
C_CYAN = "\033[96m"
C_MAGENTA = "\033[95m"
C_RED = "\033[91m"
C_DIM = "\033[2m"

# State Simulator
is_running = True
is_publishing = True
msg_count = 0
active_limits = {
    "phase_name": "penetasan",
    "temp_min": 27.0,
    "temp_max": 30.0,
    "humid_min": 60.0,
    "humid_max": 80.0,
}


def on_connect(client, userdata, flags, rc, properties=None):
    rc_code = rc if isinstance(rc, int) else getattr(rc, "value", 0)
    if rc_code == 0 or str(rc).lower() == "success":
        print(f"{C_GREEN}✔ Sukses Terhubung ke Broker MQTT ({BROKER_HOST}:{BROKER_PORT}){C_RESET}", flush=True)
        print(f"{C_CYAN}📡 Berlangganan (Subscribe) ke topik: '{TOPIC_SUB}'{C_RESET}", flush=True)
        client.subscribe(TOPIC_SUB, qos=1)
        print(f"{C_DIM}{'─' * 70}{C_RESET}\n", flush=True)
    else:
        print(f"{C_RED}✖ Gagal koneksi ke broker MQTT, kode respon: {rc_code}{C_RESET}", flush=True)


def on_message(client, userdata, msg):
    """Callback saat pesan batas baru masuk dari topik environmentLimit"""
    global active_limits
    now = datetime.now().strftime("%H:%M:%S")
    raw_payload = msg.payload.decode("utf-8", errors="ignore").strip()

    print(f"\n{C_MAGENTA}{C_BOLD}📥 [TERIMA <- {msg.topic}] Pukul: {now}{C_RESET}", flush=True)
    try:
        data = json.loads(raw_payload)

        phase = data.get("phase_name", active_limits.get("phase_name", "penetasan"))
        t_min = float(data.get("temp_min", active_limits["temp_min"]))
        t_max = float(data.get("temp_max", active_limits["temp_max"]))
        h_min = float(data.get("humid_min", active_limits["humid_min"]))
        h_max = float(data.get("humid_max", active_limits["humid_max"]))

        old_phase = active_limits.get("phase_name", "-")
        active_limits.update({
            "phase_name": phase,
            "temp_min": t_min,
            "temp_max": t_max,
            "humid_min": h_min,
            "humid_max": h_max,
        })

        print(f" {C_YELLOW}┌─ Batas Lingkungan Baru Diterima:{C_RESET}", flush=True)
        print(f" {C_YELLOW}│  • Fase       : {C_BOLD}{phase.capitalize()}{C_RESET} {C_DIM}(sebelumnya: {old_phase}){C_RESET}", flush=True)
        print(f" {C_YELLOW}│  • Batas Suhu : {C_BOLD}{t_min}°C{C_RESET} s/d {C_BOLD}{t_max}°C{C_RESET}", flush=True)
        print(f" {C_YELLOW}│  • Batas Humid: {C_BOLD}{h_min}%{C_RESET} s/d {C_BOLD}{h_max}%{C_RESET}", flush=True)
        print(f" {C_YELLOW}└──────────────────────────────────────{C_RESET}", flush=True)
    except Exception as e:
        print(f" {C_YELLOW}Payload (Raw): {raw_payload} [Parse Error: {e}]{C_RESET}", flush=True)
    print(f"{C_DIM}{'─' * 70}{C_RESET}\n", flush=True)


def generate_sensor_reading():
    """Menghasilkan pembacaan suhu dan kelembapan realistis"""
    t_min = active_limits["temp_min"]
    t_max = active_limits["temp_max"]
    h_min = active_limits["humid_min"]
    h_max = active_limits["humid_max"]

    temp = round(random.uniform(t_min - 0.5, t_max + 0.5), 2)
    humid = round(random.uniform(h_min - 1.0, h_max + 1.0), 2)

    temp = max(0.0, min(100.0, temp))
    humid = max(0.0, min(100.0, humid))

    return temp, humid


def publish_environment_data(client):
    """Mengirim data sensor ke topik environmentData dengan flag retain=True"""
    global msg_count
    msg_count += 1

    temp, humid = generate_sensor_reading()

    payload = {
        "temperature": temp,
        "humidity": humid
    }

    payload_json = json.dumps(payload)
    client.publish(TOPIC_PUB, payload_json, qos=1, retain=True)

    now_time = datetime.now().strftime("%H:%M:%S")
    print(f"{C_CYAN}[{now_time}]{C_RESET} 📤 {C_BOLD}[KIRIM -> {TOPIC_PUB}]{C_RESET} #{msg_count:04d} | Suhu: {C_BOLD}{temp:>5.2f}°C{C_RESET} | Kelembapan: {C_BOLD}{humid:>5.2f}%{C_RESET} | {C_DIM}JSON: {payload_json}{C_RESET}", flush=True)


def publisher_thread(client):
    """Loop pengiriman data telemetri otomatis setiap interval"""
    time.sleep(0.5)
    while is_running:
        if is_publishing:
            publish_environment_data(client)
            steps = int(DEFAULT_INTERVAL / 0.2)
            for _ in range(steps):
                if not is_running or not is_publishing:
                    break
                time.sleep(0.2)
        else:
            time.sleep(0.3)


def user_input_thread(client):
    """Membaca interaksi keyboard dari pengguna"""
    global is_publishing, is_running
    while is_running:
        try:
            cmd = input().strip().lower()
            if cmd in ["q", "exit", "quit", "keluar"]:
                is_running = False
                break
            elif cmd in ["n", "now", "send"]:
                publish_environment_data(client)
            elif cmd in ["s", "p", "pause", "stop", ""]:
                is_publishing = not is_publishing
                status_txt = f"{C_GREEN}AKTIF (Kirim tiap {DEFAULT_INTERVAL}s){C_RESET}" if is_publishing else f"{C_YELLOW}DIJEDA / PAUSED{C_RESET}"
                print(f"\n>> Status Simulator: {status_txt}", flush=True)
                if is_publishing:
                    print(">> Melanjutkan pengiriman data...", flush=True)
                else:
                    print(">> Pengiriman otomatis dijeda. Tekan [Enter] untuk lanjut, atau [N] untuk kirim manual.", flush=True)
        except (EOFError, KeyboardInterrupt):
            is_running = False
            break


def main():
    global is_running

    print(f"\n{C_BOLD}{'=' * 70}{C_RESET}", flush=True)
    print(f"{C_BOLD}   SIMULATOR MIKROKONTROLER IOT MAGGOT (ESP32){C_RESET}", flush=True)
    print(f"{C_BOLD}{'=' * 70}{C_RESET}", flush=True)
    print(f" • Target Broker   : {C_CYAN}{BROKER_HOST}:{BROKER_PORT}{C_RESET}", flush=True)
    print(f" • Topik Publish   : {C_GREEN}{TOPIC_PUB}{C_RESET} (Setiap {DEFAULT_INTERVAL}s dengan retain=True)", flush=True)
    print(f" • Topik Subscribe : {C_MAGENTA}{TOPIC_SUB}{C_RESET} (Batas lingkungan)", flush=True)
    print(f" • Kontrol         : Tekan {C_BOLD}[Enter]{C_RESET} Jeda/Lanjut | {C_BOLD}[N]{C_RESET} Kirim Instan | {C_BOLD}[Q]{C_RESET} Keluar", flush=True)
    print(f"{C_BOLD}{'=' * 70}{C_RESET}\n", flush=True)

    client_id = f"ESP32-Maggot-{random.randint(1000, 9999)}"
    if hasattr(mqtt, "CallbackAPIVersion"):
        client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2, client_id=client_id)
    else:
        client = mqtt.Client(client_id=client_id)

    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(BROKER_HOST, BROKER_PORT, keepalive=60)
        client.loop_start()
    except Exception as e:
        print(f"{C_RED}✖ Tidak dapat terhubung ke broker MQTT di {BROKER_HOST}:{BROKER_PORT}: {e}{C_RESET}", flush=True)
        return

    pub_t = threading.Thread(target=publisher_thread, args=(client,), daemon=True)
    pub_t.start()

    input_t = threading.Thread(target=user_input_thread, args=(client,), daemon=True)
    input_t.start()

    try:
        while is_running:
            time.sleep(0.2)
    except KeyboardInterrupt:
        pass
    finally:
        is_running = False
        print(f"\n{C_YELLOW}Menghentikan simulator mikrokontroler...{C_RESET}", flush=True)
        try:
            client.loop_stop()
            client.disconnect()
        except Exception:
            pass
        print(f"{C_GREEN}✔ Simulator selesai dihentikan.{C_RESET}\n", flush=True)


if __name__ == "__main__":
    main()
