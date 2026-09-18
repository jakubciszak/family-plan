import type { PlanSnapshot, ReminderSound } from '../api/action-plans';

export type PlanRun = {
  version: 1;
  plan: PlanSnapshot;
  index: number;
  totalMs: number;
  stepMs: number;
  anchor: number | null;
  lastReminderStepMs: number;
  mode: 'remaining' | 'elapsed';
  sound: boolean;
  reminderSound?: ReminderSound;
  executionId?: string | null;
  executionName?: string | null;
  taskCompleted?: boolean;
};

export const activitiesOf = (plan: PlanSnapshot) => plan.steps.flatMap((step, stepIndex) => step.stages.length
  ? step.stages.map((name, stageIndex) => ({ name, parent: step.name as string | null, stepIndex, stageIndex: stageIndex as number | null }))
  : [{ name: step.name, parent: null, stepIndex, stageIndex: null }]);

export const elapsedOf = (run: PlanRun, now: number) => {
  const delta = run.anchor === null ? 0 : Math.max(0, now - run.anchor);
  return { total: run.totalMs + delta, step: run.stepMs + delta };
};
export const reminderInterval = (plan: PlanSnapshot) => plan.estimatedMinutes
  ? Math.max(30000, plan.estimatedMinutes * 60000 / activitiesOf(plan).length)
  : plan.reminderMinutes * 60000;
export const startRun = (plan: PlanSnapshot, now: number): PlanRun => ({
  version: 1, plan, index: 0, totalMs: 0, stepMs: 0, anchor: now,
  lastReminderStepMs: 0, mode: plan.estimatedMinutes ? 'remaining' : 'elapsed', sound: true,
});
export const pauseRun = (run: PlanRun, now: number): PlanRun => {
  const elapsed = elapsedOf(run, now);
  return { ...run, totalMs: elapsed.total, stepMs: elapsed.step, anchor: null };
};
export const resumeRun = (run: PlanRun, now: number): PlanRun => ({ ...run, anchor: now, lastReminderStepMs: run.stepMs });
export const advanceRun = (run: PlanRun, now: number): PlanRun => ({
  ...run, index: run.index + 1, totalMs: elapsedOf(run, now).total, stepMs: 0,
  anchor: run.index + 1 === activitiesOf(run.plan).length ? null : now, lastReminderStepMs: 0,
});
export const storageKey = (userId: string) => `action-plan-run:${userId}`;
export const shouldKeepRun = (run: PlanRun | null) => run && (run.index < activitiesOf(run.plan).length || (run.executionId && !run.taskCompleted));

export const restoreRun = (raw: string | null, now: number): PlanRun | null => {
  try {
    const run = JSON.parse(raw ?? 'null');
    if (!run || run.version !== 1 || !run.plan || typeof run.plan.name !== 'string'
      || !Array.isArray(run.plan.steps) || !run.plan.steps.length
      || !run.plan.steps.every((step: { name: unknown; stages: unknown }) => step && typeof step.name === 'string' && Array.isArray(step.stages) && step.stages.every((stage: unknown) => typeof stage === 'string'))
      || !Number.isInteger(run.index) || run.index < 0 || run.index > activitiesOf(run.plan).length
      || (run.index === activitiesOf(run.plan).length && (typeof run.executionId !== 'string' || !run.executionId || run.taskCompleted))
      || ![run.totalMs, run.stepMs, run.lastReminderStepMs].every((value) => Number.isFinite(value) && value >= 0)
      || (run.anchor !== null && !Number.isFinite(run.anchor))
      || !['remaining', 'elapsed'].includes(run.mode)
      || typeof run.sound !== 'boolean'
      || !Number.isInteger(run.plan.reminderMinutes) || run.plan.reminderMinutes < 1
      || (run.plan.estimatedMinutes !== null && (!Number.isInteger(run.plan.estimatedMinutes) || run.plan.estimatedMinutes < 1))) return null;
    return pauseRun(run, now);
  } catch {
    return null;
  }
};
export const formatTime = (milliseconds: number) => {
  const seconds = Math.floor(Math.max(0, milliseconds) / 1000);
  return `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
};
