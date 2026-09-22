import apiClient from './client';

export type StudentLink = {
  studentId: string;
  studentName: string;
  userId: string | null;
};

export type SchoolAccount = {
  teamId: string;
  schoolId: string;
  login: string;
  students: StudentLink[];
  tagId: string | null;
  importedAt: string | null;
  updatedAt: string;
};

export type ImportSummary = {
  weekStart: string;
  weekEnd: string;
  added: number;
  updated: number;
  unchanged: number;
  removed: number;
  substitutions: number;
  skipped: { unlinked: number; cancelled: number };
  account: SchoolAccount;
};

export const readSchoolAccount = async (teamId: string): Promise<SchoolAccount | null> => {
  const { account } = await apiClient.get<{ account: SchoolAccount | null }>(
    `/api/school-timetable/config?teamId=${encodeURIComponent(teamId)}`
  );

  return account;
};

export const saveSchoolAccount = async (signIn: {
  teamId: string;
  schoolId: string;
  login: string;
  password?: string;
  tagId?: string | null;
}): Promise<SchoolAccount> => {
  const { account } = await apiClient.put<{ account: SchoolAccount }>(
    '/api/school-timetable/config',
    signIn
  );

  return account;
};

export const forgetSchoolAccount = (teamId: string): Promise<unknown> =>
  apiClient.delete(`/api/school-timetable/config?teamId=${encodeURIComponent(teamId)}`);

export const refreshStudents = async (
  teamId: string,
  weekStart?: string
): Promise<SchoolAccount> => {
  const { account } = await apiClient.post<{ account: SchoolAccount }>(
    '/api/school-timetable/students/refresh',
    { teamId, weekStart }
  );

  return account;
};

export const linkStudents = async (
  teamId: string,
  links: Record<string, string | null>
): Promise<SchoolAccount> => {
  const { account } = await apiClient.post<{ account: SchoolAccount }>(
    '/api/school-timetable/students/links',
    { teamId, links }
  );

  return account;
};

export const importTimetable = (teamId: string, weekStart?: string): Promise<ImportSummary> =>
  apiClient.post<ImportSummary>('/api/school-timetable/import', { teamId, weekStart });

export const mondayOf = (date: Date): string => {
  const monday = new Date(date);
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

  return [
    monday.getFullYear(),
    String(monday.getMonth() + 1).padStart(2, '0'),
    String(monday.getDate()).padStart(2, '0'),
  ].join('-');
};

export const weekAfter = (weekStart: string): string => {
  const next = new Date(`${weekStart}T00:00:00`);
  next.setDate(next.getDate() + 7);

  return mondayOf(next);
};
