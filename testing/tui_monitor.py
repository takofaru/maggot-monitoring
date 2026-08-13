#!/usr/bin/env python3
"""
MQTT Monitor & Simulator - Maggot Monitoring
============================================
Logika Sederhana:
  1. Sambung ke MQTT Broker di localhost:1883
  2. Kirim data temp & humid dalam JSON dengan nilai random (0 - 100) ke topik 'maggot/environmentData' setiap 10 detik.
  3. Tangkap data dari topik 'maggot/environmentLimit' setiap ada pesan masuk, lalu tampilkan di terminal.
  4. Kontrol: Tekan [Enter] untuk Pause/Resume | [Q] atau [Ctrl+C] untuk Keluar.
"""

import json
import os
import random
import sys
import threading
import time
from datetime import datetime

# -----------------------------------------------------------------------------
# Auto-detect .venv di direktori testing jika paho-mqtt belum ada di global
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
        print("[ERROR] Library 'paho-mqtt' tidak ditemukan. Jalankan: uv run python tui_monitor.py atau pip install paho-mqtt", flush=True)
        sys.exit(1)

# Konfigurasi MQTT
BROKER_HOST = "localhost"
BROKER_PORT = 1883
TOPIC_PUB = "maggot/environmentData"
TOPIC_SUB = "maggot/environmentLimit"
INTERVAL = 10  # Kirim setiap 10 detik

# Warna Terminal ANSI
C_RESET = "\033[0m"
C_BOLD = "\033[1m"
C_GREEN = "\033[92m"
C_YELLOW = "\033[93m"
C_CYAN = "\033[96m"
C_RED = "\033[91m"
C_DIM = "\033[2m"

# State Pengiriman
is_running = True
is_publishing = True
seq_count = 0


def on_connect(client, userdata, flags, rc, properties=None):
    rc_code = rc if isinstance(rc, int) else getattr(rc, "value", 0)
    if rc_code == 0 or str(rc).lower() == "success":
        print(f"{C_GREEN}✔ Terhubung ke Broker MQTT ({BROKER_HOST}:{BROKER_PORT}){C_RESET}", flush=True)
        print(f"{C_CYAN}📡 Berlangganan (Subscribe) ke topik: '{TOPIC_SUB}'{C_RESET}", flush=True)
        client.subscribe(TOPIC_SUB, qos=1)
        print(f"{C_DIM}{'─' * 65}{C_RESET}\n", flush=True)
    else:
        print(f"{C_RED}✖ Gagal terhubung ke broker MQTT, kode: {rc_code}{C_RESET}", flush=True)


def on_message(client, userdata, msg):
    """Callback setiap ada data masuk dari topik maggot/environmentLimit"""
    now = datetime.now().strftime("%H:%M:%S")
    raw_payload = msg.payload.decode("utf-8", errors="ignore").strip()

    print(f"\n{C_YELLOW}{C_BOLD}📥 [TERIMA <- {msg.topic}] Waktu: {now}{C_RESET}", flush=True)
    try:
        data = json.loads(raw_payload)
        formatted = json.dumps(data, indent=2)
        print(f"{C_YELLOW}{formatted}{C_RESET}", flush=True)
    except Exception:
        print(f"{C_YELLOW}Payload: {raw_payload}{C_RESET}", flush=True)
    print(f"{C_DIM}{'─' * 65}{C_RESET}\n", flush=True)


def send_environment_data(client):
    """Menghasilkan nilai temp & humid acak (0 - 100) dan mengirim ke maggot/environmentData"""
    global seq_count
    seq_count += 1

    temp = round(random.uniform(0.0, 100.0), 2)
    humid = round(random.uniform(0.0, 100.0), 2)

    payload = {
        "temp": temp,
        "humid": humid
    }
    payload_json = json.dumps(payload)

    client.publish(TOPIC_PUB, payload_json, qos=1)
    now_time = datetime.now().strftime("%H:%M:%S")
    print(f"{C_GREEN}[{now_time}] 📤 [KIRIM -> {TOPIC_PUB}] #{seq_count:04d} | Temp: {C_BOLD}{temp:>5.2f}°C{C_RESET}{C_GREEN} | Humid: {C_BOLD}{humid:>5.2f}%{C_RESET}{C_GREEN} | JSON: {payload_json}{C_RESET}", flush=True)


