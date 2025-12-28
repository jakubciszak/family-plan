/**
 * Test fixtures and helpers for Playwright tests
 */

// Mock API responses for testing
const mockApiResponses = {
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
  },
  
  // Bonus rules responses
  emptyBonusRules: {
    rules: []
  },
  
  sampleBonusRules: {
    rules: [
      {
        id: '550e8400-e29b-41d4-a716-446655440001',
        name: 'Dishwasher Streak',
        description: 'Earn 20 bonus points for emptying dishwasher 5 consecutive days',
        bonusPoints: 20,
        type: 'consecutive_days',
        config: {
          taskTemplateId: '550e8400-e29b-41d4-a716-446655440010',
          requiredDays: 5
        },
        isActive: true,
        createdAt: '2024-01-01T00:00:00Z'
      },
      {
        id: '550e8400-e29b-41d4-a716-446655440002',
        name: 'Monthly Champion',
        description: 'Complete 20 tasks in a month to earn 30 bonus points',
        bonusPoints: 30,
        type: 'monthly_task_count',
        config: {
          requiredCount: 20
        },
        isActive: false,
        createdAt: '2024-01-02T00:00:00Z'
      },
      {
        id: '550e8400-e29b-41d4-a716-446655440003',
        name: 'Weekly Warrior',
        description: 'Complete a task every day for 7 days',
        bonusPoints: 15,
        type: 'consecutive_days',
        config: {
          taskTemplateId: '550e8400-e29b-41d4-a716-446655440011',
          requiredDays: 7
        },
        isActive: true,
        createdAt: '2024-01-03T00:00:00Z'
      }
    ]
  },
  
  newBonusRule: {
    id: '550e8400-e29b-41d4-a716-446655440004',
    name: 'Test Bonus Rule',
    description: 'A test bonus rule',
    bonusPoints: 25,
    type: 'consecutive_days',
    config: {
      taskTemplateId: 'default-template-id',
      requiredDays: 3
    },
    isActive: false,
    createdAt: '2024-01-04T00:00:00Z'
  }
};

// Test credentials
const testCredentials = {
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
async function setupAuthenticatedSession(page, role = 'user') {
  const userData = role === 'admin' ? mockApiResponses.currentAdmin : mockApiResponses.currentUser;
  
  // Mock the /api/auth/me endpoint to return authenticated user
  await page.route('**/api/auth/me', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(userData)
    });
  });
  
  // Mock the user points endpoint
  await page.route('**/api/users/*/points', async route => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ balance: 100 })
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
async function setupUnauthenticatedSession(page) {
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
async function waitForNetworkIdle(page) {
  await page.waitForLoadState('networkidle');
}

// Helper to take screenshot with name
async function takeScreenshot(page, name) {
  await page.screenshot({ path: `tests/screenshots/${name}.png`, fullPage: true });
}

// Export all fixtures and helpers
module.exports = {
  mockApiResponses,
  testCredentials,
  setupAuthenticatedSession,
  setupUnauthenticatedSession,
  waitForNetworkIdle,
  takeScreenshot
};
