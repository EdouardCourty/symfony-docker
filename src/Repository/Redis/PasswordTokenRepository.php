<?php

namespace App\Repository\Redis;

use App\Entity\User;
use App\Register\PasswordTokenRegister;
use RedisException;
use Symfony\Component\Uid\Uuid;

class PasswordTokenRepository
{
    public function __construct(
        private readonly PasswordTokenRegister $passwordTokenRegister
    ) {
    }

    /**
     * @throws RedisException
     */
    public function setToken(User $user, string $token, int $ttl): void
    {
        $this->passwordTokenRegister->set($token, $user->getId()->toRfc4122(), $ttl);
    }

    /**
     * @throws RedisException
     */
    public function getUserUuid(string $token): ?Uuid
    {
        $userUuid = $this->passwordTokenRegister->get($token);

        return $userUuid ? new Uuid($userUuid) : null;
    }

    /**
     * @throws RedisException
     */
    public function deleteToken(string $token): void
    {
        $this->passwordTokenRegister->delete($token);
    }
}
