<?php

declare(strict_types=1);

namespace App\Entity\Utils;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait HasEnabledTrait
{
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $enabled = true;

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
