import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import userSettingsService from '../services/userSettingsService';
import '../styles/settings.css';

function UserSettings({ user }) {
    const { t } = useTranslation();
    const [preferences, setPreferences] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(false);

    // Load user settings
    useEffect(() => {
        if (user?.id) {
            loadSettings();
        }
    }, [user]);

    const loadSettings = async () => {
        try {
            setLoading(true);
            setError(null);
            const data = await userSettingsService.getUserSettings(user.id);
            
            // Find notification preferences or use defaults
            const notificationPrefs = data.preferences?.find(p => p.type === 'notifications');
            if (notificationPrefs) {
                setPreferences(notificationPrefs.options);
            } else {
                // Default preferences
                setPreferences([
                    { name: 'email', enabled: true },
                    { name: 'sms', enabled: false }
                ]);
            }
        } catch (err) {
            console.error('Failed to load settings:', err);
            // Set default preferences on error
            setPreferences([
                { name: 'email', enabled: true },
                { name: 'sms', enabled: false }
            ]);
        } finally {
            setLoading(false);
        }
    };

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
            
            await userSettingsService.updateUserSettings(
                user.id,
                'notifications',
                preferences
            );
            
            setSuccess(true);
            setTimeout(() => setSuccess(false), 3000);
        } catch (err) {
            console.error('Failed to save settings:', err);
            setError(t('settings.save_error') || 'Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="settings-container">
                <div className="loading">{t('settings.loading') || 'Loading settings...'}</div>
            </div>
        );
    }

    return (
        <div className="settings-container">
            <h2>{t('settings.title') || 'Notification Settings'}</h2>
            
            {error && (
                <div className="alert alert-error">
                    {error}
                </div>
            )}
            
            {success && (
                <div className="alert alert-success">
                    {t('settings.save_success') || 'Settings saved successfully!'}
                </div>
            )}

            <div className="settings-section">
                <h3>{t('settings.notification_channels') || 'Notification Channels'}</h3>
                <p className="settings-description">
                    {t('settings.channels_description') || 'Choose how you want to receive notifications'}
                </p>

                <div className="settings-options">
                    {preferences?.map((option) => (
                        <div key={option.name} className="setting-item">
                            <div className="setting-info">
                                <label htmlFor={`option-${option.name}`}>
                                    <strong>
                                        {t(`settings.channel_${option.name}`) || option.name.toUpperCase()}
                                    </strong>
                                </label>
                                <span className="setting-description">
                                    {t(`settings.channel_${option.name}_desc`) || 
                                        `Receive notifications via ${option.name}`}
                                </span>
                            </div>
                            <label className="toggle-switch">
                                <input
                                    type="checkbox"
                                    id={`option-${option.name}`}
                                    checked={option.enabled}
                                    onChange={() => handleToggle(option.name)}
                                />
                                <span className="toggle-slider"></span>
                            </label>
                        </div>
                    ))}
                </div>
            </div>

            <div className="settings-actions">
                <button 
                    onClick={handleSave}
                    disabled={saving}
                    className="btn btn-primary"
                >
                    {saving ? (t('settings.saving') || 'Saving...') : (t('settings.save') || 'Save Settings')}
                </button>
            </div>
        </div>
    );
}

export default UserSettings;
