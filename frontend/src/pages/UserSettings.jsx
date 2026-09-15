import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import userSettingsService from '../services/userSettingsService';
import { Button, Chip, Icon, Switch, CircularProgress } from '../components/md3';
import useThemeMode, { THEME_MODES } from '../hooks/useThemeMode';
import LanguageSwitcher from '../components/LanguageSwitcher';
import PushDeviceSetting from '../components/PushDeviceSetting';
import '../styles/settings.css';

const THEME_ICONS = { light: 'lightMode', dark: 'darkMode', system: 'systemMode' };

const DEFAULT_PREFERENCES = [
    { name: 'email', enabled: true },
    { name: 'sms', enabled: false },
    { name: 'in_app', enabled: true },
    { name: 'push', enabled: true },
];

const withEveryChannel = (options) =>
    DEFAULT_PREFERENCES.map((fallback) =>
        options?.find((option) => option.name === fallback.name) ?? fallback
    );

function UserSettings({ user }) {
    const { t } = useTranslation();
    const [themeMode, setThemeMode] = useThemeMode();
    const [preferences, setPreferences] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    useEffect(() => {
        if (!user?.id) {
            return;
        }

        const loadSettings = async () => {
            try {
                setLoading(true);
                setError(null);
                const data = await userSettingsService.getUserSettings(user.id);
                const notificationPrefs = data.preferences?.find(p => p.type === 'notifications');
                setPreferences(withEveryChannel(notificationPrefs?.options));
            } catch (err) {
                console.error('Failed to load settings:', err);
                setPreferences(DEFAULT_PREFERENCES);
            } finally {
                setLoading(false);
            }
        };

        loadSettings();
    }, [user]);

    const handleToggle = (optionName) => {
        setPreferences(prev =>
            prev.map(opt =>
                opt.name === optionName
                    ? { ...opt, enabled: !opt.enabled }
                    : opt
            )
        );
        setSuccess(false);
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            setError(null);
            setSuccess(false);

            await userSettingsService.updateUserSettings(user.id, 'notifications', preferences);

            setSuccess(true);
            setTimeout(() => setSuccess(false), 3000);
        } catch (err) {
            console.error('Failed to save settings:', err);
            setError(t('settings.save_error'));
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="settings-container">
                <CircularProgress label={t('settings.loading')} />
            </div>
        );
    }

    return (
        <div className="settings-container">
            <h2>{t('nav.settings')}</h2>

            {error && (
                <div className="alert alert-error" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {success && (
                <div className="alert alert-success" role="status">
                    <Icon name="checkCircle" size={20} />
                    <span>{t('settings.save_success')}</span>
                </div>
            )}

            <section className="settings-section">
                <h3><Icon name="lightMode" size={20} />{t('theme.appearance')}</h3>
                <p className="settings-description">{t('theme.appearanceDescription')}</p>

                <div className="theme-options" role="group" aria-label={t('theme.appearance')}>
                    {THEME_MODES.map((mode) => (
                        <Chip
                            key={mode}
                            icon={THEME_ICONS[mode]}
                            selected={themeMode === mode}
                            onClick={() => setThemeMode(mode)}
                        >
                            {t(`theme.${mode}`)}
                        </Chip>
                    ))}
                </div>

                <div className="setting-item setting-item--last">
                    <div className="setting-info">
                        <span className="setting-title">{t('theme.language')}</span>
                        <span className="setting-description">{t('theme.languageDescription')}</span>
                    </div>
                    <LanguageSwitcher />
                </div>
            </section>

            <section className="settings-section">
                <h3><Icon name="notifications" size={20} />{t('settings.notification_channels')}</h3>
                <p className="settings-description">{t('settings.channels_description')}</p>

                <div className="settings-options">
                    {preferences?.map((option) => (
                        <div key={option.name} className="setting-item">
                            <div className="setting-info">
                                <span className="setting-title" id={`option-${option.name}-label`}>
                                    {t(`settings.channel_${option.name}`)}
                                </span>
                                <span className="setting-description">
                                    {t(`settings.channel_${option.name}_desc`)}
                                </span>
                            </div>
                            <Switch
                                id={`option-${option.name}`}
                                checked={option.enabled}
                                onChange={() => handleToggle(option.name)}
                                labelledBy={`option-${option.name}-label`}
                            />
                        </div>
                    ))}
                    <PushDeviceSetting />
                </div>
            </section>

            <div className="settings-actions">
                <Button icon="check" onClick={handleSave} loading={saving}>
                    {saving ? t('settings.saving') : t('settings.save')}
                </Button>
            </div>
        </div>
    );
}

export default UserSettings;
