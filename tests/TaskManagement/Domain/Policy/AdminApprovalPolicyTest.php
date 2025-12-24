<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain\Policy;

use App\TaskManagement\Domain\Policy\AdminApprovalPolicy;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test for AdminApprovalPolicy
 */
class AdminApprovalPolicyTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private AdminApprovalPolicy $policy;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->policy = new AdminApprovalPolicy($this->userRepository);
    }

    public function testAdminCanApprove(): void
    {
        // Given
        $admin = UserMother::admin();
        $this->userRepository->save($admin);

        // When
        $canApprove = $this->policy->canApprove($admin->id());

        // Then
        $this->assertTrue($canApprove);
    }

    public function testRegularUserCannotApprove(): void
    {
        // Given
        $user = UserMother::regularUser();
        $this->userRepository->save($user);

        // When
        $canApprove = $this->policy->canApprove($user->id());

        // Then
        $this->assertFalse($canApprove);
    }

    public function testNonExistentUserCannotApprove(): void
    {
        // Given
        $nonExistentUserId = UuidMother::random();

        // When
        $canApprove = $this->policy->canApprove($nonExistentUserId);

        // Then
        $this->assertFalse($canApprove);
    }
}
