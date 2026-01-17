import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import RegisterScreen from '../RegisterScreen';
import apiClient from '../../services/apiClient';

jest.mock('../../services/apiClient');
const mockedApiClient = apiClient as jest.Mocked<typeof apiClient>;

const mockNavigation = {
  navigate: jest.fn(),
  replace: jest.fn(),
  goBack: jest.fn(),
} as any;

describe('RegisterScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render registration form', () => {
    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    expect(getByPlaceholderText('Name')).toBeTruthy();
    expect(getByPlaceholderText('Email')).toBeTruthy();
    expect(getByPlaceholderText('Password')).toBeTruthy();
    expect(getByPlaceholderText('Phone Number (optional)')).toBeTruthy();
    expect(getByText('Register')).toBeTruthy();
  });

  it('should render title and subtitle', () => {
    const { getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    expect(getByText('Family Plan')).toBeTruthy();
    expect(getByText('Create your account')).toBeTruthy();
  });

  it('should have link to go back to login', () => {
    const { getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    expect(getByText(/Already have an account/)).toBeTruthy();
  });

  it('should navigate to login when clicking login link', () => {
    const { getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const loginLink = getByText('Login here');
    fireEvent.press(loginLink);

    expect(mockNavigation.navigate).toHaveBeenCalledWith('Login');
  });

  it('should handle successful registration', async () => {
    mockedApiClient.register.mockResolvedValue({
      message: 'User registered successfully. Please check your email to activate your account.',
    });

    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const phoneInput = getByPlaceholderText('Phone Number (optional)');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Jan Kowalski');
    fireEvent.changeText(emailInput, 'jan@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.changeText(phoneInput, '+48123456789');
    fireEvent.press(registerButton);

    await waitFor(() => {
      expect(mockedApiClient.register).toHaveBeenCalledWith({
        name: 'Jan Kowalski',
        email: 'jan@example.com',
        password: 'password123',
        phoneNumber: '+48123456789',
      });
    });

    const successMessage = await waitFor(() =>
      getByText(/Registration successful! Please check your email to activate your account/)
    );
    expect(successMessage).toBeTruthy();
  });

  it('should register successfully without phone number', async () => {
    mockedApiClient.register.mockResolvedValue({
      message: 'User registered successfully. Please check your email to activate your account.',
    });

    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Anna Nowak');
    fireEvent.changeText(emailInput, 'anna@example.com');
    fireEvent.changeText(passwordInput, 'securePass456');
    fireEvent.press(registerButton);

    await waitFor(() => {
      expect(mockedApiClient.register).toHaveBeenCalledWith({
        name: 'Anna Nowak',
        email: 'anna@example.com',
        password: 'securePass456',
      });
    });
  });

  it('should display error for duplicate email', async () => {
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

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Jan Kowalski');
    fireEvent.changeText(emailInput, 'existing@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(registerButton);

    const errorMessage = await findByText('An account with this email already exists');
    expect(errorMessage).toBeTruthy();
  });

  it('should display generic error for other failures', async () => {
    mockedApiClient.register.mockRejectedValue(new Error('Network error'));

    const { getByPlaceholderText, getByText, findByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Test User');
    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(registerButton);

    const errorMessage = await findByText('Registration failed. Please try again.');
    expect(errorMessage).toBeTruthy();
  });

  it('should not submit with empty required fields', () => {
    const { getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const registerButton = getByText('Register');
    fireEvent.press(registerButton);

    expect(mockedApiClient.register).not.toHaveBeenCalled();
  });

  it('should not submit with only name filled', () => {
    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Test User');
    fireEvent.press(registerButton);

    expect(mockedApiClient.register).not.toHaveBeenCalled();
  });

  it('should show loading indicator during registration', async () => {
    let resolveRegister: any;
    const registerPromise = new Promise((resolve) => {
      resolveRegister = resolve;
    });

    mockedApiClient.register.mockReturnValue(registerPromise as any);

    const { getByPlaceholderText, getByText, UNSAFE_queryByType } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Test User');
    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(registerButton);

    // Should show ActivityIndicator during loading
    await waitFor(() => {
      const activityIndicator = UNSAFE_queryByType(
        require('react-native').ActivityIndicator
      );
      expect(activityIndicator).toBeTruthy();
    });

    // Resolve the promise
    resolveRegister({
      message: 'Registration successful',
    });
  });

  it('should disable inputs during registration', async () => {
    let resolveRegister: any;
    const registerPromise = new Promise((resolve) => {
      resolveRegister = resolve;
    });

    mockedApiClient.register.mockReturnValue(registerPromise as any);

    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Test User');
    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(registerButton);

    await waitFor(() => {
      expect(nameInput.props.editable).toBe(false);
      expect(emailInput.props.editable).toBe(false);
      expect(passwordInput.props.editable).toBe(false);
    });

    // Resolve the promise
    resolveRegister({
      message: 'Registration successful',
    });
  });

  it('should have proper input types and attributes', () => {
    const { getByPlaceholderText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const phoneInput = getByPlaceholderText('Phone Number (optional)');

    expect(emailInput.props.keyboardType).toBe('email-address');
    expect(emailInput.props.autoCapitalize).toBe('none');
    expect(passwordInput.props.secureTextEntry).toBe(true);
    expect(phoneInput.props.keyboardType).toBe('phone-pad');
  });

  it('should clear form after successful registration', async () => {
    mockedApiClient.register.mockResolvedValue({
      message: 'User registered successfully. Please check your email to activate your account.',
    });

    const { getByPlaceholderText, getByText } = render(
      <RegisterScreen navigation={mockNavigation} route={{} as any} />
    );

    const nameInput = getByPlaceholderText('Name');
    const emailInput = getByPlaceholderText('Email');
    const passwordInput = getByPlaceholderText('Password');
    const registerButton = getByText('Register');

    fireEvent.changeText(nameInput, 'Test User');
    fireEvent.changeText(emailInput, 'test@example.com');
    fireEvent.changeText(passwordInput, 'password123');
    fireEvent.press(registerButton);

    await waitFor(() => {
      expect(mockedApiClient.register).toHaveBeenCalled();
    });

    // Wait for success message
    await waitFor(() => {
      expect(getByText(/Registration successful/)).toBeTruthy();
    });

    // Form should be cleared
    expect(nameInput.props.value).toBe('');
    expect(emailInput.props.value).toBe('');
    expect(passwordInput.props.value).toBe('');
  });
});
