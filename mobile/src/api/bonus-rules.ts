import apiClient from './client';

export const BONUS_RULE_TYPES = [
  'consecutive_days',
  'monthly_task_count',
  'weekly_points_sum',
] as const;

export type BonusRuleType = (typeof BONUS_RULE_TYPES)[number];

export type BonusRuleConfig = {
  requiredDays?: number;
  pointsPerDay?: number;
  requiredCount?: number;
  requiredPoints?: number;
  accounts?: string[];
};

export type BonusRule = {
  id: string;
  teamId: string;
  name: string;
  description: string;
  bonusPoints: number;
  type: BonusRuleType;
  config: BonusRuleConfig;
  isActive: boolean;
};

export const listBonusRules = async (): Promise<BonusRule[]> => {
  const { rules } = await apiClient.get<{ rules: BonusRule[] }>('/api/bonus-rules');

  return rules;
};

export const listPointsAccounts = async (): Promise<string[]> => {
  const { accounts } = await apiClient.get<{ accounts: { kind: string }[] }>(
    '/api/points/accounts'
  );

  return accounts.map((account) => account.kind);
};

export const createBonusRule = (rule: {
  teamId: string;
  name: string;
  description: string;
  bonusPoints: number;
  ruleType: BonusRuleType;
  ruleConfig: BonusRuleConfig;
}): Promise<unknown> => apiClient.post('/api/bonus-rules', rule);

export const updateBonusRule = (
  id: string,
  rule: {
    name: string;
    description: string;
    bonusPoints: number;
    ruleType: BonusRuleType;
    ruleConfig: BonusRuleConfig;
  }
): Promise<unknown> => apiClient.put(`/api/bonus-rules/${id}`, rule);

export const activateBonusRule = (id: string): Promise<unknown> =>
  apiClient.post(`/api/bonus-rules/${id}/activate`);

export const deactivateBonusRule = (id: string): Promise<unknown> =>
  apiClient.post(`/api/bonus-rules/${id}/deactivate`);
