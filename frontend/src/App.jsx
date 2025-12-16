import React from 'react';
import TaskList from './pages/TaskList';
import Login from './pages/Login';
import './styles/app.css';

function App() {
    const [isAuthenticated, setIsAuthenticated] = React.useState(false);
    const [user, setUser] = React.useState(null);

    React.useEffect(() => {
        // Check if user is authenticated
        fetch('/api/auth/me')
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error('Not authenticated');
            })
            .then(data => {
                setUser(data);
                setIsAuthenticated(true);
            })
            .catch(() => {
                setIsAuthenticated(false);
            });
    }, []);

    const handleLogin = (userData) => {
        setUser(userData);
        setIsAuthenticated(true);
    };

    const handleLogout = () => {
        fetch('/api/auth/logout', { method: 'POST' })
            .then(() => {
                setUser(null);
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
                <div className="user-info">
                    <span>Welcome, {user?.name}</span>
                    <button onClick={handleLogout}>Logout</button>
                </div>
            </header>
            <main className="app-main">
                <TaskList user={user} />
            </main>
        </div>
    );
}

export default App;
