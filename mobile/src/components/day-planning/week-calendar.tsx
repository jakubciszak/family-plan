import { useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from 'react-native';
import { Text, useTheme } from 'react-native-paper';

import type { Busy, Calendar, Occurrence } from '@/api/day-planning';
import { localParts } from '@/day-planning/time';
import { layoutWeek, type WeekSegment } from '@/day-planning/week-layout';

type CalendarItem = { key: string; start: string; end: string; allDay?: boolean; timeZone?: string; event?: Occurrence; busy?: Busy };
type Props = { calendar: Calendar; date: string; zone: string; nameFor: (id: string) => string; onOpen: (event: Occurrence) => void; onCreate?: (slot: { date: string; hour?: number }) => void };
const HOUR_HEIGHT = 72;
const AXIS_WIDTH = 46;
const HEADER_HEIGHT = 58;
const ALL_DAY_HEIGHT = 38;
const clock = (minute: number) => `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(Math.floor(minute % 60)).padStart(2, '0')}`;
const inkFor = (color: string) => {
  const channels = color.slice(1).match(/.{2}/g)?.map((channel) => {
    const value = parseInt(channel, 16) / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return channels && channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722 > 0.179 ? '#171717' : '#ffffff';
};

export default function WeekCalendar({ calendar, date, zone, nameFor, onOpen, onCreate }: Props) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const { width, height: screenHeight } = useWindowDimensions();
  const heading = useRef<ScrollView>(null);
  const days = useMemo(() => layoutWeek<CalendarItem>([
    ...calendar.events.map((event) => ({ key: `event-${event.id}-${event.occurrenceKey}`, start: event.start, end: event.end, allDay: event.allDay, timeZone: event.timeZone, event })),
    ...calendar.busy.map((busy, index) => ({ key: `busy-${busy.personId}-${index}`, start: busy.start, end: busy.end, busy })),
  ], date, zone), [calendar, date, zone]);
  const segments = days.flatMap((day) => day.timed);
  const firstHour = Math.min(6, ...segments.map((segment) => Math.floor(segment.startMinute / 60)));
  const lastHour = Math.max(22, ...segments.map((segment) => Math.ceil(segment.displayEndMinute / 60)));
  const hours = Array.from({ length: lastHour - firstHour + 1 }, (_, index) => firstHour + index);
  const gridHeight = (lastHour - firstHour) * HOUR_HEIGHT;
  const allDayHeight = Math.max(1, ...days.map((day) => day.allDay.length)) * ALL_DAY_HEIGHT + 8;
  const dayWidth = Math.max(156, (width - 32 - AXIS_WIDTH) / 7);
  const today = localParts(new Date().toISOString(), zone).date;
  const dateFormatter = new Intl.DateTimeFormat(i18n.language, { weekday: 'short', day: 'numeric', month: 'numeric', timeZone: 'UTC' });
  const timeFormatter = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit', timeZone: zone });
  const renderItem = (segment: WeekSegment<CalendarItem>, allDay: boolean) => {
    const { item } = segment;
    const event = item.event;
    const title = event?.title ?? `${t('dayPlanning.busy')} · ${nameFor(item.busy!.personId)}`;
    const rawColor = event?.tags[0]?.color;
    const color = rawColor && /^#[a-f\d]{6}$/i.test(rawColor) ? rawColor : theme.colors.primary;
    const backgroundColor = event ? color : theme.colors.surfaceVariant;
    const textColor = event ? inkFor(color) : theme.colors.onSurfaceVariant;
    const height = (segment.displayEndMinute - segment.startMinute) * HOUR_HEIGHT / 60;
    const compact = !allDay && height < 48;
    const times = allDay ? t('dayPlanning.allDay') : `${timeFormatter.format(new Date(item.start))} – ${timeFormatter.format(new Date(item.end))}`;
    const accessibilityLabel = [title, times, segment.continuesBefore ? t('dayPlanning.continuesBefore') : '', segment.continuesAfter ? t('dayPlanning.continuesAfter') : '', segment.overlapCount > 0 ? t('dayPlanning.simultaneous', { count: segment.overlapCount + 1 }) : ''].filter(Boolean).join('. ');
    const content = <>
      <Text numberOfLines={compact || allDay ? 1 : 2} style={[styles.eventTitle, { color: textColor, textDecorationLine: event?.participation === 'DECLINED' ? 'line-through' : 'none' }]}>
        {segment.continuesBefore ? '‹ ' : ''}{title}{segment.continuesAfter ? ' ›' : ''}
      </Text>
      {!allDay && !compact && <Text numberOfLines={1} style={[styles.eventTime, { color: textColor }]}>{times}</Text>}
      {!allDay && height >= 78 && segment.overlapCount > 0 && <Text numberOfLines={1} style={[styles.eventTime, { color: textColor }]}>{t('dayPlanning.simultaneous', { count: segment.overlapCount + 1 })}</Text>}
    </>;
    const blockStyle = [styles.event, { backgroundColor, borderColor: event ? color : theme.colors.outline, borderStyle: event?.blocksTime === false ? 'dashed' as const : 'solid' as const }, allDay ? { height: ALL_DAY_HEIGHT - 4, marginHorizontal: 3, marginBottom: 4 } : {
      position: 'absolute' as const, top: (segment.startMinute / 60 - firstHour) * HOUR_HEIGHT,
      height: Math.max(1, height - 2), left: segment.column * (dayWidth - 6) / segment.columns + 3, width: (dayWidth - 6) / segment.columns - 3,
    }];
    return event ? <Pressable key={item.key} accessibilityRole="button" accessibilityLabel={accessibilityLabel} onPress={() => onOpen(event)} style={blockStyle} testID={`week-event-${event.id}-${segment.day}`}>{content}</Pressable>
      : <View key={item.key} accessible accessibilityLabel={accessibilityLabel} style={blockStyle} testID={`week-busy-${segment.day}`}>{content}</View>;
  };
  return <View testID="day-week-calendar" style={{ gap: 8 }}>
    {!calendar.events.length && !calendar.busy.length && <Text>{t('dayPlanning.weekEmpty')}</Text>}
    <View style={[styles.calendar, { borderColor: theme.colors.outlineVariant, backgroundColor: theme.colors.surface }]}>
      <View style={{ flexDirection: 'row' }}>
        <View style={{ width: AXIS_WIDTH }}>
          <View style={{ height: HEADER_HEIGHT }} />
          <View style={{ height: allDayHeight, paddingHorizontal: 2, justifyContent: 'center' }}><Text style={styles.axisLabel}>{t('dayPlanning.allDay')}</Text></View>
        </View>
        <ScrollView ref={heading} horizontal scrollEnabled={false} showsHorizontalScrollIndicator={false} style={{ flex: 1 }}>
          <View style={{ flexDirection: 'row' }}>
            {days.map((day) => <View key={day.date} testID={`week-day-${day.date}`} style={{ width: dayWidth, borderLeftWidth: 1, borderColor: theme.colors.outlineVariant }}>
              <View style={{ height: HEADER_HEIGHT, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 5, backgroundColor: day.date === today ? theme.colors.primaryContainer : theme.colors.surface }}>
                <Text variant="titleSmall" accessibilityRole="header" style={{ color: day.date === today ? theme.colors.onPrimaryContainer : theme.colors.onSurface }}>{dateFormatter.format(new Date(`${day.date}T12:00:00Z`))}</Text>
              </View>
              <Pressable testID={`week-all-day-${day.date}`} accessible={false} disabled={!onCreate} onPress={() => onCreate?.({ date: day.date })}
                style={({ pressed }) => ({ height: allDayHeight, paddingTop: 4, borderTopWidth: 1, borderColor: theme.colors.outlineVariant, backgroundColor: pressed ? theme.colors.surfaceVariant : 'transparent' })}>
                {day.allDay.map((segment) => renderItem(segment, true))}
              </Pressable>
            </View>)}
          </View>
        </ScrollView>
      </View>
      <ScrollView nestedScrollEnabled directionalLockEnabled style={{ height: Math.max(280, Math.min(480, screenHeight * 0.55)) }} contentContainerStyle={{ flexDirection: 'row', paddingBottom: 10 }} testID="week-hours-scroll">
        <View style={{ width: AXIS_WIDTH, height: gridHeight }}>
          {hours.map((hour) => <Text key={hour} style={[styles.axisLabel, { position: 'absolute', top: (hour - firstHour) * HOUR_HEIGHT - (hour === firstHour ? 0 : 8), right: 4 }]}>{clock(hour * 60)}</Text>)}
        </View>
        <ScrollView horizontal directionalLockEnabled nestedScrollEnabled showsHorizontalScrollIndicator accessibilityLabel={t('dayPlanning.weekCalendar')} testID="week-horizontal-scroll" style={{ flex: 1 }} scrollEventThrottle={16} onScroll={(event) => heading.current?.scrollTo({ x: event.nativeEvent.contentOffset.x, animated: false })}>
          <View style={{ flexDirection: 'row' }}>
            {days.map((day) => <View key={day.date} style={{ width: dayWidth, height: gridHeight, borderLeftWidth: 1, borderColor: theme.colors.outlineVariant }}>
              {hours.map((hour) => <View key={hour} pointerEvents="none" style={{ position: 'absolute', left: 0, right: 0, top: (hour - firstHour) * HOUR_HEIGHT, borderTopWidth: 1, borderColor: theme.colors.outlineVariant }} />)}
              {onCreate && hours.slice(0, -1).map((hour) => <Pressable key={`slot-${hour}`} testID={`week-slot-${day.date}-${hour}`} accessible={false} onPress={() => onCreate({ date: day.date, hour })}
                style={({ pressed }) => ({ position: 'absolute', left: 0, right: 0, top: (hour - firstHour) * HOUR_HEIGHT + 1, height: HOUR_HEIGHT - 1, backgroundColor: pressed ? theme.colors.surfaceVariant : 'transparent' })} />)}
              {day.timed.map((segment) => renderItem(segment, false))}
            </View>)}
          </View>
        </ScrollView>
      </ScrollView>
    </View>
  </View>;
}

const styles = StyleSheet.create({
  calendar: { borderWidth: 1, borderRadius: 12, overflow: 'hidden' },
  axisLabel: { fontSize: 10, textAlign: 'center', fontVariant: ['tabular-nums'] },
  event: { paddingHorizontal: 5, paddingVertical: 3, borderWidth: 1, borderLeftWidth: 3, borderRadius: 5, overflow: 'hidden' },
  eventTitle: { fontSize: 12, fontWeight: '600', lineHeight: 15 },
  eventTime: { fontSize: 10, lineHeight: 14, fontVariant: ['tabular-nums'] },
});
