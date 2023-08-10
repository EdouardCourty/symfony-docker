<?php

namespace App\Service\Cache;

use Redis;

final class CacheSingleton
{
    private ?Redis $instance = null;

    public function __construct(
        private readonly string $host,
        private readonly string $password,
        private readonly int $port,
    ) {
    }

    final public function getInstance(): Redis
    {
        if ($this->instance === null) {
            $this->instance = $this->buildInstance();
        }

        return $this->instance;
    }

    private function buildInstance(): Redis
    {
        return new Redis([
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->password
        ]);
    }
}
