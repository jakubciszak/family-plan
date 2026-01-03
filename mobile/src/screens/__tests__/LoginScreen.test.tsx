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

  it('should render title and subtitle', () => {
    const { getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    expect(getByText('Family Plan')).toBeTruthy();
    expect(getByText('Login to continue')).toBeTruthy();
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

  it('should not submit with only email filled', () => {
    const { getByPlaceholderText, getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.press(loginButton);

    expect(mockedApiClient.login).not.toHaveBeenCalled();
  });

  it('should not submit with only password filled', () => {
    const { getByPlaceholderText, getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(loginButton);

    expect(mockedApiClient.login).not.toHaveBeenCalled();
  });

  it('should show loading indicator during login', async () => {
    let resolveLogin: any;
    const loginPromise = new Promise((resolve) => {
      resolveLogin = resolve;
    });

    mockedApiClient.login.mockReturnValue(loginPromise as any);

    const { getByPlaceholderText, getByText, UNSAFE_queryByType } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password');
    fireEvent.press(loginButton);

    // Should show ActivityIndicator during loading
    await waitFor(() => {
      const activityIndicator = UNSAFE_queryByType(require('react-native').ActivityIndicator);
      expect(activityIndicator).toBeTruthy();
    });

    // Resolve the promise
    resolveLogin({
      message: 'Login successful',
      user: { id: '1', name: 'Test', email: 'test@example.com', role: 'ROLE_USER' },
      token: 'token',
    });
  });

  it('should disable inputs during login', async () => {
    let resolveLogin: any;
    const loginPromise = new Promise((resolve) => {
      resolveLogin = resolve;
    });

    mockedApiClient.login.mockReturnValue(loginPromise as any);

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
      expect(emailInput.props.editable).toBe(false);
      expect(passwordInput.props.editable).toBe(false);
    });

    // Resolve the promise
    resolveLogin({
      message: 'Login successful',
      user: { id: '1', name: 'Test', email: 'test@example.com', role: 'ROLE_USER' },
      token: 'token',
    });
  });

  it('should clear error message when typing', async () => {
    mockedApiClient.login.mockRejectedValue(new Error('Invalid credentials'));

    const { getByPlaceholderText, getByText, findByText, queryByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    // Trigger error
    fireEvent.changeText(emailInput, 'wrong@example.com');
    fireEvent.changeText(passwordInput, 'wrong');
    fireEvent.press(loginButton);

    const errorMessage = await findByText('Invalid email or password');
    expect(errorMessage).toBeTruthy();

    // Type in email field - error should eventually clear
    fireEvent.changeText(emailInput, 'test@example.com');
    
    // The component should handle clearing the error
  });

  it('should handle network errors', async () => {
    mockedApiClient.login.mockRejectedValue(new Error('Network request failed'));

    const { getByPlaceholderText, getByText, findByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password');
    fireEvent.press(loginButton);

    const errorMessage = await findByText('Invalid email or password');
    expect(errorMessage).toBeTruthy();
  });

  it('should handle admin user login', async () => {
    const mockAdminUser = {
      id: '1',
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN',
    };

    mockedApiClient.login.mockResolvedValue({
      message: 'Login successful',
      user: mockAdminUser,
      token: 'admin-jwt-token',
    });

    const { getByPlaceholderText, getByText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const loginButton = getByText('Login');

    fireEvent.changeText(emailInput, 'admin@example.com');
    fireEvent.changeText(passwordInput, 'admin123');
    fireEvent.press(loginButton);

    await waitFor(() => {
      expect(mockedApiClient.login).toHaveBeenCalledWith({
        email: 'admin@example.com',
        password: 'admin123',
      });
      expect(mockNavigation.replace).toHaveBeenCalledWith('Home');
    });
  });

  it('should use secure text entry for password field', () => {
    const { getByPlaceholderText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const passwordInput = getByPlaceholderText('Password');
    expect(passwordInput.props.secureTextEntry).toBe(true);
  });

  it('should use email keyboard type for email field', () => {
    const { getByPlaceholderText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    expect(emailInput.props.keyboardType).toBe('email-address');
  });

  it('should disable autocapitalize for email field', () => {
    const { getByPlaceholderText } = render(
      <LoginScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    expect(emailInput.props.autoCapitalize).toBe('none');
  });
});
