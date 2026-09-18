import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Notifications from 'expo-notifications';
import { Linking, Platform } from 'react-native';

import type { Notification } from '@/api/notifications';

Notifications.setNotificationHandler({
  handleNotification: async () => ({ shouldPlaySound: true, shouldSetBadge: false, shouldShowBanner: true, shouldShowList: true }),
});

const channel = async () => {
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('tasks', { name: 'Family Plan', importance: Notifications.AndroidImportance.DEFAULT });
  }
};

export async function requestNotificationPermission(fromSettings = false) {
  await channel();
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

export async function showDeviceNotification(notification: Notification, userId: string, isCurrent: () => boolean) {
  if (!(await Notifications.getPermissionsAsync()).granted || !isCurrent()) return;
  const key = `notifications-shown:${userId}`;
  const shown: string[] = JSON.parse(await AsyncStorage.getItem(key) ?? '[]');
  if (shown.includes(notification.id) || !isCurrent()) return;
  await channel();
  if (!isCurrent()) return;
  await Notifications.scheduleNotificationAsync({
    identifier: notification.id,
    content: { title: notification.subject ?? 'Family Plan', body: notification.message, data: { notificationId: notification.id, userId }, sound: 'default' },
    trigger: Platform.OS === 'android' ? { channelId: 'tasks' } : null,
  });
  if (!isCurrent()) await Notifications.dismissNotificationAsync(notification.id);
  await AsyncStorage.setItem(key, JSON.stringify([...shown, notification.id].slice(-200)));
}

export const clearDeviceNotifications = () => Notifications.dismissAllNotificationsAsync();
