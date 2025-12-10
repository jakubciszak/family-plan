<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Mother;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\Tests\Shared\Mother\UuidMother;

final class UserMother
{
    private ?Uuid $id = null;
    private ?string $name = null;
    private ?Email $email = null;
    private ?string $password = null;
    private ?Role $role = null;
    private bool $clearEvents = false;

    private function __construct()
    {
    }

    public static function aUser(): self
    {
        return new self();
    }

    public function withId(Uuid $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withEmail(Email $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function withPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function withRole(Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function asAdmin(): self
    {
        $this->role = RoleMother::admin();
        return $this;
    }

    public function asRegularUser(): self
    {
        $this->role = RoleMother::user();
        return $this;
    }

    public function withoutEvents(): self
    {
        $this->clearEvents = true;
        return $this;
    }

    public function build(): User
    {
        $user = User::create(
            $this->id ?? UuidMother::random(),
            $this->name ?? 'John Doe',
            $this->email ?? EmailMother::user(),
            $this->password ?? self::defaultHashedPassword(),
            $this->role ?? RoleMother::user()
        );

        if ($this->clearEvents) {
            $user->pullDomainEvents();
        }

        return $user;
    }

    // Convenience factory methods for backward compatibility
    public static function create(
        ?Uuid $id = null,
        ?string $name = null,
        ?Email $email = null,
        ?string $password = null,
        ?Role $role = null
    ): User {
        $builder = self::aUser();
        
        if ($id !== null) $builder->withId($id);
        if ($name !== null) $builder->withName($name);
        if ($email !== null) $builder->withEmail($email);
        if ($password !== null) $builder->withPassword($password);
        if ($role !== null) $builder->withRole($role);
        
        return $builder->build();
    }

    public static function admin(): User
    {
        return self::aUser()
            ->withName('Admin User')
            ->withEmail(EmailMother::admin())
            ->asAdmin()
            ->build();
    }

    public static function regularUser(): User
    {
        return self::aUser()
            ->withName('Regular User')
            ->withEmail(EmailMother::user())
            ->asRegularUser()
            ->build();
    }

    public static function random(): User
    {
        return self::aUser()
            ->withEmail(EmailMother::random())
            ->withRole(RoleMother::random())
            ->build();
    }

    private static function defaultHashedPassword(): string
    {
        return password_hash('password123', PASSWORD_BCRYPT);
    }
}
