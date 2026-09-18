import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Button,
  Card,
  Divider,
  Icon,
  List,
  Snackbar,
  Switch,
  Text,
  useTheme,
} from 'react-native-paper';

import {
  readPolicies,
  savePolicy,
  type EventPolicy,
  type NotificationChannel,
} from '@/api/notification-policies';
import { useAuth } from '@/auth/auth-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

const CHANNEL_ICONS: Record<string, string> = {
  email: 'email-outline',
  sms: 'message-text-outline',
  in_app: 'bell-outline',
};

export default function NotificationEventsScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { isSuperAdmin } = useAuth();

  const [channels, setChannels] = useState<NotificationChannel[]>([]);
  const [events, setEvents] = useState<EventPolicy[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [said, setSaid] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const matrix = await readPolicies();
      setChannels(matrix.channels);
      setEvents(matrix.events);
      setError(null);
    } catch {
      setError(t('notificationEvents.loadError'));
    }
  }, [t]);

  useFocusEffect(useCallback(() => {
    if (!isSuperAdmin) {
      setLoading(false);
      return;
    }

    void load().finally(() => setLoading(false));
  }, [isSuperAdmin, load]));

  const toggle = (event: string, channel: NotificationChannel) => {
    setEvents((current) =>
      current.map((policy) => {
        if (policy.event !== event || !policy.configurable) {
          return policy;
        }

        const next = policy.channels.includes(channel)
          ? policy.channels.filter((held) => held !== channel)
          : [...policy.channels, channel];

        return { ...policy, channels: channels.filter((known) => next.includes(known)) };
      })
    );
  };

  const save = async () => {
    setSaving(true);
    setError(null);

    try {
      await Promise.all(
        events
          .filter((policy) => policy.configurable)
          .map((policy) => savePolicy(policy.event, policy.channels))
      );

      setSaid(t('notificationEvents.saveSuccess'));
    } catch {
      setError(t('notificationEvents.saveError'));
    } finally {
      setSaving(false);
    }
  };

  if (!isSuperAdmin) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Icon source="lock-outline" size={48} color={theme.colors.onSurfaceVariant} />
        <Text variant="titleMedium">{t('notificationEvents.accessDeniedTitle')}</Text>
        <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
          {t('notificationEvents.accessDeniedBody')}
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

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}>
        {error ?? ''}
      </Banner>

      <ScrollView contentContainerStyle={styles.page}>
        <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
          {t('notificationEvents.description')}
        </Text>

        {events.map((policy) => (
          <Card key={policy.event} mode="elevated" style={styles.card}>
            <Card.Title
              title={t(`notificationEvents.events.${policy.event}`)}
              titleVariant="titleMedium"
              titleNumberOfLines={2}
              subtitle={
                policy.configurable
                  ? t(`notificationEvents.hints.${policy.event}`)
                  : t('notificationEvents.transactional')
              }
              subtitleNumberOfLines={3}
            />
            <Divider />
            {channels.map((channel) => (
              <List.Item
                key={channel}
                onPress={policy.configurable ? () => toggle(policy.event, channel) : undefined}
                title={t(`notificationEvents.channels.${channel}`)}
                left={(props) => <List.Icon {...props} icon={CHANNEL_ICONS[channel] ?? 'bell-outline'} />}
                right={() => (
                  <View pointerEvents="none">
                    <Switch
                      value={policy.channels.includes(channel)}
                      disabled={!policy.configurable}
                    />
                  </View>
                )}
              />
            ))}
          </Card>
        ))}

        <Button mode="contained" icon="check" loading={saving} disabled={saving} onPress={() => void save()}>
          {saving ? t('notificationEvents.saving') : t('notificationEvents.save')}
        </Button>
      </ScrollView>

      <Snackbar visible={Boolean(said)} onDismiss={() => setSaid(null)} duration={3000}>
        {said ?? ''}
      </Snackbar>
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
});
