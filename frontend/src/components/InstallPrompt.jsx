import React from 'react';
import { useTranslation } from 'react-i18next';
import '../styles/InstallPrompt.css';

const DISMISSED_KEY = 'installPromptDismissed';

const wasDismissed = () => {
    try {
        return localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
};

const rememberDismissal = () => {
    try {
        localStorage.setItem(DISMISSED_KEY, '1');
    } catch {
        // storage unavailable
    }
};

function InstallPrompt() {
    const { t } = useTranslation();
    const [deferredPrompt, setDeferredPrompt] = React.useState(null);

    React.useEffect(() => {
        const onBeforeInstallPrompt = (event) => {
            event.preventDefault();
            if (!wasDismissed()) {
                setDeferredPrompt(event);
            }
        };

        const onInstalled = () => setDeferredPrompt(null);

        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    if (!deferredPrompt) {
        return null;
    }

    const install = async () => {
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        setDeferredPrompt(null);
    };

    const dismiss = () => {
        rememberDismissal();
        setDeferredPrompt(null);
    };

    return (
        <div className="install-prompt" role="region" aria-label={t('install.title')}>
            <div className="install-prompt-text">
                <strong>{t('install.title')}</strong>
                <span>{t('install.description')}</span>
            </div>
            <div className="install-prompt-actions">
                <button type="button" className="btn-primary" onClick={install}>
                    {t('install.action')}
                </button>
                <button type="button" className="install-prompt-dismiss" onClick={dismiss}>
                    {t('install.dismiss')}
                </button>
            </div>
        </div>
    );
}

export default InstallPrompt;
