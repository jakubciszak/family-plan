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
  Text,
  TextInput,
  useTheme,
} from 'react-native-paper';

import {
  activateBonusRule,
  BONUS_RULE_TYPES,
  createBonusRule,
  deactivateBonusRule,
  listBonusRules,
  listPointsAccounts,
  updateBonusRule,
  type BonusRule,
  type BonusRuleType,
} from '@/api/bonus-rules';
import { listTeams, type Team } from '@/api/teams';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Draft = {
  id: string | null;
  teamId: string;
  name: string;
  description: string;
  bonusPoints: string;
  ruleType: BonusRuleType;
  requiredDays: string;
  pointsPerDay: string;
  requiredCount: string;
  requiredPoints: string;
  accounts: string[];
};

const blank = (teamId: string): Draft => ({
  id: null,
  teamId,
  name: '',
  description: '',
  bonusPoints: '10',
  ruleType: 'consecutive_days',
  requiredDays: '5',
  pointsPerDay: '10',
  requiredCount: '20',
  requiredPoints: '100',
  accounts: ['tasks'],
});

const draftOf = (rule: BonusRule): Draft => ({
  id: rule.id,
  teamId: rule.teamId,
  name: rule.name,
  description: rule.description,
  bonusPoints: String(rule.bonusPoints),
  ruleType: rule.type,
  requiredDays: String(rule.config.requiredDays ?? 5),
  pointsPerDay: String(rule.config.pointsPerDay ?? 10),
  requiredCount: String(rule.config.requiredCount ?? 20),
  requiredPoints: String(rule.config.requiredPoints ?? 100),
  accounts: rule.config.accounts ?? ['tasks'],
});

