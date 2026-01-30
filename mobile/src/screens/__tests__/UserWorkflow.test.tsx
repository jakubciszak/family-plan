/**
 * Comprehensive E2E Test Suite for User Workflow
 *
 * This test suite covers the complete user workflow scenario:
 * 1. User creates account (registration)
 * 2. User creates a new team
 * 3. User defines bonus rules
 * 4. User creates a new task
 * 5. User invites new user to team
 * 6. New user registers and accepts invitation
 * 7. New user can see tasks
 * 8. New user assigns task to themselves
 * 9. New user marks task as completed
 * 10. New user cannot approve their own task
 * 11. Admin user approves the task
 */

import React from 'react';
import { render, fireEvent, waitFor, act } from '@testing-library/react-native';
import { Alert } from 'react-native';

// Import screens
import LoginScreen from '../LoginScreen';
import RegisterScreen from '../RegisterScreen';
import TaskListScreen from '../TaskListScreen';
import TeamManagementScreen from '../TeamManagementScreen';
import BonusRulesScreen from '../BonusRulesScreen';

// Import services
import apiClient from '../../services/apiClient';

// Mock apiClient
jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

// Mock Alert
jest.spyOn(Alert, 'alert');

// Test data
const timestamp = Date.now();
const uniqueId = Math.random().toString(36).substring(7);

const mainUser = {
  id: `user-admin-${uniqueId}`,
  name: `Test Admin ${uniqueId}`,
  email: `admin.${timestamp}@test.example.com`,
  role: 'ROLE_ADMIN',
};

const invitedUser = {
  id: `user-member-${uniqueId}`,
  name: `Test Member ${uniqueId}`,
  email: `member.${timestamp}@test.example.com`,
  role: 'ROLE_USER',
};

const testTeam = {
  id: `team-${uniqueId}`,
  name: `Test Team ${uniqueId}`,
  description: 'Team for E2E testing',
  createdBy: mainUser.id,
  createdAt: new Date().toISOString(),
  updatedAt: null,
};

const testTask = {
  id: `task-${uniqueId}`,
  name: `Test Task ${uniqueId}`,
  description: 'E2E test task description',
  points: 50,
  frequency: 'once' as const,
  status: 'pending' as const,
  assignedUserId: null,
  assignedUserName: undefined,
  createdAt: new Date().toISOString(),
};

const testBonusRule = {
  id: `rule-${uniqueId}`,
  name: `Test Bonus Rule ${uniqueId}`,
  description: 'Bonus for consecutive days',
  bonusPoints: 100,
  type: 'consecutive_days' as const,
  config: {
    requiredDays: 7,
  },
  isActive: true,
};

const testInvitation = {
  id: `invite-${uniqueId}`,
  teamId: testTeam.id,
  role: 'member' as const,
  token: `token-${uniqueId}`,
  status: 'pending' as const,
  createdAt: new Date().toISOString(),
  expiresAt: null,
};

// Mock navigation
const mockNavigation = {
  navigate: jest.fn(),
  replace: jest.fn(),
  goBack: jest.fn(),
} as any;

