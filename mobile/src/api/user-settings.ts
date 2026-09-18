import type { NotificationChannel } from './notification-policies';

import apiClient from './client';

export type ChannelChoice = {
  name: NotificationChannel;
  enabled: boolean;
};

type Preference = {
  type: string;
  options?: ChannelChoice[];
};

const WHEN_UNSET: ChannelChoice[] = [
  { name: 'email', enabled: true },
  { name: 'sms', enabled: false },
  { name: 'in_app', enabled: true },
  { name: 'push', enabled: true },
];

export const readNotificationChannels = async (userId: string): Promise<ChannelChoice[]> => {
  const { preferences } = await apiClient.get<{ preferences?: Preference[] }>(
    `/api/user-settings/${userId}`
  );

  const held = preferences?.find((preference) => preference.type === 'notifications')?.options;

  return WHEN_UNSET.map(
    (fallback) => held?.find((choice) => choice.name === fallback.name) ?? fallback
  );
};

export const saveNotificationChannels = (
  userId: string,
  choices: ChannelChoice[]
): Promise<unknown> =>
  apiClient.put(`/api/user-settings/${userId}`, {
    preference_type: 'notifications',
    options: choices,
  });