def publisher_thread(client):
    """Loop pengiriman setiap 10 detik"""
    time.sleep(0.3)
    while is_running:
        if is_publishing:
            send_environment_data(client)
            for _ in range(INTERVAL):
                if not is_running or not is_publishing:
                    break
                time.sleep(1)
        else:
            time.sleep(0.5)


def user_input_thread():
    """Membaca input keyboard untuk pause/resume pengiriman atau keluar"""
    global is_publishing, is_running
    while is_running:
        try:
            cmd = input().strip().lower()
            if cmd in ["q", "exit", "quit", "keluar"]:
                is_running = False
                break
            elif cmd in ["s", "p", "pause", "stop", ""]:
                is_publishing = not is_publishing
                status = f"{C_GREEN}AKTIF (Kirim tiap 10s){C_RESET}" if is_publishing else f"{C_YELLOW}DIHENTIKAN / PAUSED{C_RESET}"
                print(f"\n>> Status Pengiriman: {status}", flush=True)
                if is_publishing:
                    print(">> Melanjutkan pengiriman...", flush=True)
                else:
                    print(">> Pengiriman dihentikan sementara. Tekan [Enter] untuk melanjutkan.", flush=True)
        except (EOFError, KeyboardInterrupt):
            is_running = False
            break


def main():
    global is_running

    print(f"\n{C_BOLD}{'=' * 65}{C_RESET}", flush=True)
    print(f"{C_BOLD}   MQTT ENVIRONMENT MONITOR & TESTER (MAGGOT){C_RESET}", flush=True)
    print(f"{C_BOLD}{'=' * 65}{C_RESET}", flush=True)
    print(f" • Target Broker : {C_CYAN}{BROKER_HOST}:{BROKER_PORT}{C_RESET}", flush=True)
    print(f" • Publish Topik : {C_GREEN}{TOPIC_PUB}{C_RESET} (Nilai random 0-100 setiap {INTERVAL} detik)", flush=True)
    print(f" • Subscribe     : {C_YELLOW}{TOPIC_SUB}{C_RESET} (Menampilkan data masuk)", flush=True)
    print(f" • Kontrol       : Tekan {C_BOLD}[Enter]{C_RESET} untuk Pause/Resume | {C_BOLD}[Q]{C_RESET} atau {C_BOLD}[Ctrl+C]{C_RESET} untuk Keluar", flush=True)
    print(f"{C_BOLD}{'=' * 65}{C_RESET}\n", flush=True)

    if hasattr(mqtt, "CallbackAPIVersion"):
        client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2, client_id=f"Maggot-Tester-{random.randint(100, 999)}")
    else:
        client = mqtt.Client(client_id=f"Maggot-Tester-{random.randint(100, 999)}")

    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(BROKER_HOST, BROKER_PORT, keepalive=60)
        client.loop_start()
    except Exception as e:
        print(f"{C_RED}✖ Gagal koneksi ke broker MQTT di {BROKER_HOST}:{BROKER_PORT}: {e}{C_RESET}", flush=True)
        return

    pub_t = threading.Thread(target=publisher_thread, args=(client,), daemon=True)
    pub_t.start()

    input_t = threading.Thread(target=user_input_thread, daemon=True)
    input_t.start()

    try:
        while is_running:
            time.sleep(0.2)
    except KeyboardInterrupt:
        pass
    finally:
        is_running = False
        print(f"\n{C_YELLOW}Menghentikan koneksi MQTT...{C_RESET}", flush=True)
        try:
            client.loop_stop()
            client.disconnect()
        except Exception:
            pass
        print(f"{C_GREEN}✔ Selesai.{C_RESET}\n", flush=True)


if __name__ == "__main__":
    main()
