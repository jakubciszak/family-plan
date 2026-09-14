import React from 'react';
import { useTranslation } from 'react-i18next';
import { Snackbar } from './md3';
import notificationService from '../services/notificationService';
import '../styles/notifications.css';

const POLL_INTERVAL_MS = 30000;
const DISPLAY_MS = 6000;
const MAX_VISIBLE = 3;

function NotificationCenter() {
    const { t } = useTranslation();
    const [visible, setVisible] = React.useState([]);
    const queued = React.useRef(new Set());

    const dismiss = React.useCallback((notificationId) => {
        setVisible((current) => current.filter((notification) => notification.id !== notificationId));
        notificationService.markAsRead(notificationId).catch(() => undefined);
    }, []);

    React.useEffect(() => {
        let cancelled = false;

        const poll = () => {
            if (document.hidden) {
                return;
            }

            notificationService.getUnread(MAX_VISIBLE)
                .then((data) => {
                    if (cancelled) {
                        return;
                    }

                    const fresh = (data.notifications || [])
                        .filter((notification) => !queued.current.has(notification.id));

                    if (fresh.length === 0) {
                        return;
                    }

                    fresh.forEach((notification) => queued.current.add(notification.id));
                    setVisible((current) => [...current, ...fresh].slice(-MAX_VISIBLE));
                })
                .catch(() => undefined);
        };

        poll();
        const timer = window.setInterval(poll, POLL_INTERVAL_MS);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, []);

    React.useEffect(() => {
        const oldest = visible[0];

        if (!oldest) {
            return undefined;
        }

        const timer = window.setTimeout(() => dismiss(oldest.id), DISPLAY_MS);

        return () => window.clearTimeout(timer);
    }, [visible, dismiss]);

    if (visible.length === 0) {
        return null;
    }

    return (
        <div className="notification-center">
            {visible.map((notification) => (
                <Snackbar
                    key={notification.id}
                    duration={0}
                    message={notification.subject
                        ? (
                            <>
                                <strong className="notification-center__subject">{notification.subject}</strong>
                                {notification.message}
                            </>
                        )
                        : notification.message}
                    actionLabel={t('notifications.dismiss')}
                    onAction={() => dismiss(notification.id)}
                    onDismiss={() => dismiss(notification.id)}
                />
            ))}
        </div>
    );
}

export default NotificationCenter;
