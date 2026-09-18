import React from 'react';
import { useTranslation } from 'react-i18next';
import taskService from '../services/taskService';
import Icon from './md3/Icon';
import IconButton from './md3/IconButton';
import Button from './md3/Button';
import TextField from './md3/TextField';

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

function WeekCalendar({ refreshToken, userId, title, manage = false }) {
    const { t, i18n } = useTranslation();
    const [week, setWeek] = React.useState(null);
    const [weekStart, setWeekStart] = React.useState(null);
    const [openDay, setOpenDay] = React.useState(null);
    const [dayDetail, setDayDetail] = React.useState(null);
    const [reloads, setReloads] = React.useState(0);
    const [editing, setEditing] = React.useState(null);
    const [doneOn, setDoneOn] = React.useState('');
    const [saving, setSaving] = React.useState(false);
    const [error, setError] = React.useState(null);
    const touchStart = React.useRef(null);

    const goToWeek = (days) => {
        setOpenDay(null);
        setWeekStart((current) => shiftedBy(current || new Date(), days));
    };

    React.useEffect(() => {
        let abandoned = false;

        taskService.getWeek(weekStart, userId)
            .then((data) => {
                if (!abandoned) {
                    setWeek(data);
                }
            })
            .catch(() => setWeek(null));

        return () => {
            abandoned = true;
        };
    }, [refreshToken, userId, weekStart, reloads]);

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
    }, [openDay, userId, refreshToken, reloads]);

    const takeBack = (entryId) => {
        taskService.takeBackBonus(entryId, userId)
            .then(() => setReloads((count) => count + 1))
            .catch(() => undefined);
    };

    const correct = async (action) => {
        setSaving(true);
        setError(null);
        try {
            await action();
            setEditing(null);
            setReloads((count) => count + 1);
        } catch (failure) {
            setError(failure.message || t('week.correctionFailed'));
        } finally {
            setSaving(false);
        }
    };

    if (!week?.days?.length) {
        return null;
    }

    const { streak } = week;
    const isCurrentWeek = mondayOf(weekStart || new Date()) === mondayOf(new Date());
    const range = [week.weekStart, shiftedBy(week.weekStart, 6)]
        .map((day) => new Date(day).toLocaleDateString(i18n.language, { day: 'numeric', month: 'short' }))
        .join(' – ');

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

    return (
        <section
            className="week-calendar"
            data-testid="week-calendar"
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <div className="week-calendar-header">
                <h3>
                    <Icon name="calendar" size={20} />
                    {title || t('week.title')}
                    <span className="week-range">{range}</span>
                </h3>
                <span className="week-total">
                    <Icon name="stars" size={18} />
                    {t('user.points', { points: week.total })}
                    {week.bonusTotal > 0 && (
                        <span className="week-bonus">{t('week.bonus', { points: week.bonusTotal })}</span>
                    )}
                </span>
            </div>

            <div className="week-steps">
                <IconButton
                    icon="back"
                    variant="text"
                    label={t('week.previous')}
                    onClick={() => goToWeek(-7)}
                />
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
                    {error && <p role="alert">{error}</p>}
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
                                    {manage && !dayDetail.closed && (
                                        <>
                                            <IconButton
                                                icon="edit"
                                                variant="text"
                                                label={t('week.moveExecution')}
                                                disabled={saving}
                                                onClick={() => {
                                                    setEditing(task.id);
                                                    setDoneOn(openDay);
                                                    setError(null);
                                                }}
                                            />
                                            <IconButton
                                                icon="delete"
                                                variant="text"
                                                label={t('week.deleteExecution')}
                                                disabled={saving}
                                                onClick={() => correct(() => taskService.deleteExecution(task.id))}
                                            />
                                            {editing === task.id && (
                                                <form className="week-execution-edit" onSubmit={(event) => {
                                                    event.preventDefault();
                                                    correct(() => taskService.moveExecution(task.id, doneOn));
                                                }}>
                                                    <TextField
                                                        id={`execution-date-${task.id}`}
                                                        type="date"
                                                        label={t('week.executionDate')}
                                                        value={doneOn}
                                                        min={week.weekStart}
                                                        max={[shiftedBy(week.weekStart, 6), new Date().toLocaleDateString('sv')].sort()[0]}
                                                        onChange={(event) => setDoneOn(event.target.value)}
                                                        required
                                                        disabled={saving}
                                                    />
                                                    <Button type="submit" disabled={saving}>{t('common.save')}</Button>
                                                    <Button type="button" variant="text" disabled={saving} onClick={() => setEditing(null)}>{t('common.cancel')}</Button>
                                                </form>
                                            )}
                                        </>
                                    )}
                                </li>
                            ))}
                            {(dayDetail.bonuses ?? []).map((bonus) => (
                                <li key={bonus.id} className="week-day-task--bonus">
                                    <span className="week-day-task-name">
                                        {bonus.points < 0
                                            ? t('week.bonusTakenBack')
                                            : bonus.name || t('week.bonusLabel')}
                                    </span>
                                    <span className="week-day-task-points">
                                        {bonus.points < 0
                                            ? t('week.bonusLost', { points: -bonus.points })
                                            : t('week.bonus', { points: bonus.points })}
                                    </span>
                                    {manage && bonus.points > 0 && !bonus.takenBack && (
                                        <IconButton
                                            icon="delete"
                                            variant="text"
                                            label={t('week.takeBackBonus')}
                                            onClick={() => takeBack(bonus.id)}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {streak && streak.length > 0 && (
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
