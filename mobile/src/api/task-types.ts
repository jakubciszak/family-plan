import apiClient from './client';
import type { TaskTemplate } from './tasks';

export type LimitType = 'unlimited' | 'once' | 'per_day' | 'per_week' | 'per_month';

export const LIMIT_TYPES: LimitType[] = ['unlimited', 'once', 'per_day', 'per_week', 'per_month'];

export const FREQUENCIES = ['once', 'daily', 'weekly', 'monthly'] as const;

export type Frequency = (typeof FREQUENCIES)[number];

export type ExecutionLimit = {
  type: LimitType;
  count?: number;
};

export type TaskTypeDraft = {
  actionPlanId?: string | null;
  teamId: string;
  name: string;
  description: string;
  points: number;
  frequency: Frequency;
  executionLimit: ExecutionLimit;
};

export const countsWith = (type: LimitType): boolean =>
  type === 'per_day' || type === 'per_week' || type === 'per_month';

export const createTaskType = (draft: TaskTypeDraft): Promise<TaskTemplate> =>
  apiClient.post<TaskTemplate>('/api/task-templates', draft);

export const updateTaskType = (id: string, draft: Omit<TaskTypeDraft, 'teamId'>): Promise<TaskTemplate> =>
  apiClient.put<TaskTemplate>(`/api/task-templates/${id}`, draft);

export const activateTaskType = (id: string): Promise<unknown> =>
  apiClient.post(`/api/task-templates/${id}/activate`);

export const deactivateTaskType = (id: string): Promise<unknown> =>
  apiClient.post(`/api/task-templates/${id}/deactivate`);

export const deleteTaskType = (id: string): Promise<unknown> =>
  apiClient.delete(`/api/task-templates/${id}`);
