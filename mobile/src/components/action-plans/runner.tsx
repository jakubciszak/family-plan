import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppState, BackHandler, StyleSheet, View } from 'react-native';
import { Button, ProgressBar, Surface, Switch, Text, useTheme } from 'react-native-paper';

import { activitiesOf, advanceRun, elapsedOf, formatTime, pauseRun, reminderInterval, resumeRun, type PlanRun } from '@/action-plans/run';
import SoundPicker, { useReminderSound } from './sound-picker';

export default function PlanRunner({ run, onChange, onLeave, onFinish, completingTask, completionError, onRetryCompletion }: {
  run: PlanRun; onChange: (run: PlanRun) => void; onLeave: (run: PlanRun) => void; onFinish: () => void;
  completingTask: boolean; completionError: string; onRetryCompletion: () => void;
}) {
  const { t } = useTranslation();
  const theme = useTheme();
  const audio = useReminderSound();
  const [now, setNow] = useState(Date.now);
  const [reminder, setReminder] = useState(false);
  const activities = activitiesOf(run.plan);
  const completed = run.index >= activities.length;
  const current = activities[run.index];
  const elapsed = elapsedOf(run, now);
  const paused = run.anchor === null;
  const estimated = run.plan.estimatedMinutes ? run.plan.estimatedMinutes * 60000 : null;
  const remaining = estimated !== null && run.mode === 'remaining';
  const stepBudget = estimated === null ? 0 : estimated / activities.length;
  const { play, stop } = audio;

  useEffect(() => {
    const leave = BackHandler.addEventListener('hardwareBackPress', () => {
      stop(); onLeave(pauseRun(run, Date.now())); return true;
    });
    return () => leave.remove();
  }, [onLeave, run, stop]);

  useEffect(() => {
    if (paused || completed) return;
    const tick = () => {
      if (AppState.currentState !== 'active') return;
      const time = Date.now();
      setNow(time);
      const step = elapsedOf(run, time).step;
      if (step - run.lastReminderStepMs >= (reminder ? 15000 : reminderInterval(run.plan))) {
        setReminder(true);
        if (run.sound) void play(run.reminderSound ?? run.plan.reminderSound);
        onChange({ ...run, lastReminderStepMs: step });
      }
    };
    const timer = setInterval(tick, 1000);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') tick(); else stop();
    });
    return () => { clearInterval(timer); subscription.remove(); };
  }, [run, paused, completed, reminder, onChange, play, stop]);

  const update = (next: PlanRun) => { stop(); setNow(Date.now()); setReminder(false); onChange(next); };
  const leave = () => { stop(); onLeave(pauseRun(run, Date.now())); };
  if (completed) return <View style={styles.content} testID="plan-completed">
    <Text variant="titleMedium">{run.plan.name}</Text>
    <Text variant="headlineLarge" accessibilityRole="header">{t('actionPlans.completed')}</Text>
    <Text>{t('actionPlans.completedHint')}</Text>
    <Text>{t('actionPlans.totalElapsed')}: {formatTime(elapsed.total)}</Text>
    {run.executionId && <View style={styles.content} accessibilityLiveRegion="polite">
      <Text>{t(run.taskCompleted ? 'actionPlans.taskCompleted' : 'actionPlans.taskCompletionPending')}</Text>
      {!!completionError && <><Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{completionError}</Text>
        <Button loading={completingTask} disabled={completingTask} onPress={onRetryCompletion}>{t('actionPlans.retry')}</Button></>}
    </View>}
    <Button mode="contained" disabled={!!run.executionId && !run.taskCompleted} onPress={onFinish}>{t(run.executionId ? 'actionPlans.backToTasks' : 'actionPlans.backToPlans')}</Button>
    {!!completionError && <Button onPress={leave}>{t('actionPlans.saveAndLeave')}</Button>}
  </View>;

  return <View style={styles.content} testID="plan-runner">
    <Text variant="titleMedium">{run.plan.name}</Text>
    <Button onPress={leave}>{t('actionPlans.saveAndLeave')}</Button>
    <Text>{t('actionPlans.progress', { current: run.index + 1, total: activities.length })}</Text>
    <ProgressBar progress={run.index / activities.length} accessibilityLabel={t('actionPlans.progressLabel')} accessibilityValue={{ min: 0, max: activities.length, now: run.index }} style={{ height: 8, borderRadius: 4 }} />
    <View style={styles.current} accessibilityLiveRegion="polite">
      {current.parent && <Text variant="titleMedium" style={{ color: theme.colors.onSurfaceVariant }}>{current.parent}</Text>}
      <Text variant="headlineLarge" accessibilityRole="header" style={{ textAlign: 'center' }}>{current.name}</Text>
      {paused && <Text style={{ textAlign: 'center' }}>{t('actionPlans.paused')}</Text>}
    </View>
    <View style={styles.clocks}>
      <Surface elevation={1} style={styles.clock}><Text style={{ textAlign: 'center' }}>{t(remaining ? 'actionPlans.stepRemaining' : 'actionPlans.stepElapsed')}</Text>
        <Text variant="headlineLarge" testID="step-clock" style={styles.digits}>{formatTime(remaining ? stepBudget - elapsed.step : elapsed.step)}</Text></Surface>
      <Surface elevation={1} style={styles.clock}><Text style={{ textAlign: 'center' }}>{t(remaining ? 'actionPlans.totalRemaining' : 'actionPlans.totalElapsed')}</Text>
        <Text variant="headlineLarge" testID="total-clock" style={styles.digits}>{formatTime(remaining ? estimated! - elapsed.total : elapsed.total)}</Text></Surface>
    </View>
    {remaining && (elapsed.step >= stepBudget || elapsed.total >= estimated!) && <Text>{t('actionPlans.timeIsEstimate')}</Text>}
    {estimated !== null && <Button onPress={() => onChange({ ...run, mode: remaining ? 'elapsed' : 'remaining' })}>{t(remaining ? 'actionPlans.showElapsed' : 'actionPlans.showRemaining')}</Button>}
    {reminder && !paused && <Surface elevation={1} style={[styles.reminder, { backgroundColor: theme.colors.secondaryContainer }]} accessibilityLiveRegion="polite">
      <Text>{t('actionPlans.reminder')}</Text>
      <Button onPress={() => update({ ...run, lastReminderStepMs: elapsedOf(run, Date.now()).step })}>{t('actionPlans.stillWorking')}</Button>
    </Surface>}
    {paused ? <Button mode="contained" onPress={() => update(resumeRun(run, Date.now()))}>{t('actionPlans.resume')}</Button>
      : <><Button mode="contained" contentStyle={{ minHeight: 56 }} labelStyle={{ fontSize: 20 }} onPress={() => update(advanceRun(run, Date.now()))}>{t(run.index === activities.length - 1 ? 'actionPlans.finish' : 'actionPlans.next')}</Button>
        <Button onPress={() => update(pauseRun(run, Date.now()))}>{t('actionPlans.pause')}</Button></>}
    <View style={styles.sound}><Text style={{ flex: 1 }}>{t('actionPlans.sound')}</Text>
      <Switch accessibilityLabel={t('actionPlans.sound')} value={run.sound} onValueChange={(sound) => { if (!sound) stop(); onChange({ ...run, sound }); }} /></View>
    {run.sound && <SoundPicker value={run.reminderSound ?? run.plan.reminderSound} onChange={(reminderSound) => onChange({ ...run, reminderSound })} audio={audio} />}
    <Text style={{ color: theme.colors.onSurfaceVariant }}>{t('actionPlans.keepOpen')}</Text>
  </View>;
}

const styles = StyleSheet.create({
  content: { gap: 16 }, current: { alignItems: 'center', gap: 12, paddingVertical: 24 },
  clocks: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  clock: { flex: 1, minWidth: 130, borderRadius: 20, padding: 16, alignItems: 'center', gap: 8 },
  digits: { fontVariant: ['tabular-nums'] }, reminder: { padding: 16, borderRadius: 16, gap: 8 },
  sound: { flexDirection: 'row', gap: 12, alignItems: 'center' },
});
