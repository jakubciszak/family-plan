import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney } from '../../services/money';
import Card from '../md3/Card';

export default function PayoutSummary({ wallet }) {
    const { t, i18n } = useTranslation();

    if (!wallet) {
        return null;
    }

    const money = (amount) => formatMoney(amount, wallet.currency, i18n.language);

    return (
        <section className="wallet" data-testid="payout-summary">
            <div className="wallet__cards">
                {['pending', 'paid'].map((kind) => (
                    <Card key={kind} className="wallet__card">
                        <span className="wallet__label">{t(`allowance.${kind}`)}</span>
                        <strong className="wallet__amount">{money(wallet[kind])}</strong>
                        <span className="wallet__hint">{t(`allowance.${kind}Hint`)}</span>
                    </Card>
                ))}
            </div>
            {wallet.awaitingConfirmation.length > 0 && <ul className="wallet__payouts" data-testid="payouts-awaiting">
                {wallet.awaitingConfirmation.map((payout) => (
                    <li key={payout.id}>
                        <span className="wallet__payout-amount">{money(payout.amount)}</span>
                        <span>{t('allowance.payoutOffered')}{payout.note ? ` · ${payout.note}` : ''}</span>
                    </li>
                ))}
            </ul>}
        </section>
    );
}
