import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatMoney, fromMinorUnits, toMinorUnits } from '../../services/money';
import Button from '../md3/Button';
import Card from '../md3/Card';
import TextField from '../md3/TextField';

const ACCOUNTS = ['tasks', 'bonuses'];

const draftOf = (rule) => ({
    minimumPoints: String(rule?.minimumPoints ?? 0),
    rateAmount: fromMinorUnits(rule?.rateAmount ?? 0),
    ratePerPoints: String(rule?.ratePerPoints ?? 1),
});

function RuleCard({ pointsAccount, rule, currency, onSave, onRemove }) {
    const { t, i18n } = useTranslation();
    const [draft, setDraft] = React.useState(() => draftOf(rule));
    const [error, setError] = React.useState(null);
    const [saved, setSaved] = React.useState(false);

    React.useEffect(() => {
        setDraft(draftOf(rule));
    }, [rule]);

    const change = (field) => (event) => {
        setSaved(false);
        setDraft((current) => ({ ...current, [field]: event.target.value }));
    };

    const submit = (event) => {
        event.preventDefault();

        const rateAmount = toMinorUnits(draft.rateAmount);
        const minimumPoints = Number.parseInt(draft.minimumPoints, 10);
        const ratePerPoints = Number.parseInt(draft.ratePerPoints, 10);

        if (rateAmount === null || !Number.isInteger(minimumPoints) || minimumPoints < 0
            || !Number.isInteger(ratePerPoints) || ratePerPoints < 1) {
            setError(t('allowance.ruleInvalid'));
            return;
        }

        setError(null);

        Promise.resolve(onSave({ pointsAccount, minimumPoints, rateAmount, ratePerPoints }))
            .then(() => setSaved(true))
            .catch((failure) => setError(failure?.response?.data?.error || t('errors.generic')));
    };

    const example = toMinorUnits(draft.rateAmount);
    const per = Number.parseInt(draft.ratePerPoints, 10);

    return (
        <Card className="allowance-rule" data-testid={`rule-${pointsAccount}`}>
            <form onSubmit={submit}>
                <h4>{t(`allowance.accounts.${pointsAccount}`)}</h4>

                <TextField
                    id={`rule-${pointsAccount}-minimum`}
                    label={t('allowance.minimumPoints')}
                    inputMode="numeric"
                    value={draft.minimumPoints}
                    onChange={change('minimumPoints')}
                    supportingText={t('allowance.minimumPointsHint')}
                />

                <div className="allowance-rule__rate">
                    <TextField
                        id={`rule-${pointsAccount}-amount`}
                        label={t('allowance.rateAmount')}
                        inputMode="decimal"
                        value={draft.rateAmount}
                        onChange={change('rateAmount')}
                    />
                    <span className="allowance-rule__per">{t('allowance.forEvery')}</span>
                    <TextField
                        id={`rule-${pointsAccount}-per`}
                        label={t('allowance.ratePerPoints')}
                        inputMode="numeric"
                        value={draft.ratePerPoints}
                        onChange={change('ratePerPoints')}
                    />
                </div>

                {example !== null && per > 0 && (
                    <p className="allowance-rule__example">
                        {t('allowance.ruleExample', {
                            points: per * 10,
                            amount: formatMoney(example * 10, currency, i18n.language),
                        })}
                    </p>
                )}

                <div className="allowance-rule__actions">
                    <Button type="submit" variant="filled">{t('common.save')}</Button>
                    {rule && (
                        <Button type="button" variant="text" tone="danger" onClick={() => onRemove(pointsAccount)}>
                            {t('allowance.removeRule')}
                        </Button>
                    )}
                </div>

                {saved && <p className="form-success">{t('allowance.ruleSaved')}</p>}
                {error && <p className="form-error" role="alert">{error}</p>}
            </form>
        </Card>
    );
}

function AllowanceRulesForm({ rules, onSave, onRemove }) {
    if (!rules) {
        return null;
    }

    const byAccount = Object.fromEntries(rules.rules.map((rule) => [rule.pointsAccount, rule]));

    return (
        <section className="allowance-rules" data-testid="allowance-rules">
            {ACCOUNTS.map((pointsAccount) => (
                <RuleCard
                    key={pointsAccount}
                    pointsAccount={pointsAccount}
                    rule={byAccount[pointsAccount]}
                    currency={rules.currency}
                    onSave={onSave}
                    onRemove={onRemove}
                />
            ))}
        </section>
    );
}

export default AllowanceRulesForm;
