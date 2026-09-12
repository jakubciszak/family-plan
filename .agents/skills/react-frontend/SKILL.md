---
name: react-frontend
description: React 18 frontend development with hooks, state management, and Playwright E2E testing. Use this skill when building React components, pages, handling state, API integration, or writing frontend tests.
---

# React Frontend Skill

This skill provides guidance for developing the Family Plan React 18 frontend application.

## Project Structure

```
frontend/
├── src/
│   ├── pages/           # Page components (routes)
│   ├── components/      # Reusable UI components
│   ├── services/        # API client and services
│   ├── i18n/            # Internationalization
│   │   └── locales/     # Translation files (en.json, pl.json)
│   ├── styles/          # CSS stylesheets
│   └── index.js         # App entry point
├── tests/
│   └── e2e/             # Playwright E2E tests
└── public/              # Static assets
```

## Component Patterns

### Functional Component with Hooks

```javascript
import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';

function TaskList({ teamId }) {
    const { t } = useTranslation();
    const [tasks, setTasks] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchTasks = useCallback(async () => {
        try {
            setLoading(true);
            const data = await apiClient.get(`/api/teams/${teamId}/tasks`);
            setTasks(data);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [teamId]);

    useEffect(() => {
        fetchTasks();
    }, [fetchTasks]);

    if (loading) return <div>{t('common.loading')}</div>;
    if (error) return <div className="error">{error}</div>;

    return (
        <div className="task-list">
            <h2>{t('tasks.title')}</h2>
            {tasks.length === 0 ? (
                <p>{t('tasks.empty')}</p>
            ) : (
                <ul>
                    {tasks.map(task => (
                        <TaskItem key={task.id} task={task} />
                    ))}
                </ul>
            )}
        </div>
    );
}

export default TaskList;
```

### Reusable Component

```javascript
import React from 'react';
import PropTypes from 'prop-types';

function Button({ children, onClick, variant, disabled, type }) {
    const className = `btn btn-${variant}`;

    return (
        <button
            type={type}
            className={className}
            onClick={onClick}
            disabled={disabled}
        >
            {children}
        </button>
    );
}

Button.propTypes = {
    children: PropTypes.node.isRequired,
    onClick: PropTypes.func,
    variant: PropTypes.oneOf(['primary', 'secondary', 'danger']),
    disabled: PropTypes.bool,
    type: PropTypes.oneOf(['button', 'submit', 'reset'])
};

Button.defaultProps = {
    variant: 'primary',
    disabled: false,
    type: 'button'
};

export default Button;
```

### Form Component

```javascript
import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import apiClient from '../services/apiClient';

function CreateTaskForm({ teamId, onSuccess }) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    const validate = () => {
        const newErrors = {};
        if (!name.trim()) {
            newErrors.name = t('validation.required');
        }
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!validate()) return;

        setSubmitting(true);
        try {
            await apiClient.post('/api/tasks', {
                name,
                description,
                teamId
            });
            setName('');
            setDescription('');
            onSuccess?.();
        } catch (err) {
            setErrors({ submit: err.message });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="create-task-form">
            <div className="form-group">
                <label htmlFor="name">{t('tasks.name')}</label>
                <input
                    id="name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    disabled={submitting}
                />
                {errors.name && <span className="error">{errors.name}</span>}
            </div>

            <div className="form-group">
                <label htmlFor="description">{t('tasks.description')}</label>
                <textarea
                    id="description"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    disabled={submitting}
                />
            </div>

            {errors.submit && <div className="error">{errors.submit}</div>}

            <button type="submit" disabled={submitting}>
                {submitting ? t('common.saving') : t('tasks.create')}
            </button>
        </form>
    );
}

export default CreateTaskForm;
```

## API Client Usage

