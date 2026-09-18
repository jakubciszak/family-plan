import type { Notification } from '@/api/notifications';

export const requestNotificationPermission = async (fromSettings = false): Promise<boolean> => false;
export const showDeviceNotification = async (notification: Notification, userId: string, isCurrent: () => boolean): Promise<void> => undefined;
export const clearDeviceNotifications = async (): Promise<void> => undefined;
