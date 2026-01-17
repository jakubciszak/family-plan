import React from 'react';
import { useTranslation } from 'react-i18next';
import TaskList from './pages/TaskList';
import Login from './pages/Login';
import Register from './pages/Register';
import BonusRulesManagement from './pages/BonusRulesManagement';
import StatusChangeRulesManagement from './pages/StatusChangeRulesManagement';
import UserSettings from './pages/UserSettings';
import TeamManagement from './pages/TeamManagement';
import LanguageSwitcher from './components/LanguageSwitcher';
import apiClient from './services/apiClient';
import './styles/app.css';

function App() {
    const { t } = useTranslation();
    const [isAuthenticated, setIsAuthenticated] = React.useState(false);
    const [user, setUser] = React.useState(null);
    const [userPoints, setUserPoints] = React.useState(0);
    const [currentPage, setCurrentPage] = React.useState('tasks');
    const [isMobileMenuOpen, setIsMobileMenuOpen] = React.useState(false);
    const [showRegister, setShowRegister] = React.useState(false);

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
        if (showRegister) {
            return <Register onBackToLogin={() => setShowRegister(false)} />;
        }
        return <Login onLogin={handleLogin} onSwitchToRegister={() => setShowRegister(true)} />;
    }

    const handlePageChange = (page) => {
        setCurrentPage(page);
        setIsMobileMenuOpen(false);
    };

    return (
        <div className="app">
            <header className="app-header">
                <div className="header-left">
                    <h1>{t('app.title')}</h1>
                </div>
                <button
                    className="hamburger-menu"
                    onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                    aria-label="Toggle menu"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <nav className={`app-nav ${isMobileMenuOpen ? 'mobile-open' : ''}`}>
                    <button
                        onClick={() => handlePageChange('tasks')}
                        className={currentPage === 'tasks' ? 'nav-active' : ''}
                    >
                        {t('nav.tasks')}
                    </button>
                    <button
                        onClick={() => handlePageChange('teams')}
                        className={currentPage === 'teams' ? 'nav-active' : ''}
                    >
                        {t('nav.teams')}
                    </button>
                    {user?.role === 'ROLE_ADMIN' && (
                        <>
                            <button
                                onClick={() => handlePageChange('bonus-rules')}
                                className={currentPage === 'bonus-rules' ? 'nav-active' : ''}
                            >
                                {t('nav.bonusRules')}
                            </button>
                            <button
                                onClick={() => handlePageChange('status-change-rules')}
                                className={currentPage === 'status-change-rules' ? 'nav-active' : ''}
                            >
                                {t('nav.statusChangeRules')}
                            </button>
                        </>
                    )}
                    <button
                        onClick={() => handlePageChange('settings')}
                        className={currentPage === 'settings' ? 'nav-active' : ''}
                    >
                        {t('nav.settings')}
                    </button>
                </nav>
                <div className="user-info">
                    <span className="user-welcome">{t('app.welcome', { name: user?.name })}</span>
                    <span className="user-points">{t('user.points', { points: userPoints })}</span>
                    <LanguageSwitcher />
                    <button onClick={handleLogout}>{t('auth.logout')}</button>
                </div>
            </header>
            <main className="app-main">
                {currentPage === 'tasks' && <TaskList user={user} />}
                {currentPage === 'teams' && <TeamManagement user={user} />}
                {currentPage === 'bonus-rules' && <BonusRulesManagement user={user} />}
                {currentPage === 'status-change-rules' && <StatusChangeRulesManagement user={user} />}
                {currentPage === 'settings' && <UserSettings user={user} />}
            </main>
        </div>
    );
}

export default App;
