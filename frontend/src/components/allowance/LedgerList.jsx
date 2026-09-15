import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney } from '../../services/money';

const OUTGOING = ['expense', 'goal_allocation', 'goal_spending', 'week_reopened'];

function LedgerList({ ledger }) {
    const { t, i18n } = useTranslation();

    if (!ledger) {
        return null;
    }

    if (ledger.bookings.length === 0) {
        return <p className="empty-hint">{t('allowance.ledgerEmpty')}</p>;
    }

    const day = (moment) => new Date(moment).toLocaleDateString(i18n.language, {
        day: 'numeric',
        month: 'short',
    });

    const titleOf = (booking) => {
        if (booking.description) {
            return booking.description;
        }

        const context = booking.context || {};

        return t(`allowance.bookingTitles.${booking.type}`, {
            goal: context.goal || '',
            week: context.week ? day(context.week) : '',
        });
    };

    return (
        <ul className="ledger" data-testid="ledger">
            {ledger.bookings.map((booking) => (
                <li key={booking.id} className={`ledger__row ledger__row--${booking.type}`}>
                    <span className="ledger__when">{day(booking.bookedAt)}</span>
                    <span className="ledger__what">
                        <strong>{titleOf(booking)}</strong>
                        {booking.description && <small>{t(`allowance.bookings.${booking.type}`)}</small>}
                    </span>
                    <span className={`ledger__amount${OUTGOING.includes(booking.type) ? ' is-out' : ' is-in'}`}>
                        {OUTGOING.includes(booking.type) ? '−' : '+'}
                        {formatMoney(booking.amount, ledger.currency, i18n.language)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default LedgerList;
