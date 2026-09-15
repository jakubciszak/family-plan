import React from 'react';
import { useTranslation } from 'react-i18next';
import pushService from '../services/pushService';
import { Button, Icon, Switch } from './md3';

const REASONS = {
    unsupported: 'push.unsupported',
    not_configured: 'push.notConfigured',
    denied: 'push.denied',
};

function PushDeviceSetting() {
    const { t } = useTranslation();
    const [enabled, setEnabled] = React.useState(false);
    const [busy, setBusy] = React.useState(true);
    const [notice, setNotice] = React.useState(null);

    const supported = pushService.isSupported();
    const needsInstall = pushService.needsHomeScreenInstall();

    React.useEffect(() => {
        if (!supported) {
            setBusy(false);
            return;
        }

        pushService.isEnabled()
            .then(setEnabled)
            .catch(() => setEnabled(false))
            .finally(() => setBusy(false));
    }, [supported]);

    const toggle = async () => {
        setBusy(true);
        setNotice(null);

        try {
            if (enabled) {
                await pushService.disable();
                setEnabled(false);
            } else {
                await pushService.enable();
                setEnabled(true);
            }
        } catch (error) {
            setNotice(REASONS[error?.message] || 'push.failed');
            setEnabled(await pushService.isEnabled().catch(() => false));
        } finally {
            setBusy(false);
        }
    };

    const sendTest = async () => {
        setBusy(true);
        setNotice(null);

        try {
            await pushService.sendTest();
            setNotice('push.testSent');
        } catch {
            setNotice('push.testFailed');
        } finally {
            setBusy(false);
        }
    };

    if (!supported) {
        return (
            <div className="setting-item">
                <div className="setting-info">
                    <span className="setting-title">{t('push.title')}</span>
                    <span className="setting-description">{t('push.unsupported')}</span>
                </div>
            </div>
        );
    }

    return (
        <>
            <div className="setting-item">
                <div className="setting-info">
                    <span className="setting-title" id="push-device-label">{t('push.title')}</span>
                    <span className="setting-description">
                        {needsInstall ? t('push.iosInstall') : t('push.description')}
                    </span>
                </div>
                <Switch
                    id="push-device"
                    checked={enabled}
                    disabled={busy || needsInstall}
                    onChange={toggle}
                    labelledBy="push-device-label"
                />
            </div>

            {notice && (
                <p className="setting-description" role="status">
                    <Icon name="info" size={16} />
                    {t(notice)}
                </p>
            )}

            {enabled && (
                <div className="settings-actions">
                    <Button variant="outlined" icon="notifications" onClick={sendTest} loading={busy}>
                        {t('push.test')}
                    </Button>
                </div>
            )}
        </>
    );
}

export default PushDeviceSetting;
