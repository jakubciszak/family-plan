import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import teamService from '../services/teamService';

function BonusRulesManagement({ user }) {
    const { t } = useTranslation();
    const [rules, setRules] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [error, setError] = React.useState(null);
    const [adminTeams, setAdminTeams] = React.useState([]);
    const [teamsLoading, setTeamsLoading] = React.useState(true);

    React.useEffect(() => {
        loadRules();
        loadAdminTeams();
    }, []);

    const loadAdminTeams = async () => {
        try {
            const data = await teamService.getTeams();
            setAdminTeams((data.teams || []).filter((team) => team.role === 'admin'));
        } catch (err) {
            console.error('Error loading teams:', err);
        } finally {
            setTeamsLoading(false);
        }
    };

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

    if (loading || teamsLoading) {
        return <div className="loading">{t('common.loading')}</div>;
    }

    if (user.role !== 'ROLE_ADMIN' && adminTeams.length === 0) {
        return (
            <div className="access-denied">
                <h2>{t('bonusRules.accessDeniedTitle')}</h2>
                <p>{t('bonusRules.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <div className="loading">{t('common.loading')}</div>;
    }

    return (
        <div className="bonus-rules-container">
            <div className="bonus-rules-header">
                <h2>{t('bonusRules.title')}</h2>
                <button 
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    className="btn-primary"
                >
                    {showCreateForm ? t('common.cancel') : t('bonusRules.create')}
                </button>
            </div>

            {error && (
                <div className="error-message">
                    {error}
                </div>
            )}

            {showCreateForm && (
                <BonusRuleForm teams={adminTeams} onSubmit={handleCreateRule} />
            )}

            <div className="bonus-rules-list">
                {rules.length === 0 ? (
                    <p>{t('bonusRules.noRules')}</p>
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
    const { t } = useTranslation();
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
            return t('bonusRules.consecutiveDaysSummary', {
                days: rule.config.requiredDays,
                points: rule.config.pointsPerDay || 1,
            });
        } else if (rule.type === 'monthly_task_count' && rule.config.requiredCount) {
            return t('bonusRules.monthlyCountSummary', { count: rule.config.requiredCount });
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
                    {t('common.edit')}
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

function BonusRuleForm({ rule, teams = [], onSubmit, onCancel }) {
    const { t } = useTranslation();
    const isEditing = !!rule;
    
    const [formData, setFormData] = React.useState({
        name: rule?.name || '',
        description: rule?.description || '',
        bonusPoints: rule?.bonusPoints || 10,
        ruleType: rule?.type || 'consecutive_days',
        requiredDays: rule?.config?.requiredDays || 5,
        pointsPerDay: rule?.config?.pointsPerDay || 10,
        requiredCount: rule?.config?.requiredCount || 20,
        taskTemplateId: rule?.config?.taskTemplateId || '',
        teamId: rule?.teamId || teams[0]?.id || '',
    });

    React.useEffect(() => {
        if (!isEditing && !formData.teamId && teams.length > 0) {
            setFormData((prev) => ({ ...prev, teamId: teams[0].id }));
        }
    }, [teams, isEditing, formData.teamId]);

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const ruleConfig = formData.ruleType === 'consecutive_days'
            ? {
                requiredDays: formData.requiredDays,
                pointsPerDay: formData.pointsPerDay,
                ...(formData.taskTemplateId ? { taskTemplateId: formData.taskTemplateId } : {}),
              }
            : { requiredCount: formData.requiredCount };

        const submitData = isEditing
            ? {
                name: formData.name,
                description: formData.description,
                bonusPoints: formData.bonusPoints,
              }
            : {
                teamId: formData.teamId,
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
            [name]: ['bonusPoints', 'requiredDays', 'requiredCount', 'pointsPerDay'].includes(name) 
                ? parseInt(value, 10) 
                : value,
        }));
    };

    return (
        <form className="bonus-rule-form" onSubmit={handleSubmit}>
            <h3>{isEditing ? t('bonusRules.edit') : t('bonusRules.create')}</h3>
            
            {!isEditing && (
                <div className="form-group">
                    <label htmlFor="teamId">{t('bonusRules.team')}</label>
                    <select
                        id="teamId"
                        name="teamId"
                        value={formData.teamId}
                        onChange={handleChange}
                        required
                    >
                        {teams.length === 0 && <option value="">{t('bonusRules.noAdminTeam')}</option>}
                        {teams.map((team) => (
                            <option key={team.id} value={team.id}>{team.name}</option>
                        ))}
                    </select>
                </div>
            )}

            <div className="form-group">
                <label htmlFor="name">{t('bonusRules.name')}</label>
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
                <label htmlFor="description">{t('tasks.description')}</label>
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
                <label htmlFor="bonusPoints">{t('tasks.points')}</label>
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
                            <label htmlFor="requiredDays">{t('bonusRules.requiredDays')}</label>
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
                            <small>{t('bonusRules.requiredDaysHint')}</small>
                        </div>
                    )}

                    {formData.ruleType === 'consecutive_days' && (
                        <div className="form-group">
                            <label htmlFor="pointsPerDay">{t('bonusRules.pointsPerDay')}</label>
                            <input
                                type="number"
                                id="pointsPerDay"
                                name="pointsPerDay"
                                value={formData.pointsPerDay}
                                onChange={handleChange}
                                min="1"
                                max="1000"
                                required
                            />
                            <small>{t('bonusRules.pointsPerDayHint')}</small>
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
                    {isEditing ? t('common.save') : t('common.create')}
                </button>
                {onCancel && (
                    <button type="button" onClick={onCancel} className="btn-secondary">
                        {t('common.cancel')}
                    </button>
                )}
            </div>
        </form>
    );
}

export default BonusRulesManagement;
