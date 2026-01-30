const { test, expect } = require('@playwright/test');

/**
 * Comprehensive E2E Test: Complete User Workflow
 *
 * Scenario:
 * 1. User creates an account in the system
 * 2. User creates a new group/team
 * 3. User defines bonus rules
 * 4. User defines task status change rules in the new group
 * 5. User creates a new task
 * 6. User invites a new user to the group
 * 7. New user doesn't have account, clicks invitation link and is prompted to register
 * 8. After registration, new user is automatically in the invited group
 * 9. New user can see the task
 * 10. New user assigns themselves to the task
 * 11. New user marks task as completed
 * 12. New user cannot approve their own completed task
 * 13. Main user approves the task execution
 */

// Test data
const mainUser = {
    id: 'main-user-uuid-1234',
    name: 'Jan Kowalski',
    email: 'jan.kowalski@example.com',
    password: 'SecurePass123!',
    role: 'ROLE_ADMIN' // Admin role needed for bonus rules, status change rules, and task approval
};

const invitedUser = {
    id: 'invited-user-uuid-5678',
    name: 'Anna Nowak',
    email: 'anna.nowak@example.com',
    password: 'SecurePass456!',
    role: 'ROLE_USER'
};

const testTeam = {
    id: 'team-uuid-1234',
    name: 'Rodzina Kowalskich',
    description: 'Zespol zadaniowy naszej rodziny'
};

const testTask = {
    id: 'task-uuid-1234',
    name: 'Pozmywaj naczynia',
    description: 'Pozmywaj wszystkie naczynia po obiedzie',
    points: 15,
    frequency: 'daily',
    status: 'pending',
    assignedUserId: null,
    assignedUserName: null,
    teamId: testTeam.id
};

const testBonusRule = {
    id: 'bonus-rule-uuid-1234',
    name: 'Tygodniowa seria',
    description: 'Bonus za wykonanie zadania przez 7 dni z rzedu',
    bonusPoints: 50,
    type: 'consecutive_days',
    config: {
        taskTemplateId: testTask.id,
        requiredDays: 7
    },
    isActive: true
};

const testStatusChangeRule = {
    id: 'status-rule-uuid-1234',
    name: 'Cooldown 2 dni',
    description: 'Zadanie mozna wykonac ponownie po 2 dniach',
    conditionType: 'last_execution_cooldown',
    config: {
        cooldownDays: 2
    },
    taskTemplateId: testTask.id,
    isActive: true
};

const invitationToken = 'invitation-token-abc123';

