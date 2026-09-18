import ReturnTaskDialog from '@/components/return-task-dialog';
import { useActionPlans } from '@/action-plans/context';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Dialog,
  Menu,
  Portal,
  RadioButton,
  Text,
  TextInput,
} from 'react-native-paper';

import { useAuth } from '@/auth/auth-context';
import apiClient from '@/api/client';
import {
  abandonExecution,
  approveExecution,
  completeExecution,
  listAwaitingApproval,
  listMyExecutions,
  listTaskTemplates,
  takeTaskTemplate,
  type TaskExecution,
  type TaskTemplate,
} from '@/api/tasks';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import Celebration from '@/components/celebration';
import PointsCalendar from '@/components/points-calendar';
import MemberWeeks from '@/components/tasks/member-weeks';
import Standings from '@/components/tasks/standings';
import TasksHeader from '@/components/tasks/tasks-header';
import {
  EmptyTasks,
  SectionHeading,
  TaskBadge,
  TaskButton as Button,
  taskStyles,
} from '@/components/tasks/task-ui';
import { dayString, isDay, shiftDay } from '@/dates';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';
import { useAppTheme } from '@/theme/theme-context';

const BACKLOG_DAYS = 7;
const OPEN = ['new', 'pending'];
const dayOffset = (days: number) => shiftDay(dayString(new Date()), days);

