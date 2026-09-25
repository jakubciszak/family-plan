import React from 'react';
import notificationService, { pageOf } from '../services/notificationService';

const POLL_MS = 10000;
const FETCH_LIMIT = 50;
/** After this long in the background, whatever arrived meanwhile is a backlog, not live news. */
const AWAY_MS = 2 * 60 * 1000;
const MAX_LIVE = 3;

const NotificationsContext = React.createContext(null);

const liveBubble = (notification) => ({ key: notification.id, kind: 'live', notification });

/**
 * Drops what stopped being news while it was on screen: read on another device, handled by somebody else
 * or out of date. `unread` holds the newest active notifications up to `FETCH_LIMIT`, so one missing from it
 * is gone for sure only when the list is complete or the notification is newer than the last one listed.
 * `known` maps every notification seen so far to when it was created.
 */
const withoutStale = (bubbles, unread, total, known) => {
    const active = new Set(unread.map((notification) => notification.id));
    const complete = unread.length < FETCH_LIMIT || unread.length >= total;
    const oldest = unread.length > 0 ? unread[unread.length - 1].createdAt : '';
    const stale = (id) => !active.has(id) && (complete || (known.get(id) ?? '') > oldest);
    let changed = false;

    const next = bubbles.flatMap((bubble) => {
        if (bubble.kind === 'live') {
            if (!stale(bubble.notification.id)) {
                return [bubble];
            }
            changed = true;
            return [];
        }

        const ids = bubble.ids.filter((id) => !stale(id));
        if (ids.length === bubble.ids.length && bubble.count <= total) {
            return [bubble];
        }
        changed = true;
        if (ids.length === 0) {
            return [];
        }

        const count = Math.min(Math.max(ids.length, bubble.count - (bubble.ids.length - ids.length)), Math.max(total, ids.length));
        const latest = ids.includes(bubble.latest.id)
            ? bubble.latest
            : unread.find((notification) => ids.includes(notification.id)) ?? bubble.latest;

        return [{ ...bubble, ids, count, latest }];
    });

    return changed ? next : bubbles;
};

/**
 * The browser keeps showing a system notification until somebody closes it. Once the app knows it was
 * read, handled by someone else or expired, it closes it too.
 */
const closeSystemNotificationsExcept = (activeIds) => {
    if (!('serviceWorker' in navigator) || !navigator.serviceWorker.getRegistration) {
        return;
    }

    navigator.serviceWorker.getRegistration()
        .then((registration) => registration?.getNotifications?.())
        .then((shown) => (shown || []).forEach((notification) => {
            const id = notification.data?.notificationId;
            if (id && !activeIds.has(id)) {
                notification.close();
            }
        }))
        .catch(() => undefined);
};

/**
 * Keeps the unread count, the bubbles and the inbox in one place.
 *
 * A bubble pops up only for news that arrives while the app is in use. Whatever piled up while nobody
 * looked is shown once, as one summary bubble, instead of a queue of old notifications.
 */
