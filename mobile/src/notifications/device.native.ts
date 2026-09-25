import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Linking, Platform } from 'react-native';

import { pushAvailability, registerPhone, unregisterPhone, type Notification } from '@/api/notifications';
import { tagOf } from '@/notifications/tray';

/** Android channel the server sends to (FcmPushSender::ANDROID_CHANNEL). High importance, so it pops up. */
const CHANNEL = 'family-plan';
const TOKEN_KEY = 'push-device-token';
/** Tags the server gives its notifications; a presented notification without one of them is not ours to tidy. */
const OUR_TAGS = /^(task|payout|calendar|streak)-|^[0-9a-f]{8}-[0-9a-f]{4}-/i;

/** 'server-off': the phone is registered, but the server has no Firebase service account to send with yet. */
export type PhonePush = 'on' | 'no-permission' | 'unavailable' | 'server-off';

let inForeground: (() => void) | null = null;

Notifications.setNotificationHandler({
  // While the app is open a bubble shows the news; a system notification would only repeat it.
  handleNotification: async () => {
    inForeground?.();
    return { shouldPlaySound: false, shouldSetBadge: false, shouldShowBanner: false, shouldShowList: false };
  },
});

const prepareChannel = async () => {
  if (Platform.OS !== 'android') return;
  await Notifications.setNotificationChannelAsync(CHANNEL, {
    name: 'Family Plan',
    importance: Notifications.AndroidImportance.HIGH,
    sound: 'default',
    vibrationPattern: [0, 200, 120, 200],
  });
  // The first channel only had tray importance, and a channel's importance cannot be raised later.
  await Notifications.deleteNotificationChannelAsync('tasks').catch(() => undefined);
};

export async function requestNotificationPermission(fromSettings = false) {
  await prepareChannel();
  const permission = await Notifications.getPermissionsAsync();
  if (permission.granted) return true;
  if (!permission.canAskAgain) {
    if (fromSettings) await Linking.openSettings();
    return false;
  }
  if (!fromSettings && await AsyncStorage.getItem('notifications-permission-asked')) return false;
  await AsyncStorage.setItem('notifications-permission-asked', '1');
  return (await Notifications.requestPermissionsAsync()).granted;
}

/**
 * Hands the Firebase token of this phone to the server, so notifications reach it while the app is closed.
 * 'unavailable' means this build has no Firebase configuration (google-services.json).
 */
export async function registerForPush(): Promise<PhonePush> {
  if (Platform.OS !== 'android') return 'unavailable';
  if (!(await Notifications.getPermissionsAsync()).granted) return 'no-permission';
  await prepareChannel();

  let token: string;
  try {
    token = String((await Notifications.getDevicePushTokenAsync()).data);
  } catch {
    return 'unavailable';
  }

  await registerPhone(token, Device.modelName ?? null);
  await AsyncStorage.setItem(TOKEN_KEY, token);
  const server = await pushAvailability().catch(() => null);
  return server && !server.native ? 'server-off' : 'on';
}

/** After signing out the phone must stop receiving the notifications of that account. */
export async function unregisterFromPush(): Promise<void> {
  const token = await AsyncStorage.getItem(TOKEN_KEY);
  if (!token) return;
  await AsyncStorage.removeItem(TOKEN_KEY);
  await unregisterPhone(token).catch(() => undefined);
}

export const subscribePushTokenChanges = () => {
  const subscription = Notifications.addPushTokenListener(({ data }) => {
    const token = String(data);
    void registerPhone(token, Device.modelName ?? null)
      .then(() => AsyncStorage.setItem(TOKEN_KEY, token))
      .catch(() => undefined);
  });
  return () => subscription.remove();
};

/** A push that arrives while the app is open: the listener fetches it at once instead of at the next poll. */
export const onPushInForeground = (listener: () => void) => {
  inForeground = listener;
  return () => {
    if (inForeground === listener) inForeground = null;
  };
};

/**
 * The system keeps a notification in the tray until somebody swipes it away.
 * Once it was read, handled by someone else or expired, the app takes it away too.
 *
 * `active` holds the newest active notifications; when it is not `complete`, an entry older than the last of
 * them may simply not have fitted in the list, so it stays.
 */
export async function tidyTray(active: Notification[], complete = true): Promise<void> {
  const keep = new Set(active.flatMap((notification) => [notification.id, notification.topic ?? notification.parameters?.tag].filter(Boolean) as string[]));
  const since = complete || active.length === 0 ? -Infinity : Math.min(...active.map((notification) => Date.parse(notification.createdAt)));
  const shown = await Notifications.getPresentedNotificationsAsync();

  await Promise.all(shown.map(async ({ date, request }) => {
    const data = (request.content.data ?? {}) as Record<string, unknown>;
    const id = typeof data.notificationId === 'string' ? data.notificationId : null;
    const tag = tagOf(request.identifier, data);

    if (date < since || (id ? keep.has(id) : !OUR_TAGS.test(tag) || keep.has(tag))) return;
    await Notifications.dismissNotificationAsync(request.identifier).catch(() => undefined);
  }));
}

export const clearDeviceNotifications = () => Notifications.dismissAllNotificationsAsync();

type Opened = { id?: string; url?: string };

/** Tapping a notification, also one that started the app, opens what it is about. */
export const subscribeNotificationOpen = (userId: string, onOpen: (opened: Opened) => void) => {
  let active = true;
  const handle = (response: Notifications.NotificationResponse | null) => {
    if (!active || !response) return;
    const { request } = response.notification;
    const data = (request.content.data ?? {}) as Record<string, unknown>;
    const remote = (request.trigger as { remoteMessage?: { data?: Record<string, string> } } | null)?.remoteMessage?.data ?? {};
    if (typeof data.userId === 'string' && data.userId !== userId) return;
    const pick = (key: string) => (typeof data[key] === 'string' ? data[key] as string : remote[key]) || undefined;
    onOpen({ id: pick('notificationId'), url: pick('url') });
    void Notifications.clearLastNotificationResponseAsync().catch(() => undefined);
  };
  void Notifications.getLastNotificationResponseAsync().then(handle).catch(() => undefined);
  const subscription = Notifications.addNotificationResponseReceivedListener(handle);
  return () => {
    active = false;
    subscription.remove();
  };
};
