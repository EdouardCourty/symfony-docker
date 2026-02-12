<?php

declare(strict_types=1);

namespace Tools\Castor\Enum;

enum Service: string
{
    case DATABASE = 'database';
    case SERVER = 'server';
    case PROXY = 'proxy';

    public function getComposeFilePath(): string
    {
        return sprintf('infrastructure/dev/services/%s/%s.yml', $this->value, $this->value);
    }

    public function getServiceName(): string
    {
        return $this->value;
    }

    /**
     * @return array<Service>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @param array<string> $serviceNames
     * @return array<Service>
     */
    public static function fromNames(array $serviceNames): array
    {
        return array_map(
            fn (string $name): Service => self::from($name),
            $serviceNames
        );
    }
}
