#!/bin/bash
set -e

echo "🚀 [Maggot-Monitoring] Memulai container aplikasi..."

# Pastikan kepemilikan dan permission direktori storage dan cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Buat file log jika belum ada
touch /var/www/html/storage/logs/reverb.log /var/www/html/storage/logs/mqtt.log /var/www/html/storage/logs/queue.log /var/www/html/storage/logs/schedule.log
chown www-data:www-data /var/www/html/storage/logs/*.log

# Menunggu database MySQL siap menerima koneksi
echo "⏳ [Maggot-Monitoring] Menunggu database (${DB_HOST:-mysql}:${DB_PORT:-3306}) siap..."
max_retries=30
count=0
until php -r "
    try {
        \$dbh = new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
        exit(0);
    } catch (\Throwable \$e) {
        exit(1);
    }
" 2>/dev/null; do
    count=$((count+1))
    if [ $count -ge $max_retries ]; then
        echo "❌ [Maggot-Monitoring] Database tidak merespons setelah 30 detik. Melanjutkan..."
        break
    fi
    echo "   ...Menunggu database ($count/$max_retries)..."
    sleep 1
done

echo "✔ [Maggot-Monitoring] Database terhubung!"

# Jalankan migrasi database
echo "📦 [Maggot-Monitoring] Menjalankan migrasi database..."
php artisan migrate --force || true

# Pastikan symbolic link storage terbuat
php artisan storage:link --force || true

# Bersihkan dan optimalkan cache Laravel
echo "⚡ [Maggot-Monitoring] Mengoptimalkan cache sistem..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✨ [Maggot-Monitoring] Aplikasi siap! Menjalankan Supervisor Daemon..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
