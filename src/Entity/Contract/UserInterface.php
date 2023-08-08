<?php

namespace App\Entity\Contract;

use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

interface UserInterface extends SymfonyUserInterface
{
    public function isEnabled(): bool;
}
