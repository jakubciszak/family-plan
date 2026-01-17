export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
}

export interface PreferenceOption {
  name: string;
  enabled: boolean;
}

export interface UserPreference {
  type: string;
  options: PreferenceOption[];
}

export interface UserPreferences {
  preferences: UserPreference[];
}

export interface UpdateUserSettingsRequest {
  preference_type: string;
  options: PreferenceOption[];
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  message: string;
  user: User;
  token: string;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  phoneNumber?: string;
}

export interface RegisterResponse {
  message: string;
}

export interface Task {
  id: string;
  name: string;
  description: string;
  points: number;
  frequency: 'once' | 'daily' | 'weekly' | 'monthly';
  status: 'pending' | 'completed' | 'approved';
  assignedUserId: string | null;
  assignedUserName?: string;
  createdAt: string;
}

export interface TasksResponse {
  tasks: Task[];
}

export interface CreateTaskData {
  name: string;
  description: string;
  points: number;
  frequency: 'once' | 'daily' | 'weekly' | 'monthly';
}

export interface BonusRule {
  id: string;
  name: string;
  description: string;
  bonusPoints: number;
  type: 'consecutive_days' | 'monthly_task_count';
  config: {
    requiredDays?: number;
    requiredCount?: number;
    taskTemplateId?: string;
  };
  isActive: boolean;
}

export interface BonusRulesResponse {
  rules: BonusRule[];
}

export interface CreateBonusRuleData {
  name: string;
  description: string;
  bonusPoints: number;
  ruleType: 'consecutive_days' | 'monthly_task_count';
  ruleConfig: {
    requiredDays?: number;
    requiredCount?: number;
    taskTemplateId?: string;
  };
}

export interface UpdateBonusRuleData {
  name: string;
  description: string;
  bonusPoints: number;
}

export interface UserPointsResponse {
  balance: number;
}

// Team Management Types
export interface Team {
  id: string;
  name: string;
  description: string | null;
  createdBy: string;
  createdAt: string;
  updatedAt: string | null;
}

export interface TeamMember {
  id: string;
  userId: string;
  userName: string;
  userEmail: string;
  role: 'admin' | 'member';
  joinedAt: string;
}

export interface TeamInvitation {
  id: string;
  teamId: string;
  role: 'admin' | 'member';
  token: string;
  status: 'pending' | 'accepted' | 'rejected' | 'expired';
  createdAt: string;
  expiresAt: string | null;
}

export interface TeamsResponse {
  teams: Team[];
}

export interface TeamMembersResponse {
  members: TeamMember[];
}

export interface TeamInvitationsResponse {
  invitations: TeamInvitation[];
}

export interface CreateTeamData {
  name: string;
  description?: string;
}

export interface UpdateTeamData {
  name: string;
  description?: string;
}

export interface InviteToTeamData {
  email: string;
  role: 'admin' | 'member';
}
