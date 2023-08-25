<?php

namespace App\Repository\Redis;

use App\Entity\User;
use App\Register\EmailVerificationTokenRegister;
use RedisException;
use Symfony\Component\Uid\Uuid;

class EmailVerificationTokenRepository
{
    public function __construct(
        private readonly EmailVerificationTokenRegister $emailVerificationTokenRegister
    ) {
    }

    /**
     * @throws RedisException
     */
    public function setToken(User $user, string $token, int $ttl): void
    {
        $this->emailVerificationTokenRegister->set($token, $user->getId()->toRfc4122(), $ttl);
    }

    /**
     * @throws RedisException
     */
    public function getUserUuid(string $token): ?Uuid
    {
        $userUuid = $this->emailVerificationTokenRegister->get($token);

        return $userUuid ? new Uuid($userUuid) : null;
    }

    /**
     * @throws RedisException
     */
    public function deleteToken(string $token): void
    {
        $this->emailVerificationTokenRegister->delete($token);
    }
}