export default function BonusRulesScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();

  const [rules, setRules] = useState<BonusRule[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [kinds, setKinds] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);

  const load = useCallback(async () => {
    try {
      const [theirs, mine, accounts] = await Promise.all([
        listBonusRules(),
        listTeams(),
        listPointsAccounts(),
      ]);
      setRules(theirs);
      setTeams(mine.filter((team) => team.role === 'admin'));
      setKinds(accounts);
      setError(null);
    } catch {
      setError(t('errors.generic'));
    }
  }, [t]);

  useFocusEffect(useCallback(() => {
    void load().finally(() => setLoading(false));
  }, [load]));

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const attempt = (action: () => Promise<unknown>) => {
    setBusy(true);

    void action()
      .then(() => load())
      .catch(() => setError(t('errors.generic')))
      .finally(() => setBusy(false));
  };

  const accountLabels = (accounts?: string[]) =>
    (accounts?.length ? accounts : [t('bonusRules.allAccounts')])
      .map((kind) => t(`bonusRules.accountNames.${kind}`, { defaultValue: kind }))
      .join(', ');

  const summarise = (rule: BonusRule): string => {
    if (rule.type === 'consecutive_days' && rule.config.requiredDays) {
      return t('bonusRules.consecutiveDaysSummary', {
        days: rule.config.requiredDays,
        points: rule.config.pointsPerDay || 1,
        accounts: accountLabels(rule.config.accounts),
      });
    }

    if (rule.type === 'monthly_task_count' && rule.config.requiredCount) {
      return t('bonusRules.monthlyCountSummary', { count: rule.config.requiredCount });
    }

    if (rule.type === 'weekly_points_sum' && rule.config.requiredPoints) {
      return t('bonusRules.weeklySumSummary', {
        points: rule.config.requiredPoints,
        accounts: accountLabels(rule.config.accounts),
      });
    }

    return t(`bonusRules.ruleTypes.${rule.type}`);
  };

  const keep = () => {
    if (!draft) {
      return;
    }

    const bonusPoints = Number(draft.bonusPoints) || 0;
    const { id, teamId, ruleType } = draft;
    const name = draft.name.trim();
    const description = draft.description.trim();

    const ruleConfig =
      ruleType === 'consecutive_days'
        ? {
            requiredDays: Number(draft.requiredDays) || 1,
            pointsPerDay: Number(draft.pointsPerDay) || 1,
            accounts: draft.accounts,
          }
        : ruleType === 'monthly_task_count'
          ? { requiredCount: Number(draft.requiredCount) || 1 }
          : { requiredPoints: Number(draft.requiredPoints) || 1, accounts: draft.accounts };

    setDraft(null);

    attempt(() =>
      id
        ? updateBonusRule(id, { name, description, bonusPoints, ruleType, ruleConfig })
        : createBonusRule({ teamId, name, description, bonusPoints, ruleType, ruleConfig })
    );
  };

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  if (!teams.length) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Text variant="titleMedium">{t('bonusRules.accessDeniedTitle')}</Text>
        <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
          {t('bonusRules.accessDeniedBody')}
        </Text>
      </View>
    );
  }

  const toggleAccount = (kind: string) =>
    setDraft(
      (held) =>
        held && {
          ...held,
          accounts: held.accounts.includes(kind)
            ? held.accounts.filter((one) => one !== kind)
            : [...held.accounts, kind],
        }
    );

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}>
        {error ?? ''}
      </Banner>

      <ScrollView
        contentContainerStyle={styles.page}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />}>
        {rules.length ? (
          rules.map((rule) => (
            <Card key={rule.id} mode="elevated" style={styles.card} testID={`bonus-rule-${rule.name}`}>
              <Card.Title
                title={rule.name}
                titleVariant="titleMedium"
                titleNumberOfLines={2}
                subtitle={`${rule.bonusPoints} ${t('tasks.points').toLowerCase()}`}
              />
              <Card.Content style={styles.section}>
                <Text variant="bodyMedium">{rule.description}</Text>
                <View style={styles.chips}>
                  <Chip compact icon="tag-outline">
                    {t(`bonusRules.ruleTypes.${rule.type}`)}
                  </Chip>
                  {!rule.isActive ? (
                    <Chip compact icon="archive-outline">
                      {t('common.inactive')}
                    </Chip>
                  ) : null}
                </View>
                <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                  {summarise(rule)}
                </Text>
              </Card.Content>
              <Card.Actions>
                <Button compact disabled={busy} onPress={() => setDraft(draftOf(rule))}>
                  {t('common.edit')}
                </Button>
                <Button
                  compact
                  disabled={busy}
                  onPress={() =>
                    attempt(() =>
                      rule.isActive ? deactivateBonusRule(rule.id) : activateBonusRule(rule.id)
                    )
                  }>
                  {t(rule.isActive ? 'common.deactivate' : 'common.activate')}
                </Button>
              </Card.Actions>
            </Card>
          ))
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('bonusRules.noRules')}
          </Text>
        )}

        <Button mode="contained" icon="plus" onPress={() => setDraft(blank(teams[0].id))}>
          {t('bonusRules.create')}
        </Button>
      </ScrollView>

      <Portal>
        <Dialog visible={Boolean(draft)} onDismiss={() => setDraft(null)}>
          <Dialog.Title>{t(draft?.id ? 'bonusRules.edit' : 'bonusRules.create')}</Dialog.Title>
          <Dialog.ScrollArea>
            <ScrollView contentContainerStyle={styles.form}>
              <TextInput
                mode="outlined"
                label={t('bonusRules.name')}
                accessibilityLabel={t('bonusRules.name')}
                placeholder={t('bonusRules.namePlaceholder')}
                value={draft?.name ?? ''}
                onChangeText={(name) => setDraft((held) => held && { ...held, name })}
              />
              <TextInput
                mode="outlined"
                label={t('tasks.description')}
                accessibilityLabel={t('tasks.description')}
                placeholder={t('bonusRules.descriptionPlaceholder')}
                value={draft?.description ?? ''}
                onChangeText={(description) => setDraft((held) => held && { ...held, description })}
                multiline
              />
              <TextInput
                mode="outlined"
                label={t('bonusRules.bonusPoints')}
                accessibilityLabel={t('bonusRules.bonusPoints')}
                value={draft?.bonusPoints ?? ''}
                onChangeText={(value) =>
                  setDraft((held) => held && { ...held, bonusPoints: value.replace(/\D/g, '') })
                }
                keyboardType="number-pad"
              />

              <Text variant="labelLarge">{t('bonusRules.ruleType')}</Text>
              <View style={styles.chips}>
                {BONUS_RULE_TYPES.map((type) => (
                  <Chip
                    key={type}
                    selected={draft?.ruleType === type}
                    showSelectedCheck
                    onPress={() => setDraft((held) => held && { ...held, ruleType: type })}>
                    {t(`bonusRules.ruleTypes.${type}`)}
                  </Chip>
                ))}
              </View>

              {draft?.ruleType === 'consecutive_days' ? (
                <>
                  <TextInput
                    mode="outlined"
                    label={t('bonusRules.requiredDays')}
                    accessibilityLabel={t('bonusRules.requiredDays')}
                    value={draft.requiredDays}
                    onChangeText={(value) =>
                      setDraft((held) => held && { ...held, requiredDays: value.replace(/\D/g, '') })
                    }
                    keyboardType="number-pad"
                  />
                  <TextInput
                    mode="outlined"
                    label={t('bonusRules.pointsPerDay')}
                    accessibilityLabel={t('bonusRules.pointsPerDay')}
                    value={draft.pointsPerDay}
                    onChangeText={(value) =>
                      setDraft((held) => held && { ...held, pointsPerDay: value.replace(/\D/g, '') })
                    }
                    keyboardType="number-pad"
                  />
                </>
              ) : null}

              {draft?.ruleType === 'monthly_task_count' ? (
                <TextInput
                  mode="outlined"
                  label={t('bonusRules.requiredCount')}
                  accessibilityLabel={t('bonusRules.requiredCount')}
                  value={draft.requiredCount}
                  onChangeText={(value) =>
                    setDraft((held) => held && { ...held, requiredCount: value.replace(/\D/g, '') })
                  }
                  keyboardType="number-pad"
                />
              ) : null}

              {draft?.ruleType === 'weekly_points_sum' ? (
                <TextInput
                  mode="outlined"
                  label={t('bonusRules.requiredPoints')}
                  accessibilityLabel={t('bonusRules.requiredPoints')}
                  value={draft.requiredPoints}
                  onChangeText={(value) =>
                    setDraft((held) => held && { ...held, requiredPoints: value.replace(/\D/g, '') })
                  }
                  keyboardType="number-pad"
                />
              ) : null}

              {draft && draft.ruleType !== 'monthly_task_count' ? (
                <>
                  <Text variant="labelLarge">{t('bonusRules.accounts')}</Text>
                  <View style={styles.chips}>
                    {kinds.map((kind) => (
                      <Chip
                        key={kind}
                        selected={draft.accounts.includes(kind)}
                        showSelectedCheck
                        onPress={() => toggleAccount(kind)}>
                        {t(`bonusRules.accountNames.${kind}`, { defaultValue: kind })}
                      </Chip>
                    ))}
                  </View>
                </>
              ) : null}

              {teams.length > 1 && !draft?.id ? (
                <>
                  <Text variant="labelLarge">{t('bonusRules.team')}</Text>
                  <View style={styles.chips}>
                    {teams.map((team) => (
                      <Chip
                        key={team.id}
                        selected={draft?.teamId === team.id}
                        showSelectedCheck
                        onPress={() => setDraft((held) => held && { ...held, teamId: team.id })}>
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
            <Button
              disabled={busy || !draft?.name.trim() || !draft?.description.trim()}
              onPress={keep}>
              {t('common.save')}
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
    gap: 8,
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
  section: {
    gap: 8,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  form: {
    gap: 12,
    paddingVertical: 12,
  },
});
