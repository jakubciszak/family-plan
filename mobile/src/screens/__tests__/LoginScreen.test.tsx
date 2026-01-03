import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import LoginScreen from '../LoginScreen';
import apiClient from '../../services/apiClient';

jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

const mockNavigation = {
  navigate: jest.fn(),
  replace: jest.fn(),
} as any;

describe('LoginScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render login form', () => {
    const { getByPlaceholderText, getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    expect(getByPlaceholderText('Email')).toBeTruthy();
    expect(getByPlaceholderText('Password')).toBeTruthy();
    expect(getByText('Login')).toBeTruthy();
  });

  it('should handle successful login', async () => {
    const mockUser = {
      id: '1',
      name: 'Test User',
      email: 'test@example.com',
      role: 'ROLE_USER',
    };

    mockedApiClient.login.mockResolvedValue({
      message: 'Login successful',
      user: mockUser,
      token: 'jwt-token',
    });

    const { getByPlaceholderText, getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password');
    fireEvent.press(loginButton);

    await waitFor(() => {
      expect(mockedApiClient.login).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password',
      });
      expect(mockNavigation.replace).toHaveBeenCalledWith('Home');
    });
  });

  it('should display error on failed login', async () => {
    mockedApiClient.login.mockRejectedValue(new Error('Invalid credentials'));

    const { getByPlaceholderText, getByText, findByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'wrong');
    fireEvent.press(loginButton);

    const errorMessage = await findByText('Invalid email or password');
    expect(errorMessage).toBeTruthy();
  });

  it('should not submit with empty fields', () => {
    const { getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const loginButton = getByText('Login');
    fireEvent.press(loginButton);

    expect(mockedApiClient.login).not.toHaveBeenCalled();
  });
});
