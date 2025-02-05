<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Utils\HasTimestampTrait;
use App\Entity\Utils\HasUuidTrait;
use App\Exception\InvalidRoleException;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements PasswordAuthenticatedUserInterface
{
    use HasTimestampTrait;
    use HasUuidTrait;

    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    public const string ROLE_DEFAULT = self::ROLE_USER;

    public const array ROLES = [
        self::ROLE_USER,
        self::ROLE_ADMIN,
    ];

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $username;

    /** @var array<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    public function __toString(): string
    {
        return $this->getUserIdentifier();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_DEFAULT;

        return array_unique($roles);
    }

    public function addRole(string $roleString): self
    {
        if (\in_array($roleString, self::ROLES, true)) {
            $this->roles[] = $roleString;

            return $this;
        }

        throw InvalidRoleException::createFromUserAndRole($this, $roleString);
    }

    public function hasRole(string $wantedRole): bool
    {
        return \in_array($wantedRole, $this->roles, true);
    }

    public function removeRole(string $roleString): self
    {
        $this->roles = array_filter($this->roles, static function ($value) use ($roleString) {
            return $value !== $roleString;
        });

        return $this;
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
