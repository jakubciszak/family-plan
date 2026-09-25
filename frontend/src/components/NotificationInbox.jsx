import React from 'react';
import { useTranslation } from 'react-i18next';
import { Icon, IconButton, CircularProgress } from './md3';
import notificationService from '../services/notificationService';
import { useNotifications } from '../hooks/useNotifications';
import { iconOf } from './NotificationCenter';

const STEPS = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.35],
    ['month', 12],
    ['year', Infinity],
];

const ago = (value, locale) => {
    let amount = (Date.parse(value) - Date.now()) / 1000;
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, size] of STEPS) {
        if (Math.abs(amount) < size) {
            return format.format(Math.round(amount), unit);
        }
        amount /= size;
    }

    return '';
};

const stateOf = (notification) => {
    if (notification.resolvedAt) {
        return 'handled';
    }
    if (notification.expiresAt && Date.parse(notification.expiresAt) <= Date.now()) {
        return 'outdated';
    }
    return notification.readAt ? 'read' : 'unread';
};

/** Everything the app told the user lately, with what is still news marked. */
function NotificationInbox({ onOpenSettings }) {
    const { t, i18n } = useTranslation();
    const notifications = useNotifications();
    const [items, setItems] = React.useState(null);
    const [failed, setFailed] = React.useState(false);
    const heading = React.useRef(null);
    const open = notifications?.inboxOpen;
    const close = notifications?.closeInbox;

    React.useEffect(() => {
        if (!open) {
            setItems(null);
            return undefined;
        }

        let cancelled = false;
        setFailed(false);
        notificationService.getRecent(50)
            .then((data) => !cancelled && setItems(data.notifications || []))
            .catch(() => !cancelled && setFailed(true));

        return () => {
            cancelled = true;
        };
    }, [open, notifications?.revision]);

    React.useEffect(() => {
        if (!open) {
            return undefined;
        }

        heading.current?.focus();
        const onKeyDown = (event) => event.key === 'Escape' && close();
        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, close]);

    if (!open) {
        return null;
    }

    const unread = (items || []).filter((item) => stateOf(item) === 'unread').length;

    return (
        <div className="notification-inbox-scrim" onMouseDown={(event) => event.target === event.currentTarget && close()}>
            <section className="notification-inbox" role="dialog" aria-modal="true" aria-labelledby="notification-inbox-title">
                <header className="notification-inbox__header">
                    <h2 id="notification-inbox-title" className="md-title-large" tabIndex={-1} ref={heading}>{t('notifications.title')}</h2>
                    <IconButton icon="doneAll" label={t('notifications.markAllRead')} disabled={unread === 0} onClick={() => notifications.markAllRead()} />
                    <IconButton icon="close" label={t('common.close')} onClick={close} />
                </header>

                <div className="notification-inbox__list">
                    {items === null && !failed && <CircularProgress label={t('common.loading')} />}
                    {failed && <p className="notification-inbox__empty" role="alert">{t('notifications.loadError')}</p>}
                    {items?.length === 0 && (
                        <p className="notification-inbox__empty">
                            <Icon name="notificationsOff" size={32} />
                            {t('notifications.empty')}
                        </p>
                    )}
                    {items?.length > 0 && (
                        <ul>
                            {items.map((item) => {
                                const state = stateOf(item);

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            className={`notification-inbox__item is-${state}`}
                                            onClick={() => {
                                                close();
                                                notifications.open(item);
                                            }}
                                        >
                                            <span className="notification-inbox__icon" aria-hidden="true"><Icon name={iconOf(item)} size={20} /></span>
                                            <span className="notification-inbox__text">
                                                {item.subject && <span className="notification-inbox__subject">{item.subject}</span>}
                                                <span className="notification-inbox__message">{item.message}</span>
                                                <span className="notification-inbox__meta">
                                                    <time dateTime={item.createdAt}>{ago(item.createdAt, i18n.language)}</time>
                                                    {state === 'handled' && <span className="notification-inbox__state">{t('notifications.handled')}</span>}
                                                    {state === 'outdated' && <span className="notification-inbox__state">{t('notifications.outdated')}</span>}
                                                </span>
                                            </span>
                                            {state === 'unread' && <span className="notification-inbox__dot" aria-label={t('notifications.unread')} role="img" />}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                <footer className="notification-inbox__footer">
                    <button
                        type="button"
                        className="notification-inbox__settings"
                        onClick={() => {
                            close();
                            onOpenSettings?.();
                        }}
                    >
                        <Icon name="settings" size={18} />
                        {t('notifications.settings')}
                    </button>
                </footer>
            </section>
        </div>
    );
}

export default NotificationInbox;
