import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import teamService from '../services/teamService';
import { Button, Icon, Select, TextField, CircularProgress } from '../components/md3';

const LIMIT_TYPES = ['unlimited', 'once', 'per_day', 'per_week', 'per_month'];

function TaskTypeManagement() {
    const { t } = useTranslation();
    const [taskTypes, setTaskTypes] = React.useState([]);
    const [teams, setTeams] = React.useState([]);
    const [selectedTeam, setSelectedTeam] = React.useState(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [showForm, setShowForm] = React.useState(false);

    const loadTaskTypes = React.useCallback(async () => {
        try {
            const data = await taskService.getTaskTypes();
            setTaskTypes(data.templates || []);
        } catch {
            setError(t('tasks.loadFailed'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    React.useEffect(() => {
        teamService.getTeams()
            .then((data) => {
                const administered = (data.teams || []).filter((team) => team.role === 'admin');
                setTeams(administered);
                setSelectedTeam((current) => current || administered[0] || null);
            })
            .catch(() => setTeams([]));
        loadTaskTypes();
    }, [loadTaskTypes]);

    const run = async (action) => {
        setError(null);
        try {
            await action();
            await loadTaskTypes();
        } catch (err) {
            setError(err?.response?.data?.error || t('tasks.actionFailed'));
        }
    };

    const handleCreate = async (formData) => {
        await run(() => taskService.createTaskType({
            teamId: selectedTeam.id,
            name: formData.name,
            description: formData.description,
            points: formData.points,
            frequency: formData.frequency,
            executionLimit: formData.limitType === 'unlimited' || formData.limitType === 'once'
                ? { type: formData.limitType }
                : { type: formData.limitType, count: formData.limitCount },
        }));
        setShowForm(false);
    };

    const limitLabel = (limit) => {
        if (!limit || limit.type === 'unlimited') return t('taskTypes.limitUnlimited');
        if (limit.type === 'once') return t('taskTypes.limitOnce');
        const key = limit.type === 'per_day' ? 'PerDay' : limit.type === 'per_week' ? 'PerWeek' : 'PerMonth';
        return t(`taskTypes.limit${key}`, { count: limit.count });
    };

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    if (teams.length === 0) {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <p>{t('taskTypes.adminOnly')}</p>
            </div>
        );
    }

    const visibleTypes = taskTypes.filter((type) => !selectedTeam || type.teamId === selectedTeam.id);

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>{t('taskTypes.title')}</h2>
                <div className="header-controls">
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
                    <Button
                        variant={showForm ? 'text' : 'filled'}
                        icon={showForm ? 'close' : 'add'}
                        onClick={() => setShowForm(!showForm)}
                    >
                        {showForm ? t('common.cancel') : t('taskTypes.create')}
                    </Button>
                </div>
            </div>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {showForm && <TaskTypeForm onSubmit={handleCreate} />}

            <div className="tasks">
                {visibleTypes.length === 0 ? (
                    <p className="empty-hint">{t('taskTypes.none')}</p>
                ) : (
                    visibleTypes.map((type) => (
                        <div className={`task-card${type.isActive ? '' : ' task-card--done'}`} key={type.id}>
                            <div className="task-row">
                                <span className="task-name" title={type.description}>{type.name}</span>
                                <span className="task-points">
                                    <Icon name="stars" size={16} />
                                    {t('user.points', { points: type.points })}
                                </span>
                                <span className="task-frequency">
                                    <Icon name="calendar" size={16} />
                                    {limitLabel(type.executionLimit)}
                                </span>
                                {type.remaining !== null && (
                                    <span className="task-remaining">
                                        {t('taskTypes.remaining', { count: type.remaining })}
                                    </span>
                                )}
                                {!type.isActive && (
                                    <span className="task-status status-inactive">
                                        <Icon name="block" size={16} />
                                        {t('taskTypes.inactive')}
                                    </span>
                                )}
                            </div>
                            {type.description && <p className="task-description">{type.description}</p>}
                            <div className="task-actions">
                                {type.isActive ? (
                                    <Button
                                        variant="outlined"
                                        icon="pause"
                                        onClick={() => run(() => taskService.deactivateTaskType(type.id))}
                                    >
                                        {t('taskTypes.deactivate')}
                                    </Button>
                                ) : (
                                    <Button
                                        tone="success"
                                        icon="restore"
                                        onClick={() => run(() => taskService.activateTaskType(type.id))}
                                    >
                                        {t('taskTypes.activate')}
                                    </Button>
                                )}
                                <Button
                                    variant="text"
                                    tone="danger"
                                    icon="delete"
                                    onClick={() => run(() => taskService.deleteTaskType(type.id))}
                                >
                                    {t('taskTypes.delete')}
                                </Button>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

function TaskTypeForm({ onSubmit }) {
    const { t } = useTranslation();
    const [formData, setFormData] = React.useState({
        name: '',
        description: '',
        points: 10,
        frequency: 'daily',
        limitType: 'unlimited',
        limitCount: 1,
    });

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: name === 'points' || name === 'limitCount' ? parseInt(value, 10) || 0 : value,
        }));
    };

    const needsCount = ['per_day', 'per_week', 'per_month'].includes(formData.limitType);

    return (
        <form
            className="task-create-form"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit(formData);
            }}
        >
            <TextField
                id="name"
                name="name"
                type="text"
                label={t('tasks.name')}
                value={formData.name}
                onChange={handleChange}
                required
            />
            <TextField
                as="textarea"
                id="description"
                name="description"
                label={t('tasks.description')}
                value={formData.description}
                onChange={handleChange}
                rows="2"
            />
            <div className="md-form__row">
                <TextField
                    id="points"
                    name="points"
                    type="number"
                    label={t('tasks.points')}
                    value={formData.points}
                    onChange={handleChange}
                    min="0"
                    max="1000"
                    required
                />
                <TextField
                    as="select"
                    id="frequency"
                    name="frequency"
                    label={t('tasks.frequency')}
                    value={formData.frequency}
                    onChange={handleChange}
                >
                    <option value="once">{t('tasks.frequencyOnce')}</option>
                    <option value="daily">{t('tasks.frequencyDaily')}</option>
                    <option value="weekly">{t('tasks.frequencyWeekly')}</option>
                    <option value="monthly">{t('tasks.frequencyMonthly')}</option>
                </TextField>
            </div>
            <div className="md-form__row">
                <TextField
                    as="select"
                    id="limitType"
                    name="limitType"
                    label={t('taskTypes.limit')}
                    value={formData.limitType}
                    onChange={handleChange}
                >
                    {LIMIT_TYPES.map((limitType) => (
                        <option key={limitType} value={limitType}>
                            {t(`taskTypes.limitOption.${limitType}`)}
                        </option>
                    ))}
                </TextField>
                {needsCount && (
                    <TextField
                        id="limitCount"
                        name="limitCount"
                        type="number"
                        label={t('taskTypes.limitCount')}
                        value={formData.limitCount}
                        onChange={handleChange}
                        min="1"
                        max="100"
                        required
                    />
                )}
            </div>
            <div className="form-actions">
                <Button type="submit" icon="add">{t('taskTypes.create')}</Button>
            </div>
        </form>
    );
}

export default TaskTypeManagement;
