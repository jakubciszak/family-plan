import type { Avatar } from './personalisation';

import apiClient from './client';

export type TeamRole = 'admin' | 'member';

export type Team = {
  id: string;
  name: string;
  description: string | null;
  createdBy: string;
  createdAt: string;
  updatedAt: string | null;
  role: TeamRole;
};

export type Face = {
  nickname: string | null;
  theme: string;
  avatar: Avatar;
};

export type Member = {
  id: string;
  userId: string;
  userName: string;
  givenName: string;
  userEmail: string;
  role: TeamRole;
  joinedAt: string;
  face: Face | null;
};

export type Invitation = {
  id: string;
  token: string;
  teamId: string;
  teamName: string;
  email: string;
  role: TeamRole;
  status: string;
  expiresAt: string | null;
  accountExists?: boolean;
  invitationUrl?: string | null;
};

export const listTeams = async (): Promise<Team[]> => {
  const { teams } = await apiClient.get<{ teams: Team[] }>('/api/teams');

  return teams;
};

export const createTeam = (name: string, description?: string): Promise<unknown> =>
  apiClient.post('/api/teams', { name, description: description || null });

export const updateTeam = (
  teamId: string,
  name: string,
  description?: string
): Promise<unknown> =>
  apiClient.put(`/api/teams/${teamId}`, { name, description: description || null });

export const listMembers = async (teamId: string): Promise<Member[]> => {
  const { members } = await apiClient.get<{ members: Member[] }>(`/api/teams/${teamId}/members`);

  return members;
};

export const inviteToTeam = (teamId: string, email: string, role: TeamRole): Promise<unknown> =>
  apiClient.post(`/api/teams/${teamId}/invite`, { email, role });

export const removeMember = (teamId: string, userId: string): Promise<unknown> =>
  apiClient.delete(`/api/teams/${teamId}/members/${userId}`);

export const listMyInvitations = async (): Promise<Invitation[]> => {
  const { invitations } = await apiClient.get<{ invitations: Invitation[] }>(
    '/api/teams/invitations'
  );

  return invitations;
};

export const acceptInvitation = (token: string): Promise<unknown> =>
  apiClient.post(`/api/teams/invitations/${token}/accept`);

export const readTeamMembers = (teamId: string): Promise<{ members: Member[]; invitations: Invitation[] }> => apiClient.get(`/api/teams/${teamId}/members`);
