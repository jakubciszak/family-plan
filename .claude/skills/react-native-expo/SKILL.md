---
name: react-native-expo
description: React Native 0.76 with Expo and TypeScript for mobile development. Use this skill when building mobile screens, navigation, native features, or working with the mobile app.
---

# React Native Expo Skill

This skill provides guidance for developing the Family Plan mobile application using React Native 0.76, Expo 52, and TypeScript.

## Project Structure

```
mobile/
├── src/
│   ├── screens/         # Screen components
│   ├── navigation/      # React Navigation setup
│   ├── components/      # Reusable components
│   ├── services/        # API clients
│   ├── hooks/           # Custom hooks
│   ├── types/           # TypeScript definitions
│   └── utils/           # Helper functions
├── __tests__/           # Jest tests
├── app.json             # Expo configuration
├── tsconfig.json        # TypeScript config
└── package.json
```

## TypeScript Patterns

### Type Definitions

```typescript
// types/task.ts
export interface Task {
    id: string;
    name: string;
    description?: string;
    status: TaskStatus;
    points: number;
    assigneeId?: string;
    teamId: string;
    createdAt: string;
    updatedAt: string;
}

export type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'approved';

export interface CreateTaskRequest {
    name: string;
    description?: string;
    points: number;
    teamId: string;
}
```

### Screen Component

```typescript
// screens/TaskListScreen.tsx
import React, { useState, useEffect, useCallback } from 'react';
import {
    View,
    Text,
    FlatList,
    StyleSheet,
    RefreshControl,
    ActivityIndicator,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Task } from '../types/task';
import { TaskItem } from '../components/TaskItem';
import { apiClient } from '../services/apiClient';
import { RootStackParamList } from '../navigation/types';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'TaskList'>;

export function TaskListScreen(): JSX.Element {
    const navigation = useNavigation<NavigationProp>();
    const [tasks, setTasks] = useState<Task[]>([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchTasks = useCallback(async () => {
        try {
            const data = await apiClient.get<Task[]>('/api/tasks');
            setTasks(data);
            setError(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Unknown error');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, []);

    useEffect(() => {
        fetchTasks();
    }, [fetchTasks]);

    const handleRefresh = useCallback(() => {
        setRefreshing(true);
        fetchTasks();
    }, [fetchTasks]);

    const handleTaskPress = useCallback((task: Task) => {
        navigation.navigate('TaskDetail', { taskId: task.id });
    }, [navigation]);

    if (loading) {
        return (
            <View style={styles.centered}>
                <ActivityIndicator size="large" />
            </View>
        );
    }

    if (error) {
        return (
            <View style={styles.centered}>
                <Text style={styles.error}>{error}</Text>
            </View>
        );
    }

    return (
        <View style={styles.container}>
            <FlatList
                data={tasks}
                keyExtractor={(item) => item.id}
                renderItem={({ item }) => (
                    <TaskItem task={item} onPress={handleTaskPress} />
                )}
                refreshControl={
                    <RefreshControl
                        refreshing={refreshing}
                        onRefresh={handleRefresh}
                    />
                }
                ListEmptyComponent={
                    <Text style={styles.empty}>No tasks found</Text>
                }
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#fff',
    },
    centered: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    error: {
        color: 'red',
        fontSize: 16,
    },
    empty: {
        textAlign: 'center',
        marginTop: 20,
        color: '#666',
    },
});
```

### Reusable Component

```typescript
// components/TaskItem.tsx
import React, { memo } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Task, TaskStatus } from '../types/task';

interface TaskItemProps {
    task: Task;
    onPress: (task: Task) => void;
}

const statusColors: Record<TaskStatus, string> = {
    pending: '#ffa500',
    in_progress: '#007bff',
    completed: '#28a745',
    approved: '#6f42c1',
};

export const TaskItem = memo(function TaskItem({
    task,
    onPress,
}: TaskItemProps): JSX.Element {
    return (
        <TouchableOpacity
            style={styles.container}
            onPress={() => onPress(task)}
        >
            <View style={styles.content}>
                <Text style={styles.name}>{task.name}</Text>
                {task.description && (
                    <Text style={styles.description} numberOfLines={2}>
                        {task.description}
                    </Text>
                )}
            </View>
            <View style={styles.meta}>
                <View
                    style={[
                        styles.status,
                        { backgroundColor: statusColors[task.status] },
                    ]}
                >
                    <Text style={styles.statusText}>{task.status}</Text>
                </View>
                <Text style={styles.points}>{task.points} pts</Text>
            </View>
        </TouchableOpacity>
    );
});

const styles = StyleSheet.create({
    container: {
        flexDirection: 'row',
        padding: 16,
        borderBottomWidth: 1,
        borderBottomColor: '#eee',
    },
    content: {
        flex: 1,
    },
    name: {
        fontSize: 16,
        fontWeight: '600',
    },
    description: {
        fontSize: 14,
        color: '#666',
        marginTop: 4,
    },
    meta: {
        alignItems: 'flex-end',
    },
    status: {
        paddingHorizontal: 8,
        paddingVertical: 4,
        borderRadius: 4,
    },
    statusText: {
        color: '#fff',
        fontSize: 12,
        fontWeight: '500',
    },
    points: {
        marginTop: 4,
        fontSize: 14,
        fontWeight: '600',
    },
});
```

## Navigation Setup

