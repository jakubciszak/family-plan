import React from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog, Icon } from '../md3';
import { addDays, capitalize, dateInZone, displayDate, displayTime } from '../../services/dayPlanningTime';

export default function DayEventDetail({ detail, zone, nameOf, organizer, scope, onScope, busy, error, confirmation, onEdit, onDelete, onParticipate, onDismissConfirmation, onReload, onClose }) {
    const { t, i18n } = useTranslation();
    const startDate = dateInZone(detail.start, detail.allDay ? detail.timeZone : zone);
    const endDate = detail.allDay ? addDays(dateInZone(detail.end, detail.timeZone), -1) : dateInZone(detail.end, zone);
    const day = (value) => displayDate(value, i18n.language, { weekday: 'long' });
    const hours = detail.allDay ? t('dayPlanning.allDay') : `${displayTime(detail.start, i18n.language, zone)}–${displayTime(detail.end, i18n.language, zone)}`;
    const scopeChoice = detail.recurring && (detail.canEdit || detail.canChangeParticipation);
    return <Dialog open onClose={busy ? undefined : onClose} headline={detail.title} actions={<Button type="button" variant="text" disabled={busy} onClick={onClose}>{t('dayPlanning.close')}</Button>}>
        <div className="day-planning day-detail">
            <p className="day-detail-when">{capitalize(day(startDate))}{endDate !== startDate ? ` – ${day(endDate)}` : ''} · {hours}</p>
            <ul className="day-detail-meta">
                <li><Icon name={detail.visibility === 'PRIVATE' ? 'lock' : 'teams'} size={18} />{t(detail.visibility === 'PRIVATE' ? 'dayPlanning.private' : 'dayPlanning.teamVisible')}</li>
                {detail.recurring && <li><Icon name="repeat" size={18} />{t('dayPlanning.repeats')}</li>}
                {detail.location && <li><Icon name="location" size={18} /><span className="md-visually-hidden">{t('dayPlanning.location')}: </span>{detail.location}</li>}
                {detail.timeZone !== zone && <li><Icon name="language" size={18} />{detail.timeZone}</li>}
            </ul>
            {detail.description && <p className="day-description">{detail.description}</p>}
            {detail.tags?.length > 0 && <div className="day-chips">{detail.tags.map((tag) => <span key={tag.id} className="day-chip"><span className="day-dot" style={{ background: tag.color }} />{tag.name}</span>)}</div>}
            <div className="day-detail-people"><h3>{t('dayPlanning.participants')}</h3><ul>{detail.participants?.map((person) => <li key={person.personId} className={person.status === 'DECLINED' ? 'is-declined' : ''}>{nameOf(person.personId)}{person.status === 'DECLINED' && <small> · {t('dayPlanning.declined')}</small>}</li>)}</ul></div>
            {detail.ownerId && !detail.participantIds?.includes(detail.ownerId) && <p className="day-hint">{t('dayPlanning.scheduledBy', { name: organizer || nameOf(detail.ownerId) })}</p>}
            {scopeChoice && <fieldset className="day-scope" disabled={busy}><legend>{t('dayPlanning.changeScope')}</legend><div className="day-segmented">{['occurrence', 'series'].map((value) => <label key={value}><input type="radio" name="day-detail-scope" value={value} checked={scope === value} onChange={() => onScope(value)} />{t(value === 'occurrence' ? 'dayPlanning.thisOccurrence' : 'dayPlanning.wholeSeries')}</label>)}</div></fieldset>}
            {(detail.canEdit || detail.canChangeParticipation) && <div className="day-actions">
                {detail.canEdit && <><Button type="button" icon="edit" disabled={busy} onClick={onEdit}>{t('dayPlanning.edit')}</Button><Button type="button" variant="text" disabled={busy} onClick={onDelete}>{t('common.delete')}</Button></>}
                {detail.canChangeParticipation && <Button type="button" variant="outlined" disabled={busy} onClick={() => onParticipate()}>{t(detail.participation === 'DECLINED' ? 'dayPlanning.rejoin' : 'dayPlanning.decline')}</Button>}
            </div>}
            {confirmation && <div role="alert" className="day-confirmation"><h3>{t('dayPlanning.conflicts')}</h3><p>{t('dayPlanning.conflictsHint')}</p><div className="day-actions"><Button type="button" disabled={busy} onClick={() => onParticipate(confirmation)}>{t('dayPlanning.confirmRejoin')}</Button><Button type="button" variant="text" disabled={busy} onClick={onDismissConfirmation}>{t('common.cancel')}</Button></div></div>}
            {error && <div role="alert" className="day-alert">{error}<Button type="button" variant="text" disabled={busy} onClick={onReload}>{t('dayPlanning.reload')}</Button></div>}
        </div>
    </Dialog>;
}
