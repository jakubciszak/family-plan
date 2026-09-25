import React from 'react';
import { useTranslation } from 'react-i18next';
import NotificationBubble from './NotificationBubble';
import { useNotifications } from '../hooks/useNotifications';
import '../styles/notifications.css';

const LIVE_MS = 8000;

const ICONS = {
    task_completed: 'approve',
    task_approved: 'checkCircle',
    task_rejected: 'undo',
    task_assigned: 'tasks',
    task_abandoned: 'tasks',
    task_corrected: 'schedule',
    task_removed: 'delete',
    calendar_changed: 'calendar',
    calendar_removed: 'calendar',
    payout_offered: 'wallet',
    streak_at_risk: 'streak',
};

export const iconOf = (notification) => ICONS[notification?.event ?? notification?.parameters?.event] ?? 'notifications';

/** Bubbles for news that arrives while the app is open, and one summary for what piled up before. */
function NotificationCenter() {
    const { t } = useTranslation();
    const notifications = useNotifications();

    if (!notifications || notifications.bubbles.length === 0) {
        return null;
    }

    return (
        <div className="notification-center" role="region" aria-label={t('notifications.region')} aria-live="polite">
            {notifications.bubbles.map((bubble) => bubble.kind === 'summary'
                ? (
                    <NotificationBubble
                        key={bubble.key}
                        icon="notifications"
                        title={t('notifications.backlog', { count: bubble.count })}
                        message={bubble.latest?.subject || bubble.latest?.message}
                        meta={t('notifications.backlogHint')}
                        closeLabel={t('notifications.dismissAll')}
                        onOpen={notifications.openInbox}
                        onDismiss={() => notifications.dismiss(bubble)}
                    >
                        <button type="button" className="notification-bubble__action" onClick={notifications.openInbox}>
                            {t('notifications.show')}
                        </button>
                    </NotificationBubble>
                )
                : (
                    <NotificationBubble
                        key={bubble.key}
                        icon={iconOf(bubble.notification)}
                        title={bubble.notification.subject}
                        message={bubble.notification.message}
                        autoHideMs={LIVE_MS}
                        closeLabel={t('notifications.dismiss')}
                        onOpen={() => notifications.open(bubble.notification)}
                        onDismiss={() => notifications.dismiss(bubble)}
                    />
                ))}
        </div>
    );
}

export default NotificationCenter;
