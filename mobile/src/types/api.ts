export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
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
