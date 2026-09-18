import { listActionPlans, type ActionPlan } from '@/api/action-plans';
import ChoicePicker from '@/components/action-plans/choice-picker';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Button,
  Card,
  Chip,
  Dialog,
  Portal,
  SegmentedButtons,
  Text,
  TextInput,
  useTheme,
} from 'react-native-paper';

import {
  activateTaskType,
  countsWith,
  createTaskType,
  deactivateTaskType,
  deleteTaskType,
  FREQUENCIES,
  LIMIT_TYPES,
  updateTaskType,
  type Frequency,
  type LimitType,
} from '@/api/task-types';
import { listTaskTemplates, type TaskTemplate } from '@/api/tasks';
import { listTeams, type Team } from '@/api/teams';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Draft = {
  actionPlanId: string | null;
  id: string | null;
  teamId: string;
  name: string;
  description: string;
  points: string;
  frequency: Frequency;
  limitType: LimitType;
  limitCount: string;
};

const blank = (teamId: string): Draft => ({
  id: null,
  actionPlanId: null,
  teamId,
  name: '',
  description: '',
  points: '10',
  frequency: 'daily',
  limitType: 'unlimited',
  limitCount: '1',
});

const draftOf = (template: TaskTemplate): Draft => ({
  id: template.id,
  actionPlanId: template.actionPlanId ?? null,
  teamId: template.teamId,
  name: template.name,
  description: template.description ?? '',
  points: String(template.points),
  frequency: template.frequency as Frequency,
  limitType: (template.executionLimit?.type ?? 'unlimited') as LimitType,
  limitCount: String((template.executionLimit as { count?: number })?.count ?? 1),
});

