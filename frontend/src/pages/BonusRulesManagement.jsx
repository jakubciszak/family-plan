import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import teamService from '../services/teamService';
import { Button, Chip, Icon, TextField, CircularProgress } from '../components/md3';

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
        return <CircularProgress label={t('common.loading')} />;
    }

    if (user.role !== 'ROLE_ADMIN' && adminTeams.length === 0) {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('bonusRules.accessDeniedTitle')}</h2>
                <p>{t('bonusRules.accessDeniedBody')}</p>
            </div>
        );
    }

    return (
        <div className="bonus-rules-container">
            <div className="bonus-rules-header">
                <h2>{t('bonusRules.title')}</h2>
                <Button
                    variant={showCreateForm ? 'text' : 'filled'}
                    icon={showCreateForm ? 'close' : 'add'}
                    onClick={() => setShowCreateForm(!showCreateForm)}
                >
                    {showCreateForm ? t('common.cancel') : t('bonusRules.create')}
                </Button>
            </div>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
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

    const accountLabels = (accounts) => (accounts?.length
        ? accounts.map((kind) => t(`bonusRules.accountNames.${kind}`, { defaultValue: kind })).join(', ')
        : t('bonusRules.allAccounts'));

    const getRuleTypeLabel = (type) => t(`bonusRules.ruleTypes.${type}`, { defaultValue: type });

    const getRuleDescription = (rule) => {
        if (rule.type === 'consecutive_days' && rule.config.requiredDays) {
            return t('bonusRules.consecutiveDaysSummary', {
                days: rule.config.requiredDays,
                points: rule.config.pointsPerDay || 1,
                accounts: accountLabels(rule.config.accounts),
            });
        } else if (rule.type === 'monthly_task_count' && rule.config.requiredCount) {
            return t('bonusRules.monthlyCountSummary', { count: rule.config.requiredCount });
        } else if (rule.type === 'weekly_points_sum' && rule.config.requiredPoints) {
            return t('bonusRules.weeklySumSummary', {
                points: rule.config.requiredPoints,
                accounts: accountLabels(rule.config.accounts),
            });
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
                        <Icon name={rule.isActive ? 'checkCircle' : 'block'} size={16} />
                        {rule.isActive ? t('common.active') : t('common.inactive')}
                    </span>
                    <span className="rule-type-badge">
                        <Icon name="rule" size={16} />
                        {getRuleTypeLabel(rule.type)}
                    </span>
                </div>
            </div>
            <div className="rule-body">
                <p className="rule-description">{rule.description}</p>
                <p className="rule-config">{getRuleDescription(rule)}</p>
                <div className="rule-points">
                    <Icon name="trophy" size={18} />
                    <strong>{t('bonusRules.bonusPoints')}:</strong> <span className="points-value">{rule.bonusPoints}</span>
                </div>
            </div>
            <div className="rule-actions">
                <Button variant="outlined" icon="edit" onClick={() => setIsEditing(true)}>
                    {t('common.edit')}
                </Button>
                {rule.isActive ? (
                    <Button variant="text" icon="pause" onClick={() => onDeactivate(rule.id)}>
                        {t('common.deactivate')}
                    </Button>
                ) : (
                    <Button tone="success" icon="restore" onClick={() => onActivate(rule.id)}>
                        {t('common.activate')}
                    </Button>
                )}
            </div>
        </div>
    );
}

function BonusRuleForm({ rule, teams = [], onSubmit, onCancel }) {
    const { t } = useTranslation();
    const isEditing = !!rule;
    const [accountKinds, setAccountKinds] = React.useState([]);

    React.useEffect(() => {
        apiClient.get('/api/points/accounts')
            .then((data) => setAccountKinds((data.accounts || []).map((a) => a.kind)))
            .catch(() => setAccountKinds([]));
    }, []);

    const [formData, setFormData] = React.useState({
        name: rule?.name || '',
        description: rule?.description || '',
        bonusPoints: rule?.bonusPoints || 10,
        ruleType: rule?.type || 'consecutive_days',
        requiredDays: rule?.config?.requiredDays || 5,
        pointsPerDay: rule?.config?.pointsPerDay || 10,
        requiredCount: rule?.config?.requiredCount || 20,
        requiredPoints: rule?.config?.requiredPoints || 100,
        accounts: rule?.config?.accounts || ['tasks'],
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
                accounts: formData.accounts,
                ...(formData.taskTemplateId ? { taskTemplateId: formData.taskTemplateId } : {}),
              }
            : formData.ruleType === 'weekly_points_sum'
                ? { requiredPoints: formData.requiredPoints, accounts: formData.accounts }
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

    const toggleAccount = (kind) => {
        setFormData((prev) => ({
            ...prev,
            accounts: prev.accounts.includes(kind)
                ? prev.accounts.filter((a) => a !== kind)
                : [...prev.accounts, kind],
        }));
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: ['bonusPoints', 'requiredDays', 'requiredCount', 'pointsPerDay', 'requiredPoints'].includes(name)
                ? parseInt(value, 10)
                : value,
        }));
    };

    return (
        <form className="bonus-rule-form" onSubmit={handleSubmit}>
            <h3>{isEditing ? t('bonusRules.edit') : t('bonusRules.create')}</h3>

            {!isEditing && (
                <TextField
                    as="select"
                    id="teamId"
                    name="teamId"
                    label={t('bonusRules.team')}
                    value={formData.teamId}
                    onChange={handleChange}
                    required
                >
                    {teams.length === 0 && <option value="">{t('bonusRules.noAdminTeam')}</option>}
                    {teams.map((team) => (
                        <option key={team.id} value={team.id}>{team.name}</option>
                    ))}
                </TextField>
            )}

            <TextField
                id="name"
                name="name"
                type="text"
                label={t('bonusRules.name')}
                value={formData.name}
                onChange={handleChange}
                placeholder={t('bonusRules.namePlaceholder')}
                required
            />

            <TextField
                as="textarea"
                id="description"
                name="description"
                label={t('tasks.description')}
                value={formData.description}
                onChange={handleChange}
                placeholder={t('bonusRules.descriptionPlaceholder')}
                rows="3"
                required
            />

            <TextField
                id="bonusPoints"
                name="bonusPoints"
                type="number"
                label={t('tasks.points')}
                value={formData.bonusPoints}
                onChange={handleChange}
                min="1"
                max="1000"
                required
            />

            {!isEditing && (
                <>
                    <TextField
                        as="select"
                        id="ruleType"
                        name="ruleType"
                        label={t('bonusRules.ruleType')}
                        value={formData.ruleType}
                        onChange={handleChange}
                        required
                    >
                        <option value="consecutive_days">{t('bonusRules.ruleTypes.consecutive_days')}</option>
                        <option value="monthly_task_count">{t('bonusRules.ruleTypes.monthly_task_count')}</option>
                        <option value="weekly_points_sum">{t('bonusRules.ruleTypes.weekly_points_sum')}</option>
                    </TextField>

                    {formData.ruleType === 'consecutive_days' && (
                        <div className="md-form__row">
                            <TextField
                                id="requiredDays"
                                name="requiredDays"
                                type="number"
                                label={t('bonusRules.requiredDays')}
                                value={formData.requiredDays}
                                onChange={handleChange}
                                supportingText={t('bonusRules.requiredDaysHint')}
                                min="2"
                                max="365"
                                required
                            />
                            <TextField
                                id="pointsPerDay"
                                name="pointsPerDay"
                                type="number"
                                label={t('bonusRules.pointsPerDay')}
                                value={formData.pointsPerDay}
                                onChange={handleChange}
                                supportingText={t('bonusRules.pointsPerDayHint')}
                                min="1"
                                max="1000"
                                required
                            />
                        </div>
                    )}

                    {formData.ruleType === 'consecutive_days' && (
                            <fieldset className="md-choice-group">
                                <legend>{t('bonusRules.accounts')}</legend>
                                <div className="md-choice-group__options">
                                    {accountKinds.map((kind) => (
                                        <Chip
                                            key={kind}
                                            icon={formData.accounts.includes(kind) ? 'check' : 'stars'}
                                            selected={formData.accounts.includes(kind)}
                                            onClick={() => toggleAccount(kind)}
                                        >
                                            {t(`bonusRules.accountNames.${kind}`, { defaultValue: kind })}
                                        </Chip>
                                    ))}
                                </div>
                                <small className="md-field__supporting">{t('bonusRules.streakAccountsHint')}</small>
                            </fieldset>
                    )}

                    {formData.ruleType === 'weekly_points_sum' && (
                        <>
                            <TextField
                                id="requiredPoints"
                                name="requiredPoints"
                                type="number"
                                label={t('bonusRules.requiredPoints')}
                                value={formData.requiredPoints}
                                onChange={handleChange}
                                supportingText={t('bonusRules.requiredPointsHint')}
                                min="1"
                                max="10000"
                                required
                            />

                            <fieldset className="md-choice-group">
                                <legend>{t('bonusRules.accounts')}</legend>
                                <div className="md-choice-group__options">
                                    {accountKinds.map((kind) => (
                                        <Chip
                                            key={kind}
                                            icon={formData.accounts.includes(kind) ? 'check' : 'stars'}
                                            selected={formData.accounts.includes(kind)}
                                            onClick={() => toggleAccount(kind)}
                                        >
                                            {t(`bonusRules.accountNames.${kind}`, { defaultValue: kind })}
                                        </Chip>
                                    ))}
                                </div>
                                <small className="md-field__supporting">{t('bonusRules.accountsHint')}</small>
                            </fieldset>
                        </>
                    )}

                    {formData.ruleType === 'monthly_task_count' && (
                        <TextField
                            id="requiredCount"
                            name="requiredCount"
                            type="number"
                            label={t('bonusRules.requiredCount')}
                            value={formData.requiredCount}
                            onChange={handleChange}
                            supportingText={t('bonusRules.requiredCountHint')}
                            min="1"
                            max="1000"
                            required
                        />
                    )}
                </>
            )}

            <div className="form-actions">
                <Button type="submit" icon="check">
                    {isEditing ? t('common.save') : t('common.create')}
                </Button>
                {onCancel && (
                    <Button type="button" variant="text" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                )}
            </div>
        </form>
    );
}

export default BonusRulesManagement;
