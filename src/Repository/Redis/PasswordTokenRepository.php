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
        $cacheKey = $this->passwordTokenRegister->getTokenKey($user);

        $this->passwordTokenRegister->set($cacheKey, $token, $ttl);
        $this->passwordTokenRegister->set($token, $user->getId()->toRfc4122(), $ttl);
    }

    /**
     * @throws RedisException
     */
    public function getToken(User $user): ?string
    {
        $cacheKey = $this->passwordTokenRegister->getTokenKey($user);

        return $this->passwordTokenRegister->get($cacheKey);
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
    public function deleteToken(User $user): void
    {
        $cacheKey = $this->passwordTokenRegister->getTokenKey($user);
        $token = $this->passwordTokenRegister->get($cacheKey);

        $this->passwordTokenRegister->delete($cacheKey);
        $this->passwordTokenRegister->delete($token);
    }
}
