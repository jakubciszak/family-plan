import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';
import { Button, Icon, Switch, TextField } from '../components/md3';
import LanguageSwitcher from '../components/LanguageSwitcher';

function Login({ onLogin, onSwitchToRegister, inviteToken }) {
    const { t } = useTranslation();
    const [email, setEmail] = React.useState('');
    const [password, setPassword] = React.useState('');
    const [rememberMe, setRememberMe] = React.useState(true);
    const [error, setError] = React.useState('');
    const [submitting, setSubmitting] = React.useState(false);

    const hasInvitation = !!inviteToken;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);

        try {
            const data = await apiClient.post('/api/auth/login', { email, password, _remember_me: rememberMe });
            onLogin(data.user);
        } catch (err) {
            setError(t('auth.loginError'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="login-container">
            <div className="login-form">
                <div className="login-form__brand">
                    <span className="login-form__mark">
                        <Icon name="checkCircle" size={32} />
                    </span>
                    <h2>{t('auth.login')}</h2>
                </div>

                {hasInvitation && (
                    <div className="invite-banner">
                        <p>{t('auth.invitedToTeam')}</p>
                        <small>{t('auth.loginToJoin')}</small>
                    </div>
                )}

                {error && (
                    <div className="error-message" role="alert">
                        <Icon name="error" size={20} />
                        <span>{error}</span>
                    </div>
                )}

                <form onSubmit={handleSubmit}>
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
                        autoComplete="current-password"
                        required
                    />
                    <div className="login-remember">
                        <span className="login-remember__label" id="remember-me-label">
                            {t('auth.rememberMe')}
                        </span>
                        <Switch
                            id="remember-me"
                            checked={rememberMe}
                            onChange={setRememberMe}
                            labelledBy="remember-me-label"
                        />
                    </div>
                    <Button type="submit" fullWidth loading={submitting}>
                        {t('auth.login')}
                    </Button>
                </form>

                <p className="auth-switch">
                    {t('auth.noAccount')}{' '}
                    <a href="#" onClick={(e) => { e.preventDefault(); onSwitchToRegister(); }}>
                        {t('auth.register')}
                    </a>
                </p>

                <div className="auth-footer">
                    <LanguageSwitcher />
                </div>
            </div>
        </div>
    );
}

export default Login;
