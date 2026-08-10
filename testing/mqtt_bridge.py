import json
import sqlite3
import os
import paho.mqtt.client as mqtt
from datetime import datetime

# Konfigurasi Broker MQTT & Topik
BROKER = "broker.hivemq.com"
PORT = 1883
TOPIC = "environmentData"

# Lokasi File SQLite milik Laravel
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.abspath(os.path.join(BASE_DIR, "..", "database", "database.sqlite"))

def save_to_db(data):
    if not os.path.exists(DB_PATH):
        print(f" ✖ [DB ERROR] File database tidak ditemukan di: {DB_PATH}")
        return

    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # 1. Ambil ID siklus aktif (atau fallback ke ID 1)
        cursor.execute("SELECT id FROM cycles WHERE is_active = 1 LIMIT 1")
        active_cycle = cursor.fetchone()
        cycle_id = active_cycle[0] if active_cycle else 1

        # 2. Ambil nilai suhu & kelembapan
        temp = data.get("temperature", data.get("Temp", 0.0))
        humid = data.get("humidity", data.get("Humid", 0.0))
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        # 3. Simpan data ke tabel environment_logs (Sesuai struktur kolom asli)
        cursor.execute("""
            INSERT INTO environment_logs (cycle_id, temperature, humidity, timestamp, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        """, (cycle_id, temp, humid, now_str, now_str, now_str))

        conn.commit()
        conn.close()
        print(f" ✔ [DB SAVED] Suhu: {temp}°C | Kelembapan: {humid}% | Siklus ID: {cycle_id}")
    except Exception as e:
        print(f" ✖ [DB ERROR] Gagal simpan ke SQLite: {e}")

def on_connect(client, userdata, flags, rc, properties=None):
    rc_code = rc if isinstance(rc, int) else getattr(rc, "value", 0)
    if rc_code == 0:
        print(f"✔ Terhubung ke Broker ({BROKER})!")
        print(f"📡 Berlangganan ke topik: '{TOPIC}'...")
        client.subscribe(TOPIC, qos=1)
    else:
        print(f"✖ Gagal terhubung ke Broker MQTT, code: {rc_code}")

def on_message(client, userdata, msg):
    try:
        payload_str = msg.payload.decode("utf-8", errors="ignore")
        payload = json.loads(payload_str)
        print(f"\n📥 [MQTT MASUK] Data Diterima: Temp={payload.get('temperature', payload.get('Temp'))}, Humid={payload.get('humidity', payload.get('Humid'))}")
        save_to_db(payload)
    except Exception as e:
        print(f"⚠ [MQTT ERROR] Gagal parse JSON: {e}")

# Inisialisasi Paho Client (Mendukung v1 & v2)
if hasattr(mqtt, "CallbackAPIVersion"):
    client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2)
else:
    client = mqtt.Client()

client.on_connect = on_connect
client.on_message = on_message

print("=========================================================")
print(f"  BRIDGE MQTT SENSOR TO SQLITE DATABASE")
print("=========================================================")
print(f" Target DB : {DB_PATH}")
print(f" Broker    : {BROKER}:{PORT}")
print("=========================================================\n")

try:
    client.connect(BROKER, PORT, 60)
    client.loop_forever()
except KeyboardInterrupt:
    print("\n[INFO] Bridge dihentikan.")