<?php

namespace App\Service\Security\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserSecurity
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function disableUser(User $user): void
    {
        $user->setEnabled(false);
        $user->removeRole(User::ROLE_USER);

        $this->entityManager->flush();
    }

    public function emptyTokenStorage(): void
    {
        $this->tokenStorage->setToken();
    }

    public function getCurrentUser(): User|UserInterface|null
    {
        return $this->security->getUser();
    }
}
