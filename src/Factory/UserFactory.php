<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param array<string> $roles
     */
    public function create(string $username, string $password, array $roles = [User::ROLE_DEFAULT]): User
    {
        $user = new User();

        $roles = [...$roles, User::ROLE_DEFAULT];

        $password = $this->passwordHasher->hashPassword($user, $password);

        $user
            ->setUsername($username)
            ->setPassword($password)
            ->setRoles(array_unique($roles));

        return $user;
    }
}
