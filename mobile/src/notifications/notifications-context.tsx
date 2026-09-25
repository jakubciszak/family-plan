import { useRouter } from 'expo-router';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AppState } from 'react-native';

import { listUnread, markAllAsRead, markAsRead, markManyAsRead, routeOf, type Notification } from '@/api/notifications';
import { useAuth } from '@/auth/auth-context';
import { notifyCalendarChanged } from '@/day-planning/changes';
import {
  onPushInForeground,
  registerForPush,
  requestNotificationPermission,
  subscribeNotificationOpen,
  subscribePushTokenChanges,
  tidyTray,
  type PhonePush,
} from '@/notifications/device';

const POLL_MS = 10000;
const FETCH_LIMIT = 50;
/** After this long in the background, whatever arrived meanwhile is a backlog, not live news. */
const AWAY_MS = 2 * 60 * 1000;
const MAX_LIVE = 3;

export type Bubble =
  | { key: string; kind: 'live'; notification: Notification }
  | { key: 'summary'; kind: 'summary'; ids: string[]; count: number; latest: Notification };

type Notifications = {
  unreadCount: number;
  bubbles: Bubble[];
  revision: number;
  phonePush: PhonePush | null;
  dismiss: (bubble: Bubble) => void;
  open: (notification: Notification) => void;
  openInbox: () => void;
  markAllRead: () => Promise<void>;
  enablePhonePush: () => Promise<PhonePush>;
};

const NotificationsContext = createContext<Notifications | null>(null);

const live = (notification: Notification): Bubble => ({ key: notification.id, kind: 'live', notification });

/**
 * Drops what stopped being news while it was on screen: read on another device, handled by somebody else
 * or out of date. `unread` holds the newest active notifications up to `FETCH_LIMIT`, so one missing from it
 * is gone for sure only when the list is complete or the notification is newer than the last one listed.
 * `known` maps every notification seen so far to when it was created.
 */
const withoutStale = (bubbles: Bubble[], unread: Notification[], total: number, known: Map<string, string>): Bubble[] => {
  const active = new Set(unread.map((notification) => notification.id));
  const complete = unread.length < FETCH_LIMIT || unread.length >= total;
  const oldest = unread.length > 0 ? unread[unread.length - 1].createdAt : '';
  const stale = (id: string) => !active.has(id) && (complete || (known.get(id) ?? '') > oldest);
  let changed = false;

  const next = bubbles.flatMap((bubble): Bubble[] => {
    if (bubble.kind === 'live') {
      if (!stale(bubble.notification.id)) return [bubble];
      changed = true;
      return [];
    }

    const ids = bubble.ids.filter((id) => !stale(id));
    if (ids.length === bubble.ids.length && bubble.count <= total) return [bubble];
    changed = true;
    if (ids.length === 0) return [];

    const count = Math.min(Math.max(ids.length, bubble.count - (bubble.ids.length - ids.length)), Math.max(total, ids.length));
    const latest = ids.includes(bubble.latest.id)
      ? bubble.latest
      : unread.find((notification) => ids.includes(notification.id)) ?? bubble.latest;

    return [{ ...bubble, ids, count, latest }];
  });

  return changed ? next : bubbles;
};

const withSummary = (bubbles: Bubble[], notifications: Notification[], total = 0, alone = true): Bubble[] => {
  if (notifications.length === 0) return bubbles;
  const summary = bubbles.find((bubble) => bubble.kind === 'summary');
  const others = bubbles.filter((bubble) => bubble.kind !== 'summary');
  const ids = [...new Set([...(summary?.kind === 'summary' ? summary.ids : []), ...notifications.map((notification) => notification.id)])];

  // A lone notification from before is shown as it is.
  if (alone && ids.length === 1 && !summary) return [...others, live(notifications[0])];

  return [...others, {
    key: 'summary',
    kind: 'summary',
    ids,
    count: Math.max(ids.length, total),
    latest: summary?.kind === 'summary' ? summary.latest : notifications[0],
  }];
};

/**
 * One place for the unread count, the bubbles and the inbox.
 *
 * A bubble pops up only for news that arrives while the app is in use. What piled up while nobody looked
 * is shown once, as one summary, and phones get the rest through Firebase while the app is closed.
 */
