<?php

declare(strict_types=1);

namespace Tools\Castor\Enum;

enum ProjectFolder: string
{
    case APP = 'app';
    case TOOLS = 'tools';
    case ALL = 'all';

    public function getPath(): string
    {
        return match ($this) {
            self::APP => '/var/www/project',
            self::TOOLS => '/var/www/tools',
            self::ALL => throw new \LogicException('ALL is not a single path'),
        };
    }

    public function isAll(): bool
    {
        return $this === self::ALL;
    }

    /**
     * @return self[]
     */
    public static function getInstallableFolders(self $folder): array
    {
        if ($folder->isAll()) {
            return [self::APP, self::TOOLS];
        }

        return [$folder];
    }
}
