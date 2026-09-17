import React from 'react';
import { useTranslation } from 'react-i18next';
import allowanceService from '../../services/allowanceService';
import { formatMoney } from '../../services/money';
import Icon from '../md3/Icon';
import IconButton from '../md3/IconButton';

const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

const SWIPE_THRESHOLD = 50;

const mondayOf = (day) => {
    const monday = new Date(day);
    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

    return monday.toLocaleDateString('sv');
};

const shiftedBy = (day, days) => {
    const shifted = new Date(day);
    shifted.setDate(shifted.getDate() + days);

    return shifted.toLocaleDateString('sv');
};

function AllowanceWeek({ userId, refreshToken, renderActions, mine = false }) {
    const { t, i18n } = useTranslation();
    const [week, setWeek] = React.useState(null);
    const [weekStart, setWeekStart] = React.useState(null);
    const touchStart = React.useRef(null);

    const [reloads, setReloads] = React.useState(0);
    const reload = React.useCallback(() => setReloads((count) => count + 1), []);

    React.useEffect(() => {
        let abandoned = false;

        allowanceService.getWeek(weekStart, userId)
            .then((data) => {
                if (!abandoned) {
                    setWeek(data);
                }
            })
            .catch(() => setWeek(null));

        return () => {
            abandoned = true;
        };
    }, [weekStart, userId, refreshToken, reloads]);

    if (!week) {
        return null;
    }

    const isCurrentWeek = mondayOf(weekStart || new Date()) === mondayOf(new Date());
    const range = [week.weekStart, shiftedBy(week.weekStart, 6)]
        .map((day) => new Date(day).toLocaleDateString(i18n.language, { day: 'numeric', month: 'short' }))
        .join(' – ');

    const goToWeek = (days) => setWeekStart((current) => shiftedBy(current || new Date(), days));

    const onTouchStart = (event) => {
        touchStart.current = event.touches[0]?.clientX ?? null;
    };

    const onTouchEnd = (event) => {
        const start = touchStart.current;
        touchStart.current = null;

        if (start === null) {
            return;
        }

        const travelled = (event.changedTouches[0]?.clientX ?? start) - start;

        if (travelled > SWIPE_THRESHOLD) {
            goToWeek(-7);
        } else if (travelled < -SWIPE_THRESHOLD && !isCurrentWeek) {
            goToWeek(7);
        }
    };

    const closed = week.closure !== null;
    const amount = closed ? week.closure.total : week.expected.total;
    const lines = closed ? week.closure.lines : week.expected.lines;

    return (
        <section
            className={`allowance-week${closed ? ' is-closed' : ''}`}
            data-testid="allowance-week"
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <header className="allowance-week__head">
                <h3>
                    <Icon name="calendar" size={20} />
                    <span className="week-range">{range}</span>
                </h3>
                <span className="allowance-week__earning">
                    <span className="allowance-week__amount-label">
                        {mine
                            ? t(closed ? 'allowance.weekEarned' : 'allowance.weekWillEarn')
                            : t('allowance.weekWorth')}
                    </span>
                    <span className="allowance-week__amount">
                        {formatMoney(amount, week.currency, i18n.language)}
                    </span>
                </span>
            </header>

            <div className="week-steps">
                <IconButton icon="back" variant="text" label={t('week.previous')} onClick={() => goToWeek(-7)} />
                {!isCurrentWeek && (
                    <button type="button" className="week-back-to-now" onClick={() => setWeekStart(null)}>
                        {t('week.thisWeek')}
                    </button>
                )}
                <IconButton
                    icon="back"
                    variant="text"
                    className="week-step--next"
                    label={t('week.next')}
                    disabled={isCurrentWeek}
                    onClick={() => goToWeek(7)}
                />
            </div>

            <ol className="week-days">
                {week.days.map((day, index) => (
                    <li key={day.date}>
                        <div className={`week-day${day.isToday ? ' is-today' : ''}`}>
                            <span className="week-day-name">{t(`week.days.${DAY_KEYS[index]}`)}</span>
                            <span className="week-day-points">{day.points}</span>
                            {day.bonus > 0 && (
                                <span className="week-day-bonus">{t('week.bonus', { points: day.bonus })}</span>
                            )}
                        </div>
                    </li>
                ))}
            </ol>

            <ul className="allowance-week__lines">
                {lines.map((line) => (
                    <li key={line.pointsAccount} className={line.reachedMinimum ? '' : 'is-short'}>
                        <span>{t(`allowance.accounts.${line.pointsAccount}`)}</span>
                        <span className="allowance-week__line-points">
                            {t('allowance.pointsOfMinimum', { points: line.points, minimum: line.minimumPoints })}
                        </span>
                        <span>{formatMoney(line.amount, week.currency, i18n.language)}</span>
                        {!closed && line.missingPoints > 0 && (
                            <span className="allowance-week__line-hint">
                                {t('allowance.missingToEarn', { points: line.missingPoints })}
                            </span>
                        )}
                    </li>
                ))}
                {lines.length === 0 && <li className="empty-hint">{t('allowance.noRulesYet')}</li>}
            </ul>

            <p className="allowance-week__state">
                {closed
                    ? t('allowance.weekClosedOn', {
                        when: new Date(week.closure.closedAt).toLocaleDateString(i18n.language),
                    })
                    : t('allowance.weekStillOpen')}
            </p>

            {renderActions?.(week, reload)}
        </section>
    );
}

export default AllowanceWeek;
