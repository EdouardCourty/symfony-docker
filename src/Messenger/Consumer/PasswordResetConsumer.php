<?php

namespace App\Messenger\Consumer;

use App\Entity\User;
use App\Messenger\Message\PasswordResetMessage;
use App\Service\Customer\PasswordReset;
use App\Service\Messenger\Consumer;
use Doctrine\ORM\EntityManagerInterface;

class PasswordResetConsumer extends Consumer
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly PasswordReset $userPasswordDirector
    ) {
        parent::__construct($entityManager);
    }

    public function __invoke(PasswordResetMessage $passwordResetMessage): void
    {
        $user = $this->getById(User::class, $passwordResetMessage->userId, true);

        $this->userPasswordDirector->resetPassword($user);
    }
}
