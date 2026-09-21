import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog } from '../md3';
import service from '../../services/dayPlanningService';
import TagColorPalette from './TagColorPalette';

export default function DayTagManager({ tags, team, onChanged, onClose }) {
    const { t } = useTranslation();
    const empty = { name: '', color: '#226a4c', scope: 'PERSONAL', teamId: null };
    const [draft, setDraft] = useState(empty);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const pending = useRef(false);
    const active = useRef(true);
    useEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
    const change = (fields) => setDraft((value) => ({ ...value, ...fields }));
    const save = async (event) => {
        event.preventDefault();
        if (pending.current || !draft.name.trim()) return;
        pending.current = true;
        setBusy(true);
        setError('');
        try { await service.saveTag({ ...draft, name: draft.name.trim(), teamId: draft.scope === 'TEAM' ? draft.teamId || team?.id : null }); if (!active.current) return; await onChanged(); if (active.current) setDraft(empty); }
        catch (failure) { if (active.current) setError(failure.response?.data?.message || t('dayPlanning.tagError')); }
        finally { pending.current = false; if (active.current) setBusy(false); }
    };
    const remove = async (tag) => {
        if (pending.current || !window.confirm(t('dayPlanning.archiveTag', { name: tag.name }))) return;
        pending.current = true;
        setBusy(true);
        setError('');
        try { await service.archiveTag(tag.id); if (!active.current) return; await onChanged(); if (active.current && draft.id === tag.id) setDraft(empty); }
        catch (failure) { if (active.current) setError(failure.response?.data?.message || t('dayPlanning.tagError')); }
        finally { pending.current = false; if (active.current) setBusy(false); }
    };
    return <Dialog open onClose={busy ? undefined : onClose} headline={t('dayPlanning.manageTags')} actions={<Button type="button" variant="text" disabled={busy} onClick={onClose}>{t('dayPlanning.close')}</Button>}>
        <div className="day-planning day-tag-manager">
            <ul className="day-tag-list">{tags.map((tag) => <li key={tag.id}><span><span className="day-dot" style={{ background: tag.color }} />{tag.name}<small>{t(tag.scope === 'TEAM' ? 'dayPlanning.teamTag' : 'dayPlanning.personalTag')}</small></span>{tag.canEdit && <span className="day-actions"><Button type="button" variant="text" disabled={busy} onClick={() => setDraft(tag)} aria-label={t('dayPlanning.editTag', { name: tag.name })}>{t('dayPlanning.edit')}</Button><Button type="button" variant="text" disabled={busy} onClick={() => remove(tag)} aria-label={t('dayPlanning.deleteTag', { name: tag.name })}>{t('common.delete')}</Button></span>}</li>)}</ul>
            {!tags.length && <p className="day-hint">{t('dayPlanning.noTags')}</p>}
            <form onSubmit={save}><fieldset disabled={busy} className="day-fields"><h3>{t(draft.id ? 'dayPlanning.editTagTitle' : 'dayPlanning.newTag')}</h3><div className="day-row"><label className="day-field">{t('dayPlanning.tagName')}<input required maxLength={60} value={draft.name} onChange={(e) => change({ name: e.target.value })} /></label></div><TagColorPalette customColor={tags.find((tag) => tag.id === draft.id)?.color} value={draft.color} onChange={(color) => change({ color })} disabled={busy} /><label className="day-field">{t('dayPlanning.tagScope')}<select aria-label={t('dayPlanning.tagScope')} disabled={!!draft.id} value={draft.scope} onChange={(e) => change({ scope: e.target.value })}><option value="PERSONAL">{t('dayPlanning.personalTag')}</option>{(team?.role === 'admin' || draft.scope === 'TEAM') && <option value="TEAM">{t('dayPlanning.teamTag')}</option>}</select></label><div className="day-actions"><Button type="submit" disabled={!draft.name.trim()}>{t('common.save')}</Button>{draft.id && <Button type="button" variant="text" onClick={() => setDraft(empty)}>{t('common.cancel')}</Button>}</div></fieldset></form>
            {error && <p role="alert" className="day-alert">{error}</p>}
        </div>
    </Dialog>;
}
