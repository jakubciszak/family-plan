import React from 'react';
import apiClient from '../services/apiClient';

function TaskList({ user }) {
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

    if (loading) {
        return <div className="loading">Loading tasks...</div>;
    }

    return (
        <div className="task-list-container">
            <div className="task-list-header">
                <h2>Tasks</h2>
                <button 
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    className="btn-primary"
                >
                    {showCreateForm ? 'Cancel' : 'Create Task'}
                </button>
            </div>

            {showCreateForm && (
                <TaskCreateForm onSubmit={handleCreateTask} />
            )}

            <div className="tasks">
                {tasks.length === 0 ? (
                    <p>No tasks yet. Create your first task!</p>
                ) : (
                    tasks.map(task => (
                        <TaskCard
                            key={task.id}
                            task={task}
                            user={user}
                            onComplete={handleCompleteTask}
                            onApprove={handleApproveTask}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

function TaskCard({ task, user, onComplete, onApprove }) {
    const isAdmin = user.role === 'ROLE_ADMIN';
    const canComplete = task.status === 'pending';
    const canApprove = task.status === 'completed' && isAdmin;

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
                    <span className="task-points">{task.points} points</span>
                    <span className="task-frequency">{task.frequency}</span>
                </div>
            </div>
            <div className="task-actions">
                {canComplete && (
                    <button
                        onClick={() => onComplete(task.id)}
                        className="btn-success"
                    >
                        Complete
                    </button>
                )}
                {canApprove && (
                    <button
                        onClick={() => onApprove(task.id)}
                        className="btn-primary"
                    >
                        Approve
                    </button>
                )}
            </div>
        </div>
    );
}

function TaskCreateForm({ onSubmit }) {
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
                <label htmlFor="name">Task Name</label>
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
                <label htmlFor="description">Description</label>
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
                    <label htmlFor="points">Points</label>
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
                    <label htmlFor="frequency">Frequency</label>
                    <select
                        id="frequency"
                        name="frequency"
                        value={formData.frequency}
                        onChange={handleChange}
                        required
                    >
                        <option value="once">Once</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
            </div>
            <button type="submit" className="btn-primary">Create Task</button>
        </form>
    );
}

export default TaskList;