export default function TaskTypesScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();

  const [plans, setPlans] = useState<ActionPlan[]>([]);
  const [plansUnavailable, setPlansUnavailable] = useState(false);
  const loadPlans = useCallback(async () => {
    try { setPlans(await listActionPlans()); setPlansUnavailable(false); } catch { setPlansUnavailable(true); }
  }, []);
  const [templates, setTemplates] = useState<TaskTemplate[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [draft, setDraft] = useState<Draft | null>(null);
  const [menuFor, setMenuFor] = useState<string | null>(null);
  const [dropping, setDropping] = useState<TaskTemplate | null>(null);

  const [teamFilter, setTeamFilter] = useState<string | null>(null);
  const admins = teams.filter((team) => team.role === 'admin');
  const selectedTeam = admins.find((team) => team.id === teamFilter) ?? admins[0];
  const visibleTemplates = templates.filter((template) => template.teamId === selectedTeam?.id);

  const load = useCallback(async () => {
    try {
      const [pool, mine] = await Promise.all([listTaskTemplates(), listTeams()]);
      setTemplates(
        pool.filter((template) =>
          mine.some((team) => team.id === template.teamId && team.role === 'admin'),
        ),
      );
      setTeams(mine);
      setError(null);
    } catch {
      setError(t('tasks.loadFailed'));
    }
  }, [t]);

  useFocusEffect(
    useCallback(() => {
      void loadPlans();
      void load().finally(() => setLoading(false));
    }, [load, loadPlans]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const attempt = async (action: () => Promise<unknown>) => {
    setBusy(true);

    try {
      await action();
      await load();
    } catch {
      setError(t('tasks.actionFailed'));
    } finally {
      setBusy(false);
    }
  };

  const keep = () => {
    if (!draft) {
      return;
    }

    const body = {
      actionPlanId: draft.actionPlanId,
      name: draft.name.trim(),
      description: draft.description.trim(),
      points: Number(draft.points) || 0,
      frequency: draft.frequency,
      executionLimit: countsWith(draft.limitType)
        ? { type: draft.limitType, count: Number(draft.limitCount) || 1 }
        : { type: draft.limitType },
    };

    const { id, teamId } = draft;
    setDraft(null);

    void attempt(() => (id ? updateTaskType(id, body) : createTaskType({ teamId, ...body })));
  };

  const limitLabel = (template: TaskTemplate): string => {
    const limit = template.executionLimit as { type?: string; count?: number };
    const count = limit?.count ?? 1;

    switch (limit?.type) {
      case 'once':
        return t('taskTypes.limitOnce');
      case 'per_day':
        return t('taskTypes.limitPerDay', { count });
      case 'per_week':
        return t('taskTypes.limitPerWeek', { count });
      case 'per_month':
        return t('taskTypes.limitPerMonth', { count });
      default:
        return t('taskTypes.limitUnlimited');
    }
  };

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  if (!admins.length) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Text variant="bodyLarge" style={{ color: theme.colors.onSurfaceVariant }}>
          {t('taskTypes.adminOnly')}
        </Text>
      </View>
    );
  }

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
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
        {admins.length > 1 ? (
          <View style={styles.chips}>
            {admins.map((team) => (
              <Chip
                key={team.id}
                selected={team.id === selectedTeam?.id}
                onPress={() => setTeamFilter(team.id)}
              >
                {team.name}
              </Chip>
            ))}
          </View>
        ) : null}
        {visibleTemplates.length ? (
          visibleTemplates.map((template) => (
            <Card
              key={template.id}
              mode="elevated"
              style={styles.card}
              testID={`task-type-${template.name}`}
            >
              <Card.Title
                title={template.name}
                titleVariant="titleMedium"
                titleNumberOfLines={2}
                subtitle={`${template.points} ${t('tasks.points').toLowerCase()}`}
              />
              <Card.Content style={styles.chips}>
                <Chip compact icon="repeat">
                  {t(
                    `tasks.frequency${template.frequency.charAt(0).toUpperCase()}${template.frequency.slice(1)}`,
                  )}
                </Chip>
                <Chip compact icon="counter">
                  {limitLabel(template)}
                </Chip>
                {!template.isActive ? (
                  <Chip compact icon="archive-outline">
                    {t('taskTypes.inactive')}
                  </Chip>
                ) : null}
              </Card.Content>
              {template.description ? (
                <Card.Content>
                  <Text style={{ marginTop: 12 }}>{template.description}</Text>
                </Card.Content>
              ) : null}
              {template.remaining != null ? (
                <Card.Content>
                  <Text>{t('taskTypes.remaining', { count: template.remaining })}</Text>
                </Card.Content>
              ) : null}
              <Card.Actions>
                <Button disabled={busy} onPress={() => setMenuFor(template.id)}>
                  {t('common.more')}
                </Button>
              </Card.Actions>
            </Card>
          ))
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('taskTypes.none')}
          </Text>
        )}

        <Button mode="contained" icon="plus" onPress={() => setDraft(blank(selectedTeam.id))}>
          {t('taskTypes.create')}
        </Button>
      </ScrollView>

      <Portal>
        <Dialog
          testID="task-type-actions"
          visible={Boolean(menuFor)}
          onDismiss={() => setMenuFor(null)}
        >
          <Dialog.Title>{templates.find((one) => one.id === menuFor)?.name}</Dialog.Title>
          <Dialog.Content>
            <Button
              accessibilityLabel={t('common.edit')}
              icon="pencil-outline"
              onPress={() => {
                const template = templates.find((one) => one.id === menuFor);
                setMenuFor(null);
                if (template) setDraft(draftOf(template));
              }}
            >
              {t('common.edit')}
            </Button>
            <Button
              accessibilityLabel={t(
                templates.find((one) => one.id === menuFor)?.isActive
                  ? 'taskTypes.deactivate'
                  : 'taskTypes.activate',
              )}
              icon="archive-outline"
              onPress={() => {
                const template = templates.find((one) => one.id === menuFor);
                setMenuFor(null);
                if (template)
                  void attempt(() =>
                    template.isActive
                      ? deactivateTaskType(template.id)
                      : activateTaskType(template.id),
                  );
              }}
            >
              {t(
                templates.find((one) => one.id === menuFor)?.isActive
                  ? 'taskTypes.deactivate'
                  : 'taskTypes.activate',
              )}
            </Button>
            <Button
              accessibilityLabel={t('taskTypes.delete')}
              icon="delete-outline"
              onPress={() => {
                setDropping(templates.find((one) => one.id === menuFor) ?? null);
                setMenuFor(null);
              }}
            >
              {t('taskTypes.delete')}
            </Button>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setMenuFor(null)}>{t('common.cancel')}</Button>
          </Dialog.Actions>
        </Dialog>
        <Dialog visible={Boolean(draft)} onDismiss={() => setDraft(null)}>
          <Dialog.Title>{t(draft?.id ? 'tasks.edit' : 'taskTypes.create')}</Dialog.Title>
          <Dialog.ScrollArea>
            <ScrollView contentContainerStyle={styles.dialog}>
              <TextInput
                mode="outlined"
                label={t('tasks.name')}
                accessibilityLabel={t('tasks.name')}
                value={draft?.name ?? ''}
                onChangeText={(name) => setDraft((held) => held && { ...held, name })}
              />
              <TextInput
                mode="outlined"
                label={t('tasks.description')}
                accessibilityLabel={t('tasks.description')}
                value={draft?.description ?? ''}
                onChangeText={(description) => setDraft((held) => held && { ...held, description })}
                multiline
              />
              <TextInput
                mode="outlined"
                label={t('tasks.points')}
                accessibilityLabel={t('tasks.points')}
                value={draft?.points ?? ''}
                onChangeText={(points) =>
                  setDraft((held) => held && { ...held, points: points.replace(/\D/g, '') })
                }
                keyboardType="number-pad"
              />

              <ChoicePicker label={t('actionPlans.taskTypePlan')} value={draft?.actionPlanId ?? ''} disabled={plansUnavailable}
                options={[{ value: '', label: t('actionPlans.noAttachedPlan') },
                  ...plans.filter((plan) => plan.teamId === draft?.teamId).map((plan) => ({ value: plan.id, label: plan.name })),
                  ...(draft?.actionPlanId && !plans.some((plan) => plan.id === draft.actionPlanId && plan.teamId === draft.teamId) ? [{ value: draft.actionPlanId, label: t('actionPlans.currentAttachedPlan') }] : [])]}
                onChange={(actionPlanId) => setDraft((held) => held && { ...held, actionPlanId: actionPlanId || null })} />
              <Text>{t(plansUnavailable ? 'actionPlans.loadError' : 'actionPlans.taskTypePlanHint')}</Text>
              {plansUnavailable && <Button onPress={() => void loadPlans()}>{t('actionPlans.retry')}</Button>}
              <Text variant="labelLarge">{t('tasks.frequency')}</Text>
              <SegmentedButtons
                value={draft?.frequency ?? 'daily'}
                onValueChange={(frequency) =>
                  setDraft((held) => held && { ...held, frequency: frequency as Frequency })
                }
                density="small"
                buttons={FREQUENCIES.map((frequency) => ({
                  value: frequency,
                  label: t(
                    `tasks.frequency${frequency.charAt(0).toUpperCase()}${frequency.slice(1)}`,
                  ),
                }))}
              />

              <Text variant="labelLarge">{t('taskTypes.limit')}</Text>
              <View style={styles.chips}>
                {LIMIT_TYPES.map((type) => (
                  <Chip
                    key={type}
                    selected={draft?.limitType === type}
                    showSelectedCheck
                    onPress={() => setDraft((held) => held && { ...held, limitType: type })}
                  >
                    {t(`taskTypes.limitOption.${type}`)}
                  </Chip>
                ))}
              </View>

              {draft && countsWith(draft.limitType) ? (
                <TextInput
                  mode="outlined"
                  label={t('taskTypes.limitCount')}
                  accessibilityLabel={t('taskTypes.limitCount')}
                  value={draft.limitCount}
                  onChangeText={(limitCount) =>
                    setDraft(
                      (held) =>
                        held && {
                          ...held,
                          limitCount: limitCount.replace(/\D/g, ''),
                        },
                    )
                  }
                  keyboardType="number-pad"
                />
              ) : null}

              {!draft?.id && admins.length > 1 ? (
                <>
                  <Text variant="labelLarge">{t('teams.myTeams')}</Text>
                  <View style={styles.chips}>
                    {admins.map((team) => (
                      <Chip
                        key={team.id}
                        selected={draft?.teamId === team.id}
                        showSelectedCheck
                        onPress={() => setDraft((held) => held && { ...held, teamId: team.id })}
                      >
                        {team.name}
                      </Chip>
                    ))}
                  </View>
                </>
              ) : null}
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions>
            <Button onPress={() => setDraft(null)}>{t('common.cancel')}</Button>
            <Button disabled={!draft?.name.trim() || busy} onPress={keep}>
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog visible={Boolean(dropping)} onDismiss={() => setDropping(null)}>
          <Dialog.Title>{t('taskTypes.delete')}</Dialog.Title>
          <Dialog.Content>
            <Text variant="bodyMedium">{dropping?.name}</Text>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setDropping(null)}>{t('common.cancel')}</Button>
            <Button
              textColor={theme.colors.error}
              onPress={() => {
                const doomed = dropping;
                setDropping(null);

                if (doomed) {
                  void attempt(() => deleteTaskType(doomed.id));
                }
              }}
            >
              {t('common.delete')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
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
    padding: 24,
  },
  page: {
    gap: 16,
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    borderRadius: 16,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  dialog: {
    gap: 12,
    paddingVertical: 12,
  },
});
