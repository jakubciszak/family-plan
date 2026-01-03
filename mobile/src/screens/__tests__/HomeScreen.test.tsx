import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import HomeScreen from '../HomeScreen';
import apiClient from '../../services/apiClient';

jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

const mockNavigation = {
  navigate: jest.fn(),
  replace: jest.fn(),
} as any;

describe('HomeScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render loading state initially', () => {
    mockedApiClient.getCurrentUser.mockImplementation(() => new Promise(() => {}));
    
    const { getByTestId } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    // ActivityIndicator should be present during loading
    expect(() => getByTestId('loading-indicator')).not.toThrow();
  });

  it('should display user information after loading', async () => {
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 150 });

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const welcomeText = await findByText('Welcome, John Doe!');
    expect(welcomeText).toBeTruthy();

    const pointsText = await findByText('150');
    expect(pointsText).toBeTruthy();
  });

  it('should navigate to TaskList when Tasks button is pressed', async () => {
    const mockUser = {
      id: '1',
      name: 'John Doe',
      email: 'john@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 100 });

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const tasksButton = await findByText(/Tasks/);
    fireEvent.press(tasksButton);

    await waitFor(() => {
      expect(mockNavigation.navigate).toHaveBeenCalledWith('TaskList', { user: mockUser });
    });
  });

  it('should show Bonus Rules button for admin users', async () => {
    const mockAdminUser = {
      id: '1',
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockAdminUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 200 });

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const bonusRulesButton = await findByText(/Bonus Rules/);
    expect(bonusRulesButton).toBeTruthy();
  });

  it('should not show Bonus Rules button for regular users', async () => {
    const mockUser = {
      id: '1',
      name: 'Regular User',
      email: 'user@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 50 });

    const { findByText, queryByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    await findByText('Welcome, Regular User!');

    const bonusRulesButton = queryByText(/Bonus Rules/);
    expect(bonusRulesButton).toBeNull();
  });

  it('should navigate to BonusRules when button is pressed (admin)', async () => {
    const mockAdminUser = {
      id: '1',
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockAdminUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 200 });

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const bonusRulesButton = await findByText(/Bonus Rules/);
    fireEvent.press(bonusRulesButton);

    await waitFor(() => {
      expect(mockNavigation.navigate).toHaveBeenCalledWith('BonusRules', { user: mockAdminUser });
    });
  });

  it('should handle logout and navigate to Login', async () => {
    const mockUser = {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 75 });
    mockedApiClient.logout.mockResolvedValue();

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const logoutButton = await findByText(/Logout/);
    fireEvent.press(logoutButton);

    await waitFor(() => {
      expect(mockedApiClient.logout).toHaveBeenCalled();
      expect(mockNavigation.replace).toHaveBeenCalledWith('Login');
    });
  });

  it('should redirect to Login on authentication error', async () => {
    mockedApiClient.getCurrentUser.mockRejectedValue(new Error('Not authenticated'));

    render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    await waitFor(() => {
      expect(mockNavigation.replace).toHaveBeenCalledWith('Login');
    });
  });

  it('should handle logout error gracefully', async () => {
    const mockUser = {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.getCurrentUser.mockResolvedValue(mockUser);
    mockedApiClient.getUserPoints.mockResolvedValue({ balance: 75 });
    mockedApiClient.logout.mockRejectedValue(new Error('Logout failed'));

    const { findByText } = render(
      <HomeScreen navigation={mockNavigation} route={{} as any} />
    );

    const logoutButton = await findByText(/Logout/);
    fireEvent.press(logoutButton);

    await waitFor(() => {
      expect(mockNavigation.replace).toHaveBeenCalledWith('Login');
    });
  });
});
