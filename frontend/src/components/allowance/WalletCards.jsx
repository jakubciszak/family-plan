import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney } from '../../services/money';
import Button from '../md3/Button';
import Card from '../md3/Card';
import Icon from '../md3/Icon';

function WalletCards({ wallet, onConfirmPayout, readOnly = false }) {
    const { t, i18n } = useTranslation();

    if (!wallet) {
        return null;
    }

    const money = (amount) => formatMoney(amount, wallet.currency, i18n.language);

    return (
        <section className="wallet" data-testid="wallet">
            <div className="wallet__cards">
                <Card className="wallet__card wallet__card--pending">
                    <span className="wallet__label">
                        <Icon name="schedule" size={18} />
                        {t('allowance.pending')}
                    </span>
                    <strong className="wallet__amount">{money(wallet.pending)}</strong>
                    <span className="wallet__hint">{t('allowance.pendingHint')}</span>
                </Card>

                <Card className="wallet__card wallet__card--available">
                    <span className="wallet__label">
                        <Icon name="wallet" size={18} />
                        {t('allowance.available')}
                    </span>
                    <strong className="wallet__amount">{money(wallet.available)}</strong>
                    <span className="wallet__hint">{t('allowance.availableHint')}</span>
                </Card>

                <Card className="wallet__card wallet__card--saved">
                    <span className="wallet__label">
                        <Icon name="savings" size={18} />
                        {t('allowance.putAside')}
                    </span>
                    <strong className="wallet__amount">{money(wallet.putAside)}</strong>
                    <span className="wallet__hint">{t('allowance.putAsideHint')}</span>
                </Card>
            </div>

            {wallet.awaitingConfirmation.length > 0 && (
                <ul className="wallet__payouts" data-testid="payouts-awaiting">
                    {wallet.awaitingConfirmation.map((payout) => (
                        <li key={payout.id}>
                            <span className="wallet__payout-amount">{money(payout.amount)}</span>
                            <span className="wallet__payout-note">
                                {payout.note || t('allowance.payoutOffered')}
                            </span>
                            {!readOnly && (
                                <Button variant="filled" onClick={() => onConfirmPayout(payout.id)}>
                                    {t('allowance.confirmPayout')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <dl className="wallet__totals">
                <div>
                    <dt>{t('allowance.earnedTotal')}</dt>
                    <dd>{money(wallet.earned)}</dd>
                </div>
                <div>
                    <dt>{t('allowance.otherIncomeTotal')}</dt>
                    <dd>{money(wallet.otherIncome)}</dd>
                </div>
                <div>
                    <dt>{t('allowance.spentTotal')}</dt>
                    <dd>{money(wallet.spent)}</dd>
                </div>
            </dl>
        </section>
    );
}

export default WalletCards;