export function NotificationsProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const router = useRouter();
  const [unreadCount, setUnreadCount] = useState(0);
  const [bubbles, setBubbles] = useState<Bubble[]>([]);
  const [revision, setRevision] = useState(0);
  const [phonePush, setPhonePush] = useState<PhonePush | null>(null);
  const seen = useRef(new Map<string, string>());
  const primed = useRef(false);
  const watermark = useRef<string | null>(null);
  const backgroundSince = useRef<number | null>(null);
  const pending = useRef(false);

  const go = useCallback((url?: string) => {
    const route = routeOf(url);
    if (route === '/day-planning') notifyCalendarChanged();
    if (route) router.navigate(route);
  }, [router]);

  const markRead = useCallback((ids: string[]) => {
    const list = [...new Set(ids)].filter(Boolean);
    if (list.length === 0) return;
    setUnreadCount((count) => Math.max(0, count - list.length));
    void (list.length === 1 ? markAsRead(list[0]) : markManyAsRead(list))
      .then((answer) => {
        if (typeof answer?.unreadCount === 'number') setUnreadCount(answer.unreadCount);
        setRevision((value) => value + 1);
      })
      .catch(() => undefined);
  }, []);

  const poll = useCallback(() => {
    if (!user || AppState.currentState !== 'active' || pending.current) return;
    pending.current = true;

    listUnread(FETCH_LIMIT)
      .then(({ notifications: unread, unreadCount: count }) => {
        setUnreadCount(count);
        void tidyTray(unread).catch(() => undefined);
        const known = seen.current;
        setBubbles((current) => withoutStale(current, unread, count, known));

        const fresh = unread.filter((notification) => !known.has(notification.id));
        fresh.forEach((notification) => known.set(notification.id, notification.createdAt));
        if (fresh.some((notification) => notification.parameters?.url === '/day-planning')) notifyCalendarChanged();

        if (fresh.length === 0) {
          primed.current = true;
          return;
        }

        // Server time on both sides: anything not newer than what was already there is backlog.
        const newest = fresh.reduce((latest, notification) => (notification.createdAt > latest ? notification.createdAt : latest), watermark.current ?? '');
        const backlog = primed.current ? fresh.filter((notification) => watermark.current !== null && notification.createdAt <= watermark.current) : fresh;
        const arriving = fresh.filter((notification) => !backlog.includes(notification)).reverse();
        const wasPrimed = primed.current;
        watermark.current = newest;
        primed.current = true;

        setBubbles((current) => {
          let next = withSummary(current, backlog, wasPrimed ? 0 : count);
          const room = Math.max(0, MAX_LIVE - next.filter((bubble) => bubble.kind === 'live').length);
          next = [...next, ...arriving.slice(0, room).map(live)];
          return withSummary(next, arriving.slice(room).reverse(), 0, false);
        });
      })
      .catch(() => undefined)
      .finally(() => {
        pending.current = false;
      });
  }, [user]);

  useEffect(() => {
    // The provider remounts for every signed-in user (the layout is keyed by the user id), so it starts clean.
    if (!user) return undefined;

    void requestNotificationPermission()
      .catch(() => false)
      .then(() => registerForPush())
      .then(setPhonePush)
      .catch(() => setPhonePush('unavailable'));

    poll();
    const timer = setInterval(poll, POLL_MS);
    const appState = AppState.addEventListener('change', (state) => {
      if (state !== 'active') {
        backgroundSince.current ??= Date.now();
        return;
      }
      if (backgroundSince.current !== null && Date.now() - backgroundSince.current > AWAY_MS) primed.current = false;
      backgroundSince.current = null;
      poll();
    });
    const tokenChanges = subscribePushTokenChanges();
    const foreground = onPushInForeground(() => setTimeout(poll, 300));

    return () => {
      clearInterval(timer);
      appState.remove();
      tokenChanges();
      foreground();
    };
  }, [user, poll]);

  useEffect(() => {
    if (!user) return undefined;
    return subscribeNotificationOpen(user.id, ({ id, url }) => {
      if (id) markRead([id]);
      go(url);
    });
  }, [user, go, markRead]);

  const value = useMemo<Notifications>(() => ({
    unreadCount,
    bubbles,
    revision,
    phonePush,
    dismiss: (bubble) => {
      setBubbles((current) => current.filter((shown) => shown.key !== bubble.key));
      markRead(bubble.kind === 'summary' ? bubble.ids : [bubble.notification.id]);
    },
    open: (notification) => {
      setBubbles((current) => current.filter((shown) => shown.key !== notification.id));
      if (!notification.readAt) markRead([notification.id]);
      go(notification.parameters?.url);
    },
    openInbox: () => {
      setBubbles((current) => current.filter((shown) => shown.kind !== 'summary'));
      router.navigate('/notifications');
    },
    markAllRead: async () => {
      setBubbles([]);
      setUnreadCount(0);
      await markAllAsRead().catch(() => undefined);
      setRevision((current) => current + 1);
    },
    enablePhonePush: async () => {
      await requestNotificationPermission(true);
      const state = await registerForPush().catch((): PhonePush => 'unavailable');
      setPhonePush(state);
      return state;
    },
  }), [unreadCount, bubbles, revision, phonePush, markRead, go, router]);

  return <NotificationsContext.Provider value={value}>{children}</NotificationsContext.Provider>;
}

export const useNotifications = () => useContext(NotificationsContext);
