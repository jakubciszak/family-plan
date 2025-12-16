/**
 * Test fixtures and helpers for Playwright tests
 */

// Mock API responses for testing
export const mockApiResponses = {
  // User authentication responses
  loginSuccess: {
    user: {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      role: 'ROLE_USER'
    }
  },
  
  loginAdmin: {
    user: {
      id: 2,
      name: 'Admin User',
      email: 'admin@example.com',
      role: 'ROLE_ADMIN'
    }
  },
  
  loginError: {
    error: 'Invalid email or password'
  },
  
  // User info
  currentUser: {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    role: 'ROLE_USER'
  },
  
  currentAdmin: {
    id: 2,
    name: 'Admin User',
    email: 'admin@example.com',
    role: 'ROLE_ADMIN'
  },
  
  // Task list responses
  emptyTasks: {
    tasks: []
  },
  
  sampleTasks: {
    tasks: [
      {
        id: 1,
        name: 'Clean the kitchen',
        description: 'Wash dishes and clean counters',
        points: 10,
        frequency: 'daily',
        status: 'pending'
      },
      {
        id: 2,
        name: 'Take out trash',
        description: 'Empty all trash bins',
        points: 5,
        frequency: 'weekly',
        status: 'pending'
      },
      {
        id: 3,
        name: 'Vacuum living room',
        description: 'Vacuum the entire living room',
        points: 15,
        frequency: 'weekly',
        status: 'completed'
      }
    ]
  },
  
  // Task creation
  newTask: {
    id: 4,
    name: 'New Task',
    description: 'A new task description',
    points: 20,
    frequency: 'once',
    status: 'pending'
  }
};

// Test credentials
export const testCredentials = {
  user: {
    email: 'test@example.com',
    password: 'password123'
  },
  admin: {
    email: 'admin@example.com',
    password: 'admin123'
  },
  invalid: {
    email: 'invalid@example.com',
    password: 'wrongpassword'
  }
};

// Helper to setup authenticated session
export async function setupAuthenticatedSession(page, role = 'user') {
  const userData = role === 'admin' ? mockApiResponses.currentAdmin : mockApiResponses.currentUser;
  
  // Mock the /api/auth/me endpoint to return authenticated user
  await page.route('**/api/auth/me', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(userData)
    });
  });
  
  // Mock the /api/tasks endpoint
  await page.route('**/api/tasks', async route => {
    if (route.request().method() === 'GET') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockApiResponses.sampleTasks)
      });
    }
  });
}

// Helper to setup unauthenticated session
export async function setupUnauthenticatedSession(page) {
  // Mock the /api/auth/me endpoint to return 401
  await page.route('**/api/auth/me', async route => {
    await route.fulfill({
      status: 401,
      contentType: 'application/json',
      body: JSON.stringify({ error: 'Unauthorized' })
    });
  });
}

// Helper to wait for network idle
export async function waitForNetworkIdle(page) {
  await page.waitForLoadState('networkidle');
}

// Helper to take screenshot with name
export async function takeScreenshot(page, name) {
  await page.screenshot({ path: `tests/screenshots/${name}.png`, fullPage: true });
}
