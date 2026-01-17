import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';

function Login({ onLogin, onSwitchToRegister }) {
    const { t } = useTranslation();
    const [email, setEmail] = React.useState('');
    const [password, setPassword] = React.useState('');
    const [error, setError] = React.useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        try {
            const data = await apiClient.post('/api/auth/login', { email, password });
            onLogin(data.user);
        } catch (err) {
            setError(t('auth.loginError'));
        }
    };

    return (
        <div className="login-container">
            <div className="login-form">
                <h2>{t('auth.login')}</h2>
                {error && <div className="error-message">{error}</div>}
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label htmlFor="email">{t('auth.email')}</label>
                        <input
                            type="email"
                            id="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                        />
                    </div>
                    <div className="form-group">
                        <label htmlFor="password">{t('auth.password')}</label>
                        <input
                            type="password"
                            id="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                        />
                    </div>
                    <button type="submit" className="btn-primary">{t('auth.login')}</button>
                </form>
                <p className="auth-switch">
                    {t('auth.noAccount')} <a href="#" onClick={(e) => { e.preventDefault(); onSwitchToRegister(); }}>{t('auth.register')}</a>
                </p>
            </div>
        </div>
    );
}

export default Login;