export default function TasksScreen() {
  const { setTask: previewTaskPlan } = useActionPlans();
  const { t } = useTranslation();
  const { user } = useAuth();
  const { theme } = useAppTheme();
  const ground = useScreenBackground();
  const { own } = usePersonalisation();
  const [celebrating, setCelebrating] = useState(false);
  const endCelebration = useCallback(() => setCelebrating(false), []);
  const [revision, setRevision] = useState(0);
  const [executions, setExecutions] = useState<TaskExecution[]>([]);
  const [templates, setTemplates] = useState<TaskTemplate[]>([]);
  const [returning, setReturning] = useState<TaskExecution | null>(null);
  const [awaiting, setAwaiting] = useState<TaskExecution[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [chosen, setChosen] = useState<string | null>(null);
  const [memberGroup, setMemberGroup] = useState<{ teamId: string; members: Member[] } | null>(
    null,
  );
  const [teamMenu, setTeamMenu] = useState(false);
  const [availableOpen, setAvailableOpen] = useState(false);
  const [standingsOpen, setStandingsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [backlogFor, setBacklogFor] = useState<TaskExecution | null>(null);
  const [backlogDay, setBacklogDay] = useState('');
  const [handOut, setHandOut] = useState<TaskTemplate | null>(null);
  const [handOutMember, setHandOutMember] = useState('');
  const [handOutDay, setHandOutDay] = useState(dayOffset(0));
  const selectedTeam = teams.find((team) => team.id === chosen) ?? teams[0];
  const isAdmin = selectedTeam?.role === 'admin';
  const members = memberGroup?.teamId === selectedTeam?.id ? (memberGroup?.members ?? []) : [];

  const load = useCallback(async () => {
    try {
      const [mine, pool, loadedTeams] = await Promise.all([
        listMyExecutions(),
        listTaskTemplates(),
        listTeams(),
      ]);
      setExecutions(mine);
      setTemplates(pool);
      setTeams(loadedTeams);
      setAwaiting(
        loadedTeams.some((team) => team.role === 'admin') ? await listAwaitingApproval() : [],
      );
      setError(null);
    } catch {
      setError(t('tasks.loadFailed'));
    }
  }, [t]);

  useFocusEffect(
    useCallback(() => {
      void load().finally(() => { setLoading(false); setRevision((value) => value + 1); });
    }, [load]),
  );
  useEffect(() => {
    let active = true;
    if (selectedTeam)
      void listMembers(selectedTeam.id)
        .then((held) => {
          if (active)
            setMemberGroup({
              teamId: selectedTeam.id,
              members: held.filter((member) => member.role !== 'admin'),
            });
        })
        .catch(() => {
          if (active) setError(t('tasks.loadFailed'));
        });
    return () => {
      active = false;
    };
  }, [selectedTeam, t]);

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRevision((value) => value + 1);
    setRefreshing(false);
  };
  const act = async (id: string, action: () => Promise<unknown>, cheer = false) => {
    setBusyId(id);
    setError(null);
    try {
      await action();
      if (cheer && own?.celebrates) setCelebrating(true);
      setRevision((value) => value + 1);
      await load();
    } catch {
      setError(t('tasks.actionFailed'));
    } finally {
      setBusyId(null);
    }
  };
  const belongs = (execution: TaskExecution) =>
    !selectedTeam ||
    !execution.taskTemplateId ||
    templates.find((template) => template.id === execution.taskTemplateId)?.teamId ===
      selectedTeam.id;
  const mine = executions
    .filter(
      (execution) =>
        [...OPEN, 'completed', 'rejected'].includes(execution.status) && belongs(execution),
    )
    .sort(
      (a, b) =>
        ['new', 'pending', 'rejected', 'completed'].indexOf(a.status) -
        ['new', 'pending', 'rejected', 'completed'].indexOf(b.status),
    );
  const pool = templates.filter(
    (template) =>
      template.isActive &&
      template.remaining !== 0 &&
      (!selectedTeam || template.teamId === selectedTeam.id),
  );
  const matching = pool.filter((template) =>
    template.name.toLocaleLowerCase().includes(search.trim().toLocaleLowerCase()),
  );
  const approvals = awaiting.filter(belongs);
  const points = (value: number) => (
    <TaskBadge tone="primary" icon="star-circle-outline">
      {t('user.points', { points: value })}
    </TaskBadge>
  );
  const cardStyle = { ...taskStyles.card, backgroundColor: theme.palette.surfaceContainerLow };
  const limitLabel = (template: TaskTemplate) => {
    const limit = template.executionLimit;
    if (!limit || limit.type === 'unlimited') return t('taskTypes.limitUnlimited');
    if (limit.type === 'once') return t('taskTypes.limitOnce');
    return t(
      `taskTypes.limit${limit.type === 'per_day' ? 'PerDay' : limit.type === 'per_week' ? 'PerWeek' : 'PerMonth'}`,
      { count: limit.count },
    );
  };
  const myTasks = (
    <View testID="my-tasks" style={taskStyles.section}>
      <SectionHeading title={t('tasks.mySection')} icon="account-outline" />
      {!mine.length ? (
        <EmptyTasks>{t('tasks.noneTaken')}</EmptyTasks>
      ) : (
        mine.map((execution) => {
          const busy = busyId === execution.id;
          return (
            <View
              key={execution.id}
              testID={`task-${execution.name}`}
              style={[
                cardStyle,
                execution.status === 'completed' && {
                  backgroundColor: theme.palette.surfaceContainer,
                  boxShadow: 'none',
                },
                execution.status === 'rejected' && {
                  borderLeftWidth: 3,
                  borderColor: theme.colors.error,
                },
              ]}
            >
              <Text style={taskStyles.name}>{execution.name}</Text>
              <View style={taskStyles.badges}>
                {points(execution.points)}
                {execution.status === 'completed' ? (
                  <TaskBadge tone="success" icon="clock-outline">
                    {t('tasks.awaitingApproval')}
                  </TaskBadge>
                ) : null}
                {execution.status === 'rejected' ? (
                  <TaskBadge tone="error" icon="alert-circle-outline">
                    {t('tasks.statusRejected')}
                  </TaskBadge>
                ) : null}
              </View>
              {execution.rejectionReason ? (
                <Text style={{ fontSize: 12, color: theme.colors.error }}>
                  {t('tasks.rejectedReason', { reason: execution.rejectionReason })}
                </Text>
              ) : null}
              {execution.actionPlan && [...OPEN, 'rejected'].includes(execution.status) && <Button icon="format-list-checks" accessibilityLabel={t('actionPlans.showPlan')} disabled={busy} onPress={() => { previewTaskPlan(execution); router.navigate('/action-plans'); }}>{t('actionPlans.showPlan')}</Button>}
              {OPEN.includes(execution.status) ? (
                <View style={taskStyles.actions}>
                  <Button
                    style={taskStyles.button}
                    mode="contained"
                    icon="check"
                    buttonColor={theme.palette.success}
                    textColor={theme.palette.onSuccess}
                    loading={busy}
                    disabled={busy}
                    onPress={() =>
                      void act(execution.id, () => completeExecution(execution.id), true)
                    }
                  >
                    {t('tasks.complete')}
                  </Button>
                  <Button
                    mode="outlined"
                    icon="calendar-blank-outline"
                    disabled={busy}
                    onPress={() => {
                      setBacklogFor(execution);
                      setBacklogDay(dayOffset(-1));
                    }}
                  >
                    {t('tasks.backlog')}
                  </Button>
                  <Button
                    style={taskStyles.button}
                    mode="outlined"
                    icon="undo"
                    disabled={busy}
                    onPress={() => void act(execution.id, () => abandonExecution(execution.id))}
                  >
                    {t('tasks.giveBack')}
                  </Button>
                </View>
              ) : execution.status === 'rejected' ? (
                <Button
                  mode="contained"
                  icon="check"
                  loading={busy}
                  disabled={busy}
                  onPress={() =>
                    void act(execution.id, () => completeExecution(execution.id), true)
                  }
                >
                  {t('tasks.submitAgain')}
                </Button>
              ) : null}
            </View>
          );
        })
      )}
    </View>
  );
  const available = (
    <View testID="available-tasks" style={taskStyles.section}>
      <SectionHeading title={t('tasks.availableSection')} icon="format-list-checks">
        {isAdmin ? (
          <Button
            compact
            mode="contained-tonal"
            icon="plus"
            onPress={() => router.navigate('/task-types')}
          >
            {t('taskTypes.create')}
          </Button>
        ) : null}
      </SectionHeading>
      {pool.length ? (
        <TextInput
          mode="outlined"
          label={t('tasks.searchAvailable')}
          accessibilityLabel={t('tasks.searchAvailable')}
          value={search}
          onChangeText={setSearch}
          style={{ maxWidth: 320, backgroundColor: theme.colors.surface }}
        />
      ) : null}
      {!pool.length ? (
        <EmptyTasks>{t('tasks.noTasks')}</EmptyTasks>
      ) : !matching.length ? (
        <EmptyTasks>{t('tasks.noMatches')}</EmptyTasks>
      ) : (
        matching.map((template) => (
          <View key={template.id} testID={`task-${template.name}`} style={cardStyle}>
            <Text style={taskStyles.name}>{template.name}</Text>
            <View style={taskStyles.badges}>
              {points(template.points)}
              <TaskBadge icon="calendar-blank-outline">{limitLabel(template)}</TaskBadge>
              {template.remaining !== null ? (
                <TaskBadge>{t('taskTypes.remaining', { count: template.remaining })}</TaskBadge>
              ) : null}
            </View>
            {template.description ? (
              <Text style={{ fontSize: 14, lineHeight: 20, color: theme.colors.onSurfaceVariant }}>
                {template.description}
              </Text>
            ) : null}
            <View style={taskStyles.actions}>
              <Button
                style={taskStyles.button}
                mode="contained"
                icon="plus"
                loading={busyId === template.id}
                disabled={busyId === template.id}
                onPress={() => void act(template.id, () => takeTaskTemplate(template.id))}
              >
                {t('tasks.takeIt')}
              </Button>
              {isAdmin && members.length ? (
                <Button
                  style={taskStyles.button}
                  mode="contained-tonal"
                  icon="account-outline"
                  onPress={() => {
                    setHandOut(template);
                    setHandOutMember(members[0].userId);
                    setHandOutDay(dayOffset(0));
                  }}
                >
                  {t('tasks.handOut')}
                </Button>
              ) : null}
            </View>
          </View>
        ))
      )}
    </View>
  );
  const approvalQueue = (
    <View testID="approval-queue" style={taskStyles.section}>
      <SectionHeading title={t('tasks.approvalSection')} icon="thumb-up-outline" />
      {!approvals.length ? (
        <EmptyTasks>{t('tasks.nothingToApprove')}</EmptyTasks>
      ) : (
        approvals.map((execution) => (
          <View key={execution.id} testID={`approval-${execution.name}`} style={cardStyle}>
            <Text style={taskStyles.name}>{execution.name}</Text>
            <View style={taskStyles.badges}>
              {points(execution.points)}
              <TaskBadge icon="account-outline">{execution.assignedUserName}</TaskBadge>
            </View>
            <View style={taskStyles.actions}><Button
              mode="contained"
              icon="thumb-up-outline"
              loading={busyId === execution.id}
              disabled={busyId === execution.id}
              onPress={() => void act(execution.id, () => approveExecution(execution.id), true)}
            >
              {t('tasks.approve')}
            </Button>
            <Button mode="outlined" icon="undo" disabled={busyId === execution.id} onPress={() => setReturning(execution)}>{t('member.reject')}</Button></View>
          </View>
        ))
      )}
    </View>
  );
  const validDay = (value: string) =>
    isDay(value) && value >= dayOffset(-BACKLOG_DAYS) && value <= dayOffset(0);
  const handTo = (book: boolean) => {
    if (!handOut) return;
    const template = handOut;
    void act(template.id, async () => {
      await apiClient.post(`/api/task-templates/${template.id}/${book ? 'book' : 'assign'}`, {
        userId: handOutMember,
        ...(book ? { doneOn: handOutDay } : {}),
      });
      setHandOut(null);
    });
  };

  return (
    <View style={{ flex: 1, backgroundColor: ground }}>
      <TasksHeader revision={revision} />
      {celebrating ? (
        <Celebration withSound={own?.makesSound ?? false} onDone={endCelebration} />
      ) : null}
      {error ? (
        <Banner visible actions={[{ label: t('common.close'), onPress: () => setError(null) }]}>
          {error}
        </Banner>
      ) : null}
      <ScrollView
        contentInsetAdjustmentBehavior="automatic"
        contentContainerStyle={{ padding: 16, paddingBottom: 32, gap: 24 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />}
      >
        <View
          style={{
            flexDirection: 'row',
            flexWrap: 'wrap',
            justifyContent: 'space-between',
            alignItems: 'center',
            gap: 16,
          }}
        >
          <Text
            accessibilityRole="header"
            style={{ fontSize: 28, lineHeight: 36, fontWeight: '400' }}
          >
            {t('tasks.title')}
          </Text>
          {teams.length > 1 ? (
            <Menu
              visible={teamMenu}
              onDismiss={() => setTeamMenu(false)}
              anchor={
                <Button
                  mode="outlined"
                  icon="chevron-down"
                  accessibilityLabel={t('teams.title')}
                  onPress={() => setTeamMenu(true)}
                >
                  {selectedTeam?.name}
                </Button>
              }
            >
              {teams.map((team) => (
                <Menu.Item
                  key={team.id}
                  title={team.name}
                  onPress={() => {
                    setChosen(team.id);
                    setSearch('');
                    setTeamMenu(false);
                  }}
                />
              ))}
            </Menu>
          ) : null}
        </View>
        {loading ? (
          <ActivityIndicator />
        ) : (
          (own?.home?.length ? own.home : ['week', 'tasks', 'standings']).map((part) => {
            if (part === 'week')
              return isAdmin ? (
                <MemberWeeks
                  key={`${part}-${selectedTeam?.id}`}
                  storageKey={`hidden-week-members:${user?.id}:${selectedTeam?.id}`}
                  members={members}
                  canManage
                  revision={revision}
                />
              ) : (
                <PointsCalendar key={part} revision={revision} />
              );
            if (part === 'members')
              return !isAdmin ? (
                <MemberWeeks
                  key={`${part}-${selectedTeam?.id}`}
                  storageKey={`hidden-week-members:${user?.id}:${selectedTeam?.id}`}
                  members={members}
                  canManage={false}
                  revision={revision}
                />
              ) : null;
            if (part === 'standings')
              return (
                <View key={part} style={taskStyles.section}>
                  {isAdmin ? (
                    <SectionHeading
                      title={t('tasks.standingsCollapsed')}
                      icon="star-circle-outline"
                      expanded={standingsOpen}
                      onPress={() => setStandingsOpen((open) => !open)}
                    />
                  ) : null}
                  {!isAdmin || standingsOpen ? (
                    <Standings
                      key={selectedTeam?.id}
                      teamId={selectedTeam?.id}
                      revision={revision}
                      canManage={isAdmin}
                    />
                  ) : null}
                </View>
              );
            if (part !== 'tasks') return null;
            return (
              <View key={part} style={{ gap: 24 }}>
                {isAdmin ? approvalQueue : myTasks}
                {isAdmin ? (
                  <View style={taskStyles.section}>
                    <SectionHeading
                      title={t('tasks.availableCollapsed')}
                      icon="format-list-checks"
                      expanded={availableOpen}
                      onPress={() => setAvailableOpen((open) => !open)}
                    />
                    {availableOpen ? (
                      <View style={{ gap: 24 }}>
                        {mine.length ? myTasks : null}
                        {available}
                      </View>
                    ) : null}
                  </View>
                ) : (
                  available
                )}
              </View>
            );
          })
        )}
      </ScrollView>
      <Portal>
        <Dialog
          visible={Boolean(backlogFor)}
          onDismiss={() => setBacklogFor(null)}
          testID="backlog-dialog"
        >
          <Dialog.Title>
            {t('tasks.backlogHeadline', { name: backlogFor?.name ?? '' })}
          </Dialog.Title>
          <Dialog.Content style={{ gap: 12 }}>
            <TextInput
              mode="outlined"
              label={t('tasks.backlogDate')}
              accessibilityLabel={t('tasks.backlogDate')}
              value={backlogDay}
              onChangeText={setBacklogDay}
              placeholder="YYYY-MM-DD"
            />
            <Text variant="bodySmall">{t('tasks.backlogHint', { days: BACKLOG_DAYS })}</Text>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setBacklogFor(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={!validDay(backlogDay)}
              onPress={() => {
                const execution = backlogFor;
                setBacklogFor(null);
                if (execution)
                  void act(execution.id, () => completeExecution(execution.id, backlogDay), true);
              }}
            >
              {t('tasks.complete')}
            </Button>
          </Dialog.Actions>
        </Dialog>
        <Dialog
          visible={Boolean(handOut)}
          onDismiss={() => {
            if (!busyId) setHandOut(null);
          }}
          testID="hand-out-dialog"
        >
          <Dialog.Title>{t('tasks.handOutHeadline', { name: handOut?.name })}</Dialog.Title>
          <Dialog.ScrollArea>
            <ScrollView>
              <RadioButton.Group value={handOutMember} onValueChange={setHandOutMember}>
                {members.map((member) => (
                  <RadioButton.Item
                    key={member.userId}
                    value={member.userId}
                    label={member.userName}
                  />
                ))}
              </RadioButton.Group>
              <TextInput
                label={t('tasks.handOutDate')}
                accessibilityLabel={t('tasks.handOutDate')}
                value={handOutDay}
                onChangeText={setHandOutDay}
              />
              <Text style={{ paddingVertical: 8, fontSize: 12 }}>{t('tasks.handOutHint')}</Text>
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions style={{ flexWrap: 'wrap' }}>
            <Button disabled={Boolean(busyId)} onPress={() => setHandOut(null)}>
              {t('common.cancel')}
            </Button>
            <Button disabled={!handOutMember || Boolean(busyId)} onPress={() => handTo(false)}>
              {t('tasks.handOutAssign')}
            </Button>
            <Button
              disabled={!handOutMember || !validDay(handOutDay) || Boolean(busyId)}
              onPress={() => handTo(true)}
            >
              {t('tasks.handOutBook')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
      {returning && <ReturnTaskDialog key={returning.id} task={returning} onClose={() => setReturning(null)} onReturned={async () => {
        await load();
        setRevision((value) => value + 1);
      }} />}
    </View>
  );
}