```typescript
// navigation/types.ts
export type RootStackParamList = {
    Login: undefined;
    Dashboard: undefined;
    TaskList: undefined;
    TaskDetail: { taskId: string };
    CreateTask: { teamId: string };
    Profile: undefined;
};

// navigation/AppNavigator.tsx
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { RootStackParamList } from './types';
import { LoginScreen } from '../screens/LoginScreen';
import { DashboardScreen } from '../screens/DashboardScreen';
import { TaskListScreen } from '../screens/TaskListScreen';
import { TaskDetailScreen } from '../screens/TaskDetailScreen';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function AppNavigator(): JSX.Element {
    return (
        <NavigationContainer>
            <Stack.Navigator initialRouteName="Login">
                <Stack.Screen
                    name="Login"
                    component={LoginScreen}
                    options={{ headerShown: false }}
                />
                <Stack.Screen
                    name="Dashboard"
                    component={DashboardScreen}
                    options={{ title: 'Dashboard' }}
                />
                <Stack.Screen
                    name="TaskList"
                    component={TaskListScreen}
                    options={{ title: 'Tasks' }}
                />
                <Stack.Screen
                    name="TaskDetail"
                    component={TaskDetailScreen}
                    options={{ title: 'Task Details' }}
                />
            </Stack.Navigator>
        </NavigationContainer>
    );
}
```

## Custom Hooks

```typescript
// hooks/useTasks.ts
import { useState, useEffect, useCallback } from 'react';
import { Task } from '../types/task';
import { apiClient } from '../services/apiClient';

interface UseTasksResult {
    tasks: Task[];
    loading: boolean;
    error: string | null;
    refresh: () => Promise<void>;
}

export function useTasks(teamId?: string): UseTasksResult {
    const [tasks, setTasks] = useState<Task[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchTasks = useCallback(async () => {
        try {
            setLoading(true);
            const endpoint = teamId
                ? `/api/teams/${teamId}/tasks`
                : '/api/tasks';
            const data = await apiClient.get<Task[]>(endpoint);
            setTasks(data);
            setError(null);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch tasks');
        } finally {
            setLoading(false);
        }
    }, [teamId]);

    useEffect(() => {
        fetchTasks();
    }, [fetchTasks]);

    return {
        tasks,
        loading,
        error,
        refresh: fetchTasks,
    };
}
```

## API Service

```typescript
// services/apiClient.ts
const BASE_URL = 'http://localhost:8080';

class ApiClient {
    private token: string | null = null;

    setToken(token: string): void {
        this.token = token;
    }

    clearToken(): void {
        this.token = null;
    }

    private async request<T>(
        endpoint: string,
        options: RequestInit = {}
    ): Promise<T> {
        const headers: HeadersInit = {
            'Content-Type': 'application/json',
            ...options.headers,
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const response = await fetch(`${BASE_URL}${endpoint}`, {
            ...options,
            headers,
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(error.message || `HTTP ${response.status}`);
        }

        return response.json();
    }

    async get<T>(endpoint: string): Promise<T> {
        return this.request<T>(endpoint);
    }

    async post<T>(endpoint: string, data: unknown): Promise<T> {
        return this.request<T>(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async put<T>(endpoint: string, data: unknown): Promise<T> {
        return this.request<T>(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async delete(endpoint: string): Promise<void> {
        await this.request(endpoint, { method: 'DELETE' });
    }
}

export const apiClient = new ApiClient();
```

## Testing

```typescript
// __tests__/TaskItem.test.tsx
import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { TaskItem } from '../src/components/TaskItem';
import { Task } from '../src/types/task';

describe('TaskItem', () => {
    const mockTask: Task = {
        id: '1',
        name: 'Test Task',
        description: 'Test description',
        status: 'pending',
        points: 10,
        teamId: 'team-1',
        createdAt: '2024-01-01T00:00:00Z',
        updatedAt: '2024-01-01T00:00:00Z',
    };

    it('renders task name and description', () => {
        const { getByText } = render(
            <TaskItem task={mockTask} onPress={jest.fn()} />
        );

        expect(getByText('Test Task')).toBeTruthy();
        expect(getByText('Test description')).toBeTruthy();
    });

    it('calls onPress when tapped', () => {
        const onPress = jest.fn();
        const { getByText } = render(
            <TaskItem task={mockTask} onPress={onPress} />
        );

        fireEvent.press(getByText('Test Task'));

        expect(onPress).toHaveBeenCalledWith(mockTask);
    });
});
```

### Running Tests

```bash
cd mobile

# Run all tests
npm test

# Run with coverage
npm test -- --coverage

# Run specific test file
npm test TaskItem.test.tsx

# Run in watch mode
npm test -- --watch
```

## Expo Commands

```bash
# Start development server
npx expo start

# Run on iOS simulator
npx expo run:ios

# Run on Android emulator
npx expo run:android

# Build for production
eas build --platform all
```

## Best Practices

1. **Use TypeScript** - Strict typing for all components and services
2. **Memoize components** - Use `memo` for list items
3. **Handle loading states** - Show ActivityIndicator during async operations
4. **Use StyleSheet.create** - For optimized styles
5. **Type navigation** - Strongly type all navigation params
6. **Custom hooks** - Extract reusable logic into hooks
7. **Handle errors gracefully** - Show user-friendly error messages
