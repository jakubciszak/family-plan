import apiClient from './client';

export type Payout = {
  id: string;
  amount: number;
  note: string | null;
  status: string;
  offeredAt: string;
};

export type Wallet = {
  currency: string;
  pending: number;
  available: number;
  putAside: number;
  earned: number;
  otherIncome: number;
  spent: number;
  awaitingConfirmation: Payout[];
};

export type Goal = {
  id: string;
  name: string;
  target: number;
  saved: number;
  percent: number;
  weeksLeft: number | null;
  weeksAtThisPace: number | null;
  perWeekNeeded: number | null;
  wantedBy: string | null;
  reached: boolean;
  missing: number;
};

export type Goals = {
  currency: string;
  weeklyPace: number;
  available: number;
  goals: Goal[];
};

export type BookingEntry = {
  account: string;
  goalId: string | null;
  amount: number;
};

export type Booking = {
  id: string;
  type: string;
  amount: number;
  description: string | null;
  reference: string | null;
  context: { goal?: string; week?: string } | unknown[];
  bookedAt: string;
  entries: BookingEntry[];
};

export const OUTGOING = ['expense', 'goal_allocation', 'goal_spending', 'week_reopened'];

export type Ledger = {
  currency: string;
  bookings: Booking[];
};

export type WeekDay = {
  date: string;
  points: number;
  bonus: number;
  isToday: boolean;
};

export type ExpectedLine = {
  pointsAccount: string;
  points: number;
  minimumPoints: number;
  reachedMinimum: boolean;
  missingPoints: number;
  amount: number;
};

export type Week = {
  weekStart: string;
  currency: string;
  days: WeekDay[];
  points: number;
  bonusPoints: number;
  isOver: boolean;
  expected: { total: number; lines: ExpectedLine[] };
  closure: { closedAt: string; total: number; lines: ExpectedLine[] } | null;
};

export type PointsAccount = 'tasks' | 'bonuses';

export const POINTS_ACCOUNTS: PointsAccount[] = ['tasks', 'bonuses'];

export type AllowanceRule = {
  id: string;
  teamId: string;
  pointsAccount: PointsAccount;
  minimumPoints: number;
  rateAmount: number;
  ratePerPoints: number;
  isActive: boolean;
};

export type Rules = {
  currency: string;
  rules: AllowanceRule[];
};

export const readRules = (teamId: string): Promise<Rules> =>
  apiClient.get<Rules>(`/api/allowance/rules?teamId=${teamId}`);

export const setRule = (rule: {
  teamId: string;
  pointsAccount: PointsAccount;
  minimumPoints: number;
  rateAmount: number;
  ratePerPoints: number;
}): Promise<unknown> => apiClient.put('/api/allowance/rules', rule);

export const removeRule = (teamId: string, pointsAccount: PointsAccount): Promise<unknown> =>
  apiClient.delete(`/api/allowance/rules/${pointsAccount}?teamId=${teamId}`);

export const closeWeek = (userId: string, weekStart: string): Promise<unknown> =>
  apiClient.post('/api/allowance/weeks/close', { userId, weekStart });

export const reopenWeek = (userId: string, weekStart: string): Promise<unknown> =>
  apiClient.post('/api/allowance/weeks/reopen', { userId, weekStart });

export const offerPayout = (userId: string, amount: number, note?: string): Promise<unknown> =>
  apiClient.post('/api/allowance/payouts', { userId, amount, note: note || null });

export const readWallet = (userId?: string): Promise<Wallet> =>
  apiClient.get<Wallet>(
    `/api/allowance/wallet${userId ? `?userId=${encodeURIComponent(userId)}` : ''}`,
  );

export const readGoals = (userId?: string): Promise<Goals> =>
  apiClient.get<Goals>(
    `/api/allowance/goals${userId ? `?userId=${encodeURIComponent(userId)}` : ''}`,
  );

export const readLedger = (): Promise<Ledger> => apiClient.get<Ledger>('/api/allowance/ledger');

export const readWeek = (userId?: string, weekStart?: string): Promise<Week> => {
  const query = new URLSearchParams();
  if (userId) query.set('userId', userId);
  if (weekStart) query.set('weekStart', weekStart);
  return apiClient.get<Week>(`/api/allowance/weeks?${query}`);
};

export const addIncome = (amount: number, description: string, on?: string): Promise<unknown> =>
  apiClient.post('/api/allowance/income', { amount, description, on });

export const addExpense = (amount: number, description: string, on?: string): Promise<unknown> =>
  apiClient.post('/api/allowance/expenses', { amount, description, on });

export const confirmPayout = (id: string): Promise<unknown> =>
  apiClient.post(`/api/allowance/payouts/${id}/confirm`);

export const cancelPayout = (id: string): Promise<unknown> =>
  apiClient.post(`/api/allowance/payouts/${id}/cancel`);

export const planGoal = (name: string, target: number, wantedBy?: string): Promise<unknown> =>
  apiClient.post('/api/allowance/goals', { name, target, wantedBy });

export const putAside = (id: string, amount: number): Promise<unknown> =>
  apiClient.post(`/api/allowance/goals/${id}/put-aside`, { amount });

export const takeBack = (id: string, amount: number): Promise<unknown> =>
  apiClient.post(`/api/allowance/goals/${id}/take-back`, { amount });

export const spendGoal = (id: string, amount: number, description: string): Promise<unknown> =>
  apiClient.post(`/api/allowance/goals/${id}/spend`, { amount, description });

export const giveUpGoal = (id: string): Promise<unknown> =>
  apiClient.delete(`/api/allowance/goals/${id}`);
