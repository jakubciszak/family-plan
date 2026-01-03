import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';

function TaskList({ user }) {
    const { t } = useTranslation();
    const [tasks, setTasks] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [showCreateForm, setShowCreateForm] = React.useState(false);

    React.useEffect(() => {
        loadTasks();
    }, []);

    const loadTasks = async () => {
        try {
            const data = await apiClient.get('/api/tasks');
            setTasks(data.tasks);
        } catch (error) {
            console.error('Error loading tasks:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleCompleteTask = async (taskId) => {
        try {
            await apiClient.post(`/api/tasks/${taskId}/complete`, {
                userId: user.id,
            });
            loadTasks();
        } catch (error) {
            console.error('Error completing task:', error);
        }
    };

    const handleApproveTask = async (taskId) => {
        try {
            await apiClient.post(`/api/tasks/${taskId}/approve`, {
                adminId: user.id,
            });
            loadTasks();
        } catch (error) {
            console.error('Error approving task:', error);
        }
    };

    const handleCreateTask = async (taskData) => {
        try {
            await apiClient.post('/api/tasks', taskData);
            setShowCreateForm(false);
            loadTasks();
        } catch (error) {
            console.error('Error creating task:', error);
        }
    };

    const handleAssignTask = async (taskId, userId) => {
        try {
            await apiClient.post(`/api/tasks/${taskId}/assign`, {
                userId: userId,
            });
            loadTasks();
        } catch (error) {
            console.error('Error assigning task:', error);
        }
    };

    if (loading) {
        return <div className="loading">{t('common.loading')}</div>;
    }

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>{t('tasks.title')}</h2>
                <button 
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    className="btn-primary"
                >
                    {showCreateForm ? t('common.cancel') : t('tasks.create')}
                </button>
            </div>

            {showCreateForm && (
                <TaskCreateForm onSubmit={handleCreateTask} />
            )}

            <div className="tasks">
                {tasks.length === 0 ? (
                    <p>{t('tasks.noTasks')}</p>
                ) : (
                    tasks.map(task => (
                        <TaskCard
                            key={task.id}
                            task={task}
                            user={user}
                            onComplete={handleCompleteTask}
                            onApprove={handleApproveTask}
                            onAssign={handleAssignTask}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

function TaskCard({ task, user, onComplete, onApprove, onAssign }) {
    const { t } = useTranslation();
    const isAdmin = user.role === 'ROLE_ADMIN';
    const isAssigned = task.assignedUserId !== null;
    const isAssignedToCurrentUser = task.assignedUserId === user.id;
    const canComplete = task.status === 'pending' && isAssignedToCurrentUser;
    const canApprove = task.status === 'completed' && isAdmin;
    const canAssign = task.status === 'pending' && !isAssigned;

    return (
        <div className="task-card">
            <div className="task-header">
                <h3>{task.name}</h3>
                <span className={`task-status status-${task.status}`}>
                    {task.status}
                </span>
            </div>
            <div className="task-body">
                <p>{task.description}</p>
                <div className="task-meta">
                    <span className="task-points">{t('user.points', { points: task.points })}</span>
                    <span className="task-frequency">{t(`tasks.frequency${task.frequency.charAt(0).toUpperCase() + task.frequency.slice(1)}`)}</span>
                </div>
                {isAssigned && (
                    <div className="task-assignment">
                        <span className="assignment-label">{t('tasks.assignedTo')}:</span>
                        <span className="assignment-user">{task.assignedUserName}</span>
                    </div>
                )}
            </div>
            <div className="task-actions">
                {canAssign && (
                    <button
                        onClick={() => onAssign(task.id, user.id)}
                        className="btn-primary"
                    >
                        {t('tasks.assign')}
                    </button>
                )}
                {canComplete && (
                    <button
                        onClick={() => onComplete(task.id)}
                        className="btn-success"
                    >
                        {t('tasks.complete')}
                    </button>
                )}
                {canApprove && (
                    <button
                        onClick={() => onApprove(task.id)}
                        className="btn-primary"
                    >
                        {t('tasks.approve')}
                    </button>
                )}
            </div>
        </div>
    );
}

function TaskCreateForm({ onSubmit }) {
    const { t } = useTranslation();
    const [formData, setFormData] = React.useState({
        name: '',
        description: '',
        points: 0,
        frequency: 'once',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit(formData);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: name === 'points' ? parseInt(value, 10) : value,
        }));
    };

    return (
        <form className="task-create-form" onSubmit={handleSubmit}>
            <div className="form-group">
                <label htmlFor="name">{t('tasks.name')}</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    required
                />
            </div>
            <div className="form-group">
                <label htmlFor="description">{t('tasks.description')}</label>
                <textarea
                    id="description"
                    name="description"
                    value={formData.description}
                    onChange={handleChange}
                    rows="3"
                />
            </div>
            <div className="form-row">
                <div className="form-group">
                    <label htmlFor="points">{t('tasks.points')}</label>
                    <input
                        type="number"
                        id="points"
                        name="points"
                        value={formData.points}
                        onChange={handleChange}
                        min="0"
                        max="1000"
                        required
                    />
                </div>
                <div className="form-group">
                    <label htmlFor="frequency">{t('tasks.frequency')}</label>
                    <select
                        id="frequency"
                        name="frequency"
                        value={formData.frequency}
                        onChange={handleChange}
                        required
                    >
                        <option value="once">{t('tasks.frequencyOnce')}</option>
                        <option value="daily">{t('tasks.frequencyDaily')}</option>
                        <option value="weekly">{t('tasks.frequencyWeekly')}</option>
                        <option value="monthly">{t('tasks.frequencyMonthly')}</option>
                    </select>
                </div>
            </div>
            <button type="submit" className="btn-primary">{t('tasks.create')}</button>
        </form>
    );
}

export default TaskList;
