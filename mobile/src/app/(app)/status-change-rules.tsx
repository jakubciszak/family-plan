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
  activateStatusChangeRule,
  CONDITION_TYPES,
  conditionKey,
  createStatusChangeRule,
  deactivateStatusChangeRule,
  listStatusChangeRules,
  updateStatusChangeRule,
  type ConditionType,
  type StatusChangeRule,
} from '@/api/status-change-rules';
import { listTaskTemplates, type TaskTemplate } from '@/api/tasks';
import { useAuth } from '@/auth/auth-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Draft = {
  id: string | null;
  taskTemplateId: string;
  name: string;
  description: string;
  conditionType: ConditionType;
  cooldownDays: string;
  requiredTaskTemplateId: string;
};

const blank = (taskTemplateId: string): Draft => ({
  id: null,
  taskTemplateId,
  name: '',
  description: '',
  conditionType: 'last_execution_cooldown',
  cooldownDays: '2',
  requiredTaskTemplateId: '',
});

const draftOf = (rule: StatusChangeRule): Draft => ({
  id: rule.id,
  taskTemplateId: rule.taskTemplateId,
  name: rule.name,
  description: rule.description,
  conditionType: rule.conditionType,
  cooldownDays: String(rule.config.cooldownDays ?? 2),
  requiredTaskTemplateId: rule.config.requiredTaskTemplateId ?? '',
});

