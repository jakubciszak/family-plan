import React from 'react';
import { useTranslation } from 'react-i18next';
import { toMinorUnits } from '../../services/money';
import Button from '../md3/Button';
import TextField from '../md3/TextField';

const today = () => new Date().toLocaleDateString('sv');

function BookingForm({ kind, onSubmit, onCancel }) {
    const { t } = useTranslation();
    const [amount, setAmount] = React.useState('');
    const [description, setDescription] = React.useState('');
    const [on, setOn] = React.useState(today);
    const [error, setError] = React.useState(null);
    const [saving, setSaving] = React.useState(false);

    const submit = (event) => {
        event.preventDefault();
        const minorUnits = toMinorUnits(amount);

        if (minorUnits === null || minorUnits === 0) {
            setError(t('allowance.amountInvalid'));
            return;
        }

        if (description.trim() === '') {
            setError(t('allowance.descriptionNeeded'));
            return;
        }

        setError(null);
        setSaving(true);

        Promise.resolve(onSubmit({ amount: minorUnits, description: description.trim(), on }))
            .then(() => {
                setAmount('');
                setDescription('');
                onCancel?.();
            })
            .catch((failure) => setError(failure?.response?.data?.error || t('errors.generic')))
            .finally(() => setSaving(false));
    };

    return (
        <form className={`booking-form booking-form--${kind}`} onSubmit={submit} data-testid={`booking-${kind}`}>
            <TextField
                id={`booking-${kind}-amount`}
                label={t('allowance.amount')}
                inputMode="decimal"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
            />
            <TextField
                id={`booking-${kind}-description`}
                label={t(`allowance.${kind}Description`)}
                value={description}
                onChange={(event) => setDescription(event.target.value)}
            />
            <TextField
                id={`booking-${kind}-on`}
                label={t('allowance.on')}
                type="date"
                max={today()}
                value={on}
                onChange={(event) => setOn(event.target.value)}
            />

            {error && <p className="form-error" role="alert">{error}</p>}

            <div className="dialog-form__actions">
                {onCancel && (
                    <Button type="button" variant="text" onClick={onCancel}>{t('common.cancel')}</Button>
                )}
                <Button type="submit" variant="filled" loading={saving}>
                    {t(`allowance.add${kind === 'income' ? 'Income' : 'Expense'}`)}
                </Button>
            </div>
        </form>
    );
}

export default BookingForm;
