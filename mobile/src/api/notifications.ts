import apiClient from './client';

export type Notification = {
  id: string;
  subject: string | null;
  message: string;
  createdAt: string;
  readAt: string | null;
  parameters?: { delivery_channels?: string[] };
};

export const listUnread = async (limit: number): Promise<Notification[]> => {
  const { notifications } = await apiClient.get<{ notifications: Notification[] }>(
    `/api/notifications?unread=1&limit=${limit}`
  );

  return notifications;
};

export const markAsRead = (id: string): Promise<unknown> =>
  apiClient.post(`/api/notifications/${id}/read`);