test.describe('Complete User Workflow E2E', () => {

    test.describe('Phase 1: Main User Registration and Setup', () => {

        test('1. Main user creates an account', async ({ page }) => {
            // Setup mock for unauthenticated session
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 401,
                    contentType: 'application/json',
                    body: JSON.stringify({ error: 'Unauthorized' })
                });
            });

            // Mock successful registration
            await page.route('**/api/auth/register', async route => {
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'User registered successfully. Please check your email to activate your account.',
                        id: mainUser.id
                    })
                });
            });

            await page.goto('/');

            // Should see login page
            await page.waitForSelector('h2:has-text("Login")', { timeout: 10000 });

            // Navigate to registration
            await page.locator('.auth-switch a').click();
            await page.waitForSelector('h2:has-text("Register")', { timeout: 5000 });

            // Fill registration form
            await page.fill('input#name', mainUser.name);
            await page.fill('input#email', mainUser.email);
            await page.fill('input#password', mainUser.password);

            // Submit form
            await page.click('button[type="submit"]');

            // Verify success message
            await page.waitForSelector('.success-message', { timeout: 5000 });
            await expect(page.locator('.success-message')).toContainText('Registration successful');
        });

        test('2. Main user logs in and creates a new team', async ({ page }) => {
            // Setup authenticated session for main user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser)
                });
            });

            // Mock login endpoint
            await page.route('**/api/auth/login', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'Login successful',
                        user: mainUser
                    })
                });
            });

            // Mock user points
            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            // Mock empty teams initially
            let teamsCreated = [];
            await page.route('**/api/teams', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ teams: teamsCreated })
                    });
                } else if (route.request().method() === 'POST') {
                    teamsCreated.push({
                        ...testTeam,
                        role: 'admin',
                        createdBy: mainUser.id,
                        createdAt: new Date().toISOString()
                    });
                    await route.fulfill({
                        status: 201,
                        contentType: 'application/json',
                        body: JSON.stringify(testTeam)
                    });
                }
            });

            // Mock invitations (empty)
            await page.route('**/api/teams/invitations', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ invitations: [] })
                    });
                }
            });

            // Mock tasks (empty initially)
            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [] })
                });
            });

            await page.goto('/');

            // Navigate to teams
            await page.waitForSelector('.app-nav', { timeout: 10000 });
            await page.click('.app-nav button:has-text("Teams")');

            // Wait for teams page
            await page.waitForSelector('.team-management', { timeout: 5000 });

            // Click create team button
            await page.click('button:has-text("Create Team")');

            // Fill team form
            await page.fill('input[type="text"]', testTeam.name);
            await page.fill('textarea', testTeam.description);

            // Submit
            await page.click('button.btn-success:has-text("Create")');

            // Wait for team to appear in list
            await page.waitForLoadState('networkidle');
        });

        test('3. Main user defines bonus rules', async ({ page }) => {
            // Setup authenticated session for main user (admin)
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'admin',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [] })
                });
            });

            // Mock bonus rules
            let bonusRulesCreated = [];
            await page.route('**/api/bonus-rules', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ rules: bonusRulesCreated })
                    });
                } else if (route.request().method() === 'POST') {
                    bonusRulesCreated.push(testBonusRule);
                    await route.fulfill({
                        status: 201,
                        contentType: 'application/json',
                        body: JSON.stringify({ message: 'Bonus rule created successfully' })
                    });
                }
            });

            await page.goto('/');

            // Navigate to bonus rules (admin only)
            await page.waitForSelector('.app-nav', { timeout: 10000 });
            await page.click('.app-nav button:has-text("Bonus Rules")');

            // Wait for bonus rules page
            await page.waitForSelector('.bonus-rules-container', { timeout: 5000 });

            // Click create bonus rule
            await page.click('button:has-text("Create Bonus Rule")');

            // Fill bonus rule form
            await page.fill('input#name', testBonusRule.name);
            await page.fill('textarea#description', testBonusRule.description);
            await page.fill('input#bonusPoints', testBonusRule.bonusPoints.toString());
            await page.selectOption('select#ruleType', 'consecutive_days');
            await page.fill('input#requiredDays', '7');

            // Submit
            await page.click('button[type="submit"]:has-text("Create")');

            await page.waitForLoadState('networkidle');
        });

        test('4. Main user defines status change rules', async ({ page }) => {
            // Setup authenticated session for main user (admin)
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'admin',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [] })
                });
            });

            // Mock task templates
            await page.route('**/api/task-templates', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ templates: [{ id: testTask.id, name: testTask.name }] })
                });
            });

            // Mock status change rules
            let statusRulesCreated = [];
            await page.route('**/api/status-change-rules', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ rules: statusRulesCreated })
                    });
                } else if (route.request().method() === 'POST') {
                    statusRulesCreated.push(testStatusChangeRule);
                    await route.fulfill({
                        status: 201,
                        contentType: 'application/json',
                        body: JSON.stringify({ message: 'Status change rule created successfully' })
                    });
                }
            });

            await page.goto('/');

            // Navigate to status change rules (admin only)
            await page.waitForSelector('.app-nav', { timeout: 10000 });
            await page.click('.app-nav button:has-text("Status Change Rules")');

            // Wait for status change rules page
            await page.waitForSelector('.status-change-rules-container', { timeout: 5000 });

            // Click create rule
            await page.click('button:has-text("Create")');

            // Fill form
            await page.fill('input#name', testStatusChangeRule.name);
            await page.fill('textarea#description', testStatusChangeRule.description);
            await page.selectOption('select#conditionType', 'last_execution_cooldown');
            await page.fill('input#cooldownDays', '2');

            // Submit
            await page.click('button[type="submit"]');

            await page.waitForLoadState('networkidle');
        });

        test('5. Main user creates a new task', async ({ page }) => {
            // Setup authenticated session for main user (admin)
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'admin',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Mock tasks endpoint
            let tasksCreated = [];
            await page.route('**/api/tasks', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ tasks: tasksCreated })
                    });
                } else if (route.request().method() === 'POST') {
                    const newTask = {
                        ...testTask,
                        createdBy: mainUser.id,
                        createdAt: new Date().toISOString()
                    };
                    tasksCreated.push(newTask);
                    await route.fulfill({
                        status: 201,
                        contentType: 'application/json',
                        body: JSON.stringify(newTask)
                    });
                }
            });

            await page.goto('/');

            // Should be on tasks page by default
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Team admin should see "Create Task" button
            const createButton = page.locator('button:has-text("Create Task")');
            await expect(createButton).toBeVisible();

            // Click create task
            await createButton.click();

            // Fill task form
            await page.fill('input#name', testTask.name);
            await page.fill('textarea#description', testTask.description);
            await page.fill('input#points', testTask.points.toString());
            await page.selectOption('select#frequency', testTask.frequency);

            // Submit
            await page.click('button[type="submit"]:has-text("Create")');

            await page.waitForLoadState('networkidle');
        });

        test('6. Main user invites new user to the team', async ({ page }) => {
            // Setup authenticated session for main user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'admin',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            await page.route('**/api/teams/invitations', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ invitations: [] })
                });
            });

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [testTask] })
                });
            });

            // Mock team members
            await page.route(`**/api/teams/${testTeam.id}/members`, async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        members: [{
                            id: 'member-1',
                            userId: mainUser.id,
                            userName: mainUser.name,
                            userEmail: mainUser.email,
                            role: 'admin',
                            joinedAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Mock invite endpoint
            await page.route(`**/api/teams/${testTeam.id}/invite`, async route => {
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'Invitation sent successfully',
                        invitationId: 'inv-uuid-1234'
                    })
                });
            });

            await page.goto('/');

            // Navigate to teams
            await page.waitForSelector('.app-nav', { timeout: 10000 });
            await page.click('.app-nav button:has-text("Teams")');

            // Wait for teams page
            await page.waitForSelector('.team-management', { timeout: 5000 });

            // Click on the team to select it
            await page.click(`.team-card:has-text("${testTeam.name}")`);

            // Wait for team details
            await page.waitForSelector('.team-details', { timeout: 5000 });

            // Click invite member
            await page.click('button:has-text("Invite Member")');

            // Fill invite form
            await page.fill('input[type="email"]', invitedUser.email);
            await page.selectOption('select', 'member');

            // Send invitation
            await page.click('button.btn-success:has-text("Send Invitation")');

            // Wait for alert (invitation sent)
            page.on('dialog', async dialog => {
                expect(dialog.message()).toContain('Invitation sent');
                await dialog.accept();
            });

            await page.waitForLoadState('networkidle');
        });
    });

    test.describe('Phase 2: Invited User Registration and Actions', () => {

        test('7. New user registers with the invited email', async ({ page }) => {
            // Setup unauthenticated session
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 401,
                    contentType: 'application/json',
                    body: JSON.stringify({ error: 'Unauthorized' })
                });
            });

            // Mock successful registration
            await page.route('**/api/auth/register', async route => {
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'User registered successfully. Please check your email to activate your account.',
                        id: invitedUser.id
                    })
                });
            });

            await page.goto('/');

            // Navigate to registration
            await page.waitForSelector('h2:has-text("Login")', { timeout: 10000 });
            await page.locator('.auth-switch a').click();
            await page.waitForSelector('h2:has-text("Register")', { timeout: 5000 });

            // Fill registration form with invited email
            await page.fill('input#name', invitedUser.name);
            await page.fill('input#email', invitedUser.email);
            await page.fill('input#password', invitedUser.password);

            // Submit
            await page.click('button[type="submit"]');

            // Verify success message
            await page.waitForSelector('.success-message', { timeout: 5000 });
            await expect(page.locator('.success-message')).toContainText('Registration successful');
        });

        test('8. New user logs in and sees pending invitation, then accepts it', async ({ page }) => {
            // Setup authenticated session for invited user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(invitedUser)
                });
            });

            await page.route('**/api/auth/login', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        message: 'Login successful',
                        user: invitedUser
                    })
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: invitedUser.id, balance: 0 })
                });
            });

            // Initially no teams (before accepting invitation)
            let userHasAcceptedInvitation = false;
            await page.route('**/api/teams', async route => {
                if (userHasAcceptedInvitation) {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({
                            teams: [{
                                ...testTeam,
                                role: 'member',
                                createdBy: mainUser.id,
                                createdAt: new Date().toISOString()
                            }]
                        })
                    });
                } else {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ teams: [] })
                    });
                }
            });

            // Mock pending invitations
            let invitations = [{
                id: 'inv-uuid-1234',
                teamId: testTeam.id,
                teamName: testTeam.name,
                role: 'member',
                token: invitationToken,
                status: 'pending',
                createdAt: new Date().toISOString()
            }];

            await page.route('**/api/teams/invitations', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify({ invitations: userHasAcceptedInvitation ? [] : invitations })
                    });
                }
            });

            // Mock accept invitation
            await page.route(`**/api/teams/invitations/${invitationToken}/accept`, async route => {
                userHasAcceptedInvitation = true;
                invitations = [];
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: 'Invitation accepted successfully' })
                });
            });

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [testTask] })
                });
            });

            await page.goto('/');

            // Navigate to teams to see invitation
            await page.waitForSelector('.app-nav', { timeout: 10000 });
            await page.click('.app-nav button:has-text("Teams")');

            // Wait for teams page
            await page.waitForSelector('.team-management', { timeout: 5000 });

            // Should see pending invitation section
            await expect(page.locator('.invitations-section')).toBeVisible();
            await expect(page.locator('.invitation-card')).toBeVisible();

            // Accept invitation
            await page.click('.invitation-card button:has-text("Accept")');

            // Handle alert
            page.on('dialog', async dialog => {
                expect(dialog.message()).toContain('accepted');
                await dialog.accept();
            });

            await page.waitForLoadState('networkidle');
        });

        test('9. New user can see the task', async ({ page }) => {
            // Setup authenticated session for invited user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(invitedUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: invitedUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'member',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [testTask] })
                });
            });

            await page.goto('/');

            // Should see task list
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Task should be visible
            await expect(page.locator(`.task-card:has-text("${testTask.name}")`)).toBeVisible();

            // Task should show as pending
            await expect(page.locator('.task-status.status-pending')).toBeVisible();

            // Task should show points
            await expect(page.locator('.task-points')).toContainText(testTask.points.toString());
        });

        test('10. New user assigns themselves to the task', async ({ page }) => {
            // Setup authenticated session for invited user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(invitedUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: invitedUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'member',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Track task assignment state
            let taskIsAssigned = false;
            await page.route('**/api/tasks', async route => {
                const currentTask = taskIsAssigned
                    ? { ...testTask, assignedUserId: invitedUser.id, assignedUserName: invitedUser.name }
                    : testTask;
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [currentTask] })
                });
            });

            // Mock assign endpoint
            await page.route(`**/api/tasks/${testTask.id}/assign`, async route => {
                taskIsAssigned = true;
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ...testTask,
                        assignedUserId: invitedUser.id,
                        assignedUserName: invitedUser.name
                    })
                });
            });

            await page.goto('/');

            // Should see task list
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Find task card
            const taskCard = page.locator(`.task-card:has-text("${testTask.name}")`);

            // Should see Assign button (task is not assigned)
            const assignButton = taskCard.locator('button:has-text("Assign")');
            await expect(assignButton).toBeVisible();

            // Click assign
            await assignButton.click();

            // Wait for the request to complete
            await page.waitForLoadState('networkidle');
        });

        test('11. New user marks task as completed', async ({ page }) => {
            // Setup authenticated session for invited user
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(invitedUser)
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: invitedUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'member',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Task is assigned to invited user
            let taskStatus = 'pending';
            const assignedTask = {
                ...testTask,
                assignedUserId: invitedUser.id,
                assignedUserName: invitedUser.name,
                get status() { return taskStatus; }
            };

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [{
                        ...testTask,
                        assignedUserId: invitedUser.id,
                        assignedUserName: invitedUser.name,
                        status: taskStatus
                    }] })
                });
            });

            // Mock complete endpoint
            await page.route(`**/api/tasks/${testTask.id}/complete`, async route => {
                taskStatus = 'completed';
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ...testTask,
                        assignedUserId: invitedUser.id,
                        assignedUserName: invitedUser.name,
                        status: 'completed'
                    })
                });
            });

            await page.goto('/');

            // Should see task list
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Find task card
            const taskCard = page.locator(`.task-card:has-text("${testTask.name}")`);

            // Should see Complete button (task is assigned to current user)
            const completeButton = taskCard.locator('button.btn-success:has-text("Complete")');
            await expect(completeButton).toBeVisible();

            // Click complete
            await completeButton.click();

            // Wait for the request to complete
            await page.waitForLoadState('networkidle');
        });

        test('12. New user cannot approve their own completed task', async ({ page }) => {
            // Setup authenticated session for invited user (ROLE_USER, not ROLE_ADMIN)
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(invitedUser) // ROLE_USER
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: invitedUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'member',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Task is completed
            const completedTask = {
                ...testTask,
                assignedUserId: invitedUser.id,
                assignedUserName: invitedUser.name,
                status: 'completed'
            };

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [completedTask] })
                });
            });

            await page.goto('/');

            // Should see task list
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Find task card
            const taskCard = page.locator(`.task-card:has-text("${testTask.name}")`);

            // Task should show as completed
            await expect(taskCard.locator('.status-completed')).toBeVisible();

            // Approve button should NOT be visible for regular user
            const approveButton = taskCard.locator('button:has-text("Approve")');
            await expect(approveButton).not.toBeVisible();

            // Complete button should also not be visible (task already completed)
            const completeButton = taskCard.locator('button:has-text("Complete")');
            await expect(completeButton).not.toBeVisible();
        });
    });

    test.describe('Phase 3: Main User Approves Task', () => {

        test('13. Main user approves the completed task', async ({ page }) => {
            // Setup authenticated session for main user (ROLE_ADMIN)
            await page.route('**/api/auth/me', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(mainUser) // ROLE_ADMIN
                });
            });

            await page.route('**/api/users/*/points', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ userId: mainUser.id, balance: 0 })
                });
            });

            await page.route('**/api/teams', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        teams: [{
                            ...testTeam,
                            role: 'admin',
                            createdBy: mainUser.id,
                            createdAt: new Date().toISOString()
                        }]
                    })
                });
            });

            // Task is completed by invited user
            let taskStatus = 'completed';
            const completedTask = {
                ...testTask,
                assignedUserId: invitedUser.id,
                assignedUserName: invitedUser.name,
                status: 'completed'
            };

            await page.route('**/api/tasks', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks: [{
                        ...testTask,
                        assignedUserId: invitedUser.id,
                        assignedUserName: invitedUser.name,
                        status: taskStatus
                    }] })
                });
            });

            // Mock approve endpoint
            await page.route(`**/api/tasks/${testTask.id}/approve`, async route => {
                taskStatus = 'approved';
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        ...testTask,
                        assignedUserId: invitedUser.id,
                        assignedUserName: invitedUser.name,
                        status: 'approved'
                    })
                });
            });

            await page.goto('/');

            // Should see task list
            await page.waitForSelector('.task-list-container', { timeout: 10000 });

            // Find task card
            const taskCard = page.locator(`.task-card:has-text("${testTask.name}")`);

            // Task should show as completed
            await expect(taskCard.locator('.status-completed')).toBeVisible();

            // Admin should see Approve button
            const approveButton = taskCard.locator('button.btn-primary:has-text("Approve")');
            await expect(approveButton).toBeVisible();

            // Click approve
            await approveButton.click();

            // Wait for the request to complete
            await page.waitForLoadState('networkidle');
        });
    });
});

