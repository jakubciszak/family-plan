import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import { Button, Icon, TextField, CircularProgress } from '../components/md3';

function StatusChangeRulesManagement({ user, taskTemplateId }) {
    const { t } = useTranslation();
    const [rules, setRules] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [error, setError] = React.useState(null);
    const [taskTemplates, setTaskTemplates] = React.useState([]);

    React.useEffect(() => {
        loadRules();
        loadTaskTemplates();
    }, [taskTemplateId]);

    const loadRules = async () => {
        try {
            setLoading(true);
            const params = taskTemplateId ? `?taskTemplateId=${taskTemplateId}` : '';
            const data = await apiClient.get(`/api/status-change-rules${params}`);
            setRules(data.rules || []);
            setError(null);
        } catch (error) {
            console.error('Error loading status change rules:', error);
            setError('Failed to load status change rules');
        } finally {
            setLoading(false);
        }
    };

    const loadTaskTemplates = async () => {
        try {
            const data = await apiClient.get('/api/task-templates');
            setTaskTemplates(data.templates || data || []);
        } catch (error) {
            console.error('Error loading task templates:', error);
        }
    };

    const handleCreateRule = async (ruleData) => {
        try {
            await apiClient.post('/api/status-change-rules', ruleData);
            setShowCreateForm(false);
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error creating rule:', error);
            setError('Failed to create status change rule');
        }
    };

    const handleUpdateRule = async (ruleId, ruleData) => {
        try {
            await apiClient.put(`/api/status-change-rules/${ruleId}`, ruleData);
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error updating rule:', error);
            setError('Failed to update status change rule');
        }
    };

    const handleActivateRule = async (ruleId) => {
        try {
            await apiClient.post(`/api/status-change-rules/${ruleId}/activate`, {});
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error activating rule:', error);
            setError('Failed to activate status change rule');
        }
    };

    const handleDeactivateRule = async (ruleId) => {
        try {
            await apiClient.post(`/api/status-change-rules/${ruleId}/deactivate`, {});
            loadRules();
            setError(null);
        } catch (error) {
            console.error('Error deactivating rule:', error);
            setError('Failed to deactivate status change rule');
        }
    };

    if (user.role !== 'ROLE_ADMIN') {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('statusChangeRules.accessDeniedTitle')}</h2>
                <p>{t('statusChangeRules.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    return (
        <div className="status-change-rules-container">
            <div className="status-change-rules-header">
                <h2>{t('statusChangeRules.title')}</h2>
                <Button
                    variant={showCreateForm ? 'text' : 'filled'}
                    icon={showCreateForm ? 'close' : 'add'}
                    onClick={() => setShowCreateForm(!showCreateForm)}
                >
                    {showCreateForm ? t('common.cancel') : t('statusChangeRules.create')}
                </Button>
            </div>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {showCreateForm && (
                <StatusChangeRuleForm
                    onSubmit={handleCreateRule}
                    taskTemplates={taskTemplates}
                    currentTaskTemplateId={taskTemplateId}
                />
            )}

            <div className="status-change-rules-list">
                {rules.length === 0 ? (
                    <p>{t('statusChangeRules.noRules')}</p>
                ) : (
                    rules.map(rule => (
                        <StatusChangeRuleCard
                            key={rule.id}
                            rule={rule}
                            taskTemplates={taskTemplates}
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

function StatusChangeRuleCard({ rule, taskTemplates, onUpdate, onActivate, onDeactivate }) {
    const { t } = useTranslation();
    const [isEditing, setIsEditing] = React.useState(false);

    const getConditionTypeLabel = (type) => {
        switch (type) {
            case 'other_task_completed_today':
                return t('statusChangeRules.conditionTypes.otherTaskCompletedToday');
            case 'last_execution_cooldown':
                return t('statusChangeRules.conditionTypes.lastExecutionCooldown');
            default:
                return type;
        }
    };

    const findTaskTemplateName = (taskTemplateId) => {
        const template = taskTemplates.find(t => t.id === taskTemplateId);
        return template ? template.name : taskTemplateId;
    };

    const getRuleDescription = (rule) => {
        if (rule.conditionType === 'other_task_completed_today' && rule.config.requiredTaskTemplateId) {
            return t('statusChangeRules.descriptions.otherTaskCompletedToday', {
                taskName: findTaskTemplateName(rule.config.requiredTaskTemplateId)
            });
        } else if (rule.conditionType === 'last_execution_cooldown' && rule.config.cooldownDays) {
            return t('statusChangeRules.descriptions.lastExecutionCooldown', {
                days: rule.config.cooldownDays
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
            <div className="status-change-rule-card editing">
                <StatusChangeRuleForm
                    rule={rule}
                    taskTemplates={taskTemplates}
                    onSubmit={handleUpdate}
                    onCancel={() => setIsEditing(false)}
                />
            </div>
        );
    }

    return (
        <div className={`status-change-rule-card ${rule.isActive ? 'active' : 'inactive'}`}>
            <div className="rule-header">
                <h3>{rule.name}</h3>
                <div className="rule-status">
                    <span className={`status-badge ${rule.isActive ? 'status-active' : 'status-inactive'}`}>
                        <Icon name={rule.isActive ? 'checkCircle' : 'block'} size={16} />
                        {rule.isActive ? t('common.active') : t('common.inactive')}
                    </span>
                    <span className="rule-type-badge">
                        <Icon name="rule" size={16} />
                        {getConditionTypeLabel(rule.conditionType)}
                    </span>
                </div>
            </div>
            <div className="rule-body">
                <p className="rule-description">{rule.description}</p>
                <p className="rule-config">{getRuleDescription(rule)}</p>
                <p className="rule-task-template">
                    <strong>{t('statusChangeRules.appliesTo')}:</strong> {findTaskTemplateName(rule.taskTemplateId)}
                </p>
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

function StatusChangeRuleForm({ rule, taskTemplates, currentTaskTemplateId, onSubmit, onCancel }) {
    const { t } = useTranslation();
    const isEditing = !!rule;

    const [formData, setFormData] = React.useState({
        taskTemplateId: rule?.taskTemplateId || currentTaskTemplateId || '',
        name: rule?.name || '',
        description: rule?.description || '',
        conditionType: rule?.conditionType || 'last_execution_cooldown',
        cooldownDays: rule?.config?.cooldownDays || 2,
        requiredTaskTemplateId: rule?.config?.requiredTaskTemplateId || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        const conditionConfig = formData.conditionType === 'last_execution_cooldown'
            ? { cooldownDays: formData.cooldownDays }
            : { requiredTaskTemplateId: formData.requiredTaskTemplateId };

        const submitData = isEditing
            ? {
                name: formData.name,
                description: formData.description,
              }
            : {
                taskTemplateId: formData.taskTemplateId,
                name: formData.name,
                description: formData.description,
                conditionType: formData.conditionType,
                conditionConfig: conditionConfig,
              };

        onSubmit(submitData);
    };

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: ['cooldownDays'].includes(name)
                ? parseInt(value, 10)
                : value,
        }));
    };

    return (
        <form className="status-change-rule-form" onSubmit={handleSubmit}>
            <h3>{isEditing ? t('statusChangeRules.edit') : t('statusChangeRules.create')}</h3>

            {!isEditing && (
                <TextField
                    as="select"
                    id="taskTemplateId"
                    name="taskTemplateId"
                    label={t('statusChangeRules.taskTemplate')}
                    value={formData.taskTemplateId}
                    onChange={handleChange}
                    required
                    disabled={!!currentTaskTemplateId}
                >
                    <option value="">{t('statusChangeRules.selectTaskTemplate')}</option>
                    {taskTemplates.map(template => (
                        <option key={template.id} value={template.id}>{template.name}</option>
                    ))}
                </TextField>
            )}

            <TextField
                id="name"
                name="name"
                type="text"
                label={t('statusChangeRules.name')}
                value={formData.name}
                onChange={handleChange}
                placeholder={t('statusChangeRules.namePlaceholder')}
                required
            />

            <TextField
                as="textarea"
                id="description"
                name="description"
                label={t('tasks.description')}
                value={formData.description}
                onChange={handleChange}
                placeholder={t('statusChangeRules.descriptionPlaceholder')}
                rows="3"
                required
            />

            {!isEditing && (
                <>
                    <TextField
                        as="select"
                        id="conditionType"
                        name="conditionType"
                        label={t('statusChangeRules.conditionType')}
                        value={formData.conditionType}
                        onChange={handleChange}
                        required
                    >
                        <option value="last_execution_cooldown">
                            {t('statusChangeRules.conditionTypes.lastExecutionCooldown')}
                        </option>
                        <option value="other_task_completed_today">
                            {t('statusChangeRules.conditionTypes.otherTaskCompletedToday')}
                        </option>
                    </TextField>

                    {formData.conditionType === 'last_execution_cooldown' && (
                        <TextField
                            id="cooldownDays"
                            name="cooldownDays"
                            type="number"
                            label={t('statusChangeRules.cooldownDays')}
                            value={formData.cooldownDays}
                            onChange={handleChange}
                            supportingText={t('statusChangeRules.cooldownDaysHint')}
                            min="1"
                            max="365"
                            required
                        />
                    )}

                    {formData.conditionType === 'other_task_completed_today' && (
                        <TextField
                            as="select"
                            id="requiredTaskTemplateId"
                            name="requiredTaskTemplateId"
                            label={t('statusChangeRules.requiredTask')}
                            value={formData.requiredTaskTemplateId}
                            onChange={handleChange}
                            supportingText={t('statusChangeRules.requiredTaskHint')}
                            required
                        >
                            <option value="">{t('statusChangeRules.selectRequiredTask')}</option>
                            {taskTemplates.map(template => (
                                <option key={template.id} value={template.id}>{template.name}</option>
                            ))}
                        </TextField>
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

export default StatusChangeRulesManagement;
