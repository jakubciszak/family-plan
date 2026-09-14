import React from 'react';
import { useTranslation } from 'react-i18next';
import WeekCalendar from '../components/WeekCalendar';
import Leaderboard from '../components/Leaderboard';
import taskService from '../services/taskService';
import teamService from '../services/teamService';
import { Button, Icon, Select, CircularProgress } from '../components/md3';

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

function TaskList({ onNavigate, user, onInspectMember }) {
    const { t } = useTranslation();
    const [taskTypes, setTaskTypes] = React.useState([]);
    const [myTasks, setMyTasks] = React.useState([]);
    const [awaitingApproval, setAwaitingApproval] = React.useState([]);
    const [teams, setTeams] = React.useState([]);
    const [selectedTeam, setSelectedTeam] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [refreshToken, setRefreshToken] = React.useState(0);
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
        setRefreshToken((token) => token + 1);
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
    const myRejectedTasks = myTasks.filter(
        (task) => task.status === 'rejected' && belongsToSelectedTeam(teamOfType(task.taskTemplateId))
    );

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    const points = (value) => (
        <span className="task-points">
            <Icon name="stars" size={16} />
            {t('user.points', { points: value })}
        </span>
    );

    const myWeek = <WeekCalendar refreshToken={refreshToken} />;

    const standings = (
        <Leaderboard
            teamId={selectedTeam?.id}
            currentUserId={user?.id}
            refreshToken={refreshToken}
            onSelectMember={isTeamAdmin ? onInspectMember : undefined}
        />
    );

    const myTasksSection = (
        <section className="task-section" data-testid="my-tasks">
                <h3><Icon name="person" size={20} />{t('tasks.mySection')}</h3>
                {myOpenTasks.length === 0 && myFinishedTasks.length === 0 && myRejectedTasks.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noneTaken')}</p>
                ) : (
                    <div className="tasks">
                        {myOpenTasks.map((task) => (
                            <div className="task-card" key={task.id}>
                                <div className="task-row">
                                    <span className="task-name">{task.name}</span>
                                    {points(task.points)}
                                </div>
                                <div className="task-actions">
                                    <Button
                                        tone="success"
                                        icon="check"
                                        onClick={() => run(() => taskService.complete(task.id))}
                                    >
                                        {t('tasks.complete')}
                                    </Button>
                                    <Button
                                        variant="outlined"
                                        icon="undo"
                                        onClick={() => run(() => taskService.abandon(task.id))}
                                    >
                                        {t('tasks.giveBack')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {myRejectedTasks.map((task) => (
                            <div className="task-card task-card--rejected" key={task.id}>
                                <div className="task-row">
                                    <span className="task-name">{task.name}</span>
                                    {points(task.points)}
                                    <span className="task-status status-rejected">
                                        <Icon name="error" size={16} />
                                        {t('tasks.status.rejected', 'rejected')}
                                    </span>
                                </div>
                                {task.rejectionReason && (
                                    <p className="task-rejection-reason">
                                        {t('tasks.rejectedReason', { reason: task.rejectionReason })}
                                    </p>
                                )}
                                <div className="task-actions">
                                    <Button
                                        icon="check"
                                        onClick={() => run(() => taskService.complete(task.id))}
                                    >
                                        {t('tasks.submitAgain')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {myFinishedTasks.map((task) => (
                            <div className="task-card task-card--done" key={task.id}>
                                <div className="task-row">
                                    <span className="task-name">{task.name}</span>
                                    {points(task.points)}
                                    <span className="task-status status-completed">
                                        <Icon name="schedule" size={16} />
                                        {t('tasks.awaitingApproval')}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>
    );

    const available = (
        <section className="task-section" data-testid="available-tasks">
                <div className="task-section__header">
                    <h3><Icon name="tasks" size={20} />{t('tasks.availableSection')}</h3>
                    {isTeamAdmin && onNavigate && (
                        <Button
                            variant="tonal"
                            icon="add"
                            onClick={() => onNavigate('task-types')}
                        >
                            {t('taskTypes.create')}
                        </Button>
                    )}
                </div>
                {availableTypes.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noTasks')}</p>
                ) : (
                    <div className="tasks">
                        {availableTypes.map((type) => (
                            <div className="task-card" key={type.id}>
                                <div className="task-row">
                                    <span className="task-name" title={type.description}>{type.name}</span>
                                    {points(type.points)}
                                    <span className="task-frequency">
                                        <Icon name="calendar" size={16} />
                                        {limitLabel(type.executionLimit)}
                                    </span>
                                    {type.remaining !== null && (
                                        <span className="task-remaining">
                                            {t('taskTypes.remaining', { count: type.remaining })}
                                        </span>
                                    )}
                                </div>
                                {type.description && <p className="task-description">{type.description}</p>}
                                <div className="task-actions">
                                    <Button icon="add" onClick={() => run(() => taskService.take(type.id))}>
                                        {t('tasks.takeIt')}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </section>
    );

    const approvalQueue = (
        <section className="task-section" data-testid="approval-queue">
                    <h3><Icon name="approve" size={20} />{t('tasks.approvalSection')}</h3>
                    {awaitingApproval.length === 0 ? (
                        <p className="empty-hint">{t('tasks.nothingToApprove')}</p>
                    ) : (
                        <div className="tasks">
                            {awaitingApproval.map((task) => (
                                <div className="task-card" key={task.id}>
                                    <div className="task-row">
                                        <span className="task-name">{task.name}</span>
                                        {points(task.points)}
                                        <span className="task-assignee">
                                            <Icon name="person" size={16} />
                                            {task.assignedUserName}
                                        </span>
                                    </div>
                                    <div className="task-actions">
                                        <Button
                                            icon="approve"
                                            onClick={() => run(() => taskService.approve(task.id))}
                                        >
                                            {t('tasks.approve')}
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
    );

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>{t('tasks.title')}</h2>
                {teams.length > 1 && (
                    <Select
                        id="team-selector"
                        value={selectedTeam?.id || ''}
                        onChange={(e) => setSelectedTeam(teams.find((team) => team.id === e.target.value))}
                        triggerClassName="team-selector"
                        ariaLabel={t('teams.title')}
                    >
                        {teams.map((team) => (
                            <option key={team.id} value={team.id}>{team.name}</option>
                        ))}
                    </Select>
                )}
            </div>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {isTeamAdmin ? (
                <>
                    {standings}
                    {approvalQueue}
                    <details className="task-section task-section--collapsible" data-testid="available-tasks-collapsed">
                        <summary>
                            <Icon name="tasks" size={20} />
                            {t('tasks.availableCollapsed')}
                        </summary>
                        {available}
                    </details>
                </>
            ) : (
                <>
                    {myWeek}
                    {myTasksSection}
                    {available}
                    {standings}
                </>
            )}
        </div>
    );
}

export default TaskList;
