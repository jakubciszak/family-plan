import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';

import type { Busy, Calendar, Occurrence } from '@/api/day-planning';
import { shiftDay } from '@/dates';
import { localParts } from '@/day-planning/time';
import { layoutWeek, monthWeeks, type WeekSegment } from '@/day-planning/week-layout';

type CalendarItem = { key: string; start: string; end: string; allDay?: boolean; timeZone?: string; event?: Occurrence; busy?: Busy };
type Props = { calendar: Calendar; date: string; zone: string; fullScreen?: boolean; nameFor: (id: string) => string; onOpen: (event: Occurrence) => void; onShowDay: (date: string) => void; onShowMore: (date: string) => void; onCreate?: (spot: { date: string }) => void };
const LINE = 20;
const GAP = 2;
const CELL_PADDING = 3;
const DAY_NUMBER = 24;
export const MONTH_ROW_MINIMUM = CELL_PADDING * 2 + DAY_NUMBER + GAP + LINE;
const capitalize = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);
const noon = (day: string) => new Date(`${day}T12:00:00Z`);

export default function MonthCalendar({ calendar, date, zone, fullScreen = false, nameFor, onOpen, onShowDay, onShowMore, onCreate }: Props) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const { width } = useWindowDimensions();
  const wide = width >= 700;
  const { start, weeks } = monthWeeks(date);
  const month = date.slice(0, 7);
  const [bodyHeight, setBodyHeight] = useState(0);
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 60000);
    return () => clearInterval(timer);
  }, []);
  const today = localParts(new Date(now).toISOString(), zone).date;
  const rows = useMemo(() => {
    const items: CalendarItem[] = [
      ...calendar.events.map((event) => ({ key: `event-${event.id}-${event.occurrenceKey}`, start: event.start, end: event.end, allDay: event.allDay, timeZone: event.timeZone, event })),
      ...calendar.busy.map((busy, index) => ({ key: `busy-${busy.personId}-${index}`, start: busy.start, end: busy.end, busy })),
    ];
    return Array.from({ length: weeks }, (_, week) => layoutWeek(items, shiftDay(start, week * 7), zone).map((day) => ({ date: day.date, segments: [...day.allDay, ...day.timed] })));
  }, [calendar, start, weeks, zone]);
  const rowHeight = fullScreen ? Math.max(MONTH_ROW_MINIMUM, bodyHeight / weeks) : wide ? 120 : 88;
  const listHeight = rowHeight - CELL_PADDING * 2 - DAY_NUMBER - GAP;
  const capacity = Math.max(1, Math.floor((listHeight + GAP) / (LINE + GAP)));
  const weekday = new Intl.DateTimeFormat(i18n.language, { weekday: 'short', timeZone: 'UTC' });
  const firstOfMonth = new Intl.DateTimeFormat(i18n.language, { day: 'numeric', month: 'short', timeZone: 'UTC' });
  const longDate = new Intl.DateTimeFormat(i18n.language, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit', timeZone: zone });
  const long = (day: string) => capitalize(longDate.format(noon(day)));
  const line = (segment: WeekSegment<CalendarItem>) => {
    const { item, continuesBefore, continuesAfter } = segment;
    const event = item.event;
    const title = event?.title ?? `${t('dayPlanning.busy')} · ${nameFor(item.busy!.personId)}`;
    const rawColor = event?.tags[0]?.color;
    const color = !event ? theme.colors.outline : rawColor && /^#[a-f\d]{6}$/i.test(rawColor) ? rawColor : theme.colors.primary;
    const span = !!item.allDay || continuesBefore || continuesAfter;
    const hours = item.allDay ? t('dayPlanning.allDay') : `${time.format(new Date(item.start))} – ${time.format(new Date(item.end))}`;
    const accessibilityLabel = [title, hours, continuesBefore ? t('dayPlanning.continuesBefore') : '', continuesAfter ? t('dayPlanning.continuesAfter') : ''].filter(Boolean).join('. ');
    const declined = event?.participation === 'DECLINED';
    const content = <>
      <View style={[StyleSheet.absoluteFill, { backgroundColor: color, opacity: span ? 0.3 : 0.12, pointerEvents: 'none' }]} />
      {continuesBefore && <Text style={[styles.text, { color: theme.colors.onSurface }]}>‹</Text>}
      {wide && !item.allDay && !continuesBefore && <Text style={[styles.text, styles.time, { color: theme.colors.onSurfaceVariant }]}>{time.format(new Date(item.start))}</Text>}
      {wide && (!event || event.visibility === 'PRIVATE') && <Icon source="lock-outline" size={11} color={theme.colors.onSurfaceVariant} />}
      <Text numberOfLines={1} style={[styles.text, styles.title, { color: theme.colors.onSurface, fontWeight: span ? '600' : '400', textDecorationLine: declined ? 'line-through' : 'none' }]}>{title}</Text>
      {continuesAfter && <Text style={[styles.text, { color: theme.colors.onSurface }]}>›</Text>}
    </>;
    const style = [styles.line, { borderLeftColor: color, opacity: declined ? 0.7 : 1 }, wide && { paddingHorizontal: 5 }];
    return event
      ? <Pressable key={item.key} accessibilityRole="button" accessibilityLabel={accessibilityLabel} onPress={() => onOpen(event)} style={style} testID={`month-event-${event.id}-${segment.day}`}>{content}</Pressable>
      : <View key={item.key} accessible accessibilityLabel={accessibilityLabel} style={style} testID={`month-busy-${segment.day}`}>{content}</View>;
  };
  return <View testID="day-month-calendar" accessibilityLabel={t('dayPlanning.monthCalendar')} style={[styles.month, { borderColor: theme.colors.outlineVariant, backgroundColor: theme.colors.surface }, fullScreen && { flex: 1 }]}>
    <View style={[styles.weekdays, { borderColor: theme.colors.outlineVariant, backgroundColor: theme.colors.elevation.level1 }]}>
      {rows[0].map((day) => <Text key={day.date} numberOfLines={1} style={[styles.weekday, { color: theme.colors.onSurfaceVariant }]}>{weekday.format(noon(day.date))}</Text>)}
    </View>
    <View onLayout={(event) => setBodyHeight(event.nativeEvent.layout.height)} style={fullScreen ? { flex: 1, minHeight: weeks * MONTH_ROW_MINIMUM } : undefined}>
      {rows.map((row, index) => <View key={row[0].date} style={[styles.week, { borderColor: theme.colors.outlineVariant, borderTopWidth: index ? 1 : 0 }, fullScreen ? { flex: 1 } : { height: rowHeight }]}>
        {row.map((day, column) => {
          const shown = day.segments.length > capacity ? day.segments.slice(0, capacity - 1) : day.segments;
          const hidden = day.segments.length - shown.length;
          const outside = day.date.slice(0, 7) !== month;
          const isToday = day.date === today;
          const count = day.segments.length ? `, ${t('dayPlanning.eventsCount', { count: day.segments.length })}` : '';
          return <View key={day.date} testID={`month-day-${day.date}`} style={[styles.cell, { borderColor: theme.colors.outlineVariant, borderLeftWidth: column ? 1 : 0, backgroundColor: outside ? theme.colors.elevation.level1 : 'transparent' }]}>
            {onCreate && <Pressable testID={`month-create-${day.date}`} accessible={false} onPress={() => onCreate({ date: day.date })} style={({ pressed }) => [StyleSheet.absoluteFill, { backgroundColor: pressed ? theme.colors.surfaceVariant : 'transparent' }]} />}
            <View style={styles.cellContent}>
              <Pressable accessibilityRole="button" accessibilityLabel={`${t('dayPlanning.showDay', { date: long(day.date) })}${count}`} onPress={() => onShowDay(day.date)} testID={`month-day-number-${day.date}`}
                style={({ pressed }) => [styles.dayNumber, { backgroundColor: isToday ? theme.colors.primary : pressed ? theme.colors.elevation.level3 : 'transparent' }]}>
                <Text numberOfLines={1} style={[styles.dayNumberText, { color: isToday ? theme.colors.onPrimary : outside ? theme.colors.onSurfaceVariant : theme.colors.onSurface }]}>
                  {day.date.endsWith('-01') ? firstOfMonth.format(noon(day.date)) : Number(day.date.slice(8))}
                </Text>
              </Pressable>
              {shown.map((segment) => line(segment))}
              {hidden > 0 && <Pressable accessibilityRole="button" accessibilityLabel={`${t('dayPlanning.moreEvents', { count: hidden })}: ${long(day.date)}`} onPress={() => onShowMore(day.date)} testID={`month-more-${day.date}`}
                style={({ pressed }) => [styles.line, styles.more, pressed && { backgroundColor: theme.colors.elevation.level3 }, wide && { paddingHorizontal: 5 }]}>
                <Text numberOfLines={1} style={[styles.text, { color: theme.colors.onSurfaceVariant, fontWeight: '600' }]}>{wide ? t('dayPlanning.moreEvents', { count: hidden }) : `+${hidden}`}</Text>
              </Pressable>}
            </View>
          </View>;
        })}
      </View>)}
    </View>
  </View>;
}

