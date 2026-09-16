import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import pushService from '../services/pushService';
import { Button, Icon, TextField, CircularProgress } from '../components/md3';
import '../styles/push-announcement.css';

const EVERYONE = 'everyone';

function PushAnnouncement({ user }) {
    const { t } = useTranslation();
    const [people, setPeople] = useState([]);
    const [recipient, setRecipient] = useState(EVERYONE);
    const [title, setTitle] = useState('');
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const [sentTo, setSentTo] = useState(null);

    const load = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const data = await pushService.audience();
            setPeople(data.users ?? []);
        } catch (err) {
            console.error('Failed to load push audience:', err);
            setError(t('pushAnnouncement.loadError'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        if (user?.role === 'ROLE_ADMIN') {
            load();
        } else {
            setLoading(false);
        }
    }, [user, load]);

    const reachable = people.filter((person) => person.devices > 0).length;

    const handleSend = async (event) => {
        event.preventDefault();

        try {
            setSending(true);
            setError(null);
            setSentTo(null);

            const result = await pushService.announce({
                message: message.trim(),
                title: title.trim(),
                userId: recipient === EVERYONE ? null : recipient,
            });

            setSentTo(result?.recipients ?? 0);
            setMessage('');
            setTitle('');
            await load();
        } catch (err) {
            console.error('Failed to send push announcement:', err);
            setError(err?.response?.status === 409
                ? t('pushAnnouncement.nobodyReachable')
                : t('pushAnnouncement.sendError'));
        } finally {
            setSending(false);
        }
    };

    if (user?.role !== 'ROLE_ADMIN') {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('pushAnnouncement.accessDeniedTitle')}</h2>
                <p>{t('pushAnnouncement.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    return (
        <div className="push-announcement">
            <h2>{t('pushAnnouncement.title')}</h2>
            <p className="push-announcement__description">{t('pushAnnouncement.description')}</p>

            {error && (
                <div className="alert alert-error" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {sentTo !== null && (
                <div className="alert alert-success" role="status">
                    <Icon name="checkCircle" size={20} />
                    <span>{t('pushAnnouncement.sent', { count: sentTo })}</span>
                </div>
            )}

            <form className="push-announcement__form" onSubmit={handleSend}>
                <TextField
                    id="push-recipient"
                    as="select"
                    label={t('pushAnnouncement.recipient')}
                    value={recipient}
                    onChange={(event) => setRecipient(event.target.value)}
                    supportingText={t('pushAnnouncement.reachable', { count: reachable })}
                >
                    <option value={EVERYONE}>{t('pushAnnouncement.everyone')}</option>
                    {people.map((person) => (
                        <option key={person.id} value={person.id} disabled={person.devices === 0}>
                            {person.devices === 0
                                ? t('pushAnnouncement.personWithoutDevice', { name: person.name })
                                : person.name}
                        </option>
                    ))}
                </TextField>

                <TextField
                    id="push-title"
                    label={t('pushAnnouncement.messageTitle')}
                    value={title}
                    maxLength={80}
                    onChange={(event) => setTitle(event.target.value)}
                    supportingText={t('pushAnnouncement.messageTitleHint')}
                />

                <TextField
                    id="push-message"
                    as="textarea"
                    rows={4}
                    label={t('pushAnnouncement.message')}
                    value={message}
                    maxLength={500}
                    required
                    onChange={(event) => setMessage(event.target.value)}
                />

                <div className="push-announcement__actions">
                    <Button type="submit" icon="send" loading={sending} disabled={message.trim() === ''}>
                        {sending ? t('pushAnnouncement.sending') : t('pushAnnouncement.send')}
                    </Button>
                </div>
            </form>
        </div>
    );
}

export default PushAnnouncement;
