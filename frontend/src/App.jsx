import React from 'react';
import TaskList from './pages/TaskList';
import Login from './pages/Login';
import BonusRulesManagement from './pages/BonusRulesManagement';
import apiClient from './services/apiClient';
import './styles/app.css';

function App() {
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
                <h1>Family Plan</h1>
                <nav className="app-nav">
                    <button 
                        onClick={() => setCurrentPage('tasks')}
                        className={currentPage === 'tasks' ? 'nav-active' : ''}
                    >
                        Tasks
                    </button>
                    {user?.role === 'ROLE_ADMIN' && (
                        <button 
                            onClick={() => setCurrentPage('bonus-rules')}
                            className={currentPage === 'bonus-rules' ? 'nav-active' : ''}
                        >
                            Bonus Rules
                        </button>
                    )}
                </nav>
                <div className="user-info">
                    <span>Welcome, {user?.name}</span>
                    <span className="user-points">{userPoints} points</span>
                    <button onClick={handleLogout}>Logout</button>
                </div>
            </header>
            <main className="app-main">
                {currentPage === 'tasks' && <TaskList user={user} />}
                {currentPage === 'bonus-rules' && <BonusRulesManagement user={user} />}
            </main>
        </div>
    );
}

export default App;
