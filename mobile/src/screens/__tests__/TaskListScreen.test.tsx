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

describe('TaskListScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
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
});
