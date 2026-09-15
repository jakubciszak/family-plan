import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney, fromMinorUnits, toMinorUnits } from '../../services/money';
import Button from '../md3/Button';
import TextField from '../md3/TextField';

function PayoutForm({ wallet, onOffer }) {
    const { t, i18n } = useTranslation();
    const [amount, setAmount] = React.useState('');
    const [note, setNote] = React.useState('');
    const [error, setError] = React.useState(null);

    if (!wallet) {
        return null;
    }

    const offered = wallet.awaitingConfirmation.reduce((sum, payout) => sum + payout.amount, 0);
    const free = wallet.pending - offered;

    const offer = (minorUnits) => {
        if (minorUnits === null || minorUnits <= 0) {
            setError(t('allowance.amountInvalid'));
            return;
        }

        setError(null);

        Promise.resolve(onOffer({ amount: minorUnits, note: note.trim() || null }))
            .then(() => {
                setAmount('');
                setNote('');
            })
            .catch((failure) => setError(failure?.response?.data?.error || t('errors.generic')));
    };

    return (
        <form className="payout-form" onSubmit={(event) => { event.preventDefault(); offer(toMinorUnits(amount)); }}>
            <p className="payout-form__free">
                {t('allowance.freeToPayOut', { amount: formatMoney(free, wallet.currency, i18n.language) })}
            </p>

            <TextField
                id="payout-amount"
                label={t('allowance.amount')}
                inputMode="decimal"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
            />
            <TextField
                id="payout-note"
                label={t('allowance.payoutNote')}
                value={note}
                onChange={(event) => setNote(event.target.value)}
            />

            <div className="payout-form__actions">
                <Button type="submit" variant="tonal" disabled={free <= 0}>
                    {t('allowance.payPart')}
                </Button>
                <Button
                    type="button"
                    variant="filled"
                    disabled={free <= 0}
                    onClick={() => { setAmount(fromMinorUnits(free)); offer(free); }}
                >
                    {t('allowance.payEverything')}
                </Button>
            </div>

            {error && <p className="form-error" role="alert">{error}</p>}
        </form>
    );
}

export default PayoutForm;
