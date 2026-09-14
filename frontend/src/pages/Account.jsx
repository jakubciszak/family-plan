import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import { Button, Chip, Icon, TextField } from '../components/md3';
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

    const isAdmin = user?.role === 'ROLE_ADMIN';
    const roleLabel = isAdmin ? t('account.roleAdmin') : t('account.roleUser');

    return (
        <div className="account-page">
            <h2>{t('account.title')}</h2>

            <section className="account-card">
                <div className="account-identity">
                    <span className="md-avatar md-avatar--large" aria-hidden="true">
                        {user?.name?.trim()?.charAt(0) || '?'}
                    </span>
                    <span className="account-identity__text">
                        <span className="account-identity__name">{user?.name}</span>
                        <span className="account-identity__email">{user?.email}</span>
                    </span>
                </div>

                <h3><Icon name="info" size={20} />{t('account.details')}</h3>
                <dl className="account-details">
                    <dt>{t('account.name')}</dt>
                    <dd>{user?.name}</dd>
                    <dt>{t('account.email')}</dt>
                    <dd>{user?.email}</dd>
                    <dt>{t('account.role')}</dt>
                    <dd>
                        <Chip icon={isAdmin ? 'admin' : 'person'} tone={isAdmin ? 'primary' : 'tonal'}>
                            {roleLabel}
                        </Chip>
                    </dd>
                    <dt>{t('account.points')}</dt>
                    <dd>
                        <Chip icon="stars" tone="success">{points ?? 0}</Chip>
                    </dd>
                </dl>
            </section>

            <section className="account-card">
                <h3><Icon name="key" size={20} />{t('account.changePassword')}</h3>

                {error && (
                    <div className="error-message" role="alert">
                        <Icon name="error" size={20} />
                        <span>{error}</span>
                    </div>
                )}
                {success && (
                    <div className="success-message" role="status">
                        <Icon name="checkCircle" size={20} />
                        <span>{success}</span>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="account-form">
                    <TextField
                        id="currentPassword"
                        type="password"
                        label={t('account.currentPassword')}
                        value={currentPassword}
                        onChange={(e) => setCurrentPassword(e.target.value)}
                        autoComplete="current-password"
                        required
                    />
                    <TextField
                        id="newPassword"
                        type="password"
                        label={t('account.newPassword')}
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                        autoComplete="new-password"
                        minLength={8}
                        required
                    />
                    <TextField
                        id="confirmPassword"
                        type="password"
                        label={t('account.confirmPassword')}
                        value={confirmPassword}
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        autoComplete="new-password"
                        minLength={8}
                        required
                    />
                    <Button type="submit" icon="check" loading={saving}>
                        {saving ? t('common.saving') : t('account.changePassword')}
                    </Button>
                </form>
            </section>
        </div>
    );
}

export default Account;
