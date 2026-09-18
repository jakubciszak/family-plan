import apiClient from './client';

export const REMINDER_SOUNDS = ['soft', 'bell', 'double', 'melody'] as const;
export type ReminderSound = (typeof REMINDER_SOUNDS)[number];
export type PlanStep = { name: string; stages: string[] };
export type PlanSnapshot = {
  name: string;
  steps: PlanStep[];
  estimatedMinutes: number | null;
  reminderMinutes: number;
  reminderSound: ReminderSound;
};
export type ActionPlan = PlanSnapshot & {
  id: string;
  teamId: string | null;
  canManage: boolean;
  canChangeScope: boolean;
};
export type PlanDraft = PlanSnapshot & { id?: string; teamId: string | null };

export const listActionPlans = async (): Promise<ActionPlan[]> =>
  (await apiClient.get<{ plans: ActionPlan[] }>('/api/action-plans')).plans;
export const saveActionPlan = (draft: PlanDraft): Promise<ActionPlan> => draft.id
  ? apiClient.put(`/api/action-plans/${draft.id}`, draft)
  : apiClient.post('/api/action-plans', draft);
export const deleteActionPlan = (id: string): Promise<unknown> => apiClient.delete(`/api/action-plans/${id}`);
