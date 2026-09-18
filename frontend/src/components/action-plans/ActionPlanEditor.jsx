import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '../md3';
import ReminderSoundPicker from './ReminderSoundPicker';

const emptyStep = () => ({ name: '', stages: [] });
const move = (items, index, delta) => {
    const result = [...items];
    [result[index], result[index + delta]] = [result[index + delta], result[index]];
    return result;
};

export default function ActionPlanEditor({ plan, teams = [], onSave, onCancel, saving, error }) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState(() => ({ reminderSound: 'soft', ...(plan || { name: '', teamId: null, estimatedMinutes: null, reminderMinutes: 10, steps: [emptyStep()] }) }));
    const change = (fields) => setDraft((current) => ({ ...current, ...fields }));
    const stepChange = (index, fields) => change({ steps: draft.steps.map((step, i) => i === index ? { ...step, ...fields } : step) });
    const reorderButtons = (items, index, update, label) => <>
        <Button type="button" variant="text" disabled={index === 0} aria-label={t('actionPlans.moveUp', { name: label })} onClick={() => update(move(items, index, -1))}>↑</Button>
        <Button type="button" variant="text" disabled={index === items.length - 1} aria-label={t('actionPlans.moveDown', { name: label })} onClick={() => update(move(items, index, 1))}>↓</Button>
    </>;

    return <form className="action-plans__editor" onSubmit={(event) => { event.preventDefault(); onSave(draft); }}>
        <h2>{t(plan ? 'actionPlans.edit' : 'actionPlans.create')}</h2>
        <fieldset disabled={saving}>
            <label>{t('actionPlans.name')}<input autoFocus required maxLength={160} value={draft.name} onChange={(event) => change({ name: event.target.value })} /></label>
            <label>{t('actionPlans.visibility')}
                <select aria-label={t('actionPlans.visibility')} value={draft.teamId || ''} disabled={plan?.canChangeScope === false} onChange={(event) => change({ teamId: event.target.value || null })}>
                    <option value="">{t('actionPlans.private')}</option>
                    {teams.map((team) => <option value={team.id} key={team.id}>{team.name}</option>)}
                </select>
            </label>
            <p className="action-plans__hint">{t('actionPlans.visibilityHint')}</p>
            <div className="action-plans__settings">
                <label>{t('actionPlans.duration')}<input type="number" min="1" max="1440" step="1" value={draft.estimatedMinutes ?? ''} onChange={(event) => change({ estimatedMinutes: event.target.value === '' ? null : Number(event.target.value) })} /></label>
                {!draft.estimatedMinutes && <label>{t('actionPlans.reminderMinutes')}<input type="number" required min="1" max="120" step="1" value={draft.reminderMinutes} onChange={(event) => change({ reminderMinutes: event.target.value === '' ? '' : Number(event.target.value) })} /></label>}
            </div>
            <p className="action-plans__hint">{t(draft.estimatedMinutes ? 'actionPlans.estimateHint' : 'actionPlans.noEstimateHint')}</p>
            <ReminderSoundPicker value={draft.reminderSound} onChange={(reminderSound) => change({ reminderSound })} />
            <h3>{t('actionPlans.steps')}</h3>
            <p className="action-plans__hint">{t('actionPlans.stagesHint')}</p>
            {draft.steps.map((step, index) => <section className="action-plans__step" key={index} aria-label={t('actionPlans.stepNumber', { number: index + 1 })}>
                <div className="action-plans__row">
                    <label>{t('actionPlans.stepNumber', { number: index + 1 })}<input required maxLength={240} value={step.name} onChange={(event) => stepChange(index, { name: event.target.value })} /></label>
                    <div className="action-plans__actions">
                        {reorderButtons(draft.steps, index, (steps) => change({ steps }), step.name || `${index + 1}`)}
                        <Button type="button" variant="text" disabled={draft.steps.length === 1} aria-label={t('actionPlans.removeStep', { number: index + 1 })} onClick={() => change({ steps: draft.steps.filter((_, i) => i !== index) })}>{t('common.delete')}</Button>
                    </div>
                </div>
                {step.stages.map((stage, stageIndex) => <div className="action-plans__row action-plans__stage" key={stageIndex}>
                    <label>{t('actionPlans.stageNumber', { number: `${index + 1}.${stageIndex + 1}` })}<input required maxLength={240} value={stage} onChange={(event) => stepChange(index, { stages: step.stages.map((value, i) => i === stageIndex ? event.target.value : value) })} /></label>
                    <div className="action-plans__actions">
                        {reorderButtons(step.stages, stageIndex, (stages) => stepChange(index, { stages }), stage || `${index + 1}.${stageIndex + 1}`)}
                        <Button type="button" variant="text" aria-label={t('actionPlans.removeStage', { number: `${index + 1}.${stageIndex + 1}` })} onClick={() => stepChange(index, { stages: step.stages.filter((_, i) => i !== stageIndex) })}>{t('common.delete')}</Button>
                    </div>
                </div>)}
                <Button type="button" variant="text" disabled={step.stages.length >= 100} onClick={() => stepChange(index, { stages: [...step.stages, ''] })}>{t('actionPlans.addStage')}</Button>
            </section>)}
            <Button type="button" variant="tonal" disabled={draft.steps.length >= 100} onClick={() => change({ steps: [...draft.steps, emptyStep()] })}>{t('actionPlans.addStep')}</Button>
            {error && <p role="alert" className="action-plans__error">{error}</p>}
            <div className="action-plans__actions action-plans__footer">
                <Button type="submit" loading={saving}>{t('common.save')}</Button>
                <Button type="button" variant="text" onClick={onCancel}>{t('common.cancel')}</Button>
            </div>
        </fieldset>
    </form>;
}
