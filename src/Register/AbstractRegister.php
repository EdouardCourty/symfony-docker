<?php

namespace App\Register;

use App\Service\Cache\CacheSingleton;
use Redis;
use RedisException;

abstract class AbstractRegister
{
    public function __construct(
        private readonly CacheSingleton $cacheSingleton
    ) {
    }

    private function getInstance(): Redis
    {
        return $this->cacheSingleton->getInstance();
    }

    protected abstract function getTag(): string;

    private function getCacheKey(string $key): string
    {
        return $this->getTag() . '_' . $key;
    }

    /**
     * @throws RedisException
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if ($ttl !== null) {
            $this->getInstance()->setex($this->getCacheKey($key), $ttl, $value);
            return;
        }

        $this->getInstance()->set($key, $value);
    }

    /**
     * @throws RedisException
     */
    public function get(string $key): mixed
    {
        return $this->getInstance()->get($this->getCacheKey($key));
    }

    /**
     * @throws RedisException
     */
    public function delete(string $key): void
    {
        $this->getInstance()->del($this->getCacheKey($key));
    }
}