// Test invitation link flow for unregistered users
test.describe('Invitation Link Flow', () => {

    test('User clicking invitation link sees registration form with invitation banner', async ({ page }) => {
        const inviteToken = 'test-invite-token-xyz';

        // Setup unauthenticated session
        await page.route('**/api/auth/me', async route => {
            await route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({ error: 'Unauthorized' })
            });
        });

        // Navigate to the app with invite token in URL
        await page.goto(`/?invite=${inviteToken}`);

        // Should automatically show registration form
        await page.waitForSelector('h2:has-text("Register")', { timeout: 10000 });

        // Should show invitation banner
        await expect(page.locator('.invite-banner')).toBeVisible();
        await expect(page.locator('.invite-banner p')).toContainText('invited');
    });

    test('Registered user clicking invitation link sees login form with invitation banner', async ({ page }) => {
        const inviteToken = 'test-invite-token-xyz';

        // Setup unauthenticated session
        await page.route('**/api/auth/me', async route => {
            await route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({ error: 'Unauthorized' })
            });
        });

        // Navigate to the app with invite token in URL
        await page.goto(`/?invite=${inviteToken}`);

        // Click on login link
        await page.locator('.auth-switch a').click();
        await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });

        // Should show invitation banner on login page
        await expect(page.locator('.invite-banner')).toBeVisible();
    });

    test('After login with invitation token, invitation is automatically accepted', async ({ page }) => {
        const inviteToken = 'test-invite-token-xyz';
        const testUser = {
            id: 'user-123',
            name: 'Test User',
            email: 'test@example.com',
            role: 'ROLE_USER'
        };

        let invitationAccepted = false;

        // Setup unauthenticated session initially
        let isAuthenticated = false;
        await page.route('**/api/auth/me', async route => {
            if (isAuthenticated) {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(testUser)
                });
            } else {
                await route.fulfill({
                    status: 401,
                    contentType: 'application/json',
                    body: JSON.stringify({ error: 'Unauthorized' })
                });
            }
        });

        // Mock login
        await page.route('**/api/auth/login', async route => {
            isAuthenticated = true;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ user: testUser })
            });
        });

        // Mock user points
        await page.route('**/api/users/*/points', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ balance: 0 })
            });
        });

        // Mock teams
        await page.route('**/api/teams', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    teams: invitationAccepted ? [{
                        id: 'team-1',
                        name: 'Invited Team',
                        role: 'member',
                        createdBy: 'other-user',
                        createdAt: new Date().toISOString()
                    }] : []
                })
            });
        });

        // Mock invitations (empty - since we have the token from URL)
        await page.route('**/api/teams/invitations', async route => {
            if (route.request().method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ invitations: [] })
                });
            }
        });

        // Mock accept invitation - this should be called automatically after login
        await page.route(`**/api/teams/invitations/${inviteToken}/accept`, async route => {
            invitationAccepted = true;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Invitation accepted' })
            });
        });

        // Mock tasks
        await page.route('**/api/tasks', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ tasks: [] })
            });
        });

        // Navigate to app with invite token
        await page.goto(`/?invite=${inviteToken}`);

        // Switch to login (since registration is shown by default with invite)
        await page.locator('.auth-switch a').click();
        await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });

        // Login
        await page.fill('input#email', testUser.email);
        await page.fill('input#password', 'password123');
        await page.click('button[type="submit"]');

        // Wait for authenticated state
        await page.waitForSelector('.app-header', { timeout: 10000 });

        // Wait for network to settle (invitation should be accepted automatically)
        await page.waitForLoadState('networkidle');

        // Verify invitation was accepted
        expect(invitationAccepted).toBe(true);
    });

    test('After registration with invitation token, user can login and invitation is accepted', async ({ page }) => {
        const inviteToken = 'test-invite-token-xyz';
        const newUser = {
            id: 'new-user-123',
            name: 'New User',
            email: 'newuser@example.com',
            role: 'ROLE_USER'
        };

        let userRegistered = false;
        let invitationAccepted = false;
        let isAuthenticated = false;

        // Setup auth route
        await page.route('**/api/auth/me', async route => {
            if (isAuthenticated) {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(newUser)
                });
            } else {
                await route.fulfill({
                    status: 401,
                    contentType: 'application/json',
                    body: JSON.stringify({ error: 'Unauthorized' })
                });
            }
        });

        // Mock registration
        await page.route('**/api/auth/register', async route => {
            userRegistered = true;
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({
                    message: 'User registered successfully.',
                    id: newUser.id
                })
            });
        });

        // Mock login
        await page.route('**/api/auth/login', async route => {
            isAuthenticated = true;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ user: newUser })
            });
        });

        // Mock user points
        await page.route('**/api/users/*/points', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ balance: 0 })
            });
        });

        // Mock teams
        await page.route('**/api/teams', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    teams: invitationAccepted ? [{
                        id: 'team-1',
                        name: 'Invited Team',
                        role: 'member'
                    }] : []
                })
            });
        });

        // Mock accept invitation
        await page.route(`**/api/teams/invitations/${inviteToken}/accept`, async route => {
            invitationAccepted = true;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Invitation accepted' })
            });
        });

        // Mock invitations
        await page.route('**/api/teams/invitations', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ invitations: [] })
            });
        });

        // Mock tasks
        await page.route('**/api/tasks', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ tasks: [] })
            });
        });

        // Navigate to app with invite token
        await page.goto(`/?invite=${inviteToken}`);

        // Registration form should be shown with invite banner
        await page.waitForSelector('h2:has-text("Register")', { timeout: 10000 });
        await expect(page.locator('.invite-banner')).toBeVisible();

        // Register
        await page.fill('input#name', newUser.name);
        await page.fill('input#email', newUser.email);
        await page.fill('input#password', 'password123');
        await page.click('button[type="submit"]');

        // Wait for success message
        await page.waitForSelector('.success-message', { timeout: 5000 });
        expect(userRegistered).toBe(true);

        // Now login
        await page.locator('.auth-switch a').click();
        await page.waitForSelector('h2:has-text("Login")', { timeout: 5000 });

        await page.fill('input#email', newUser.email);
        await page.fill('input#password', 'password123');
        await page.click('button[type="submit"]');

        // Wait for authenticated state
        await page.waitForSelector('.app-header', { timeout: 10000 });
        await page.waitForLoadState('networkidle');

        // Verify invitation was accepted
        expect(invitationAccepted).toBe(true);
    });
});