export function NotificationsProvider({ onNavigate, children }) {
    const [unreadCount, setUnreadCount] = React.useState(0);
    const [bubbles, setBubbles] = React.useState([]);
    const [inboxOpen, setInboxOpen] = React.useState(false);
    const [revision, setRevision] = React.useState(0);
    const seen = React.useRef(new Map());
    const primed = React.useRef(false);
    const watermark = React.useRef(null);
    const hiddenAt = React.useRef(null);
    const navigate = React.useRef(onNavigate);
    navigate.current = onNavigate;

    const addBacklog = React.useCallback((notifications, total) => {
        if (notifications.length === 0) {
            return;
        }

        setBubbles((current) => {
            const summary = current.find((bubble) => bubble.kind === 'summary');
            const others = current.filter((bubble) => bubble.kind !== 'summary');
            const ids = [...new Set([...(summary?.ids || []), ...notifications.map((notification) => notification.id)])];

            if (ids.length === 1 && !summary) {
                return [...others, liveBubble(notifications[0])];
            }

            return [...others, {
                key: 'summary',
                kind: 'summary',
                ids,
                count: Math.max(ids.length, total ?? 0),
                latest: summary?.latest ?? notifications[0],
            }];
        });
    }, []);

    const poll = React.useCallback(() => {
        if (document.hidden) {
            return Promise.resolve();
        }

        return notificationService.getUnread(FETCH_LIMIT)
            .then((data) => {
                const unread = data.notifications || [];
                const total = data.unreadCount ?? unread.length;
                setUnreadCount(total);
                closeSystemNotificationsExcept(new Set(unread.map((notification) => notification.id)));
                setBubbles((current) => withoutStale(current, unread, total, seen.current));

                const fresh = unread.filter((notification) => !seen.current.has(notification.id));
                fresh.forEach((notification) => seen.current.set(notification.id, notification.createdAt));

                if (fresh.some((notification) => notification.parameters?.url === '/day-planning')) {
                    window.dispatchEvent(new Event('day-planning:changed'));
                }

                if (fresh.length === 0) {
                    primed.current = true;
                    return;
                }

                // Server time on both sides: whatever is not newer than what was already there is backlog.
                const newest = fresh.reduce((latest, notification) => (notification.createdAt > latest ? notification.createdAt : latest), watermark.current || '');
                const backlog = primed.current
                    ? fresh.filter((notification) => watermark.current && notification.createdAt <= watermark.current)
                    : fresh;
                const live = fresh.filter((notification) => !backlog.includes(notification));

                watermark.current = newest;

                if (!primed.current) {
                    primed.current = true;
                    addBacklog(backlog, data.unreadCount);
                    return;
                }

                addBacklog(backlog);

                if (live.length > 0) {
                    setBubbles((current) => {
                        const shownLive = current.filter((bubble) => bubble.kind === 'live').length;
                        const room = Math.max(0, MAX_LIVE - shownLive);
                        // Oldest of the new ones first, so they stack in the order they happened.
                        const arriving = [...live].reverse();
                        const next = [...current, ...arriving.slice(0, room).map(liveBubble)];
                        const overflow = arriving.slice(room);

                        if (overflow.length === 0) {
                            return next;
                        }

                        const summary = next.find((bubble) => bubble.kind === 'summary');
                        const ids = [...new Set([...(summary?.ids || []), ...overflow.map((notification) => notification.id)])];

                        return [...next.filter((bubble) => bubble.kind !== 'summary'), {
                            key: 'summary', kind: 'summary', ids, count: ids.length, latest: overflow[overflow.length - 1],
                        }];
                    });
                }
            })
            .catch(() => undefined);
    }, [addBacklog]);

    const refresh = React.useCallback(() => {
        setRevision((current) => current + 1);
        return poll();
    }, [poll]);

    const markRead = React.useCallback((ids) => {
        const list = [...new Set(ids)].filter(Boolean);

        if (list.length === 0) {
            return Promise.resolve();
        }

        setUnreadCount((count) => Math.max(0, count - list.length));
        const request = list.length === 1
            ? notificationService.markAsRead(list[0])
            : notificationService.markManyAsRead(list);

        return request
            .then((data) => {
                if (typeof data?.unreadCount === 'number') {
                    setUnreadCount(data.unreadCount);
                }
                setRevision((current) => current + 1);
            })
            .catch(() => undefined);
    }, []);

    const removeBubble = React.useCallback((key) => {
        setBubbles((current) => current.filter((bubble) => bubble.key !== key));
    }, []);

    /** Swiped away, closed or timed out: the recipient has seen it. */
    const dismiss = React.useCallback((bubble) => {
        removeBubble(bubble.key);
        markRead(bubble.kind === 'summary' ? bubble.ids : [bubble.notification.id]);
    }, [markRead, removeBubble]);

    const open = React.useCallback((notification) => {
        setBubbles((current) => current.filter((bubble) => bubble.key !== notification.id));
        if (!notification.readAt) {
            markRead([notification.id]);
        }
        const page = pageOf(notification.parameters?.url);
        if (page) {
            navigate.current?.(page);
        }
    }, [markRead]);

    const openInbox = React.useCallback(() => {
        setBubbles((current) => current.filter((bubble) => bubble.kind !== 'summary'));
        setInboxOpen(true);
    }, []);

    const markAllRead = React.useCallback(() => {
        setBubbles([]);
        setUnreadCount(0);
        return notificationService.markAllAsRead()
            .then(() => setRevision((current) => current + 1))
            .catch(() => undefined);
    }, []);

    React.useEffect(() => {
        poll();
        const timer = window.setInterval(poll, POLL_MS);

        const onVisibility = () => {
            if (document.hidden) {
                hiddenAt.current = Date.now();
                return;
            }

            if (hiddenAt.current && Date.now() - hiddenAt.current > AWAY_MS) {
                primed.current = false;
            }
            hiddenAt.current = null;
            poll();
        };

        const onMessage = (event) => {
            if (event.data?.type === 'notifications:arrived') {
                poll();
            }
            if (event.data?.type === 'notifications:open') {
                if (event.data.id) {
                    markRead([event.data.id]);
                }
                const page = pageOf(event.data.url);
                if (page) {
                    navigate.current?.(page);
                }
            }
        };

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('focus', onVisibility);
        navigator.serviceWorker?.addEventListener?.('message', onMessage);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('focus', onVisibility);
            navigator.serviceWorker?.removeEventListener?.('message', onMessage);
        };
    }, [poll, markRead]);

    // A system notification opened the app: it says which one, and where it leads.
    React.useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('notification');

        if (!id) {
            return;
        }

        markRead([id]);
        params.delete('notification');
        const query = params.toString();
        window.history.replaceState(null, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
        const page = pageOf(window.location.pathname);
        if (page) {
            navigate.current?.(page);
        }
    }, [markRead]);

    const value = React.useMemo(() => ({
        unreadCount,
        bubbles,
        revision,
        inboxOpen,
        dismiss,
        removeBubble,
        open,
        openInbox,
        closeInbox: () => setInboxOpen(false),
        markRead,
        markAllRead,
        refresh,
    }), [unreadCount, bubbles, revision, inboxOpen, dismiss, removeBubble, open, openInbox, markRead, markAllRead, refresh]);

    return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}

export function useNotifications() {
    return React.useContext(NotificationsContext);
}
