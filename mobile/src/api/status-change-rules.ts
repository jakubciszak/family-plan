import apiClient from './client';

export const CONDITION_TYPES = ['other_task_completed_today', 'last_execution_cooldown'] as const;

export type ConditionType = (typeof CONDITION_TYPES)[number];

/** The API speaks snake_case, the translation keys camelCase. */
export const conditionKey = (type: ConditionType): string =>
  type === 'other_task_completed_today' ? 'otherTaskCompletedToday' : 'lastExecutionCooldown';

export type ConditionConfig = {
  cooldownDays?: number;
  requiredTaskTemplateId?: string | null;
};

export type StatusChangeRule = {
  id: string;
  taskTemplateId: string;
  name: string;
  description: string;
  conditionType: ConditionType;
  config: ConditionConfig;
  isActive: boolean;
};

export const listStatusChangeRules = async (): Promise<StatusChangeRule[]> => {
  const { rules } = await apiClient.get<{ rules: StatusChangeRule[] }>('/api/status-change-rules');

  return rules;
};

export const createStatusChangeRule = (rule: {
  taskTemplateId: string;
  name: string;
  description: string;
  conditionType: ConditionType;
  conditionConfig: ConditionConfig;
}): Promise<unknown> => apiClient.post('/api/status-change-rules', rule);

export const updateStatusChangeRule = (
  id: string,
  rule: {
    name: string;
    description: string;
    conditionType: ConditionType;
    conditionConfig: ConditionConfig;
  }
): Promise<unknown> => apiClient.put(`/api/status-change-rules/${id}`, rule);

export const activateStatusChangeRule = (id: string): Promise<unknown> =>
  apiClient.post(`/api/status-change-rules/${id}/activate`);

export const deactivateStatusChangeRule = (id: string): Promise<unknown> =>
  apiClient.post(`/api/status-change-rules/${id}/deactivate`);
