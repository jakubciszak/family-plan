import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import Icon from './md3/Icon';

const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

function WeekCalendar({ refreshToken, userId }) {
    const { t } = useTranslation();
    const [week, setWeek] = React.useState(null);
    const [openDay, setOpenDay] = React.useState(null);
    const [dayDetail, setDayDetail] = React.useState(null);

    React.useEffect(() => {
        let abandoned = false;

        taskService.getWeek(undefined, userId)
            .then((data) => {
                if (!abandoned) {
                    setWeek(data);
                }
            })
            .catch(() => setWeek(null));

        return () => {
            abandoned = true;
        };
    }, [refreshToken, userId]);

    React.useEffect(() => {
        if (!openDay) {
            setDayDetail(null);
            return undefined;
        }

        let abandoned = false;
        setDayDetail(null);

        taskService.getDay(openDay, userId)
            .then((data) => {
                if (!abandoned) {
                    setDayDetail(data);
                }
            })
            .catch(() => setDayDetail({ tasks: [], total: 0, bonus: 0 }));

        return () => {
            abandoned = true;
        };
    }, [openDay, userId, refreshToken]);

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
                    {week.bonusTotal > 0 && (
                        <span className="week-bonus">{t('week.bonus', { points: week.bonusTotal })}</span>
                    )}
                </span>
            </div>

            <ol className="week-days">
                {week.days.map((day, index) => (
                    <li key={day.date} data-date={day.date}>
                        <button
                            type="button"
                            className={[
                                'week-day',
                                day.isToday ? 'is-today' : '',
                                day.inStreak ? 'in-streak' : '',
                                day.reachedThreshold ? 'reached' : '',
                                openDay === day.date ? 'is-open' : '',
                            ].filter(Boolean).join(' ')}
                            aria-expanded={openDay === day.date}
                            onClick={() => setOpenDay((current) => (current === day.date ? null : day.date))}
                        >
                            <span className="week-day-name">{t(`week.days.${DAY_KEYS[index]}`)}</span>
                            <span className="week-day-points">{day.points}</span>
                            {day.bonus > 0 && (
                                <span className="week-day-bonus">{t('week.bonus', { points: day.bonus })}</span>
                            )}
                            {day.inStreak && <span className="week-day-flame" aria-hidden="true">🔥</span>}
                        </button>
                    </li>
                ))}
            </ol>

            {openDay && (
                <div className="week-day-detail" data-testid="week-day-detail">
                    {dayDetail === null ? (
                        <p className="empty-hint">{t('common.loading')}</p>
                    ) : dayDetail.tasks.length === 0 ? (
                        <p className="empty-hint">{t('week.nothingThatDay')}</p>
                    ) : (
                        <ul className="week-day-tasks">
                            {dayDetail.tasks.map((task) => (
                                <li key={task.id}>
                                    <span className="week-day-task-name">{task.name}</span>
                                    <span className="week-day-task-points">
                                        {t('user.points', { points: task.points })}
                                    </span>
                                </li>
                            ))}
                            {dayDetail.bonus > 0 && (
                                <li className="week-day-task--bonus">
                                    <span className="week-day-task-name">{t('week.bonusLabel')}</span>
                                    <span className="week-day-task-points">
                                        {t('week.bonus', { points: dayDetail.bonus })}
                                    </span>
                                </li>
                            )}
                        </ul>
                    )}
                </div>
            )}

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
