import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import { Button, Dialog, TextField } from './md3';

export default function ReturnTaskDialog({ task, onClose, onReturned }) {
    const { t } = useTranslation();
    const [reason, setReason] = React.useState('');
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState(false);
    const close = React.useCallback(() => { if (!busy) onClose(); }, [busy, onClose]);

    const submit = async () => {
        if (busy || !reason.trim() || reason.trim().length > 500) return;
        setBusy(true);
        setError(false);
        try {
            await taskService.rejectExecution(task.id, reason.trim());
            onClose();
            await onReturned();
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open onClose={close} headline={t('member.rejectHeadline', { name: task.name })} actions={<>
            <Button variant="text" disabled={busy} onClick={close}>{t('common.cancel')}</Button>
            <Button tone="danger" disabled={busy || !reason.trim() || reason.trim().length > 500} onClick={submit}>{t('member.reject')}</Button>
        </>}>
            {error && <p role="alert">{t('tasks.returnFailed')}</p>}
            <TextField id="rejection-reason" as="textarea" label={t('member.reasonLabel')} value={reason}
                onChange={(event) => setReason(event.target.value)} supportingText={t('member.reasonHint')}
                maxLength={500} disabled={busy} required />
        </Dialog>
    );
}
