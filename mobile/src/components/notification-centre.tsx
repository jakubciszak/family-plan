import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Portal } from 'react-native-paper';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import type { Notification } from '@/api/notifications';
import NotificationBubble from '@/components/notification-bubble';
import { BAR_HEIGHT } from '@/navigation/app-tab-bar';
import { useNotifications } from '@/notifications/notifications-context';

const LIVE_MS = 8000;

const ICONS: Record<string, string> = {
  task_completed: 'thumb-up-outline',
  task_approved: 'check-circle-outline',
  task_rejected: 'undo',
  task_assigned: 'format-list-checks',
  task_abandoned: 'format-list-checks',
  task_corrected: 'clock-outline',
  task_removed: 'delete-outline',
  calendar_changed: 'calendar-blank-outline',
  calendar_removed: 'calendar-blank-outline',
  payout_offered: 'wallet-outline',
  streak_at_risk: 'fire',
};

export const iconOf = (notification?: Notification) =>
  ICONS[notification?.event ?? notification?.parameters?.event ?? ''] ?? 'bell-outline';

/** Bubbles for news that arrives while the app is open, and one summary for what piled up before. */
export default function NotificationCentre() {
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();
  const notifications = useNotifications();

  if (!notifications || notifications.bubbles.length === 0) return null;

  return (
    <Portal>
      <View pointerEvents="box-none" style={[styles.dock, { bottom: BAR_HEIGHT + insets.bottom + 8 }]}>
      <View pointerEvents="box-none" accessibilityLiveRegion="polite" style={styles.stack}>
        {notifications.bubbles.map((bubble) => bubble.kind === 'summary' ? (
          <NotificationBubble
            key={bubble.key}
            testID="notification-summary"
            icon="bell-outline"
            title={t('notifications.backlog', { count: bubble.count })}
            message={bubble.latest.subject || bubble.latest.message}
            meta={t('notifications.backlogHint')}
            closeLabel={t('notifications.dismissAll')}
            action={{ label: t('notifications.show'), onPress: notifications.openInbox }}
            onOpen={notifications.openInbox}
            onDismiss={() => notifications.dismiss(bubble)}
          />
        ) : (
          <NotificationBubble
            key={bubble.key}
            testID="notification-bubble"
            icon={iconOf(bubble.notification)}
            title={bubble.notification.subject}
            message={bubble.notification.message}
            autoHideMs={LIVE_MS}
            closeLabel={t('notifications.dismiss')}
            onOpen={() => notifications.open(bubble.notification)}
            onDismiss={() => notifications.dismiss(bubble)}
          />
        ))}
      </View>
      </View>
    </Portal>
  );
}

const styles = StyleSheet.create({
  dock: {
    alignItems: 'center',
    left: 12,
    position: 'absolute',
    right: 12,
  },
  stack: {
    flexDirection: 'column-reverse',
    gap: 8,
    maxWidth: 560,
    width: '100%',
  },
});
