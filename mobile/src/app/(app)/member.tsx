import ReturnTaskDialog from '@/components/return-task-dialog';
import { useFocusEffect, router, useLocalSearchParams, useNavigation } from 'expo-router';
import { useCallback, useLayoutEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  IconButton,
  Text,
  useTheme,
} from 'react-native-paper';

import {
  approveExecution,
  listExecutionsOf,
  type TaskExecution,
} from '@/api/tasks';
import {
  TaskBadge,
  TaskButton as TaskAction,
  SectionHeading,
  taskStyles,
} from '@/components/tasks/task-ui';
import Celebration from '@/components/celebration';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import PointsCalendar from '@/components/points-calendar';
import { useScreenBackground } from '@/personalisation/use-screen-background';

const CASED: Record<string, string> = {
  new: 'statusNew',
  pending: 'statusPending',
  completed: 'statusCompleted',
  approved: 'statusApproved',
  rejected: 'statusRejected',
};

export default function MemberScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const navigation = useNavigation();
  const { id, name, from } = useLocalSearchParams<{
    id: string;
    name?: string;
    from?: string;
  }>();

  const [executions, setExecutions] = useState<TaskExecution[] | null>(null);
  const { own } = usePersonalisation();
  const [celebrating, setCelebrating] = useState(false);
  const endCelebration = useCallback(() => setCelebrating(false), []);
  const [revision, setRevision] = useState(0);
  const [refreshing, setRefreshing] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rejecting, setRejecting] = useState<TaskExecution | null>(null);

  useLayoutEffect(() => {
    navigation.setOptions({
      title: t('member.tasksOf', { name: name ?? '' }),
      headerLeft: () => (
        <IconButton
          icon="arrow-left"
          accessibilityLabel={t('member.backToTasks')}
          onPress={() => router.navigate(from === 'teams' ? '/teams' : '/')}
        />
      ),
    });
  }, [navigation, name, from, t]);

  const load = useCallback(async () => {
    try {
      setExecutions(await listExecutionsOf(id));
      setError(null);
    } catch {
      setExecutions([]);
      setError(t('common.error'));
    }
  }, [id, t]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const act = async (executionId: string, action: () => Promise<unknown>, cheer = false) => {
    setBusyId(executionId);

    try {
      await action();
      if (cheer && own?.celebrates) setCelebrating(true);
      setRevision((value) => value + 1);
      await load();
    } catch {
      setError(t('common.error'));
    } finally {
      setBusyId(null);
    }
  };

  if (executions === null) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  const waiting = executions.filter((execution) => execution.status === 'completed');
  const rest = executions.filter((execution) => execution.status !== 'completed');

  const card = (execution: TaskExecution, actions: React.ReactNode) => (
    <View
      key={execution.id}
      style={[taskStyles.card, { backgroundColor: theme.colors.elevation.level1 }]}
      testID={`member-task-${execution.name}`}
    >
      <Text style={taskStyles.name}>{execution.name}</Text>
      <View style={taskStyles.badges}>
        <TaskBadge tone="primary" icon="star-circle-outline">
          {t('user.points', { points: execution.points })}
        </TaskBadge>
        <TaskBadge>
          {CASED[execution.status] ? t(`tasks.${CASED[execution.status]}`) : execution.status}
        </TaskBadge>
      </View>
      {execution.rejectionReason ? (
        <Text style={{ color: theme.colors.error }}>
          {t('tasks.rejectedReason', { reason: execution.rejectionReason })}
        </Text>
      ) : null}
      {actions}
    </View>
  );

  return (
    <View testID="member-screen" style={[styles.screen, { backgroundColor: ground }]}>
      {celebrating ? (
        <Celebration withSound={own?.makesSound ?? false} onDone={endCelebration} />
      ) : null}
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}
      >
        {error ?? ''}
      </Banner>

      <ScrollView
        contentContainerStyle={styles.page}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />}
      >
        <PointsCalendar key={id} userId={id} canManage revision={revision} onChanged={load} />
        <SectionHeading title={t('tasks.approvalSection')} icon="thumb-up-outline" />
        {waiting.length ? (
          waiting.map((execution) =>
            card(
              execution,
              <View style={taskStyles.actions}>
                <TaskAction
                  mode="contained"
                  icon="check"
                  loading={busyId === execution.id}
                  disabled={busyId === execution.id}
                  onPress={() => void act(execution.id, () => approveExecution(execution.id), true)}
                >
                  {t('tasks.approve')}
                </TaskAction>
                <TaskAction
                  mode="outlined"
                  icon="close"
                  disabled={busyId === execution.id}
                  onPress={() => {
                    setRejecting(execution);
                  }}
                >
                  {t('member.reject')}
                </TaskAction>
              </View>,
            ),
          )
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('tasks.nothingToApprove')}
          </Text>
        )}

        <SectionHeading title={t('member.tasksSection')} icon="account-outline" />
        {rest.length ? (
          rest.map((execution) => card(execution, null))
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('member.noTasks')}
          </Text>
        )}
      </ScrollView>

      {rejecting && <ReturnTaskDialog key={rejecting.id} task={rejecting} onClose={() => setRejecting(null)} onReturned={async () => {
        await load();
        setRevision((value) => value + 1);
      }} />}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  centre: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
  },
  page: {
    gap: 12,
    padding: 16,
    paddingBottom: 32,
  },
  heading: {
    marginTop: 8,
  },
  card: {
    borderRadius: 16,
  },
  cardContent: {
    alignItems: 'flex-start',
    gap: 8,
  },
  dialog: {
    gap: 12,
  },
});
