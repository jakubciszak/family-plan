import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import teamService from '../services/teamService';

function useLimitLabel() {
    const { t } = useTranslation();

    return (limit) => {
        if (!limit || limit.type === 'unlimited') {
            return t('taskTypes.limitUnlimited');
        }
        if (limit.type === 'once') {
            return t('taskTypes.limitOnce');
        }
        return t(`taskTypes.limit${limit.type === 'per_day' ? 'PerDay' : limit.type === 'per_week' ? 'PerWeek' : 'PerMonth'}`, {
            count: limit.count,
        });
    };
}

function TaskList() {
    const { t } = useTranslation();
    const [taskTypes, setTaskTypes] = React.useState([]);
    const [myTasks, setMyTasks] = React.useState([]);
    const [awaitingApproval, setAwaitingApproval] = React.useState([]);
    const [teams, setTeams] = React.useState([]);
    const [selectedTeam, setSelectedTeam] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const limitLabel = useLimitLabel();

    const isTeamAdmin = selectedTeam?.role === 'admin';

    const loadTeams = React.useCallback(async () => {
        try {
            const data = await teamService.getTeams();
            const loaded = data.teams || [];
            setTeams(loaded);
            setSelectedTeam((current) => current || loaded[0] || null);
        } catch {
            setTeams([]);
        }
    }, []);

    const loadTasks = React.useCallback(async () => {
        try {
            const [types, mine] = await Promise.all([
                taskService.getTaskTypes(),
                taskService.getMyTasks(),
            ]);
            setTaskTypes(types.templates || []);
            setMyTasks(mine.executions || []);
        } catch {
            setError(t('tasks.loadFailed'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    const loadAwaitingApproval = React.useCallback(async () => {
        try {
            const data = await taskService.getAwaitingApproval();
            setAwaitingApproval(data.executions || []);
        } catch {
            setAwaitingApproval([]);
        }
    }, []);

    React.useEffect(() => {
        loadTeams();
        loadTasks();
        loadAwaitingApproval();
    }, [loadTeams, loadTasks, loadAwaitingApproval]);

    const refresh = async () => {
        await Promise.all([loadTasks(), loadAwaitingApproval()]);
    };

    const run = async (action) => {
        setError(null);
        try {
            await action();
            await refresh();
        } catch (err) {
            setError(err?.response?.data?.error || t('tasks.actionFailed'));
        }
    };

    const teamOfType = (taskTemplateId) =>
        taskTypes.find((type) => type.id === taskTemplateId)?.teamId || null;

    const belongsToSelectedTeam = (teamId) => !selectedTeam || teamId === selectedTeam.id;

    const availableTypes = taskTypes.filter((type) =>
        belongsToSelectedTeam(type.teamId) && type.isActive && type.remaining !== 0
    );

    const openStatuses = ['new', 'pending'];
    const myOpenTasks = myTasks.filter(
        (task) => openStatuses.includes(task.status) && belongsToSelectedTeam(teamOfType(task.taskTemplateId))
    );
    const myFinishedTasks = myTasks.filter(
        (task) => task.status === 'completed' && belongsToSelectedTeam(teamOfType(task.taskTemplateId))
    );

    if (loading) {
        return <div className="loading">{t('common.loading')}</div>;
    }

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>{t('tasks.title')}</h2>
                {teams.length > 1 && (
                    <select
                        value={selectedTeam?.id || ''}
                        onChange={(e) => setSelectedTeam(teams.find((team) => team.id === e.target.value))}
                        className="team-selector"
                        aria-label={t('teams.title')}
                    >
                        {teams.map((team) => (
                            <option key={team.id} value={team.id}>{team.name}</option>
                        ))}
                    </select>
                )}
            </div>

            {error && <div className="error-message">{error}</div>}

            <section className="task-section" data-testid="my-tasks">
                <h3>{t('tasks.mySection')}</h3>
                {myOpenTasks.length === 0 && myFinishedTasks.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noneTaken')}</p>
                ) : (
                    <div className="tasks">
                        {myOpenTasks.map((task) => (
                            <div className="task-card" key={task.id}>
                                <div className="task-row">
                                    <span className="task-name">{task.name}</span>
                                    <span className="task-points">{t('user.points', { points: task.points })}</span>
                                </div>
                                <div className="task-actions">
                                    <button className="btn-success" onClick={() => run(() => taskService.complete(task.id))}>
                                        {t('tasks.complete')}
                                    </button>
                                    <button className="btn-secondary" onClick={() => run(() => taskService.abandon(task.id))}>
                                        {t('tasks.giveBack')}
                                    </button>
                                </div>
                            </div>
                        ))}
                        {myFinishedTasks.map((task) => (
                            <div className="task-card" key={task.id}>
                                <div className="task-row">
                                    <span className="task-name">{task.name}</span>
                                    <span className="task-points">{t('user.points', { points: task.points })}</span>
                                    <span className="task-status status-completed">{t('tasks.awaitingApproval')}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            <section className="task-section" data-testid="available-tasks">
                <h3>{t('tasks.availableSection')}</h3>
                {availableTypes.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noTasks')}</p>
                ) : (
                    <div className="tasks">
                        {availableTypes.map((type) => (
                            <div className="task-card" key={type.id}>
                                <div className="task-row">
                                    <span className="task-name" title={type.description}>{type.name}</span>
                                    <span className="task-points">{t('user.points', { points: type.points })}</span>
                                    <span className="task-frequency">{limitLabel(type.executionLimit)}</span>
                                    {type.remaining !== null && (
                                        <span className="task-remaining">{t('taskTypes.remaining', { count: type.remaining })}</span>
                                    )}
                                </div>
                                <div className="task-actions">
                                    <button className="btn-primary" onClick={() => run(() => taskService.take(type.id))}>
                                        {t('tasks.takeIt')}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {isTeamAdmin && (
                <section className="task-section" data-testid="approval-queue">
                    <h3>{t('tasks.approvalSection')}</h3>
                    {awaitingApproval.length === 0 ? (
                        <p className="empty-hint">{t('tasks.nothingToApprove')}</p>
                    ) : (
                        <div className="tasks">
                            {awaitingApproval.map((task) => (
                                <div className="task-card" key={task.id}>
                                    <div className="task-row">
                                        <span className="task-name">{task.name}</span>
                                        <span className="task-points">{t('user.points', { points: task.points })}</span>
                                        <span className="task-assignee">{task.assignedUserName}</span>
                                    </div>
                                    <div className="task-actions">
                                        <button className="btn-primary" onClick={() => run(() => taskService.approve(task.id))}>
                                            {t('tasks.approve')}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            )}
        </div>
    );
}

export default TaskList;
