import React from 'react';
import { useTranslation } from 'react-i18next';
import { toMinorUnits } from '../../services/money';
import Button from '../md3/Button';
import TextField from '../md3/TextField';

function GoalForm({ onPlan, onCancel }) {
    const { t } = useTranslation();
    const [name, setName] = React.useState('');
    const [target, setTarget] = React.useState('');
    const [wantedBy, setWantedBy] = React.useState('');
    const [error, setError] = React.useState(null);
    const [saving, setSaving] = React.useState(false);

    const submit = (event) => {
        event.preventDefault();
        const minorUnits = toMinorUnits(target);

        if (name.trim() === '' || minorUnits === null || minorUnits === 0) {
            setError(t('allowance.goalNeedsNameAndTarget'));
            return;
        }

        setError(null);
        setSaving(true);

        Promise.resolve(onPlan({ name: name.trim(), target: minorUnits, wantedBy: wantedBy || null }))
            .then(() => {
                setName('');
                setTarget('');
                setWantedBy('');
                onCancel?.();
            })
            .catch((failure) => setError(failure?.response?.data?.error || t('errors.generic')))
            .finally(() => setSaving(false));
    };

    return (
        <form className="goal-form" onSubmit={submit} data-testid="goal-form">
            <TextField
                id="goal-name"
                label={t('allowance.goalName')}
                value={name}
                onChange={(event) => setName(event.target.value)}
            />
            <TextField
                id="goal-target"
                label={t('allowance.goalTarget')}
                inputMode="decimal"
                value={target}
                onChange={(event) => setTarget(event.target.value)}
            />
            <TextField
                id="goal-wanted-by"
                label={t('allowance.goalWantedBy')}
                type="date"
                value={wantedBy}
                onChange={(event) => setWantedBy(event.target.value)}
            />

            {error && <p className="form-error" role="alert">{error}</p>}

            <div className="dialog-form__actions">
                {onCancel && (
                    <Button type="button" variant="text" onClick={onCancel}>{t('common.cancel')}</Button>
                )}
                <Button type="submit" variant="filled" loading={saving}>{t('allowance.planGoal')}</Button>
            </div>
        </form>
    );
}

export default GoalForm;
