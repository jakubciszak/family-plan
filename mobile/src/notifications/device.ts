import type { Notification } from '@/api/notifications';

// The web build (and the e2e tests) has no system notifications and no Firebase.
export type PhonePush = 'on' | 'no-permission' | 'unavailable' | 'server-off';

export const requestNotificationPermission = async (fromSettings = false): Promise<boolean> => false;
export const registerForPush = async (): Promise<PhonePush> => 'unavailable';
export const unregisterFromPush = async (): Promise<void> => undefined;
export const subscribePushTokenChanges = () => () => undefined;
export const onPushInForeground = (listener: () => void) => () => undefined;
export const tidyTray = async (active: Notification[], complete = true): Promise<void> => undefined;
export const clearDeviceNotifications = async (): Promise<void> => undefined;
export const subscribeNotificationOpen = (userId: string, onOpen: (opened: { id?: string; url?: string }) => void) => () => undefined;
