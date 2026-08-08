#!/usr/bin/env python3
"""
Skrip Pengujian: Mengirim Pembaruan Batas Lingkungan (environmentLimit) & Hari Siklus (totalDay)
------------------------------------------------------------------------------------------------
Skrip ini digunakan untuk menguji respons simulator mikrokontroler (simulator.py)
dalam mendeteksi perubahan data pada topik yang di-subscribe.
"""

import argparse
import json
import os
import sys
import time

try:
    import paho.mqtt.client as mqtt
except ImportError:
    print("Error: Library 'paho-mqtt' diperlukan. Jalankan: pip install paho-mqtt")
    sys.exit(1)


def send_limits(client, temp_top, temp_bottom, humid_top, humid_bottom):
    payload = {
        "TempTop": temp_top,
        "TempBottom": temp_bottom,
        "HumidTop": humid_top,
        "HumidBottom": humid_bottom,
    }
    payload_str = json.dumps(payload)
    print(f"\n[KIRIM DATA] -> Topik: environmentLimit")
    print(f"  Payload: {payload_str}")
    client.publish("environmentLimit", payload_str, qos=1)


def send_day(client, day):
    payload = {"day": day}
    payload_str = json.dumps(payload)
    print(f"\n[KIRIM DATA] -> Topik: totalDay")
    print(f"  Payload: {payload_str}")
    client.publish("totalDay", payload_str, qos=1)


def main():
    parser = argparse.ArgumentParser(description="Pengirim data uji untuk environmentLimit dan totalDay")
    parser.add_argument("--host", default=os.getenv("MQTT_HOST", "localhost"), help="Host broker MQTT")
    parser.add_argument("--port", type=int, default=int(os.getenv("MQTT_PORT", 1883)), help="Port broker MQTT")
    parser.add_argument("--temp-top", type=float, default=32.0, help="Batas TempTop (°C)")
    parser.add_argument("--temp-bottom", type=float, default=26.0, help="Batas TempBottom (°C)")
    parser.add_argument("--humid-top", type=float, default=80.0, help="Batas HumidTop (%)")
    parser.add_argument("--humid-bottom", type=float, default=65.0, help="Batas HumidBottom (%)")
    parser.add_argument("--day", type=int, default=7, help="Hari siklus budidaya")
    parser.add_argument("--interactive", action="store_true", help="Jalankan mode interaktif")

    args = parser.parse_args()

    if hasattr(mqtt, "CallbackAPIVersion"):
        client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2, client_id="Penguji-MQTT-CLI")
    else:
        client = mqtt.Client(client_id="Penguji-MQTT-CLI")

    print(f"Menghubungkan ke broker MQTT di {args.host}:{args.port}...")
    try:
        client.connect(args.host, args.port, 60)
        client.loop_start()
    except Exception as e:
        print(f"Gagal menghubungkan ke broker: {e}")
        return

    time.sleep(0.5)

    if not args.interactive:
        send_limits(client, args.temp_top, args.temp_bottom, args.humid_top, args.humid_bottom)
        send_day(client, args.day)
        time.sleep(0.5)
        print("\n✔ Data uji berhasil dikirim ke broker MQTT.")
    else:
        print("\n=== MODE INTERAKTIF PENGUJIAN PERUBAHAN DATA ===")
        print("1. Ubah Batas Ambang (TempTop, TempBottom, HumidTop, HumidBottom)")
        print("2. Ubah Hari Siklus (totalDay)")
        print("3. Keluar")
        while True:
            try:
                choice = input("\nPilih menu (1/2/3): ").strip()
                if choice == "1":
                    tt = float(input("  TempTop (°C) [contoh 35.0]: ") or "35.0")
                    tb = float(input("  TempBottom (°C) [contoh 25.0]: ") or "25.0")
                    ht = float(input("  HumidTop (%) [contoh 85.0]: ") or "85.0")
                    hb = float(input("  HumidBottom (%) [contoh 60.0]: ") or "60.0")
                    send_limits(client, tt, tb, ht, hb)
                elif choice == "2":
                    d = int(input("  Masukkan Hari Siklus [contoh 5]: ") or "5")
                    send_day(client, d)
                elif choice in ["3", "q", "exit", "keluar"]:
                    break
                else:
                    print("Pilihan tidak valid.")
            except KeyboardInterrupt:
                break
            except Exception as ex:
                print(f"Terjadi kesalahan: {ex}")

    client.loop_stop()
    client.disconnect()


if __name__ == "__main__":
    main()