```javascript
// services/apiClient.js pattern
import apiClient from './services/apiClient';

// GET request
const tasks = await apiClient.get('/api/tasks');

// POST request
const newTask = await apiClient.post('/api/tasks', {
    name: 'Task name',
    teamId: 'uuid'
});

// PUT request
await apiClient.put(`/api/tasks/${taskId}`, {
    name: 'Updated name'
});

// DELETE request
await apiClient.delete(`/api/tasks/${taskId}`);
```

## Internationalization (i18n)

### Using Translations

```javascript
import { useTranslation } from 'react-i18next';

function MyComponent() {
    const { t, i18n } = useTranslation();

    const changeLanguage = (lang) => {
        i18n.changeLanguage(lang);
    };

    return (
        <div>
            <h1>{t('page.title')}</h1>
            <p>{t('page.description', { name: 'John' })}</p>

            <button onClick={() => changeLanguage('pl')}>Polski</button>
            <button onClick={() => changeLanguage('en')}>English</button>
        </div>
    );
}
```

### Translation File Structure (`locales/en.json`)

```json
{
  "common": {
    "loading": "Loading...",
    "save": "Save",
    "cancel": "Cancel",
    "delete": "Delete"
  },
  "tasks": {
    "title": "Tasks",
    "name": "Task Name",
    "description": "Description",
    "create": "Create Task",
    "empty": "No tasks found"
  },
  "validation": {
    "required": "This field is required"
  }
}
```

## Playwright E2E Testing

### Test Example (`tests/e2e/tasks.spec.js`)

```javascript
import { test, expect } from '@playwright/test';

test.describe('Task Management', () => {
    test.beforeEach(async ({ page }) => {
        // Login before each test
        await page.goto('/login');
        await page.fill('[name="email"]', 'test@example.com');
        await page.fill('[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/dashboard');
    });

    test('should display task list', async ({ page }) => {
        await page.goto('/tasks');

        await expect(page.locator('h2')).toContainText('Tasks');
        await expect(page.locator('.task-list')).toBeVisible();
    });

    test('should create a new task', async ({ page }) => {
        await page.goto('/tasks');

        await page.click('button:has-text("Create Task")');
        await page.fill('[name="name"]', 'Test Task');
        await page.fill('[name="description"]', 'Test description');
        await page.click('button[type="submit"]');

        await expect(page.locator('.task-item')).toContainText('Test Task');
    });

    test('should show validation error for empty name', async ({ page }) => {
        await page.goto('/tasks/create');

        await page.click('button[type="submit"]');

        await expect(page.locator('.error')).toContainText('required');
    });
});
```

### Running Tests

```bash
cd frontend

# Run all E2E tests
npm run test

# Run with visible browser
npm run test:headed

# Run with Playwright UI
npm run test:ui

# Run specific test file
npx playwright test tasks.spec.js
```

## File Naming Conventions

- Components: `PascalCase.jsx` (e.g., `TaskList.jsx`)
- Pages: `PascalCase.jsx` (e.g., `DashboardPage.jsx`)
- Services: `camelCase.js` (e.g., `apiClient.js`)
- Tests: `*.spec.js` (e.g., `tasks.spec.js`)
- CSS: `kebab-case.css` (e.g., `task-list.css`)

## Best Practices

1. **Keep components small** - Single responsibility principle
2. **Lift state up** - Share state through common parent
3. **Use hooks** - Prefer functional components with hooks
4. **Handle loading/error states** - Always show appropriate UI
5. **Validate forms** - Client-side validation before API calls
6. **Use translations** - Never hardcode user-facing text
7. **Test user flows** - E2E tests for critical paths

## Common Patterns

### Conditional Rendering

```javascript
{isLoading && <Spinner />}
{error && <ErrorMessage message={error} />}
{data && <DataList items={data} />}
```

### List Rendering

```javascript
{items.map(item => (
    <Item key={item.id} {...item} />
))}
```

### Event Handling

```javascript
const handleClick = useCallback((id) => {
    // Handle click
}, [dependency]);

<button onClick={() => handleClick(item.id)}>Click</button>
```
