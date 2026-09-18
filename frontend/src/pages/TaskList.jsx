import React from 'react';
import { useTranslation } from 'react-i18next';
import WeekCalendar from '../components/WeekCalendar';
import Leaderboard from '../components/Leaderboard';
import MemberWeeks from '../components/MemberWeeks';
import usePersonalisation from '../hooks/usePersonalisation';
import celebrate from '../services/celebrate';
import taskService from '../services/taskService';
import teamService from '../services/teamService';
import { Button, Chip, Dialog, Icon, Select, TextField, CircularProgress } from '../components/md3';

const BACKLOG_DAYS = 7;

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

function TaskList({ onNavigate, user, onInspectMember, onOpenPlan }) {
    const { t } = useTranslation();
    const { own } = usePersonalisation();
    const [taskTypes, setTaskTypes] = React.useState([]);
    const [myTasks, setMyTasks] = React.useState([]);
    const [awaitingApproval, setAwaitingApproval] = React.useState([]);
    const [teams, setTeams] = React.useState([]);
    const [selectedTeam, setSelectedTeam] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [refreshToken, setRefreshToken] = React.useState(0);
    const [backlogTasks, setBacklogTasks] = React.useState({});
    const [backlogTask, setBacklogTask] = React.useState(null);
    const [backlogDate, setBacklogDate] = React.useState('');
    const [members, setMembers] = React.useState([]);
    const [handOut, setHandOut] = React.useState(null);
    const [handOutMember, setHandOutMember] = React.useState('');
    const [handOutDate, setHandOutDate] = React.useState('');
    const [availableSearch, setAvailableSearch] = React.useState('');
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

    React.useEffect(() => {
        if (!selectedTeam || selectedTeam.role !== 'admin') {
            setMembers([]);
            return;
        }

        teamService.getTeamMembers(selectedTeam.id)
            .then((data) => setMembers((data.members || []).filter((member) => member.role !== 'admin')))
            .catch(() => setMembers([]));
    }, [selectedTeam, refreshToken]);

    const refresh = async () => {
        await Promise.all([loadTasks(), loadAwaitingApproval()]);
        setRefreshToken((token) => token + 1);
    };

    const run = async (action, { cheer = false } = {}) => {
        setError(null);
        try {
            await action();

            if (cheer && own?.celebrates) {
                celebrate({ withSound: own.makesSound });
            }

            await refresh();
        } catch (err) {
            setError(err?.response?.data?.error || t('tasks.actionFailed'));
        }
    };

    const dayOffset = (days) => {
        const day = new Date();
        day.setDate(day.getDate() + days);

        return day.toLocaleDateString('sv');
    };

    const backlogWindow = { earliest: dayOffset(-BACKLOG_DAYS), latest: dayOffset(0) };

    const toggleBacklog = (taskId) => setBacklogTasks((current) => ({
        ...current,
        [taskId]: !current[taskId],
    }));

    const startHandingOut = (type) => {
        setHandOut(type);
        setHandOutMember(members[0]?.userId || '');
        setHandOutDate(dayOffset(0));
    };

    const handOutTo = (booked) => run(async () => {
        await (booked
            ? taskService.bookFor(handOut.id, handOutMember, handOutDate)
            : taskService.assignTo(handOut.id, handOutMember));

        setHandOut(null);
    });

    const startCompleting = (task) => {
        if (!backlogTasks[task.id]) {
            run(() => taskService.complete(task.id), { cheer: true });

            return;
        }

        setBacklogDate(dayOffset(-1));
        setBacklogTask(task);
    };

    const completeAsBacklog = async () => {
        const task = backlogTask;
        setBacklogTask(null);

        await run(() => taskService.complete(task.id, backlogDate), { cheer: true });
        setBacklogTasks((current) => ({ ...current, [task.id]: false }));
    };

    const teamOfType = (taskTemplateId) =>
        taskTypes.find((type) => type.id === taskTemplateId)?.teamId || null;

    const belongsToSelectedTeam = (teamId) => !selectedTeam || teamId === selectedTeam.id;

    const availableTypes = taskTypes.filter((type) =>
        belongsToSelectedTeam(type.teamId) && type.isActive && type.remaining !== 0
    );

    const searchedFor = availableSearch.trim().toLowerCase();
    const matchingTypes = availableTypes.filter(
        (type) => type.name.toLowerCase().includes(searchedFor)
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

    const teamWeeks = <MemberWeeks teamId={selectedTeam?.id} refreshToken={refreshToken} />;

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
                                    {task.actionPlan && onOpenPlan && <Button variant="tonal" onClick={() => onOpenPlan(task)}>{t('actionPlans.showPlan')}</Button>}
                                    <Button
                                        tone="success"
                                        icon="check"
                                        onClick={() => startCompleting(task)}
                                    >
                                        {t('tasks.complete')}
                                    </Button>
                                    <Chip
                                        icon={backlogTasks[task.id] ? 'check' : 'calendar'}
                                        selected={!!backlogTasks[task.id]}
                                        onClick={() => toggleBacklog(task.id)}
                                    >
                                        {t('tasks.backlog')}
                                    </Chip>
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
                                    {task.actionPlan && onOpenPlan && <Button variant="tonal" onClick={() => onOpenPlan(task)}>{t('actionPlans.showPlan')}</Button>}
                                    <Button
                                        icon="check"
                                        onClick={() => run(() => taskService.complete(task.id), { cheer: true })}
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
                {availableTypes.length > 0 && (
                    <TextField
                        id="available-search"
                        className="task-section__search"
                        type="search"
                        label={t('tasks.searchAvailable')}
                        value={availableSearch}
                        onChange={(event) => setAvailableSearch(event.target.value)}
                    />
                )}
                {availableTypes.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noTasks')}</p>
                ) : matchingTypes.length === 0 ? (
                    <p className="empty-hint">{t('tasks.noMatches')}</p>
                ) : (
                    <div className="tasks">
                        {matchingTypes.map((type) => (
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
                                    {isTeamAdmin && members.length > 0 && (
                                        <Button
                                            variant="tonal"
                                            icon="person"
                                            onClick={() => startHandingOut(type)}
                                        >
                                            {t('tasks.handOut')}
                                        </Button>
                                    )}
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
                                            onClick={() => run(() => taskService.approve(task.id), { cheer: true })}
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

    const pieces = isTeamAdmin ? {
        week: teamWeeks,
        tasks: (
            <>
                {approvalQueue}
                <details className="task-section task-section--collapsible" data-testid="available-tasks-collapsed">
                    <summary>
                        <Icon name="tasks" size={20} />
                        {t('tasks.availableCollapsed')}
                    </summary>
                    {available}
                </details>
            </>
        ),
        standings: (
            <details className="task-section task-section--collapsible" data-testid="standings-collapsed">
                <summary>
                    <Icon name="stars" size={20} />
                    {t('tasks.standingsCollapsed')}
                </summary>
                {standings}
            </details>
        ),
        members: null,
    } : {
        week: myWeek,
        tasks: <>{myTasksSection}{available}</>,
        standings,
        members: teamWeeks,
    };

    const homeOrder = own?.home?.length ? own.home : ['week', 'tasks', 'standings'];

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

            <Dialog
                open={!!backlogTask}
                onClose={() => setBacklogTask(null)}
                headline={t('tasks.backlogHeadline', { name: backlogTask?.name })}
                actions={
                    <>
                        <Button variant="text" onClick={() => setBacklogTask(null)}>
                            {t('common.cancel')}
                        </Button>
                        <Button tone="success" icon="check" onClick={completeAsBacklog} disabled={!backlogDate}>
                            {t('tasks.complete')}
                        </Button>
                    </>
                }
            >
                <TextField
                    id="backlog-date"
                    type="date"
                    label={t('tasks.backlogDate')}
                    value={backlogDate}
                    onChange={(e) => setBacklogDate(e.target.value)}
                    min={backlogWindow.earliest}
                    max={backlogWindow.latest}
                    supportingText={t('tasks.backlogHint', { days: BACKLOG_DAYS })}
                />
            </Dialog>

            <Dialog
                open={!!handOut}
                onClose={() => setHandOut(null)}
                headline={t('tasks.handOutHeadline', { name: handOut?.name })}
                actions={
                    <>
                        <Button variant="text" onClick={() => setHandOut(null)}>
                            {t('common.cancel')}
                        </Button>
                        <Button variant="outlined" onClick={() => handOutTo(false)} disabled={!handOutMember}>
                            {t('tasks.handOutAssign')}
                        </Button>
                        <Button tone="success" icon="check" onClick={() => handOutTo(true)} disabled={!handOutMember || !handOutDate}>
                            {t('tasks.handOutBook')}
                        </Button>
                    </>
                }
            >
                <TextField
                    id="hand-out-member"
                    as="select"
                    label={t('tasks.handOutMember')}
                    value={handOutMember}
                    onChange={(e) => setHandOutMember(e.target.value)}
                >
                    {members.map((member) => (
                        <option key={member.userId} value={member.userId}>{member.userName}</option>
                    ))}
                </TextField>

                <TextField
                    id="hand-out-date"
                    type="date"
                    label={t('tasks.handOutDate')}
                    value={handOutDate}
                    onChange={(e) => setHandOutDate(e.target.value)}
                    min={backlogWindow.earliest}
                    max={backlogWindow.latest}
                    supportingText={t('tasks.handOutHint')}
                />
            </Dialog>

            {homeOrder.map((place) => (
                <React.Fragment key={place}>{pieces[place]}</React.Fragment>
            ))}
        </div>
    );
}

export default TaskList;
