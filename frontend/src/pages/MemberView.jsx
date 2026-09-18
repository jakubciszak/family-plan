import React from 'react';
import ReturnTaskDialog from '../components/ReturnTaskDialog';
import { useTranslation } from 'react-i18next';
import WeekCalendar from '../components/WeekCalendar';
import taskService from '../services/taskService';
import { Button, Icon, CircularProgress } from '../components/md3';

function MemberView({ member, onBack }) {
    const { t } = useTranslation();
    const [executions, setExecutions] = React.useState(null);
    const [error, setError] = React.useState(null);
    const [refreshToken, setRefreshToken] = React.useState(0);
    const [toReject, setToReject] = React.useState(null);

    const load = React.useCallback(async () => {
        try {
            const data = await taskService.executionsOf(member.id);
            setExecutions(data.executions || []);
        } catch {
            setExecutions([]);
            setError(t('common.error'));
        }
    }, [member.id, t]);

    React.useEffect(() => {
        load();
    }, [load]);

    const run = async (action) => {
        setError(null);
        try {
            await action();
            await load();
            setRefreshToken((value) => value + 1);
        } catch {
            setError(t('common.error'));
        }
    };

    if (executions === null) {
        return <CircularProgress label={t('common.loading')} />;
    }

    const waiting = executions.filter((execution) => execution.status === 'completed');
    const rest = executions.filter((execution) => execution.status !== 'completed');

    const card = (execution, actions) => (
        <div className="task-card" key={execution.id}>
            <div className="task-row">
                <span className="task-name">{execution.name}</span>
                <span className="task-points">
                    <Icon name="stars" size={16} />
                    {t('user.points', { points: execution.points })}
                </span>
                <span className="task-status">{t(`tasks.status${execution.status[0].toUpperCase()}${execution.status.slice(1)}`, execution.status)}</span>
            </div>
            {execution.rejectionReason && <p>{t('tasks.rejectedReason', { reason: execution.rejectionReason })}</p>}
            {actions}
        </div>
    );

    return (
        <div className="task-list-container" data-testid="member-view">
            <div className="task-list-header">
                <Button variant="text" icon="back" onClick={onBack}>
                    {t('member.backToTasks')}
                </Button>
                <h2>{t('member.tasksOf', { name: member.name })}</h2>
            </div>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            <WeekCalendar userId={member.id} refreshToken={refreshToken} manage />

            <section className="task-section" data-testid="member-awaiting">
                <h3><Icon name="approve" size={20} />{t('tasks.approvalSection')}</h3>
                {waiting.length === 0 ? (
                    <p className="empty-hint">{t('tasks.nothingToApprove')}</p>
                ) : (
                    <div className="tasks">
                        {waiting.map((execution) => card(execution, (
                            <div className="task-actions">
                                <Button
                                    icon="approve"
                                    onClick={() => run(() => taskService.approve(execution.id))}
                                >
                                    {t('tasks.approve')}
                                </Button>
                                <Button
                                    variant="outlined"
                                    icon="close"
                                    onClick={() => setToReject(execution)}
                                >
                                    {t('member.reject')}
                                </Button>
                            </div>
                        )))}
                    </div>
                )}
            </section>

            <section className="task-section" data-testid="member-tasks">
                <h3><Icon name="person" size={20} />{t('member.tasksSection')}</h3>
                {rest.length === 0 ? (
                    <p className="empty-hint">{t('member.noTasks')}</p>
                ) : (
                    <div className="tasks">
                        {rest.map((execution) => card(execution, null))}
                    </div>
                )}
            </section>

            {toReject && <ReturnTaskDialog key={toReject.id} task={toReject} onClose={() => setToReject(null)} onReturned={async () => {
                await load();
                setRefreshToken((value) => value + 1);
            }} />}
        </div>
    );
}

export default MemberView;
