import Icon from '@/components/tasks/task-icon';
import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Svg, { Defs, LinearGradient, Rect, Stop } from 'react-native-svg';
import {
  Banner,
  Button,
  Dialog,
  IconButton,
  List,
  Portal,
  Text,
  TextInput,
} from 'react-native-paper';

import {
  deleteExecution,
  moveExecution,
  readCalendar,
  readDay,
  takeBackBonus,
  type CalendarDay,
  type CalendarWeek,
} from '@/api/calendar';
import { currentMonday, dayString, shiftDay } from '@/dates';
import { useAppTheme } from '@/theme/theme-context';

export default function PointsCalendar({
  userId,
  canManage = false,
  revision = 0,
  title,
  onChanged,
}: {
  userId?: string;
  title?: string;
  canManage?: boolean;
  revision?: number;
  onChanged?: () => Promise<void>;
}) {
  const { t, i18n } = useTranslation();
  const { theme } = useAppTheme();
  const p = theme.palette;
  const gesture = useRef<{ x: number; y: number } | null>(null);
  const [weekStart, setWeekStart] = useState(currentMonday);
  const [week, setWeek] = useState<CalendarWeek | null>(null);
  const [day, setDay] = useState<CalendarDay | null>(null);
  const [editing, setEditing] = useState<string | null>(null);
  const [doneOn, setDoneOn] = useState('');
  const [removing, setRemoving] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);

  const load = useCallback(async () => {
    try {
      setWeek(await readCalendar(weekStart, userId));
      setError(false);
    } catch {
      setError(true);
    }
  }, [weekStart, userId]);

  useFocusEffect(
    useCallback(() => {
      if (revision >= 0) void load();
    }, [load, revision]),
  );

  const openDay = async (date: string) => {
    try {
      setDay(await readDay(date, userId));
      setError(false);
    } catch {
      setError(true);
    }
  };

  const change = async (action: () => Promise<unknown>) => {
    setBusy(true);
    try {
      await action();
      if (day) await openDay(day.date);
      await load();
      await onChanged?.();
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View
      testID="points-calendar"
      onTouchStart={(event) => {
        gesture.current = { x: event.nativeEvent.pageX, y: event.nativeEvent.pageY };
      }}
      onTouchEnd={(event) => {
        const start = gesture.current;
        gesture.current = null;
        if (!start || busy) return;
        const dx = event.nativeEvent.pageX - start.x;
        const dy = event.nativeEvent.pageY - start.y;
        if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;
        if (dx > 0) setWeekStart(shiftDay(weekStart, -7));
        else if (weekStart < currentMonday()) setWeekStart(shiftDay(weekStart, 7));
      }}
      style={{
        borderRadius: 28,
        paddingHorizontal: 12,
        paddingVertical: 16,
        gap: 16,
        overflow: 'hidden',
        backgroundColor: p.primaryContainer,
      }}
    >
      <View style={StyleSheet.absoluteFill} pointerEvents="none">
        <Svg width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 100 100">
          <Defs>
            <LinearGradient id="week-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
              <Stop offset="0" stopColor={p.primaryContainer} />
              <Stop offset="1" stopColor={p.secondaryContainer} />
            </LinearGradient>
          </Defs>
          <Rect width="100%" height="100%" fill="url(#week-gradient)" />
        </Svg>
      </View>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: 12,
        }}
      >
        <View
          style={{ flex: 1, flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}
        >
          <Icon source="calendar-blank-outline" size={20} color={p.onPrimaryContainer} />
          <Text style={{ fontSize: 16, fontWeight: '500', color: p.onPrimaryContainer }}>
            {title || t('week.title')}
          </Text>
          <Text style={{ fontSize: 12, color: p.onPrimaryContainer, opacity: 0.75 }}>
            {[week?.weekStart ?? weekStart, shiftDay(week?.weekStart ?? weekStart, 6)]
              .map((date) =>
                new Date(`${date}T12:00:00`).toLocaleDateString(i18n.language, {
                  day: 'numeric',
                  month: 'short',
                }),
              )
              .join(' – ')}
          </Text>
        </View>
        <View
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            gap: 6,
            borderRadius: 100,
            minHeight: 32,
            paddingHorizontal: 14,
            backgroundColor: `${p.onPrimaryContainer}1f`,
          }}
        >
          <Icon source="star-circle-outline" size={18} color={p.onPrimaryContainer} />
          <Text style={{ fontSize: 14, fontWeight: '500', color: p.onPrimaryContainer }}>
            {t('user.points', { points: week?.total ?? 0 })}
          </Text>
          {week?.bonusTotal ? (
            <Text style={{ fontSize: 11, color: p.primary }}>
              {t('week.bonus', { points: week.bonusTotal })}
            </Text>
          ) : null}
        </View>
      </View>
      {error ? (
        <Banner visible actions={[{ label: t('common.retry'), onPress: () => void load() }]}>
          {t('errors.generic')}
        </Banner>
      ) : null}
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: 8,
          marginBottom: -8,
        }}
      >
        <IconButton
          icon="arrow-left"
          size={20}
          accessibilityLabel={t('week.previous')}
          disabled={busy}
          onPress={() => setWeekStart(shiftDay(weekStart, -7))}
        />
        {weekStart < currentMonday() ? (
          <Button compact mode="text" onPress={() => setWeekStart(currentMonday())}>
            {t('week.thisWeek')}
          </Button>
        ) : null}
        <IconButton
          icon="arrow-right"
          size={20}
          accessibilityLabel={t('week.next')}
          disabled={busy || weekStart >= currentMonday()}
          onPress={() => setWeekStart(shiftDay(weekStart, 7))}
        />
      </View>
      <View testID="week-days" style={{ flexDirection: 'row', gap: 4 }}>
        {week?.days.map((one, index) => {
          const color = one.inStreak
            ? p.onStreakContainer
            : one.reachedThreshold
              ? p.primary
              : p.onSurfaceVariant;
          return (
            <Pressable
              key={one.date}
              accessibilityRole="button"
              accessibilityLabel={one.date}
              onPress={() => void openDay(one.date)}
              style={{
                flex: 1,
                minWidth: 0,
                minHeight: 68,
                gap: 4,
                paddingVertical: 8,
                borderRadius: 12,
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: one.inStreak
                  ? p.streakContainer
                  : one.reachedThreshold
                    ? p.surface
                    : `${p.surface}8c`,
                outlineWidth: one.isToday ? 2 : 0,
                outlineColor: p.onPrimaryContainer,
                outlineOffset: 1,
              }}
            >
              <Text style={{ fontSize: 11, fontWeight: '500', textTransform: 'uppercase', color }}>
                {t(`week.days.${['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][index]}`)}
              </Text>
              <Text
                style={{
                  fontSize: 16,
                  lineHeight: 24,
                  fontWeight: '500',
                  color: one.inStreak || one.reachedThreshold ? color : p.onSurface,
                }}
              >
                {one.points}
              </Text>
              {one.bonus > 0 ? (
                <Text style={{ fontSize: 11, color: p.primary }}>
                  {t('week.bonus', { points: one.bonus })}
                </Text>
              ) : null}
              {one.inStreak ? (
                <Text
                  testID={`week-day-flame-${one.date}`}
                  style={{ position: 'absolute', top: -7, right: -2, fontSize: 14 }}
                >
                  🔥
                </Text>
              ) : null}
            </Pressable>
          );
        })}
      </View>
      {week?.streak && week.streak.length > 0 ? (
        <View
          testID="week-streak"
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            gap: 8,
            paddingVertical: 12,
            paddingHorizontal: 16,
            borderRadius: 12,
            backgroundColor: `${p.surface}8c`,
          }}
        >
          <Icon source="fire" size={20} color={p.streak} />
          <Text style={{ flex: 1, fontSize: 14, lineHeight: 20 }}>
            {t(week.streak.met ? 'week.streakMet' : 'week.streakProgress', {
              count: week.streak.length,
              required: week.streak.requiredDays,
              pointsPerDay: week.streak.pointsPerDay,
              points: week.streak.bonusPoints,
            })}
          </Text>
        </View>
      ) : null}
      <Portal>
        <Dialog
          visible={Boolean(day)}
          onDismiss={() => {
            if (!busy) {
              setDay(null);
              setEditing(null);
              setRemoving(null);
            }
          }}
        >
          <Dialog.Title>{day?.date}</Dialog.Title>
          <Dialog.ScrollArea>
            <ScrollView contentContainerStyle={{ paddingVertical: 12 }}>
              {error ? <Text>{t('errors.generic')}</Text> : null}
              {!day?.tasks.length ? <Text>{t('week.nothingThatDay')}</Text> : null}
              {day?.tasks.map((task) => (
                <View key={task.id}>
                  <List.Item title={task.name} description={String(task.points)} />
                  {canManage && !day.closed ? (
                    <View style={{ flexDirection: 'row' }}>
                      <Button
                        disabled={busy}
                        onPress={() => {
                          setEditing(task.id);
                          setDoneOn(day.date);
                        }}
                      >
                        {t('week.changeDate')}
                      </Button>
                      <Button disabled={busy} onPress={() => setRemoving(task.id)}>
                        {t('common.delete')}
                      </Button>
                    </View>
                  ) : null}
                </View>
              ))}
              {day?.bonuses?.map((bonus) => (
                <View key={bonus.id}>
                  <List.Item title={bonus.name} description={String(bonus.points)} />
                  {canManage && !bonus.takenBack && bonus.points > 0 ? (
                    <Button
                      disabled={busy}
                      onPress={() => void change(() => takeBackBonus(bonus.id, day.userId))}
                    >
                      {t('common.delete')}
                    </Button>
                  ) : null}
                </View>
              ))}
              {editing ? (
                <>
                  <TextInput
                    label={t('week.changeDate')}
                    accessibilityLabel={t('week.changeDate')}
                    value={doneOn}
                    onChangeText={setDoneOn}
                    placeholder="YYYY-MM-DD"
                  />
                  <Button
                    disabled={
                      busy ||
                      !/^\d{4}-\d{2}-\d{2}$/.test(doneOn) ||
                      doneOn < weekStart ||
                      doneOn > shiftDay(weekStart, 6) ||
                      doneOn > dayString(new Date())
                    }
                    onPress={() => {
                      const id = editing;
                      setEditing(null);
                      void change(() => moveExecution(id, doneOn));
                    }}
                  >
                    {t('common.save')}
                  </Button>
                </>
              ) : null}
              {removing ? (
                <>
                  <Text>{t('week.deleteConfirmation')}</Text>
                  <Button disabled={busy} onPress={() => setRemoving(null)}>
                    {t('common.cancel')}
                  </Button>
                  <Button
                    disabled={busy}
                    onPress={() => {
                      const id = removing;
                      setRemoving(null);
                      void change(() => deleteExecution(id));
                    }}
                  >
                    {t('common.delete')}
                  </Button>
                </>
              ) : null}
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions>
            <Button
              disabled={busy}
              onPress={() => {
                setDay(null);
                setEditing(null);
                setRemoving(null);
              }}
            >
              {t('common.close')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
    </View>
  );
}
