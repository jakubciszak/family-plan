import React from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';

function Register({ onBackToLogin }) {
    const { t } = useTranslation();
    const [name, setName] = React.useState('');
    const [email, setEmail] = React.useState('');
    const [password, setPassword] = React.useState('');
    const [phoneNumber, setPhoneNumber] = React.useState('');
    const [error, setError] = React.useState('');
    const [success, setSuccess] = React.useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        try {
            const payload = {
                name,
                email,
                password
            };

            // Only include phoneNumber if provided
            if (phoneNumber.trim()) {
                payload.phoneNumber = phoneNumber;
            }

            await apiClient.post('/api/auth/register', payload);

            setSuccess(t('auth.registerSuccess'));

            // Clear form on success
            setName('');
            setEmail('');
            setPassword('');
            setPhoneNumber('');
        } catch (err) {
            // Check for specific error messages
            if (err.response?.data?.error?.includes('already exists')) {
                setError(t('auth.duplicateEmail'));
            } else {
                setError(t('auth.registerError'));
            }
        }
    };

    return (
        <div className="login-container">
            <div className="login-form">
                <h2>{t('auth.register')}</h2>
                {error && <div className="error-message">{error}</div>}
                {success && <div className="success-message">{success}</div>}
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label htmlFor="name">{t('auth.name')}</label>
                        <input
                            type="text"
                            id="name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                        />
                    </div>
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
                    <div className="form-group">
                        <label htmlFor="phoneNumber">{t('auth.phoneNumberOptional')}</label>
                        <input
                            type="tel"
                            id="phoneNumber"
                            value={phoneNumber}
                            onChange={(e) => setPhoneNumber(e.target.value)}
                        />
                    </div>
                    <button type="submit" className="btn-primary">{t('auth.register')}</button>
                </form>
                <p className="auth-switch">
                    {t('auth.alreadyHaveAccount')} <a href="#" onClick={(e) => { e.preventDefault(); onBackToLogin(); }}>{t('auth.login')}</a>
                </p>
            </div>
        </div>
    );
}

export default Register;
