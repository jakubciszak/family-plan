import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog } from '../md3';
import service from '../../services/dayPlanningService';

export default function DayTagManager({ tags, team, onChanged, onClose }) {
    const { t } = useTranslation();
    const empty = { name: '', color: '#226a4c', scope: 'PERSONAL', teamId: null };
    const [draft, setDraft] = useState(empty);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const change = (fields) => setDraft((value) => ({ ...value, ...fields }));
    const save = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        try { await service.saveTag({ ...draft, name: draft.name.trim(), teamId: draft.scope === 'TEAM' ? team.id : null }); await onChanged(); setDraft(empty); }
        catch (failure) { setError(failure.response?.data?.message || t('dayPlanning.tagError')); }
        finally { setBusy(false); }
    };
    const remove = async (tag) => {
        if (!window.confirm(t('dayPlanning.archiveTag', { name: tag.name }))) return;
        setBusy(true);
        setError('');
        try { await service.archiveTag(tag.id); await onChanged(); if (draft.id === tag.id) setDraft(empty); }
        catch (failure) { setError(failure.response?.data?.message || t('dayPlanning.tagError')); }
        finally { setBusy(false); }
    };
    return <Dialog open onClose={busy ? undefined : onClose} headline={t('dayPlanning.manageTags')} actions={<Button type="button" variant="text" disabled={busy} onClick={onClose}>{t('dayPlanning.close')}</Button>}>
        <div className="day-planning day-tag-manager">
            <ul className="day-tag-list">{tags.map((tag) => <li key={tag.id}><span><span className="day-dot" style={{ background: tag.color }} />{tag.name}<small>{t(tag.scope === 'TEAM' ? 'dayPlanning.teamTag' : 'dayPlanning.personalTag')}</small></span>{tag.canEdit && <span className="day-actions"><Button type="button" variant="text" disabled={busy} onClick={() => setDraft(tag)} aria-label={t('dayPlanning.editTag', { name: tag.name })}>{t('dayPlanning.edit')}</Button><Button type="button" variant="text" disabled={busy} onClick={() => remove(tag)} aria-label={t('dayPlanning.deleteTag', { name: tag.name })}>{t('common.delete')}</Button></span>}</li>)}</ul>
            {!tags.length && <p className="day-hint">{t('dayPlanning.noTags')}</p>}
            <form onSubmit={save}><fieldset disabled={busy} className="day-fields"><h3>{t(draft.id ? 'dayPlanning.editTagTitle' : 'dayPlanning.newTag')}</h3><div className="day-row"><label className="day-field">{t('dayPlanning.tagName')}<input required maxLength={60} value={draft.name} onChange={(e) => change({ name: e.target.value })} /></label><label className="day-field day-color">{t('dayPlanning.color')}<input type="color" value={draft.color} onChange={(e) => change({ color: e.target.value })} /></label></div><label className="day-field">{t('dayPlanning.tagScope')}<select aria-label={t('dayPlanning.tagScope')} disabled={!!draft.id} value={draft.scope} onChange={(e) => change({ scope: e.target.value })}><option value="PERSONAL">{t('dayPlanning.personalTag')}</option>{(team?.role === 'admin' || draft.scope === 'TEAM') && <option value="TEAM">{t('dayPlanning.teamTag')}</option>}</select></label><div className="day-actions"><Button type="submit">{t('common.save')}</Button>{draft.id && <Button type="button" variant="text" onClick={() => setDraft(empty)}>{t('common.cancel')}</Button>}</div></fieldset></form>
            {error && <p role="alert" className="day-alert">{error}</p>}
        </div>
    </Dialog>;
}