export default function StatusChangeRulesScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { isSuperAdmin } = useAuth();

  const [rules, setRules] = useState<StatusChangeRule[]>([]);
  const [templates, setTemplates] = useState<TaskTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);

  const load = useCallback(async () => {
    try {
      const [theirs, pool] = await Promise.all([listStatusChangeRules(), listTaskTemplates()]);
      setRules(theirs);
      setTemplates(pool);
      setError(null);
    } catch {
      setError(t('errors.generic'));
    }
  }, [t]);

  useFocusEffect(useCallback(() => {
    if (!isSuperAdmin) {
      setLoading(false);
      return;
    }

    void load().finally(() => setLoading(false));
  }, [load, isSuperAdmin]));

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

  const nameOfTemplate = (id: string | null | undefined): string =>
    templates.find((template) => template.id === id)?.name ?? '—';

  const summarise = (rule: StatusChangeRule): string => {
    if (rule.conditionType === 'other_task_completed_today' && rule.config.requiredTaskTemplateId) {
      return t('statusChangeRules.descriptions.otherTaskCompletedToday', {
        taskName: nameOfTemplate(rule.config.requiredTaskTemplateId),
      });
    }

    if (rule.conditionType === 'last_execution_cooldown' && rule.config.cooldownDays) {
      return t('statusChangeRules.descriptions.lastExecutionCooldown', {
        days: rule.config.cooldownDays,
      });
    }

    return t(`statusChangeRules.conditionTypes.${conditionKey(rule.conditionType)}`);
  };

  const keep = () => {
    if (!draft) {
      return;
    }

    const { id, taskTemplateId, conditionType } = draft;
    const name = draft.name.trim();
    const description = draft.description.trim();

    const conditionConfig =
      conditionType === 'last_execution_cooldown'
        ? { cooldownDays: Number(draft.cooldownDays) || 1 }
        : { requiredTaskTemplateId: draft.requiredTaskTemplateId };

    setDraft(null);

    attempt(() =>
      id
        ? updateStatusChangeRule(id, { name, description, conditionType, conditionConfig })
        : createStatusChangeRule({
            taskTemplateId,
            name,
            description,
            conditionType,
            conditionConfig,
          })
    );
  };

  if (!isSuperAdmin) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Text variant="titleMedium">{t('statusChangeRules.accessDeniedTitle')}</Text>
        <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
          {t('statusChangeRules.accessDeniedBody')}
        </Text>
      </View>
    );
  }

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  const canAdd = templates.length > 0;
  const ready =
    draft &&
    draft.name.trim() &&
    draft.description.trim() &&
    (draft.conditionType === 'last_execution_cooldown' || draft.requiredTaskTemplateId);

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
            <Card
              key={rule.id}
              mode="elevated"
              style={styles.card}
              testID={`status-rule-${rule.name}`}>
              <Card.Title
                title={rule.name}
                titleVariant="titleMedium"
                titleNumberOfLines={2}
                subtitle={`${t('statusChangeRules.appliesTo')}: ${nameOfTemplate(rule.taskTemplateId)}`}
              />
              <Card.Content style={styles.section}>
                <Text variant="bodyMedium">{rule.description}</Text>
                <View style={styles.chips}>
                  <Chip compact icon="gavel">
                    {t(`statusChangeRules.conditionTypes.${conditionKey(rule.conditionType)}`)}
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
                      rule.isActive
                        ? deactivateStatusChangeRule(rule.id)
                        : activateStatusChangeRule(rule.id)
                    )
                  }>
                  {t(rule.isActive ? 'common.deactivate' : 'common.activate')}
                </Button>
              </Card.Actions>
            </Card>
          ))
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('statusChangeRules.noRules')}
          </Text>
        )}

        <Button
          mode="contained"
          icon="plus"
          disabled={!canAdd}
          onPress={() => setDraft(blank(templates[0].id))}>
          {t('statusChangeRules.create')}
        </Button>

        {canAdd ? null : (
          <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('taskTypes.none')}
          </Text>
        )}
      </ScrollView>

      <Portal>
        <Dialog visible={Boolean(draft)} onDismiss={() => setDraft(null)}>
          <Dialog.Title>
            {t(draft?.id ? 'statusChangeRules.edit' : 'statusChangeRules.create')}
          </Dialog.Title>
          <Dialog.ScrollArea>
            <ScrollView contentContainerStyle={styles.form}>
              <TextInput
                mode="outlined"
                label={t('statusChangeRules.name')}
                accessibilityLabel={t('statusChangeRules.name')}
                placeholder={t('statusChangeRules.namePlaceholder')}
                value={draft?.name ?? ''}
                onChangeText={(name) => setDraft((held) => held && { ...held, name })}
              />
              <TextInput
                mode="outlined"
                label={t('statusChangeRules.description')}
                accessibilityLabel={t('statusChangeRules.description')}
                placeholder={t('statusChangeRules.descriptionPlaceholder')}
                value={draft?.description ?? ''}
                onChangeText={(description) => setDraft((held) => held && { ...held, description })}
                multiline
              />

              {draft?.id ? null : (
                <>
                  <Text variant="labelLarge">{t('statusChangeRules.taskTemplate')}</Text>
                  <View style={styles.chips}>
                    {templates.map((template) => (
                      <Chip
                        key={template.id}
                        selected={draft?.taskTemplateId === template.id}
                        showSelectedCheck
                        onPress={() =>
                          setDraft((held) => held && { ...held, taskTemplateId: template.id })
                        }>
                        {template.name}
                      </Chip>
                    ))}
                  </View>
                </>
              )}

              <Text variant="labelLarge">{t('statusChangeRules.conditionType')}</Text>
              <View style={styles.chips}>
                {CONDITION_TYPES.map((type) => (
                  <Chip
                    key={type}
                    selected={draft?.conditionType === type}
                    showSelectedCheck
                    onPress={() => setDraft((held) => held && { ...held, conditionType: type })}>
                    {t(`statusChangeRules.conditionTypes.${conditionKey(type)}`)}
                  </Chip>
                ))}
              </View>

              {draft?.conditionType === 'last_execution_cooldown' ? (
                <>
                  <TextInput
                    mode="outlined"
                    label={t('statusChangeRules.cooldownDays')}
                    accessibilityLabel={t('statusChangeRules.cooldownDays')}
                    value={draft.cooldownDays}
                    onChangeText={(value) =>
                      setDraft((held) => held && { ...held, cooldownDays: value.replace(/\D/g, '') })
                    }
                    keyboardType="number-pad"
                  />
                  <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                    {t('statusChangeRules.cooldownDaysHint')}
                  </Text>
                </>
              ) : (
                <>
                  <Text variant="labelLarge">{t('statusChangeRules.requiredTask')}</Text>
                  <View style={styles.chips}>
                    {templates
                      .filter((template) => template.id !== draft?.taskTemplateId)
                      .map((template) => (
                        <Chip
                          key={template.id}
                          selected={draft?.requiredTaskTemplateId === template.id}
                          showSelectedCheck
                          onPress={() =>
                            setDraft(
                              (held) => held && { ...held, requiredTaskTemplateId: template.id }
                            )
                          }>
                          {template.name}
                        </Chip>
                      ))}
                  </View>
                  <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                    {t('statusChangeRules.requiredTaskHint')}
                  </Text>
                </>
              )}
            </ScrollView>
          </Dialog.ScrollArea>
          <Dialog.Actions>
            <Button onPress={() => setDraft(null)}>{t('common.cancel')}</Button>
            <Button disabled={busy || !ready} onPress={keep}>
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
