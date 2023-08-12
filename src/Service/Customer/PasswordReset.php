<?php

namespace App\Service\Customer;

use App\Entity\User;
use App\Factory\Entity\UserFactory;
use App\Messenger\Message\PasswordResetMessage;
use App\Repository\Doctrine\UserRepository;
use App\Repository\Redis\PasswordTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PasswordReset
{
    private const TOKEN_TTL = 300; // 5 minutes

    public function __construct(
        private readonly PasswordTokenRepository $passwordTokenRepository,
        private readonly UserRepository $userRepository,
        private readonly UserFactory $userFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function dispatchPasswordReset(User $user): void
    {
        $message = new PasswordResetMessage();
        $message->userId = $user->getId()->toRfc4122();

        $this->messageBus->dispatch($message);
    }

    public function resetPassword(User $user): void
    {
        $token = $this->generateToken();
        $this->passwordTokenRepository->setToken($user, $token, self::TOKEN_TTL);

        $this->sendPasswordResetEmail($user, $token);
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {

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
