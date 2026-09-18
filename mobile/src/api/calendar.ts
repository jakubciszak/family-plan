import apiClient from './client';

export type CalendarWeek = {
  weekStart: string;
  total: number;
  bonusTotal: number;
  days: { date: string; points: number; bonus: number; inStreak: boolean; reachedThreshold?: boolean; isToday: boolean }[];
  streak: { length: number; requiredDays: number; pointsPerDay: number; bonusPoints: number; met: boolean } | null;
};

export type CalendarDay = {
  date: string;
  closed: boolean;
  userId: string;
  tasks: { id: string; name: string; points: number; earnedOn: string }[];
  bonuses: { id: string; points: number; name: string; takenBack: boolean }[];
};

export const readCalendar = (weekStart: string, userId?: string): Promise<CalendarWeek> =>
  apiClient.get(`/api/points/week?${new URLSearchParams({ weekStart, ...(userId ? { userId } : {}) })}`);

export const readDay = (date: string, userId?: string): Promise<CalendarDay> =>
  apiClient.get(`/api/points/day?${new URLSearchParams({ date, ...(userId ? { userId } : {}) })}`);

export type Standing = { userId: string; name: string; total: number; perDay?: Record<string, number> };

export const readStandings = (teamId: string, weekStart: string): Promise<{ standings: Standing[]; days?: string[]; today?: string }> =>
  apiClient.get(`/api/points/leaderboard?${new URLSearchParams({ teamId, weekStart })}`);

export const moveExecution = (id: string, doneOn: string): Promise<unknown> =>
  apiClient.put(`/api/task-executions/${id}`, { doneOn });

export const deleteExecution = (id: string): Promise<unknown> =>
  apiClient.delete(`/api/task-executions/${id}`);

export const takeBackBonus = (id: string, userId: string): Promise<unknown> =>
  apiClient.delete(`/api/points/bonuses/${id}?${new URLSearchParams({ userId })}`);
