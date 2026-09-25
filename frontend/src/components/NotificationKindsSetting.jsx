import React from 'react';
import { useTranslation } from 'react-i18next';
import { Switch } from './md3';

const GROUPS = ['tasks', 'calendar', 'allowance', 'streaks'];

/** One switch per kind of notification, grouped the way the app is. */
function NotificationKindsSetting({ kinds, onToggle }) {
    const { t } = useTranslation();
    const shown = kinds.filter((kind) => kind.relevant);
    const through = (channels) => channels
        .filter((channel) => channel !== 'sms')
        .map((channel) => t(`notificationEvents.channels.${channel}`))
        .join(', ');

    return (
        <div className="notification-kinds">
            {GROUPS.map((group) => {
                const inGroup = shown.filter((kind) => kind.group === group);

                if (inGroup.length === 0) {
                    return null;
                }

                return (
                    <div key={group} className="notification-kinds__group" role="group" aria-labelledby={`kinds-${group}`}>
                        <h4 id={`kinds-${group}`} className="notification-kinds__heading">{t(`notificationKinds.groups.${group}`)}</h4>
                        {inGroup.map((kind) => (
                            <div key={kind.event} className="setting-item">
                                <div className="setting-info">
                                    <span className="setting-title" id={`kind-${kind.event}-label`}>
                                        {t(`notificationKinds.events.${kind.event}`)}
                                    </span>
                                    <span className="setting-description">
                                        {t(`notificationKinds.hints.${kind.event}`)}
                                        {' '}
                                        {kind.channels.length > 0
                                            ? t('notificationKinds.through', { channels: through(kind.channels) })
                                            : t('notificationKinds.offByAdmin')}
                                    </span>
                                </div>
                                <Switch
                                    id={`kind-${kind.event}`}
                                    checked={kind.enabled}
                                    onChange={() => onToggle(kind.event)}
                                    labelledBy={`kind-${kind.event}-label`}
                                />
                            </div>
                        ))}
                    </div>
                );
            })}
        </div>
    );
}

export default NotificationKindsSetting;
