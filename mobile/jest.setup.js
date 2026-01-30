import '@testing-library/react-native/extend-expect';

// Mock AsyncStorage for all tests
jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
}));

// Use fake timers to control animations and async operations
beforeEach(() => {
  jest.useFakeTimers();
});

afterEach(() => {
  // Flush all pending timers and microtasks
  jest.runOnlyPendingTimers();
  jest.useRealTimers();
});

// Silence console.error for act() warnings and expected test errors
// These are expected warnings/errors in React Native testing
const originalError = console.error;
beforeAll(() => {
  console.error = (...args) => {
    const message = typeof args[0] === 'string' ? args[0] : '';

    // Silence act() warnings
    if (
      message.includes('Warning: An update to') &&
      message.includes('inside a test was not wrapped in act')
    ) {
      return;
    }

    // Silence expected error logs from error handling tests
    if (
      message.includes('Error loading user data:') ||
      message.includes('Error logging out:') ||
      message.includes('Error loading bonus rules:') ||
      message.includes('Error loading tasks:')
    ) {
      return;
    }

    originalError.call(console, ...args);
  };
});

afterAll(() => {
  console.error = originalError;
});
