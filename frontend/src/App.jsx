import React from 'react';
import { useTranslation } from 'react-i18next';
import TaskList from './pages/TaskList';
import Login from './pages/Login';
import Register from './pages/Register';
import BonusRulesManagement from './pages/BonusRulesManagement';
import StatusChangeRulesManagement from './pages/StatusChangeRulesManagement';
import UserSettings from './pages/UserSettings';
import TeamManagement from './pages/TeamManagement';
import TaskTypeManagement from './pages/TaskTypeManagement';
import Account from './pages/Account';
import InstallPrompt from './components/InstallPrompt';
import LanguageSwitcher from './components/LanguageSwitcher';
import apiClient from './services/apiClient';
import teamService from './services/teamService';
import './styles/app.css';

// Helper to get invite token from URL
const getInviteTokenFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    return params.get('invite');
};

// Helper to clear invite token from URL
const clearInviteTokenFromUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('invite');
    window.history.replaceState({}, document.title, url.pathname + url.search);
};

const INVITE_STORAGE_KEY = 'pendingInviteToken';
const INVITE_TTL_MS = 60 * 60 * 1000;

const clearPendingInvite = () => {
    try {
        localStorage.removeItem(INVITE_STORAGE_KEY);
    } catch {
        // storage unavailable
    }
};

const storePendingInvite = (token) => {
    try {
        localStorage.setItem(INVITE_STORAGE_KEY, JSON.stringify({ token, storedAt: Date.now() }));
    } catch {
        // storage unavailable
    }
};

const readPendingInvite = () => {
    let raw = null;
    try {
        raw = localStorage.getItem(INVITE_STORAGE_KEY);
    } catch {
        return null;
    }

    if (!raw) {
        return null;
    }

    try {
        const { token, storedAt } = JSON.parse(raw);
        if (!token || !storedAt || Date.now() - storedAt > INVITE_TTL_MS) {
            clearPendingInvite();
            return null;
        }
        return token;
    } catch {
        clearPendingInvite();
        return null;
    }
};

function App() {
    const { t } = useTranslation();
    const [isAuthenticated, setIsAuthenticated] = React.useState(false);
    const [user, setUser] = React.useState(null);
    const [userPoints, setUserPoints] = React.useState(0);
    const [currentPage, setCurrentPage] = React.useState('tasks');
    const [isMobileMenuOpen, setIsMobileMenuOpen] = React.useState(false);
    const [showRegister, setShowRegister] = React.useState(false);
    const [inviteToken, setInviteToken] = React.useState(null);
    const [administersTeam, setAdministersTeam] = React.useState(false);

    // Check for invite token in URL on mount
    React.useEffect(() => {
        const tokenFromUrl = getInviteTokenFromUrl();
        if (tokenFromUrl) {
            setInviteToken(tokenFromUrl);
            storePendingInvite(tokenFromUrl);
            setShowRegister(true);
            return;
        }

        readPendingInvite();
    }, []);

    // Process pending invitation after authentication
    const processPendingInvitation = React.useCallback(async () => {
        const token = inviteToken || readPendingInvite();
        if (token) {
            try {
                await teamService.acceptInvitation(token);
                clearPendingInvite();
                setInviteToken(null);
                clearInviteTokenFromUrl();
                // Optionally navigate to teams page
                setCurrentPage('teams');
            } catch (err) {
                console.error('Error accepting invitation:', err);
                clearPendingInvite();
                setInviteToken(null);
            }
        }
    }, [inviteToken]);

    const refreshTeamAdminFlag = React.useCallback(() => {
        teamService.getTeams()
            .then((data) => setAdministersTeam((data.teams || []).some((team) => team.role === 'admin')))
            .catch(() => setAdministersTeam(false));
    }, []);

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
                refreshTeamAdminFlag();
            })
            .catch(() => {
                setIsAuthenticated(false);
            });
    }, [refreshTeamAdminFlag]);

    // Process invitation when user becomes authenticated
    React.useEffect(() => {
        if (isAuthenticated && (inviteToken || readPendingInvite())) {
            processPendingInvitation();
        }
    }, [isAuthenticated, inviteToken, processPendingInvitation]);

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
        refreshTeamAdminFlag();
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
        return (
            <>
                {showRegister
                    ? <Register onBackToLogin={() => setShowRegister(false)} inviteToken={inviteToken} />
                    : <Login onLogin={handleLogin} onSwitchToRegister={() => setShowRegister(true)} inviteToken={inviteToken} />}
                <InstallPrompt />
            </>
        );
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
                    {(user?.role === 'ROLE_ADMIN' || administersTeam) && (
                        <>
                            <button
                                onClick={() => handlePageChange('task-types')}
                                className={currentPage === 'task-types' ? 'nav-active' : ''}
                            >
                                {t('nav.taskTypes')}
                            </button>
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
                        onClick={() => handlePageChange('account')}
                        className={currentPage === 'account' ? 'nav-active' : ''}
                    >
                        {t('nav.account')}
                    </button>
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
                {currentPage === 'tasks' && <TaskList />}
                {currentPage === 'teams' && <TeamManagement user={user} />}
                {currentPage === 'task-types' && <TaskTypeManagement />}
                {currentPage === 'bonus-rules' && <BonusRulesManagement user={user} />}
                {currentPage === 'status-change-rules' && <StatusChangeRulesManagement user={user} />}
                {currentPage === 'account' && <Account user={user} points={userPoints} />}
                {currentPage === 'settings' && <UserSettings user={user} />}
            </main>
            <InstallPrompt />
        </div>
    );
}

export default App;
