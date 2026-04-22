#!/bin/sh
set -eu

APP_DIR="/var/www/html"
LOG_DIR="$APP_DIR/logs"

mkdir -p \
  "$APP_DIR/config" \
  "$APP_DIR/data" \
  "$APP_DIR/data/order_locks" \
  "$LOG_DIR" \
  "$APP_DIR/qrcode"

chown -R www-data:www-data \
  "$APP_DIR/config" \
  "$APP_DIR/data" \
  "$LOG_DIR" \
  "$APP_DIR/qrcode"

if command -v su >/dev/null 2>&1; then
  su -s /bin/sh -c "nohup php $APP_DIR/container_monitor.php >> $LOG_DIR/monitor.log 2>&1 &" www-data
else
  nohup php "$APP_DIR/container_monitor.php" >> "$LOG_DIR/monitor.log" 2>&1 &
fi

(
  attempts=0
  until php -r '$ctx = stream_context_create(["http" => ["timeout" => 2, "ignore_errors" => true]]); $body = @file_get_contents("http://127.0.0.1/health.php?action=status", false, $ctx); exit($body === false ? 1 : 0);' >/dev/null 2>&1
  do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 45 ]; then
      printf '%s startup self-check skipped: web service did not become ready in time\n' "$(date '+%F %T')" >> "$LOG_DIR/self-check.log"
      exit 0
    fi
    sleep 1
  done

  if command -v su >/dev/null 2>&1; then
    su -s /bin/sh -c "php $APP_DIR/scripts/self-check.php --base-url=http://127.0.0.1 --mode=startup --write-status" www-data >> "$LOG_DIR/self-check.log" 2>&1 || true
  else
    php "$APP_DIR/scripts/self-check.php" --base-url=http://127.0.0.1 --mode=startup --write-status >> "$LOG_DIR/self-check.log" 2>&1 || true
  fi
) &

exec apache2-foreground
