#!/bin/sh
set -e

HOUR="${STREAK_WARNING_HOUR:-18:00}"

echo "Streak warnings will go out at ${HOUR} (${TZ:-UTC})"

while :; do
    now=$(date +%s)
    target=$(date -d "today ${HOUR}" +%s 2>/dev/null || date -j -f '%H:%M' "${HOUR}" +%s)

    if [ "${target}" -le "${now}" ]; then
        target=$(date -d "tomorrow ${HOUR}" +%s)
    fi

    sleep $((target - now))

    php /app/bin/console app:warn-about-streaks-at-risk --no-interaction || \
        echo "Streak warnings failed, will try again tomorrow"
done
