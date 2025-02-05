<?php

declare(strict_types=1);

namespace App\Entity\Utils;

use Doctrine\ORM\Mapping as ORM;

trait HasIdTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