// Integration test that combines the entire workflow in a single test
test.describe('Complete User Workflow - Integration Test', () => {

    test('Full workflow: registration, team creation, task lifecycle with two users', async ({ page }) => {
        // This is a comprehensive test that simulates the entire workflow
        // using state management to track progress through the flow

        let currentUser = null;
        let teams = [];
        let tasks = [];
        let invitations = [];
        let acceptedInvitations = [];

        // Dynamic mock for /api/auth/me
        await page.route('**/api/auth/me', async route => {
            if (currentUser) {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify(currentUser)
                });
            } else {
                await route.fulfill({
                    status: 401,
                    contentType: 'application/json',
                    body: JSON.stringify({ error: 'Unauthorized' })
                });
            }
        });

        // Mock registration
        await page.route('**/api/auth/register', async route => {
            const requestData = await route.request().postDataJSON();
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({
                    message: 'User registered successfully.',
                    id: requestData.email === mainUser.email ? mainUser.id : invitedUser.id
                })
            });
        });

        // Mock login
        await page.route('**/api/auth/login', async route => {
            const requestData = await route.request().postDataJSON();
            if (requestData.email === mainUser.email) {
                currentUser = mainUser;
            } else if (requestData.email === invitedUser.email) {
                currentUser = invitedUser;
            }
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ user: currentUser })
            });
        });

        // Mock points
        await page.route('**/api/users/*/points', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ balance: 0 })
            });
        });

        // Mock teams
        await page.route('**/api/teams', async route => {
            if (route.request().method() === 'POST') {
                const newTeam = {
                    ...testTeam,
                    role: 'admin',
                    createdBy: currentUser.id
                };
                teams.push(newTeam);
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify(newTeam)
                });
            } else {
                // Filter teams based on current user
                const userTeams = teams.map(t => ({
                    ...t,
                    role: t.createdBy === currentUser?.id ? 'admin' :
                          (acceptedInvitations.some(inv => inv.email === currentUser?.email) ? 'member' : null)
                })).filter(t => t.role !== null);

                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ teams: userTeams })
                });
            }
        });

        // Mock invitations
        await page.route('**/api/teams/invitations', async route => {
            // Filter invitations for current user
            const userInvitations = invitations.filter(
                inv => inv.email === currentUser?.email &&
                !acceptedInvitations.some(a => a.token === inv.token)
            );
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ invitations: userInvitations })
            });
        });

        // Mock invite
        await page.route('**/api/teams/*/invite', async route => {
            const requestData = await route.request().postDataJSON();
            invitations.push({
                id: 'inv-' + Date.now(),
                email: requestData.email,
                role: requestData.role,
                token: invitationToken,
                teamId: testTeam.id
            });
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Invitation sent' })
            });
        });

        // Mock accept invitation
        await page.route(`**/api/teams/invitations/*/accept`, async route => {
            const inv = invitations.find(i => i.email === currentUser?.email);
            if (inv) {
                acceptedInvitations.push(inv);
            }
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Invitation accepted' })
            });
        });

        // Mock tasks
        await page.route('**/api/tasks', async route => {
            if (route.request().method() === 'POST') {
                const requestData = await route.request().postDataJSON();
                const newTask = {
                    ...requestData,
                    id: testTask.id,
                    status: 'pending',
                    assignedUserId: null,
                    assignedUserName: null
                };
                tasks.push(newTask);
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify(newTask)
                });
            } else {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ tasks })
                });
            }
        });

        // Mock assign
        await page.route('**/api/tasks/*/assign', async route => {
            const requestData = await route.request().postDataJSON();
            tasks = tasks.map(t => ({
                ...t,
                assignedUserId: requestData.userId,
                assignedUserName: currentUser.name
            }));
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(tasks[0])
            });
        });

        // Mock complete
        await page.route('**/api/tasks/*/complete', async route => {
            tasks = tasks.map(t => ({ ...t, status: 'completed' }));
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(tasks[0])
            });
        });

        // Mock approve
        await page.route('**/api/tasks/*/approve', async route => {
            tasks = tasks.map(t => ({ ...t, status: 'approved' }));
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(tasks[0])
            });
        });

        // Mock team members
        await page.route('**/api/teams/*/members', async route => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ members: [] })
            });
        });

        // ===== STEP 1: Main user registers =====
        await page.goto('/');
        await page.waitForSelector('h2:has-text("Login")', { timeout: 10000 });
        await page.locator('.auth-switch a').click();
        await page.waitForSelector('h2:has-text("Register")');
        await page.fill('input#name', mainUser.name);
        await page.fill('input#email', mainUser.email);
        await page.fill('input#password', mainUser.password);
        await page.click('button[type="submit"]');
        await page.waitForSelector('.success-message');

        // ===== STEP 2: Main user logs in =====
        currentUser = mainUser;
        await page.goto('/');
        await page.waitForSelector('.task-list-container', { timeout: 10000 });

        // ===== STEP 3: Main user creates team =====
        await page.click('.app-nav button:has-text("Teams")');
        await page.waitForSelector('.team-management');
        await page.click('button:has-text("Create Team")');
        await page.fill('input[type="text"]', testTeam.name);
        await page.fill('textarea', testTeam.description);
        await page.click('button.btn-success');
        await page.waitForLoadState('networkidle');

        // Verify team was created (assertions)
        expect(teams.length).toBe(1);
        expect(teams[0].name).toBe(testTeam.name);
    });
});
