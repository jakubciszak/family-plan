import apiClient from './client';

export type Registration = {
  name: string;
  email: string;
  password: string;
  phoneNumber?: string;
  inviteToken?: string;
};

export type Registered = {
  message: string;
  activationRequired: boolean;
  id: string;
};

export const register = (registration: Registration): Promise<Registered> =>
  apiClient.post<Registered>('/api/auth/register', registration);

export const addressInvited = async (token: string): Promise<string | null> => {
  const { email } = await apiClient.get<{ email?: string }>(
    `/api/teams/invitations/${encodeURIComponent(token)}`
  );

  return email ?? null;
};
