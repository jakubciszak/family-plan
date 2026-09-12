import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import teamService from '../services/teamService';

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
        return <div className="loading">{t('common.loading')}</div>;
    }

    if (teams.length === 0) {
        return <div className="task-list-container"><p className="empty-hint">{t('taskTypes.adminOnly')}</p></div>;
    }

    const visibleTypes = taskTypes.filter((type) => !selectedTeam || type.teamId === selectedTeam.id);

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>{t('taskTypes.title')}</h2>
                <div className="header-controls">
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
                    <button className="btn-primary" onClick={() => setShowForm(!showForm)}>
                        {showForm ? t('common.cancel') : t('taskTypes.create')}
                    </button>
                </div>
            </div>

            {error && <div className="error-message">{error}</div>}

            {showForm && <TaskTypeForm onSubmit={handleCreate} />}

            <div className="tasks">
                {visibleTypes.length === 0 ? (
                    <p className="empty-hint">{t('taskTypes.none')}</p>
                ) : (
                    visibleTypes.map((type) => (
                        <div className="task-card" key={type.id}>
                            <div className="task-row">
                                <span className="task-name" title={type.description}>{type.name}</span>
                                <span className="task-points">{t('user.points', { points: type.points })}</span>
                                <span className="task-frequency">{limitLabel(type.executionLimit)}</span>
                                {type.remaining !== null && (
                                    <span className="task-remaining">{t('taskTypes.remaining', { count: type.remaining })}</span>
                                )}
                                {!type.isActive && <span className="task-status status-inactive">{t('taskTypes.inactive')}</span>}
                            </div>
                            <div className="task-actions">
                                {type.isActive ? (
                                    <button className="btn-secondary" onClick={() => run(() => taskService.deactivateTaskType(type.id))}>
                                        {t('taskTypes.deactivate')}
                                    </button>
                                ) : (
                                    <button className="btn-success" onClick={() => run(() => taskService.activateTaskType(type.id))}>
                                        {t('taskTypes.activate')}
                                    </button>
                                )}
                                <button className="btn-danger" onClick={() => run(() => taskService.deleteTaskType(type.id))}>
                                    {t('taskTypes.delete')}
                                </button>
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
            <div className="form-group">
                <label htmlFor="name">{t('tasks.name')}</label>
                <input type="text" id="name" name="name" value={formData.name} onChange={handleChange} required />
            </div>
            <div className="form-group">
                <label htmlFor="description">{t('tasks.description')}</label>
                <textarea id="description" name="description" value={formData.description} onChange={handleChange} rows="2" />
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label htmlFor="points">{t('tasks.points')}</label>
                    <input type="number" id="points" name="points" value={formData.points} onChange={handleChange} min="0" max="1000" required />
                </div>
                <div className="form-group">
                    <label htmlFor="frequency">{t('tasks.frequency')}</label>
                    <select id="frequency" name="frequency" value={formData.frequency} onChange={handleChange}>
                        <option value="once">{t('tasks.frequencyOnce')}</option>
                        <option value="daily">{t('tasks.frequencyDaily')}</option>
                        <option value="weekly">{t('tasks.frequencyWeekly')}</option>
                        <option value="monthly">{t('tasks.frequencyMonthly')}</option>
                    </select>
                </div>
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label htmlFor="limitType">{t('taskTypes.limit')}</label>
                    <select id="limitType" name="limitType" value={formData.limitType} onChange={handleChange}>
                        {LIMIT_TYPES.map((limitType) => (
                            <option key={limitType} value={limitType}>
                                {t(`taskTypes.limitOption.${limitType}`)}
                            </option>
                        ))}
                    </select>
                </div>
                {needsCount && (
                    <div className="form-group">
                        <label htmlFor="limitCount">{t('taskTypes.limitCount')}</label>
                        <input type="number" id="limitCount" name="limitCount" value={formData.limitCount} onChange={handleChange} min="1" max="100" required />
                    </div>
                )}
            </div>
            <button type="submit" className="btn-primary">{t('taskTypes.create')}</button>
        </form>
    );
}

export default TaskTypeManagement;
