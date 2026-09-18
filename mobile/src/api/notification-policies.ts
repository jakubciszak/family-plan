import apiClient from './client';

export type NotificationChannel = 'email' | 'sms' | 'in_app' | 'push';

export type EventPolicy = {
  event: string;
  channels: NotificationChannel[];
  defaultChannels: NotificationChannel[];
  configurable: boolean;
};

export type PolicyMatrix = {
  channels: NotificationChannel[];
  events: EventPolicy[];
};

export const readPolicies = (): Promise<PolicyMatrix> =>
  apiClient.get<PolicyMatrix>('/api/notification-policies');

export const savePolicy = (event: string, channels: NotificationChannel[]): Promise<unknown> =>
  apiClient.put(`/api/notification-policies/${event}`, { channels });
