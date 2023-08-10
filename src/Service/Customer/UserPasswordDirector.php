<?php

namespace App\Service\Customer;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use App\Repository\Doctrine\UserRepository;
use App\Repository\Redis\PasswordTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordDirector
{
    private const TOKEN_TTL = 300; // 5 minutes

    public function __construct(
        private readonly PasswordTokenRepository $passwordTokenRepository,
        private readonly UserRepository $userRepository,
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    public function resetPassword(User $user): string
    {
        $token = $this->generateToken();
        $this->passwordTokenRepository->setToken($user, $token, self::TOKEN_TTL);

        $this->sendPasswordResetEmail($user);

        return $token;
    }

    public function sendPasswordResetEmail(User $user): void
    {
        return;
    }

    public function isPasswordValid(User $user, string $password): bool
    {
        return $this->userPasswordHasher->isPasswordValid($user, $password);
    }

    public function updatePassword(User $user, string $password): void
    {
        $this->userFactory->updatePassword($user, $password);
        $this->entityManager->flush();
    }

    public function invalidateToken(User $user): void
    {
        $this->passwordTokenRepository->deleteToken($user);
    }

    public function retrieveUser(string $token): ?User
    {
        $userUuid = $this->passwordTokenRepository->getUserUuid($token);

        return $userUuid
            ? $this->userRepository->find($userUuid)
            : null;
    }

    private function generateToken(): string
    {
        return md5(uniqid() . '_' . microtime());
    }
}
