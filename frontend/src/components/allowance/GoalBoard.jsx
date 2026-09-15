import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney, toMinorUnits } from '../../services/money';
import Button from '../md3/Button';
import Card from '../md3/Card';
import Icon from '../md3/Icon';
import TextField from '../md3/TextField';
import { LinearProgress } from '../md3/Progress';

function GoalForm({ onPlan }) {
    const { t } = useTranslation();
    const [name, setName] = React.useState('');
    const [target, setTarget] = React.useState('');
    const [wantedBy, setWantedBy] = React.useState('');
    const [error, setError] = React.useState(null);

    const submit = (event) => {
        event.preventDefault();
        const minorUnits = toMinorUnits(target);

        if (name.trim() === '' || minorUnits === null || minorUnits === 0) {
            setError(t('allowance.goalNeedsNameAndTarget'));
            return;
        }

        setError(null);

        Promise.resolve(onPlan({ name: name.trim(), target: minorUnits, wantedBy: wantedBy || null }))
            .then(() => {
                setName('');
                setTarget('');
                setWantedBy('');
            })
            .catch((failure) => setError(failure?.response?.data?.error || t('errors.generic')));
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
            <Button type="submit" variant="filled" icon="add">{t('allowance.planGoal')}</Button>
            {error && <p className="form-error" role="alert">{error}</p>}
        </form>
    );
}

function GoalRow({ goal, currency, readOnly, onPutAside, onTakeBack, onSpend, onClose }) {
    const { t, i18n } = useTranslation();
    const [amount, setAmount] = React.useState('');
    const money = (value) => formatMoney(value, currency, i18n.language);

    const withAmount = (action) => {
        const minorUnits = toMinorUnits(amount);

        if (minorUnits === null || minorUnits === 0) {
            return;
        }

        Promise.resolve(action(minorUnits))
            .then(() => setAmount(''))
            .catch(() => undefined);
    };

    return (
        <Card className={`goal${goal.reached ? ' is-reached' : ''}`} data-testid="goal">
            <header className="goal__head">
                <h4>
                    <Icon name="goal" size={18} />
                    {goal.name}
                </h4>
                <span className="goal__numbers">
                    {money(goal.saved)} <small>/ {money(goal.target)}</small>
                </span>
            </header>

            <LinearProgress value={goal.percent} label={goal.name} />

            <p className="goal__nudge">
                {goal.reached
                    ? t('allowance.goalReached')
                    : goal.perWeekNeeded !== null && goal.weeksLeft !== null
                        ? t('allowance.goalPerWeek', {
                            amount: money(goal.perWeekNeeded),
                            weeks: goal.weeksLeft,
                        })
                        : goal.weeksAtThisPace !== null
                            ? t('allowance.goalAtThisPace', { weeks: goal.weeksAtThisPace })
                            : t('allowance.goalMissing', { amount: money(goal.missing) })}
            </p>

            {!readOnly && (
            <div className="goal__actions">
                <TextField
                    id={`goal-amount-${goal.id}`}
                    label={t('allowance.amount')}
                    inputMode="decimal"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                />
                <Button variant="filled" onClick={() => withAmount((value) => onPutAside(goal.id, value))}>
                    {t('allowance.putAsideAction')}
                </Button>
                <Button variant="outlined" onClick={() => withAmount((value) => onTakeBack(goal.id, value))}>
                    {t('allowance.takeBackAction')}
                </Button>
                {goal.reached && (
                    <Button variant="tonal" onClick={() => onSpend(goal.id, goal.saved, goal.name)}>
                        {t('allowance.spendGoal')}
                    </Button>
                )}
                <Button variant="text" tone="danger" onClick={() => onClose(goal.id)}>
                    {t('allowance.giveUpGoal')}
                </Button>
            </div>
            )}
        </Card>
    );
}

function GoalBoard({ goals, readOnly = false, onPlan, onPutAside, onTakeBack, onSpend, onClose }) {
    const { t, i18n } = useTranslation();

    if (!goals) {
        return null;
    }

    return (
        <section className="goals" data-testid="goals">
            {goals.weeklyPace > 0 && (
                <p className="goals__pace">
                    {t('allowance.weeklyPace', {
                        amount: formatMoney(goals.weeklyPace, goals.currency, i18n.language),
                    })}
                </p>
            )}

            {goals.goals.length === 0 && <p className="empty-hint">{t('allowance.noGoalsYet')}</p>}

            {goals.goals.map((goal) => (
                <GoalRow
                    key={goal.id}
                    goal={goal}
                    currency={goals.currency}
                    readOnly={readOnly}
                    onPutAside={onPutAside}
                    onTakeBack={onTakeBack}
                    onSpend={onSpend}
                    onClose={onClose}
                />
            ))}

            {!readOnly && <GoalForm onPlan={onPlan} />}
        </section>
    );
}

export default GoalBoard;
