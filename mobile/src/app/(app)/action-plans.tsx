import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { KeyboardAvoidingView, Platform, RefreshControl, ScrollView, View } from 'react-native';
import { ActivityIndicator, Banner, Button, Card, Dialog, Portal, Text, useTheme } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useActionPlans } from '@/action-plans/context';
import { activitiesOf, pauseRun, startRun, type PlanRun } from '@/action-plans/run';
import { deleteActionPlan, listActionPlans, saveActionPlan, type ActionPlan, type PlanDraft, type PlanSnapshot } from '@/api/action-plans';
import { ApiError } from '@/api/client';
import { completeExecution, type TaskExecution } from '@/api/tasks';
import { listTeams, type Team } from '@/api/teams';
import ChoicePicker from '@/components/action-plans/choice-picker';
import PlanEditor from '@/components/action-plans/editor';
import PlanCard from '@/components/action-plans/plan-card';
import PlanRunner from '@/components/action-plans/runner';
import { useScreenBackground } from '@/personalisation/use-screen-background';

export default function ActionPlansScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { run, changeRun, ready, storageFailed, focused, setFocused, task, setTask } = useActionPlans();
  const [plans, setPlans] = useState<ActionPlan[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamError, setTeamError] = useState(false);
  const [scope, setScope] = useState('all');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<ActionPlan | null | undefined>(undefined);
  const [saving, setSaving] = useState(false);
  const [confirmation, setConfirmation] = useState<{ text: string; action: () => void } | null>(null);
  const [completionError, setCompletionError] = useState('');
  const [completingTask, setCompletingTask] = useState(false);
  const completing = useRef(false);
  const latest = useRef(run);
  useEffect(() => { latest.current = run; }, [run]);
  const scroll = useRef<ScrollView>(null);
  const loadTeams = useCallback(async () => {
    try { setTeams(await listTeams()); setTeamError(false); } catch { setTeamError(true); }
  }, []);
  const load = useCallback(async () => {
    setLoading(true);
    try { setPlans(await listActionPlans()); setError(''); } catch { setError(t('actionPlans.loadError')); }
    finally { setLoading(false); }
  }, [t]);
  useFocusEffect(useCallback(() => {
    void load(); void loadTeams();
    return () => {
      if (latest.current?.anchor !== null && latest.current) changeRun(pauseRun(latest.current, Date.now()));
      setFocused(false);
    };
  }, [load, loadTeams, changeRun, setFocused]));
  useEffect(() => { scroll.current?.scrollTo({ y: 0, animated: false }); }, [focused, editing, run?.index, task?.id]);

  useEffect(() => {
    if (!task) return;
    let active = true;
    void Promise.resolve().then(() => { if (active) { setEditing(undefined); setError(''); } });
    return () => { active = false; };
  }, [task]);

  const completeTask = useCallback(async () => {
    const current = latest.current;
    if (completing.current || !current?.executionId || current.taskCompleted) return;
    completing.current = true; setCompletingTask(true); setCompletionError('');
    try {
      await completeExecution(current.executionId);
      if (latest.current?.executionId === current.executionId) changeRun({ ...latest.current, taskCompleted: true });
    } catch { setCompletionError(t('actionPlans.taskCompletionError')); }
    finally { completing.current = false; setCompletingTask(false); }
  }, [changeRun, t]);
  useEffect(() => {
    if (!focused || !run?.executionId || run.taskCompleted || run.index !== activitiesOf(run.plan).length) return;
    let active = true;
    void Promise.resolve().then(() => { if (active) return completeTask(); });
    return () => { active = false; };
  }, [focused, run?.index, run?.executionId, run?.taskCompleted, run?.plan, completeTask]);

  const failureMessage = (failure: unknown, fallback: string) => {
    const key = failure instanceof ApiError ? (failure.body as { error?: unknown })?.error : null;
    return t(typeof key === 'string' && key.startsWith('actionPlans.') ? key : fallback);
  };
  const save = async (draft: PlanDraft) => {
    setSaving(true); setError('');
    try {
      const saved = await saveActionPlan(draft);
      setPlans((current) => [...current.filter((plan) => plan.id !== saved.id), saved]);
      setScope(saved.teamId || 'private'); setEditing(undefined);
    } catch (failure) { setError(failureMessage(failure, 'actionPlans.saveError')); }
    finally { setSaving(false); }
  };
  const remove = async (plan: ActionPlan) => {
    setSaving(true); setError('');
    try { await deleteActionPlan(plan.id); setPlans((current) => current.filter((item) => item.id !== plan.id)); }
    catch (failure) { setError(failureMessage(failure, 'actionPlans.deleteError')); }
    finally { setSaving(false); }
  };
  const begin = (plan: PlanSnapshot, linkedTask?: TaskExecution) => {
    if (linkedTask && run?.executionId === linkedTask.id) { setTask(null); setFocused(true); return; }
    const action = () => {
      setCompletionError('');
      changeRun({ ...startRun(plan, Date.now()), executionId: linkedTask?.id ?? null, executionName: linkedTask?.name ?? null });
      setTask(null); setFocused(true);
    };
    if (run) setConfirmation({ text: t('actionPlans.confirmRestart'), action }); else action();
  };
  const leave = (paused: PlanRun) => { changeRun(paused); setFocused(false); };
  const backToTasks = () => { setTask(null); router.navigate('/'); };
  const finish = () => { const linked = !!run?.executionId; changeRun(null); setFocused(false); if (linked) backToTasks(); };
  const visiblePlans = plans.filter((plan) => scope === 'all' || (scope === 'private' ? !plan.teamId : plan.teamId === scope));

  return <SafeAreaView edges={focused ? ['top', 'bottom', 'left', 'right'] : ['left', 'right']} style={{ flex: 1, backgroundColor: focused ? theme.colors.background : ground }}>
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
      <ScrollView ref={scroll} keyboardShouldPersistTaps="handled" contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 16, width: '100%', maxWidth: 720, alignSelf: 'center' }}
        refreshControl={!focused && editing === undefined ? <RefreshControl refreshing={loading} onRefresh={() => { void load(); void loadTeams(); }} /> : undefined}>
        {storageFailed && <Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{t('actionPlans.storageError')}</Text>}
        {!ready ? <ActivityIndicator /> : focused && run ? <PlanRunner run={run} onChange={changeRun} onLeave={leave} onFinish={finish} completingTask={completingTask} completionError={completionError} onRetryCompletion={() => void completeTask()} /> : <>
          {teamError && <Banner visible={teamError} actions={[{ label: t('actionPlans.retry'), onPress: () => void loadTeams() }]}>{t('actionPlans.teamLoadError')}</Banner>}
          {editing !== undefined ? <PlanEditor plan={editing} teams={teams} saving={saving} error={error} onSave={(draft) => void save(draft)} onCancel={() => { setEditing(undefined); setError(''); }} /> : <>
            <Text>{t('actionPlans.intro')}</Text>
            <Button mode="contained" icon="plus" accessibilityLabel={t('actionPlans.create')} onPress={() => { setEditing(null); setError(''); }}>{t('actionPlans.create')}</Button>
            {task?.actionPlan && <View style={{ gap: 8 }} testID="task-plan-preview"><PlanCard plan={task.actionPlan} taskName={task.name} onStart={() => begin(task.actionPlan!, task)} />
              <Button onPress={backToTasks}>{t('actionPlans.backToTasks')}</Button></View>}
            {run && <Card mode="contained" testID="saved-plan"><Card.Content style={{ gap: 12 }}>
              <Text variant="titleLarge">{run.plan.name}</Text>
              <Text>{run.index === activitiesOf(run.plan).length ? t('actionPlans.taskCompletionPending') : t('actionPlans.savedProgress', { current: run.index + 1, total: activitiesOf(run.plan).length })}</Text>
              {run.executionName && <Text>{t('actionPlans.forTask', { name: run.executionName })}</Text>}
              <Button mode="contained" onPress={() => setFocused(true)}>{t('actionPlans.returnToRun')}</Button>
              <Button onPress={() => setConfirmation({ text: t('actionPlans.confirmDiscard'), action: () => changeRun(null) })}>{t('actionPlans.discard')}</Button>
            </Card.Content></Card>}
            <ChoicePicker label={t('actionPlans.filter')} value={scope} onChange={setScope} options={[
              { value: 'all', label: t('actionPlans.allPlans') }, { value: 'private', label: t('actionPlans.private') }, ...teams.map((team) => ({ value: team.id, label: team.name })),
            ]} />
            {!!error && <View><Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{error}</Text><Button onPress={() => void load()}>{t('actionPlans.retry')}</Button></View>}
            {loading ? <ActivityIndicator /> : !visiblePlans.length && !error ? <View style={{ paddingVertical: 24, gap: 12 }}><Text variant="titleLarge">{t(plans.length ? 'actionPlans.emptyScope' : 'actionPlans.empty')}</Text><Text>{t('actionPlans.emptyHint')}</Text></View> : visiblePlans.map((plan) => <PlanCard key={plan.id} plan={plan} busy={saving}
              scope={plan.teamId ? t('actionPlans.teamPlan', { name: teams.find((team) => team.id === plan.teamId)?.name ?? t('teams.title') }) : t('actionPlans.private')}
              onStart={() => begin(plan)} onEdit={plan.canManage !== false ? () => { setEditing(plan); setError(''); } : undefined}
              onDelete={plan.canManage !== false ? () => setConfirmation({ text: t('actionPlans.confirmDelete', { name: plan.name }), action: () => void remove(plan) }) : undefined} />)}
          </>}
        </>}
      </ScrollView>
    </KeyboardAvoidingView>
    <Portal><Dialog visible={!!confirmation} onDismiss={() => setConfirmation(null)}>
      <Dialog.Content><Text>{confirmation?.text}</Text></Dialog.Content>
      <Dialog.Actions><Button onPress={() => setConfirmation(null)}>{t('common.cancel')}</Button>
        <Button onPress={() => { const action = confirmation?.action; setConfirmation(null); action?.(); }}>{t('common.confirm')}</Button></Dialog.Actions>
    </Dialog></Portal>
  </SafeAreaView>;
}
