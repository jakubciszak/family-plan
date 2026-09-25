import apiClient from './client';

export type Notification = {
  id: string;
  subject: string | null;
  message: string;
  createdAt: string;
  readAt: string | null;
  event?: string | null;
  topic?: string | null;
  expiresAt?: string | null;
  resolvedAt?: string | null;
  /** Still news: not read, not handled by anyone and not out of date. */
  active?: boolean;
  parameters?: { delivery_channels?: string[]; url?: string; event?: string; tag?: string };
};

export type NotificationList = { notifications: Notification[]; unreadCount: number };

export type NotificationKind = {
  event: string;
  group: 'tasks' | 'calendar' | 'allowance' | 'streaks';
  enabled: boolean;
  channels: string[];
  /** False for kinds that cannot reach this user, e.g. approval requests for somebody who administers no team. */
  relevant: boolean;
};

/** Unread notifications that are still true, newest first. */
export const listUnread = async (limit: number): Promise<NotificationList> => {
  const list = await apiClient.get<NotificationList>(`/api/notifications?unread=1&limit=${limit}`);

  return { notifications: list.notifications ?? [], unreadCount: list.unreadCount ?? list.notifications?.length ?? 0 };
};

export const listRecent = async (limit: number): Promise<Notification[]> =>
  (await apiClient.get<NotificationList>(`/api/notifications?limit=${limit}`)).notifications ?? [];

export const markAsRead = (id: string): Promise<{ unreadCount?: number }> =>
  apiClient.post(`/api/notifications/${id}/read`);

export const markManyAsRead = (ids: string[]): Promise<{ unreadCount?: number }> =>
  apiClient.post('/api/notifications/read-all', { ids });

export const markAllAsRead = (): Promise<{ unreadCount?: number }> => apiClient.post('/api/notifications/read-all');

export const readKinds = async (): Promise<NotificationKind[]> =>
  (await apiClient.get<{ events: NotificationKind[] }>('/api/notifications/preferences')).events ?? [];

export const saveKinds = async (kinds: NotificationKind[]): Promise<NotificationKind[]> =>
  (await apiClient.put<{ events: NotificationKind[] }>('/api/notifications/preferences', {
    events: Object.fromEntries(kinds.map((kind) => [kind.event, kind.enabled])),
  })).events ?? kinds;

/** Where in the app a notification leads. */
export const routeOf = (url?: string): '/' | '/allowance' | '/day-planning' | null =>
  ({ '/tasks': '/', '/allowance': '/allowance', '/day-planning': '/day-planning' } as const)[url as '/tasks'] ?? null;

export type PushAvailability = { available: boolean; native: boolean };

export const pushAvailability = async (): Promise<PushAvailability> => {
  const key = await apiClient.get<{ available?: boolean; native?: boolean }>('/api/push/key');

  return { available: Boolean(key.available), native: Boolean(key.native) };
};

export const registerPhone = (token: string, deviceLabel: string | null): Promise<unknown> =>
  apiClient.post('/api/push/devices', { token, platform: 'android', deviceLabel });

export const unregisterPhone = (token: string): Promise<unknown> =>
  apiClient.delete(`/api/push/devices?token=${encodeURIComponent(token)}`);
