<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserManagement\Domain\Event\UserCreated;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        
        #[ORM\Column(type: 'email', unique: true)]
        private Email $email,
        
        #[ORM\Column(type: 'string', length: 255)]
        private string $password,
        
        #[ORM\Column(type: 'role')]
        private Role $role,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null,
        
        #[ORM\Column(type: 'string', length: 20, nullable: true)]
        private ?string $phoneNumber = null,
        
        #[ORM\Column(type: 'boolean')]
        private bool $isActive = true,
        
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $activationToken = null
    ) {
    }

    public static function create(
        Uuid $id,
        string $name,
        Email $email,
        string $hashedPassword,
        Role $role
    ): self {
        $user = new self(
            $id,
            $name,
            $email,
            $hashedPassword,
            $role,
            new DateTimeImmutable(),
            null,
            null,
            true
        );

        $user->record(new UserCreated($id, $email, $role, new DateTimeImmutable()));

        return $user;
    }

    public static function register(
        Uuid $id,
        string $name,
        Email $email,
        string $hashedPassword,
        ?string $phoneNumber = null
    ): self {
        $activationToken = bin2hex(random_bytes(32));
        
        $user = new self(
            $id,
            $name,
            $email,
            $hashedPassword,
            Role::USER,
            new DateTimeImmutable(),
            null,
            $phoneNumber,
            false,
            $activationToken
        );

        $user->record(new \App\UserManagement\Domain\Event\UserRegistered(
            $id, 
            $email, 
            $name,
            $activationToken, 
            new DateTimeImmutable()
        ));

        return $user;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function promoteToAdmin(): void
    {
        $this->role = Role::ADMIN;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activationToken(): ?string
    {
        return $this->activationToken;
    }

    public function activate(string $token): void
    {
        if ($this->activationToken !== $token) {
            throw new \DomainException('Invalid activation token');
        }

        $this->isActive = true;
        $this->activationToken = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    // Symfony Security UserInterface methods
    public function getUserIdentifier(): string
    {
        return $this->email->value();
    }

    public function getRoles(): array
    {
        return [$this->role->value];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase - we don't store plain passwords
    }
}
