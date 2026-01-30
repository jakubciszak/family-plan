<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\AssignTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Domain\ValueObject\Email;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;

/**
 * Context for task management scenarios
 * Handles behavioral scenarios for task lifecycle
 */
final class TaskManagementContext extends AcceptanceContext
{
    private array $users = [];
    private array $tasks = [];
    private ?\Throwable $lastException = null;
    private ?Uuid $currentAdminId = null;

    #[BeforeScenario]
    public function resetState(BeforeScenarioScope $scope): void
    {
        $this->reset();
        $this->users = [];
        $this->tasks = [];
        $this->lastException = null;
        $this->currentAdminId = null;
    }

    #[Given('there is an administrator :name')]
    public function thereIsAnAdministrator(string $name): void
    {
        $userId = Uuid::generate();
        $email = Email::fromString(strtolower(str_replace(' ', '.', $name)) . '@family.com');

        $admin = UserMother::aUser()
            ->withId($userId)
            ->withName($name)
            ->withEmail($email)
            ->asAdmin()
            ->build();

        $this->userRepository->save($admin);
        $this->users[$name] = $userId;
        $this->currentAdminId = $userId;

        $person = Person::create($userId, $name, $email);
        $relationship = PartyRelationship::create(
            Uuid::generate(),
            $person,
            $this->teamOrganization,
            PartyRelationshipType::adminOf()
        );
        $this->partyRelationshipRepository->save($relationship);
    }

    #[Given('there is a family member :name')]
    public function thereIsAFamilyMember(string $name): void
    {
        $userId = Uuid::generate();
        $email = Email::fromString(strtolower(str_replace(' ', '.', $name)) . '@family.com');

        $user = UserMother::aUser()
            ->withId($userId)
            ->withName($name)
            ->withEmail($email)
            ->asRegularUser()
            ->build();

        $this->userRepository->save($user);
        $this->users[$name] = $userId;
    }

    #[Given(':admin creates a task :taskName worth :points points')]
    public function createsATaskWorthPoints(string $admin, string $taskName, int $points): void
    {
        $taskId = Uuid::generate();
        $adminId = $this->users[$admin];

        $command = new CreateTaskCommand(
            $taskId->value(),
            $taskName,
            "Task: {$taskName}",
            $points,
            'daily',
            $adminId->value(),
            $this->teamId->value(),
            null
        );

        ($this->createTaskHandler)($command);
        $this->tasks[$taskName] = $taskId;
    }

    #[When(':admin assigns task :taskName to :user')]
    public function assignsTaskTo(string $admin, string $taskName, string $user): void
    {
        $taskId = $this->tasks[$taskName];
        $userId = $this->users[$user];

        $command = new AssignTaskCommand(
            $taskId->value(),
            $userId->value()
        );

        ($this->assignTaskHandler)($command);
    }

    #[When(':user completes task :taskName')]
    public function completesTask(string $user, string $taskName): void
    {
        $taskId = $this->tasks[$taskName];
        $userId = $this->users[$user];

        try {
            $command = new CompleteTaskCommand(
                $taskId->value(),
                $userId->value()
            );

            ($this->completeTaskHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    #[When(':admin approves task :taskName')]
    public function approvesTask(string $admin, string $taskName): void
    {
        $taskId = $this->tasks[$taskName];
        $adminId = $this->users[$admin];

        try {
            $command = new ApproveTaskCommand(
                $taskId->value(),
                $adminId->value()
            );

            ($this->approveTaskHandler)($command);
        } catch (\Throwable $e) {
            $this->lastException = $e;
        }
    }

    #[Then('task :taskName should have status :status')]
    public function taskShouldHaveStatus(string $taskName, string $status): void
    {
        $taskId = $this->tasks[$taskName];
        $task = $this->taskRepository->findById($taskId);

        Assert::assertNotNull($task, "Task {$taskName} not found");
        Assert::assertEquals(
            strtolower($status),
            $task->status()->value,
            "Task {$taskName} should have status {$status}"
        );
    }

    #[Then(':user should have :points points')]
    public function shouldHavePoints(string $user, int $points): void
    {
        $userId = $this->users[$user];
        $wallet = $this->walletRepository->findByUserId($userId);

        Assert::assertNotNull($wallet, "Wallet for {$user} not found");
        Assert::assertEquals(
            $points,
            $wallet->balance()->value(),
            "{$user} should have {$points} points"
        );
    }

    #[Then(':user should have no wallet yet')]
    public function shouldHaveNoWalletYet(string $user): void
    {
        $userId = $this->users[$user];
        $wallet = $this->walletRepository->findByUserId($userId);

        Assert::assertNull($wallet, "{$user} should not have a wallet yet");
    }

    #[Given('it is :dateTime')]
    public function itIs(string $dateTime): void
    {
        $this->setCurrentTime($dateTime);
    }

    #[When(':days day(s) pass(es)')]
    public function daysPass(int $days): void
    {
        $this->advanceTimeByDays($days);
    }

    #[Then('the operation should fail with :message')]
    public function theOperationShouldFailWith(string $message): void
    {
        Assert::assertNotNull($this->lastException, 'Expected an exception but none was thrown');
        Assert::assertStringContainsString(
            $message,
            $this->lastException->getMessage(),
            'Exception message does not match expected'
        );
    }

    #[Given('there are the following family members:')]
    public function theFollowingFamilyMembersExist(TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            $this->thereIsAFamilyMember($row['name']);
        }
    }

    #[When('the following tasks have been created:')]
    public function theFollowingTasksAreCreated(TableNode $table): void
    {
        Assert::assertNotNull($this->currentAdminId, 'Expected an administrator to create tasks');

        foreach ($table->getHash() as $row) {
            $taskId = Uuid::generate();
            $command = new CreateTaskCommand(
                $taskId->value(),
                $row['task'],
                $row['description'] ?? "Task: {$row['task']}",
                (int) $row['points'],
                'daily',
                $this->currentAdminId->value(),
                $this->teamId->value(),
                null
            );

            ($this->createTaskHandler)($command);
            $this->tasks[$row['task']] = $taskId;
        }
    }
}
