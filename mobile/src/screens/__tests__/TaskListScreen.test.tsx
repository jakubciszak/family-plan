import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import TaskListScreen from '../TaskListScreen';
import apiClient from '../../services/apiClient';

jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

const mockNavigation = {
  navigate: jest.fn(),
} as any;

const mockRoute = {
  params: {
    user: {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'ROLE_USER',
    },
  },
} as any;

const mockAdminRoute = {
  params: {
    user: {
      id: '2',
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN',
    },
  },
} as any;

describe('TaskListScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render loading state initially', () => {
    mockedApiClient.getTasks.mockImplementation(() => new Promise(() => {}));
    
    const { getByTestId } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    expect(() => getByTestId('loading-indicator')).not.toThrow();
  });

  it('should render task list', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Clean kitchen',
        description: 'Wash dishes',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const taskName = await findByText('Clean kitchen');
    expect(taskName).toBeTruthy();
  });

  it('should display empty state when no tasks exist', async () => {
    mockedApiClient.getTasks.mockResolvedValue({ tasks: [] });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const emptyMessage = await findByText('No tasks yet');
    expect(emptyMessage).toBeTruthy();
  });

  it('should handle task assignment', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Clean kitchen',
        description: 'Wash dishes',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });
    mockedApiClient.assignTask.mockResolvedValue();

    const { findByText, getByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    await findByText('Clean kitchen');

    const assignButton = getByText('Assign to Me');
    fireEvent.press(assignButton);

    await waitFor(() => {
      expect(mockedApiClient.assignTask).toHaveBeenCalledWith('1', '1');
    });
  });

  it('should handle task completion', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Clean kitchen',
        description: 'Wash dishes',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: '1',
        assignedUserName: 'Test User',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });
    mockedApiClient.completeTask.mockResolvedValue();

    const { findByText, getByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    await findByText('Clean kitchen');

    const completeButton = getByText('Complete');
    fireEvent.press(completeButton);

    await waitFor(() => {
      expect(mockedApiClient.completeTask).toHaveBeenCalledWith('1', '1');
    });
  });

  it('should show approve button for admin on completed tasks', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Completed Task',
        description: 'Already done',
        points: 100,
        frequency: 'once' as const,
        status: 'completed' as const,
        assignedUserId: '1',
        assignedUserName: 'Some User',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText, getByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    await findByText('Completed Task');

    const approveButton = getByText('Approve');
    expect(approveButton).toBeTruthy();
  });

  it('should handle task approval by admin', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Completed Task',
        description: 'Ready for approval',
        points: 75,
        frequency: 'weekly' as const,
        status: 'completed' as const,
        assignedUserId: '1',
        assignedUserName: 'Test User',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });
    mockedApiClient.approveTask.mockResolvedValue();

    const { findByText, getByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    await findByText('Completed Task');

    const approveButton = getByText('Approve');
    fireEvent.press(approveButton);

    await waitFor(() => {
      expect(mockedApiClient.approveTask).toHaveBeenCalledWith('1', '2');
    });
  });

  it('should display task points correctly', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'High Point Task',
        description: 'Worth many points',
        points: 250,
        frequency: 'monthly' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const points = await findByText('250 points');
    expect(points).toBeTruthy();
  });

  it('should display task frequency correctly', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Daily Task',
        description: 'Do this daily',
        points: 10,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const frequency = await findByText('daily');
    expect(frequency).toBeTruthy();
  });

  it('should display assigned user name when task is assigned', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Assigned Task',
        description: 'Already assigned',
        points: 50,
        frequency: 'once' as const,
        status: 'pending' as const,
        assignedUserId: '3',
        assignedUserName: 'John Doe',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const assignedTo = await findByText(/Assigned to:/);
    expect(assignedTo).toBeTruthy();
  });

  it('should handle pull-to-refresh', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Test Task',
        description: 'Test',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText, UNSAFE_getByType } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    await findByText('Test Task');

    // Simulate pull-to-refresh
    const scrollView = UNSAFE_getByType(require('react-native').ScrollView);
    const refreshControl = scrollView.props.refreshControl;
    
    if (refreshControl && refreshControl.props.onRefresh) {
      refreshControl.props.onRefresh();
    }

    await waitFor(() => {
      expect(mockedApiClient.getTasks).toHaveBeenCalledTimes(2);
    });
  });

  it('should display different status colors', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Pending Task',
        description: 'Not started',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: null,
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    const statusBadge = await findByText('pending');
    expect(statusBadge).toBeTruthy();
  });

  it('should handle API errors gracefully', async () => {
    mockedApiClient.getTasks.mockRejectedValue(new Error('Network error'));

    const { findByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    // Should show empty state after error
    const emptyMessage = await findByText('No tasks yet');
    expect(emptyMessage).toBeTruthy();
  });

  it('should not show assign button for already assigned tasks', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Assigned Task',
        description: 'Already taken',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: '3',
        assignedUserName: 'Other User',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText, queryByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    await findByText('Assigned Task');

    const assignButton = queryByText('Assign to Me');
    expect(assignButton).toBeNull();
  });

  it('should not show complete button for tasks assigned to others', async () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Other User Task',
        description: 'Not my task',
        points: 50,
        frequency: 'daily' as const,
        status: 'pending' as const,
        assignedUserId: '99',
        assignedUserName: 'Someone Else',
        createdAt: '2024-01-01T00:00:00Z',
      },
    ];

    mockedApiClient.getTasks.mockResolvedValue({ tasks: mockTasks });

    const { findByText, queryByText } = render(
      <TaskListScreen navigation={mockNavigation} route={mockRoute} />
    );

    await findByText('Other User Task');

    const completeButton = queryByText('Complete');
    expect(completeButton).toBeNull();
  });
});
