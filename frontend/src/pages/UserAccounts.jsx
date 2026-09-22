import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import userAccountService from '../services/userAccountService';
import { Button, Dialog, Icon, TextField, CircularProgress } from '../components/md3';
import '../styles/user-accounts.css';

const MIN_PASSWORD_LENGTH = 8;

function UserAccounts({ user }) {
    const { t } = useTranslation();
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [target, setTarget] = useState(null);
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [formError, setFormError] = useState(null);
    const [saving, setSaving] = useState(false);

    const isAdmin = user?.role === 'ROLE_ADMIN';

    const load = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);
            setAccounts(await userAccountService.list());
        } catch (err) {
            console.error('Failed to load user accounts:', err);
            setError(t('userAccounts.loadError'));
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        if (isAdmin) {
            load();
        } else {
            setLoading(false);
        }
    }, [isAdmin, load]);

    const openReset = (account) => {
        setTarget(account);
        setNewPassword('');
        setConfirmPassword('');
        setFormError(null);
        setSuccess(null);
    };

    const closeReset = () => {
        setTarget(null);
        setNewPassword('');
        setConfirmPassword('');
        setFormError(null);
    };

    const submitReset = async () => {
        if (newPassword.length < MIN_PASSWORD_LENGTH) {
            setFormError(t('userAccounts.passwordTooShort', { count: MIN_PASSWORD_LENGTH }));
            return;
        }

        if (newPassword !== confirmPassword) {
            setFormError(t('userAccounts.passwordsDoNotMatch'));
            return;
        }

        try {
            setSaving(true);
            setFormError(null);
            await userAccountService.resetPassword(target.id, newPassword);
            setSuccess(t('userAccounts.passwordReset', { name: target.name }));
            closeReset();
        } catch (err) {
            console.error('Failed to reset password:', err);
            setFormError(err?.response?.status === 403
                ? t('userAccounts.forbidden')
                : t('userAccounts.resetError'));
        } finally {
            setSaving(false);
        }
    };

    if (!isAdmin) {
        return (
            <div className="access-denied">
                <Icon name="lock" size={48} />
                <h2>{t('userAccounts.accessDeniedTitle')}</h2>
                <p>{t('userAccounts.accessDeniedBody')}</p>
            </div>
        );
    }

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    return (
        <div className="user-accounts">
            <h2>{t('userAccounts.title')}</h2>
            <p className="user-accounts__description">{t('userAccounts.description')}</p>

            {error && (
                <div className="alert alert-error" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {success && (
                <div className="alert alert-success" role="status">
                    <Icon name="checkCircle" size={20} />
                    <span>{success}</span>
                </div>
            )}

            <ul className="user-accounts__list">
                {accounts.map((account) => (
                    <li key={account.id} className="user-accounts__item">
                        <div className="user-accounts__identity">
                            <span className="user-accounts__name">{account.name}</span>
                            <span className="user-accounts__email">{account.email}</span>
                        </div>
                        <Button
                            variant="outlined"
                            onClick={() => openReset(account)}
                        >
                            <Icon name="key" size={18} />
                            {t('userAccounts.resetPassword')}
                        </Button>
                    </li>
                ))}
            </ul>

            <Dialog
                open={target !== null}
                onClose={closeReset}
                headline={t('userAccounts.resetHeadline', { name: target?.name ?? '' })}
                actions={(
                    <>
                        <Button variant="text" onClick={closeReset} disabled={saving}>
                            {t('common.cancel')}
                        </Button>
                        <Button variant="filled" onClick={submitReset} disabled={saving}>
                            {saving ? t('common.saving') : t('userAccounts.confirmReset')}
                        </Button>
                    </>
                )}
            >
                <p className="user-accounts__warning">
                    {t('userAccounts.resetWarning', { email: target?.email ?? '' })}
                </p>

                {formError && (
                    <div className="alert alert-error" role="alert">
                        <Icon name="error" size={20} />
                        <span>{formError}</span>
                    </div>
                )}

                <TextField
                    id="resetNewPassword"
                    type="password"
                    label={t('userAccounts.newPassword')}
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    autoComplete="new-password"
                />

                <TextField
                    id="resetConfirmPassword"
                    type="password"
                    label={t('userAccounts.confirmPassword')}
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    autoComplete="new-password"
                />
            </Dialog>
        </div>
    );
}

export default UserAccounts;