const styles = StyleSheet.create({
  month: { borderWidth: 1, borderRadius: 12, overflow: 'hidden' },
  weekdays: { flexDirection: 'row', borderBottomWidth: 1 },
  weekday: { flex: 1, paddingVertical: 6, paddingHorizontal: 2, textAlign: 'center', fontSize: 11, fontWeight: '500' },
  week: { flexDirection: 'row' },
  cell: { flex: 1, minWidth: 0, overflow: 'hidden' },
  cellContent: { flex: 1, gap: GAP, paddingVertical: CELL_PADDING, paddingHorizontal: 2, pointerEvents: 'box-none' },
  dayNumber: { alignSelf: 'center', minWidth: 26, height: DAY_NUMBER, paddingHorizontal: 5, borderRadius: DAY_NUMBER / 2, alignItems: 'center', justifyContent: 'center' },
  dayNumberText: { fontSize: 12, fontWeight: '500', fontVariant: ['tabular-nums'] },
  line: { flexDirection: 'row', alignItems: 'center', gap: 3, height: LINE, paddingHorizontal: 3, borderLeftWidth: 3, borderRadius: 4, overflow: 'hidden' },
  more: { borderLeftWidth: 0 },
  text: { fontSize: 11, lineHeight: LINE },
  time: { fontVariant: ['tabular-nums'] },
  title: { flexShrink: 1 },
});
