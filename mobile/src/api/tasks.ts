import type { PlanSnapshot } from './action-plans';
import apiClient from './client';

export type ExecutionLimit = {
  type: string;
  count?: number;
};

export type TaskTemplate = {
  actionPlanId?: string | null;
  id: string;
  teamId: string;
  name: string;
  description: string | null;
  points: number;
  frequency: string;
  executionLimit: ExecutionLimit;
  remaining: number | null;
  isActive: boolean;
};

export type TaskExecution = {
  actionPlan?: PlanSnapshot | null;
  id: string;
  taskTemplateId: string | null;
  assignedUserId?: string;
  assignedUserName?: string;
  name: string;
  points: number;
  status: string;
  scheduledFor: string | null;
  completedAt: string | null;
  rejectionReason: string | null;
};

export const listTaskTemplates = async (): Promise<TaskTemplate[]> => {
  const { templates } = await apiClient.get<{ templates: TaskTemplate[] }>('/api/task-templates');

  return templates;
};

export const listMyExecutions = async (): Promise<TaskExecution[]> => {
  const { executions } = await apiClient.get<{ executions: TaskExecution[] }>(
    '/api/task-executions/mine'
  );

  return executions;
};

export const takeTaskTemplate = (id: string): Promise<unknown> =>
  apiClient.post(`/api/task-templates/${id}/take`);

export const completeExecution = (id: string, doneOn?: string): Promise<unknown> =>
  apiClient.post(`/api/task-executions/${id}/complete`, doneOn ? { doneOn } : {});

export const abandonExecution = (id: string): Promise<unknown> =>
  apiClient.post(`/api/task-executions/${id}/abandon`);

export const listExecutionsOf = async (userId: string): Promise<TaskExecution[]> => {
  const { executions } = await apiClient.get<{ executions: TaskExecution[] }>(
    `/api/task-executions/of/${userId}`
  );

  return executions;
};

export const approveExecution = (id: string): Promise<unknown> =>
  apiClient.post(`/api/task-executions/${id}/approve`);

export const rejectExecution = (id: string, reason: string): Promise<unknown> =>
  apiClient.post(`/api/task-executions/${id}/reject`, { reason });

export const listAwaitingApproval = async (): Promise<TaskExecution[]> => {
  const { executions } = await apiClient.get<{ executions: TaskExecution[] }>('/api/task-executions/awaiting-approval');
  return executions;
};
