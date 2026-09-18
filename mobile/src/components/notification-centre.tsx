import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppState, StyleSheet } from 'react-native';
import { Portal, Snackbar, Text } from 'react-native-paper';

import { useAuth } from '@/auth/auth-context';
import { readNotificationChannels } from '@/api/user-settings';
import { clearDeviceNotifications, requestNotificationPermission, showDeviceNotification } from '@/notifications/device';

import { listUnread, markAsRead, type Notification } from '@/api/notifications';

const POLL_MS = 10000;
const SHOW_MS = 6000;
const AT_ONCE = 3;

export default function NotificationCentre() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const [queue, setQueue] = useState<Notification[]>([]);
  const alreadySeen = useRef(new Set<string>());
  const alreadyRead = useRef(new Set<string>());

  const dismiss = useCallback((id: string) => {
    if (alreadyRead.current.has(id)) {
      return;
    }

    alreadyRead.current.add(id);
    setQueue((waiting) => waiting.filter((notification) => notification.id !== id));
    void markAsRead(id).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!user) return;
    let cancelled = false;
    let pending = false;
    void requestNotificationPermission().catch(() => undefined);

    const poll = () => {
      if (AppState.currentState !== 'active' || pending) {
        return;
      }

      pending = true;
      Promise.all([listUnread(AT_ONCE), readNotificationChannels(user.id).catch(() => [])])
        .then(async ([unread, channels]) => {
          if (cancelled) {
            return;
          }

          const fresh = unread.filter(
            (notification) => !alreadySeen.current.has(notification.id)
          );

          if (!fresh.length) {
            return;
          }

          fresh.forEach((notification) => alreadySeen.current.add(notification.id));
          setQueue((waiting) => [...waiting, ...fresh]);
          if (channels.find((choice) => choice.name === 'push')?.enabled) {
            for (const notification of fresh) {
              if (cancelled) break;
              await showDeviceNotification(notification, user.id, () => !cancelled).catch(() => undefined);
            }
          }
        })
        .catch(() => undefined)
        .finally(() => { pending = false; });
    };

    poll();
    const timer = setInterval(poll, POLL_MS);
    const subscription = AppState.addEventListener('change', (state) => { if (state === 'active') poll(); });

    return () => {
      cancelled = true;
      clearInterval(timer);
      subscription.remove();
      void clearDeviceNotifications().catch(() => undefined);
    };
  }, [user]);

  const showing = queue[0];

  if (!showing) {
    return null;
  }

  return (
    <Portal>
      <Snackbar
        visible
        testID="notification-snackbar"
        duration={SHOW_MS}
        onDismiss={() => dismiss(showing.id)}
        action={{ label: t('notifications.dismiss'), onPress: () => dismiss(showing.id) }}>
        {showing.subject ? (
          <Text variant="bodyMedium">
            <Text variant="titleSmall" style={styles.subject}>
              {`${showing.subject}\n`}
            </Text>
            {showing.message}
          </Text>
        ) : (
          showing.message
        )}
      </Snackbar>
    </Portal>
  );
}

const styles = StyleSheet.create({
  subject: {
    fontWeight: '600',
  },
});
