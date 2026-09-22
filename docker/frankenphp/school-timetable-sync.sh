#!/bin/sh
set -e

INTERVAL="${SCHOOL_TIMETABLE_SYNC_INTERVAL:-3600}"
WEEKS="${SCHOOL_TIMETABLE_SYNC_WEEKS:-2}"

echo "School timetables sync every ${INTERVAL}s, ${WEEKS} week(s) ahead"

while :; do
    php /app/bin/console app:school-timetable:sync --weeks="${WEEKS}" --no-interaction || \
        echo "School timetable sync failed, will try again in ${INTERVAL}s"

    sleep "${INTERVAL}"
done
