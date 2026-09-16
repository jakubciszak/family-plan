import React from 'react';
import { useTranslation } from 'react-i18next';
import TaskList from './pages/TaskList';
import MemberView from './pages/MemberView';
import Allowance from './pages/Allowance';
import Personalise from './pages/Personalise';
import Login from './pages/Login';
import Register from './pages/Register';
import BonusRulesManagement from './pages/BonusRulesManagement';
import StatusChangeRulesManagement from './pages/StatusChangeRulesManagement';
import UserSettings from './pages/UserSettings';
import NotificationEvents from './pages/NotificationEvents';
import PushAnnouncement from './pages/PushAnnouncement';
import TeamManagement from './pages/TeamManagement';
import TaskTypeManagement from './pages/TaskTypeManagement';
import Account from './pages/Account';
import InstallPrompt from './components/InstallPrompt';
import NotificationCenter from './components/NotificationCenter';
import AppNavigation from './components/AppNavigation';
import AppBarActions from './components/AppBarActions';
import useThemeMode from './hooks/useThemeMode';
import { PersonalisationProvider, usePersonalisation } from './hooks/usePersonalisation';
import { useWindowClass } from './hooks/useMediaQuery';
import { CircularProgress } from './components/md3';
import apiClient from './services/apiClient';
import teamService from './services/teamService';
import taskService from './services/taskService';
import './styles/app.css';

const getInviteTokenFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    return params.get('invite');
};

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
    const { own, reload: reloadOwnLook } = usePersonalisation();
    const [themeMode, setThemeMode] = useThemeMode();
    const windowClass = useWindowClass();
    const [isAuthenticated, setIsAuthenticated] = React.useState(null);
    const [user, setUser] = React.useState(null);
    const [userPoints, setUserPoints] = React.useState(0);
    const [weekPoints, setWeekPoints] = React.useState(0);
    const [currentPage, setCurrentPage] = React.useState('tasks');
    const [showRegister, setShowRegister] = React.useState(false);
    const [inviteToken, setInviteToken] = React.useState(null);
    const [administersTeam, setAdministersTeam] = React.useState(false);
    const [scrolled, setScrolled] = React.useState(false);
    const [inspectedMember, setInspectedMember] = React.useState(null);

    React.useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 4);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

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

    const processPendingInvitation = React.useCallback(async () => {
        const token = inviteToken || readPendingInvite();
        if (token) {
            try {
                await teamService.acceptInvitation(token);
                clearPendingInvite();
                setInviteToken(null);
                clearInviteTokenFromUrl();
                setCurrentPage('teams');
            } catch (err) {
                console.error('Error accepting invitation:', err);
                clearPendingInvite();
                setInviteToken(null);
            }
        }
    }, [inviteToken]);

    const refreshPoints = React.useCallback((userId) => {
        apiClient.get(`/api/users/${userId}/points`)
            .then((pointsData) => setUserPoints(pointsData.balance))
            .catch(() => setUserPoints(0));

        taskService.getWeek()
            .then((week) => setWeekPoints(week.total))
            .catch(() => setWeekPoints(0));
    }, []);

    const refreshTeamAdminFlag = React.useCallback(() => {
        teamService.getTeams()
            .then((data) => setAdministersTeam((data.teams || []).some((team) => team.role === 'admin')))
            .catch(() => setAdministersTeam(false));
    }, []);

    React.useEffect(() => {
        apiClient.get('/api/auth/me')
            .then(data => {
                setUser(data);
                setIsAuthenticated(true);
                refreshTeamAdminFlag();
                refreshPoints(data.id);
            })
            .catch(() => {
                setIsAuthenticated(false);
            });
    }, [refreshTeamAdminFlag, refreshPoints]);

    React.useEffect(() => {
        if (isAuthenticated && (inviteToken || readPendingInvite())) {
            processPendingInvitation();
        }
    }, [isAuthenticated, inviteToken, processPendingInvitation]);

    const handleLogin = (userData) => {
        setUser(userData);
        setIsAuthenticated(true);
        reloadOwnLook();
        refreshTeamAdminFlag();
        refreshPoints(userData.id);
    };

    const handleLogout = () => {
        apiClient.post('/api/auth/logout', {})
            .catch(() => undefined)
            .then(() => {
                setUser(null);
                setUserPoints(0);
                setWeekPoints(0);
                setIsAuthenticated(false);
                setCurrentPage('tasks');
            });
    };

    if (isAuthenticated === null) {
        return (
            <div className="app-splash">
                <CircularProgress label={t('common.loading')} />
            </div>
        );
    }

    if (!isAuthenticated) {
        return (
            <>
                {showRegister
                    ? <Register onBackToLogin={() => setShowRegister(false)} onLogin={handleLogin} inviteToken={inviteToken} />
                    : <Login onLogin={handleLogin} onSwitchToRegister={() => setShowRegister(true)} inviteToken={inviteToken} />}
                <InstallPrompt />
            </>
        );
    }

    const isSuperAdmin = user?.role === 'ROLE_ADMIN';
    const manages = isSuperAdmin || administersTeam;

    const allNavItems = {
        tasks: { id: 'tasks', icon: 'tasks', label: t('nav.tasks') },
        teams: { id: 'teams', icon: 'teams', label: t('nav.teams') },
        allowance: { id: 'allowance', icon: 'wallet', label: t('nav.allowance'), shortLabel: t('nav.allowanceShort') },
        personalise: { id: 'personalise', icon: 'stars', label: t('personalise.title'), shortLabel: t('personalise.short') },
        'task-types': { id: 'task-types', icon: 'taskTypes', label: t('nav.taskTypes'), shortLabel: t('nav.taskTypesShort'), needsManaging: true },
        'bonus-rules': { id: 'bonus-rules', icon: 'workspacePremium', label: t('nav.bonusRules'), shortLabel: t('nav.bonusRulesShort'), needsManaging: true },
        account: { id: 'account', icon: 'account', label: t('nav.account'), shortLabel: t('nav.accountShort') },
        settings: { id: 'settings', icon: 'settings', label: t('nav.settings') },
    };

    const chosenNav = own?.navigation?.length ? own.navigation : Object.keys(allNavItems);

    const navItems = [
        ...chosenNav
            .map((id) => allNavItems[id])
            .filter((item) => item && (!item.needsManaging || manages)),
        ...(isSuperAdmin ? [
            { id: 'status-change-rules', icon: 'rule', label: t('nav.statusChangeRules'), shortLabel: t('nav.statusChangeRulesShort') },
            { id: 'notification-events', icon: 'notifications', label: t('nav.notificationEvents'), shortLabel: t('nav.notificationEventsShort') },
            { id: 'push-announcement', icon: 'send', label: t('nav.pushAnnouncement'), shortLabel: t('nav.pushAnnouncementShort') },
        ] : []),
    ];

    return (
        <div className="app">
            <header className={`app-header${scrolled ? ' app-header--scrolled' : ''}`}>
                <div className="header-left">
                    <h1>{t('app.title')}</h1>
                </div>

                <AppBarActions
                    user={user}
                    points={weekPoints}
                    totalPoints={userPoints}
                    themeMode={themeMode}
                    onThemeModeChange={setThemeMode}
                    onLogout={handleLogout}
                    onOpenAccount={() => setCurrentPage('account')}
                    onOpenPersonalise={() => setCurrentPage('personalise')}
                />
            </header>

            <AppNavigation
                items={navItems}
                currentPage={currentPage}
                onSelect={setCurrentPage}
                windowClass={windowClass}
                appTitle={t('app.title')}
            />

            <main className="app-main">
                {currentPage === 'tasks' && (
                    <TaskList
                        onNavigate={setCurrentPage}
                        user={user}
                        onInspectMember={(member) => { setInspectedMember(member); setCurrentPage('member'); }}
                    />
                )}
                {currentPage === 'member' && inspectedMember && (
                    <MemberView
                        member={inspectedMember}
                        onBack={() => { setInspectedMember(null); setCurrentPage('tasks'); }}
                    />
                )}
                {currentPage === 'teams' && (
                    <TeamManagement
                        user={user}
                        onMembershipChanged={refreshTeamAdminFlag}
                        onInspectMember={(member) => { setInspectedMember(member); setCurrentPage('member'); }}
                    />
                )}
                {currentPage === 'allowance' && <Allowance user={user} />}
                {currentPage === 'personalise' && <Personalise user={user} />}
                {currentPage === 'task-types' && <TaskTypeManagement />}
                {currentPage === 'bonus-rules' && <BonusRulesManagement user={user} />}
                {currentPage === 'status-change-rules' && <StatusChangeRulesManagement user={user} />}
                {currentPage === 'notification-events' && <NotificationEvents user={user} />}
                {currentPage === 'push-announcement' && <PushAnnouncement user={user} />}
                {currentPage === 'account' && <Account user={user} points={weekPoints} />}
                {currentPage === 'settings' && <UserSettings user={user} />}
            </main>

            <NotificationCenter />
            <InstallPrompt />
        </div>
    );
}

function AppWithOwnLook() {
    return (
        <PersonalisationProvider>
            <App />
        </PersonalisationProvider>
    );
}

export default AppWithOwnLook;