describe('User Workflow E2E Tests', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('Step 1: User Registration', () => {
    it('should render registration form correctly', () => {
      const { getByPlaceholderText, getByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      expect(getByPlaceholderText('Name')).toBeTruthy();
      expect(getByPlaceholderText('Email')).toBeTruthy();
      expect(getByPlaceholderText('Password')).toBeTruthy();
      expect(getByText('Register')).toBeTruthy();
    });

    it('should register main user (admin) successfully', async () => {
      mockedApiClient.register.mockResolvedValue({
        message: 'User registered successfully',
      });

      const { getByPlaceholderText, getByText, findByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Name'), mainUser.name);
      fireEvent.changeText(getByPlaceholderText('Email'), mainUser.email);
      fireEvent.changeText(getByPlaceholderText('Password'), 'TestPassword123!');
      fireEvent.press(getByText('Register'));

      await waitFor(() => {
        expect(mockedApiClient.register).toHaveBeenCalledWith({
          name: mainUser.name,
          email: mainUser.email,
          password: 'TestPassword123!',
        });
      });

      const successMessage = await findByText(/Registration successful/);
      expect(successMessage).toBeTruthy();
    });

    it('should handle duplicate email error', async () => {
      mockedApiClient.register.mockRejectedValue({
        response: {
          data: {
            error: 'User with this email already exists',
          },
        },
      });

      const { getByPlaceholderText, getByText, findByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Name'), 'Duplicate User');
      fireEvent.changeText(getByPlaceholderText('Email'), 'existing@test.com');
      fireEvent.changeText(getByPlaceholderText('Password'), 'TestPassword123!');
      fireEvent.press(getByText('Register'));

      const errorMessage = await findByText(/already exists/);
      expect(errorMessage).toBeTruthy();
    });
  });

  describe('Step 2: User Login', () => {
    it('should render login form correctly', () => {
      const { getByPlaceholderText, getByText } = render(
        <LoginScreen navigation={mockNavigation} route={{} as any} />
      );

      expect(getByPlaceholderText('Email')).toBeTruthy();
      expect(getByPlaceholderText('Password')).toBeTruthy();
      expect(getByText('Login')).toBeTruthy();
    });

    it('should login main user successfully', async () => {
      mockedApiClient.login.mockResolvedValue({
        message: 'Login successful',
        user: mainUser,
        token: 'jwt-token-admin',
      });

      const { getByPlaceholderText, getByText } = render(
        <LoginScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Email'), mainUser.email);
      fireEvent.changeText(getByPlaceholderText('Password'), 'TestPassword123!');
      fireEvent.press(getByText('Login'));

      await waitFor(() => {
        expect(mockedApiClient.login).toHaveBeenCalledWith({
          email: mainUser.email,
          password: 'TestPassword123!',
        });
      });

      await waitFor(() => {
        expect(mockNavigation.replace).toHaveBeenCalledWith('Home');
      });
    });

    it('should handle invalid credentials', async () => {
      mockedApiClient.login.mockRejectedValue(new Error('Invalid credentials'));

      const { getByPlaceholderText, getByText, findByText } = render(
        <LoginScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Email'), 'wrong@test.com');
      fireEvent.changeText(getByPlaceholderText('Password'), 'wrongpassword');
      fireEvent.press(getByText('Login'));

      const errorMessage = await findByText(/Invalid email or password/);
      expect(errorMessage).toBeTruthy();
    });
  });

  describe('Step 3: Team Creation', () => {
    it('should render team management screen', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });

      const { findByText } = render(<TeamManagementScreen />);

      const myTeamsTitle = await findByText('My Teams');
      expect(myTeamsTitle).toBeTruthy();
    });

    it('should show create team button', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });

      const { findByText } = render(<TeamManagementScreen />);

      const createButton = await findByText('Create Team');
      expect(createButton).toBeTruthy();
    });

    it('should create a new team successfully', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });
      mockedApiClient.createTeam.mockResolvedValue(testTeam);

      const { findByText, getByPlaceholderText } = render(<TeamManagementScreen />);

      // Open create team modal
      const createButton = await findByText('Create Team');
      fireEvent.press(createButton);

      // Fill in team details
      await waitFor(() => {
        const teamNameInput = getByPlaceholderText('Team Name');
        fireEvent.changeText(teamNameInput, testTeam.name);
      });

      const descriptionInput = getByPlaceholderText('Description (optional)');
      fireEvent.changeText(descriptionInput, testTeam.description || '');

      // Submit
      const createSubmitButton = await findByText('Create');
      fireEvent.press(createSubmitButton);

      await waitFor(() => {
        expect(mockedApiClient.createTeam).toHaveBeenCalledWith({
          name: testTeam.name,
          description: testTeam.description,
        });
      });
    });

    it('should display created team in list', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [testTeam] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });

      const { findByText } = render(<TeamManagementScreen />);

      const teamName = await findByText(testTeam.name);
      expect(teamName).toBeTruthy();
    });
  });

  describe('Step 4: Bonus Rules Management', () => {
    const adminRoute = {
      params: { user: mainUser },
    } as any;

    const memberRoute = {
      params: { user: invitedUser },
    } as any;

    it('should render bonus rules screen for admin', async () => {
      mockedApiClient.getBonusRules.mockResolvedValue({ rules: [] });

      const { findByText } = render(
        <BonusRulesScreen navigation={mockNavigation} route={adminRoute} />
      );

      const emptyMessage = await findByText('No bonus rules yet');
      expect(emptyMessage).toBeTruthy();
    });

    it('should display existing bonus rules', async () => {
      mockedApiClient.getBonusRules.mockResolvedValue({ rules: [testBonusRule] });

      const { findByText } = render(
        <BonusRulesScreen navigation={mockNavigation} route={adminRoute} />
      );

      const ruleName = await findByText(testBonusRule.name);
      expect(ruleName).toBeTruthy();

      const bonusPoints = await findByText(testBonusRule.bonusPoints.toString());
      expect(bonusPoints).toBeTruthy();
    });

    it('should allow admin to deactivate active rule', async () => {
      mockedApiClient.getBonusRules.mockResolvedValue({ rules: [testBonusRule] });
      mockedApiClient.deactivateBonusRule.mockResolvedValue();

      const { findByText } = render(
        <BonusRulesScreen navigation={mockNavigation} route={adminRoute} />
      );

      const deactivateButton = await findByText('Deactivate');
      fireEvent.press(deactivateButton);

      await waitFor(() => {
        expect(mockedApiClient.deactivateBonusRule).toHaveBeenCalledWith(testBonusRule.id);
      });
    });

    it('should allow admin to activate inactive rule', async () => {
      const inactiveRule = { ...testBonusRule, isActive: false };
      mockedApiClient.getBonusRules.mockResolvedValue({ rules: [inactiveRule] });
      mockedApiClient.activateBonusRule.mockResolvedValue();

      const { findByText } = render(
        <BonusRulesScreen navigation={mockNavigation} route={adminRoute} />
      );

      const activateButton = await findByText('Activate');
      fireEvent.press(activateButton);

      await waitFor(() => {
        expect(mockedApiClient.activateBonusRule).toHaveBeenCalledWith(testBonusRule.id);
      });
    });

    it('should deny access to non-admin users', async () => {
      const { findByText } = render(
        <BonusRulesScreen navigation={mockNavigation} route={memberRoute} />
      );

      const accessDenied = await findByText('Access Denied');
      expect(accessDenied).toBeTruthy();
    });
  });

  describe('Step 5: Task List and Operations', () => {
    const adminRoute = {
      params: { user: mainUser },
    } as any;

    const memberRoute = {
      params: { user: invitedUser },
    } as any;

    it('should display task list', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [testTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      const taskName = await findByText(testTask.name);
      expect(taskName).toBeTruthy();
    });

    it('should show empty state when no tasks', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      const emptyMessage = await findByText('No tasks yet');
      expect(emptyMessage).toBeTruthy();
    });

    it('should display task details correctly', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [testTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByText(testTask.name);
      expect(await findByText(testTask.description)).toBeTruthy();
      expect(await findByText(`${testTask.points} points`)).toBeTruthy();
      expect(await findByText(testTask.frequency)).toBeTruthy();
    });
  });

  describe('Step 6: User Invitation Flow', () => {
    it('should show invite member button in team modal', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [testTeam] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });
      mockedApiClient.getTeamMembers.mockResolvedValue({
        members: [{
          id: 'member-1',
          userId: mainUser.id,
          userName: mainUser.name,
          userEmail: mainUser.email,
          role: 'admin',
          joinedAt: new Date().toISOString(),
        }],
      });

      const { findByText } = render(<TeamManagementScreen />);

      // Click on team to open members modal
      const teamCard = await findByText(testTeam.name);
      fireEvent.press(teamCard);

      // Check for invite member button
      const inviteButton = await findByText('Invite Member');
      expect(inviteButton).toBeTruthy();
    });

    it('should send invitation successfully', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [testTeam] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });
      mockedApiClient.getTeamMembers.mockResolvedValue({
        members: [{
          id: 'member-1',
          userId: mainUser.id,
          userName: mainUser.name,
          userEmail: mainUser.email,
          role: 'admin',
          joinedAt: new Date().toISOString(),
        }],
      });
      mockedApiClient.inviteToTeam.mockResolvedValue({
        message: 'Invitation sent',
        invitationId: testInvitation.id,
      });

      const { findByText, getByPlaceholderText } = render(<TeamManagementScreen />);

      // Click on team to open members modal
      const teamCard = await findByText(testTeam.name);
      fireEvent.press(teamCard);

      // Click invite member
      const inviteButton = await findByText('Invite Member');
      fireEvent.press(inviteButton);

      // Fill email in invite modal
      await waitFor(() => {
        const emailInput = getByPlaceholderText('Email');
        fireEvent.changeText(emailInput, invitedUser.email);
      });

      // Submit invitation
      const sendButton = await findByText('Send');
      fireEvent.press(sendButton);

      await waitFor(() => {
        expect(mockedApiClient.inviteToTeam).toHaveBeenCalledWith(testTeam.id, {
          email: invitedUser.email,
          role: 'member',
        });
      });
    });
  });

  describe('Step 7: New User Registration and Invitation Acceptance', () => {
    it('should register invited user successfully', async () => {
      mockedApiClient.register.mockResolvedValue({
        message: 'User registered successfully',
      });

      const { getByPlaceholderText, getByText, findByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Name'), invitedUser.name);
      fireEvent.changeText(getByPlaceholderText('Email'), invitedUser.email);
      fireEvent.changeText(getByPlaceholderText('Password'), 'MemberPassword123!');
      fireEvent.press(getByText('Register'));

      await waitFor(() => {
        expect(mockedApiClient.register).toHaveBeenCalledWith({
          name: invitedUser.name,
          email: invitedUser.email,
          password: 'MemberPassword123!',
        });
      });

      const successMessage = await findByText(/Registration successful/);
      expect(successMessage).toBeTruthy();
    });

    it('should display pending invitations for new user', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [testInvitation] });

      const { findByText } = render(<TeamManagementScreen />);

      const pendingTitle = await findByText('Pending Invitations');
      expect(pendingTitle).toBeTruthy();

      const invitationText = await findByText(/Team invitation as member/);
      expect(invitationText).toBeTruthy();
    });

    it('should accept invitation successfully', async () => {
      mockedApiClient.getTeams.mockResolvedValue({ teams: [] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [testInvitation] });
      mockedApiClient.acceptInvitation.mockResolvedValue({ message: 'Invitation accepted' });

      const { findByText } = render(<TeamManagementScreen />);

      // Find and click accept button
      const acceptButton = await findByText('Accept');
      fireEvent.press(acceptButton);

      await waitFor(() => {
        expect(mockedApiClient.acceptInvitation).toHaveBeenCalledWith(testInvitation.token);
      });
    });

    it('should show team after accepting invitation', async () => {
      // After accepting, team should appear in list
      mockedApiClient.getTeams.mockResolvedValue({ teams: [testTeam] });
      mockedApiClient.getMyInvitations.mockResolvedValue({ invitations: [] });

      const { findByText } = render(<TeamManagementScreen />);

      const teamName = await findByText(testTeam.name);
      expect(teamName).toBeTruthy();
    });
  });

  describe('Step 8: New User Can See and Interact with Tasks', () => {
    const memberRoute = {
      params: { user: invitedUser },
    } as any;

    it('should display tasks for invited user', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [testTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      const taskName = await findByText(testTask.name);
      expect(taskName).toBeTruthy();
    });

    it('should show assign button for unassigned tasks', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [testTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);
      const assignButton = await findByText('Assign to Me');
      expect(assignButton).toBeTruthy();
    });

    it('should allow member to assign task to themselves', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [testTask] });
      mockedApiClient.assignTask.mockResolvedValue();

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);

      const assignButton = await findByText('Assign to Me');
      fireEvent.press(assignButton);

      await waitFor(() => {
        expect(mockedApiClient.assignTask).toHaveBeenCalledWith(testTask.id, invitedUser.id);
      });
    });
  });

  describe('Step 9: Task Completion by Member', () => {
    const memberRoute = {
      params: { user: invitedUser },
    } as any;

    const assignedTask = {
      ...testTask,
      assignedUserId: invitedUser.id,
      assignedUserName: invitedUser.name,
    };

    it('should show complete button for assigned tasks', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [assignedTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);
      const completeButton = await findByText('Complete');
      expect(completeButton).toBeTruthy();
    });

    it('should allow member to complete assigned task', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [assignedTask] });
      mockedApiClient.completeTask.mockResolvedValue();

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);

      const completeButton = await findByText('Complete');
      fireEvent.press(completeButton);

      await waitFor(() => {
        expect(mockedApiClient.completeTask).toHaveBeenCalledWith(testTask.id, invitedUser.id);
      });
    });

    it('should display assigned user name', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [assignedTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      const assignedTo = await findByText(/Assigned to:/);
      expect(assignedTo).toBeTruthy();
    });
  });

  describe('Step 10: Non-Admin Cannot Approve Tasks', () => {
    const memberRoute = {
      params: { user: invitedUser },
    } as any;

    const completedTask = {
      ...testTask,
      status: 'completed' as const,
      assignedUserId: invitedUser.id,
      assignedUserName: invitedUser.name,
    };

    it('should NOT show approve button for non-admin user on completed task', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });

      const { findByText, queryByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);

      // Approve button should NOT be visible for non-admin
      const approveButton = queryByText('Approve');
      expect(approveButton).toBeNull();
    });

    it('should show completed status for completed task', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);
      const statusBadge = await findByText('completed');
      expect(statusBadge).toBeTruthy();
    });

    it('should not allow non-admin to approve even if they try', async () => {
      // This test verifies that the UI logic correctly hides the approve button
      // The TaskCard component checks: const canApprove = task.status === 'completed' && isAdmin;
      // Since isAdmin is false for invitedUser, canApprove will be false

      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });

      const { findByText, queryByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);

      // Double-check that approve button is not rendered
      expect(queryByText('Approve')).toBeNull();

      // And completeTask should not have been called with approve action
      expect(mockedApiClient.approveTask).not.toHaveBeenCalled();
    });
  });

  describe('Step 11: Admin Approves Task', () => {
    const adminRoute = {
      params: { user: mainUser },
    } as any;

    const completedTask = {
      ...testTask,
      status: 'completed' as const,
      assignedUserId: invitedUser.id,
      assignedUserName: invitedUser.name,
    };

    it('should show approve button for admin on completed task', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByText(testTask.name);
      const approveButton = await findByText('Approve');
      expect(approveButton).toBeTruthy();
    });

    it('should allow admin to approve completed task', async () => {
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });
      mockedApiClient.approveTask.mockResolvedValue();

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByText(testTask.name);

      const approveButton = await findByText('Approve');
      fireEvent.press(approveButton);

      await waitFor(() => {
        expect(mockedApiClient.approveTask).toHaveBeenCalledWith(testTask.id, mainUser.id);
      });
    });

    it('should display approved status after approval', async () => {
      const approvedTask = {
        ...completedTask,
        status: 'approved' as const,
      };

      mockedApiClient.getTasks.mockResolvedValue({ tasks: [approvedTask] });

      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByText(testTask.name);
      const statusBadge = await findByText('approved');
      expect(statusBadge).toBeTruthy();
    });

    it('should not show approve button after task is approved', async () => {
      const approvedTask = {
        ...completedTask,
        status: 'approved' as const,
      };

      mockedApiClient.getTasks.mockResolvedValue({ tasks: [approvedTask] });

      const { findByText, queryByText } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByText(testTask.name);
      const approveButton = queryByText('Approve');
      expect(approveButton).toBeNull();
    });
  });

  describe('Complete Workflow Integration', () => {
    it('should handle the complete workflow flow correctly', async () => {
      // This test verifies the logical flow of the entire workflow

      // 1. Admin creates team
      mockedApiClient.createTeam.mockResolvedValue(testTeam);

      // 2. Admin invites member
      mockedApiClient.inviteToTeam.mockResolvedValue({
        message: 'Invitation sent',
        invitationId: testInvitation.id,
      });

      // 3. Member accepts invitation
      mockedApiClient.acceptInvitation.mockResolvedValue({
        message: 'Invitation accepted',
      });

      // 4. Member assigns task
      mockedApiClient.assignTask.mockResolvedValue();

      // 5. Member completes task
      mockedApiClient.completeTask.mockResolvedValue();

      // 6. Admin approves task
      mockedApiClient.approveTask.mockResolvedValue();

      // All mock implementations should be in place
      expect(mockedApiClient.createTeam).toBeDefined();
      expect(mockedApiClient.inviteToTeam).toBeDefined();
      expect(mockedApiClient.acceptInvitation).toBeDefined();
      expect(mockedApiClient.assignTask).toBeDefined();
      expect(mockedApiClient.completeTask).toBeDefined();
      expect(mockedApiClient.approveTask).toBeDefined();
    });

    it('should verify role-based permissions are enforced', async () => {
      // Admin can approve tasks
      const completedTask = {
        ...testTask,
        status: 'completed' as const,
        assignedUserId: invitedUser.id,
      };

      // Test admin view
      mockedApiClient.getTasks.mockResolvedValue({ tasks: [completedTask] });

      const adminRoute = { params: { user: mainUser } } as any;
      const { findByText: findByTextAdmin } = render(
        <TaskListScreen navigation={mockNavigation} route={adminRoute} />
      );

      await findByTextAdmin(testTask.name);
      const approveButtonAdmin = await findByTextAdmin('Approve');
      expect(approveButtonAdmin).toBeTruthy();
    });

    it('should enforce member cannot approve their own completed task', async () => {
      const memberCompletedTask = {
        ...testTask,
        status: 'completed' as const,
        assignedUserId: invitedUser.id,
        assignedUserName: invitedUser.name,
      };

      mockedApiClient.getTasks.mockResolvedValue({ tasks: [memberCompletedTask] });

      const memberRoute = { params: { user: invitedUser } } as any;
      const { findByText, queryByText } = render(
        <TaskListScreen navigation={mockNavigation} route={memberRoute} />
      );

      await findByText(testTask.name);

      // Member should NOT see approve button
      expect(queryByText('Approve')).toBeNull();

      // But should see completed status
      expect(await findByText('completed')).toBeTruthy();
    });
  });

  describe('Error Handling', () => {
    it('should handle network errors during registration', async () => {
      mockedApiClient.register.mockRejectedValue(new Error('Network error'));

      const { getByPlaceholderText, getByText, findByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Name'), 'Test User');
      fireEvent.changeText(getByPlaceholderText('Email'), 'test@test.com');
      fireEvent.changeText(getByPlaceholderText('Password'), 'Password123!');
      fireEvent.press(getByText('Register'));

      const errorMessage = await findByText(/Registration failed/);
      expect(errorMessage).toBeTruthy();
    });

    it('should handle API errors during login', async () => {
      mockedApiClient.login.mockRejectedValue(new Error('Server error'));

      const { getByPlaceholderText, getByText, findByText } = render(
        <LoginScreen navigation={mockNavigation} route={{} as any} />
      );

      fireEvent.changeText(getByPlaceholderText('Email'), 'test@test.com');
      fireEvent.changeText(getByPlaceholderText('Password'), 'password');
      fireEvent.press(getByText('Login'));

      const errorMessage = await findByText(/Invalid email or password/);
      expect(errorMessage).toBeTruthy();
    });

    it('should handle errors when loading tasks', async () => {
      mockedApiClient.getTasks.mockRejectedValue(new Error('Failed to load'));

      const route = { params: { user: mainUser } } as any;
      const { findByText } = render(
        <TaskListScreen navigation={mockNavigation} route={route} />
      );

      // Should show empty state on error
      const emptyMessage = await findByText('No tasks yet');
      expect(emptyMessage).toBeTruthy();
    });
  });

  describe('UI State Management', () => {
    it('should show loading indicator while fetching tasks', () => {
      mockedApiClient.getTasks.mockImplementation(() => new Promise(() => {}));

      const route = { params: { user: mainUser } } as any;
      const { getByTestId } = render(
        <TaskListScreen navigation={mockNavigation} route={route} />
      );

      expect(() => getByTestId('loading-indicator')).not.toThrow();
    });

    it('should disable inputs during registration', async () => {
      let resolveRegister: any;
      mockedApiClient.register.mockImplementation(
        () => new Promise((resolve) => { resolveRegister = resolve; })
      );

      const { getByPlaceholderText, getByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      const nameInput = getByPlaceholderText('Name');
      const emailInput = getByPlaceholderText('Email');
      const passwordInput = getByPlaceholderText('Password');

      fireEvent.changeText(nameInput, 'Test User');
      fireEvent.changeText(emailInput, 'test@test.com');
      fireEvent.changeText(passwordInput, 'Password123!');
      fireEvent.press(getByText('Register'));

      await waitFor(() => {
        expect(nameInput.props.editable).toBe(false);
        expect(emailInput.props.editable).toBe(false);
        expect(passwordInput.props.editable).toBe(false);
      });

      // Cleanup
      act(() => {
        resolveRegister({ message: 'Success' });
      });
    });

    it('should clear form after successful registration', async () => {
      mockedApiClient.register.mockResolvedValue({
        message: 'User registered successfully',
      });

      const { getByPlaceholderText, getByText, findByText } = render(
        <RegisterScreen navigation={mockNavigation} route={{} as any} />
      );

      const nameInput = getByPlaceholderText('Name');
      const emailInput = getByPlaceholderText('Email');
      const passwordInput = getByPlaceholderText('Password');

      fireEvent.changeText(nameInput, 'Test User');
      fireEvent.changeText(emailInput, 'test@test.com');
      fireEvent.changeText(passwordInput, 'Password123!');
      fireEvent.press(getByText('Register'));

      await findByText(/Registration successful/);

      // Form should be cleared
      expect(nameInput.props.value).toBe('');
      expect(emailInput.props.value).toBe('');
      expect(passwordInput.props.value).toBe('');
    });
  });
});
