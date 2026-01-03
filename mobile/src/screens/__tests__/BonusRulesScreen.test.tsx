import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import BonusRulesScreen from '../BonusRulesScreen';
import apiClient from '../../services/apiClient';

jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

const mockNavigation = {
  navigate: jest.fn(),
} as any;

const mockAdminRoute = {
  params: {
    user: {
      id: '1',
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN',
    },
  },
} as any;

const mockUserRoute = {
  params: {
    user: {
      id: '2',
      name: 'Regular User',
      email: 'user@example.com',
      role: 'ROLE_USER',
    },
  },
} as any;

describe('BonusRulesScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should deny access for non-admin users', () => {
    const { getByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockUserRoute} />
    );

    expect(getByText('Access Denied')).toBeTruthy();
    expect(getByText('Only administrators can manage bonus rules.')).toBeTruthy();
  });

  it('should render bonus rules list for admin users', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Consecutive Days Bonus',
        description: 'Complete tasks for consecutive days',
        bonusPoints: 50,
        type: 'consecutive_days' as const,
        config: { requiredDays: 7 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const ruleName = await findByText('Consecutive Days Bonus');
    expect(ruleName).toBeTruthy();
  });

  it('should display empty state when no rules exist', async () => {
    mockedApiClient.getBonusRules.mockResolvedValue({ rules: [] });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const emptyMessage = await findByText('No bonus rules yet');
    expect(emptyMessage).toBeTruthy();
  });

  it('should show active status badge for active rules', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Active Rule',
        description: 'An active bonus rule',
        bonusPoints: 100,
        type: 'monthly_task_count' as const,
        config: { requiredCount: 20 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const activeStatus = await findByText('Active');
    expect(activeStatus).toBeTruthy();
  });

  it('should show inactive status badge for inactive rules', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Inactive Rule',
        description: 'An inactive bonus rule',
        bonusPoints: 75,
        type: 'consecutive_days' as const,
        config: { requiredDays: 5 },
        isActive: false,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const inactiveStatus = await findByText('Inactive');
    expect(inactiveStatus).toBeTruthy();
  });

  it('should handle activate button press', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Inactive Rule',
        description: 'To be activated',
        bonusPoints: 50,
        type: 'consecutive_days' as const,
        config: { requiredDays: 3 },
        isActive: false,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });
    mockedApiClient.activateBonusRule.mockResolvedValue();

    const { findByText, getByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    await findByText('Inactive Rule');

    const activateButton = getByText('Activate');
    fireEvent.press(activateButton);

    await waitFor(() => {
      expect(mockedApiClient.activateBonusRule).toHaveBeenCalledWith('1');
    });
  });

  it('should handle deactivate button press', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Active Rule',
        description: 'To be deactivated',
        bonusPoints: 100,
        type: 'monthly_task_count' as const,
        config: { requiredCount: 15 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });
    mockedApiClient.deactivateBonusRule.mockResolvedValue();

    const { findByText, getByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    await findByText('Active Rule');

    const deactivateButton = getByText('Deactivate');
    fireEvent.press(deactivateButton);

    await waitFor(() => {
      expect(mockedApiClient.deactivateBonusRule).toHaveBeenCalledWith('1');
    });
  });

  it('should display rule type labels correctly', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Consecutive Rule',
        description: 'Daily streak',
        bonusPoints: 50,
        type: 'consecutive_days' as const,
        config: { requiredDays: 7 },
        isActive: true,
      },
      {
        id: '2',
        name: 'Monthly Rule',
        description: 'Monthly target',
        bonusPoints: 100,
        type: 'monthly_task_count' as const,
        config: { requiredCount: 20 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const consecutiveType = await findByText('Consecutive Days');
    expect(consecutiveType).toBeTruthy();

    const monthlyType = await findByText('Monthly Task Count');
    expect(monthlyType).toBeTruthy();
  });

  it('should display bonus points correctly', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Test Rule',
        description: 'Test description',
        bonusPoints: 250,
        type: 'consecutive_days' as const,
        config: { requiredDays: 10 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    const points = await findByText('250');
    expect(points).toBeTruthy();
  });

  it('should handle pull-to-refresh', async () => {
    const mockRules = [
      {
        id: '1',
        name: 'Test Rule',
        description: 'Test',
        bonusPoints: 50,
        type: 'consecutive_days' as const,
        config: { requiredDays: 5 },
        isActive: true,
      },
    ];

    mockedApiClient.getBonusRules.mockResolvedValue({ rules: mockRules });

    const { findByText, UNSAFE_getByType } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    await findByText('Test Rule');

    // Simulate pull-to-refresh
    const scrollView = UNSAFE_getByType(require('react-native').ScrollView);
    const refreshControl = scrollView.props.refreshControl;
    
    if (refreshControl && refreshControl.props.onRefresh) {
      refreshControl.props.onRefresh();
    }

    await waitFor(() => {
      expect(mockedApiClient.getBonusRules).toHaveBeenCalledTimes(2);
    });
  });

  it('should handle API errors gracefully', async () => {
    mockedApiClient.getBonusRules.mockRejectedValue(new Error('API Error'));

    const { findByText } = render(
      <BonusRulesScreen navigation={mockNavigation} route={mockAdminRoute} />
    );

    // Should show empty state after error
    const emptyMessage = await findByText('No bonus rules yet');
    expect(emptyMessage).toBeTruthy();
  });
});
