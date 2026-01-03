import React from 'react';
import { useTranslation } from 'react-i18next';
import TaskList from './pages/TaskList';
import Login from './pages/Login';
import BonusRulesManagement from './pages/BonusRulesManagement';
import StatusChangeRulesManagement from './pages/StatusChangeRulesManagement';
import LanguageSwitcher from './components/LanguageSwitcher';
import apiClient from './services/apiClient';
import './styles/app.css';

function App() {
    const { t } = useTranslation();
    const [isAuthenticated, setIsAuthenticated] = React.useState(false);
    const [user, setUser] = React.useState(null);
    const [userPoints, setUserPoints] = React.useState(0);
    const [currentPage, setCurrentPage] = React.useState('tasks');

    React.useEffect(() => {
        // Check if user is authenticated
        apiClient.get('/api/auth/me')
            .then(data => {
                setUser(data);
                setIsAuthenticated(true);
                // Fetch user points
                return apiClient.get(`/api/users/${data.id}/points`);
            })
            .then(pointsData => {
                setUserPoints(pointsData.balance);
            })
            .catch(() => {
                setIsAuthenticated(false);
            });
    }, []);

    const handleLogin = (userData) => {
        setUser(userData);
        setIsAuthenticated(true);
        // Fetch points after login
        apiClient.get(`/api/users/${userData.id}/points`)
            .then(pointsData => {
                setUserPoints(pointsData.balance);
            })
            .catch(() => {
                setUserPoints(0);
            });
    };

    const handleLogout = () => {
        apiClient.post('/api/auth/logout', {})
            .then(() => {
                setUser(null);
                setUserPoints(0);
                setIsAuthenticated(false);
            })
            .catch(() => {
                // Even if request fails, clear local state
                setUser(null);
                setUserPoints(0);
                setIsAuthenticated(false);
            });
    };

    if (!isAuthenticated) {
        return <Login onLogin={handleLogin} />;
    }

    return (
        <div className="app">
            <header className="app-header">
                <h1>{t('app.title')}</h1>
                <nav className="app-nav">
                    <button 
                        onClick={() => setCurrentPage('tasks')}
                        className={currentPage === 'tasks' ? 'nav-active' : ''}
                    >
                        {t('nav.tasks')}
                    </button>
                    {user?.role === 'ROLE_ADMIN' && (
                        <>
                            <button 
                                onClick={() => setCurrentPage('bonus-rules')}
                                className={currentPage === 'bonus-rules' ? 'nav-active' : ''}
                            >
                                {t('nav.bonusRules')}
                            </button>
                            <button 
                                onClick={() => setCurrentPage('status-change-rules')}
                                className={currentPage === 'status-change-rules' ? 'nav-active' : ''}
                            >
                                {t('nav.statusChangeRules')}
                            </button>
                        </>
                    )}
                </nav>
                <div className="user-info">
                    <span>{t('app.welcome', { name: user?.name })}</span>
                    <span className="user-points">{t('user.points', { points: userPoints })}</span>
                    <LanguageSwitcher />
                    <button onClick={handleLogout}>{t('auth.logout')}</button>
                </div>
            </header>
            <main className="app-main">
                {currentPage === 'tasks' && <TaskList user={user} />}
                {currentPage === 'bonus-rules' && <BonusRulesManagement user={user} />}
                {currentPage === 'status-change-rules' && <StatusChangeRulesManagement user={user} />}
            </main>
        </div>
    );
}

export default App;
