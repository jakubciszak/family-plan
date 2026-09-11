import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import '../styles/Account.css';

function Account({ user, points }) {
    const { t } = useTranslation();
    const [currentPassword, setCurrentPassword] = React.useState('');
    const [newPassword, setNewPassword] = React.useState('');
    const [confirmPassword, setConfirmPassword] = React.useState('');
    const [error, setError] = React.useState('');
    const [success, setSuccess] = React.useState('');
    const [saving, setSaving] = React.useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        if (newPassword !== confirmPassword) {
            setError(t('account.passwordsDoNotMatch'));
            return;
        }

        if (newPassword.length < 8) {
            setError(t('account.passwordTooShort'));
            return;
        }

        setSaving(true);
        try {
            await apiClient.post('/api/auth/change-password', {
                currentPassword,
                newPassword,
            });
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
            setSuccess(t('account.passwordChanged'));
        } catch (err) {
            setError(err.response?.status === 400
                ? t('account.currentPasswordIncorrect')
                : t('account.passwordChangeError'));
        } finally {
            setSaving(false);
        }
    };

    const roleLabel = user?.role === 'ROLE_ADMIN' ? t('account.roleAdmin') : t('account.roleUser');

    return (
        <div className="account-page">
            <h2>{t('account.title')}</h2>

            <section className="account-card">
                <h3>{t('account.details')}</h3>
                <dl className="account-details">
                    <dt>{t('account.name')}</dt>
                    <dd>{user?.name}</dd>
                    <dt>{t('account.email')}</dt>
                    <dd>{user?.email}</dd>
                    <dt>{t('account.role')}</dt>
                    <dd>{roleLabel}</dd>
                    <dt>{t('account.points')}</dt>
                    <dd>{points ?? 0}</dd>
                </dl>
            </section>

            <section className="account-card">
                <h3>{t('account.changePassword')}</h3>

                {error && <div className="error-message">{error}</div>}
                {success && <div className="success-message">{success}</div>}

                <form onSubmit={handleSubmit} className="account-form">
                    <div className="form-group">
                        <label htmlFor="currentPassword">{t('account.currentPassword')}</label>
                        <input
                            id="currentPassword"
                            type="password"
                            value={currentPassword}
                            onChange={(e) => setCurrentPassword(e.target.value)}
                            autoComplete="current-password"
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="newPassword">{t('account.newPassword')}</label>
                        <input
                            id="newPassword"
                            type="password"
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="confirmPassword">{t('account.confirmPassword')}</label>
                        <input
                            id="confirmPassword"
                            type="password"
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            autoComplete="new-password"
                            minLength={8}
                            required
                        />
                    </div>
                    <button type="submit" className="btn-primary" disabled={saving}>
                        {saving ? t('common.saving') : t('account.changePassword')}
                    </button>
                </form>
            </section>
        </div>
    );
}

export default Account;
