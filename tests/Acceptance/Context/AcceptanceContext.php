<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Context;

use App\Party\Domain\Entity\Organization;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Handler\ActivateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\CreateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\DeactivateBonusPointsRuleHandler;
use App\TaskManagement\Application\BonusRule\Handler\GetAllBonusPointsRulesQueryHandler;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\AssignTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\ActivateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\CreateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\DeactivateStatusChangeRuleHandler;
use App\TaskManagement\Application\StatusChangeRule\Handler\GetAllStatusChangeRulesQueryHandler;
use App\TaskManagement\Domain\Policy\AdminApprovalPolicy;
use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\TaskManagement\Infrastructure\Persistence\InMemoryBonusPointsRuleRepository;
use App\TaskManagement\Infrastructure\Persistence\InMemoryStatusChangeRuleRepository;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use Behat\Behat\Context\Context;
use DateTimeImmutable;

/**
 * Base context for acceptance tests
 * Provides common infrastructure and dependencies for behavioral tests
 */
abstract class AcceptanceContext implements Context
{
    protected InMemoryUserRepository $userRepository;
    protected InMemoryTaskRepository $taskRepository;
    protected InMemoryUserWalletRepository $walletRepository;
    protected InMemoryBonusPointsRuleRepository $bonusPointsRuleRepository;
    protected InMemoryStatusChangeRuleRepository $statusChangeRuleRepository;
    protected InMemoryPartyRelationshipRepository $partyRelationshipRepository;
    protected FixedClock $clock;
    protected Uuid $teamId;
    protected Organization $teamOrganization;

    // Task Management Handlers
    protected CreateTaskHandler $createTaskHandler;
    protected AssignTaskHandler $assignTaskHandler;
    protected CompleteTaskHandler $completeTaskHandler;
    protected ApproveTaskHandler $approveTaskHandler;

    // Bonus Points Handlers
    protected CreateBonusPointsRuleHandler $createBonusPointsRuleHandler;
    protected ActivateBonusPointsRuleHandler $activateBonusPointsRuleHandler;
    protected DeactivateBonusPointsRuleHandler $deactivateBonusPointsRuleHandler;
    protected GetAllBonusPointsRulesQueryHandler $getAllBonusPointsRulesQueryHandler;

    // Status Change Rule Handlers
    protected CreateStatusChangeRuleHandler $createStatusChangeRuleHandler;
    protected ActivateStatusChangeRuleHandler $activateStatusChangeRuleHandler;
    protected DeactivateStatusChangeRuleHandler $deactivateStatusChangeRuleHandler;
    protected GetAllStatusChangeRulesQueryHandler $getAllStatusChangeRulesQueryHandler;

    public function __construct()
    {
        $this->initializeRepositories();
        $this->initializeClock();
        $this->initializeHandlers();
    }

    private function initializeRepositories(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->taskRepository = new InMemoryTaskRepository();
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->bonusPointsRuleRepository = new InMemoryBonusPointsRuleRepository();
        $this->statusChangeRuleRepository = new InMemoryStatusChangeRuleRepository();
        $this->partyRelationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->teamId = Uuid::generate();
        $this->teamOrganization = Organization::create($this->teamId, 'Test Family', null);
    }

    private function initializeClock(): void
    {
        // Default to a fixed time for consistent test behavior
        $this->clock = new FixedClock(new DateTimeImmutable('2025-01-01 08:00:00'));
    }

    private function initializeHandlers(): void
    {
        // Task Management
        $this->createTaskHandler = new CreateTaskHandler(
            $this->taskRepository,
            $this->partyRelationshipRepository
        );
        $this->assignTaskHandler = new AssignTaskHandler($this->taskRepository);
        $this->completeTaskHandler = new CompleteTaskHandler($this->taskRepository);

        $approvalPolicy = new AdminApprovalPolicy($this->userRepository);
        $pointsAwardStrategy = new TaskApprovalPointsAwardStrategy($this->walletRepository, $this->clock);
        $this->approveTaskHandler = new ApproveTaskHandler(
            $this->taskRepository,
            $approvalPolicy,
            $pointsAwardStrategy
        );

        // Bonus Points
        $this->createBonusPointsRuleHandler = new CreateBonusPointsRuleHandler($this->bonusPointsRuleRepository);
        $this->activateBonusPointsRuleHandler = new ActivateBonusPointsRuleHandler($this->bonusPointsRuleRepository);
        $this->deactivateBonusPointsRuleHandler = new DeactivateBonusPointsRuleHandler($this->bonusPointsRuleRepository);
        $this->getAllBonusPointsRulesQueryHandler = new GetAllBonusPointsRulesQueryHandler($this->bonusPointsRuleRepository);

        // Status Change Rules
        $this->createStatusChangeRuleHandler = new CreateStatusChangeRuleHandler($this->statusChangeRuleRepository);
        $this->activateStatusChangeRuleHandler = new ActivateStatusChangeRuleHandler($this->statusChangeRuleRepository);
        $this->deactivateStatusChangeRuleHandler = new DeactivateStatusChangeRuleHandler($this->statusChangeRuleRepository);
        $this->getAllStatusChangeRulesQueryHandler = new GetAllStatusChangeRulesQueryHandler($this->statusChangeRuleRepository);
    }

    /**
     * Set the current time for time-based scenarios
     */
    protected function setCurrentTime(string $dateTime): void
    {
        $this->clock->setTime(new DateTimeImmutable($dateTime));
    }

    /**
     * Advance time by a number of days
     */
    protected function advanceTimeByDays(int $days): void
    {
        $newTime = $this->clock->now()->modify("+{$days} days");
        $this->clock->setTime($newTime);
    }

    /**
     * Reset all repositories and clock to initial state
     */
    protected function reset(): void
    {
        $this->initializeRepositories();
        $this->initializeClock();
        $this->initializeHandlers();
    }
}
