import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import notificationPolicyService from '../services/notificationPolicyService';
import { Button, Icon, Switch, CircularProgress } from '../components/md3';
import '../styles/notification-events.css';

const CHANNEL_ICONS = {
    email: 'mail',
    sms: 'send',
    in_app: 'notifications',
    push: 'phone',
};

function NotificationEvents({ user }) {
    const { t } = useTranslation();
    const [channels, setChannels] = useState([]);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    const load = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            const data = await notificationPolicyService.getPolicies();
            setChannels(data.channels ?? []);
            setEvents(data.events ?? []);
        } catch (err) {
            console.error('Failed to load notification policies:', err);
            setError(t('notificationEvents.loadError'));
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

    const toggleChannel = (eventName, channel) => {
        setSuccess(false);
        setEvents(prev => prev.map(event => {
            if (event.event !== eventName || !event.configurable) {
                return event;
            }

            const next = event.channels.includes(channel)
                ? event.channels.filter(name => name !== channel)
                : [...event.channels, channel];

            return { ...event, channels: channels.filter(name => next.includes(name)) };
        }));
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            setError(null);
            setSuccess(false);

            await Promise.all(
                events
                    .filter(event => event.configurable)
                    .map(event => notificationPolicyService.updatePolicy(event.event, event.channels))
            );

            setSuccess(true);
            setTimeout(() => setSuccess(false), 3000);
        } catch (err) {
            console.error('Failed to save notification policies:', err);
            setError(t('notificationEvents.saveError'));
        } finally {
            setSaving(false);
        }
    };

    if (user?.role !== 'ROLE_ADMIN') {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('notificationEvents.accessDeniedTitle')}</h2>
                <p>{t('notificationEvents.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    return (
        <div className="notification-events">
            <h2>{t('notificationEvents.title')}</h2>
            <p className="notification-events__description">{t('notificationEvents.description')}</p>

            {error && (
                <div className="alert alert-error" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {success && (
                <div className="alert alert-success" role="status">
                    <Icon name="checkCircle" size={20} />
                    <span>{t('notificationEvents.saveSuccess')}</span>
                </div>
            )}

            <div className="notification-events__matrix" role="table">
                <div className="notification-events__row notification-events__row--head" role="row">
                    <span role="columnheader">{t('notificationEvents.event')}</span>
                    {channels.map(channel => (
                        <span key={channel} role="columnheader" className="notification-events__channel">
                            <Icon name={CHANNEL_ICONS[channel] ?? 'notifications'} size={18} />
                            {t(`notificationEvents.channels.${channel}`)}
                        </span>
                    ))}
                </div>

                {events.map(event => (
                    <div key={event.event} className="notification-events__row" role="row">
                        <span className="notification-events__event" role="rowheader">
                            <span className="notification-events__event-name" id={`event-${event.event}`}>
                                {t(`notificationEvents.events.${event.event}`)}
                            </span>
                            <span className="notification-events__event-hint">
                                {event.configurable
                                    ? t(`notificationEvents.hints.${event.event}`)
                                    : t('notificationEvents.transactional')}
                            </span>
                        </span>

                        {channels.map(channel => (
                            <span key={channel} className="notification-events__cell" role="cell">
                                <Switch
                                    id={`policy-${event.event}-${channel}`}
                                    checked={event.channels.includes(channel)}
                                    disabled={!event.configurable}
                                    onChange={() => toggleChannel(event.event, channel)}
                                    label={`${t(`notificationEvents.events.${event.event}`)} - ${t(`notificationEvents.channels.${channel}`)}`}
                                />
                            </span>
                        ))}
                    </div>
                ))}
            </div>

            <div className="notification-events__actions">
                <Button icon="check" onClick={handleSave} loading={saving}>
                    {saving ? t('notificationEvents.saving') : t('notificationEvents.save')}
                </Button>
            </div>
        </div>
    );
}

export default NotificationEvents;
