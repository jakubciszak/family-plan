import React from 'react';
import apiClient from '../services/apiClient';

function BonusRulesManagement({ user }) {
    const [rules, setRules] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [error, setError] = React.useState(null);

    React.useEffect(() => {
        loadRules();
    }, []);

    const loadRules = async () => {
        try {
            setLoading(true);
            const data = await apiClient.get('/api/bonus-rules');
            setRules(data.rules || []);
            setError(null);
        } catch (error) {
            console.error('Error loading bonus rules:', error);
            setError('Failed to load bonus rules');
        } finally {
            setLoading(false);
        }
    };

    const handleCreateRule = async (ruleData) => {
        try {
            await apiClient.post('/api/bonus-rules', ruleData);
            setShowCreateForm(false);
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error creating rule:', error);
            setError('Failed to create bonus rule');
        }
    };

    const handleUpdateRule = async (ruleId, ruleData) => {
        try {
            await apiClient.put(`/api/bonus-rules/${ruleId}`, ruleData);
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error updating rule:', error);
            setError('Failed to update bonus rule');
        }
    };

    const handleActivateRule = async (ruleId) => {
        try {
            await apiClient.post(`/api/bonus-rules/${ruleId}/activate`, {});
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error activating rule:', error);
            setError('Failed to activate bonus rule');
        }
    };

    const handleDeactivateRule = async (ruleId) => {
        try {
            await apiClient.post(`/api/bonus-rules/${ruleId}/deactivate`, {});
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error deactivating rule:', error);
            setError('Failed to deactivate bonus rule');
        }
    };

    if (user.role !== 'ROLE_ADMIN') {
        return (
            <div className="access-denied">
                <h2>Access Denied</h2>
                <p>Only administrators can manage bonus rules.</p>
            </div>
        );
    }

    if (loading) {
        return <div className="loading">Loading bonus rules...</div>;
    }

    return (
        <div className="bonus-rules-container">
            <div className="bonus-rules-header">
                <h2>Bonus Points Rules</h2>
                <button 
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    className="btn-primary"
                >
                    {showCreateForm ? 'Cancel' : 'Create New Rule'}
                </button>
            </div>

            {error && (
                <div className="error-message">
                    {error}
                </div>
            )}

            {showCreateForm && (
                <BonusRuleForm onSubmit={handleCreateRule} />
            )}

            <div className="bonus-rules-list">
                {rules.length === 0 ? (
                    <p>No bonus rules yet. Create your first rule!</p>
                ) : (
                    rules.map(rule => (
                        <BonusRuleCard
                            key={rule.id}
                            rule={rule}
                            onUpdate={handleUpdateRule}
                            onActivate={handleActivateRule}
                            onDeactivate={handleDeactivateRule}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

function BonusRuleCard({ rule, onUpdate, onActivate, onDeactivate }) {
    const [isEditing, setIsEditing] = React.useState(false);

    const getRuleTypeLabel = (type) => {
        switch (type) {
            case 'consecutive_days':
                return 'Consecutive Days';
            case 'monthly_task_count':
                return 'Monthly Task Count';
            default:
                return type;
        }
    };

    const getRuleDescription = (rule) => {
        if (rule.type === 'consecutive_days' && rule.config.requiredDays) {
            return `Complete task ${rule.config.requiredDays} consecutive days`;
        } else if (rule.type === 'monthly_task_count' && rule.config.requiredCount) {
            return `Complete ${rule.config.requiredCount} tasks in a month`;
        }
        return rule.description;
    };

    const handleUpdate = (data) => {
        onUpdate(rule.id, data);
        setIsEditing(false);
    };

    if (isEditing) {
        return (
            <div className="bonus-rule-card editing">
                <BonusRuleForm 
                    rule={rule}
                    onSubmit={handleUpdate}
                    onCancel={() => setIsEditing(false)}
                />
            </div>
        );
    }

    return (
        <div className={`bonus-rule-card ${rule.isActive ? 'active' : 'inactive'}`}>
            <div className="rule-header">
                <h3>{rule.name}</h3>
                <div className="rule-status">
                    <span className={`status-badge ${rule.isActive ? 'status-active' : 'status-inactive'}`}>
                        {rule.isActive ? 'Active' : 'Inactive'}
                    </span>
                    <span className="rule-type-badge">
                        {getRuleTypeLabel(rule.type)}
                    </span>
                </div>
            </div>
            <div className="rule-body">
                <p className="rule-description">{rule.description}</p>
                <p className="rule-config">{getRuleDescription(rule)}</p>
                <div className="rule-points">
                    <strong>Bonus Points:</strong> <span className="points-value">{rule.bonusPoints}</span>
                </div>
            </div>
            <div className="rule-actions">
                <button
                    onClick={() => setIsEditing(true)}
                    className="btn-secondary"
                >
                    Edit
                </button>
                {rule.isActive ? (
                    <button
                        onClick={() => onDeactivate(rule.id)}
                        className="btn-warning"
                    >
                        Deactivate
                    </button>
                ) : (
                    <button
                        onClick={() => onActivate(rule.id)}
                        className="btn-success"
                    >
                        Activate
                    </button>
                )}
            </div>
        </div>
    );
}

function BonusRuleForm({ rule, onSubmit, onCancel }) {
    const isEditing = !!rule;
    
    const [formData, setFormData] = React.useState({
        name: rule?.name || '',
        description: rule?.description || '',
        bonusPoints: rule?.bonusPoints || 10,
        ruleType: rule?.type || 'consecutive_days',
        requiredDays: rule?.config?.requiredDays || 5,
        requiredCount: rule?.config?.requiredCount || 20,
        taskTemplateId: rule?.config?.taskTemplateId || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const ruleConfig = formData.ruleType === 'consecutive_days'
            ? { 
                taskTemplateId: formData.taskTemplateId || 'default-template-id', 
                requiredDays: formData.requiredDays 
              }
            : { requiredCount: formData.requiredCount };

        const submitData = isEditing
            ? {
                name: formData.name,
                description: formData.description,
                bonusPoints: formData.bonusPoints,
              }
            : {
                name: formData.name,
                description: formData.description,
                bonusPoints: formData.bonusPoints,
                ruleType: formData.ruleType,
                ruleConfig: ruleConfig,
              };

        onSubmit(submitData);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: ['bonusPoints', 'requiredDays', 'requiredCount'].includes(name) 
                ? parseInt(value, 10) 
                : value,
        }));
    };

    return (
        <form className="bonus-rule-form" onSubmit={handleSubmit}>
            <h3>{isEditing ? 'Edit Rule' : 'Create New Bonus Rule'}</h3>
            
            <div className="form-group">
                <label htmlFor="name">Rule Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value={formData.name}
                    onChange={handleChange}
                    placeholder="e.g., Dishwasher Streak Bonus"
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
                    placeholder="Describe what users need to do to earn this bonus"
                    rows="3"
                    required
                />
            </div>

            <div className="form-group">
                <label htmlFor="bonusPoints">Bonus Points</label>
                <input
                    type="number"
                    id="bonusPoints"
                    name="bonusPoints"
                    value={formData.bonusPoints}
                    onChange={handleChange}
                    min="1"
                    max="1000"
                    required
                />
            </div>

            {!isEditing && (
                <>
                    <div className="form-group">
                        <label htmlFor="ruleType">Rule Type</label>
                        <select
                            id="ruleType"
                            name="ruleType"
                            value={formData.ruleType}
                            onChange={handleChange}
                            required
                        >
                            <option value="consecutive_days">Consecutive Days</option>
                            <option value="monthly_task_count">Monthly Task Count</option>
                        </select>
                    </div>

                    {formData.ruleType === 'consecutive_days' && (
                        <div className="form-group">
                            <label htmlFor="requiredDays">Required Consecutive Days</label>
                            <input
                                type="number"
                                id="requiredDays"
                                name="requiredDays"
                                value={formData.requiredDays}
                                onChange={handleChange}
                                min="2"
                                max="365"
                                required
                            />
                            <small>Number of consecutive days the task must be completed</small>
                        </div>
                    )}

                    {formData.ruleType === 'monthly_task_count' && (
                        <div className="form-group">
                            <label htmlFor="requiredCount">Required Task Count</label>
                            <input
                                type="number"
                                id="requiredCount"
                                name="requiredCount"
                                value={formData.requiredCount}
                                onChange={handleChange}
                                min="1"
                                max="1000"
                                required
                            />
                            <small>Number of tasks to complete in a month</small>
                        </div>
                    )}
                </>
            )}

            <div className="form-actions">
                <button type="submit" className="btn-primary">
                    {isEditing ? 'Update Rule' : 'Create Rule'}
                </button>
                {onCancel && (
                    <button type="button" onClick={onCancel} className="btn-secondary">
                        Cancel
                    </button>
                )}
            </div>
        </form>
    );
}

export default BonusRulesManagement;
