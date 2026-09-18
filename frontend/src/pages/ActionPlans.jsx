import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Autocomplete, Button } from '../components/md3';
import ActionPlanEditor from '../components/action-plans/ActionPlanEditor';
import ActionPlanRunner from '../components/action-plans/ActionPlanRunner';
import actionPlanService from '../services/actionPlanService';
import teamService from '../services/teamService';
import taskService from '../services/taskService';
import { activitiesOf, restoreRun, startRun, storageKey } from '../services/actionPlanRun';
import '../styles/action-plans.css';

export default function ActionPlans({ user, onFocusChange, taskPlan, onConsumeTaskPlan, onTaskCompleted, onBackToTasks }) {
    const { t } = useTranslation();
    const [plans, setPlans] = useState([]);
    const [teams, setTeams] = useState([]);
    const [teamError, setTeamError] = useState(false);
    const [scope, setScope] = useState('all');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState(undefined);
    const [saving, setSaving] = useState(false);
    const [storageFailed, setStorageFailed] = useState(false);
    const [completionError, setCompletionError] = useState('');
    const [completingTask, setCompletingTask] = useState(false);
    const completing = useRef(false);
    const [run, setRun] = useState(() => {
        try { return restoreRun(localStorage.getItem(storageKey(user.id)), Date.now()); } catch { return null; }
    });
    const [running, setRunning] = useState(false);
    const audio = useRef(null);

    const openRunner = (sound) => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            audio.current = sound && AudioContext ? new AudioContext() : null;
            audio.current?.resume().catch(() => {});
        } catch {
            audio.current = null;
        }
        setRunning(true);
    };

    const loadTeams = useCallback(async () => {
        try {
            setTeams((await teamService.getTeams()).teams || []);
            setTeamError(false);
        } catch { setTeamError(true); }
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        setError('');
        try { setPlans((await actionPlanService.list()).plans); }
        catch { setError(t('actionPlans.loadError')); }
        finally { setLoading(false); }
    }, [t]);

    useEffect(() => { load(); loadTeams(); }, [load, loadTeams]);
    useEffect(() => {
        onFocusChange(running);
        return () => onFocusChange(false);
    }, [running, onFocusChange]);
    useEffect(() => {
        try {
            if (run && (run.index < activitiesOf(run.plan).length || (run.executionId && !run.taskCompleted))) {
                localStorage.setItem(storageKey(user.id), JSON.stringify(run));
            } else {
                localStorage.removeItem(storageKey(user.id));
            }
        } catch { setStorageFailed(true); }
    }, [run, user.id]);

    const completeTask = async () => {
        if (completing.current || !run?.executionId || run.taskCompleted) return;
        completing.current = true;
        setCompletingTask(true);
        setCompletionError('');
        try {
            await taskService.complete(run.executionId);
            setRun((current) => current?.executionId === run.executionId ? { ...current, taskCompleted: true } : current);
            onTaskCompleted?.();
        } catch {
            setCompletionError(t('actionPlans.taskCompletionError'));
        } finally {
            completing.current = false;
            setCompletingTask(false);
        }
    };

    useEffect(() => {
        if (running && run?.executionId && !run.taskCompleted && run.index === activitiesOf(run.plan).length) completeTask();
    }, [running, run?.index, run?.executionId, run?.taskCompleted]);

    const save = async (draft) => {
        setSaving(true);
        setError('');
        try {
            const saved = await actionPlanService.save(draft);
            setPlans((current) => [...current.filter((plan) => plan.id !== saved.id), saved]);
            setScope(saved.teamId || 'private');
            setEditing(undefined);
        } catch (failure) {
            const key = failure.response?.data?.error;
            setError(typeof key === 'string' && key.startsWith('actionPlans.') ? t(key) : t('actionPlans.saveError'));
        } finally { setSaving(false); }
    };

    const remove = async (plan) => {
        if (!window.confirm(t('actionPlans.confirmDelete', { name: plan.name }))) return;
        setSaving(true);
        setError('');
        try {
            await actionPlanService.remove(plan.id);
            setPlans((current) => current.filter((item) => item.id !== plan.id));
        } catch (failure) {
            const key = failure.response?.data?.error;
            setError(typeof key === 'string' && key.startsWith('actionPlans.') ? t(key) : t('actionPlans.deleteError'));
        } finally { setSaving(false); }
    };

    const begin = (plan, task = null) => {
        if (task && run?.executionId === task.id) {
            onConsumeTaskPlan?.();
            openRunner(run.sound);
            return;
        }
        if (run && !window.confirm(t('actionPlans.confirmRestart'))) return;
        setCompletionError('');
        setRun({ ...startRun(plan, Date.now()), executionId: task?.id || null, executionName: task?.name || null });
        onConsumeTaskPlan?.();
        openRunner(true);
    };

    const finish = () => {
        const linked = !!run?.executionId;
        setRun(null);
        setRunning(false);
        if (linked) onBackToTasks?.();
    };

    const planCard = (plan, task = null) => <article className="action-plans__card" key={task?.id || plan.id}>
        <h3>{plan.name}</h3>
        <p className="action-plans__hint">{task ? t('actionPlans.forTask', { name: task.name }) : plan.teamId ? t('actionPlans.teamPlan', { name: teams.find((team) => team.id === plan.teamId)?.name || t('teams.title') }) : t('actionPlans.private')}</p>
        <p className="action-plans__hint">{t('actionPlans.activityCount', { count: activitiesOf(plan).length })} · {plan.estimatedMinutes ? t('actionPlans.minutes', { count: plan.estimatedMinutes }) : t('actionPlans.noTimeLimit')}</p>
        <details><summary>{t('actionPlans.preview')}</summary><ol>{plan.steps.map((step, index) => <li key={index}>{step.name}{step.stages.length > 0 && <ol>{step.stages.map((stage, stageIndex) => <li key={stageIndex}>{stage}</li>)}</ol>}</li>)}</ol></details>
        {task && <p>{t('actionPlans.completesTaskHint')}</p>}
        <div className="action-plans__actions">
            <Button disabled={saving} onClick={() => begin(plan, task)}>{t('actionPlans.start')}</Button>
            {!task && plan.canManage !== false && <>
                <Button variant="text" disabled={saving} onClick={() => { setEditing(plan); setError(''); }}>{t('actionPlans.edit')}</Button>
                <Button variant="text" disabled={saving} onClick={() => remove(plan)}>{t('common.delete')}</Button>
            </>}
        </div>
    </article>;

    const visiblePlans = plans.filter((plan) => scope === 'all' || (scope === 'private' ? !plan.teamId : plan.teamId === scope));

    return <div className="action-plans">
        {storageFailed && <p role="alert">{t('actionPlans.storageError')}</p>}
        {running && run
            ? <ActionPlanRunner audioContext={audio.current} run={run} onChange={setRun}
                onLeave={(paused) => { setRun(paused); setRunning(false); }} onFinish={finish}
                completingTask={completingTask} completionError={completionError} onRetryCompletion={completeTask} />
            : <>
                {teamError && <div role="alert"><p>{t('actionPlans.teamLoadError')}</p><Button variant="text" onClick={loadTeams}>{t('actionPlans.retry')}</Button></div>}
                {editing !== undefined
                    ? <ActionPlanEditor plan={editing} teams={teams} onSave={save} saving={saving} error={error} onCancel={() => { setEditing(undefined); setError(''); }} />
                    : <>
                        <div className="action-plans__head"><div><h2>{t('actionPlans.title')}</h2><p className="action-plans__hint">{t('actionPlans.intro')}</p></div><Button onClick={() => { setEditing(null); setError(''); }}>{t('actionPlans.create')}</Button></div>
                        {taskPlan?.actionPlan && <section className="action-plans__task-preview">
                            {planCard(taskPlan.actionPlan, taskPlan)}
                            <Button variant="text" onClick={onBackToTasks}>{t('actionPlans.backToTasks')}</Button>
                        </section>}
                        {run && <section className="action-plans__resume">
                            <div><h3>{run.plan.name}</h3><p>{run.index === activitiesOf(run.plan).length ? t('actionPlans.taskCompletionPending') : t('actionPlans.savedProgress', { current: run.index + 1, total: activitiesOf(run.plan).length })}</p>
                                {run.executionName && <p>{t('actionPlans.forTask', { name: run.executionName })}</p>}
                            </div>
                            <div className="action-plans__actions"><Button onClick={() => openRunner(run.sound)}>{t('actionPlans.returnToRun')}</Button><Button variant="text" onClick={() => { if (window.confirm(t('actionPlans.confirmDiscard'))) setRun(null); }}>{t('actionPlans.discard')}</Button></div>
                        </section>}
                        <div className="action-plans__filter">
                            <Autocomplete id="action-plan-scope" label={t('actionPlans.filter')} value={scope} onChange={setScope}
                                placeholder={t('actionPlans.searchScope')} emptyText={t('actionPlans.noMatchingScopes')}
                                options={[
                                    { value: 'all', label: t('actionPlans.allPlans') },
                                    { value: 'private', label: t('actionPlans.private') },
                                    ...teams.map((team) => ({ value: team.id, label: team.name })),
                                ]} />
                        </div>
                        {error && <div role="alert" className="action-plans__error"><p>{error}</p><Button variant="text" onClick={load}>{t('actionPlans.retry')}</Button></div>}
                        {loading ? <p role="status">{t('common.loading')}</p> : <>
                            {!visiblePlans.length && !error && <div className="action-plans__empty"><h3>{t(plans.length ? 'actionPlans.emptyScope' : 'actionPlans.empty')}</h3><p>{t('actionPlans.emptyHint')}</p></div>}
                            <div className="action-plans__list">{visiblePlans.map((plan) => planCard(plan))}</div>
                        </>}
                    </>}
            </>}
    </div>;
}
