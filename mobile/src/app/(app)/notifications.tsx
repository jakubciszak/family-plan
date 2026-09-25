import { useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { ActivityIndicator, Button, Text, TouchableRipple, useTheme } from 'react-native-paper';

import { listRecent, type Notification } from '@/api/notifications';
import { iconOf } from '@/components/notification-centre';
import Icon from '@/components/tasks/task-icon';
import { useNotifications } from '@/notifications/notifications-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type State = 'unread' | 'read' | 'handled' | 'outdated';

const stateOf = (notification: Notification): State => {
  if (notification.resolvedAt) return 'handled';
  if (notification.expiresAt && Date.parse(notification.expiresAt) <= Date.now()) return 'outdated';
  return notification.readAt ? 'read' : 'unread';
};

const STEPS: [Intl.RelativeTimeFormatUnit, number][] = [['second', 60], ['minute', 60], ['hour', 24], ['day', 7], ['week', 4.35], ['month', 12], ['year', Infinity]];

const ago = (value: string, locale: string) => {
  let amount = (Date.parse(value) - Date.now()) / 1000;
  const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  for (const [unit, size] of STEPS) {
    if (Math.abs(amount) < size) return format.format(Math.round(amount), unit);
    amount /= size;
  }
  return '';
};

/** Everything the app told the user lately, with what is still news marked. */
export default function NotificationsScreen() {
  const { t, i18n } = useTranslation();
  const { colors } = useTheme();
  const ground = useScreenBackground();
  const router = useRouter();
  const notifications = useNotifications();
  const [items, setItems] = useState<Notification[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const revision = notifications?.revision ?? 0;

  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let current = true;
    listRecent(50)
      .then((recent) => {
        if (!current) return;
        setItems(recent);
        setFailed(false);
      })
      .catch(() => current && setFailed(true))
      .finally(() => current && setRefreshing(false));
    return () => {
      current = false;
    };
  }, [revision, attempt]);

  const load = useCallback(() => setAttempt((value) => value + 1), []);

  const unread = (items ?? []).filter((item) => stateOf(item) === 'unread').length;

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <View style={styles.toolbar}>
        <Button icon="check-all" disabled={unread === 0} onPress={() => void notifications?.markAllRead()}>
          {t('notifications.markAllRead')}
        </Button>
        <Button icon="cog-outline" onPress={() => router.navigate('/settings')}>
          {t('notifications.settings')}
        </Button>
      </View>

      {items === null && !failed ? <ActivityIndicator style={styles.loading} /> : null}
      {failed ? (
        <View style={styles.empty}>
          <Text>{t('notifications.loadError')}</Text>
          <Button onPress={load}>{t('common.retry')}</Button>
        </View>
      ) : null}

      {items !== null ? (
        <FlatList
          data={items}
          keyExtractor={(item) => item.id}
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Icon source="bell-off-outline" size={32} color={colors.onSurfaceVariant} />
              <Text style={{ color: colors.onSurfaceVariant }}>{t('notifications.empty')}</Text>
            </View>
          }
          renderItem={({ item }) => {
            const state = stateOf(item);
            const quiet = state === 'handled' || state === 'outdated';

            return (
              <TouchableRipple
                testID="notification-item"
                accessibilityRole="button"
                accessibilityState={{ selected: state === 'unread' }}
                onPress={() => notifications?.open(item)}
                style={[styles.item, state === 'unread' && { backgroundColor: colors.secondaryContainer }]}
              >
                <View style={styles.row}>
                  <View style={[styles.avatar, { backgroundColor: colors.surfaceVariant }]}>
                    <Icon source={iconOf(item)} size={20} color={colors.onSurfaceVariant} />
                  </View>
                  <View style={styles.text}>
                    {item.subject ? <Text variant="titleSmall" style={{ color: quiet ? colors.onSurfaceVariant : colors.onSurface }}>{item.subject}</Text> : null}
                    <Text variant="bodyMedium" style={{ color: quiet ? colors.onSurfaceVariant : colors.onSurface }}>{item.message}</Text>
                    <View style={styles.meta}>
                      <Text variant="bodySmall" style={{ color: colors.onSurfaceVariant }}>{ago(item.createdAt, i18n.language)}</Text>
                      {quiet ? (
                        <Text variant="labelSmall" style={[styles.state, { backgroundColor: colors.surfaceVariant, color: colors.onSurfaceVariant }]}>
                          {t(state === 'handled' ? 'notifications.handled' : 'notifications.outdated')}
                        </Text>
                      ) : null}
                    </View>
                  </View>
                  {state === 'unread' ? (
                    <View accessibilityLabel={t('notifications.unread')} accessibilityRole="image" style={[styles.dot, { backgroundColor: colors.primary }]} />
                  ) : null}
                </View>
              </TouchableRipple>
            );
          }}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  toolbar: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    paddingHorizontal: 8,
  },
  loading: {
    marginTop: 32,
  },
  list: {
    gap: 4,
    padding: 8,
    paddingBottom: 120,
  },
  item: {
    borderRadius: 12,
    padding: 12,
  },
  row: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 12,
  },
  avatar: {
    alignItems: 'center',
    borderRadius: 18,
    height: 36,
    justifyContent: 'center',
    width: 36,
  },
  text: {
    flex: 1,
    gap: 2,
    minWidth: 0,
  },
  meta: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  state: {
    borderRadius: 4,
    overflow: 'hidden',
    paddingHorizontal: 6,
  },
  dot: {
    borderRadius: 5,
    height: 10,
    marginTop: 6,
    width: 10,
  },
  empty: {
    alignItems: 'center',
    gap: 8,
    padding: 32,
  },
});
