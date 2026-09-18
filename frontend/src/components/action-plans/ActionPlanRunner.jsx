import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../md3';
import ReminderSoundPicker from './ReminderSoundPicker';
import { playReminderSound } from '../../services/actionPlanSounds';
import { activitiesOf, advanceRun, elapsedOf, formatTime, pauseRun, reminderInterval, resumeRun } from '../../services/actionPlanRun';

export default function ActionPlanRunner({ audioContext, run, onChange, onLeave, onFinish, completingTask, completionError, onRetryCompletion }) {
    const { t } = useTranslation();
    const [now, setNow] = useState(Date.now);
    const [reminder, setReminder] = useState(false);
    const [soundUnavailable, setSoundUnavailable] = useState(false);
    const audio = useRef(audioContext);
    const heading = useRef(null);
    const activities = activitiesOf(run.plan);
    const completed = run.index >= activities.length;
    const current = activities[run.index];
    const elapsed = elapsedOf(run, now);
    const paused = run.anchor === null;
    const estimated = run.plan.estimatedMinutes ? run.plan.estimatedMinutes * 60000 : null;
    const remaining = estimated !== null && run.mode === 'remaining';
    const stepBudget = estimated === null ? null : estimated / activities.length;

    const enableAudio = async () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) throw new Error();
            if (!audio.current) audio.current = new AudioContext();
            await audio.current.resume();
            setSoundUnavailable(audio.current.state !== 'running');
        } catch {
            setSoundUnavailable(true);
        }
    };

    const playReminder = () => {
        if (!run.sound) return;
        const context = audio.current;
        if (!context || context.state !== 'running') {
            setSoundUnavailable(true);
            return;
        }
        try {
            playReminderSound(context, run.reminderSound ?? run.plan.reminderSound);
        } catch {
            setSoundUnavailable(true);
        }
    };

    useEffect(() => {
        if (run.sound) enableAudio();
        return () => { audio.current?.close().catch(() => {}); };
    }, []);

    useEffect(() => { heading.current?.focus(); }, [run.index]);

    useEffect(() => {
        if (paused || completed) return undefined;
        const tick = () => {
            const time = Date.now();
            setNow(time);
            const step = elapsedOf(run, time).step;
            const interval = reminder ? 15000 : reminderInterval(run.plan);
            if (step - run.lastReminderStepMs >= interval) {
                setReminder(true);
                playReminder();
                onChange({ ...run, lastReminderStepMs: step });
            }
        };
        const timer = window.setInterval(tick, 1000);
        return () => window.clearInterval(timer);
    }, [run, paused, completed, reminder]);

    const update = (next) => {
        setNow(Date.now());
        setReminder(false);
        onChange(next);
    };

    if (completed) return <section className="action-plans__runner action-plans__complete">
        <p>{run.plan.name}</p>
        <h2 tabIndex={-1} ref={heading}>{t('actionPlans.completed')}</h2>
        <p>{t('actionPlans.completedHint')}</p>
        <p>{t('actionPlans.totalElapsed')}: <strong>{formatTime(elapsed.total)}</strong></p>
        {run.executionId && <div aria-live="polite">
            <p>{t(run.taskCompleted ? 'actionPlans.taskCompleted' : 'actionPlans.taskCompletionPending')}</p>
            {completionError && <p role="alert" className="action-plans__error">{completionError}</p>}
            {completionError && <Button onClick={onRetryCompletion} loading={completingTask}>{t('actionPlans.retry')}</Button>}
        </div>}
        <Button onClick={onFinish} disabled={!!run.executionId && !run.taskCompleted}>{t(run.executionId ? 'actionPlans.backToTasks' : 'actionPlans.backToPlans')}</Button>
        {completionError && <Button variant="text" onClick={() => onLeave(run)}>{t('actionPlans.saveAndLeave')}</Button>}
    </section>;

    return <section className="action-plans__runner" aria-label={run.plan.name}>
        <div className="action-plans__runner-head">
            <span>{run.plan.name}</span>
            <Button variant="text" onClick={() => onLeave(pauseRun(run, Date.now()))}>{t('actionPlans.saveAndLeave')}</Button>
        </div>
        <p className="action-plans__hint">{t('actionPlans.progress', { current: run.index + 1, total: activities.length })}</p>
        <progress value={run.index} max={activities.length} aria-label={t('actionPlans.progressLabel')} />
        <div className="action-plans__current" aria-live="polite" aria-atomic="true">
            {current.parent && <p className="action-plans__parent">{current.parent}</p>}
            <h2 tabIndex={-1} ref={heading}>{current.name}</h2>
            {paused && <p>{t('actionPlans.paused')}</p>}
        </div>
        <div className="action-plans__clocks">
            <div><span>{t(remaining ? 'actionPlans.stepRemaining' : 'actionPlans.stepElapsed')}</span><strong data-testid="step-clock">{formatTime(remaining ? stepBudget - elapsed.step : elapsed.step)}</strong></div>
            <div><span>{t(remaining ? 'actionPlans.totalRemaining' : 'actionPlans.totalElapsed')}</span><strong data-testid="total-clock">{formatTime(remaining ? estimated - elapsed.total : elapsed.total)}</strong></div>
        </div>
        {remaining && (elapsed.step >= stepBudget || elapsed.total >= estimated) && <p className="action-plans__hint">{t('actionPlans.timeIsEstimate')}</p>}
        {estimated !== null && <Button variant="text" onClick={() => onChange({ ...run, mode: remaining ? 'elapsed' : 'remaining' })}>{t(remaining ? 'actionPlans.showElapsed' : 'actionPlans.showRemaining')}</Button>}
        {reminder && !paused && <div className="action-plans__reminder" role="status">
            <p>{t('actionPlans.reminder')}</p>
            <Button variant="text" onClick={() => update({ ...run, lastReminderStepMs: elapsedOf(run, Date.now()).step })}>{t('actionPlans.stillWorking')}</Button>
        </div>}
        <div className="action-plans__controls">
            {paused
                ? <Button onClick={() => { if (run.sound) enableAudio(); update(resumeRun(run, Date.now())); }}>{t('actionPlans.resume')}</Button>
                : <>
                    <Button className="action-plans__next" onClick={() => update(advanceRun(run, Date.now()))}>{t(run.index === activities.length - 1 ? 'actionPlans.finish' : 'actionPlans.next')}</Button>
                    <Button variant="text" onClick={() => update(pauseRun(run, Date.now()))}>{t('actionPlans.pause')}</Button>
                </>}
        </div>
        <label className="action-plans__sound"><input type="checkbox" checked={run.sound} onChange={(event) => { if (event.target.checked) enableAudio(); onChange({ ...run, sound: event.target.checked }); }} />{t('actionPlans.sound')}</label>
        {run.sound && <ReminderSoundPicker value={run.reminderSound ?? run.plan.reminderSound} onChange={(reminderSound) => onChange({ ...run, reminderSound })} />}
        {soundUnavailable && run.sound && <div role="status"><p>{t('actionPlans.soundUnavailable')}</p><Button variant="text" onClick={enableAudio}>{t('actionPlans.enableSound')}</Button></div>}
        <p className="action-plans__hint action-plans__browser-note">{t('actionPlans.keepOpen')}</p>
    </section>;
}
