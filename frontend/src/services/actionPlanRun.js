export const activitiesOf = (plan) => plan.steps.flatMap((step, stepIndex) => step.stages.length
    ? step.stages.map((name, stageIndex) => ({ name, parent: step.name, stepIndex, stageIndex }))
    : [{ name: step.name, parent: null, stepIndex, stageIndex: null }]);

export const elapsedOf = (run, now) => {
    const delta = run.anchor === null ? 0 : Math.max(0, now - run.anchor);
    return { total: run.totalMs + delta, step: run.stepMs + delta };
};

export const reminderInterval = (plan) => plan.estimatedMinutes
    ? Math.max(30000, plan.estimatedMinutes * 60000 / activitiesOf(plan).length)
    : plan.reminderMinutes * 60000;

export const startRun = (plan, now) => ({
    version: 1, plan, index: 0, totalMs: 0, stepMs: 0, anchor: now,
    lastReminderStepMs: 0, mode: plan.estimatedMinutes ? 'remaining' : 'elapsed', sound: true,
});

export const pauseRun = (run, now) => {
    const elapsed = elapsedOf(run, now);
    return { ...run, totalMs: elapsed.total, stepMs: elapsed.step, anchor: null };
};

export const resumeRun = (run, now) => ({ ...run, anchor: now, lastReminderStepMs: run.stepMs });

export const advanceRun = (run, now) => ({
    ...run, index: run.index + 1, totalMs: elapsedOf(run, now).total,
    stepMs: 0, anchor: run.index + 1 === activitiesOf(run.plan).length ? null : now,
    lastReminderStepMs: 0,
});

export const storageKey = (userId) => `action-plan-run:${userId}`;

export const restoreRun = (raw, now) => {
    try {
        const run = JSON.parse(raw);
        if (!run || run.version !== 1 || !run.plan || typeof run.plan.name !== 'string'
            || !Array.isArray(run.plan.steps) || !run.plan.steps.length
            || !run.plan.steps.every((step) => typeof step.name === 'string' && Array.isArray(step.stages) && step.stages.every((stage) => typeof stage === 'string'))
            || !Number.isInteger(run.index) || run.index < 0 || run.index > activitiesOf(run.plan).length
            || (run.index === activitiesOf(run.plan).length && (typeof run.executionId !== 'string' || !run.executionId || run.taskCompleted))
            || ![run.totalMs, run.stepMs, run.lastReminderStepMs].every((value) => Number.isFinite(value) && value >= 0)
            || (run.anchor !== null && !Number.isFinite(run.anchor))
            || !['remaining', 'elapsed'].includes(run.mode)
            || !Number.isInteger(run.plan.reminderMinutes) || run.plan.reminderMinutes < 1
            || (run.plan.estimatedMinutes !== null && (!Number.isInteger(run.plan.estimatedMinutes) || run.plan.estimatedMinutes < 1))) return null;
        return pauseRun(run, now);
    } catch {
        return null;
    }
};

export const formatTime = (milliseconds) => {
    const seconds = Math.floor(Math.max(0, milliseconds) / 1000);
    const minutes = Math.floor(seconds / 60);
    return `${minutes.toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
};
