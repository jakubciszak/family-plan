import axios, { AxiosInstance } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  LoginCredentials,
  LoginResponse,
  User,
  TasksResponse,
  CreateTaskData,
  BonusRulesResponse,
  CreateBonusRuleData,
  UpdateBonusRuleData,
  UserPointsResponse,
  UserPreferences,
  UpdateUserSettingsRequest,
  TeamsResponse,
  Team,
  CreateTeamData,
  UpdateTeamData,
  TeamMembersResponse,
  InviteToTeamData,
  TeamInvitationsResponse,
} from '../types/api';

const API_URL = process.env.API_URL || 'http://localhost:8080';
const TOKEN_KEY = 'jwt_token';

class ApiClient {
  private axiosInstance: AxiosInstance;

  constructor() {
    this.axiosInstance = axios.create({
      baseURL: API_URL,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    });
  }

  async setToken(token: string): Promise<void> {
    await AsyncStorage.setItem(TOKEN_KEY, token);
  }

  async getToken(): Promise<string | null> {
    return await AsyncStorage.getItem(TOKEN_KEY);
  }

  async clearToken(): Promise<void> {
    await AsyncStorage.removeItem(TOKEN_KEY);
  }

  private async getAuthHeaders() {
    const token = await this.getToken();
    if (token) {
      return { Authorization: `Bearer ${token}` };
    }
    return {};
  }

  async login(credentials: LoginCredentials): Promise<LoginResponse> {
    const response = await this.axiosInstance.post<LoginResponse>(
      '/api/auth/login',
      credentials
    );
    if (response.data.token) {
      await this.setToken(response.data.token);
    }
    return response.data;
  }

  async getCurrentUser(): Promise<User> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get<User>('/api/auth/me', {
      headers,
    });
    return response.data;
  }

  async logout(): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post('/api/auth/logout', {}, { headers });
    await this.clearToken();
  }

  async getTasks(): Promise<TasksResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get<TasksResponse>('/api/tasks', {
      headers,
    });
    return response.data;
  }

  async createTask(taskData: CreateTaskData): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post('/api/tasks', taskData, { headers });
  }

  async completeTask(taskId: string, userId: string): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post(
      `/api/tasks/${taskId}/complete`,
      { userId },
      { headers }
    );
  }

  async approveTask(taskId: string, adminId: string): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post(
      `/api/tasks/${taskId}/approve`,
      { adminId },
      { headers }
    );
  }

  async assignTask(taskId: string, userId: string): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post(
      `/api/tasks/${taskId}/assign`,
      { userId },
      { headers }
    );
  }

  async getUserPoints(userId: string): Promise<UserPointsResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get<UserPointsResponse>(
      `/api/users/${userId}/points`,
      { headers }
    );
    return response.data;
  }

  async getBonusRules(): Promise<BonusRulesResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get<BonusRulesResponse>(
      '/api/bonus-rules',
      { headers }
    );
    return response.data;
  }

  async createBonusRule(ruleData: CreateBonusRuleData): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post('/api/bonus-rules', ruleData, { headers });
  }

  async updateBonusRule(
    ruleId: string,
    ruleData: UpdateBonusRuleData
  ): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.put(`/api/bonus-rules/${ruleId}`, ruleData, {
      headers,
    });
  }

  async activateBonusRule(ruleId: string): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post(
      `/api/bonus-rules/${ruleId}/activate`,
      {},
      { headers }
    );
  }

  async deactivateBonusRule(ruleId: string): Promise<void> {
    const headers = await this.getAuthHeaders();
    await this.axiosInstance.post(
      `/api/bonus-rules/${ruleId}/deactivate`,
      {},
      { headers }
    );
  }

  async getUserSettings(userId: string): Promise<UserPreferences> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get(
      `/api/user-settings/${userId}`,
      { headers }
    );
    return response.data;
  }

  async updateUserSettings(
    userId: string,
    data: UpdateUserSettingsRequest
  ): Promise<{ status: string }> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.put(
      `/api/user-settings/${userId}`,
      data,
      { headers }
    );
    return response.data;
  }

  // Team Management Methods
  async getTeams(): Promise<TeamsResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get('/api/teams', { headers });
    return response.data;
  }

  async createTeam(data: CreateTeamData): Promise<Team> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.post('/api/teams', data, { headers });
    return response.data;
  }

  async updateTeam(teamId: string, data: UpdateTeamData): Promise<{ message: string }> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.put(`/api/teams/${teamId}`, data, { headers });
    return response.data;
  }

  async getTeamMembers(teamId: string): Promise<TeamMembersResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get(`/api/teams/${teamId}/members`, { headers });
    return response.data;
  }

  async inviteToTeam(teamId: string, data: InviteToTeamData): Promise<{ message: string; invitationId: string }> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.post(`/api/teams/${teamId}/invite`, data, { headers });
    return response.data;
  }

  async removeMember(teamId: string, userId: string): Promise<{ message: string }> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.delete(`/api/teams/${teamId}/members/${userId}`, { headers });
    return response.data;
  }

  async getMyInvitations(): Promise<TeamInvitationsResponse> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.get('/api/teams/invitations', { headers });
    return response.data;
  }

  async acceptInvitation(token: string): Promise<{ message: string }> {
    const headers = await this.getAuthHeaders();
    const response = await this.axiosInstance.post(`/api/teams/invitations/${token}/accept`, {}, { headers });
    return response.data;
  }
}

const apiClient = new ApiClient();
export default apiClient;
