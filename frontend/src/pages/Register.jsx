import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import { Button, Icon, TextField } from '../components/md3';
import LanguageSwitcher from '../components/LanguageSwitcher';

function Register({ onBackToLogin, onLogin, inviteToken }) {
    const { t } = useTranslation();
    const [name, setName] = React.useState('');
    const [email, setEmail] = React.useState('');
    const [password, setPassword] = React.useState('');
    const [phoneNumber, setPhoneNumber] = React.useState('');
    const [error, setError] = React.useState('');
    const [success, setSuccess] = React.useState('');
    const [submitting, setSubmitting] = React.useState(false);

    const isInvitedRegistration = !!inviteToken;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');
        setSubmitting(true);

        try {
            const payload = { name, email, password };

            if (phoneNumber.trim()) {
                payload.phoneNumber = phoneNumber;
            }

            const data = await apiClient.post('/api/auth/register', payload);

            if (data?.activationRequired === false && onLogin) {
                try {
                    const session = await apiClient.post('/api/auth/login', { email, password });
                    onLogin(session.user);
                    return;
                } catch {
                    // konto powstalo, ale zalogowanie sie nie udalo - zostaje recznie
                }
            }

            setSuccess(data?.activationRequired === false
                ? t('auth.registerSuccessNoActivation')
                : t('auth.registerSuccess'));

            setName('');
            setEmail('');
            setPassword('');
            setPhoneNumber('');
        } catch (err) {
            if (err.response?.data?.error?.includes('already exists')) {
                setError(t('auth.duplicateEmail'));
            } else {
                setError(t('auth.registerError'));
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="login-container">
            <div className="login-form">
                <div className="login-form__brand">
                    <span className="login-form__mark">
                        <Icon name="person" size={32} />
                    </span>
                    <h2>{t('auth.register')}</h2>
                </div>

                {isInvitedRegistration && (
                    <div className="invite-banner">
                        <p>{t('auth.invitedToTeam')}</p>
                        <small>{t('auth.registerToJoin')}</small>
                    </div>
                )}

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

                <form onSubmit={handleSubmit}>
                    <TextField
                        id="name"
                        type="text"
                        label={t('auth.name')}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        autoComplete="name"
                        required
                    />
                    <TextField
                        id="email"
                        type="email"
                        label={t('auth.email')}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        autoComplete="email"
                        required
                    />
                    <TextField
                        id="password"
                        type="password"
                        label={t('auth.password')}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        autoComplete="new-password"
                        required
                    />
                    <TextField
                        id="phoneNumber"
                        type="tel"
                        label={t('auth.phoneNumberOptional')}
                        value={phoneNumber}
                        onChange={(e) => setPhoneNumber(e.target.value)}
                        autoComplete="tel"
                    />
                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.register')}
                    </Button>
                </form>

                <p className="auth-switch">
                    {t('auth.alreadyHaveAccount')}{' '}
                    <a href="#" onClick={(e) => { e.preventDefault(); onBackToLogin(); }}>
                        {t('auth.login')}
                    </a>
                </p>

                <div className="auth-footer">
                    <LanguageSwitcher />
                </div>
            </div>
        </div>
    );
}

export default Register;
