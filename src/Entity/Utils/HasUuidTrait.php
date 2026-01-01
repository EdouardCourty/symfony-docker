<?php

declare(strict_types=1);

namespace App\Entity\Utils;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

trait HasUuidTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected Uuid $id;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIdString(): string
    {
        return $this->id->toRfc4122();
    }

    public function setId(Uuid|string $id): self
    {
        if (\is_string($id)) {
            $id = Uuid::fromString($id);
        }

        $this->id = $id;

        return $this;
    }
}
