import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import Icon from './md3/Icon';

const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

function WeekCalendar({ refreshToken }) {
    const { t } = useTranslation();
    const [week, setWeek] = React.useState(null);

    React.useEffect(() => {
        let abandoned = false;

        taskService.getWeek()
            .then((data) => {
                if (!abandoned) {
                    setWeek(data);
                }
            })
            .catch(() => setWeek(null));

        return () => {
            abandoned = true;
        };
    }, [refreshToken]);

    if (!week?.days?.length) {
        return null;
    }

    const { streak } = week;

    return (
        <section className="week-calendar" data-testid="week-calendar">
            <div className="week-calendar-header">
                <h3>
                    <Icon name="calendar" size={20} />
                    {t('week.title')}
                </h3>
                <span className="week-total">
                    <Icon name="stars" size={18} />
                    {t('user.points', { points: week.total })}
                </span>
            </div>

            <ol className="week-days">
                {week.days.map((day, index) => (
                    <li
                        key={day.date}
                        className={[
                            'week-day',
                            day.isToday ? 'is-today' : '',
                            day.inStreak ? 'in-streak' : '',
                            day.reachedThreshold ? 'reached' : '',
                        ].filter(Boolean).join(' ')}
                        data-date={day.date}
                    >
                        <span className="week-day-name">{t(`week.days.${DAY_KEYS[index]}`)}</span>
                        <span className="week-day-points">{day.points}</span>
                        {day.inStreak && <span className="week-day-flame" aria-hidden="true">🔥</span>}
                    </li>
                ))}
            </ol>

            {streak && (
                <p className="week-streak" data-testid="week-streak">
                    <Icon name="streak" size={20} />
                    <span>
                        {streak.met
                            ? t('week.streakMet', { count: streak.length, points: streak.bonusPoints })
                            : t('week.streakProgress', {
                                count: streak.length,
                                required: streak.requiredDays,
                                pointsPerDay: streak.pointsPerDay,
                            })}
                    </span>
                </p>
            )}
        </section>
    );
}

export default WeekCalendar;
